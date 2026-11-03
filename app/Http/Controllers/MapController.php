<?php

namespace App\Http\Controllers;

use App\Services\Ontology\DestinationRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MapController extends Controller
{
    public function index(Request $request, DestinationRepository $repository): View
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'jenis' => $request->string('jenis')->toString(),
            'provinsi' => $request->string('provinsi')->toString(),
        ];

        if (! in_array($filters['jenis'], DestinationRepository::TYPES, true)) {
            $filters['jenis'] = '';
        }

        if (! in_array($filters['provinsi'], DestinationRepository::PROVINCES, true)) {
            $filters['provinsi'] = '';
        }

        $filters = array_filter($filters, static fn (string $value): bool => $value !== '');
        $matches = $repository->search($filters);
        $results = $matches->take(60);
        $markers = $repository->markers($filters);

        return view('map', [
            'filters' => $filters,
            'facets' => $repository->facets(),
            'results' => $results,
            'matchCount' => $matches->count(),
            'markers' => $markers,
            'types' => DestinationRepository::TYPES,
            'provinces' => DestinationRepository::PROVINCES,
        ]);
    }
}
