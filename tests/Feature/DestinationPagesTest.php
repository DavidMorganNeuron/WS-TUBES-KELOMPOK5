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
        $response->assertSee('250');
        $response->assertDontSee('Acehnese');
        $response->assertDontSee('Serambi');
    }

    public function test_shared_layout_includes_theme_toggle_and_skeleton_image_placeholder(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="btn-theme-toggle"', false);
        $response->assertSee('data-theme-icon="moon"', false);
        $response->assertSee('data-theme-icon="sun"', false);
        $response->assertSee('skeleton-shimmer');
        $response->assertSee('wayfinder.theme');
    }

    public function test_chatbot_is_available_as_a_fullscreen_ready_dialog_from_the_navigation(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="btn-chatbot"', false);
        $response->assertSee('id="wayfinder-chatbot"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('data-chatbot-greeting', false);
        $response->assertSee('id="chat-input"', false);
        $response->assertSee('id="btn-resize-chatbot"', false);
        $response->assertSee('id="btn-close-chatbot"', false);
        $response->assertSee('WayFinder AI sedang memproses data...');
    }

    public function test_destinations_page_lists_paginated_destinations_sorted_by_name_by_default(): void
    {
        $response = $this->get('/destinations');

        $response->assertOk();
        $response->assertSee('Explore North Sumatra');
        $response->assertSee('Showing 1');
        $response->assertSee('of 250');
        $response->assertSeeInOrder(['Aek Sijorni', 'Agrowisata Kebun Jeruk Pearung']);
        $response->assertDontSee('name="provinsi"', false);
    }

    public function test_destinations_page_can_be_filtered_by_type_and_search_query(): void
    {
        $this->get('/destinations?jenis=Bahari')
            ->assertOk()
            ->assertSee('Pantai Bali Lestari')
            ->assertDontSee('Aek Sijorni');

        $this->get('/destinations?q=toba')
            ->assertOk()
            ->assertSee('Danau Toba')
            ->assertDontSee('Aek Sijorni');
    }

    public function test_destinations_page_can_be_filtered_by_kabupaten(): void
    {
        $this->get('/destinations?kabupaten=Kota%20Medan')
            ->assertOk()
            ->assertSee('Istana Maimun')
            ->assertDontSee('Aek Sijorni');
    }

    public function test_destinations_page_can_be_sorted_by_rating_and_price(): void
    {
        $this->get('/destinations?sort=rating')
            ->assertOk()
            ->assertSeeInOrder(['Air Terjun Mbulayan', 'Candi Bara']);

        $this->get('/destinations?sort=price_desc')
            ->assertOk()
            ->assertSeeInOrder(['Pulau Kalimantung', 'Pulau Pandang']);

        $this->get('/destinations?sort=price_asc')
            ->assertOk()
            ->assertSee('Free');
    }

    public function test_destinations_page_clamps_an_out_of_range_page_to_the_last_page(): void
    {
        $response = $this->get('/destinations?page=9999');

        $response->assertOk();
        $response->assertSee('Showing 244');
        $response->assertSee('of 250');
    }

    public function test_destinations_page_shows_an_empty_state_for_unmatched_search(): void
    {
        $this->get('/destinations?q=zzzz')
            ->assertOk()
            ->assertSee('No destinations match your filters');
    }

    public function test_detail_page_renders_destination_data(): void
    {
        $response = $this->get('/destinations/ws-148');

        $response->assertOk();
        $response->assertSee('Air Terjun Pelaruga');
        $response->assertSee('Rp 10.000');
        $response->assertSee('Area Parkir');
        $response->assertSee('ID ws-148');
    }

    public function test_detail_page_handles_sparse_destination_data(): void
    {
        $response = $this->get('/destinations/ws-002');

        $response->assertOk();
        $response->assertSee('Air Terjun Lae Mbilulu');
        $response->assertSee('No ticket information recorded.');
        $response->assertDontSee('No official contact recorded.');
        $response->assertSee('data-latitude="2.6119486688740423"', false);
    }

    public function test_unknown_destination_returns_the_custom_not_found_page(): void
    {
        $response = $this->get('/destinations/DST-9999');

        $response->assertNotFound();
        $response->assertSee('Page not found');
        $response->assertSee(url('/destinations'));
        $response->assertSee('id="btn-theme-toggle"', false);
    }

    public function test_map_page_renders_markers_from_the_ontology_without_cdn_scripts(): void
    {
        $response = $this->get('/map');

        $response->assertOk();
        $response->assertSee('WayFinderMapConfig');
        $response->assertSee('ws-002');
        $response->assertSee('mapped points');
        $response->assertSee('data-map-all');
        $response->assertSee('>ALL</button>', false);
        $response->assertDontSee('unpkg.com');
        $response->assertDontSee('Serambi');
    }

    public function test_pages_never_expose_the_gemini_api_key(): void
    {
        config()->set('services.gemini.key', 'super-secret-test-key');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('super-secret-test-key');
    }
}
