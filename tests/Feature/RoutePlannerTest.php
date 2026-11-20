<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoutePlannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
        config()->set('services.google_maps.key', 'test-routes-key');
    }

    public function test_routes_api_response_is_formatted_and_cached_per_travel_mode(): void
    {
        Http::fake([
            'https://routes.googleapis.com/*' => Http::response([
                'routes' => [
                    [
                        'description' => 'Lewat Jalan Lintas Sumatera',
                        'distanceMeters' => 383000,
                        'duration' => '32940s',
                        'routeLabels' => ['DEFAULT_ROUTE'],
                        'polyline' => ['encodedPolyline' => 'encoded-main-route'],
                        'legs' => [
                            [
                                'steps' => [
                                    [
                                        'navigationInstruction' => ['instructions' => 'Belok kiri ke Jalan Lintas Sumatera'],
                                        'distanceMeters' => 2100,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'distanceMeters' => 402500,
                        'duration' => '3600s',
                        'routeLabels' => [],
                        'polyline' => ['encodedPolyline' => 'encoded-alternative-route'],
                        'legs' => [],
                    ],
                ],
            ]),
        ]);

        $payload = $this->routePayload();

        $this->postJson(route('routes.compute'), $payload)
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('routes.0.index', 0)
            ->assertJsonPath('routes.0.summary', 'Lewat Jalan Lintas Sumatera')
            ->assertJsonPath('routes.0.distanceText', '383 km')
            ->assertJsonPath('routes.0.durationText', '9 jam 9 mnt')
            ->assertJsonPath('routes.0.isDefault', true)
            ->assertJsonPath('routes.0.encodedPolyline', 'encoded-main-route')
            ->assertJsonPath('routes.0.steps.0.instruction', 'Belok kiri ke Jalan Lintas Sumatera')
            ->assertJsonPath('routes.0.steps.0.distanceText', '2,1 km')
            ->assertJsonPath('routes.1.summary', 'Rute alternatif 2')
            ->assertJsonCount(2, 'routes');

        $defaultModePayload = $payload;
        unset($defaultModePayload['travel_mode']);

        $this->postJson(route('routes.compute'), $defaultModePayload)->assertOk();
        $this->postJson(route('routes.compute'), [...$payload, 'travel_mode' => 'TWO_WHEELER'])->assertOk();

        Http::assertSentCount(2);
        Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://routes.googleapis.com/directions/v2:computeRoutes'
            && $request->hasHeader('X-Goog-Api-Key', 'test-routes-key')
            && $request->hasHeader('X-Goog-FieldMask', 'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline,routes.description,routes.routeLabels,routes.legs.steps.navigationInstruction,routes.legs.steps.distanceMeters')
            && data_get($request->data(), 'origin.location.latLng.latitude') === 3.5
            && data_get($request->data(), 'destination.location.latLng.longitude') === 98.5
            && data_get($request->data(), 'travelMode') === 'DRIVE');
        Http::assertSent(static fn (Request $request): bool => data_get($request->data(), 'travelMode') === 'TWO_WHEELER');
    }

    public function test_provider_failure_returns_a_stable_unavailable_response(): void
    {
        Http::fake([
            'https://routes.googleapis.com/*' => Http::response(['error' => 'quota exceeded'], 429),
        ]);

        $this->postJson(route('routes.compute'), $this->routePayload())
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('message', 'Rute belum dapat dihitung. Periksa koneksi atau coba lagi.');
    }

    public function test_missing_api_key_returns_unavailable_without_calling_google(): void
    {
        config()->set('services.google_maps.key', '');
        Http::fake();

        $this->postJson(route('routes.compute'), $this->routePayload())
            ->assertOk()
            ->assertJsonPath('available', false);

        Http::assertSentCount(0);
    }

    public function test_invalid_coordinates_are_rejected_before_calling_google(): void
    {
        Http::fake();

        $this->postJson(route('routes.compute'), [
            ...$this->routePayload(),
            'origin_lat' => 91,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['origin_lat']);

        Http::assertSentCount(0);
    }

    /**
     * @return array{origin_lat: float, origin_lng: float, destination_lat: float, destination_lng: float, travel_mode: string}
     */
    private function routePayload(): array
    {
        return [
            'origin_lat' => 3.5,
            'origin_lng' => 98.6,
            'destination_lat' => 3.4,
            'destination_lng' => 98.5,
            'travel_mode' => 'DRIVE',
        ];
    }
}
