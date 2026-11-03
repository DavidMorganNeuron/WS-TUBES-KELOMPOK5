<?php

namespace Tests\Feature;

use Tests\TestCase;

class DestinationPagesTest extends TestCase
{
    public function test_home_page_shows_ontology_statistics_and_featured_destinations(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Live Ontology Dashboard');
        $response->assertSee('Sipiso-Piso');
        $response->assertSee('18');
    }

    public function test_destinations_page_lists_paginated_destinations(): void
    {
        $response = $this->get('/destinations');

        $response->assertOk();
        $response->assertSee('Explore North Sumatra');
        $response->assertSee('Showing 1');
        $response->assertSee('of 18');
        $response->assertSee('Sipiso-Piso');
    }

    public function test_destinations_page_can_be_filtered_by_type_and_search_query(): void
    {
        $this->get('/destinations?jenis=Kuliner')
            ->assertOk()
            ->assertSee('Bika Ambon')
            ->assertDontSee('Air Terjun Sipiso-Piso');

        $this->get('/destinations?q=toba')
            ->assertOk()
            ->assertSee('Danau Toba')
            ->assertDontSee('Bika Ambon');
    }

    public function test_destinations_page_shows_an_empty_state_for_unmatched_search(): void
    {
        $this->get('/destinations?q=zzzz')
            ->assertOk()
            ->assertSee('No destinations match your filters');
    }

    public function test_detail_page_renders_destination_data(): void
    {
        $response = $this->get('/destinations/DST-0001');

        $response->assertOk();
        $response->assertSee('Air Terjun Sipiso-Piso');
        $response->assertSee('Domestik: Rp 10.000');
        $response->assertSee('Area parkir');
        $response->assertSee('ID DST-0001');
    }

    public function test_detail_page_handles_sparse_destination_data(): void
    {
        $response = $this->get('/destinations/DST-0006');

        $response->assertOk();
        $response->assertSee('Danau Lau Kawar');
        $response->assertSee('No ticket information recorded.');
    }

    public function test_unknown_destination_returns_not_found(): void
    {
        $this->get('/destinations/DST-9999')->assertNotFound();
    }

    public function test_map_page_renders_markers_from_the_ontology(): void
    {
        $response = $this->get('/map');

        $response->assertOk();
        $response->assertSee('markerData');
        $response->assertSee('DST-0001');
        $response->assertSee('mapped points');
    }
}
