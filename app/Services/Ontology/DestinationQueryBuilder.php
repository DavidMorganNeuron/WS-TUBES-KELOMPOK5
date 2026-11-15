<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use App\Data\Destination;

final class DestinationQueryBuilder
{
    private const PREFIXES = <<<'SPARQL'
PREFIX ws: <http://pariwisatasumut.org/ontology/v2#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
SPARQL;

    /**
     * @param  list<string>  $districts
     */
    private readonly SearchTokenizer $tokenizer;

    public function __construct(
        private readonly SparqlEscaper $escaper,
        private readonly array $districts,
        ?SearchTokenizer $tokenizer = null,
    ) {
        $this->tokenizer = $tokenizer ?? new SearchTokenizer;
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null}  $filters
     */
    public function count(array $filters): string
    {
        return self::PREFIXES."\n"
            .'SELECT (COUNT(DISTINCT ?destination) AS ?count) WHERE { '
            .$this->where($filters)
            .' }';
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null}  $filters
     */
    public function ids(
        array $filters,
        ?int $limit = null,
        int $offset = 0,
        bool $ratedOnly = false,
        bool $coordinatesOnly = false,
    ): string {
        $sort = $this->sort($filters['sort'] ?? null, trim((string) ($filters['q'] ?? '')) !== '');
        $where = $this->where($filters);
        if ($ratedOnly) {
            $where .= "\nFILTER(BOUND(?ratingValue))";
        }
        if ($coordinatesOnly) {
            $where .= "\n?destination ws:lintang ?latitude ; ws:bujur ?longitude .";
        }
        $query = self::PREFIXES."\n"
            .'SELECT ?destination ?idDestinasi (SAMPLE(?nameValue) AS ?nameSort) '
            .'(MAX(?ratingValue) AS ?ratingSort) (MIN(?priceValue) AS ?priceSort) '
            .'(MAX(?searchScore) AS ?relevanceSort) WHERE { '
            .$where
            .' } GROUP BY ?destination ?idDestinasi ';

        $query .= $this->orderBy($sort);

        if ($limit !== null) {
            $query .= 'LIMIT '.$this->escaper->number(max(1, $limit))
                .' OFFSET '.$this->escaper->number(max(0, $offset));
        }

        return $query;
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null}  $filters
     */
    public function page(array $filters, int $limit, int $offset): string
    {
        $selection = substr($this->ids($filters, $limit, $offset), strlen(self::PREFIXES) + 1);
        $countWhere = str_replace('?destination', '?countedDestination', $this->where($filters));

        return self::PREFIXES."\n".<<<SPARQL
SELECT ?destination ?idDestinasi ?nameSort ?ratingSort ?priceSort ?relevanceSort ?total WHERE {
    { SELECT (COUNT(DISTINCT ?countedDestination) AS ?total) WHERE { {$countWhere} } }
    OPTIONAL {
        { {$selection} }
    }
}
{$this->orderBy($this->sort($filters['sort'] ?? null, trim((string) ($filters['q'] ?? '')) !== ''))}
SPARQL;
    }

    public function dashboard(string $facetsQuery): string
    {
        $facetsSelection = substr($facetsQuery, (int) strpos($facetsQuery, 'SELECT '));
        $topRatedSelection = substr(
            $this->ids(['sort' => 'rating'], 5, 0, true),
            strlen(self::PREFIXES) + 1,
        );

        return self::PREFIXES."\n".<<<SPARQL
SELECT ?kind ?label ?count ?total ?rated ?average ?coordinates ?media ?idDestinasi WHERE {
    { {$facetsSelection} }
    UNION {
        SELECT ("top_rated" AS ?kind) ?idDestinasi WHERE {
            { {$topRatedSelection} }
        }
    }
}
SPARQL;
    }

