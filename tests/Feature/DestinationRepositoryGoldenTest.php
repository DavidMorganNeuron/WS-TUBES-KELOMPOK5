<?php

namespace Tests\Feature;

use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\DestinationSource;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DestinationRepositoryGoldenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ontology.driver', 'ttl');
        app()->forgetInstance(DestinationSource::class);
        app()->forgetInstance(DestinationRepository::class);
        Cache::flush();
    }

    public function test_local_source_matches_the_search_filter_sort_and_facets_baseline(): void
    {
        $repository = app(DestinationRepository::class);
        $golden = require __DIR__.'/../Fixtures/destination-golden.php';

        foreach ($golden['searches'] as $case) {
            $destinations = $repository->search($case['filters']);

            $this->assertSame($case['total'], $destinations->count());
            $this->assertSame($case['ids'], $destinations->take(count($case['ids']))->pluck('id')->all());
        }

        foreach ($golden['city_sorts'] as $sort => $expectedIds) {
            $destinations = $repository->search(['kabupaten' => 'Kota Medan', 'sort' => $sort]);

            $this->assertSame(23, $destinations->count());
            $this->assertSame($expectedIds, $destinations->take(count($expectedIds))->pluck('id')->all());
        }

        $this->assertSame($golden['facets'], $repository->facets());
        $dashboard = $repository->dashboard();
        $dashboardFacets = array_intersect_key($dashboard, $golden['facets']);
        $this->assertSame($golden['facets'], $dashboardFacets);
        $this->assertSame(
            ['ws-070', 'ws-019', 'ws-054', 'ws-127', 'ws-180'],
            $dashboard['top_rated']->pluck('id')->all(),
        );
        $this->assertSame(
            ['label' => 'Kabupaten Karo', 'count' => 26],
            $dashboard['top_region'],
        );
        $this->assertSame([], $repository->search(['q' => '!!!'])->all());
        $this->assertSame([], $repository->search(['kabupaten' => 'Kabupaten Tidak Dikenal'])->all());
    }

    public function test_province_is_fixed_to_north_sumatra_and_legacy_filters_are_no_ops(): void
    {
        $repository = app(DestinationRepository::class);
        $destination = $repository->find('ws-046');
        $allIds = $repository->search(['sort' => 'id'])->pluck('id')->all();

        $this->assertNotNull($destination);
        $this->assertSame('Sumatera Utara', $destination->province());
        $this->assertSame(['Sumatera Utara' => 228], $repository->facets()['provinces']);
        $this->assertSame(
            $allIds,
            $repository->search(['provinsi' => 'Aceh', 'sort' => 'id'])->pluck('id')->all(),
        );
    }
}
