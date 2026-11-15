<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use InvalidArgumentException;

final class WikidataQueryBuilder
{
    private const PREFIXES = <<<'SPARQL'
PREFIX bd: <http://www.bigdata.com/rdf#>
PREFIX geo: <http://www.opengis.net/ont/geosparql#>
PREFIX p: <http://www.wikidata.org/prop/>
PREFIX pq: <http://www.wikidata.org/prop/qualifier/>
PREFIX ps: <http://www.wikidata.org/prop/statement/>
PREFIX psv: <http://www.wikidata.org/prop/statement/value/>
PREFIX psn: <http://www.wikidata.org/prop/statement/value-normalized/>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX schema: <http://schema.org/>
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
PREFIX wdt: <http://www.wikidata.org/prop/direct/>
PREFIX wd: <http://www.wikidata.org/entity/>
PREFIX wikibase: <http://wikiba.se/ontology#>
SPARQL;

    /**
     * @param  list<string>  $qids
     */
    public function regions(array $qids, string $countryQid, string $provinceQid): WikidataQuery
    {
        $regionQids = $this->validatedQids($qids);
        $validatedQids = $this->validatedQids([...$regionQids, $countryQid, $provinceQid]);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?item ?itemLabel ?country ?parent ?instance ?instanceLabel
WHERE {
    VALUES ?item { __ITEMS__ }
    ?item wdt:P17 ?country ;
          wdt:P131 ?parent ;
          wdt:P31 ?instance .
    FILTER(?country = wd:__COUNTRY__ && ?parent = wd:__PROVINCE__)
    OPTIONAL {
        ?item rdfs:label ?indonesianLabel .
        FILTER(LANG(?indonesianLabel) = "id")
    }
    OPTIONAL {
        ?item rdfs:label ?englishLabel .
        FILTER(LANG(?englishLabel) = "en")
    }
    BIND(COALESCE(?indonesianLabel, ?englishLabel) AS ?itemLabel)
    ?instance rdfs:label ?instanceLabel .
    FILTER(LANG(?instanceLabel) = "en")
}
ORDER BY ?item ?instance
SPARQL;

        $query = str_replace(
            ['__ITEMS__', '__COUNTRY__', '__PROVINCE__'],
            [$this->values($regionQids), $countryQid, $provinceQid],
            $query,
        );

        return new WikidataQuery($query, $validatedQids);
    }

    /**
     * @param  list<string>  $qids
     */
    public function boundaries(array $qids): WikidataQuery
    {
        $validatedQids = $this->validatedQids($qids);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?item ?neighbor
WHERE {
    VALUES ?item { __ITEMS__ }
    VALUES ?neighbor { __ITEMS__ }
    ?item wdt:P47 ?neighbor .
    FILTER(?item != ?neighbor)
}
ORDER BY ?item ?neighbor
SPARQL;

        return new WikidataQuery(str_replace('__ITEMS__', $this->values($validatedQids), $query), $validatedQids);
    }

    /**
     * @param  list<string>  $qids
     */
    public function details(array $qids): WikidataQuery
    {
        $validatedQids = $this->validatedQids($qids);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?item ?coordinate ?website
       ?population ?populationRank ?populationTime
       ?areaAmount ?areaUnit ?areaRank ?areaTime
       ?normalizedAreaAmount ?normalizedAreaUnit
WHERE {
    VALUES ?item { __ITEMS__ }
    OPTIONAL { ?item wdt:P625 ?coordinate . }
    OPTIONAL { ?item wdt:P856 ?website . }
    OPTIONAL {
        ?item p:P1082 ?populationStatement .
        ?populationStatement ps:P1082 ?population ;
                             wikibase:rank ?populationRank .
        OPTIONAL { ?populationStatement pq:P585 ?populationTime . }
    }
    OPTIONAL {
        ?item p:P2046 ?areaStatement .
        ?areaStatement ps:P2046 ?areaAmount ;
                       wikibase:rank ?areaRank .
        OPTIONAL { ?areaStatement pq:P585 ?areaTime . }
        OPTIONAL {
            ?areaStatement psv:P2046 ?areaValue .
            ?areaValue wikibase:quantityUnit ?areaUnit .
        }
        OPTIONAL {
            ?areaStatement psn:P2046 ?normalizedArea .
            ?normalizedArea wikibase:quantityAmount ?normalizedAreaAmount ;
                            wikibase:quantityUnit ?normalizedAreaUnit .
        }
    }
}
ORDER BY ?item ?populationTime ?areaTime
SPARQL;

        return new WikidataQuery(str_replace('__ITEMS__', $this->values($validatedQids), $query), $validatedQids);
    }