    /**
     * @param  list<string>  $ids
     */
    public function details(array $ids): string
    {
        $values = implode(' ', array_map($this->escaper->destinationIri(...), $ids));

        return self::PREFIXES."\n".<<<SPARQL
SELECT ?destination ?idDestinasi ?namaDestinasi
    (GROUP_CONCAT(DISTINCT STR(?namaAlternatifValue); separator=" | ") AS ?namaAlternatif)
    (SAMPLE(STR(?deskripsiSingkatValue)) AS ?deskripsiSingkat)
    (SAMPLE(STR(?deskripsiLengkapValue)) AS ?deskripsiLengkap)
    (GROUP_CONCAT(DISTINCT STR(?kataKunciValue); separator=" | ") AS ?kataKunci)
    (SAMPLE(STR(?locationValue)) AS ?beradaDi)
    (SAMPLE(STR(?alamatValue)) AS ?alamat)
    (SAMPLE(STR(?lintangValue)) AS ?lintang)
    (SAMPLE(STR(?bujurValue)) AS ?bujur)
    (SAMPLE(STR(?petunjukValue)) AS ?petunjukLokasi)
    (SAMPLE(STR(?jamBukaValue)) AS ?jamBuka)
    (SAMPLE(STR(?jamTutupValue)) AS ?jamTutup)
    (SAMPLE(STR(?jadwalValue)) AS ?keteranganJadwal)
    (GROUP_CONCAT(DISTINCT STR(?priceValue); separator=" | ") AS ?hargaMasuk)
    (GROUP_CONCAT(DISTINCT STR(?phoneValue); separator=" | ") AS ?noHpResmi)
    (GROUP_CONCAT(DISTINCT STR(?emailValue); separator=" | ") AS ?emailResmi)
    (GROUP_CONCAT(DISTINCT STR(?instagramValue); separator=" | ") AS ?instagramResmi)
    (GROUP_CONCAT(DISTINCT STR(?typeValue); separator=" | ") AS ?jenisDestinasi)
    (GROUP_CONCAT(DISTINCT STR(?facilityValue); separator=" | ") AS ?memilikiFasilitas)
    (GROUP_CONCAT(DISTINCT STR(?activityValue); separator=" | ") AS ?dapatMelakukan)
    (GROUP_CONCAT(DISTINCT STR(?mediaValue); separator=" | ") AS ?memilikiMedia)
    (SAMPLE(STR(?ratingValue)) AS ?ratingPengunjung)
WHERE {
    VALUES ?destination { {$values} }
    ?destination a ws:DestinasiWisata ;
        ws:idDestinasi ?idDestinasi ;
        ws:namaDestinasi ?namaDestinasi .
    OPTIONAL { ?destination ws:namaAlternatif ?namaAlternatifValue . FILTER(STR(?namaAlternatifValue) != "-") }
    OPTIONAL { ?destination ws:deskripsiSingkat ?deskripsiSingkatValue }
    OPTIONAL { ?destination ws:deskripsiLengkap ?deskripsiLengkapValue }
    OPTIONAL { ?destination ws:kataKunci ?kataKunciValue . FILTER(STR(?kataKunciValue) != "-") }
    OPTIONAL { ?destination ws:berlokasiDi/rdfs:label ?locationValue }
    OPTIONAL { ?destination ws:alamat ?alamatValue }
    OPTIONAL { ?destination ws:lintang ?lintangValue }
    OPTIONAL { ?destination ws:bujur ?bujurValue }
    OPTIONAL { ?destination ws:petunjukLokasi ?petunjukValue }
    OPTIONAL {
        ?destination ws:memilikiJadwal ?schedule .
        OPTIONAL { ?schedule ws:jamBuka ?jamBukaValue }
        OPTIONAL { ?schedule ws:jamTutup ?jamTutupValue }
        OPTIONAL { ?schedule ws:keteranganJadwal ?jadwalValue . FILTER(STR(?jadwalValue) != "-") }
    }
    OPTIONAL { ?destination ws:memilikiTarif/ws:hargaMasuk ?priceValue }
    OPTIONAL { ?destination ws:memilikiKontak/ws:nomorHp ?phoneValue . FILTER(STR(?phoneValue) != "-") }
    OPTIONAL { ?destination ws:memilikiKontak/ws:email ?emailValue . FILTER(STR(?emailValue) != "-") }
    OPTIONAL { ?destination ws:memilikiKontak/ws:urlInstagram ?instagramValue . FILTER(STR(?instagramValue) != "-") }
    OPTIONAL { ?destination ws:berkategori/rdfs:label ?typeValue . FILTER(STR(?typeValue) != "-") }
    OPTIONAL { ?destination ws:menyediakanFasilitas/rdfs:label ?facilityValue . FILTER(STR(?facilityValue) != "-") }
    OPTIONAL { ?destination ws:menawarkanAktivitas/rdfs:label ?activityValue . FILTER(STR(?activityValue) != "-") }
    OPTIONAL { ?destination ws:memilikiMedia/ws:urlMedia ?mediaValue . FILTER(STR(?mediaValue) != "-") }
    OPTIONAL { ?destination ws:ratingPengunjung ?ratingValue }
}
GROUP BY ?destination ?idDestinasi ?namaDestinasi
ORDER BY STR(?idDestinasi)
SPARQL;
    }

