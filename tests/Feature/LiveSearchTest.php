<?php

namespace Tests\Feature;

use Tests\TestCase;

class LiveSearchTest extends TestCase
{
    public function test_destinations_live_endpoint_returns_cards_for_word_prefix_search(): void
    {
        $response = $this->getJson('/destinations/live?q=Toba');

        $response->assertOk();
        $response->assertJsonStructure(['html', 'total', 'first_item', 'last_item', 'count', 'has_filters']);

        $this->assertTrue($response->json('has_filters'));
        $this->assertGreaterThan(0, $response->json('total'));
        $this->assertStringContainsString('Danau Toba', $response->json('html'));
    }

    public function test_destinations_live_endpoint_does_not_match_arbitrary_substrings(): void
    {
        $response = $this->getJson('/destinations/live?q=oba');

        $response->assertOk();
        $this->assertSame(0, $response->json('total'));
        $this->assertStringContainsString('No destinations match your filters', $response->json('html'));
    }

    public function test_destinations_live_endpoint_applies_sort_and_kabupaten_filters(): void
    {
        $response = $this->getJson('/destinations/live?sort=price_desc&kabupaten='.urlencode('Kota Medan'));

        $response->assertOk();
        $this->assertTrue($response->json('has_filters'));
        $this->assertGreaterThan(0, $response->json('total'));
        $this->assertStringContainsString('Kota Medan', $response->json('html'));
    }

    public function test_map_live_endpoint_returns_results_and_markers(): void
    {
        $response = $this->getJson('/map/live?q=Danau');

        $response->assertOk();
        $response->assertJsonStructure([
            'html',
            'matchCount',
            'resultCount',
            'markerCount',
            'markers' => ['*' => ['id', 'name', 'latitude', 'longitude', 'url']],
        ]);

        $this->assertGreaterThan(0, $response->json('matchCount'));
        $this->assertCount($response->json('markerCount'), $response->json('markers'));
    }

    public function test_destination_suggestion_endpoint_returns_prefix_matches(): void
    {
        $response = $this->getJson('/destinations/suggest?q=Dan');

        $response->assertOk();
        $response->assertJsonStructure(['html', 'count']);

        $this->assertGreaterThan(0, $response->json('count'));
        $this->assertStringContainsString('Danau', $response->json('html'));
    }
}
