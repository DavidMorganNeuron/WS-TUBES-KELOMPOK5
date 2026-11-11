<?php

namespace App\Http\Controllers;

use App\Data\Destination;
use App\Services\Ontology\DestinationRepository;
use Illuminate\Http\Client\ConnectionException;
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

    public function chat(Request $request, DestinationRepository $repository): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:800'],
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
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            return response()->json([
                'reply' => 'WayFinder AI sedang sibuk. Silakan coba lagi sebentar lagi.',
            ], 502);
        }

        $reply = collect($response->json('candidates.0.content.parts', []))
            ->pluck('text')
            ->filter(fn (mixed $text): bool => is_string($text) && trim($text) !== '')
            ->implode("\n");

        if ($reply === '') {
            return response()->json([
                'reply' => 'Maaf, WayFinder AI belum dapat menyusun jawaban. Coba ajukan pertanyaan lain.',
            ]);
        }

        return response()->json(['reply' => $reply]);
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
        $contents = collect($history)
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
{$this->context($message, $repository)}
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
            'Jumlah per kategori: '.collect($facets['types'])
                ->map(fn (int $count, string $type): string => $type.'='.$count)
                ->implode(', '),
            'Kabupaten/kota tercatat: '.count($facets['kabupaten']),
            'Rata-rata rating: '.($facets['average_rating'] ?? 'tidak tersedia')
                .' dari '.$facets['rated_count'].' destinasi berating.',
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
            $name = Str::lower($destination->name);
            $alternative = Str::lower($destination->alternativeName);

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

        return $matched->unique('id')->take(10)->values();
    }

    private function describe(Destination $destination): string
    {
        return sprintf(
            '- %s | kategori: %s | lokasi: %s | rating: %s | tiket: %s | %s',
            $destination->name,
            $destination->types === [] ? 'tidak tercatat' : implode(', ', $destination->types),
            $destination->location !== '' ? $destination->location : $destination->province(),
            $destination->ratingLabel(),
            $destination->priceLabel(),
            Str::limit($destination->shortDescription !== '' ? $destination->shortDescription : 'deskripsi belum tercatat', 180),
        );
    }
}
