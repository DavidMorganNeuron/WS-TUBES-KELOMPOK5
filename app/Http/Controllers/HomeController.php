<?php

namespace App\Http\Controllers;

use App\Data\Destination;
use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\WikidataRegionRepository;
use App\Services\Seo\SeoDataFactory;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(
        DestinationRepository $repository,
        WikidataRegionRepository $wikidataRegionRepository,
        SeoDataFactory $seoDataFactory,
    ): View {
        $dashboard = $repository->dashboard();
        $candidates = $dashboard['top_rated'];
        $topRegion = $dashboard['top_region'];
        unset($dashboard['top_rated'], $dashboard['top_region']);

        $facets = $dashboard;
        $facets['types'] = array_filter($facets['types'], static fn (int $count): bool => $count > 0);
        $facets['provinces'] = array_filter($facets['provinces'], static fn (int $count): bool => $count > 0);

        $featured = $candidates->first(
            static fn (Destination $destination): bool => $destination->media !== '' && $destination->shortDescription !== '',
        ) ?? $candidates->first();

        $highlights = $candidates
            ->reject(static fn (Destination $destination): bool => $destination->id === $featured?->id)
            ->take(4)
            ->values();

        return view('home', [
            'facets' => $facets,
            'featured' => $featured,
            'highlights' => $highlights,
            'topRegion' => $topRegion,
            'wikidataRegion' => $topRegion === null
                ? null
                : $wikidataRegionRepository->findForRegion(
                    $topRegion['label'],
                    (bool) config('ontology.wikidata.allow_stale'),
                ),
            'provinces' => $facets['provinces'],
            'seo' => $seoDataFactory->home(),
        ]);
    }
}
