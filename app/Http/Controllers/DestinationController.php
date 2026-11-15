<?php

namespace App\Http\Controllers;

use App\Services\Ontology\DestinationLinkedDataProvider;
use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\WikidataRegionRepository;
use App\Services\Seo\SeoDataFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DestinationController extends Controller
{
    public function index(Request $request, DestinationRepository $repository, SeoDataFactory $seoDataFactory): View
    {
        $this->validateFilterInputs($request);
        $sorts = $this->sorts();

        $facets = $repository->facets();
        $filters = $this->filters($request, array_keys($sorts), array_keys($facets['kabupaten']));
        if (($filters['q'] ?? '') !== '' && ! array_key_exists('sort', $filters)) {
            $filters['sort'] = 'relevansi';
        } elseif (! array_key_exists('sort', $filters)) {
            $filters['sort'] = 'name';
        }

        $searchPlan = $repository->planSearch(
            $filters['q'] ?? '',
            (bool) ($filters['exact'] ?? false),
        );
        $destinations = $repository->paginate($filters)->withQueryString();

        return view('explore', [
            'destinations' => $destinations,
            'filters' => $filters,
            'facets' => $facets,
            'types' => DestinationRepository::TYPES,
            'sorts' => $sorts,
            'searchPlan' => $searchPlan,
            'seo' => $seoDataFactory->destinations($request, $filters),
        ]);
    }

    public function live(Request $request, DestinationRepository $repository): JsonResponse
    {
        $this->validateFilterInputs($request);
        $sorts = $this->sorts();

        $facets = $repository->facets();
        $filters = $this->filters($request, array_keys($sorts), array_keys($facets['kabupaten']));
        if (($filters['q'] ?? '') !== '' && ! array_key_exists('sort', $filters)) {
            $filters['sort'] = 'relevansi';
        } elseif (! array_key_exists('sort', $filters)) {
            $filters['sort'] = 'name';
        }

        $searchPlan = $repository->planSearch(
            $filters['q'] ?? '',
            (bool) ($filters['exact'] ?? false),
        );
        $destinations = $repository->paginate($filters)->withQueryString();
        $destinations->setPath(route('destinations'));

        return response()->json([
            'html' => view('partials.destination-results', [
                'destinations' => $destinations,
                'searchPlan' => $searchPlan,
                'routeName' => 'destinations',
            ])->render(),
            'total' => $destinations->total(),
            'first_item' => $destinations->firstItem() ?? 0,
            'last_item' => $destinations->lastItem() ?? 0,
            'count' => $destinations->count(),
            'has_filters' => $this->hasActiveFilters($filters),
            'correction' => $searchPlan->correction,
            'suggestions' => $searchPlan->suggestions,
        ]);
    }

    public function suggest(Request $request, DestinationRepository $repository): JsonResponse
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'exact' => ['sometimes', 'nullable', 'boolean'],
        ]);

        $query = $request->string('q')->trim()->toString();
        $exact = $request->boolean('exact');
        $searchPlan = $repository->planSearch($query, $exact);

        $destinations = $query === ''
            ? collect()
            : $repository->search(['q' => $query, 'sort' => 'relevansi', 'exact' => $exact])->take(8);

        return response()->json([
            'html' => view('partials.destination-suggestions', [
                'destinations' => $destinations,
                'searchPlan' => $searchPlan,
                'routeName' => 'destinations',
            ])->render(),
            'count' => $destinations->count(),
            'correction' => $searchPlan->correction,
            'suggestions' => $searchPlan->suggestions,
        ]);
    }

    public function show(
        string $id,
        DestinationRepository $repository,
        DestinationLinkedDataProvider $linkedDataProvider,
        SeoDataFactory $seoDataFactory,
        WikidataRegionRepository $wikidataRegionRepository,
    ): View {
        $destination = $repository->find($id);

        abort_if($destination === null, 404);
        $linkedData = $linkedDataProvider->forDestination($id);
        $wikidataDetails = $wikidataRegionRepository->findForDestinationPage(
            $destination->id,
            $destination->location,
            (bool) config('ontology.wikidata.allow_stale'),
        );

        return view('destination-detail', [
            'destination' => $destination,
            'linkedData' => $linkedData,
            'wikidataDestination' => $wikidataDetails['destination'],
            'nearbyDestinations' => $repository->nearby($destination),
            'seo' => $seoDataFactory->destination($destination),
            'wikidataRegion' => $wikidataDetails['region'],
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
            'relevansi' => 'Paling relevan',
            'name' => 'Name A–Z',
            'rating' => 'Rating: high to low',
            'rating_asc' => 'Rating: low to high',
            'price_desc' => 'Price: high to low',
            'price_asc' => 'Price: low to high',
        ];
    }

    private function validateFilterInputs(Request $request): void
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'jenis' => ['sometimes', 'nullable', 'string', 'max:64'],
            'kabupaten' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:32'],
            'exact' => ['sometimes', 'nullable', 'boolean'],
        ]);
    }

    /**
     * @param  list<string>  $sortOptions
     * @param  list<string>  $kabupatenOptions
     * @return array{q?: string, jenis?: string, kabupaten?: string, sort?: string, exact?: bool}
     */
    private function filters(Request $request, array $sortOptions, array $kabupatenOptions): array
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'jenis' => $request->string('jenis')->toString(),
            'kabupaten' => $request->string('kabupaten')->toString(),
            'sort' => $request->string('sort')->toString(),
            'exact' => $request->boolean('exact'),
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

        if ($filters['exact'] === false) {
            unset($filters['exact']);
        }

        return array_filter($filters, static fn (mixed $value): bool => $value !== '' && $value !== false && $value !== null);
    }

    /**
     * @param  array{q?: string, jenis?: string, kabupaten?: string, sort?: string, exact?: bool}  $filters
     */
    private function hasActiveFilters(array $filters): bool
    {
        return ($filters['q'] ?? '') !== ''
            || ($filters['jenis'] ?? '') !== ''
            || ($filters['kabupaten'] ?? '') !== ''
            || ($filters['sort'] ?? 'name') !== 'name'
            || ($filters['exact'] ?? false);
    }
}