    public function nearbyIds(string $id): string
    {
        return self::PREFIXES."\n"
            .'SELECT ?idDestinasi WHERE { '
            .$this->escaper->destinationIri($id).' ws:dekatDengan ?neighbor . '
            .'?neighbor ws:idDestinasi ?idDestinasi . '
            .'} ORDER BY STR(?idDestinasi)';
    }

    public function searchableTexts(): string
    {
        return self::PREFIXES."\n".<<<'SPARQL'
SELECT ?text WHERE {
    ?destination a ws:DestinasiWisata .
    {
        ?destination ws:namaDestinasi ?text .
    } UNION {
        ?destination ws:namaAlternatif ?text .
    } UNION {
        ?destination ws:berlokasiDi/rdfs:label ?text .
    } UNION {
        ?destination ws:alamat ?text .
    } UNION {
        ?destination ws:deskripsiSingkat ?text .
    } UNION {
        ?destination ws:deskripsiLengkap ?text .
    } UNION {
        ?destination ws:petunjukLokasi ?text .
    } UNION {
        ?destination ws:kataKunci ?text .
    } UNION {
        ?destination ws:menyediakanFasilitas/rdfs:label ?text .
    } UNION {
        ?destination ws:menawarkanAktivitas/rdfs:label ?text .
    }
    FILTER(isLiteral(?text) && STR(?text) != "-")
}
SPARQL;
    }

    public function relatedIds(Destination $destination, int $limit): string
    {
        if ($destination->types === []) {
            return self::PREFIXES."\nSELECT ?idDestinasi WHERE { FILTER(false) }";
        }

        $limit = $this->escaper->number(max(1, $limit));
        $targetIri = $this->escaper->destinationIri($destination->id);

        return self::PREFIXES."\n".<<<SPARQL
SELECT ?idDestinasi (SAMPLE(?nameValue) AS ?nameSort)
    (COUNT(DISTINCT ?candidateType) AS ?overlap)
    (MAX(?ratingValue) AS ?ratingSort)
WHERE {
    {$targetIri} ws:berkategori ?targetType .
    ?candidate a ws:DestinasiWisata ;
        ws:idDestinasi ?idDestinasi ;
        ws:namaDestinasi ?nameValue ;
        ws:berkategori ?candidateType .
    FILTER(?candidate != {$targetIri} && ?candidateType = ?targetType)
    OPTIONAL { ?candidate ws:ratingPengunjung ?ratingValue . FILTER(isNumeric(?ratingValue)) }
}
GROUP BY ?candidate ?idDestinasi
ORDER BY DESC((COUNT(DISTINCT ?candidateType) * 100) + COALESCE(MAX(?ratingValue), 0))
    STR(?idDestinasi)
LIMIT {$limit}
SPARQL;
    }

