<?php

namespace App\Services\Ontology;

use App\Data\Destination;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class InMemoryDestinationSource implements DestinationSource
{
    private ?Collection $memo = null;

    private ?string $memoCacheKey = null;

    public function __construct(
        private TurtleParser $parser,
        private OntologyGraphMapper $graphMapper,
        private string $ttlPath,
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

        $rows = Cache::remember($cacheKey, $this->cacheTtl, fn (): array => $this->loadRows());
        $this->memoCacheKey = $cacheKey;

        return $this->memo = $this->mapRows(is_array($rows) ? $rows : []);
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null}  $filters
     * @return Collection<int, Destination>
     */
    public function search(array $filters = []): Collection
    {
        $query = Str::lower(trim((string) ($filters['q'] ?? '')));
        $type = $this->validType($filters['jenis'] ?? null);
        $kabupaten = trim((string) ($filters['kabupaten'] ?? ''));
        $province = $this->validProvince($filters['provinsi'] ?? null);

        $destinations = $this->all()
            ->when($query !== '', fn (Collection $items): Collection => $items->filter(
                fn (Destination $destination): bool => $this->matchesWordPrefix($destination, $query),
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

        $sort = in_array($filters['sort'] ?? null, DestinationRepository::SORTS, true)
            ? $filters['sort']
            : 'rating';

        $destinations = match ($sort) {
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
                $provinceBonus = $item->province() === $destination->province() ? 5.0 : 0.0;

                return ($overlap * 100) + ($item->rating ?? 0) + $provinceBonus;
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

        $score = fn (Destination $item): float => ($item->province() === $destination->province() ? 5.0 : 0.0) + ($item->rating ?? 0);

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
        if (! $destination->hasCoordinates()) {
            return collect();
        }

        $radius = (float) config('ontology.nearby.radius_km', 15.0);
        $maximum = (int) config('ontology.nearby.max_neighbors', 3);
        $limit = min($maximum, max(1, $limit ?? $maximum));

        return $this->all()
            ->reject(fn (Destination $candidate): bool => $candidate->id === $destination->id)
            ->filter(fn (Destination $candidate): bool => $candidate->hasCoordinates())
            ->map(fn (Destination $candidate): array => [
                'destination' => $candidate,
                'distance' => $this->distance($destination, $candidate),
            ])
            ->filter(fn (array $candidate): bool => $candidate['distance'] <= $radius)
            ->sortBy(fn (array $candidate): array => [
                $candidate['distance'],
                Str::lower($candidate['destination']->name),
                $candidate['destination']->id,
            ])
            ->take($limit)
            ->pluck('destination')
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

    private function distance(Destination $origin, Destination $destination): float
    {
        $earthRadiusKm = 6371.0;
        $latitudeDelta = deg2rad((float) $destination->latitude - (float) $origin->latitude);
        $longitudeDelta = deg2rad((float) $destination->longitude - (float) $origin->longitude);
        $latitudeOrigin = deg2rad((float) $origin->latitude);
        $latitudeDestination = deg2rad((float) $destination->latitude);
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos($latitudeOrigin) * cos($latitudeDestination) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * asin(min(1.0, sqrt($haversine)));
    }
}
