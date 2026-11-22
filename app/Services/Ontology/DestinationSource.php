<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use App\Data\Destination;
use App\Data\SearchPlan;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DestinationSource
{
    /**
     * @return Collection<int, Destination>
     */
    public function all(): Collection;

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null, dapatMelakukan?: string|null, memilikiFasilitas?: string|null, exact?: bool|int|string|null}  $filters
     * @return Collection<int, Destination>
     */
    public function search(array $filters = []): Collection;

    public function planSearch(string $query, bool $exact = false): SearchPlan;

    /**
     * @return list<string>
     */
    public function searchableTexts(): array;

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null, dapatMelakukan?: string|null, memilikiFasilitas?: string|null, exact?: bool|int|string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 9): LengthAwarePaginator;

    public function find(string $id): ?Destination;

    /**
     * @return Collection<int, Destination>
     */
    public function topRated(int $limit = 6): Collection;

    /**
     * @return Collection<int, Destination>
     */
    public function related(Destination $destination, int $limit = 4): Collection;

    /**
     * @return Collection<int, Destination>
     */
    public function crossCategory(Destination $destination, int $limit = 4): Collection;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function markers(array $filters = []): array;

    /**
     * @return array{total: int, types: array<string, int>, provinces: array<string, int>, kabupaten: array<string, int>, average_rating: float|null, rated_count: int, with_coordinates: int, with_media: int}
     */
    public function facets(): array;

    /**
     * @return array{activities: list<string>, facilities: list<string>}
     */
    public function filterOptions(): array;

    /**
     * @return array{total: int, types: array<string, int>, provinces: array<string, int>, kabupaten: array<string, int>, average_rating: float|null, rated_count: int, with_coordinates: int, with_media: int, top_rated: Collection<int, Destination>, top_region: array{label: string, count: int}|null}
     */
    public function dashboard(): array;

    /**
     * @return array{total: int, rated: int, unrated: int, average_rating: float|null, with_coordinates: int, with_media: int}
     */
    public function statistics(): array;

    /**
     * @return Collection<int, Destination>
     */
    public function nearby(Destination $destination, ?int $limit = null): Collection;

    /**
     * @return Collection<int, Destination>
     */
    public function similarByCategory(Destination $destination, int $limit = 4): Collection;

    /**
     * @param  array{q?: string, jenis?: string, kabupaten?: string, provinsi?: string}  $criteria
     * @return list<array{id: string, name: string, types: list<string>, location: string, rating: float|null, price: float|null}>
     */
    public function retrieveForContext(array $criteria = [], int $limit = 5): array;

    public function forgetCache(): void;
}
