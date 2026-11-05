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
        $response->assertSee('285');
    }

    public function test_destinations_page_lists_paginated_destinations_sorted_by_name_by_default(): void
    {
        $response = $this->get('/destinations');

        $response->assertOk();
        $response->assertSee('Explore North Sumatra');
        $response->assertSee('Showing 1');
        $response->assertSee('of 285');
        $response->assertSeeInOrder(['Air Terjun Bolon', 'Air Terjun Dua Warna']);
    }

    public function test_destinations_page_can_be_filtered_by_type_and_search_query(): void
    {
        $this->get('/destinations?jenis=Bahari')
            ->assertOk()
            ->assertSee('Air Terjun Pelaruga')
            ->assertDontSee('Air Terjun Bolon');

        $this->get('/destinations?q=toba')
            ->assertOk()
            ->assertSee('Danau Toba')
            ->assertDontSee('Air Terjun Bolon');
    }

    public function test_destinations_page_can_be_filtered_by_kabupaten(): void
    {
        $this->get('/destinations?kabupaten=Kota%20Medan')
            ->assertOk()
            ->assertSee('Istana Maimun')
            ->assertDontSee('Air Terjun Bolon');
    }

    public function test_destinations_page_can_be_sorted_by_price(): void
    {
        $this->get('/destinations?sort=price_desc')
            ->assertOk()
            ->assertSeeInOrder(['Rahmat International Wildlife Museum & Gallery', 'Kebun Binatang Pematang Siantar']);

        $this->get('/destinations?sort=price_asc')
            ->assertOk()
            ->assertSee('Gratis');
    }

    public function test_destinations_page_shows_an_empty_state_for_unmatched_search(): void
    {
        $this->get('/destinations?q=zzzz')
            ->assertOk()
            ->assertSee('No destinations match your filters');
    }

    public function test_detail_page_renders_destination_data(): void
    {
        $response = $this->get('/destinations/ws-272');

        $response->assertOk();
        $response->assertSee('Air Terjun Pelaruga');
        $response->assertSee('Rp 10.000');
        $response->assertSee('Area Parkir');
        $response->assertSee('ID ws-272');
    }

    public function test_detail_page_handles_sparse_destination_data(): void
    {
        $response = $this->get('/destinations/ws-001');

        $response->assertOk();
        $response->assertSee('Air Terjun Bolon');
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
        $response->assertSee('ws-001');
        $response->assertSee('mapped points');
    }
}
