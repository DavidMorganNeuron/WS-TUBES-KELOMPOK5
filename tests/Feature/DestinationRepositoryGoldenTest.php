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
        $this->assertSame([], $repository->search(['q' => '!!!'])->all());
        $this->assertSame([], $repository->search(['kabupaten' => 'Kabupaten Tidak Dikenal'])->all());
    }
}