    public function similarIds(Destination $destination, int $limit): string
    {
        if ($destination->types === []) {
            return self::PREFIXES."\nSELECT ?idDestinasi WHERE { FILTER(false) }";
        }

        $limit = $this->escaper->number(max(1, $limit));
        $targetIri = $this->escaper->destinationIri($destination->id);

        return self::PREFIXES."\n".<<<SPARQL
SELECT ?idDestinasi (SAMPLE(?nameValue) AS ?nameSort)
    (COUNT(DISTINCT ?sharedType) AS ?overlap)
    (MAX(?ratingValue) AS ?ratingSort)
WHERE {
    {$targetIri} ws:berkategori ?sharedType .
    ?candidate a ws:DestinasiWisata ;
        ws:idDestinasi ?idDestinasi ;
        ws:namaDestinasi ?nameValue ;
        ws:berkategori ?sharedType .
    FILTER(?candidate != {$targetIri})
    OPTIONAL { ?candidate ws:ratingPengunjung ?ratingValue . FILTER(isNumeric(?ratingValue)) }
}
GROUP BY ?candidate ?idDestinasi
ORDER BY DESC(COUNT(DISTINCT ?sharedType)) DESC(COALESCE(MAX(?ratingValue), -1))
    LCASE(STR(SAMPLE(?nameValue))) STR(?idDestinasi)
LIMIT {$limit}
SPARQL;
    }

    /**
     * @param  list<string>  $excludedTypes
     */
    public function crossCategoryRows(Destination $destination, array $excludedTypes): string
    {
        if ($excludedTypes === []) {
            return self::PREFIXES."\nSELECT ?idDestinasi ?type WHERE { FILTER(false) }";
        }

        $targetIri = $this->escaper->destinationIri($destination->id);
        $types = implode(' ', array_map($this->escaper->literal(...), $excludedTypes));

        return self::PREFIXES."\n".<<<SPARQL
SELECT ?idDestinasi ?type (SAMPLE(?nameValue) AS ?nameSort)
    (MAX(?ratingValue) AS ?ratingSort)
WHERE {
    VALUES ?allowedType { {$types} }
    ?candidate a ws:DestinasiWisata ;
        ws:idDestinasi ?idDestinasi ;
        ws:namaDestinasi ?nameValue ;
        ws:berkategori/rdfs:label ?typeLabel .
    BIND(STR(?typeLabel) AS ?type)
    FILTER(?type = ?allowedType)
    FILTER NOT EXISTS {
        ?candidate ws:berkategori ?targetType .
        {$targetIri} ws:berkategori ?targetType .
    }
    OPTIONAL { ?candidate ws:ratingPengunjung ?ratingValue . FILTER(isNumeric(?ratingValue)) }
}
GROUP BY ?candidate ?idDestinasi ?type
ORDER BY STR(?type) DESC(COALESCE(MAX(?ratingValue), 0))
    STR(?idDestinasi)
SPARQL;
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null}  $filters
     */
    private function where(array $filters): string
    {
        $where = <<<'SPARQL'
?destination a ws:DestinasiWisata ;
    ws:idDestinasi ?idDestinasi ;
    ws:namaDestinasi ?nameValue .
OPTIONAL { ?destination ws:ratingPengunjung ?ratingValue . FILTER(isNumeric(?ratingValue)) }
OPTIONAL { ?destination ws:memilikiTarif/ws:hargaMasuk ?priceValue . FILTER(isNumeric(?priceValue)) }
SPARQL;

        $query = trim((string) ($filters['q'] ?? ''));
        $tokens = $this->tokenizer->tokenize($query);
        if ($query !== '' && $tokens !== []) {
            $scoreParts = [];
            foreach ($tokens as $token) {
                $where .= "\nFILTER(".$this->tokenMatchExpression($token).')';
                $scoreParts[] = $this->tokenScoreExpression($token);
            }
            $where .= "\nBIND((".implode(' + ', $scoreParts).') AS ?searchScore)';
        } else {
            $where .= "\nBIND(0 AS ?searchScore)";
        }

        $type = $filters['jenis'] ?? null;
        if (is_string($type) && in_array($type, DestinationRepository::TYPES, true)) {
            $where .= "\n?destination ws:berkategori/rdfs:label ?typeLabel ."
                ."\nFILTER(STR(?typeLabel) = ".$this->escaper->literal($type).')';
        }

        $district = trim((string) ($filters['kabupaten'] ?? ''));
        if ($district !== '') {
            if (! in_array($district, $this->districts, true)) {
                $where .= "\nFILTER(false)";
            } else {
                $where .= "\n?destination ws:berlokasiDi/rdfs:label ?districtLabel ."
                    ."\nFILTER(STR(?districtLabel) = ".$this->escaper->literal($district).')';
            }
        }

        return $where;
    }

