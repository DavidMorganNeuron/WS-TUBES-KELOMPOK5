<?php

namespace App\Services\Ontology;

use App\Data\Destination;
use App\Data\SearchPlan;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class DestinationRepository
{
    public const TYPES = ['Alam', 'Budaya', 'Religi', 'Bahari', 'Kuliner', 'Buatan'];

    public const PROVINCES = ['Sumatera Utara'];

    public const SORTS = ['relevansi', 'rating', 'rating_asc', 'name', 'id', 'price_desc', 'price_asc'];

    public function __construct(private DestinationSource $source) {}

    /**
     * @return Collection<int, Destination>
     */
    public function all(): Collection
    {
        return $this->source->all();
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null, dapatMelakukan?: string|null, memilikiFasilitas?: string|null, exact?: bool|int|string|null}  $filters
     * @return Collection<int, Destination>
     */
    public function search(array $filters = []): Collection
    {
        return $this->source->search($filters);
    }

<<<<<<< HEAD
    public function planSearch(string $query, bool $exact = false): SearchPlan
    {
        return $this->source->planSearch($query, $exact);
=======
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

        $sort = in_array($filters['sort'] ?? null, self::SORTS, true)
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
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null, dapatMelakukan?: string|null, memilikiFasilitas?: string|null, exact?: bool|int|string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 9): LengthAwarePaginator
    {
<<<<<<< HEAD
        return $this->source->paginate($filters, $perPage);
=======
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
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    }

    public function find(string $id): ?Destination
    {
        return $this->source->find($id);
    }

    /**
     * @return Collection<int, Destination>
     */
    public function topRated(int $limit = 6): Collection
    {
<<<<<<< HEAD
        return $this->source->topRated($limit);
=======
        return $this->search(['sort' => 'rating'])
            ->filter(fn (Destination $destination): bool => $destination->rating !== null)
            ->take($limit)
            ->values();
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    }

    /**
     * @return Collection<int, Destination>
     */
    public function related(Destination $destination, int $limit = 4): Collection
    {
        return $this->source->related($destination, $limit);
    }

    /**
<<<<<<< HEAD
=======
     * Kategori yang TIDAK dimiliki destinasi ini.
     *
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
     * @return list<string>
     */
    public function otherTypes(Destination $destination): array
    {
        return array_values(array_diff(self::TYPES, $destination->types));
    }

    /**
<<<<<<< HEAD
=======
     * Rekomendasi dari kategori lain, dibagi rata per kategori.
     *
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
     * @return Collection<int, Destination>
     */
    public function crossCategory(Destination $destination, int $limit = 4): Collection
    {
<<<<<<< HEAD
        return $this->source->crossCategory($destination, $limit);
    }

    /**
     * @param  array<string, mixed>  $filters
=======
        $otherTypes = $this->otherTypes($destination);

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
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
     * @return list<array<string, mixed>>
     */
    public function markers(array $filters = []): array
    {
        return $this->source->markers($filters);
    }

    /**
     * @return array{total: int, types: array<string, int>, provinces: array<string, int>, kabupaten: array<string, int>, average_rating: float|null, rated_count: int, with_coordinates: int, with_media: int}
     */
    public function facets(): array
    {
        return $this->source->facets();
    }

    /**
     * @return array{activities: list<string>, facilities: list<string>}
     */
    public function filterOptions(): array
    {
        return $this->source->filterOptions();
    }

    /**
     * @return array{total: int, types: array<string, int>, provinces: array<string, int>, kabupaten: array<string, int>, average_rating: float|null, rated_count: int, with_coordinates: int, with_media: int, top_rated: Collection<int, Destination>, top_region: array{label: string, count: int}|null}
     */
    public function dashboard(): array
    {
        return $this->source->dashboard();
    }

    /**
     * @return array{total: int, rated: int, unrated: int, average_rating: float|null, with_coordinates: int, with_media: int}
     */
    public function statistics(): array
    {
        return $this->source->statistics();
    }

    /**
     * @return Collection<int, Destination>
     */
    public function nearby(Destination $destination, ?int $limit = null): Collection
    {
        return $this->source->nearby($destination, $limit);
    }

    /**
     * @return Collection<int, Destination>
     */
    public function similarByCategory(Destination $destination, int $limit = 4): Collection
    {
        return $this->source->similarByCategory($destination, $limit);
    }

    /**
     * @param  array{q?: string, jenis?: string, kabupaten?: string, provinsi?: string}  $criteria
     * @return list<array{id: string, name: string, types: list<string>, location: string, rating: float|null, price: float|null}>
     */
    public function retrieveForContext(array $criteria = [], int $limit = 5): array
    {
        return $this->source->retrieveForContext($criteria, $limit);
    }

    public function forgetCache(): void
    {
<<<<<<< HEAD
        $this->source->forgetCache();
=======
        Cache::forget($this->cacheKey());
        $this->memo = null;
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function loadRows(): array
    {
        if (in_array($this->driver, ['auto', 'fuseki'], true)) {
            try {
                $rows = $this->fuseki->fetchDestinations();

                if ($rows !== []) {
                    return $rows;
                }

                if ($this->driver === 'fuseki') {
                    throw new RuntimeException('Fuseki mengembalikan dataset kosong.');
                }
            } catch (Throwable $exception) {
                if ($this->driver === 'fuseki') {
                    throw $exception;
                }

                Log::warning('Fuseki tidak dapat diakses, beralih ke file Turtle lokal.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $this->parser->parseFile($this->ttlPath);
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
        return is_string($value) && in_array($value, self::TYPES, true) ? $value : null;
    }

    private function validProvince(mixed $value): ?string
    {
        return is_string($value) && in_array($value, self::PROVINCES, true) ? $value : null;
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

        return 'ontology.destinations.'.md5($this->driver.'|'.$this->ttlPath.'|'.$modifiedAt);
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    }
}
