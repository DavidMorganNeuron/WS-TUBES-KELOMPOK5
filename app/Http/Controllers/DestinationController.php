<?php

namespace App\Http\Controllers;

use App\Services\Ontology\DestinationLinkedDataProvider;
use App\Services\Ontology\DestinationRepository;
<<<<<<< HEAD
use App\Services\Ontology\WikidataRegionRepository;
use App\Services\Seo\SeoDataFactory;
=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
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
        $filterOptions = $repository->filterOptions();
        $filters = $this->filters(
            $request,
            array_keys($sorts),
            array_keys($facets['kabupaten']),
            $filterOptions['activities'],
            $filterOptions['facilities'],
        );
        $searchFilters = $filters;
        if (($filters['q'] ?? '') === '' && ! array_key_exists('sort', $filters)) {
            $searchFilters['sort'] = 'name';
        }

        $searchPlan = $repository->planSearch($filters['q'] ?? '');
        $destinations = $repository->paginate($searchFilters)->withQueryString();

        return view('explore', [
            'destinations' => $destinations,
            'filters' => $filters,
            'facets' => $facets,
            'types' => DestinationRepository::TYPES,
            'sorts' => $sorts,
            'filterOptions' => $filterOptions,
            'searchPlan' => $searchPlan,
            'seo' => $seoDataFactory->destinations($request, $filters),
        ]);
    }

    public function live(Request $request, DestinationRepository $repository): JsonResponse
<<<<<<< HEAD
=======
    {
        $this->validateFilterInputs($request);
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
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

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
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    {
        $this->validateFilterInputs($request);
        $sorts = $this->sorts();

        $facets = $repository->facets();
        $filterOptions = $repository->filterOptions();
        $filters = $this->filters(
            $request,
            array_keys($sorts),
            array_keys($facets['kabupaten']),
            $filterOptions['activities'],
            $filterOptions['facilities'],
        );
        $searchFilters = $filters;
        if (($filters['q'] ?? '') === '' && ! array_key_exists('sort', $filters)) {
            $searchFilters['sort'] = 'name';
        }

        $searchPlan = $repository->planSearch($filters['q'] ?? '');
        $destinations = $repository->paginate($searchFilters)->withQueryString();
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
        ]);

        $query = $request->string('q')->trim()->toString();
        $searchPlan = $repository->planSearch($query);

        $destinations = $query === ''
            ? collect()
            : $repository->search(['q' => $query, 'sort' => 'relevansi'])->take(8);

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
<<<<<<< HEAD
            'dapatMelakukan' => ['sometimes', 'nullable', 'string', 'max:100'],
            'memilikiFasilitas' => ['sometimes', 'nullable', 'string', 'max:100'],
=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        ]);
    }

    /**
     * @param  list<string>  $sortOptions
     * @param  list<string>  $kabupatenOptions
     * @param  list<string>  $activityOptions
     * @param  list<string>  $facilityOptions
     * @return array{q?: string, jenis?: string, kabupaten?: string, sort?: string, dapatMelakukan?: string, memilikiFasilitas?: string}
     */
    private function filters(
        Request $request,
        array $sortOptions,
        array $kabupatenOptions,
        array $activityOptions,
        array $facilityOptions,
    ): array {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'jenis' => $request->string('jenis')->toString(),
            'kabupaten' => $request->string('kabupaten')->toString(),
            'sort' => $request->string('sort')->toString(),
            'dapatMelakukan' => $request->string('dapatMelakukan')->trim()->toString(),
            'memilikiFasilitas' => $request->string('memilikiFasilitas')->trim()->toString(),
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

        $filters['dapatMelakukan'] = $this->matchingOption($filters['dapatMelakukan'], $activityOptions);
        $filters['memilikiFasilitas'] = $this->matchingOption($filters['memilikiFasilitas'], $facilityOptions);

        return array_filter($filters, static fn (mixed $value): bool => $value !== '' && $value !== null);
    }

    /**
     * @param  list<string>  $options
     */
    private function matchingOption(string $value, array $options): string
    {
        foreach ($options as $option) {
            if (mb_strtolower($value, 'UTF-8') === mb_strtolower($option, 'UTF-8')) {
                return $option;
            }
        }

        return '';
    }

    /**
     * @param  array{q?: string, jenis?: string, kabupaten?: string, sort?: string, dapatMelakukan?: string, memilikiFasilitas?: string}  $filters
     */
    private function hasActiveFilters(array $filters): bool
    {
        return ($filters['q'] ?? '') !== ''
            || ($filters['jenis'] ?? '') !== ''
            || ($filters['kabupaten'] ?? '') !== ''
            || ($filters['dapatMelakukan'] ?? '') !== ''
            || ($filters['memilikiFasilitas'] ?? '') !== ''
            || ($filters['sort'] ?? 'name') !== 'name';
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
