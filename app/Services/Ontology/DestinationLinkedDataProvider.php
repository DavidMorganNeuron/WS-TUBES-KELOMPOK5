<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use App\Data\DestinationLinkedData;
use App\Data\LinkedResource;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class DestinationLinkedDataProvider
{
    private const RESOURCE_TYPES = [
        'category' => ['ws:KategoriWisata', 'rdfs:label'],
        'region' => ['ws:KabupatenKota', 'rdfs:label'],
        'facility' => ['ws:Fasilitas', 'rdfs:label'],
        'activity' => ['ws:Aktivitas', 'rdfs:label'],
        'neighbor' => ['ws:DestinasiWisata', 'ws:namaDestinasi schema:name'],
        'regionNeighbor' => ['ws:KabupatenKota', 'rdfs:label'],
    ];

    public function __construct(
        private readonly FusekiClient $client,
        private readonly DestinationRdfQueryBuilder $queryBuilder,
        private readonly SparqlEscaper $escaper,
        private readonly TurtleParser $parser,
        private readonly OntologyDataVersion $dataVersion,
    ) {}

    public function forDestination(string $id): DestinationLinkedData
    {
        $destinationIri = substr($this->escaper->destinationIri($id), 1, -1);
        $version = $this->version();
        $cacheKey = 'ontology.linked-data-rows.'.hash('sha256', $version.'|'.$id);
        $rows = Cache::remember($cacheKey, (int) config('ontology.cache_ttl', 600), function () use ($id, $destinationIri, $version): array {
            return config('ontology.driver') === 'ttl'
                ? $this->localRows($destinationIri, $version)
                : $this->client->select($this->queryBuilder->linkedDataSelect($id));
        });

        if (! is_array($rows)) {
            throw OntologyUnavailableException::badResponse();
        }

        return $this->fromRows($destinationIri, $rows);
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     */
    private function fromRows(string $destinationIri, array $rows): DestinationLinkedData
    {
        $resources = [
            'category' => [],
            'region' => [],
            'facility' => [],
            'activity' => [],
            'neighbor' => [],
            'regionNeighbor' => [],
        ];

        foreach ($rows as $row) {
            $kind = $row['kind'] ?? null;
            $iri = $row['resource'] ?? null;
            $label = $row['label'] ?? null;
            if (! is_string($kind) || ! isset(self::RESOURCE_TYPES[$kind])
                || ! is_string($iri) || ! $this->isLocalResourceIri($iri)
                || ! is_string($label)) {
                throw OntologyUnavailableException::badResponse();
            }

            $label = trim($label);
            if ($label === '' || $label === '-') {
                continue;
            }

            $destinationId = $row['destinationId'] ?? null;
            if ($destinationId !== null && (! is_string($destinationId) || preg_match('/^ws-\d{3}$/', $destinationId) !== 1)) {
                throw OntologyUnavailableException::badResponse();
            }

            $regionType = $row['regionType'] ?? null;
            if ($regionType === '-') {
                $regionType = null;
            }

            [$type, $labelProperty] = self::RESOURCE_TYPES[$kind];
            $resources[$kind][$iri] = new LinkedResource(
                iri: $iri,
                label: $label,
                type: $type,
                labelProperty: $labelProperty,
                regionType: $kind === 'region' && is_string($regionType) ? $regionType : null,
                destinationId: $kind === 'neighbor' ? $destinationId : null,
            );
        }

        foreach ($resources as &$items) {
            ksort($items, SORT_STRING);
            $items = array_values($items);
        }
        unset($items);

        return new DestinationLinkedData(
            iri: $destinationIri,
            categories: $resources['category'],
            region: $resources['region'][0] ?? null,
            facilities: $resources['facility'],
            activities: $resources['activity'],
            neighbors: $resources['neighbor'],
            regionNeighbors: $resources['regionNeighbor'],
        );
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function localRows(string $destinationIri, string $version): array
    {
        $triplesKey = 'ontology.linked-triples.'.hash('sha256', $version);
        $triples = Cache::remember(
            $triplesKey,
            (int) config('ontology.cache_ttl', 600),
            fn (): array => $this->parser->parseTriplesFile((string) config('ontology.ttl_path')),
        );
        $namespace = (string) config('ontology.namespaces.ws');
        $rdfsLabel = (string) config('ontology.namespaces.rdfs').'label';
        $properties = [];
        $resources = [];

        foreach ($triples as $triple) {
            $properties[$triple['subject']][$triple['predicate']][] = $triple['object']['value'];
            if ($triple['subject'] !== $destinationIri || $triple['object']['kind'] !== 'iri') {
                continue;
            }

            foreach (DestinationRdfQueryBuilder::LINKED_RESOURCE_PREDICATES as $kind => $predicate) {
                if ($triple['predicate'] === $namespace.$predicate) {
                    $resources[$kind][$triple['object']['value']] = true;
                }
            }

            if ($triple['predicate'] === $namespace.'dekatDengan') {
                $resources['neighbor'][$triple['object']['value']] = true;
            }
        }

        foreach (array_keys($resources['region'] ?? []) as $regionIri) {
            foreach ($properties[$regionIri][$namespace.'berbatasanDengan'] ?? [] as $neighborIri) {
                $resources['regionNeighbor'][$neighborIri] = true;
            }
        }

        $rows = [];
        foreach ($resources as $kind => $resourceIris) {
            foreach (array_keys($resourceIris) as $resourceIri) {
                $label = $properties[$resourceIri][$rdfsLabel][0]
                    ?? $properties[$resourceIri][$namespace.'namaDestinasi'][0]
                    ?? null;
                if (! is_string($label)) {
                    continue;
                }

                $regionType = $properties[$resourceIri][$namespace.'tipeWilayah'][0] ?? null;
                $destinationId = $properties[$resourceIri][$namespace.'idDestinasi'][0] ?? null;
                $rows[] = [
                    'kind' => $kind === 'resource' ? 'resource' : $kind,
                    'resource' => $resourceIri,
                    'label' => $label,
                    'regionType' => $regionType,
                    'destinationId' => $destinationId,
                ];
            }
        }

        return $rows;
    }

    private function isLocalResourceIri(string $iri): bool
    {
        $namespace = config('ontology.namespaces.data');
        if (! is_string($namespace) || ! str_starts_with($iri, $namespace)) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9._~-]+$/', substr($iri, strlen($namespace))) === 1;
    }

    private function version(): string
    {
        $version = $this->dataVersion->current() ?? 'initial';
        if (config('ontology.driver') !== 'ttl') {
            return $version;
        }

        $path = (string) config('ontology.ttl_path');
        $resolvedPath = preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);
        if (! is_file($resolvedPath)) {
            throw new RuntimeException("File Turtle ontologi tidak ditemukan: {$resolvedPath}");
        }

        $fingerprint = hash_file('sha256', $resolvedPath);
        if ($fingerprint === false) {
            throw new RuntimeException('File Turtle ontologi tidak dapat diperiksa versinya.');
        }

        return 'ttl-'.$fingerprint.'-'.$version;
    }
}