    /**
     * @param  list<string>  $qids
     */
    public function labelsAndAliases(array $qids): WikidataQuery
    {
        $validatedQids = $this->validatedQids($qids);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?item ?kind ?language ?value
WHERE {
    VALUES ?item { __ITEMS__ }
    {
        ?item rdfs:label ?value .
        FILTER(LANG(?value) IN ("id", "en"))
        BIND("label" AS ?kind)
    }
    UNION
    {
        ?item skos:altLabel ?value .
        FILTER(LANG(?value) IN ("id", "en"))
        BIND("alias" AS ?kind)
    }
    BIND(LANG(?value) AS ?language)
}
ORDER BY ?item ?kind ?language ?value
SPARQL;

        return new WikidataQuery(str_replace('__ITEMS__', $this->values($validatedQids), $query), $validatedQids);
    }

    /**
     * @param  list<string>  $qids
     */
    public function images(array $qids): WikidataQuery
    {
        $validatedQids = $this->validatedQids($qids);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?item ?image
WHERE {
    VALUES ?item { __ITEMS__ }
    ?item wdt:P18 ?image .
}
ORDER BY ?item ?image
SPARQL;

        return new WikidataQuery(str_replace('__ITEMS__', $this->values($validatedQids), $query), $validatedQids);
    }

    /**
     * @param  list<string>  $qids
     */
    public function sitelinks(array $qids): WikidataQuery
    {
        $validatedQids = $this->validatedQids($qids);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?item ?kind ?language ?url
WHERE {
    VALUES ?item { __ITEMS__ }
    {
        ?page schema:about ?item ;
              schema:isPartOf <https://id.wikipedia.org/> .
        BIND("wikipedia" AS ?kind)
        BIND("id" AS ?language)
        BIND(STR(?page) AS ?url)
    }
    UNION
    {
        ?page schema:about ?item ;
              schema:isPartOf <https://id.wikivoyage.org/> .
        BIND("wikivoyage" AS ?kind)
        BIND("id" AS ?language)
        BIND(STR(?page) AS ?url)
    }
    UNION
    {
        ?page schema:about ?item ;
              schema:isPartOf <https://en.wikivoyage.org/> .
        BIND("wikivoyage" AS ?kind)
        BIND("en" AS ?language)
        BIND(STR(?page) AS ?url)
    }
}
ORDER BY ?item ?kind ?language ?url
SPARQL;

        return new WikidataQuery(str_replace('__ITEMS__', $this->values($validatedQids), $query), $validatedQids);
    }

