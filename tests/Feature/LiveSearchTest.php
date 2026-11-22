<?php

namespace Tests\Feature;

<<<<<<< HEAD
use App\Data\Destination;
use App\Services\Ontology\DestinationRepository;
=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
use Tests\TestCase;

class LiveSearchTest extends TestCase
{
    public function test_destinations_live_endpoint_returns_cards_for_word_prefix_search(): void
    {
        $response = $this->getJson('/destinations/live?q=Toba');

        $response->assertOk();
<<<<<<< HEAD
        $response->assertJsonStructure(['html', 'total', 'first_item', 'last_item', 'count', 'has_filters', 'correction', 'suggestions']);
=======
        $response->assertJsonStructure(['html', 'total', 'first_item', 'last_item', 'count', 'has_filters']);
>>>>>>> ee7c4b41627891394572865c2960230988e32b45

        $this->assertTrue($response->json('has_filters'));
        $this->assertGreaterThan(0, $response->json('total'));
        $this->assertStringContainsString('Danau Toba', $response->json('html'));
    }

<<<<<<< HEAD
    public function test_destinations_live_endpoint_matches_single_letter_prefixes_but_not_arbitrary_substrings(): void
    {
        $singleLetter = $this->getJson('/destinations/live?q=D');

        $singleLetter->assertOk();
        $this->assertGreaterThan(0, $singleLetter->json('total'));
        $this->assertStringContainsString('Danau Toba', $singleLetter->json('html'));

=======
    public function test_destinations_live_endpoint_does_not_match_arbitrary_substrings(): void
    {
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        $response = $this->getJson('/destinations/live?q=oba');

        $response->assertOk();
        $this->assertSame(0, $response->json('total'));
<<<<<<< HEAD
        $this->assertStringContainsString('Tidak ada destinasi yang sesuai dengan saringan', $response->json('html'));
    }

    public function test_live_search_corrects_a_transposed_typo_even_when_legacy_exact_parameter_is_present(): void
    {
        $corrected = $this->getJson('/destinations/live?q=dnau+toba');

        $corrected->assertOk()
            ->assertJsonPath('correction.original', 'dnau toba')
            ->assertJsonPath('correction.corrected', 'danau toba');
        $this->assertGreaterThan(0, $corrected->json('total'));

        $exact = $this->getJson('/destinations/live?q=dnau&exact=1');

        $exact->assertOk()
            ->assertJsonPath('correction.corrected', 'danau');
        $this->assertGreaterThan(0, $exact->json('total'));
    }

    public function test_live_search_matches_all_tokens_across_destination_fields(): void
    {
        $response = $this->getJson('/destinations/live?q=Holbung+Bolon');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('total'));
        $this->assertStringContainsString('Air Terjun Bolon', $response->json('html'));
=======
        $this->assertStringContainsString('No destinations match your filters', $response->json('html'));
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    }

    public function test_destinations_live_endpoint_applies_sort_and_kabupaten_filters(): void
    {
        $response = $this->getJson('/destinations/live?sort=price_desc&kabupaten='.urlencode('Kota Medan'));

        $response->assertOk();
        $this->assertTrue($response->json('has_filters'));
        $this->assertGreaterThan(0, $response->json('total'));
        $this->assertStringContainsString('Kota Medan', $response->json('html'));
    }

<<<<<<< HEAD
    public function test_destinations_live_endpoint_filters_optional_activities_and_facilities_case_insensitively(): void
    {
        $destination = app(DestinationRepository::class)
            ->search()
            ->first(static fn (Destination $item): bool => $item->activities !== [] && $item->facilities !== []);

        $this->assertNotNull($destination);

        $response = $this->getJson('/destinations/live?'.http_build_query([
            'dapatMelakukan' => mb_strtoupper($destination->activities[0]),
            'memilikiFasilitas' => mb_strtoupper($destination->facilities[0]),
        ]));

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('total'));
        $this->assertStringContainsString($destination->name, $response->json('html'));
    }

=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    public function test_map_live_endpoint_returns_results_and_markers(): void
    {
        $response = $this->getJson('/map/live?q=Danau');

        $response->assertOk();
        $response->assertJsonStructure([
            'html',
            'matchCount',
            'resultCount',
            'markerCount',
<<<<<<< HEAD
            'correction',
            'suggestions',
=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
            'markers' => ['*' => ['id', 'name', 'latitude', 'longitude', 'url']],
        ]);

        $this->assertGreaterThan(0, $response->json('matchCount'));
        $this->assertCount($response->json('markerCount'), $response->json('markers'));
    }

    public function test_destination_suggestion_endpoint_returns_prefix_matches(): void
    {
        $response = $this->getJson('/destinations/suggest?q=Dan');

        $response->assertOk();
<<<<<<< HEAD
        $response->assertJsonStructure(['html', 'count', 'correction', 'suggestions']);
=======
        $response->assertJsonStructure(['html', 'count']);
>>>>>>> ee7c4b41627891394572865c2960230988e32b45

        $this->assertGreaterThan(0, $response->json('count'));
        $this->assertStringContainsString('Danau', $response->json('html'));
    }

    public function test_search_routes_reject_array_valued_query_parameters_without_server_errors(): void
    {
<<<<<<< HEAD
        config()->set('app.locale', 'id');

=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        foreach ([
            '/destinations?q%5B%5D=Danau',
            '/destinations/live?q%5B%5D=Danau',
            '/destinations/suggest?q%5B%5D=Danau',
            '/map?q%5B%5D=Danau',
            '/map/live?q%5B%5D=Danau',
        ] as $url) {
<<<<<<< HEAD
            $this->getJson($url)
                ->assertUnprocessable()
                ->assertJsonPath('errors.q.0', 'kata pencarian harus berupa teks.');
=======
            $this->getJson($url)->assertUnprocessable();
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        }
    }
}
