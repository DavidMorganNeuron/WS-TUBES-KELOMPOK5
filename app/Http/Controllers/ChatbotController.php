<?php

namespace App\Http\Controllers;

use App\Data\Destination;
use App\Services\Ontology\DestinationRepository;
use Illuminate\Http\Client\ConnectionException;
<<<<<<< HEAD
use Illuminate\Http\Client\RequestException;
=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ChatbotController extends Controller
{
    /**
     * Kata yang terlalu umum untuk dijadikan kata kunci pencarian konteks.
     *
     * @var list<string>
     */
    private const STOPWORDS = [
        'yang', 'untuk', 'dengan', 'dari', 'saya', 'apa', 'dimana', 'bagaimana', 'rekomendasi',
        'wisata', 'tempat', 'destinasi', 'terbaik', 'dan', 'atau', 'juga', 'bisa', 'tolong',
        'the', 'and', 'for', 'with', 'what', 'where', 'how', 'recommend', 'recommendation',
        'please', 'about', 'near', 'best', 'place', 'places', 'travel', 'tourist', 'tourism',
    ];

    /**
     * @var array<string, string>
     */
    private const TYPE_HINTS = [
        'alam' => 'Alam', 'nature' => 'Alam', 'gunung' => 'Alam', 'air terjun' => 'Alam',
        'danau' => 'Alam', 'hutan' => 'Alam', 'waterfall' => 'Alam', 'lake' => 'Alam',
        'budaya' => 'Budaya', 'culture' => 'Budaya', 'cultural' => 'Budaya', 'heritage' => 'Budaya',
        'museum' => 'Budaya', 'sejarah' => 'Budaya', 'history' => 'Budaya',
        'religi' => 'Religi', 'religion' => 'Religi', 'religious' => 'Religi', 'masjid' => 'Religi',
        'gereja' => 'Religi', 'vihara' => 'Religi', 'klenteng' => 'Religi', 'church' => 'Religi',
        'bahari' => 'Bahari', 'marine' => 'Bahari', 'pantai' => 'Bahari', 'beach' => 'Bahari',
        'pulau' => 'Bahari', 'island' => 'Bahari',
        'kuliner' => 'Kuliner', 'culinary' => 'Kuliner', 'food' => 'Kuliner', 'makanan' => 'Kuliner',
        'buatan' => 'Buatan', 'man-made' => 'Buatan', 'theme park' => 'Buatan', 'waterpark' => 'Buatan',
        'hiburan' => 'Buatan',
    ];

<<<<<<< HEAD
    /**
     * @var list<string>
     */
    private const COMPLEX_HINTS = [
        'itinerary', 'itinerari', 'rencana perjalanan', 'susun perjalanan', 'susun rute', 'rundown',
        'bandingkan', 'perbandingan', 'membandingkan', 'compare', 'comparison', 'lebih bagus mana',
        'kenapa', 'mengapa', 'bagaimana rute', 'tips', 'cocok untuk', 'keluarga', 'anak-anak',
        'honeymoon',
    ];

    /**
     * @var list<string>
     */
    private const FREE_HINTS = ['gratis', 'free', 'tanpa tiket'];

    /**
     * @var list<string>
     */
    private const CHEAP_HINTS = ['murah', 'termurah'];

    /**
     * @var list<string>
     */
    private const TOP_HINTS = ['terbaik', 'terpopuler', 'populer', 'rating tertinggi', 'favorit'];

    /**
     * @var array<string, list<string>>
     */
    private const ATTRIBUTE_HINTS = [
        'camping' => ['camping', 'berkemah'],
        'berkemah' => ['camping', 'berkemah'],
        'foto' => ['foto', 'berfoto', 'spot foto'],
        'musholla' => ['musholla', 'mushola', 'musala'],
        'mushola' => ['musholla', 'mushola', 'musala'],
        'musala' => ['musholla', 'mushola', 'musala'],
        'parkir' => ['parkir'],
        'toilet' => ['toilet'],
        'penginapan' => ['penginapan'],
        'kuliner' => ['kuliner'],
        'berenang' => ['berenang', 'renang'],
        'renang' => ['berenang', 'renang'],
    ];

=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    public function chat(Request $request, DestinationRepository $repository): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:800'],
<<<<<<< HEAD
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,model'],
            'history.*.text' => ['required_with:history', 'string', 'max:4000'],
        ]);

        $message = trim($validated['message']);
        $localReply = $this->tryLocalReply($message, $repository);

        if ($localReply !== null) {
            return response()->json($localReply);
        }

        $apiKey = trim((string) config('services.gemini.key', ''));

        if ($apiKey === '') {
            Log::warning('GEMINI_API_KEY belum dikonfigurasi; chatbot menggunakan pencarian lokal.');

            return response()->json($this->fallbackSearchReply($message, $repository));
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.gemini.timeout', 30))
                ->retry(2, 1000, static function (Throwable $exception): bool {
                    return $exception instanceof RequestException
                        && in_array($exception->response->status(), [429, 503], true);
                })
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post($this->endpoint(), [
                    'systemInstruction' => [
=======
            'history' => ['sometimes', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,model'],
            'history.*.text' => ['required_with:history', 'string', 'max:800'],
        ]);

        $apiKey = (string) config('services.gemini.key');

        if ($apiKey === '') {
            Log::warning('Permintaan chatbot ditolak karena GEMINI_API_KEY belum dikonfigurasi.');

            return response()->json([
                'reply' => 'WayFinder AI belum siap digunakan. Silakan hubungi pengelola situs.',
            ], 503);
        }

        $message = trim($validated['message']);

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.gemini.timeout', 30))
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post($this->endpoint(), [
                    'system_instruction' => [
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
                        'parts' => [
                            ['text' => $this->systemInstruction($message, $repository)],
                        ],
                    ],
                    'contents' => $this->contents($message, $validated['history'] ?? []),
                    'generationConfig' => [
                        'maxOutputTokens' => 700,
                        'temperature' => 0.2,
                    ],
                ]);
        } catch (ConnectionException $exception) {
<<<<<<< HEAD
            Log::warning('Koneksi Gemini gagal; chatbot menggunakan pencarian lokal.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json($this->fallbackSearchReply($message, $repository));
        } catch (RequestException $exception) {
            Log::warning('Gemini gagal setelah percobaan ulang; chatbot menggunakan pencarian lokal.', [
                'status' => $exception->response->status(),
            ]);

            return response()->json($this->fallbackSearchReply($message, $repository));
        }

        if ($response->failed()) {
            Log::warning('Gemini mengembalikan respons gagal; chatbot menggunakan pencarian lokal.', [
=======
            Log::error('Koneksi ke Gemini gagal.', ['message' => $exception->getMessage()]);

            return response()->json([
                'reply' => 'WayFinder AI sedang tidak dapat dihubungi. Coba lagi beberapa saat lagi.',
            ], 502);
        } catch (Throwable $exception) {
            Log::error('Permintaan chatbot gagal.', [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'reply' => 'Terjadi kesalahan saat memproses pesan Anda. Silakan coba lagi.',
            ], 500);
        }

        if ($response->failed()) {
            Log::error('Gemini mengembalikan respons gagal.', [
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

<<<<<<< HEAD
            return response()->json($this->fallbackSearchReply($message, $repository));
=======
            return response()->json([
                'reply' => 'WayFinder AI sedang sibuk. Silakan coba lagi sebentar lagi.',
            ], 502);
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        }

        $reply = collect($response->json('candidates.0.content.parts', []))
            ->pluck('text')
            ->filter(fn (mixed $text): bool => is_string($text) && trim($text) !== '')
            ->implode("\n");

<<<<<<< HEAD
        if (trim($reply) === '') {
            return response()->json($this->fallbackSearchReply($message, $repository));
        }

        return response()->json($this->responsePayload(
            trim($reply),
            $this->matchingDestinations(Str::lower($message), $repository->all(), $repository),
        ));
    }

    /**
     * @return array{reply: string, destinations: list<array{id: string, name: string, latitude: float, longitude: float, location: string}>}|null
     */
    private function tryLocalReply(string $message, DestinationRepository $repository): ?array
    {
        $normalized = Str::lower(trim($message));

        if ($normalized === '') {
            return null;
        }

        if ($this->isGreeting($normalized)) {
            return $this->responsePayload(
                'Halo! Saya WayFinder AI, asisten wisata Sumatera Utara. Saya bisa membantu mencari destinasi berdasarkan kategori, wilayah, harga, rating, dan informasi destinasi. Contoh: "wisata alam di Kabupaten Karo", "tempat wisata gratis", atau "info Istana Maimun".',
            );
        }

        if ($this->hasAny($normalized, ['berapa total', 'jumlah wisata', 'ada berapa', 'statistik'])) {
            return $this->statisticsReply($repository);
        }

        if ($this->isComplexNarrativeQuery($normalized)) {
            return null;
        }

        $region = $this->extractRegion($normalized, $repository);
        $types = $this->extractTypes($normalized);
        $requestedLimit = $this->extractRequestedLimit($normalized, 0);
        $limit = $this->extractRequestedLimit($normalized);
        $attributeTerms = $this->extractAttributeTerms($normalized, $repository);
        if (in_array('Kuliner', $types, true)) {
            $attributeTerms = array_values(array_diff($attributeTerms, ['kuliner']));
        }

        $mentioned = $this->findMentionedDestinations($normalized, $repository->all());

        if ($mentioned->isNotEmpty()) {
            $destination = $mentioned->first();

            return $this->destinationDetailReply($destination, $repository);
        }

        $wantsFree = $this->hasAny($normalized, self::FREE_HINTS);
        $wantsCheap = $this->hasAny($normalized, self::CHEAP_HINTS);
        $wantsTopRated = $this->hasAny($normalized, self::TOP_HINTS);
        $wantsRecommendation = $this->hasAny($normalized, ['rekomendasi', 'recommend', 'wisata terbaik']);

        if ($types !== [] || $region !== null || $wantsFree || $wantsCheap || $wantsTopRated
            || $wantsRecommendation || $attributeTerms !== [] || $requestedLimit > 0) {
            return $this->recommendationReply(
                $repository,
                $types,
                $region,
                $wantsFree,
                $wantsCheap,
                $wantsTopRated,
                $attributeTerms,
                $limit,
            );
        }

        return null;
    }

    /**
     * @return array{reply: string, destinations: list<array{id: string, name: string, latitude: float, longitude: float, location: string}>}
     */
    private function statisticsReply(DestinationRepository $repository): array
    {
        $destinations = $repository->all();
        $facets = $repository->facets();
        $types = collect($facets['types'] ?? [])
            ->map(fn (int $count, string $type): string => '- '.$type.': '.$count)
            ->implode("\n");

        $reply = sprintf(
            "Dataset WayFinder mencatat **%d destinasi** di **%d kabupaten/kota**. Ada %d destinasi dengan rating, dengan rata-rata rating %s.\n\nJumlah destinasi per kategori:\n%s",
            $destinations->count(),
            count($facets['kabupaten'] ?? []),
            (int) ($facets['rated_count'] ?? 0),
            isset($facets['average_rating']) && $facets['average_rating'] !== null
                ? number_format((float) $facets['average_rating'], 2, ',', '.')
                : 'belum tersedia',
            $types !== '' ? $types : '- Data kategori belum tersedia.',
        );

        return $this->responsePayload($reply, $repository->topRated(5));
    }

    /**
     * @param  list<string>  $types
     * @param  list<string>  $attributeTerms
     * @return array{reply: string, destinations: list<array{id: string, name: string, latitude: float, longitude: float, location: string}>}
     */
    private function recommendationReply(
        DestinationRepository $repository,
        array $types,
        ?string $region,
        bool $wantsFree,
        bool $wantsCheap,
        bool $wantsTopRated,
        array $attributeTerms,
        int $limit,
    ): array {
        $destinations = $repository->search([
            'jenis' => $types[0] ?? null,
            'kabupaten' => $region,
            'sort' => 'rating',
        ]);

        if ($wantsFree) {
            $destinations = $destinations->filter(fn (Destination $destination): bool => $this->isFree($destination));
        }

        if ($attributeTerms !== []) {
            $destinations = $destinations->filter(
                fn (Destination $destination): bool => $this->matchesAttributeTerms($destination, $attributeTerms),
            );
        }

        if ($wantsTopRated) {
            $destinations = $destinations->filter(fn (Destination $destination): bool => ($destination->rating ?? null) !== null);
        }

        $destinations = $destinations
            ->filter(fn (Destination $destination): bool => $this->hasValidCoordinates($destination));

        if ($wantsCheap) {
            $destinations = $destinations->sortBy(fn (Destination $destination): array => [
                $destination->lowestPrice() ?? PHP_FLOAT_MAX,
                -($destination->rating ?? -1.0),
            ]);
        } else {
            $destinations = $destinations->sortByDesc(fn (Destination $destination): float => $destination->rating ?? -1.0);
        }

        $destinations = $destinations->take($limit)->values();

        if ($destinations->isEmpty()) {
            return $this->responsePayload(
                'Belum ada destinasi yang cocok dengan filter tersebut pada dataset WayFinder. Coba kategori atau wilayah lain.',
            );
        }

        $count = $destinations->count();
        $category = $types[0] ?? '';
        $subject = $category !== '' ? 'wisata '.$category : 'destinasi wisata';
        $location = $region !== null ? ' di '.$region : ' di Sumatera Utara';
        $opening = $wantsCheap
            ? "Ditemukan **{$count} {$subject}{$location}** dengan harga tiket terendah:"
            : "Berikut **{$count} rekomendasi {$subject}{$location}** dengan rating tertinggi:";

        if ($attributeTerms !== []) {
            $opening = rtrim($opening, ':').' yang sesuai dengan '.implode(', ', $attributeTerms).':';
        }

        $reply = $opening."\n\n"
            .$this->recommendationList($destinations);

        return $this->responsePayload($reply, $destinations, $limit);
    }

    /**
     * @return array{reply: string, destinations: list<array{id: string, name: string, latitude: float, longitude: float, location: string}>}
     */
    private function destinationDetailReply(Destination $destination, DestinationRepository $repository): array
    {
        $types = $destination->types ?? [];
        $prices = $destination->priceLabels();
        $facilities = $destination->facilities ?? [];
        $activities = $destination->activities ?? [];
        $nearby = $destination->hasCoordinates()
            ? $repository->nearby($destination, 3)
                ->map(fn (Destination $nearbyDestination): string => $nearbyDestination->name ?? '')
                ->filter()
                ->implode(', ')
            : '';
        $unknown = (string) config('ontology.unknown_label', 'tidak tercatat');

        $reply = sprintf(
            "**%s**\n\n%s\n\n- Kategori: %s\n- Wilayah: %s\n- Rating: %s\n- Harga tiket: %s\n- Alamat: %s\n- Jadwal: %s\n- Fasilitas: %s\n- Aktivitas: %s\n- Destinasi terdekat: %s",
            $destination->name ?? 'Destinasi',
            ($destination->fullDescription ?? '') !== ''
                ? $destination->fullDescription
                : (($destination->shortDescription ?? '') !== '' ? $destination->shortDescription : $unknown),
            $types !== [] ? implode(', ', $types) : $unknown,
            ($destination->location ?? '') !== '' ? $destination->location : $destination->province(),
            $destination->ratingLabel(),
            $prices !== [] ? implode(', ', $prices) : $unknown,
            ($destination->address ?? '') !== '' ? $destination->address : $unknown,
            $destination->openingHoursLabel(),
            $facilities !== [] ? implode(', ', $facilities) : $unknown,
            $activities !== [] ? implode(', ', $activities) : $unknown,
            $nearby !== '' ? $nearby : $unknown,
        );

        if ($destination->hasCoordinates()) {
            $reply .= "\n\n[Lihat lokasi di Google Maps](".$destination->mapsUrl().')';
        }

        return $this->responsePayload($reply, collect([$destination]));
    }

    /**
     * @return array{reply: string, destinations: list<array{id: string, name: string, latitude: float, longitude: float, location: string}>}
     */
    private function fallbackSearchReply(string $message, DestinationRepository $repository): array
    {
        $normalized = Str::lower($message);
        $limit = $this->extractRequestedLimit($normalized);
        $matches = $this->matchingDestinations($normalized, $repository->all(), $repository);
        $region = $this->extractRegion($normalized, $repository);
        $types = $this->extractTypes($normalized);

        if ($matches->isEmpty() && ($region !== null || $types !== [])) {
            $matches = $repository->search([
                'jenis' => $types[0] ?? null,
                'kabupaten' => $region,
                'sort' => 'rating',
            ])->take($limit);
        }

        if ($matches->isEmpty()) {
            return $this->responsePayload(
                'WayFinder AI sedang tidak dapat menjawab pertanyaan naratif ini. Saya belum menemukan kecocokan yang cukup di dataset lokal. Coba tanyakan nama destinasi, kategori, wilayah, atau harga tiket.',
            );
        }

        $matches = $matches->take($limit)->values();

        return $this->responsePayload(
            "WayFinder AI sedang tidak tersedia. Berikut informasi yang dapat saya temukan pada dataset lokal:\n\n"
                .$this->recommendationList($matches),
            $matches,
            $limit,
        );
    }

    /**
     * @param  Collection<int, Destination>  $destinations
     * @return list<array{id: string, name: string, latitude: float, longitude: float, location: string}>
     */
    private function mapDestinations(Collection $destinations, int $limit = 5): array
    {
        return $destinations
            ->filter(fn (mixed $destination): bool => $destination instanceof Destination
                && $this->hasValidCoordinates($destination))
            ->map(fn (Destination $destination): array => [
                'id' => $destination->id ?? '',
                'name' => $destination->name ?? '',
                'latitude' => (float) ($destination->latitude ?? 0.0),
                'longitude' => (float) ($destination->longitude ?? 0.0),
                'location' => $destination->location ?? '',
            ])
            ->take(min(10, max(1, $limit)))
            ->values()
            ->all();
    }

    private function hasValidCoordinates(Destination $destination): bool
    {
        $latitude = $destination->latitude ?? null;
        $longitude = $destination->longitude ?? null;

        return is_numeric($latitude)
            && is_numeric($longitude)
            && $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180;
    }

    private function responsePayload(string $reply, ?Collection $destinations = null, int $limit = 5): array
    {
        return [
            'reply' => $reply,
            'destinations' => $this->mapDestinations($destinations ?? collect(), $limit),
        ];
    }

    /**
     * @param  Collection<int, Destination>  $destinations
     */
    private function recommendationList(Collection $destinations): string
    {
        return $destinations
            ->map(fn (Destination $destination): string => sprintf(
                '- **%s** (%s) | Rating: %s | Tiket: %s',
                $destination->name ?? 'Destinasi',
                ($destination->location ?? '') !== '' ? $destination->location : $destination->province(),
                $destination->ratingLabel(),
                $destination->priceLabel(),
            ))
            ->implode("\n");
    }

    private function extractRegion(string $message, DestinationRepository $repository): ?string
    {
        $regions = array_keys($repository->facets()['kabupaten'] ?? []);
        usort($regions, fn (string $first, string $second): int => mb_strlen($second) <=> mb_strlen($first));

        foreach ($regions as $region) {
            if ($region !== '' && str_contains($message, Str::lower($region))) {
                return $region;
            }
        }

        return null;
    }

    private function extractRequestedLimit(string $lower, int $default = 5, int $max = 10): int
    {
        $max = max(1, $max);
        $default = min($max, max(0, $default));
        $numberWords = [
            'satu' => 1,
            'dua' => 2,
            'tiga' => 3,
            'empat' => 4,
            'lima' => 5,
            'enam' => 6,
            'tujuh' => 7,
            'delapan' => 8,
            'sembilan' => 9,
            'sepuluh' => 10,
        ];
        $numberPattern = '(?:10|[1-9]|'.implode('|', array_keys($numberWords)).')';
        $pattern = '/(?<![\p{L}\p{N}.,])(?<number>'.$numberPattern.')(?![\p{L}\p{N}]|[.,]\d)/iu';
        preg_match_all($pattern, $lower, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches['number'] ?? [] as [$number, $offset]) {
            $number = Str::lower($number);
            $after = substr($lower, $offset + strlen($number));
            $before = substr($lower, max(0, $offset - 24), min(24, $offset));
            $context = substr($lower, max(0, $offset - 32), strlen($number) + 64);

            if (preg_match('/^\s*(?:rb|ribu|k|jam|hari|malam|bintang|rating|km)\b/iu', $after) === 1
                || preg_match('/\b(?:rp|harga|rating|bintang)\s*$/iu', $before) === 1
                || preg_match('/\b(?:wisata|destinasi|rekomendasi|tempat)\b/iu', $context) !== 1) {
                continue;
            }

            $value = ctype_digit($number) ? (int) $number : ($numberWords[$number] ?? 0);

            return min($max, max(1, $value));
        }

        return $default;
    }

    private function isComplexNarrativeQuery(string $lower): bool
    {
        return $this->hasAny($lower, self::COMPLEX_HINTS);
    }

    /**
     * @return list<string>
     */
    private function extractAttributeTerms(string $message, DestinationRepository $repository): array
    {
        $terms = [];

        foreach (self::ATTRIBUTE_HINTS as $hint => $aliases) {
            if (str_contains($message, $hint)) {
                $terms = [...$terms, ...$aliases];
            }
        }

        foreach ($repository->filterOptions() as $labels) {
            foreach ($labels as $label) {
                $normalizedLabel = Str::lower($label);

                if ($normalizedLabel !== ''
                    && ! in_array($normalizedLabel, ['tempat wisata', 'wisata', 'destinasi'], true)
                    && str_contains($message, $normalizedLabel)) {
                    $terms[] = $normalizedLabel;
                }
            }
        }

        return array_values(array_unique($terms));
    }

    /**
     * @param  list<string>  $terms
     */
    private function matchesAttributeTerms(Destination $destination, array $terms): bool
    {
        $searchableAttributes = Str::lower(implode(' ', [
            ...($destination->facilities ?? []),
            ...($destination->activities ?? []),
            $destination->shortDescription ?? '',
        ]));

        foreach ($terms as $term) {
            if ($term !== '' && str_contains($searchableAttributes, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function extractTypes(string $message): array
    {
        $types = [];

        foreach (self::TYPE_HINTS as $hint => $type) {
            if (str_contains($message, $hint) && ! in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * @param  Collection<int, Destination>  $destinations
     * @return Collection<int, Destination>
     */
    private function findMentionedDestinations(string $message, Collection $destinations): Collection
    {
        $matches = $destinations->filter(function (Destination $destination) use ($message): bool {
            $name = Str::lower($destination->name ?? '');
            $alternative = Str::lower($destination->alternativeName ?? '');

            return ($name !== '' && str_contains($message, $name))
                || (mb_strlen($alternative) >= 4 && str_contains($message, $alternative));
        });

        if ($matches->isNotEmpty()) {
            return $matches->sortBy(fn (Destination $destination): int => -mb_strlen($destination->name ?? ''))->values();
        }

        $query = preg_replace(
            '/\b(info|informasi|tiket|harga|alamat|jadwal|profil|jelaskan|tentang|destinasi|wisata|di|ke|dong|tolong|please)\b/u',
            ' ',
            $message,
        ) ?? $message;
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);

        if (mb_strlen($query) < 5) {
            return collect();
        }

        $candidates = $destinations->map(function (Destination $destination) use ($query): array {
            $name = Str::lower($destination->name ?? '');
            similar_text($query, $name, $similarity);

            return ['destination' => $destination, 'similarity' => $similarity];
        })->filter(fn (array $candidate): bool => $candidate['similarity'] >= 72.0);

        $best = $candidates->sortByDesc('similarity')->first();

        return $best !== null ? collect([$best['destination']]) : collect();
    }

    private function isGreeting(string $message): bool
    {
        $normalized = trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $message) ?? $message);

        return in_array($normalized, [
            'halo', 'hai', 'hi', 'hello', 'selamat pagi', 'selamat siang', 'selamat sore',
            'selamat malam', 'apa kabar',
        ], true);
    }

    /**
     * @param  list<string>  $hints
     */
    private function hasAny(string $message, array $hints): bool
    {
        foreach ($hints as $hint) {
            if (str_contains($message, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function isFree(Destination $destination): bool
    {
        foreach ($destination->prices ?? [] as $price) {
            if (in_array(Str::lower(trim((string) $price)), self::FREE_HINTS, true)) {
                return true;
            }
        }

        return ($destination->lowestPrice() ?? null) === 0.0;
=======
        if ($reply === '') {
            return response()->json([
                'reply' => 'Maaf, WayFinder AI belum dapat menyusun jawaban. Coba ajukan pertanyaan lain.',
            ]);
        }

        return response()->json(['reply' => $reply]);
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    }

    private function endpoint(): string
    {
        $endpoint = (string) config('services.gemini.endpoint');
        $model = (string) config('services.gemini.model');

        return $endpoint.'/models/'.$model.':generateContent';
    }

    /**
     * @param  list<array{role: string, text: string}>  $history
     * @return list<array{role: string, parts: list<array{text: string}>}>
     */
    private function contents(string $message, array $history): array
    {
<<<<<<< HEAD
        $contents = collect(array_slice($history, -6))
=======
        $contents = collect($history)
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
            ->map(fn (array $item): array => [
                'role' => $item['role'],
                'parts' => [['text' => $item['text']]],
            ])
            ->values()
            ->all();

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message]],
        ];

        return $contents;
    }

    private function systemInstruction(string $message, DestinationRepository $repository): string
    {
        return <<<PROMPT
Anda adalah WayFinder AI, pemandu wisata digital untuk destinasi di Sumatera Utara.
Jawab dengan ramah, ringkas, dan informatif. Jangan membuka jawaban dengan sapaan pembuka.
Gunakan hanya data dari konteks ontologi di bawah. Jika data tidak tersedia, katakan terus terang
bahwa informasinya belum tercatat dan jangan mengarang angka, alamat, atau nomor kontak.
Jawab dalam bahasa yang digunakan pengguna. Jangan menampilkan data pribadi atau kredensial.

KONTEKS ONTOLOGI (ringkas):
<<<<<<< HEAD
<ontology_context>
{$this->context($message, $repository)}
</ontology_context>
=======
{$this->context($message, $repository)}
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
PROMPT;
    }

    private function context(string $message, DestinationRepository $repository): string
    {
        $destinations = $repository->all();
        $facets = $repository->facets();
        $lower = Str::lower($message);
        $matched = $this->matchingDestinations($lower, $destinations, $repository);

        $lines = [
            'Total destinasi: '.$destinations->count(),
<<<<<<< HEAD
            'Jumlah per kategori: '.collect($facets['types'] ?? [])
                ->map(fn (int $count, string $type): string => $type.'='.$count)
                ->implode(', '),
            'Kabupaten/kota tercatat: '.count($facets['kabupaten'] ?? []),
            'Rata-rata rating: '.($facets['average_rating'] ?? 'tidak tersedia')
                .' dari '.($facets['rated_count'] ?? 0).' destinasi berating.',
=======
            'Jumlah per kategori: '.collect($facets['types'])
                ->map(fn (int $count, string $type): string => $type.'='.$count)
                ->implode(', '),
            'Kabupaten/kota tercatat: '.count($facets['kabupaten']),
            'Rata-rata rating: '.($facets['average_rating'] ?? 'tidak tersedia')
                .' dari '.$facets['rated_count'].' destinasi berating.',
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
            '',
            'Destinasi dengan rating tertinggi:',
        ];

        foreach ($repository->topRated(5) as $destination) {
            $lines[] = $this->describe($destination);
        }

        if ($matched->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Destinasi yang relevan dengan pertanyaan pengguna:';

            foreach ($matched as $destination) {
                $lines[] = $this->describe($destination);
            }
        }

        return Str::limit(implode("\n", $lines), 6000);
    }

    /**
     * @param  Collection<int, Destination>  $destinations
     * @return Collection<int, Destination>
     */
    private function matchingDestinations(string $lower, Collection $destinations, DestinationRepository $repository): Collection
    {
        $matched = $destinations->filter(function (Destination $destination) use ($lower): bool {
<<<<<<< HEAD
            $name = Str::lower($destination->name ?? '');
            $alternative = Str::lower($destination->alternativeName ?? '');
=======
            $name = Str::lower($destination->name);
            $alternative = Str::lower($destination->alternativeName);
>>>>>>> ee7c4b41627891394572865c2960230988e32b45

            return ($name !== '' && str_contains($lower, $name))
                || (mb_strlen($alternative) >= 4 && str_contains($lower, $alternative));
        });

        if ($matched->count() < 8) {
            $words = collect(preg_split('/[^\p{L}\p{N}]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [])
                ->filter(fn (string $word): bool => mb_strlen($word) >= 4)
                ->reject(fn (string $word): bool => in_array($word, self::STOPWORDS, true))
                ->take(4);

            foreach ($words as $word) {
                $matched = $matched->merge($repository->search(['q' => $word, 'sort' => 'rating'])->take(3));
            }
        }

        foreach (self::TYPE_HINTS as $hint => $type) {
            if (str_contains($lower, $hint)) {
                $matched = $matched->merge($repository->search(['jenis' => $type, 'sort' => 'rating'])->take(2));
            }
        }

<<<<<<< HEAD
        $attributeTerms = $this->extractAttributeTerms($lower, $repository);
        if ($attributeTerms !== []) {
            $matched = $matched->merge(
                $destinations
                    ->filter(fn (Destination $destination): bool => $this->matchesAttributeTerms($destination, $attributeTerms))
                    ->take(5),
            );
        }

=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        return $matched->unique('id')->take(10)->values();
    }

    private function describe(Destination $destination): string
    {
<<<<<<< HEAD
        $facilities = ($destination->facilities ?? []) !== []
            ? implode(', ', $destination->facilities ?? [])
            : 'tidak tercatat';
        $activities = ($destination->activities ?? []) !== []
            ? implode(', ', $destination->activities ?? [])
            : 'tidak tercatat';

        return sprintf(
            '- %s | kategori: %s | lokasi: %s | rating: %s | tiket: %s | fasilitas: %s | aktivitas: %s | %s',
            $destination->name ?? 'Destinasi',
            ($destination->types ?? []) === [] ? 'tidak tercatat' : implode(', ', $destination->types ?? []),
            ($destination->location ?? '') !== '' ? $destination->location : $destination->province(),
            $destination->ratingLabel(),
            $destination->priceLabel(),
            $facilities,
            $activities,
            Str::limit(
                ($destination->shortDescription ?? '') !== '' ? $destination->shortDescription : 'deskripsi belum tercatat',
                180,
            ),
=======
        return sprintf(
            '- %s | kategori: %s | lokasi: %s | rating: %s | tiket: %s | %s',
            $destination->name,
            $destination->types === [] ? 'tidak tercatat' : implode(', ', $destination->types),
            $destination->location !== '' ? $destination->location : $destination->province(),
            $destination->ratingLabel(),
            $destination->priceLabel(),
            Str::limit($destination->shortDescription !== '' ? $destination->shortDescription : 'deskripsi belum tercatat', 180),
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        );
    }
}