    /**
     * Fetches all page-level enrichments as category rows, avoiding joins between multi-valued properties.
     *
     * @param  list<string>  $qids
     */
    public function liveEnrichment(array $qids): WikidataQuery
    {
        $validatedQids = $this->validatedQids($qids);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?item ?kind ?language ?value ?status ?rank
       ?amount ?unit ?normalizedAmount ?normalizedUnit ?date ?precision
WHERE {
    VALUES ?item { __ITEMS__ }
    {
        ?item rdfs:label ?value .
        FILTER(LANG(?value) IN ("id", "en"))
        BIND("label" AS ?kind)
        BIND(LANG(?value) AS ?language)
    }
    UNION
    {
        ?item skos:altLabel ?value .
        FILTER(LANG(?value) IN ("id", "en"))
        BIND("alias" AS ?kind)
        BIND(LANG(?value) AS ?language)
    }
    UNION
    {
        ?item wdt:P18 ?image .
        BIND("image" AS ?kind)
        BIND("" AS ?language)
        BIND(STR(?image) AS ?value)
    }
    UNION
    {
        ?page schema:about ?item ;
              schema:isPartOf <https://id.wikipedia.org/> .
        BIND("wikipedia" AS ?kind)
        BIND("id" AS ?language)
        BIND(STR(?page) AS ?value)
    }
    UNION
    {
        ?page schema:about ?item ;
              schema:isPartOf <https://id.wikivoyage.org/> .
        BIND("wikivoyage" AS ?kind)
        BIND("id" AS ?language)
        BIND(STR(?page) AS ?value)
    }
    UNION
    {
        ?page schema:about ?item ;
              schema:isPartOf <https://en.wikivoyage.org/> .
        BIND("wikivoyage" AS ?kind)
        BIND("en" AS ?language)
        BIND(STR(?page) AS ?value)
    }
    UNION
    {
        ?item p:P1435 ?statement .
        ?statement ps:P1435 ?status ;
                   wikibase:rank ?rank .
        FILTER(?rank != wikibase:DeprecatedRank)
        ?status rdfs:label ?value .
        FILTER(LANG(?value) IN ("id", "en"))
        BIND("heritage" AS ?kind)
        BIND(LANG(?value) AS ?language)
    }
    UNION
    {
        ?item p:P2044 ?statement .
        ?statement ps:P2044 ?amount ;
                   wikibase:rank ?rank .
        FILTER(?rank != wikibase:DeprecatedRank)
        OPTIONAL {
            ?statement psv:P2044 ?quantity .
            ?quantity wikibase:quantityUnit ?unit .
        }
        OPTIONAL {
            ?statement psn:P2044 ?normalizedQuantity .
            ?normalizedQuantity wikibase:quantityAmount ?normalizedAmount ;
                                wikibase:quantityUnit ?normalizedUnit .
        }
        BIND("elevation" AS ?kind)
        BIND("" AS ?language)
    }
    UNION
    {
        ?item p:P571 ?statement .
        ?statement ps:P571 ?date ;
                   wikibase:rank ?rank .
        FILTER(?rank != wikibase:DeprecatedRank)
        ?statement psv:P571 ?timeValue .
        ?timeValue wikibase:timePrecision ?precision .
        BIND("establishment" AS ?kind)
        BIND("" AS ?language)
    }
}
ORDER BY ?item ?kind ?language ?value ?rank ?amount ?date
SPARQL;

        return new WikidataQuery(str_replace('__ITEMS__', $this->values($validatedQids), $query), $validatedQids);
    }

    /**
     * @param  list<string>  $qids
     */
    public function nearbyEntities(array $qids): WikidataQuery
    {
        $validatedQids = $this->validatedQids($qids);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?region ?regionLabel ?center ?candidate ?candidateLabel ?location ?distance
WHERE {
    VALUES ?region { __ITEMS__ }
    ?region wdt:P625 ?center .
    SERVICE wikibase:around {
        ?candidate wdt:P625 ?location .
        bd:serviceParam wikibase:center ?center ;
                        wikibase:radius "15" ;
                        wikibase:distance ?distance .
    }
    FILTER(?candidate != ?region)
    SERVICE wikibase:label {
        bd:serviceParam wikibase:language "id,en" .
    }
}
ORDER BY ?region ?distance ?candidate
LIMIT 2000
SPARQL;

        return new WikidataQuery(str_replace('__ITEMS__', $this->values($validatedQids), $query), $validatedQids);
    }

    /**
     * @param  list<string>  $qids
     */
    public function tourismClass(array $qids): WikidataQuery
    {
        $validatedQids = $this->validatedQids($qids);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?item ?label
WHERE {
    VALUES ?item { __ITEMS__ }
    ?item rdfs:label ?label .
    FILTER(LANG(?label) = "en")
}
ORDER BY ?item ?label
SPARQL;

        return new WikidataQuery(str_replace('__ITEMS__', $this->values($validatedQids), $query), $validatedQids);
    }

