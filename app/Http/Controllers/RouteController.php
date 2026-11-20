<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RouteController extends Controller
{
    private const ENDPOINT = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    private const FIELD_MASK = 'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline,routes.description,routes.routeLabels,routes.legs.steps.navigationInstruction,routes.legs.steps.distanceMeters';

    public function compute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'destination_lat' => ['required', 'numeric', 'between:-90,90'],
            'destination_lng' => ['required', 'numeric', 'between:-180,180'],
            'travel_mode' => ['sometimes', 'string', 'in:DRIVE,TWO_WHEELER,WALK'],
        ]);
        $apiKey = config('services.google_maps.key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return response()->json($this->unavailable());
        }

        $originLatitude = (float) $validated['origin_lat'];
        $originLongitude = (float) $validated['origin_lng'];
        $destinationLatitude = (float) $validated['destination_lat'];
        $destinationLongitude = (float) $validated['destination_lng'];
        $travelMode = $validated['travel_mode'] ?? 'DRIVE';
        $cacheKey = 'google-routes:'.hash('sha256', implode('|', [
            sprintf('%.6F', $originLatitude),
            sprintf('%.6F', $originLongitude),
            sprintf('%.6F', $destinationLatitude),
            sprintf('%.6F', $destinationLongitude),
            $travelMode,
        ]));

        $result = Cache::remember($cacheKey, now()->addMinutes(30), function () use (
            $apiKey,
            $originLatitude,
            $originLongitude,
            $destinationLatitude,
            $destinationLongitude,
            $travelMode,
        ): array {
            try {
                $response = Http::acceptJson()
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-Goog-Api-Key' => $apiKey,
                        'X-Goog-FieldMask' => self::FIELD_MASK,
                    ])
                    ->connectTimeout(3)
                    ->timeout(15)
                    ->post(self::ENDPOINT, [
                        'origin' => [
                            'location' => [
                                'latLng' => [
                                    'latitude' => $originLatitude,
                                    'longitude' => $originLongitude,
                                ],
                            ],
                        ],
                        'destination' => [
                            'location' => [
                                'latLng' => [
                                    'latitude' => $destinationLatitude,
                                    'longitude' => $destinationLongitude,
                                ],
                            ],
                        ],
                        'travelMode' => $travelMode,
                        'computeAlternativeRoutes' => true,
                        'languageCode' => 'id',
                        'units' => 'METRIC',
                    ]);
            } catch (ConnectionException $exception) {
                Log::warning('Google Routes API tidak dapat dihubungi.', [
                    'exception' => $exception::class,
                ]);

                return $this->unavailable();
            }

            if ($response->failed()) {
                Log::warning('Google Routes API mengembalikan respons gagal.', [
                    'status' => $response->status(),
                ]);

                return $this->unavailable();
            }

            return $this->formatRoutes($response->json());
        });

        if (($result['available'] ?? false) !== true) {
            Cache::forget($cacheKey);
        }

        return response()->json($result);
    }

    /**
     * @return array{available: bool, message?: string, routes?: list<array<string, mixed>>}
     */
    private function formatRoutes(mixed $payload): array
    {
        $routeItems = is_array($payload) ? ($payload['routes'] ?? null) : null;

        if (! is_array($routeItems)) {
            Log::warning('Google Routes API mengembalikan data rute yang tidak valid.');

            return $this->unavailable();
        }

        $routes = [];

        foreach (array_values($routeItems) as $index => $route) {
            if (! is_array($route)) {
                continue;
            }

            $encodedPolyline = data_get($route, 'polyline.encodedPolyline');

            if (! is_string($encodedPolyline) || $encodedPolyline === '') {
                continue;
            }

            $labels = $route['routeLabels'] ?? [];
            $labels = is_array($labels) ? $labels : [];
            $isDefault = in_array('DEFAULT_ROUTE', $labels, true);
            $routeLabel = $labels[0] ?? null;
            $description = $route['description'] ?? null;
            $summary = is_string($description) && trim($description) !== ''
                ? trim($description)
                : $this->routeLabel($routeLabel, $isDefault, $index);

            $routes[] = [
                'index' => $index,
                'summary' => $summary,
                'distanceText' => $this->distanceText($route['distanceMeters'] ?? null),
                'durationText' => $this->durationText($route['duration'] ?? null),
                'isDefault' => $isDefault,
                'encodedPolyline' => $encodedPolyline,
                'steps' => $this->formatSteps($route['legs'] ?? []),
            ];
        }

        if ($routes === []) {
            Log::warning('Google Routes API tidak mengembalikan jalur yang dapat ditampilkan.');

            return $this->unavailable();
        }

        return [
            'available' => true,
            'routes' => $routes,
        ];
    }

    /**
     * @return list<array{instruction: string, distanceText: string}>
     */
    private function formatSteps(mixed $legs): array
    {
        if (! is_array($legs)) {
            return [];
        }

        $steps = [];

        foreach ($legs as $leg) {
            if (! is_array($leg) || ! is_array($leg['steps'] ?? null)) {
                continue;
            }

            foreach ($leg['steps'] as $step) {
                if (! is_array($step)) {
                    continue;
                }

                $instruction = data_get($step, 'navigationInstruction.instructions');

                if (! is_string($instruction) || trim($instruction) === '') {
                    continue;
                }

                $steps[] = [
                    'instruction' => trim($instruction),
                    'distanceText' => $this->stepDistanceText($step['distanceMeters'] ?? null),
                ];
            }
        }

        return $steps;
    }

    private function distanceText(mixed $distanceMeters): string
    {
        if (! is_numeric($distanceMeters) || (float) $distanceMeters < 0) {
            return 'Jarak tidak tersedia';
        }

        $distance = number_format((float) $distanceMeters / 1000, 1, ',', '.');
        $distance = preg_replace('/,0$/', '', $distance) ?? $distance;

        return $distance.' km';
    }

    private function stepDistanceText(mixed $distanceMeters): string
    {
        if (! is_numeric($distanceMeters) || (float) $distanceMeters < 0) {
            return '';
        }

        if ((float) $distanceMeters < 1000) {
            return number_format((float) $distanceMeters, 0, ',', '.').' m';
        }

        return $this->distanceText($distanceMeters);
    }

    private function durationText(mixed $duration): string
    {
        if (! is_string($duration) || preg_match('/^(\d+(?:\.\d+)?)s$/', $duration, $matches) !== 1) {
            return 'Durasi tidak tersedia';
        }

        $totalMinutes = (int) round((float) $matches[1] / 60);
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return $hours > 0
            ? $hours.' jam '.$minutes.' mnt'
            : $minutes.' mnt';
    }

    private function routeLabel(mixed $label, bool $isDefault, int $index): string
    {
        if ($isDefault) {
            return 'Rute utama';
        }

        if (is_string($label) && trim($label) !== '') {
            $knownLabels = [
                'FUEL_EFFICIENT' => 'Rute hemat bahan bakar',
                'SHORTER_DISTANCE' => 'Rute lebih singkat',
            ];

            return $knownLabels[$label] ?? 'Rute '.ucwords(strtolower(str_replace('_', ' ', $label)));
        }

        return 'Rute alternatif '.($index + 1);
    }

    /**
     * @return array{available: false, message: string}
     */
    private function unavailable(): array
    {
        return [
            'available' => false,
            'message' => 'Rute belum dapat dihitung. Periksa koneksi atau coba lagi.',
        ];
    }
}
