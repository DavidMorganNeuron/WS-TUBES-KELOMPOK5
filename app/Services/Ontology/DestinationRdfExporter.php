<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use App\Data\DestinationRdfDocument;
use DOMDocument;
use Illuminate\Support\Facades\Cache;
use JsonException;
use RuntimeException;

final class DestinationRdfExporter
{
    private const NEIGHBOR_PREDICATES = [
        OntologyMigrator::RDF_NS.'type',
        OntologyMigrator::WS_NS.'idDestinasi',
        OntologyMigrator::WS_NS.'namaDestinasi',
    ];

    public function __construct(
        private readonly FusekiClient $client,
        private readonly DestinationRdfQueryBuilder $queryBuilder,
        private readonly SparqlEscaper $escaper,
        private readonly TurtleParser $parser,
        private readonly TurtleWriter $writer,
        private readonly OntologyDataVersion $dataVersion,
    ) {}

    public function export(string $id, string $format): ?DestinationRdfDocument
    {
        $destinationIri = substr($this->escaper->destinationIri($id), 1, -1);
        $formats = config('ontology.rdf_export.formats');
        $formatConfig = is_array($formats) ? ($formats[$format] ?? null) : null;
        if (! is_array($formatConfig)
            || ! is_string($formatConfig['media_type'] ?? null)
            || ! is_string($formatConfig['extension'] ?? null)) {
            throw new \InvalidArgumentException('Format ekspor RDF tidak didukung.');
        }

        if (config('ontology.driver') === 'ttl' && $format !== 'ttl') {
            throw new RdfFormatUnavailableException('Driver TTL lokal hanya mendukung ekspor Turtle.');
        }

        $version = $this->version();
        $cacheKey = 'ontology.rdf-export.'.hash('sha256', $version.'|'.$id.'|'.$format);
        $body = Cache::get($cacheKey);

        if (! is_string($body) || trim($body) === '') {
            $body = config('ontology.driver') === 'ttl'
                ? $this->localTurtle($destinationIri)
                : $this->client->construct(
                    $this->queryBuilder->constructDepthOne($id),
                    $formatConfig['media_type'],
                );

            if ($body === null || $this->isEmptyGraph($body, $format)) {
                return null;
            }

            if ((int) config('ontology.rdf_export.cache_ttl', 600) > 0) {
                Cache::put($cacheKey, $body, (int) config('ontology.rdf_export.cache_ttl', 600));
            }
        }

        return new DestinationRdfDocument(
            body: $body,
            mediaType: $formatConfig['media_type'],
            extension: $formatConfig['extension'],
            etag: '"'.hash('sha256', $version.'|'.$id.'|'.$format).'"',
            maxAge: (int) config('ontology.rdf_export.cache_ttl', 600),
        );
    }

    private function localTurtle(string $destinationIri): ?string
    {
        $triples = $this->parser->parseTriplesFile((string) config('ontology.ttl_path'));
        $subgraph = $this->depthOneSubgraph($triples, $destinationIri);

        return $subgraph === [] ? null : $this->writer->write($subgraph);
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @return list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>
     */
    private function depthOneSubgraph(array $triples, string $destinationIri): array
    {
        $resourcePredicates = array_map(
            static fn (string $predicate): string => OntologyMigrator::WS_NS.$predicate,
            DestinationRdfQueryBuilder::RESOURCE_PREDICATES,
        );
        $resources = [];
        $neighbors = [];
        $subgraph = [];

        foreach ($triples as $triple) {
            if ($triple['subject'] !== $destinationIri) {
                continue;
            }

            $subgraph[] = $triple;
            if ($triple['object']['kind'] !== 'iri') {
                continue;
            }

            if (in_array($triple['predicate'], $resourcePredicates, true)) {
                $resources[$triple['object']['value']] = true;
            } elseif ($triple['predicate'] === OntologyMigrator::WS_NS.'dekatDengan') {
                $neighbors[$triple['object']['value']] = true;
            }
        }

        foreach ($triples as $triple) {
            if (isset($resources[$triple['subject']])) {
                $subgraph[] = $triple;

                continue;
            }

            if (isset($neighbors[$triple['subject']]) && in_array($triple['predicate'], self::NEIGHBOR_PREDICATES, true)) {
                $subgraph[] = $triple;
            }
        }

        $unique = [];
        foreach ($subgraph as $triple) {
            $key = serialize($triple);
            $unique[$key] = $triple;
        }

        return array_values($unique);
    }

    private function isEmptyGraph(string $body, string $format): bool
    {
        if (trim($body) === '') {
            return true;
        }

        return match ($format) {
            'ttl' => $this->hasTurtleStatements($body) === false,
            'rdf' => $this->hasRdfXmlStatements($body) === false,
            'jsonld' => $this->hasJsonLdStatements($body) === false,
            default => true,
        };
    }

    private function hasTurtleStatements(string $body): bool
    {
        $statements = preg_replace(
            '/^[ \t]*(?:PREFIX|@prefix)[ \t]+\S+:[ \t]*<[^>]+>[ \t]*\.?[ \t]*(?:\r?\n|$)/im',
            '',
            $body,
        );

        return is_string($statements) && trim($statements) !== '';
    }

    private function hasRdfXmlStatements(string $body): bool
    {
        $document = new DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        if (! $document->loadXML($body, LIBXML_NONET) || $document->documentElement === null) {
            throw OntologyUnavailableException::badResponse();
        }

        foreach ($document->documentElement->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                return true;
            }
        }

        return false;
    }

    private function hasJsonLdStatements(string $body): bool
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw OntologyUnavailableException::badResponse($exception);
        }

        if (! is_array($data)) {
            return false;
        }

        if (array_is_list($data)) {
            return $data !== [];
        }

        return isset($data['@id'])
            || (isset($data['@graph']) && is_array($data['@graph']) && $data['@graph'] !== []);
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
