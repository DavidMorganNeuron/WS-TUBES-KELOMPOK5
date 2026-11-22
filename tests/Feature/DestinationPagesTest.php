<?php

namespace Tests\Feature;

use App\Data\Destination;
use App\Data\LinkedResource;
use App\Services\Ontology\DestinationLinkedDataProvider;
use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\WikidataClient;
use App\Services\Ontology\WikidataRegionRepository;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DestinationPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake([
            WikidataClient::ENDPOINT.'*' => Http::response('', 503),
        ]);
        Cache::forget(WikidataRegionRepository::LIVE_CACHE_KEY);
        Cache::forget(WikidataRegionRepository::STALE_CACHE_KEY);
        Cache::forget(WikidataRegionRepository::FAILURE_COOLDOWN_KEY);
    }

    public function test_home_page_shows_ontology_statistics_and_featured_destinations(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Dasbor ontologi langsung');
        $response->assertDontSee('bg-green-500 inline-block mr-1', false);
        $response->assertDontSee('Siap ditampilkan di peta');
        $response->assertDontSee('Destinasi dengan koordinat');
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
        $response->assertSee('id="destination-search-form"', false);
        $response->assertSee('placeholder="Cari destinasi wisata di Sumatera Utara..."', false);
        $response->assertDontSee('>Kategori</button>', false);
    }

    public function test_chatbot_opens_as_a_resizable_popup_from_the_navigation(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="btn-chatbot"', false);
        $response->assertSee('id="wayfinder-chatbot"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('Halo! Ada destinasi wisata di Sumatera Utara yang ingin Anda cari atau tanyakan?');
        $response->assertDontSee('Sebaiknya kita mulai dari mana?');
        $response->assertDontSee('id="chatbot-empty-state"', false);
        $response->assertSee('id="chat-input"', false);
        $response->assertSee('id="chat-input-area"', false);
        $response->assertSee('id="chatbot-resize-handle"', false);
        $response->assertSee('focus:outline-none focus:ring-0', false);
        $response->assertSee('overflow-y-auto', false);
        $response->assertSee('cursor-grab active:cursor-grabbing select-none', false);
        $response->assertSee('Jawaban AI merujuk pada informasi yang tersedia di dataset destinasi.');
        $response->assertSee('id="btn-resize-chatbot"', false);
        $response->assertSee('id="btn-close-chatbot"', false);
        $response->assertSee('WayFinder AI sedang memproses data...');
    }

    public function test_destinations_page_lists_paginated_destinations_sorted_by_name_by_default(): void
    {
        $response = $this->get('/destinations');

        $response->assertOk();
        $response->assertSee('Jelajahi Destinasi Sumatera Utara');
        $response->assertSeeInOrder([
            'Beranda</a> <span aria-hidden="true">›</span>',
            '<span class="text-brand-primary font-bold">Destinasi</span>',
        ], false);
        $response->assertDontSee('Direktori Destinasi');
        $response->assertSee('data-live-destinations', false);
        $response->assertSee('form="destination-search-form"', false);
        $response->assertDontSee('Cari nama objek wisata, kaldera, atau pulau...');
        $filterOptions = $this->repository()->filterOptions();
        $response->assertSee('<select name="dapatMelakukan"', false);
        $response->assertDontSee('<select name="memilikiFasilitas"', false);
        $response->assertSee('<option value="" hidden', false);
        $response->assertSee('>Urutan</option>', false);
        $response->assertDontSee('Urutan otomatis');
        $this->assertNotEmpty($filterOptions['activities']);
        $response->assertSee('value="'.e($filterOptions['activities'][0]).'"', false);
        $response->assertDontSee('Paling relevan');
        $response->assertDontSee('Tanpa koreksi otomatis');
        $response->assertDontSee('Menampilkan 1');
        $categoryPillsStart = strpos($response->getContent(), 'data-category-pills');
        $this->assertNotFalse($categoryPillsStart);
        $categoryPillsEnd = strpos($response->getContent(), '</div>', $categoryPillsStart);
        $this->assertNotFalse($categoryPillsEnd);
        $categoryPills = substr($response->getContent(), $categoryPillsStart, $categoryPillsEnd - $categoryPillsStart);
        foreach (['Alam', 'Budaya', 'Religi', 'Bahari', 'Kuliner', 'Buatan'] as $type) {
            $this->assertStringContainsString($type, $categoryPills);
            $this->assertStringNotContainsString($type.' (', $categoryPills);
        }
        $response->assertSee('Hapus filter');
        $response->assertDontSee('Hapus saringan');
        $destinations = $this->repository()->search(['sort' => 'name']);
        $response->assertSeeInOrder($destinations->take(2)->pluck('name')->all());
        $response->assertDontSee('name="provinsi"', false);
    }

    public function test_destination_navbar_search_and_filter_subbar_preserve_filter_state(): void
    {
        $repository = $this->repository();
        $kabupaten = array_key_first($repository->facets()['kabupaten']);
        $activity = $repository->filterOptions()['activities'][0];
        $filters = [
            'q' => 'danau',
            'jenis' => 'Alam',
            'kabupaten' => $kabupaten,
            'sort' => 'rating',
            'dapatMelakukan' => $activity,
        ];

        $response = $this->get(route('destinations', $filters));

        $response->assertOk();
        $this->assertMatchesRegularExpression('/<input\s+type="text"\s+name="q"\s+value="danau"/', $response->getContent());
        $response->assertSee('type="hidden" name="jenis" value="Alam"', false);
        $response->assertSee('<select name="sort" form="destination-search-form"', false);
        $response->assertSee('<select name="kabupaten" form="destination-search-form"', false);
        $response->assertSee('<select name="dapatMelakukan" form="destination-search-form"', false);
        $response->assertSee('value="'.e($kabupaten).'" selected', false);
        $response->assertSee('value="'.e($activity).'" selected', false);
        $response->assertSee('href="'.e(route('destinations', array_merge($filters, ['jenis' => 'Bahari']))).'"', false);
    }

    public function test_navbar_search_from_another_page_keeps_supported_filters(): void
    {
        $response = $this->get('/map?q=danau&jenis=Bahari');

        $response->assertOk();
        $response->assertSee('action="'.route('destinations').'"', false);
        $response->assertSee('name="q" value="danau"', false);
        $response->assertSee('type="hidden" name="jenis" value="Bahari"', false);
        $response->assertDontSee('data-live-destinations', false);
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

    public function test_search_uses_relevance_by_default_without_a_manual_sort(): void
    {
        $expectedNames = $this->repository()->search(['q' => 'toba'])->take(3)->pluck('name')->all();
        $this->assertCount(3, $expectedNames);

        $this->get('/destinations?q=toba')
            ->assertOk()
            ->assertSeeInOrder($expectedNames);
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
        $destinations = $this->repository()->search(['sort' => 'name']);
        $lastPageFirstItem = ((int) ceil($destinations->count() / 9) - 1) * 9;
        $lastPageNames = $destinations->slice($lastPageFirstItem, 2)->pluck('name')->all();

        $response->assertOk();
        $response->assertSeeInOrder($lastPageNames);
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
        $response->assertSeeInOrder([
            'Beranda</a> <span>›</span>',
            'Destinasi</a> <span>›</span>',
            '<span class="text-brand-primary font-bold">'.$destination->name.'</span>',
        ], false);
        $response->assertDontSee($destination->province().'</a> <span>›</span>', false);
        $response->assertSee('property="ws:idDestinasi" content="'.$destination->id.'"', false);
        $response->assertDontSee('ID ontologi');
        $response->assertDontSee('Media ontologi');
        $response->assertDontSee('Data RDF:');
        if ($destination->priceLabels() !== []) {
            $response->assertSee($destination->priceLabels()[0]);
        }
        if ($destination->facilities !== []) {
            $response->assertSee($destination->facilities[0]);
        }
    }

    public function test_detail_page_uses_google_maps_without_weather_widget(): void
    {
        $destination = $this->repository()->find('ws-002');
        $this->assertNotNull($destination);
        $this->assertTrue($destination->hasCoordinates());
        config()->set('services.google_maps.key', 'public-test-key');
        $escapedDirectionsUrl = str_replace('&', '&amp;', $destination->directionsUrl());

        $this->get(route('destination.detail', $destination->id))
            ->assertOk()
            ->assertSee('googleMapsKey')
            ->assertSee('public-test-key')
            ->assertSee('id="detail-map"', false)
            ->assertDontSee('destination-weather')
            ->assertDontSee('weather_url')
            ->assertDontSee('Cuaca terkini')
            ->assertSee($escapedDirectionsUrl, false);

        $this->assertArrayNotHasKey('weather_url', $destination->toMapMarker());
    }

    public function test_weather_endpoints_are_removed(): void
    {
        $destination = $this->repository()->find('ws-002');
        $this->assertNotNull($destination);

        $this->getJson('/weather/current?lat=3.5&lng=98.6')->assertNotFound();
        $this->getJson('/destinations/'.$destination->id.'/weather')->assertNotFound();
    }

    public function test_detail_and_map_show_known_schedule_while_unknown_schedules_stay_unmarked(): void
    {
        $destinations = $this->repository()->search();
        $known = $destinations->first(static fn (Destination $destination): bool => $destination->openingHours !== '');
        $unknown = $destinations->first(static fn (Destination $destination): bool => $destination->openingHours === '');

        $this->assertNotNull($known);
        $this->assertNotNull($unknown);
        $this->assertSame('-', $unknown->openingHoursLabel());
        $knownCard = Blade::render('<x-destination-card :destination="$destination" />', ['destination' => $known]);
        $unknownCard = Blade::render('<x-destination-card :destination="$destination" />', ['destination' => $unknown]);
        $this->assertStringContainsString('🕒', $knownCard);
        $this->assertStringNotContainsString('🕒', $unknownCard);

        $knownResponse = $this->get('/destinations/'.$known->id);
        $knownResponse->assertOk();
        $knownResponse->assertSee('Jam operasional');
        $knownResponse->assertSee($known->openingHours);
        $knownResponse->assertSee('rel="ws:memilikiJadwal"', false);
        $knownResponse->assertSee('property="ws:keteranganJadwal"', false);
        $knownResponse->assertSee('resource="'.rtrim((string) config('ontology.namespaces.data'), '/').'/jadwal-'.$known->id.'"', false);

        $document = new \DOMDocument;
        $previousErrorMode = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML($knownResponse->getContent() ?? '');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
        $this->assertTrue($loaded);
        $xpath = new \DOMXPath($document);
        $destinationSubject = $xpath->query('//*[@typeof="schema:TouristAttraction ws:DestinasiWisata"]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $destinationSubject);
        $schedule = $xpath->query('.//*[@rel="ws:memilikiJadwal"]', $destinationSubject)->item(0);
        $this->assertInstanceOf(\DOMElement::class, $schedule);
        $this->assertSame('ws:JadwalOperasional', $schedule->getAttribute('typeof'));
        $this->assertSame(
            rtrim((string) config('ontology.namespaces.data'), '/').'/jadwal-'.$known->id,
            $schedule->getAttribute('resource'),
        );
        $this->assertSame(
            $known->openingHours,
            trim($xpath->evaluate('string(.//*[@property="ws:keteranganJadwal"])', $schedule)),
        );

        $unknownResponse = $this->get('/destinations/'.$unknown->id);
        $unknownResponse->assertOk();
        $unknownResponse->assertSee('Jam operasional');
        $unknownText = preg_replace('/\s+/u', ' ', strip_tags($unknownResponse->getContent() ?? ''));
        $this->assertIsString($unknownText);
        $this->assertStringContainsString('Jam operasional -', $unknownText);
        $unknownResponse->assertDontSee('Jadwal dapat berubah');
        $unknownResponse->assertDontSee('rel="ws:memilikiJadwal"', false);
        $unknownResponse->assertDontSee('property="ws:keteranganJadwal"', false);

        $mapResponse = $this->get('/map/live?q='.urlencode($known->name));
        $mapResponse->assertOk();
        $this->assertStringContainsString($known->openingHours, $mapResponse->json('html'));
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
                'wikidata_url' => 'http://www.wikidata.org/entity/Q12345',
                'image_url' => 'https://upload.wikimedia.org/example.jpg',
                'image_page_url' => 'https://commons.wikimedia.org/wiki/File:Contoh.jpg',
                'wikipedia_url' => 'https://id.wikipedia.org/wiki/Kabupaten_Contoh',
                'wikivoyage_url' => 'https://id.wikivoyage.org/wiki/Kabupaten_Contoh',
                'wikivoyage_language' => 'id',
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
            'wikipediaUrl' => null,
            'wikivoyageUrl' => null,
        ]);
        $this->assertStringContainsString('123.456', $complete);
        $this->assertStringContainsString('5,00 km²', $complete);
        $this->assertStringContainsString('contoh.go.id', $complete);
        $this->assertStringContainsString('Baca selengkapnya', $complete);
        $this->assertStringContainsString('Panduan perjalanan', $complete);
        $this->assertStringContainsString('rel="owl:sameAs"', $complete);
        $this->assertStringContainsString('rel="ws:berbatasanDengan"', $complete);
        $this->assertStringContainsString('href="'.route('destinations', ['kabupaten' => 'Kabupaten Lain']).'"', $complete);
        $this->assertStringContainsString('href="https://commons.wikimedia.org/wiki/File:Contoh.jpg"', $complete);
        $this->assertStringContainsString('href="https://id.wikipedia.org/wiki/Kabupaten_Contoh"', $complete);
        $this->assertStringContainsString('href="https://id.wikivoyage.org/wiki/Kabupaten_Contoh"', $complete);
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

    public function test_destination_info_component_renders_complete_safe_enrichment_and_hidden_same_as(): void
    {
        $html = Blade::render('<x-info-destinasi :destination="$destination" :destination-name="$name" :destination-resource="$resource" />', [
            'destination' => [
                'wikidata_url' => 'http://www.wikidata.org/entity/Q12345',
                'labels' => ['id' => 'Nama lokal', 'en' => 'English name'],
                'aliases' => ['id' => ['Nama lain'], 'en' => ['Other name']],
                'image_url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Foto%20%281%29.jpg',
                'image_page_url' => 'https://commons.wikimedia.org/wiki/File:Foto%20%281%29.jpg',
                'wikipedia_url' => 'https://id.wikipedia.org/wiki/Nama_lokal',
                'wikivoyage_url' => 'https://en.wikivoyage.org/wiki/Nama_lokal',
                'heritage_statuses' => [['qid' => 'Q5678', 'label' => 'Cagar budaya']],
                'elevation_m' => 2157,
                'establishment_year' => 1887,
            ],
            'name' => 'Nama lokal',
            'resource' => config('ontology.namespaces.data').'ws-049',
        ]);

        $this->assertStringContainsString('rel="owl:sameAs"', $html);
        $this->assertStringContainsString('resource="http://www.wikidata.org/entity/Q12345"', $html);
        $this->assertStringContainsString('2.157 mdpl', $html);
        $this->assertStringContainsString('Tahun berdiri: 1887', $html);
        $this->assertStringContainsString('Dikenal juga sebagai: Nama lain, Other name, English name', $html);
        $this->assertStringContainsString('href="https://commons.wikimedia.org/wiki/File:Foto%20%281%29.jpg"', $html);
        $this->assertStringContainsString('href="https://id.wikipedia.org/wiki/Nama_lokal"', $html);
        $this->assertStringContainsString('href="https://en.wikivoyage.org/wiki/Nama_lokal"', $html);
        $this->assertStringNotContainsString('wikidata', mb_strtolower(strip_tags($html)));
    }

    public function test_destination_info_component_renders_partial_data_and_hides_empty_or_unsafe_values(): void
    {
        $partial = Blade::render('<x-info-destinasi :destination="$destination" :destination-name="$name" />', [
            'destination' => ['elevation_m' => 12.5],
            'name' => 'Contoh',
        ]);
        $this->assertStringContainsString('Ketinggian: 12,5 mdpl', $partial);
        $this->assertStringNotContainsString('Panduan perjalanan', $partial);

        $empty = Blade::render('<x-info-destinasi :destination="$destination" />', ['destination' => null]);
        $this->assertSame('', trim($empty));

        $unsafe = Blade::render('<x-info-destinasi :destination="$destination" :destination-name="$name" :destination-resource="$resource" />', [
            'destination' => [
                'wikidata_url' => 'javascript:alert(1)',
                'image_url' => 'https://evil.example/image.svg',
                'image_page_url' => 'javascript:alert(1)',
                'wikipedia_url' => 'javascript:alert(1)',
                'wikivoyage_url' => 'https://evil.example/wiki/Travel',
                'labels' => ['en' => '<script>alert(1)</script>'],
                'aliases' => ['id' => ['<img src=x onerror=alert(1)>']],
                'heritage_statuses' => [['label' => '<script>alert(1)</script>']],
                'elevation_m' => '<script>alert(1)</script>',
                'establishment_year' => '1887',
            ],
            'name' => 'Contoh',
            'resource' => 'javascript:alert(1)',
        ]);
        $this->assertStringNotContainsString('javascript:', $unsafe);
        $this->assertStringNotContainsString('evil.example', $unsafe);
        $this->assertStringNotContainsString('<script', $unsafe);
        $this->assertStringNotContainsString('<img', $unsafe);
        $this->assertStringNotContainsString('rel="owl:sameAs"', $unsafe);
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
        $response->assertSee('class="flex items-center gap-2" data-live-map', false);
        $response->assertDontSee('name="exact"', false);
        $response->assertDontSee('Tanpa koreksi otomatis');
        $response->assertSee('Hapus filter');
        $response->assertDontSee('Hapus saringan');
        $response->assertSee('data-map-all');
        $response->assertSee('>SATUAN</button>', false);
        $response->assertDontSee('unpkg.com');
        $response->assertDontSee('Serambi');

        $categoryPillsStart = strpos($response->getContent(), 'data-map-category-pills');
        $this->assertNotFalse($categoryPillsStart);
        $categoryPillsEnd = strpos($response->getContent(), '</div>', $categoryPillsStart);
        $this->assertNotFalse($categoryPillsEnd);
        $categoryPills = substr($response->getContent(), $categoryPillsStart, $categoryPillsEnd - $categoryPillsStart);
        $this->assertStringContainsString('Semua', $categoryPills);
        $this->assertStringNotContainsString('Semua (', $categoryPills);
        foreach ([
            'Alam' => '#15803d',
            'Budaya' => '#92400e',
            'Religi' => '#374151',
            'Bahari' => '#1d4ed8',
            'Kuliner' => '#facc15',
            'Buatan' => '#7e22ce',
        ] as $type => $color) {
            $this->assertStringContainsString($type, $categoryPills);
            $this->assertStringContainsString('style="background-color: '.$color.'"', $categoryPills);
            $this->assertStringNotContainsString($type.' (', $categoryPills);
        }
    }

    public function test_map_page_filters_by_category_and_preserves_the_active_category_indicator(): void
    {
        $response = $this->get('/map?jenis=Bahari');

        $response->assertOk();
        $response->assertSee('Pantai Bali Lestari');
        $response->assertDontSee('Aek Sijorni');
        $response->assertSee('data-jenis="Bahari"', false);
        $response->assertSee('style="background-color: #1d4ed8"', false);
        $response->assertSee('flex items-center gap-1 transition bg-brand-dark text-white', false);

        $liveResponse = $this->getJson(route('map.live', ['jenis' => 'Bahari']));
        $liveResponse->assertOk();
        $this->assertStringContainsString('Pantai Bali Lestari', $liveResponse->json('html'));
        $this->assertStringNotContainsString('Aek Sijorni', $liveResponse->json('html'));
    }

    public function test_map_page_includes_the_in_app_route_planner_and_origin_options(): void
    {
        $this->get('/map')
            ->assertOk()
            ->assertSee('id="map-route-panel"', false)
            ->assertSee('id="route-origin-select"', false)
            ->assertSee('data-route-mode="DRIVE"', false)
            ->assertSee('data-route-mode="TWO_WHEELER"', false)
            ->assertSee('data-route-mode="WALK"', false)
            ->assertSee('data-route-current-location', false)
            ->assertSee('data-route-swap', false)
            ->assertSee('data-route-reset', false)
            ->assertSee('title="Kembali ke daftar destinasi"', false)
            ->assertSee('M6 6l12 12M18 6L6 18', false)
            ->assertSee('data-route-destination="', false)
            ->assertSee('routeComputeUrl', false);
    }

    public function test_map_legend_starts_at_top_left_and_contains_marker_mode_control(): void
    {
        $response = $this->get('/map');

        $response->assertOk()
            ->assertSeeInOrder([
                'id="map" role="region"',
                'data-map-legend',
                'data-map-legend-handle',
                'data-map-all',
                '>SATUAN</button>',
            ], false)
            ->assertSee('top-4 left-4 z-[1000]', false)
            ->assertSee('cursor-grab active:cursor-grabbing select-none touch-none', false)
            ->assertSee('⋮⋮', false)
            ->assertDontSee('data-map-fit')
            ->assertDontSee('title="Buka direktori destinasi"', false);
    }

    public function test_map_page_passes_the_configured_google_maps_key_to_its_browser_config(): void
    {
        config()->set('services.google_maps.key', 'public-test-key');

        $this->get('/map')
            ->assertOk()
            ->assertSee('googleMapsKey')
            ->assertSee('public-test-key');
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
