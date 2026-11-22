<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use InvalidArgumentException;

final class DestinationRdfQueryBuilder
{
    /**
     * @var array<string, string>
     */
    public const LINKED_RESOURCE_PREDICATES = [
        'category' => 'berkategori',
        'region' => 'berlokasiDi',
        'facility' => 'menyediakanFasilitas',
        'activity' => 'menawarkanAktivitas',
    ];

    /**
     * @var list<string>
     */
    public const RESOURCE_PREDICATES = [
        'berkategori',
        'berlokasiDi',
        'menyediakanFasilitas',
        'menawarkanAktivitas',
        'memilikiMedia',
        'memilikiKontak',
        'memilikiJadwal',
        'memilikiTarif',
    ];

    public function __construct(private readonly SparqlEscaper $escaper) {}

    public function constructDepthOne(string $id): string
    {
        $destination = $this->escaper->destinationIri($id);
        $linkedPredicates = implode(
            ' ',
            array_map(
                static fn (string $predicate): string => 'ws:'.$predicate,
                self::RESOURCE_PREDICATES,
            ),
        );

        return $this->prefixes(['ws', 'rdf', 'rdfs'])."\n".<<<SPARQL
CONSTRUCT {
    ?destination ?destinationPredicate ?destinationObject .
    ?related ?relatedPredicate ?relatedObject .
    ?neighbor rdf:type ws:DestinasiWisata ;
        ws:idDestinasi ?neighborId ;
        ws:namaDestinasi ?neighborName .
}
WHERE {
    VALUES ?destination { {$destination} }
    ?destination ?destinationPredicate ?destinationObject .
    OPTIONAL {
        ?destination ?relatedProperty ?related .
        VALUES ?relatedProperty { {$linkedPredicates} }
        ?related ?relatedPredicate ?relatedObject .
    }
    OPTIONAL {
        ?destination ws:dekatDengan ?neighbor .
        ?neighbor rdf:type ws:DestinasiWisata .
        OPTIONAL { ?neighbor ws:idDestinasi ?neighborId }
        OPTIONAL { ?neighbor ws:namaDestinasi ?neighborName }
    }
}
SPARQL;
    }

    public function linkedDataSelect(string $id): string
    {
        $destination = $this->escaper->destinationIri($id);

        return $this->prefixes(['ws', 'rdf', 'rdfs'])."\n".<<<SPARQL
SELECT DISTINCT ?kind ?resource ?label ?regionType ?destinationId
WHERE {
    VALUES ?destination { {$destination} }
    {
        { ?destination ws:berkategori ?resource . BIND("category" AS ?kind) }
        UNION
        { ?destination ws:berlokasiDi ?resource . BIND("region" AS ?kind) }
        UNION
        { ?destination ws:menyediakanFasilitas ?resource . BIND("facility" AS ?kind) }
        UNION
        { ?destination ws:menawarkanAktivitas ?resource . BIND("activity" AS ?kind) }
        UNION
        { ?destination ws:dekatDengan ?resource . BIND("neighbor" AS ?kind) }
        UNION
        { ?destination ws:berlokasiDi/ws:berbatasanDengan ?resource . BIND("regionNeighbor" AS ?kind) }
    }
    OPTIONAL { ?resource rdfs:label ?resourceLabel }
    OPTIONAL { ?resource ws:namaDestinasi ?destinationName }
    OPTIONAL { ?resource ws:tipeWilayah ?regionType }
    OPTIONAL { ?resource ws:idDestinasi ?destinationId }
    BIND(COALESCE(?resourceLabel, ?destinationName) AS ?label)
    FILTER(BOUND(?label) && STR(?label) != "-")
}
ORDER BY ?kind STR(?label) ?resource
SPARQL;
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function prefixes(array $prefixes): string
    {
        $namespaces = config('ontology.namespaces');
        if (! is_array($namespaces)) {
            throw new InvalidArgumentException('Peta namespace ontologi tidak tersedia.');
        }

        $lines = [];
        foreach ($prefixes as $prefix) {
            $namespace = $namespaces[$prefix] ?? null;
            if (! is_string($namespace)) {
                throw new InvalidArgumentException("Namespace {$prefix} tidak tersedia.");
            }

            $lines[] = "PREFIX {$prefix}: <{$namespace}>";
        }

        return implode("\n", $lines);
    }
}
