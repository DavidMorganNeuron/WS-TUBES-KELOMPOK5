<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    public function test_chatbot_reports_when_the_api_key_is_missing(): void
    {
        config()->set('services.gemini.key', '');

        $this->postJson('/chatbot/send', ['message' => 'Halo'])
            ->assertStatus(503)
            ->assertJsonPath('reply', 'WayFinder AI belum siap digunakan. Silakan hubungi pengelola situs.');
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
    }

    public function test_chatbot_sends_the_key_in_a_header_and_forwards_conversation_history(): void
    {
        config()->set('services.gemini.key', 'test-key');
        config()->set('services.gemini.model', 'gemini-2.5-flash-lite');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Danau Toba adalah danau vulkanik.']]]],
                ],
            ]),
        ]);

        $this->postJson('/chatbot/send', [
            'message' => 'Ceritakan Danau Toba',
            'history' => [
                ['role' => 'user', 'text' => 'Halo'],
                ['role' => 'model', 'text' => 'Hai! Ada yang bisa dibantu?'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('reply', 'Danau Toba adalah danau vulkanik.');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && $payload['contents'][0]['role'] === 'user'
                && $payload['contents'][1]['role'] === 'model'
                && $payload['contents'][2]['parts'][0]['text'] === 'Ceritakan Danau Toba'
                && str_contains($payload['system_instruction']['parts'][0]['text'], 'KONTEKS ONTOLOGI');
        });
    }

    public function test_chatbot_returns_a_generic_message_when_the_upstream_service_fails(): void
    {
        config()->set('services.gemini.key', 'test-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'secret upstream detail'], 500),
        ]);

        $response = $this->postJson('/chatbot/send', ['message' => 'Halo']);

        $response->assertStatus(502);
        $response->assertJsonPath('reply', 'WayFinder AI sedang sibuk. Silakan coba lagi sebentar lagi.');
        $this->assertStringNotContainsString('secret upstream detail', $response->getContent());
    }

    public function test_chatbot_is_rate_limited(): void
    {
        config()->set('services.gemini.key', '');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55']);

        for ($request = 0; $request < 10; $request++) {
            $this->postJson('/chatbot/send', ['message' => 'Halo'])->assertStatus(503);
        }

        $this->postJson('/chatbot/send', ['message' => 'Halo'])->assertStatus(429);
    }
}
