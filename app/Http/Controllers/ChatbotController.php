<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http; // <-- PENTING: Tambahkan ini untuk membaca file
use Throwable;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        try {
            $apiKey = env('GEMINI_API_KEY');
            $userMessage = $request->input('message');

            if (empty($apiKey)) {
                return response()->json([
                    'reply' => 'Error: GEMINI_API_KEY belum diisi di file .env!',
                ]);
            }

            // --- TAMBAHAN BARU: Membaca file JSON dari root folder proyek ---
            $jsonPath = base_path('wisata_sumut_v2.json');
            $dataWisata = '';

            // Cek apakah file JSON ada, lalu baca isinya
            if (File::exists($jsonPath)) {
                $dataWisata = File::get($jsonPath);
            }
            // ----------------------------------------------------------------

            // --- UPDATE: Menggabungkan Persona kalian dengan Data JSON ---
            $instruksiSistem = 'Anda adalah WayFinder AI, pemandu wisata digital resmi untuk destinasi Sumatera Utara. Berikan rekomendasi secara lengkap, ramah, dan informatif. PENTING: Jangan pernah memberikan salam pembuka (seperti "Halo", "Selamat datang", dll) di awal jawaban Anda. Langsung jawab intinya saja dan lanjutkan obrolan secara natural. Selalu selesaikan jawaban hingga tuntas.
            
            Gunakan data destinasi wisata (JSON) berikut sebagai sumber utama untuk menjawab pertanyaan pengguna:
            
            '.$dataWisata;
            // -------------------------------------------------------------

            $response = Http::withoutVerifying()
                ->timeout(60)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key={$apiKey}", [
                    'system_instruction' => [
                        'parts' => [
                            ['text' => $instruksiSistem], // Memasukkan instruksi yang sudah digabung JSON
                        ],
                    ],
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $userMessage],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 700,
                        'temperature' => 0.2,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, tidak ada respon dari AI.';

                return response()->json(['reply' => $reply]);
            }

            return response()->json([
                'reply' => 'Google API Error ('.$response->status().'): '.$response->body(),
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'reply' => 'Kesalahan Koneksi Backend: '.$e->getMessage(),
            ]);
        }
    }
}
