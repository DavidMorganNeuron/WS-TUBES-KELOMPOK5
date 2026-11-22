<?php

namespace Tests\Feature;

<<<<<<< HEAD
use App\Services\Ontology\DestinationRepository;
=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
<<<<<<< HEAD
    public function test_chatbot_uses_local_search_when_the_api_key_is_missing(): void
    {
        config()->set('services.gemini.key', '');

        $response = $this->postJson('/chatbot/send', [
            'message' => 'Buatkan itinerary 3 hari ke Danau Toba',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['reply', 'destinations']);
        $this->assertStringContainsString('dataset lokal', $response->json('reply'));
        Http::assertNothingSent();
=======
    public function test_chatbot_reports_when_the_api_key_is_missing(): void
    {
        config()->set('services.gemini.key', '');

        $this->postJson('/chatbot/send', ['message' => 'Halo'])
            ->assertStatus(503)
            ->assertJsonPath('reply', 'WayFinder AI belum siap digunakan. Silakan hubungi pengelola situs.');
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    }

    public function test_chatbot_validates_message_length_and_history_shape(): void
    {
        config()->set('services.gemini.key', 'test-key');

        $this->postJson('/chatbot/send', ['message' => str_repeat('a', 801)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->postJson('/chatbot/send', [
            'message' => 'Halo',
            'history' => [['role' => 'system', 'text' => 'ignored']],
        ])->assertStatus(422);
<<<<<<< HEAD

        $this->postJson('/chatbot/send', [
            'message' => 'Halo',
            'history' => array_fill(0, 21, ['role' => 'user', 'text' => 'Hai']),
        ])->assertStatus(422)
            ->assertJsonValidationErrors('history');

        $this->postJson('/chatbot/send', [
            'message' => 'Halo',
            'history' => [['role' => 'user', 'text' => str_repeat('a', 4001)]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('history.0.text');
=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    }

    public function test_chatbot_sends_the_key_in_a_header_and_forwards_conversation_history(): void
    {
        config()->set('services.gemini.key', 'test-key');
<<<<<<< HEAD
        config()->set('services.gemini.model', 'gemini-3.5-flash-lite');
=======
        config()->set('services.gemini.model', 'gemini-2.5-flash-lite');
>>>>>>> ee7c4b41627891394572865c2960230988e32b45

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Danau Toba adalah danau vulkanik.']]]],
                ],
            ]),
        ]);

<<<<<<< HEAD
        $history = [];
        for ($index = 0; $index < 20; $index++) {
            $history[] = [
                'role' => $index % 2 === 0 ? 'user' : 'model',
                'text' => 'Riwayat '.$index,
            ];
        }

        $this->postJson('/chatbot/send', [
            'message' => 'Buatkan itinerary 3 hari ke Danau Toba',
            'history' => $history,
        ])
            ->assertOk()
            ->assertJsonPath('reply', 'Danau Toba adalah danau vulkanik.')
            ->assertJsonStructure([
                'reply',
                'destinations' => [
                    '*' => ['id', 'name', 'latitude', 'longitude', 'location'],
                ],
            ]);
=======
        $this->postJson('/chatbot/send', [
            'message' => 'Ceritakan Danau Toba',
            'history' => [
                ['role' => 'user', 'text' => 'Halo'],
                ['role' => 'model', 'text' => 'Hai! Ada yang bisa dibantu?'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('reply', 'Danau Toba adalah danau vulkanik.');
>>>>>>> ee7c4b41627891394572865c2960230988e32b45

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

<<<<<<< HEAD
            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && $payload['contents'][0]['role'] === 'user'
                && $payload['contents'][0]['parts'][0]['text'] === 'Riwayat 14'
                && $payload['contents'][5]['role'] === 'model'
                && $payload['contents'][5]['parts'][0]['text'] === 'Riwayat 19'
                && count($payload['contents']) === 7
                && $payload['contents'][6]['parts'][0]['text'] === 'Buatkan itinerary 3 hari ke Danau Toba'
                && str_contains($payload['systemInstruction']['parts'][0]['text'], 'KONTEKS ONTOLOGI');
        });
    }

    public function test_chatbot_uses_local_search_when_the_upstream_service_fails(): void
=======
            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && $payload['contents'][0]['role'] === 'user'
                && $payload['contents'][1]['role'] === 'model'
                && $payload['contents'][2]['parts'][0]['text'] === 'Ceritakan Danau Toba'
                && str_contains($payload['system_instruction']['parts'][0]['text'], 'KONTEKS ONTOLOGI');
        });
    }

    public function test_chatbot_returns_a_generic_message_when_the_upstream_service_fails(): void
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    {
        config()->set('services.gemini.key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'secret upstream detail'], 500),
        ]);

<<<<<<< HEAD
        $response = $this->postJson('/chatbot/send', [
            'message' => 'Buatkan itinerary 3 hari ke Danau Toba',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['reply', 'destinations']);
        $this->assertStringContainsString('dataset lokal', $response->json('reply'));
        $this->assertStringNotContainsString('secret upstream detail', $response->getContent());
    }

    public function test_chatbot_retries_rate_limited_requests_then_uses_local_search(): void
    {
        config()->set('services.gemini.key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'service unavailable'], 503),
        ]);

        $response = $this->postJson('/chatbot/send', [
            'message' => 'Buatkan itinerary 3 hari ke Danau Toba',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['reply', 'destinations']);
        $this->assertStringContainsString('dataset lokal', $response->json('reply'));
        Http::assertSentCount(2);
    }

    public function test_structured_local_questions_do_not_call_gemini_and_return_map_destinations(): void
    {
        config()->set('services.gemini.key', 'test-key');

        $response = $this->postJson('/chatbot/send', [
            'message' => 'wisata alam di Kabupaten Karo',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'reply',
                'destinations' => [
                    '*' => ['id', 'name', 'latitude', 'longitude', 'location'],
                ],
            ]);

        $this->assertNotEmpty($response->json('destinations'));
        $this->assertLessThanOrEqual(5, count($response->json('destinations')));
        foreach ($response->json('destinations') as $destination) {
            $this->assertIsNumeric($destination['latitude']);
            $this->assertGreaterThanOrEqual(-90, $destination['latitude']);
            $this->assertLessThanOrEqual(90, $destination['latitude']);
            $this->assertIsNumeric($destination['longitude']);
            $this->assertGreaterThanOrEqual(-180, $destination['longitude']);
            $this->assertLessThanOrEqual(180, $destination['longitude']);
        }

        Http::assertNothingSent();
    }

    public function test_local_recommendations_respect_digit_and_word_number_limits(): void
    {
        config()->set('services.gemini.key', 'test-key');
        $bahariCount = app(DestinationRepository::class)->search(['jenis' => 'Bahari'])
            ->filter(fn ($destination): bool => $destination->hasCoordinates())
            ->count();
        $this->assertGreaterThanOrEqual(3, $bahariCount);

        foreach ([
            ['message' => '3 tempat wisata bahari', 'count' => 3],
            ['message' => 'sebutkan lima wisata alam', 'count' => 5],
            ['message' => '10 wisata alam', 'count' => 10],
            ['message' => 'wisata alam harga 10 rb', 'count' => 5],
            ['message' => 'kasih 2 rekomendasi wisata alam di Kabupaten Karo', 'count' => 2],
        ] as $case) {
            $response = $this->postJson('/chatbot/send', ['message' => $case['message']]);

            $response->assertOk();
            $this->assertCount(
                $case['count'],
                $response->json('destinations'),
                'Permintaan jumlah tidak dipenuhi: '.$case['message'].'; '.$response->json('reply'),
            );
            $this->assertStringContainsString('**'.$case['count'], $response->json('reply'));
        }

        Http::assertNothingSent();
    }

    public function test_activity_filter_matches_ontology_activity_labels(): void
    {
        config()->set('services.gemini.key', 'test-key');
        $repository = app(DestinationRepository::class);
        $activity = $repository->filterOptions()['activities'][0];
        $expectedIds = $repository->search([
            'dapatMelakukan' => $activity,
            'sort' => 'rating',
        ])->filter(fn ($destination): bool => $destination->hasCoordinates())
            ->take(2)
            ->pluck('id')
            ->all();

        $response = $this->postJson('/chatbot/send', [
            'message' => '2 destinasi untuk '.$activity,
        ]);

        $response->assertOk();
        $this->assertCount(2, $response->json('destinations'));
        $this->assertSame($expectedIds, collect($response->json('destinations'))->pluck('id')->all());

        $facility = $repository->filterOptions()['facilities'][0];
        $expectedFacilityIds = $repository->search([
            'memilikiFasilitas' => $facility,
            'sort' => 'rating',
        ])->filter(fn ($destination): bool => $destination->hasCoordinates())
            ->take(2)
            ->pluck('id')
            ->all();

        $facilityResponse = $this->postJson('/chatbot/send', [
            'message' => '2 destinasi dengan '.$facility,
        ]);

        $facilityResponse->assertOk();
        $this->assertCount(2, $facilityResponse->json('destinations'));
        $this->assertSame($expectedFacilityIds, collect($facilityResponse->json('destinations'))->pluck('id')->all());
        Http::assertNothingSent();
    }

    public function test_narrative_comparison_is_forwarded_to_gemini_with_ontology_context(): void
    {
        config()->set('services.gemini.key', 'test-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Keduanya cocok untuk liburan keluarga.']]]],
                ],
            ]),
        ]);

        $this->postJson('/chatbot/send', [
            'message' => 'Lebih bagus mana Danau Toba atau Berastagi untuk keluarga?',
        ])->assertOk()
            ->assertJsonPath('reply', 'Keduanya cocok untuk liburan keluarga.');

        Http::assertSent(fn (Request $request): bool => str_contains(
            $request->data()['systemInstruction']['parts'][0]['text'],
            '<ontology_context>',
        ));
    }

    public function test_dataset_statistics_are_answered_locally_from_the_ontology(): void
    {
        config()->set('services.gemini.key', 'test-key');
        $total = app(DestinationRepository::class)->all()->count();

        $response = $this->postJson('/chatbot/send', ['message' => 'Berapa total wisata?']);

        $response->assertOk()
            ->assertJsonStructure(['reply', 'destinations']);
        $this->assertStringContainsString('**'.$total.' destinasi**', $response->json('reply'));
        Http::assertNothingSent();
    }

    public function test_destination_details_do_not_include_weather_information(): void
    {
        config()->set('services.gemini.key', 'test-key');

        $response = $this->postJson('/chatbot/send', ['message' => 'Info Danau Toba']);

        $response->assertOk()
            ->assertJsonStructure(['reply', 'destinations'])
            ->assertJsonPath('destinations.0.name', 'Danau Toba');

        $this->assertStringNotContainsString('cuaca', mb_strtolower($response->json('reply')));
        Http::assertNothingSent();
    }

    public function test_greeting_is_answered_locally_without_a_gemini_key(): void
    {
        config()->set('services.gemini.key', '');

        $this->postJson('/chatbot/send', ['message' => 'Halo!'])
            ->assertOk()
            ->assertJsonStructure(['reply', 'destinations'])
            ->assertJsonPath('destinations', []);

        Http::assertNothingSent();
    }

=======
        $response = $this->postJson('/chatbot/send', ['message' => 'Halo']);

        $response->assertStatus(502);
        $response->assertJsonPath('reply', 'WayFinder AI sedang sibuk. Silakan coba lagi sebentar lagi.');
        $this->assertStringNotContainsString('secret upstream detail', $response->getContent());
    }

>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    public function test_chatbot_is_rate_limited(): void
    {
        config()->set('services.gemini.key', '');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55']);

        for ($request = 0; $request < 10; $request++) {
<<<<<<< HEAD
            $this->postJson('/chatbot/send', ['message' => 'Halo'])->assertOk();
=======
            $this->postJson('/chatbot/send', ['message' => 'Halo'])->assertStatus(503);
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        }

        $this->postJson('/chatbot/send', ['message' => 'Halo'])->assertStatus(429);
    }
}
