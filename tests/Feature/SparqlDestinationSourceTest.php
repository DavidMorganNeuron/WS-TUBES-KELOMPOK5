<?php

namespace Tests\Feature;

use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\DestinationSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SparqlDestinationSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ontology.driver', 'fuseki');
        app()->forgetInstance(DestinationSource::class);
        app()->forgetInstance(DestinationRepository::class);
        Cache::flush();
    }

    public function test_paginated_search_uses_count_id_and_detail_queries_and_preserves_id_order(): void
    {
        Http::fake(function (Request $request) {
            $query = (string) ($request->data()['query'] ?? '');
            if (str_contains($query, 'COUNT(DISTINCT ?countedDestination)')) {
                return Http::response($this->response([
                    [
                        'total' => ['type' => 'literal', 'value' => '2'],
                        'idDestinasi' => ['type' => 'literal', 'value' => 'ws-001'],
                    ],
                    [
                        'total' => ['type' => 'literal', 'value' => '2'],
                        'idDestinasi' => ['type' => 'literal', 'value' => 'ws-002'],
                    ],
                ]));
            }

            if (str_contains($query, 'GROUP_CONCAT')) {
                return Http::response($this->response([
                    $this->destinationRow('ws-002', 'Destination Two'),
                    $this->destinationRow('ws-001', 'Destination One'),
                ]));
            }

            return Http::response($this->response([]));
        });

        $page = app(DestinationRepository::class)->paginate(['sort' => 'id'], 2);

        $this->assertSame(2, $page->total());
        $this->assertSame(['ws-001', 'ws-002'], $page->getCollection()->pluck('id')->all());
        Http::assertSentCount(2);
    }

    public function test_facets_are_mapped_from_a_single_sparql_request(): void
    {
        $queries = [];
        Http::fake(function (Request $request) use (&$queries) {
            $query = (string) ($request->data()['query'] ?? '');
            $queries[] = $query;

            if (str_contains($query, 'GROUP_CONCAT')) {
                return Http::response($this->response(array_map(
                    fn (int $number): array => $this->destinationRow(
                        sprintf('ws-%03d', $number),
                        'Destination '.$number,
                    ),
                    range(1, 5),
                )));
            }

            if (str_contains($query, 'LIMIT 5')) {
                return Http::response($this->response([
                    [
                        'kind' => ['type' => 'literal', 'value' => 'overall'],
                        'total' => ['type' => 'literal', 'value' => '10'],
                        'rated' => ['type' => 'literal', 'value' => '8'],
                        'average' => ['type' => 'literal', 'value' => '4.25'],
                        'coordinates' => ['type' => 'literal', 'value' => '10'],
                        'media' => ['type' => 'literal', 'value' => '9'],
                    ],
                    [
                        'kind' => ['type' => 'literal', 'value' => 'district'],
                        'label' => ['type' => 'literal', 'value' => 'Kabupaten Toba'],
                        'count' => ['type' => 'literal', 'value' => '2'],
                    ],
                    [
                        'kind' => ['type' => 'literal', 'value' => 'district'],
                        'label' => ['type' => 'literal', 'value' => 'Kabupaten Karo'],
                        'count' => ['type' => 'literal', 'value' => '2'],
                    ],
                    ...array_map(
                        static fn (int $number): array => [
                            'kind' => ['type' => 'literal', 'value' => 'top_rated'],
                            'idDestinasi' => ['type' => 'literal', 'value' => sprintf('ws-%03d', $number)],
                        ],
                        range(1, 5),
                    ),
                ]));
            }

            return Http::response($this->response([
                [
                    'kind' => ['type' => 'literal', 'value' => 'overall'],
                    'total' => ['type' => 'literal', 'value' => '2'],
                    'rated' => ['type' => 'literal', 'value' => '2'],
                    'average' => ['type' => 'literal', 'value' => '4.25'],
                    'coordinates' => ['type' => 'literal', 'value' => '2'],
                    'media' => ['type' => 'literal', 'value' => '1'],
                ],
                [
                    'kind' => ['type' => 'literal', 'value' => 'type'],
                    'label' => ['type' => 'literal', 'value' => 'Alam'],
                    'count' => ['type' => 'literal', 'value' => '2'],
                ],
                [
                    'kind' => ['type' => 'literal', 'value' => 'district'],
                    'label' => ['type' => 'literal', 'value' => 'Kabupaten Toba'],
                    'count' => ['type' => 'literal', 'value' => '2'],
                ],
            ]));
        });

        $facets = app(DestinationRepository::class)->facets();

        $this->assertSame(2, $facets['total']);
        $this->assertSame(2, $facets['types']['Alam']);
        $this->assertSame(['Kabupaten Toba' => 2], $facets['kabupaten']);
        $this->assertSame(['Sumatera Utara' => 2], $facets['provinces']);
        $this->assertSame(4.25, $facets['average_rating']);
        $this->assertSame(2, $facets['rated_count']);
        $this->assertSame(2, $facets['with_coordinates']);
        $this->assertSame(1, $facets['with_media']);
        $this->assertStringNotContainsString('Aceh', $queries[0]);
        $this->assertStringNotContainsString('province', $queries[0]);
        Http::assertSentCount(1);

        Cache::flush();
        $dashboard = app(DestinationRepository::class)->dashboard();

        $this->assertSame(10, $dashboard['total']);
        $this->assertSame(['Kabupaten Karo' => 2, 'Kabupaten Toba' => 2], $dashboard['kabupaten']);
        $this->assertSame(['label' => 'Kabupaten Karo', 'count' => 2], $dashboard['top_region']);
        $this->assertSame(
            ['ws-001', 'ws-002', 'ws-003', 'ws-004', 'ws-005'],
            $dashboard['top_rated']->pluck('id')->all(),
        );
        $this->assertStringContainsString('FILTER(BOUND(?ratingValue))', $queries[1]);
        $this->assertStringContainsString('LIMIT 5', $queries[1]);
        $cachedDashboard = app(DestinationRepository::class)->dashboard();
        $this->assertSame($dashboard['top_rated']->pluck('id')->all(), $cachedDashboard['top_rated']->pluck('id')->all());
        $this->assertSame($dashboard['top_region'], $cachedDashboard['top_region']);
        $this->assertCount(3, $queries);
        Http::assertSentCount(3);
    }

    public function test_map_markers_require_coordinates_in_the_sparql_query(): void
    {
        $queries = [];
        Http::fake(function (Request $request) use (&$queries) {
            $query = (string) ($request->data()['query'] ?? '');
            $queries[] = $query;

            if (str_contains($query, 'GROUP_CONCAT')) {
                return Http::response($this->response([[
                    'idDestinasi' => ['type' => 'literal', 'value' => 'ws-001'],
                    'namaDestinasi' => ['type' => 'literal', 'value' => 'Danau Contoh'],
                    'lintang' => ['type' => 'literal', 'value' => '2.5'],
                    'bujur' => ['type' => 'literal', 'value' => '98.5'],
                ]]));
            }

            return Http::response($this->response([
                ['idDestinasi' => ['type' => 'literal', 'value' => 'ws-001']],
            ]));
        });

        $markers = app(DestinationRepository::class)->markers(['sort' => 'id']);

        $this->assertCount(1, $markers);
        $this->assertSame('ws-001', $markers[0]['id']);
        $this->assertSame(2.5, $markers[0]['latitude']);
        $this->assertStringContainsString(
            '?destination ws:lintang ?latitude ; ws:bujur ?longitude .',
            $queries[0],
        );
        Http::assertSentCount(2);
    }

    /**
     * @param  list<array<string, array{type: string, value: string}>>  $bindings
     * @return array{head: array{vars: list<string>}, results: array{bindings: list<array<string, array{type: string, value: string}>>}}
     */
    private function response(array $bindings): array
    {
        return [
            'head' => ['vars' => []],
            'results' => ['bindings' => $bindings],
        ];
    }

    /**
     * @return array<string, array{type: string, value: string}>
     */
    private function destinationRow(string $id, string $name): array
    {
        return [
            'idDestinasi' => ['type' => 'literal', 'value' => $id],
            'namaDestinasi' => ['type' => 'literal', 'value' => $name],
            'jenisDestinasi' => ['type' => 'literal', 'value' => 'Alam'],
            'beradaDi' => ['type' => 'literal', 'value' => 'Kabupaten Toba'],
            'ratingPengunjung' => ['type' => 'literal', 'value' => '4.5'],
        ];
    }
}