    private function tokenMatchExpression(string $token): string
    {
        $pattern = $this->escaper->literal('(^|[^\\p{L}\\p{N}])'.preg_quote($token, '/'));

        return implode(' || ', [
            'EXISTS { ?destination ws:namaDestinasi ?matchName . FILTER(REGEX(LCASE(STR(?matchName)), '.$pattern.')) }',
            'EXISTS { ?destination ws:namaAlternatif ?matchAlias . FILTER(REGEX(LCASE(STR(?matchAlias)), '.$pattern.')) }',
            'EXISTS { ?destination ws:berlokasiDi/rdfs:label ?matchLocation . FILTER(REGEX(LCASE(STR(?matchLocation)), '.$pattern.')) }',
            'EXISTS { ?destination ws:alamat ?matchAddress . FILTER(REGEX(LCASE(STR(?matchAddress)), '.$pattern.')) }',
            'EXISTS { ?destination ws:deskripsiSingkat ?matchShortDescription . FILTER(REGEX(LCASE(STR(?matchShortDescription)), '.$pattern.')) }',
            'EXISTS { ?destination ws:deskripsiLengkap ?matchFullDescription . FILTER(REGEX(LCASE(STR(?matchFullDescription)), '.$pattern.')) }',
            'EXISTS { ?destination ws:petunjukLokasi ?matchDirections . FILTER(REGEX(LCASE(STR(?matchDirections)), '.$pattern.')) }',
            'EXISTS { ?destination ws:kataKunci ?matchKeyword . FILTER(REGEX(LCASE(STR(?matchKeyword)), '.$pattern.')) }',
            'EXISTS { ?destination ws:menyediakanFasilitas/rdfs:label ?matchFacility . FILTER(REGEX(LCASE(STR(?matchFacility)), '.$pattern.')) }',
            'EXISTS { ?destination ws:menawarkanAktivitas/rdfs:label ?matchActivity . FILTER(REGEX(LCASE(STR(?matchActivity)), '.$pattern.')) }',
        ]);
    }

