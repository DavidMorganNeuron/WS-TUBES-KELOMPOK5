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
        $highlights = $repository->topRated(6);
        $featured = $highlights->first();

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
