<?php

namespace App\Http\Controllers;

use App\Services\Ontology\DestinationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DestinationController extends Controller
{
    public function index(Request $request, DestinationRepository $repository): View
    {
        $sorts = $this->sorts();

        $facets = $repository->facets();
        $filters = $this->filters($request, array_keys($sorts), array_keys($facets['kabupaten']));
        $filters['sort'] ??= 'name';

        $destinations = $repository->paginate($filters)->withQueryString();

        return view('explore', [
            'destinations' => $destinations,
            'filters' => $filters,
            'facets' => $facets,
            'types' => DestinationRepository::TYPES,
            'sorts' => $sorts,
        ]);
    }

    public function live(Request $request, DestinationRepository $repository): JsonResponse
    {
        $sorts = $this->sorts();

        $facets = $repository->facets();
        $filters = $this->filters($request, array_keys($sorts), array_keys($facets['kabupaten']));
        $filters['sort'] ??= 'name';

        $destinations = $repository->paginate($filters)->withQueryString();
        $destinations->setPath(route('destinations'));

        return response()->json([
            'html' => view('partials.destination-results', [
                'destinations' => $destinations,
            ])->render(),
            'total' => $destinations->total(),
            'first_item' => $destinations->firstItem() ?? 0,
            'last_item' => $destinations->lastItem() ?? 0,
            'count' => $destinations->count(),
            'has_filters' => $this->hasActiveFilters($filters),
        ]);
    }

    public function suggest(Request $request, DestinationRepository $repository): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();

        $destinations = $query === ''
            ? collect()
            : $repository->search(['q' => $query, 'sort' => 'rating'])->take(8);

        return response()->json([
            'html' => view('partials.destination-suggestions', [
                'destinations' => $destinations,
            ])->render(),
            'count' => $destinations->count(),
        ]);
    }

    public function show(string $id, DestinationRepository $repository): View
    {
        $destination = $repository->find($id);

        abort_if($destination === null, 404);

        return view('destination-detail', [
            'destination' => $destination,
            'related' => $repository->related($destination, 4),
            'otherTypes' => $repository->otherTypes($destination),
            'crossCategory' => $repository->crossCategory($destination, 4),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function sorts(): array
    {
        return [
            'name' => 'Nama A-Z',
            'price_desc' => 'Harga Tertinggi',
            'price_asc' => 'Harga Terendah',
        ];
    }

    /**
     * @param  list<string>  $sortOptions
     * @param  list<string>  $kabupatenOptions
     * @return array{q?: string, jenis?: string, kabupaten?: string, sort?: string}
     */
    private function filters(Request $request, array $sortOptions, array $kabupatenOptions): array
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'jenis' => $request->string('jenis')->toString(),
            'kabupaten' => $request->string('kabupaten')->toString(),
            'sort' => $request->string('sort')->toString(),
        ];

        if (! in_array($filters['jenis'], DestinationRepository::TYPES, true)) {
            $filters['jenis'] = '';
        }

        if (! in_array($filters['kabupaten'], $kabupatenOptions, true)) {
            $filters['kabupaten'] = '';
        }

        if (! in_array($filters['sort'], $sortOptions, true)) {
            $filters['sort'] = '';
        }

        return array_filter($filters, static fn (string $value): bool => $value !== '');
    }

    /**
     * @param  array{q?: string, jenis?: string, kabupaten?: string, sort?: string}  $filters
     */
    private function hasActiveFilters(array $filters): bool
    {
        return ($filters['q'] ?? '') !== ''
            || ($filters['jenis'] ?? '') !== ''
            || ($filters['kabupaten'] ?? '') !== ''
            || ($filters['sort'] ?? 'name') !== 'name';
    }
}
