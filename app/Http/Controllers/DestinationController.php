<?php

namespace App\Http\Controllers;

use App\Services\Ontology\DestinationRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DestinationController extends Controller
{
    public function index(Request $request, DestinationRepository $repository): View
    {
        $filters = $this->filters($request);
        $destinations = $repository->paginate($filters)->withQueryString();

        return view('explore', [
            'destinations' => $destinations,
            'filters' => $filters,
            'facets' => $repository->facets(),
            'types' => DestinationRepository::TYPES,
            'provinces' => DestinationRepository::PROVINCES,
            'sorts' => [
                'rating' => 'Rating tertinggi',
                'rating_asc' => 'Rating terendah',
                'name' => 'Nama A-Z',
                'id' => 'ID destinasi',
            ],
        ]);
    }

    public function show(string $id, DestinationRepository $repository): View
    {
        $destination = $repository->find($id);

        abort_if($destination === null, 404);

        return view('destination-detail', [
            'destination' => $destination,
            'related' => $repository->related($destination, 4),
        ]);
    }

    /**
     * @return array{q?: string, jenis?: string, provinsi?: string, sort?: string}
     */
    private function filters(Request $request): array
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'jenis' => $request->string('jenis')->toString(),
            'provinsi' => $request->string('provinsi')->toString(),
            'sort' => $request->string('sort')->toString(),
        ];

        if (! in_array($filters['jenis'], DestinationRepository::TYPES, true)) {
            $filters['jenis'] = '';
        }

        if (! in_array($filters['provinsi'], DestinationRepository::PROVINCES, true)) {
            $filters['provinsi'] = '';
        }

        if (! in_array($filters['sort'], DestinationRepository::SORTS, true)) {
            $filters['sort'] = 'rating';
        }

        return array_filter($filters, static fn (string $value): bool => $value !== '');
    }
}