    public function tourismCandidates(string $tourismClassQid): WikidataQuery
    {
        $validatedQids = $this->validatedQids([$tourismClassQid]);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?candidate ?class ?classLabel ?location ?tourismClassLabel
WHERE {
    VALUES ?tourismClass { __TOURISM_CLASS__ }
    SERVICE wikibase:box {
        ?candidate wdt:P625 ?location .
        bd:serviceParam wikibase:cornerSouthWest "Point(97 0.5)"^^geo:wktLiteral ;
                        wikibase:cornerNorthEast "Point(100.5 4.5)"^^geo:wktLiteral .
    }
    ?candidate wdt:P31 ?class .
    ?class wdt:P279* ?tourismClass .
    ?class rdfs:label ?classLabel .
    FILTER(LANG(?classLabel) IN ("id", "en"))
    ?tourismClass rdfs:label ?tourismClassLabel .
    FILTER(LANG(?tourismClassLabel) = "en")
}
ORDER BY ?candidate ?class ?classLabel
LIMIT 5000
SPARQL;

        return new WikidataQuery(
            str_replace('__TOURISM_CLASS__', $this->values($validatedQids), $query),
            $validatedQids,
        );
    }

    /**
     * @param  list<string>  $qids
     */
    public function heritageStatuses(array $qids): WikidataQuery
    {
        $validatedQids = $this->validatedQids($qids);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?item ?status ?label ?rank
WHERE {
    VALUES ?item { __ITEMS__ }
    ?item p:P1435 ?statement .
    ?statement ps:P1435 ?status ;
               wikibase:rank ?rank .
    FILTER(?rank != wikibase:DeprecatedRank)
    ?status rdfs:label ?label .
    FILTER(LANG(?label) IN ("id", "en"))
}
ORDER BY ?item ?rank ?status ?label
SPARQL;

        return new WikidataQuery(str_replace('__ITEMS__', $this->values($validatedQids), $query), $validatedQids);
    }

    /**
     * @param  list<string>  $qids
     */
    public function elevations(array $qids): WikidataQuery
    {
        $validatedQids = $this->validatedQids($qids);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?item ?amount ?unit ?rank ?normalizedAmount ?normalizedUnit
WHERE {
    VALUES ?item { __ITEMS__ }
    ?item p:P2044 ?statement .
    ?statement ps:P2044 ?amount ;
               wikibase:rank ?rank .
    FILTER(?rank != wikibase:DeprecatedRank)
    OPTIONAL {
        ?statement psv:P2044 ?quantity .
        ?quantity wikibase:quantityUnit ?unit .
    }
    OPTIONAL {
        ?statement psn:P2044 ?normalizedQuantity .
        ?normalizedQuantity wikibase:quantityAmount ?normalizedAmount ;
                            wikibase:quantityUnit ?normalizedUnit .
    }
}
ORDER BY ?item ?rank ?amount
SPARQL;

        return new WikidataQuery(str_replace('__ITEMS__', $this->values($validatedQids), $query), $validatedQids);
    }

    /**
     * @param  list<string>  $qids
     */
    public function establishmentYears(array $qids): WikidataQuery
    {
        $validatedQids = $this->validatedQids($qids);
        $query = self::PREFIXES.<<<'SPARQL'

SELECT DISTINCT ?item ?date ?precision ?rank
WHERE {
    VALUES ?item { __ITEMS__ }
    ?item p:P571 ?statement .
    ?statement ps:P571 ?date ;
               wikibase:rank ?rank .
    FILTER(?rank != wikibase:DeprecatedRank)
    ?statement psv:P571 ?timeValue .
    ?timeValue wikibase:timePrecision ?precision .
}
ORDER BY ?item ?rank ?date
SPARQL;

        return new WikidataQuery(str_replace('__ITEMS__', $this->values($validatedQids), $query), $validatedQids);
    }

    /**
     * @param  list<string>  $qids
     * @return list<string>
     */
    private function validatedQids(array $qids): array
    {
        if ($qids === []) {
            throw new InvalidArgumentException('Kueri Wikidata membutuhkan setidaknya satu QID.');
        }

        foreach ($qids as $qid) {
            if (! is_string($qid) || preg_match('/^Q[0-9]+$/', $qid) !== 1) {
                throw new InvalidArgumentException('QID Wikidata tidak valid.');
            }
        }

        $qids = array_values(array_unique($qids));
        sort($qids);

        return $qids;
    }

    /**
     * @param  list<string>  $qids
     */
    private function values(array $qids): string
    {
        foreach ($qids as $qid) {
            if (preg_match('/^Q[0-9]+$/', $qid) !== 1) {
                throw new InvalidArgumentException('QID Wikidata tidak valid.');
            }
        }

        return implode(' ', array_map(static fn (string $qid): string => 'wd:'.$qid, $qids));
    }
}
