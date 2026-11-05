<?php

namespace App\Services\Ontology;

use App\Data\Destination;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DestinationRepository
{
    public const TYPES = ['Alam', 'Budaya', 'Religi', 'Bahari', 'Kuliner', 'Buatan'];

    public const PROVINCES = ['Sumatera Utara', 'Aceh', 'Lainnya'];

    public const SORTS = ['rating', 'rating_asc', 'name', 'id', 'price_desc', 'price_asc'];

    private ?Collection $memo = null;

    public function __construct(
        private FusekiClient $fuseki,
        private TurtleParser $parser,
        private string $driver,
        private string $ttlPath,
        private int $cacheTtl = 600,
    ) {}

    /**
     * @return Collection<int, Destination>
     */
    public function all(): Collection
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $rows = Cache::remember($this->cacheKey(), $this->cacheTtl, fn (): array => $this->loadRows());

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
                fn (Destination $destination): bool => Str::contains($this->searchableText($destination), $query),
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
            'name' => $destinations->sortBy(fn (Destination $destination): string => Str::lower($destination->name)),
            'id' => $destinations->sortBy('id'),
            'rating_asc' => $destinations->sortBy(fn (Destination $destination): float => $destination->rating ?? -1),
            'price_asc' => $destinations->sortBy(fn (Destination $destination): float => $destination->lowestPrice() ?? PHP_FLOAT_MAX),
            'price_desc' => $destinations->sortByDesc(fn (Destination $destination): float => $destination->lowestPrice() ?? -PHP_FLOAT_MAX),
            default => $destinations->sortByDesc(fn (Destination $destination): float => $destination->rating ?? -1),
        };

        return $destinations->values();
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 9): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage('page');
        $destinations = $this->search($filters);

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
        return $this->search(['sort' => 'rating'])->take($limit);
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
            'types' => collect(self::TYPES)
                ->mapWithKeys(fn (string $type): array => [
                    $type => $destinations->filter(fn (Destination $destination): bool => in_array($type, $destination->types, true))->count(),
                ])
                ->all(),
            'provinces' => collect(self::PROVINCES)
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

    public function forgetCache(): void
    {
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

    private function searchableText(Destination $destination): string
    {
        return Str::lower(implode(' ', [
            $destination->id,
            $destination->name,
            $destination->alternativeName,
            $destination->shortDescription,
            implode(' ', $destination->keywords),
            $destination->location,
            $destination->address,
        ]));
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
        $modifiedAt = is_file(base_path($this->ttlPath)) ? (string) filemtime(base_path($this->ttlPath)) : '0';

        return 'ontology.destinations.'.md5($this->driver.'|'.$this->ttlPath.'|'.$modifiedAt);
    }
}
