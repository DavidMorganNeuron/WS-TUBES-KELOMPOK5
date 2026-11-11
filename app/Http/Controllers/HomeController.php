<?php

namespace App\Http\Controllers;

use App\Data\Destination;
use App\Services\Ontology\DestinationRepository;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(DestinationRepository $repository): View
    {
        $facets = $repository->facets();
        $facets['types'] = array_filter($facets['types'], static fn (int $count): bool => $count > 0);
        $facets['provinces'] = array_filter($facets['provinces'], static fn (int $count): bool => $count > 0);

        $candidates = $repository->topRated(12);
        $featured = $candidates->first(
            static fn (Destination $destination): bool => $destination->media !== '' && $destination->shortDescription !== '',
        ) ?? $candidates->first();

        $highlights = $candidates
            ->reject(static fn (Destination $destination): bool => $destination->id === $featured?->id)
            ->take(4)
            ->values();

        $categoryHighlights = collect(DestinationRepository::TYPES)
            ->map(function (string $type) use ($repository, $facets): array {
                /** @var Destination|null $topDestination */
                $topDestination = $repository->search(['jenis' => $type, 'sort' => 'rating'])->first();

                return [
                    'type' => $type,
                    'count' => $facets['types'][$type] ?? 0,
                    'destination' => $topDestination,
                ];
            })
            ->filter(static fn (array $category): bool => $category['count'] > 0)
            ->values()
            ->all();

        return view('home', [
            'facets' => $facets,
            'featured' => $featured,
            'highlights' => $highlights,
            'categoryHighlights' => $categoryHighlights,
            'provinces' => $facets['provinces'],
        ]);
    }
}