    private function tokenScoreExpression(string $token): string
    {
        $defaults = [
            'name_exact' => 1100,
            'name_prefix' => 1000,
            'name_word' => 900,
            'alias_keyword' => 800,
            'locality' => 600,
            'address' => 400,
            'description' => 200,
        ];
        $weights = function_exists('app') && app()->bound('config')
            ? config('ontology.search.relevance_weights', $defaults)
            : $defaults;
        $literal = $this->escaper->literal($token);
        $wordPattern = $this->escaper->literal('(^|[^\\p{L}\\p{N}])'.preg_quote($token, '/'));
        $nameExact = 'EXISTS { ?destination ws:namaDestinasi ?scoreNameExact . FILTER(LCASE(STR(?scoreNameExact)) = '.$literal.') }';
        $namePrefix = 'EXISTS { ?destination ws:namaDestinasi ?scoreNamePrefix . FILTER(STRSTARTS(LCASE(STR(?scoreNamePrefix)), '.$literal.')) }';
        $nameWord = 'EXISTS { ?destination ws:namaDestinasi ?scoreNameWord . FILTER(REGEX(LCASE(STR(?scoreNameWord)), '.$wordPattern.')) }';
        $aliasAndKeyword = implode(' || ', [
            'EXISTS { ?destination ws:namaAlternatif ?scoreAlias . FILTER(REGEX(LCASE(STR(?scoreAlias)), '.$wordPattern.')) }',
            'EXISTS { ?destination ws:kataKunci ?scoreKeyword . FILTER(REGEX(LCASE(STR(?scoreKeyword)), '.$wordPattern.')) }',
            'EXISTS { ?destination ws:menyediakanFasilitas/rdfs:label ?scoreFacility . FILTER(REGEX(LCASE(STR(?scoreFacility)), '.$wordPattern.')) }',
            'EXISTS { ?destination ws:menawarkanAktivitas/rdfs:label ?scoreActivity . FILTER(REGEX(LCASE(STR(?scoreActivity)), '.$wordPattern.')) }',
        ]);
        $locality = 'EXISTS { ?destination ws:berlokasiDi/rdfs:label ?scoreLocation . FILTER(REGEX(LCASE(STR(?scoreLocation)), '.$wordPattern.')) }';
        $address = 'EXISTS { ?destination ws:alamat ?scoreAddress . FILTER(REGEX(LCASE(STR(?scoreAddress)), '.$wordPattern.')) }';
        $description = implode(' || ', [
            'EXISTS { ?destination ws:deskripsiSingkat ?scoreShortDescription . FILTER(REGEX(LCASE(STR(?scoreShortDescription)), '.$wordPattern.')) }',
            'EXISTS { ?destination ws:deskripsiLengkap ?scoreFullDescription . FILTER(REGEX(LCASE(STR(?scoreFullDescription)), '.$wordPattern.')) }',
            'EXISTS { ?destination ws:petunjukLokasi ?scoreDirections . FILTER(REGEX(LCASE(STR(?scoreDirections)), '.$wordPattern.')) }',
        ]);

        return 'IF('.$nameExact.', '.(int) ($weights['name_exact'] ?? 1100)
            .', IF('.$namePrefix.', '.(int) ($weights['name_prefix'] ?? 1000)
            .', IF('.$nameWord.', '.(int) ($weights['name_word'] ?? 900)
            .', IF('.$aliasAndKeyword.', '.(int) ($weights['alias_keyword'] ?? 800)
            .', IF('.$locality.', '.(int) ($weights['locality'] ?? 600)
            .', IF('.$address.', '.(int) ($weights['address'] ?? 400)
            .', IF('.$description.', '.(int) ($weights['description'] ?? 200).', 0))))))';
    }

    private function orderBy(string $sort): string
    {
        return match ($sort) {
            'relevansi' => 'ORDER BY DESC(?relevanceSort) DESC(BOUND(?ratingSort)) DESC(?ratingSort) LCASE(STR(?nameSort)) STR(?idDestinasi) ',
            'name' => 'ORDER BY LCASE(STR(?nameSort)) STR(?idDestinasi) ',
            'id' => 'ORDER BY STR(?idDestinasi) ',
            'rating_asc' => 'ORDER BY DESC(BOUND(?ratingSort)) ASC(?ratingSort) LCASE(STR(?nameSort)) STR(?idDestinasi) ',
            'price_asc' => 'ORDER BY DESC(BOUND(?priceSort)) ASC(?priceSort) LCASE(STR(?nameSort)) STR(?idDestinasi) ',
            'price_desc' => 'ORDER BY DESC(BOUND(?priceSort)) DESC(?priceSort) LCASE(STR(?nameSort)) STR(?idDestinasi) ',
            default => 'ORDER BY DESC(BOUND(?ratingSort)) DESC(?ratingSort) LCASE(STR(?nameSort)) STR(?idDestinasi) ',
        };
    }

    private function sort(mixed $sort, bool $hasQuery = false): string
    {
        return is_string($sort) && in_array($sort, DestinationRepository::SORTS, true)
            ? $sort
            : ($hasQuery ? 'relevansi' : 'rating');
    }
}
