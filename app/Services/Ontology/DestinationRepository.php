<?php

namespace App\Services\Ontology;

use App\Data\Destination;
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
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null}  $filters
     * @return Collection<int, Destination>
     */
    public function search(array $filters = []): Collection
    {
        return $this->source->search($filters);
    }

    /**
     * @param  array{q?: string|null, jenis?: string|null, kabupaten?: string|null, provinsi?: string|null, sort?: string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 9): LengthAwarePaginator
    {
        return $this->source->paginate($filters, $perPage);
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
        return $this->source->topRated($limit);
    }

    /**
     * @return Collection<int, Destination>
     */
    public function related(Destination $destination, int $limit = 4): Collection
    {
        return $this->source->related($destination, $limit);
    }

    /**
     * @return list<string>
     */
    public function otherTypes(Destination $destination): array
    {
        return array_values(array_diff(self::TYPES, $destination->types));
    }

    /**
     * @return Collection<int, Destination>
     */
    public function crossCategory(Destination $destination, int $limit = 4): Collection
    {
        return $this->source->crossCategory($destination, $limit);
    }

    /**
     * @param  array<string, mixed>  $filters
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
        $this->source->forgetCache();
    }
}
