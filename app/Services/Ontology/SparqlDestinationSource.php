<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use App\Data\Destination;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class SparqlDestinationSource implements DestinationSource
{
    public function __construct(
        private readonly FusekiClient $client,
        private readonly DestinationQueryBuilder $queryBuilder,
        private readonly SparqlRowMapper $rowMapper,
        private readonly int $cacheTtl = 600,
    ) {}

    /**
     * @return Collection<int, Destination>
     */
    public function all(): Collection
    {
        $rows = $this->selectCached($this->queryBuilder->ids(['sort' => 'id']));
        if ($rows === []) {
            throw OntologyUnavailableException::badResponse();
        }

        return $this->destinationsForRows($rows);
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null}  $filters
     * @return Collection<int, Destination>
     */
    public function search(array $filters = []): Collection
    {
        if ($this->hasUnmatchableQuery($filters)) {
            return collect();
        }

        $rows = $this->selectCached($this->queryBuilder->ids($filters));
        $destinations = $this->destinationsForRows($rows);

        return $this->orderForSearch($destinations, $filters);
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 9): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);
        if ($this->hasUnmatchableQuery($filters)) {
            return new LengthAwarePaginator(collect(), 0, $perPage, 1, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => array_filter($filters, static fn (mixed $value): bool => $value !== null && $value !== ''),
            ]);
        }

        $requestedPage = max(1, LengthAwarePaginator::resolveCurrentPage('page'));
        $offset = ($requestedPage - 1) * $perPage;
        $pageRows = $this->selectCached($this->queryBuilder->page($filters, $perPage, $offset));
        $count = filter_var($pageRows[0]['total'] ?? null, FILTER_VALIDATE_INT);
        if ($count === false || $count < 0) {
            throw OntologyUnavailableException::badResponse();
        }

        $lastPage = max(1, (int) ceil($count / $perPage));
        $page = min($requestedPage, $lastPage);
        if ($page !== $requestedPage) {
            $offset = ($page - 1) * $perPage;
            $pageRows = $this->selectCached($this->queryBuilder->page($filters, $perPage, $offset));
        }

        $destinationRows = array_values(array_filter(
            $pageRows,
            static fn (array $row): bool => isset($row['idDestinasi']),
        ));
        $destinations = $this->orderForSearch(
            $this->destinationsForRows($destinationRows),
            $filters,
        );

        return new LengthAwarePaginator(
            $destinations,
            $count,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => array_filter($filters, static fn (mixed $value): bool => $value !== null && $value !== ''),
            ],
        );
    }

    public function find(string $id): ?Destination
    {
        $id = $this->normalizeId($id);
        if (preg_match('/^ws-\d{3}$/', $id) !== 1) {
            return null;
        }

        $rows = $this->selectCached($this->queryBuilder->details([$id]));

        return $this->rowMapper->destinations($rows)->get($id);
    }

    /**
     * @return Collection<int, Destination>
     */
    public function topRated(int $limit = 6): Collection
    {
        $limit = max(0, $limit);
        if ($limit === 0) {
            return collect();
        }

        return $this->destinationsForRows($this->selectCached(
            $this->queryBuilder->ids(['sort' => 'rating'], $limit, 0, true),
        ));
    }

    /**
     * @return Collection<int, Destination>
     */
    public function related(Destination $destination, int $limit = 4): Collection
    {
        $limit = max(0, $limit);
        if ($limit === 0) {
            return collect();
        }

        return $this->destinationsForRows($this->selectCached(
            $this->queryBuilder->relatedIds($destination, $limit),
        ));
    }

    /**
     * @return Collection<int, Destination>
     */
    public function crossCategory(Destination $destination, int $limit = 4): Collection
    {
        $otherTypes = array_values(array_diff(DestinationRepository::TYPES, $destination->types));
        if ($otherTypes === []) {
            return collect();
        }

        $limit = max(0, $limit);
        if ($limit === 0) {
            return collect();
        }

        $rows = $this->selectCached($this->queryBuilder->crossCategoryRows($destination, $otherTypes));
        $perType = array_fill_keys($otherTypes, []);
        foreach ($rows as $row) {
            $type = $row['type'] ?? null;
            $id = $row['idDestinasi'] ?? null;
            if (is_string($type) && isset($perType[$type])
                && is_string($id) && preg_match('/^ws-\d{3}$/', $id) === 1) {
                $perType[$type][] = $id;
            }
        }

        $ids = [];
        for ($round = 0; count($ids) < $limit; $round++) {
            $added = false;
            foreach ($otherTypes as $type) {
                $id = $perType[$type][$round] ?? null;
                if ($id === null || in_array($id, $ids, true)) {
                    continue;
                }

                $ids[] = $id;
                $added = true;
                if (count($ids) >= $limit) {
                    break;
                }
            }

            if (! $added) {
                break;
            }
        }

        return $this->destinationsForIds($ids);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function markers(array $filters = []): array
    {
        return $this->destinationsForRows($this->selectCached(
            $this->queryBuilder->ids($filters, coordinatesOnly: true),
        ))
            ->map(static fn (Destination $destination): array => $destination->toMapMarker())
            ->values()
            ->all();
    }

    /**
     * @return array{total: int, types: array<string, int>, provinces: array<string, int>, kabupaten: array<string, int>, average_rating: float|null, rated_count: int, with_coordinates: int, with_media: int}
     */
    public function facets(): array
    {
        return $this->mapFacets($this->selectCached($this->facetsQuery()));
    }

    /**
     * @return array{total: int, types: array<string, int>, provinces: array<string, int>, kabupaten: array<string, int>, average_rating: float|null, rated_count: int, with_coordinates: int, with_media: int, top_rated: Collection<int, Destination>, top_region: array{label: string, count: int}|null}
     */
    public function dashboard(): array
    {
        $rows = $this->selectCached($this->queryBuilder->dashboard($this->facetsQuery()));
        $facets = $this->mapFacets($rows);
        $topRatedIds = [];

        foreach ($rows as $row) {
            if (($row['kind'] ?? null) !== 'top_rated') {
                continue;
            }

            $id = $row['idDestinasi'] ?? null;
            if (! is_string($id) || preg_match('/^ws-\\d{3}$/', $id) !== 1) {
                throw OntologyUnavailableException::badResponse();
            }

            $topRatedIds[] = $id;
        }

        $districtLabels = array_keys($facets['kabupaten']);
        usort($districtLabels, static fn (string $left, string $right): int => (
            $facets['kabupaten'][$right] <=> $facets['kabupaten'][$left]
        ) ?: strcmp($left, $right));
        $topDistrict = $districtLabels[0] ?? null;

        return $facets + [
            'top_rated' => $this->destinationsForIds($topRatedIds),
            'top_region' => $topDistrict === null ? null : [
                'label' => $topDistrict,
                'count' => $facets['kabupaten'][$topDistrict],
            ],
        ];
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function facetsQuery(): string
    {
        return <<<'SPARQL'
PREFIX ws: <http://pariwisatasumut.org/ontology/v2#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
SELECT ?kind ?label ?count ?total ?rated ?average ?coordinates ?media WHERE {
    {
        SELECT ("overall" AS ?kind) ?total ?rated ?average ?coordinates ?media WHERE {
            { SELECT (COUNT(DISTINCT ?allDestination) AS ?total) WHERE { ?allDestination a ws:DestinasiWisata } }
            { SELECT (COUNT(DISTINCT ?ratedDestination) AS ?rated) (AVG(?rating) AS ?average) WHERE {
                ?ratedDestination a ws:DestinasiWisata ; ws:ratingPengunjung ?rating .
                FILTER(isNumeric(?rating))
            } }
            { SELECT (COUNT(DISTINCT ?coordinateDestination) AS ?coordinates) WHERE {
                ?coordinateDestination a ws:DestinasiWisata ; ws:lintang ?lat ; ws:bujur ?lon .
            } }
            { SELECT (COUNT(DISTINCT ?mediaDestination) AS ?media) WHERE {
                ?mediaDestination a ws:DestinasiWisata ; ws:memilikiMedia/ws:urlMedia ?url .
                FILTER(STR(?url) != "-")
            } }
        }
    }
    UNION
    {
        SELECT ("type" AS ?kind) (STR(?typeValue) AS ?label) (COUNT(DISTINCT ?typeDestination) AS ?count) WHERE {
            ?typeDestination a ws:DestinasiWisata ; ws:berkategori/rdfs:label ?typeValue .
            FILTER(STR(?typeValue) != "-")
        }
        GROUP BY ?typeValue
    }
    UNION
    {
        SELECT ("district" AS ?kind) (STR(?districtValue) AS ?label) (COUNT(DISTINCT ?districtDestination) AS ?count) WHERE {
            ?districtDestination a ws:DestinasiWisata ; ws:berlokasiDi/rdfs:label ?districtValue .
            FILTER(STRSTARTS(STR(?districtValue), "Kabupaten ") || STRSTARTS(STR(?districtValue), "Kota "))
        }
        GROUP BY ?districtValue
    }
}
SPARQL;
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return array{total: int, types: array<string, int>, provinces: array<string, int>, kabupaten: array<string, int>, average_rating: float|null, rated_count: int, with_coordinates: int, with_media: int}
     */
    private function mapFacets(array $rows): array
    {
        $overall = null;
        $types = array_fill_keys(DestinationRepository::TYPES, 0);
        $districts = [];
        $provinces = array_fill_keys(DestinationRepository::PROVINCES, 0);
        foreach ($rows as $row) {
            $kind = $row['kind'] ?? null;
            $label = $row['label'] ?? null;
            if ($kind === 'overall') {
                $overall = $row;
            } elseif ($kind === 'type' && is_string($label) && array_key_exists($label, $types)) {
                $types[$label] = $this->integer($row['count'] ?? null);
            } elseif ($kind === 'district' && is_string($label)) {
                $districts[$label] = $this->integer($row['count'] ?? null);
            }
        }

        if ($overall === null) {
            throw OntologyUnavailableException::badResponse();
        }

        ksort($districts);
        $total = $this->integer($overall['total'] ?? null);
        $provinces['Sumatera Utara'] = $total;

        return [
            'total' => $total,
            'types' => $types,
            'provinces' => $provinces,
            'kabupaten' => $districts,
            'average_rating' => isset($overall['average']) && is_numeric($overall['average'])
                ? round((float) $overall['average'], 2)
                : null,
            'rated_count' => $this->integer($overall['rated'] ?? null),
            'with_coordinates' => $this->integer($overall['coordinates'] ?? null),
            'with_media' => $this->integer($overall['media'] ?? null),
        ];
    }

    /**
     * @return array{total: int, rated: int, unrated: int, average_rating: float|null, with_coordinates: int, with_media: int}
     */
    public function statistics(): array
    {
        $facets = $this->facets();

        return [
            'total' => $facets['total'],
            'rated' => $facets['rated_count'],
            'unrated' => $facets['total'] - $facets['rated_count'],
            'average_rating' => $facets['average_rating'],
            'with_coordinates' => $facets['with_coordinates'],
            'with_media' => $facets['with_media'],
        ];
    }

    /**
     * @return Collection<int, Destination>
     */
    public function nearby(Destination $destination, ?int $limit = null): Collection
    {
        $query = $this->queryBuilder->nearbyIds($destination->id);
        $ids = array_values(array_filter(array_map(
            static fn (array $row): mixed => $row['idDestinasi'] ?? null,
            $this->selectCached($query),
        ), static fn (mixed $id): bool => is_string($id) && preg_match('/^ws-\d{3}$/', $id) === 1));
        if ($limit !== null) {
            $ids = array_slice($ids, 0, max(0, $limit));
        }

        return $this->destinationsForIds($ids);
    }

    /**
     * @return Collection<int, Destination>
     */
    public function similarByCategory(Destination $destination, int $limit = 4): Collection
    {
        if ($destination->types === []) {
            return collect();
        }

        $limit = max(0, $limit);
        if ($limit === 0) {
            return collect();
        }

        return $this->destinationsForRows($this->selectCached(
            $this->queryBuilder->similarIds($destination, $limit),
        ));
    }

    /**
     * @param  array{q?: string, jenis?: string, kabupaten?: string, provinsi?: string}  $criteria
     * @return list<array{id: string, name: string, types: list<string>, location: string, rating: float|null, price: float|null}>
     */
    public function retrieveForContext(array $criteria = [], int $limit = 5): array
    {
        $unsupported = array_diff(array_keys($criteria), ['q', 'jenis', 'kabupaten', 'provinsi']);
        if ($unsupported !== []) {
            throw new \InvalidArgumentException('Kriteria konteks tidak didukung: '.implode(', ', $unsupported).'.');
        }

        foreach ($criteria as $key => $value) {
            if (! is_string($value)) {
                throw new \InvalidArgumentException("Kriteria konteks {$key} harus berupa teks.");
            }
        }

        return $this->search($criteria)
            ->take(min(10, max(1, $limit)))
            ->map(static fn (Destination $destination): array => [
                'id' => $destination->id,
                'name' => $destination->name,
                'types' => $destination->types,
                'location' => $destination->location,
                'rating' => $destination->rating,
                'price' => $destination->lowestPrice(),
            ])
            ->values()
            ->all();
    }

    public function forgetCache(): void
    {
        // Cache keys are versioned; the next read uses the refreshed ontology version.
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return Collection<int, Destination>
     */
    private function destinationsForRows(array $rows): Collection
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = $row['idDestinasi'] ?? null;
            if (! is_string($id) || preg_match('/^ws-\d{3}$/', $id) !== 1) {
                throw OntologyUnavailableException::badResponse();
            }
            $ids[] = $id;
        }

        return $this->destinationsForIds($ids);
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<int, Destination>
     */
    private function destinationsForIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $details = $this->selectCached($this->queryBuilder->details($ids));
        $destinations = $this->rowMapper->destinations($details);
        $ordered = collect();
        foreach ($ids as $id) {
            if ($destinations->has($id)) {
                $ordered->push($destinations->get($id));
            }
        }

        return $ordered->values();
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function selectCached(string $query): array
    {
        $version = app(OntologyDataVersion::class)->current() ?? 'initial';
        $key = 'ontology.sparql.'.hash('sha256', $version.'|'.$query);

        return Cache::remember($key, $this->cacheTtl, fn (): array => $this->client->select($query));
    }

    private function orderForSearch(Collection $destinations, array $filters): Collection
    {
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query === '') {
            return $destinations;
        }

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = ['di', 'yang', 'ke', 'dari', 'untuk', 'dengan', 'pada', 'dalam', 'serta', 'atau'];
        $tokens = array_values(array_filter(
            array_map(static fn (string $token): string => Str::lower($token), $tokens),
            static fn (string $token): bool => $token !== ''
                && ! in_array($token, $stopWords, true)
                && strlen($token) > 1,
        ));

        if ($tokens === []) {
            return $destinations;
        }

        return $destinations
            ->sortBy(fn (Destination $destination): array => [
                -(float) $this->relevanceScore($destination, $tokens),
                -(float) ($destination->rating ?? -1.0),
                Str::lower($destination->name),
                $destination->id,
            ])
            ->values();
    }

    private function relevanceScore(Destination $destination, array $tokens): float
    {
        $score = 0.0;

        foreach ($tokens as $token) {
            $name = Str::lower($destination->name);
            $alt = Str::lower($destination->alternativeName);
            $location = Str::lower($destination->location);

            if ($name === $token || str_starts_with($name, $token) || $alt === $token || str_starts_with($alt, $token)) {
                $score += 200.0;
            } elseif (str_contains($name, $token)) {
                $score += 120.0;
            } elseif (str_contains($alt, $token)) {
                $score += 100.0;
            } elseif ($location === $token || str_starts_with($location, $token)) {
                $score += 50.0;
            } elseif (str_contains($location, $token)) {
                $score += 30.0;
            }
        }

        return $score + ($destination->rating ?? 0.0);
    }

    private function hasUnmatchableQuery(array $filters): bool
    {
        $query = trim((string) ($filters['q'] ?? ''));

        return $query !== ''
            && (preg_split('/[^\p{L}\p{N}]+/u', Str::lower($query), -1, PREG_SPLIT_NO_EMPTY) ?: []) === [];
    }

    private function integer(mixed $value): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < 0) {
            throw OntologyUnavailableException::badResponse();
        }

        return $integer;
    }

    private function normalizeId(string $id): string
    {
        $id = trim($id);
        if (str_contains($id, '#')) {
            $id = substr($id, (int) strrpos($id, '#') + 1);
        } elseif (str_contains($id, '/')) {
            $id = substr($id, (int) strrpos($id, '/') + 1);
        }

        if (str_contains($id, ':')) {
            $id = substr($id, (int) strrpos($id, ':') + 1);
        }

        return $id;
    }
}
