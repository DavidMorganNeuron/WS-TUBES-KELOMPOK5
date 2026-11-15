<?php

namespace Tests\Feature;

use App\Data\Destination;
use App\Data\LinkedResource;
use App\Services\Ontology\DestinationLinkedDataProvider;
use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\WikidataRegionRepository;
use App\Support\IndonesianFormat;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DestinationPagesTest extends TestCase
{
    public function test_home_page_shows_ontology_statistics_and_featured_destinations(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Dasbor ontologi langsung');
        $response->assertSee(number_format($this->repository()->facets()['total']));
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
        $response->assertSee('Lewati ke konten utama');
        $response->assertSee('WayFinder');
        $response->assertDontSee('Sumatera Heritage &amp; Trails');
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
        $response->assertSee('Jelajahi Destinasi Sumatera Utara');
        $response->assertSee('Menampilkan 1');
        $destinations = $this->repository()->search(['sort' => 'name']);
        $response->assertSee('dari '.IndonesianFormat::number($destinations->count()));
        $response->assertSeeInOrder($destinations->take(2)->pluck('name')->all());
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
        $repository = $this->repository();

        foreach (['rating', 'price_desc', 'price_asc'] as $sort) {
            $expectedNames = $repository->search(['sort' => $sort])->take(2)->pluck('name')->all();
            $this->assertCount(2, $expectedNames);

            $this->get('/destinations?sort='.$sort)
                ->assertOk()
                ->assertSeeInOrder($expectedNames);
        }
    }

    public function test_destinations_page_clamps_an_out_of_range_page_to_the_last_page(): void
    {
        $response = $this->get('/destinations?page=9999');
        $total = $this->repository()->search(['sort' => 'name'])->count();
        $lastPageFirstItem = ((int) ceil($total / 9) - 1) * 9 + 1;

        $response->assertOk();
        $response->assertSee('Menampilkan '.IndonesianFormat::number($lastPageFirstItem));
        $response->assertSee('dari '.IndonesianFormat::number($total));
    }

    public function test_destinations_page_shows_an_empty_state_for_unmatched_search(): void
    {
        $this->get('/destinations?q=zzzz')
            ->assertOk()
            ->assertSee('Tidak ada destinasi yang sesuai dengan saringan');
    }

    public function test_detail_page_renders_destination_data(): void
    {
        $destination = $this->repository()->find('ws-148');
        $this->assertNotNull($destination);

        $response = $this->get('/destinations/ws-148');

        $response->assertOk();
        $response->assertSee($destination->name);
        $response->assertSee('property="ws:idDestinasi"', false);
        $response->assertSee($destination->id);
        if ($destination->priceLabels() !== []) {
            $response->assertSee($destination->priceLabels()[0]);
        }
        if ($destination->facilities !== []) {
            $response->assertSee($destination->facilities[0]);
        }
    }

    public function test_detail_page_includes_open_graph_and_rdfa_metadata(): void
    {
        $destination = $this->repository()->find('ws-148');
        $this->assertNotNull($destination);

        $response = $this->get('/destinations/'.$destination->id);
        $response->assertOk();

        $document = new \DOMDocument;
        $previousErrorMode = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML($response->getContent() ?? '');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }

        $this->assertTrue($loaded);
        $xpath = new \DOMXPath($document);
        $this->assertSame($destination->name.' — WayFinder', $xpath->evaluate('string(//meta[@property="og:title"]/@content)'));
        $this->assertSame('website', $xpath->evaluate('string(//meta[@property="og:type"]/@content)'));
        $this->assertNotSame('', $xpath->evaluate('string(//meta[@property="place:location:latitude"]/@content)'));
        $this->assertSame(route('destination.detail', $destination->id), $xpath->evaluate('string(//link[@rel="canonical"]/@href)'));
        $this->assertSame($destination->name, $xpath->evaluate('string(//*[@property="schema:name ws:namaDestinasi"])'));
        $this->assertSame(
            'schema:TouristAttraction ws:DestinasiWisata',
            $xpath->evaluate('string(//*[@typeof="schema:TouristAttraction ws:DestinasiWisata"]/@typeof)'),
        );
        $response->assertSee('Mengenal wilayah');
        $response->assertSee('Tentang '.$destination->location);
        $this->assertSame(1, $xpath->query('//link[@rel="alternate" and @type="text/turtle"]')->length);
        $linkedRegion = app(DestinationLinkedDataProvider::class)->forDestination('ws-148')->region;
        $this->assertNotNull($linkedRegion);
        $wikidataRegion = app(WikidataRegionRepository::class)->findForRegion($destination->location);
        $this->assertNotNull($wikidataRegion);
        $sameAs = $xpath->query('//span[@rel="owl:sameAs"]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $sameAs);
        $this->assertSame($linkedRegion->iri, $sameAs->getAttribute('about'));
        $this->assertSame($wikidataRegion['wikidata_url'], $sameAs->getAttribute('resource'));
        $this->assertNotSame('', $sameAs->getAttribute('about'));
        $this->assertNotSame('-', $sameAs->getAttribute('resource'));
    }

    public function test_region_cards_hide_provenance_terms_on_home_and_destination_pages(): void
    {
        $destination = $this->repository()->find('ws-148');
        $this->assertNotNull($destination);

        foreach (['/', '/destinations/'.$destination->id] as $path) {
            $response = $this->get($path);
            $response->assertOk();

            $document = new \DOMDocument;
            $previousErrorMode = libxml_use_internal_errors(true);
            try {
                $loaded = $document->loadHTML($response->getContent() ?? '');
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previousErrorMode);
            }

            $this->assertTrue($loaded);
            $text = mb_strtolower($document->getElementsByTagName('body')->item(0)?->textContent ?? '');
            foreach (['wikidata', 'sumber', 'snapshot', 'identitas', 'referensi', 'query service'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $text, "Teks terlarang '{$forbidden}' tampil pada {$path}.");
            }
            $this->assertDoesNotMatchRegularExpression('/\bQ\d+\b/i', $text);
        }
    }

    public function test_nearby_destination_cards_preserve_neighbor_rdfa_and_match_repository_results(): void
    {
        $destination = $this->repository()->find('ws-148');
        $this->assertNotNull($destination);
        $nearby = $this->repository()->nearby($destination);
        $linkedNeighbors = app(DestinationLinkedDataProvider::class)->forDestination($destination->id)->neighbors;
        $linkedIds = collect($linkedNeighbors)->pluck('destinationId')->sort()->values()->all();

        $this->assertSame($nearby->pluck('id')->sort()->values()->all(), $linkedIds);

        $response = $this->get('/destinations/'.$destination->id);
        $response->assertOk();
        $document = new \DOMDocument;
        $previousErrorMode = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML($response->getContent() ?? '');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }

        $this->assertTrue($loaded);
        $xpath = new \DOMXPath($document);
        $cards = $xpath->query('//h2[normalize-space(.)="Destinasi terdekat"]/following-sibling::div[1]/article[@rel="ws:dekatDengan"]');
        $this->assertSame($nearby->count(), $cards->length);

        foreach ($nearby as $index => $neighbor) {
            $card = $cards->item($index);
            $this->assertInstanceOf(\DOMElement::class, $card);
            $this->assertSame(config('ontology.namespaces.data').$neighbor->id, $card->getAttribute('resource'));
            $this->assertSame($neighbor->name, trim($xpath->evaluate('string(.//a[@property="ws:namaDestinasi schema:name"])', $card)));
            $this->assertSame(route('destination.detail', $neighbor->id), $xpath->evaluate('string(.//a[@property="ws:namaDestinasi schema:name"]/@href)', $card));
        }
    }

    public function test_region_component_renders_safe_facts_optional_links_and_no_empty_card(): void
    {
        $complete = Blade::render('<x-info-wilayah :region="$region" :region-name="$regionName" :region-resource="$regionResource" :region-neighbors="$regionNeighbors" :wikipedia-url="$wikipediaUrl" :wikivoyage-url="$wikivoyageUrl" />', [
            'region' => [
                'wikidata_label' => 'Kabupaten Contoh',
                'wikidata_url' => 'https://www.wikidata.org/entity/Q12345',
                'image_url' => 'https://upload.wikimedia.org/example.jpg',
                'image_page_url' => 'https://commons.wikimedia.org/wiki/File:Contoh.jpg',
                'website_url' => 'https://contoh.go.id/',
                'population' => ['amount' => '123456'],
                'area' => [
                    'amount' => '999',
                    'unit_qid' => 'Q999',
                    'normalized' => ['amount' => '5000000', 'unit_qid' => 'Q25343'],
                ],
            ],
            'regionName' => 'Kabupaten Contoh',
            'regionResource' => config('ontology.namespaces.data').'wilayah-kabupaten-contoh',
            'regionNeighbors' => [
                new LinkedResource(
                    iri: config('ontology.namespaces.data').'wilayah-kabupaten-lain',
                    label: 'Kabupaten Lain',
                    type: 'ws:KabupatenKota',
                ),
            ],
            'wikipediaUrl' => 'https://id.wikipedia.org/wiki/Kabupaten_Contoh',
            'wikivoyageUrl' => 'https://id.wikivoyage.org/wiki/Kabupaten_Contoh',
        ]);
        $this->assertStringContainsString('123.456', $complete);
        $this->assertStringContainsString('5,00 km²', $complete);
        $this->assertStringContainsString('contoh.go.id', $complete);
        $this->assertStringContainsString('Baca selengkapnya', $complete);
        $this->assertStringContainsString('Panduan perjalanan', $complete);
        $this->assertStringContainsString('rel="owl:sameAs"', $complete);
        $this->assertStringContainsString('rel="ws:berbatasanDengan"', $complete);
        $this->assertStringContainsString('href="'.route('destinations', ['kabupaten' => 'Kabupaten Lain']).'"', $complete);
        $this->assertStringContainsString('loading="lazy"', $complete);
        $this->assertStringContainsString('referrerpolicy="no-referrer"', $complete);

        $unsafe = Blade::render('<x-info-wilayah :region="$region" :region-name="$regionName" :region-resource="$regionResource" :wikipedia-url="$wikipediaUrl" :wikivoyage-url="$wikivoyageUrl" />', [
            'region' => [
                'wikidata_label' => 'Wilayah',
                'wikidata_url' => 'javascript:alert(1)',
                'image_url' => 'https://evil.example/image.svg',
                'image_page_url' => 'javascript:alert(1)',
                'website_url' => 'javascript:alert(1)',
                'population' => ['amount' => '<script>alert(1)</script>'],
                'area' => ['amount' => '1', 'unit_qid' => 'javascript:alert(1)'],
            ],
            'regionName' => 'Wilayah',
            'regionResource' => 'javascript:alert(1)',
            'wikipediaUrl' => 'javascript:alert(1)',
            'wikivoyageUrl' => 'https://evil.example/travel',
        ]);
        $this->assertStringNotContainsString('javascript:', $unsafe);
        $this->assertStringNotContainsString('evil.example', $unsafe);
        $this->assertStringNotContainsString('<script', $unsafe);

        $empty = Blade::render('<x-info-wilayah :region="$region" />', ['region' => null]);
        $this->assertSame('', trim($empty));
    }

    public function test_detail_page_handles_sparse_destination_data(): void
    {
        $destination = $this->repository()->all()->first(
            static fn (Destination $destination): bool => $destination->prices === []
                && $destination->rating === null
                && $destination->phone === ''
                && $destination->email === ''
                && $destination->instagram === ''
                && $destination->hasCoordinates(),
        );
        $this->assertNotNull($destination);

        $response = $this->get('/destinations/'.$destination->id);

        $response->assertOk();
        $response->assertSee($destination->name);
        $response->assertSee('Tiket mulai dari');
        $response->assertSee($destination->priceLabel());
        $response->assertSee('⭐ -');
        $response->assertDontSee('N/A');
        $response->assertSee('data-latitude="'.json_encode($destination->latitude).'"', false);
    }

    public function test_unknown_destination_returns_the_custom_not_found_page(): void
    {
        $response = $this->get('/destinations/DST-9999');

        $response->assertNotFound();
        $response->assertSee('Halaman tidak ditemukan');
        $response->assertSee(url('/destinations'));
        $response->assertSee('id="btn-theme-toggle"', false);
    }

    public function test_map_page_renders_markers_from_the_ontology_without_cdn_scripts(): void
    {
        $response = $this->get('/map');

        $response->assertOk();
        $response->assertSee('WayFinderMapConfig');
        $response->assertSee('ws-002');
        $response->assertSee('titik pada peta');
        $response->assertSee('data-map-all');
        $response->assertSee('>SATUAN</button>', false);
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

    private function repository(): DestinationRepository
    {
        return app(DestinationRepository::class);
    }
}
