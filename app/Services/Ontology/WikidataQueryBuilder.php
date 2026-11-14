<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use InvalidArgumentException;

final class WikidataQueryBuilder
{
    private const PREFIXES = <<<'SPARQL'
PREFIX bd: <http://www.bigdata.com/rdf#>
PREFIX p: <http://www.wikidata.org/prop/>
PREFIX pq: <http://www.wikidata.org/prop/qualifier/>
PREFIX ps: <http://www.wikidata.org/prop/statement/>
PREFIX psv: <http://www.wikidata.org/prop/statement/value/>
PREFIX psn: <http://www.wikidata.org/prop/statement/value-normalized/>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
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

SELECT DISTINCT ?item ?coordinate ?image ?website
       ?population ?populationRank ?populationTime
       ?areaAmount ?areaUnit ?areaRank ?areaTime
       ?normalizedAreaAmount ?normalizedAreaUnit
WHERE {
    VALUES ?item { __ITEMS__ }
    OPTIONAL { ?item wdt:P625 ?coordinate . }
    OPTIONAL { ?item wdt:P18 ?image . }
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
