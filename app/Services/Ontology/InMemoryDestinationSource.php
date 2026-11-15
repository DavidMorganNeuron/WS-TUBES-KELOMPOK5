<?php

namespace App\Services\Ontology;

use App\Data\Destination;
use App\Data\SearchPlan;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class InMemoryDestinationSource implements DestinationSource
{
    private ?Collection $memo = null;

    private ?string $memoCacheKey = null;

    public function __construct(
        private TurtleParser $parser,
        private OntologyGraphMapper $graphMapper,
        private string $ttlPath,
        private SearchPlanner $searchPlanner,
        private int $cacheTtl = 600,
    ) {}

    /**
     * @return Collection<int, Destination>
     */
    public function all(): Collection
    {
        $cacheKey = $this->cacheKey();

        if ($this->memo !== null && $this->memoCacheKey === $cacheKey) {
            return $this->memo;
        }

        try {
            $cachedRows = Cache::get($cacheKey);
        } catch (Throwable $exception) {
            Log::warning('Cache data ontologi tidak tersedia; data dimuat langsung.', [
                'exception' => $exception::class,
            ]);

            $cachedRows = null;
        }

        if (is_array($cachedRows)) {
            $rows = $cachedRows;
        } else {
            $rows = $this->loadRows();
            try {
                Cache::put($cacheKey, $rows, $this->cacheTtl);
            } catch (Throwable $exception) {
                Log::warning('Data ontologi tidak dapat disimpan ke cache.', [
                    'exception' => $exception::class,
                ]);
            }
        }

        $this->memoCacheKey = $cacheKey;

        return $this->memo = $this->mapRows($rows);
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null, exact?: bool|int|string|null}  $filters
     * @return Collection<int, Destination>
     */
    public function search(array $filters = []): Collection
    {
        $query = trim((string) ($filters['q'] ?? ''));
        $type = $this->validType($filters['jenis'] ?? null);
        $kabupaten = trim((string) ($filters['kabupaten'] ?? ''));
        $province = $this->validProvince($filters['provinsi'] ?? null);
        $plan = $this->planSearch($query, filter_var($filters['exact'] ?? false, FILTER_VALIDATE_BOOLEAN));
        if ($query !== '' && $plan->tokens === []) {
            return collect();
        }

        $tokens = $plan->tokens;
        $normalizedQuery = $query !== '' ? implode(' ', $tokens) : '';

        $destinations = $this->all()
            ->when($normalizedQuery !== '', fn (Collection $items): Collection => $items->filter(
                fn (Destination $destination): bool => $this->matchesAllTokens($destination, $tokens),
            ))
            ->when($type !== null, fn (Collection $items): Collection => $items->filter(
                fn (Destination $destination): bool => in_array($type, $destination->types, true),
            ))
            ->when($kabupaten !== '', fn (Collection $items): Collection => $items->filter(
                fn (Destination $destination): bool => $destination->location === $kabupaten,
            ))
            ->when($province !== null, fn (Collection $items): Collection => $items->filter(
                fn (Destination $destination): bool => $destination->province() === $province,
            ));

        $requestedSort = $filters['sort'] ?? null;
        $sort = in_array($requestedSort, DestinationRepository::SORTS, true)
            ? $requestedSort
            : ($query !== '' ? 'relevansi' : 'rating');

        $destinations = match ($sort) {
            'relevansi' => $destinations->sortBy(fn (Destination $destination): array => [
                -(float) $this->relevanceScore($destination, $tokens),
                -(float) ($destination->rating ?? -1.0),
                Str::lower($destination->name),
                $destination->id,
            ]),
            'name' => $destinations->sortBy(fn (Destination $destination): array => [
                Str::lower($destination->name),
                $destination->id,
            ]),
            'id' => $destinations->sortBy('id'),
            'rating_asc' => $destinations->sortBy(fn (Destination $destination): array => [
                $destination->rating ?? PHP_FLOAT_MAX,
                Str::lower($destination->name),
            ]),
            'price_asc' => $destinations->sortBy(fn (Destination $destination): array => [
                $destination->lowestPrice() ?? PHP_FLOAT_MAX,
                Str::lower($destination->name),
            ]),
            'price_desc' => $destinations->sortBy(fn (Destination $destination): array => [
                -($destination->lowestPrice() ?? -PHP_FLOAT_MAX),
                Str::lower($destination->name),
            ]),
            default => $destinations->sortBy(fn (Destination $destination): array => [
                -($destination->rating ?? -1),
                Str::lower($destination->name),
            ]),
        };

        return $destinations->values();
    }

    public function planSearch(string $query, bool $exact = false): SearchPlan
    {
        return $this->searchPlanner->plan($query, fn (): array => $this->searchableTexts(), $exact);
    }

    /**
     * @return list<string>
     */
    public function searchableTexts(): array
    {
        $texts = [];
        foreach ($this->all() as $destination) {
            foreach ($this->searchableFields($destination) as $text) {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function matchesAllTokens(Destination $destination, array $tokens): bool
    {
        if ($tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            if (! $this->matchesToken($destination, $token)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function relevanceScore(Destination $destination, array $tokens): float
    {
        if ($tokens === []) {
            return 0.0;
        }

        $weights = config('ontology.search.relevance_weights', []);
        $score = 0.0;
        foreach ($tokens as $token) {
            if (Str::lower($destination->name) === $token) {
                $score += (int) ($weights['name_exact'] ?? 1100);
            } elseif (str_starts_with(Str::lower($destination->name), $token)) {
                $score += (int) ($weights['name_prefix'] ?? 1000);
            } elseif ($this->fieldMatches($destination->name, $token, false)) {
                $score += (int) ($weights['name_word'] ?? 900);
            } elseif ($this->fieldMatches($destination->alternativeName, $token, false)
                || $this->fieldMatches(implode(' ', array_merge($destination->keywords, $destination->facilities, $destination->activities)), $token, false)) {
                $score += (int) ($weights['alias_keyword'] ?? 800);
            } elseif ($this->fieldMatches($destination->location, $token, false)) {
                $score += (int) ($weights['locality'] ?? 600);
            } elseif ($this->fieldMatches($destination->address, $token, false)) {
                $score += (int) ($weights['address'] ?? 400);
            } elseif ($this->fieldMatches($destination->shortDescription.' '.$destination->fullDescription.' '.$destination->directions, $token, false)) {
                $score += (int) ($weights['description'] ?? 200);
            }
        }

        return $score;
    }

    private function fieldMatches(string $field, string $token, bool $exact): bool
    {
        $field = Str::lower($field);
        if ($exact) {
            return $field === $token;
        }

        $pattern = '/(^|[^\p{L}\p{N}])'.preg_quote($token, '/').'/u';

        return preg_match($pattern, $field) === 1;
    }

    /**
     * @return list<string>
     */
    private function searchableFields(Destination $destination): array
    {
        return [
            $destination->name,
            $destination->alternativeName,
            $destination->shortDescription,
            $destination->fullDescription,
            $destination->location,
            $destination->address,
            $destination->directions,
            implode(' ', $destination->keywords),
            implode(' ', $destination->facilities),
            implode(' ', $destination->activities),
        ];
    }

    private function matchesToken(Destination $destination, string $token): bool
    {
        foreach ($this->searchableFields($destination) as $field) {
            $pattern = '/(^|[^\p{L}\p{N}])'.preg_quote($token, '/').'/u';
            if (preg_match($pattern, Str::lower($field)) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 9): LengthAwarePaginator
    {
        $destinations = $this->search($filters);
        $lastPage = max(1, (int) ceil($destinations->count() / $perPage));
        $page = min(max(1, LengthAwarePaginator::resolveCurrentPage('page')), $lastPage);

        return new LengthAwarePaginator(
            $destinations->forPage($page, $perPage)->values(),
            $destinations->count(),
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
        $normalized = Str::lower($this->normalizeId($id));

        return $this->all()->first(
            fn (Destination $destination): bool => Str::lower($destination->id) === $normalized,
        );
    }

    /**
     * @return Collection<int, Destination>
     */
    public function topRated(int $limit = 6): Collection
    {
        return $this->search(['sort' => 'rating'])
            ->filter(fn (Destination $destination): bool => $destination->rating !== null)
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, Destination>
     */
    public function related(Destination $destination, int $limit = 4): Collection
    {
        return $this->all()
            ->reject(fn (Destination $item): bool => $item->id === $destination->id)
            ->sortByDesc(function (Destination $item) use ($destination): float {
                $overlap = count(array_intersect($item->types, $destination->types));

                return ($overlap * 100) + ($item->rating ?? 0);
            })
            ->take($limit)
            ->values();
    }

    /**
     * Rekomendasi dari kategori lain, dibagi rata per kategori.
     *
     * @return Collection<int, Destination>
     */
    public function crossCategory(Destination $destination, int $limit = 4): Collection
    {
        $otherTypes = array_values(array_diff(DestinationRepository::TYPES, $destination->types));

        if ($otherTypes === []) {
            return collect();
        }

        $score = fn (Destination $item): float => $item->rating ?? 0;

        // Kandidat: bukan destinasi ini dan tidak punya kategori yang sama dengan destinasi utama
        $candidates = $this->all()->filter(
            fn (Destination $item): bool => $item->id !== $destination->id
                && array_intersect($item->types, $destination->types) === [],
        );

        // Satu destinasi terbaik per kategori dulu (round-robin), supaya semua kategori terwakili
        $perType = [];
        foreach ($otherTypes as $type) {
            $perType[$type] = $candidates
                ->filter(fn (Destination $item): bool => in_array($type, $item->types, true))
                ->sortByDesc($score)
                ->values();
        }

        $result = collect();
        $round = 0;

        while ($result->count() < $limit) {
            $added = false;

            foreach ($otherTypes as $type) {
                $item = $perType[$type][$round] ?? null;

                if ($item !== null && ! $result->contains('id', $item->id)) {
                    $result->push($item);
                    $added = true;

                    if ($result->count() >= $limit) {
                        break;
                    }
                }
            }

            if (! $added && $round > $candidates->count()) {
                break;
            }

            $round++;
        }

        return $result->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function markers(array $filters = []): array
    {
        return $this->search($filters)
            ->filter(fn (Destination $destination): bool => $destination->hasCoordinates())
            ->map(fn (Destination $destination): array => $destination->toMapMarker())
            ->values()
            ->all();
    }

    /**
     * @return array{total: int, types: array<string, int>, provinces: array<string, int>, kabupaten: array<string, int>, average_rating: float|null, rated_count: int, with_coordinates: int, with_media: int}
     */
    public function facets(): array
    {
        $destinations = $this->all();
        $ratings = $destinations->pluck('rating')->filter(fn (mixed $rating): bool => $rating !== null);

        return [
            'total' => $destinations->count(),
            'types' => collect(DestinationRepository::TYPES)
                ->mapWithKeys(fn (string $type): array => [
                    $type => $destinations->filter(fn (Destination $destination): bool => in_array($type, $destination->types, true))->count(),
                ])
                ->all(),
            'provinces' => collect(DestinationRepository::PROVINCES)
                ->mapWithKeys(fn (string $province): array => [
                    $province => $destinations->filter(fn (Destination $destination): bool => $destination->province() === $province)->count(),
                ])
                ->all(),
            'kabupaten' => $destinations
                ->filter(fn (Destination $destination): bool => Str::startsWith($destination->location, ['Kabupaten ', 'Kota ']))
                ->countBy('location')
                ->sortKeys()
                ->all(),
            'average_rating' => $ratings->isEmpty() ? null : round((float) $ratings->avg(), 2),
            'rated_count' => $ratings->count(),
            'with_coordinates' => $destinations->filter(fn (Destination $destination): bool => $destination->hasCoordinates())->count(),
            'with_media' => $destinations->filter(fn (Destination $destination): bool => $destination->media !== '')->count(),
        ];
    }

    /**
     * @return array{total: int, types: array<string, int>, provinces: array<string, int>, kabupaten: array<string, int>, average_rating: float|null, rated_count: int, with_coordinates: int, with_media: int, top_rated: Collection<int, Destination>, top_region: array{label: string, count: int}|null}
     */
    public function dashboard(): array
    {
        $facets = $this->facets();
        $districtLabels = array_keys($facets['kabupaten']);
        usort($districtLabels, static fn (string $left, string $right): int => (
            $facets['kabupaten'][$right] <=> $facets['kabupaten'][$left]
        ) ?: strcmp($left, $right));
        $topDistrict = $districtLabels[0] ?? null;

        return $facets + [
            'top_rated' => $this->topRated(5),
            'top_region' => $topDistrict === null ? null : [
                'label' => $topDistrict,
                'count' => $facets['kabupaten'][$topDistrict],
            ],
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
        $links = $this->nearbyLinks();
        $ids = $links[$destination->id] ?? [];
        if ($limit !== null) {
            $ids = array_slice($ids, 0, max(0, $limit));
        }

        $destinations = $this->all()->keyBy('id');

        return collect($ids)
            ->map(static fn (string $id): ?Destination => $destinations->get($id))
            ->filter(static fn (?Destination $item): bool => $item !== null)
            ->values();
    }

    /**
     * @return Collection<int, Destination>
     */
    public function similarByCategory(Destination $destination, int $limit = 4): Collection
    {
        if ($destination->types === []) {
            return collect();
        }

        return $this->all()
            ->reject(fn (Destination $candidate): bool => $candidate->id === $destination->id)
            ->map(fn (Destination $candidate): array => [
                'destination' => $candidate,
                'overlap' => count(array_intersect($candidate->types, $destination->types)),
            ])
            ->filter(static fn (array $candidate): bool => $candidate['overlap'] > 0)
            ->sortBy(fn (array $candidate): array => [
                -$candidate['overlap'],
                -($candidate['destination']->rating ?? -1),
                Str::lower($candidate['destination']->name),
                $candidate['destination']->id,
            ])
            ->take(max(0, $limit))
            ->pluck('destination')
            ->values();
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
        Cache::forget($this->cacheKey());
        $this->memo = null;
        $this->memoCacheKey = null;
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function loadRows(): array
    {
        return $this->graphMapper->destinations(
            $this->parser->parseTriplesFile($this->ttlPath),
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function nearbyLinks(): array
    {
        $cacheKey = $this->cacheKey();

        return Cache::remember(
            'ontology.nearby-links.'.hash('sha256', $cacheKey),
            $this->cacheTtl,
            function (): array {
                $dataNamespace = (string) config('ontology.namespaces.data');
                $nearbyPredicate = (string) config('ontology.namespaces.ws').'dekatDengan';
                $links = [];

                foreach ($this->parser->parseTriplesFile($this->ttlPath) as $triple) {
                    if (($triple['predicate'] ?? null) !== $nearbyPredicate
                        || ($triple['object']['kind'] ?? null) !== 'iri') {
                        continue;
                    }

                    $sourceId = $this->destinationIdFromIri((string) ($triple['subject'] ?? ''), $dataNamespace);
                    $neighborId = $this->destinationIdFromIri((string) $triple['object']['value'], $dataNamespace);
                    if ($sourceId !== null && $neighborId !== null && $sourceId !== $neighborId) {
                        $links[$sourceId][$neighborId] = true;
                    }
                }

                foreach ($links as $sourceId => $neighborIds) {
                    $neighborIds = array_keys($neighborIds);
                    sort($neighborIds, SORT_STRING);
                    $links[$sourceId] = $neighborIds;
                }

                ksort($links, SORT_STRING);

                return $links;
            },
        );
    }

    private function destinationIdFromIri(string $iri, string $namespace): ?string
    {
        if (! str_starts_with($iri, $namespace)) {
            return null;
        }

        $id = substr($iri, strlen($namespace));

        return preg_match('/^ws-\d{3}$/', $id) === 1 ? $id : null;
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return Collection<int, Destination>
     */
    private function mapRows(array $rows): Collection
    {
        $grouped = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (($row['idDestinasi'] ?? '') === '' && ($row['id'] ?? '') === '' && isset($row['s'])) {
                $row['idDestinasi'] = $this->normalizeId((string) $row['s']);
            }

            $id = trim((string) ($row['idDestinasi'] ?? $row['id'] ?? ''));

            if ($id === '') {
                continue;
            }

            $row['idDestinasi'] = $id;

            $grouped[$id] = isset($grouped[$id])
                ? $this->mergeRow($grouped[$id], $row)
                : $row;
        }

        return collect($grouped)
            ->map(fn (array $row): Destination => Destination::fromArray($row))
            ->filter(fn (Destination $destination): bool => $destination->name !== '')
            ->sortBy('id')
            ->values();
    }

    /**
     * @param  array<string, string|null>  $existing
     * @param  array<string, string|null>  $incoming
     * @return array<string, string|null>
     */
    private function mergeRow(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (($existing[$key] ?? '') === '') {
                $existing[$key] = $value;

                continue;
            }

            if ($existing[$key] !== $value && ! str_contains((string) $existing[$key], (string) $value)) {
                $existing[$key] .= ' | '.$value;
            }
        }

        return $existing;
    }

    /**
     * Cocokkan setiap kata pada query sebagai awalan salah satu kata nama destinasi.
     */
    private function matchesWordPrefix(Destination $destination, string $query): bool
    {
        $queryWords = preg_split('/[^\p{L}\p{N}]+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($queryWords === []) {
            return false;
        }

        $nameWords = preg_split(
            '/[^\p{L}\p{N}]+/u',
            Str::lower($destination->name.' '.$destination->alternativeName),
            -1,
            PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        foreach ($queryWords as $queryWord) {
            $matches = false;

            foreach ($nameWords as $nameWord) {
                if (str_starts_with($nameWord, $queryWord)) {
                    $matches = true;

                    break;
                }
            }

            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    private function validType(mixed $value): ?string
    {
        return is_string($value) && in_array($value, DestinationRepository::TYPES, true) ? $value : null;
    }

    private function validProvince(mixed $value): ?string
    {
        return is_string($value) && in_array($value, DestinationRepository::PROVINCES, true) ? $value : null;
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

    private function cacheKey(): string
    {
        $path = preg_match('/^[A-Za-z]:[\\\\\\/]/', $this->ttlPath) === 1 || str_starts_with($this->ttlPath, DIRECTORY_SEPARATOR)
            ? $this->ttlPath
            : base_path($this->ttlPath);
        $modifiedAt = is_file($path) ? (string) filemtime($path) : '0';
        $fileSize = is_file($path) ? (string) filesize($path) : '0';
        $version = Cache::get(OntologyDataVersion::CACHE_KEY, 'initial');

        return 'ontology.destinations.'.hash(
            'sha256',
            'ttl|'.$this->ttlPath.'|'.$modifiedAt.'|'.$fileSize.'|'.$version,
        );
    }
}
