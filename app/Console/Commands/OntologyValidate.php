<?php

namespace App\Console\Commands;

use App\Data\Destination;
use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\TurtleParser;
use Illuminate\Console\Command;

class OntologyValidate extends Command
{
    protected $signature = 'ontology:validate {--file= : Lokasi file Turtle yang akan divalidasi}';

    protected $description = 'Validasi data destinasi pada file Turtle terhadap aturan ontologi';

    /**
     * @var list<string>
     */
    private array $errors = [];

    public function handle(TurtleParser $parser): int
    {
        $path = (string) ($this->option('file') ?: config('ontology.ttl_path'));
        $resolvedPath = $this->resolvePath($path);

        if (! is_file($resolvedPath)) {
            $this->error("File Turtle tidak ditemukan: {$resolvedPath}");

            return self::FAILURE;
        }

        try {
            $rows = $parser->parseFile($resolvedPath);
        } catch (\Throwable $exception) {
            $this->error('Gagal membaca file Turtle: '.$exception->getMessage());

            return self::FAILURE;
        }

        $destinations = collect($rows)
            ->map(fn (array $row): Destination => Destination::fromArray($row))
            ->filter(fn (Destination $destination): bool => $destination->name !== '')
            ->values();

        foreach ($rows as $row) {
            $this->validateRow($row);
        }

        $ratings = $destinations->pluck('rating')->filter(fn (mixed $rating): bool => $rating !== null);

        $this->table(['Statistik', 'Nilai'], [
            ['File', $resolvedPath],
            ['Total destinasi', $destinations->count()],
            ['Memiliki koordinat', $destinations->filter(fn (Destination $d): bool => $d->hasCoordinates())->count()],
            ['Memiliki media', $destinations->filter(fn (Destination $d): bool => $d->media !== '')->count()],
            ['Rating rata-rata', $ratings->isEmpty() ? '—' : number_format((float) $ratings->avg(), 2)],
            ['Destinasi Aceh', $destinations->filter(fn (Destination $d): bool => $d->province() === 'Aceh')->count()],
            ['Destinasi Sumatera Utara', $destinations->filter(fn (Destination $d): bool => $d->province() === 'Sumatera Utara')->count()],
        ]);

        if ($this->errors !== []) {
            $this->newLine();
            $this->error(count($this->errors).' masalah ditemukan:');

            foreach ($this->errors as $error) {
                $this->line(' - '.$error);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Validasi selesai. Tidak ada masalah yang ditemukan.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function validateRow(array $row): void
    {
        $id = $row['idDestinasi'] ?? '(tanpa ID)';
        $types = array_values(array_filter(array_map(
            fn (string $value): string => $this->cleanValue($value),
            explode('|', $row['jenisDestinasi'] ?? ''),
        )));

        if (preg_match('/^DST-\d{4}$/', $id) !== 1) {
            $this->errors[] = "{$id}: format ID harus DST-0000.";
        }

        if (($row['namaDestinasi'] ?? '') === '') {
            $this->errors[] = "{$id}: namaDestinasi wajib diisi.";
        }

        if ($types === []) {
            $this->errors[] = "{$id}: jenisDestinasi wajib diisi.";
        }

        foreach ($types as $type) {
            if (! in_array($type, DestinationRepository::TYPES, true)) {
                $this->errors[] = "{$id}: jenisDestinasi tidak dikenal ({$type}).";
            }
        }

        if (($row['beradaDi'] ?? '') === '') {
            $this->errors[] = "{$id}: beradaDi wajib diisi.";
        }

        $this->validateCoordinate($id, $row['lintang'] ?? '', -90, 90, 'lintang');
        $this->validateCoordinate($id, $row['bujur'] ?? '', -180, 180, 'bujur');

        $rating = $this->cleanValue($row['ratingPengunjung'] ?? '');

        if ($rating !== '' && (! is_numeric($rating) || (float) $rating < 0 || (float) $rating > 5)) {
            $this->errors[] = "{$id}: ratingPengunjung harus berupa angka 0 sampai 5.";
        }

        $media = array_values(array_filter(array_map(
            fn (string $value): string => $this->cleanValue($value),
            explode('|', $row['memilikiMedia'] ?? ''),
        )));

        if (count($media) > 1) {
            $this->errors[] = "{$id}: memilikiMedia bersifat functional, hanya boleh satu nilai.";
        }

        foreach ($media as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                $this->errors[] = "{$id}: memilikiMedia bukan URL yang valid ({$url}).";
            }
        }

        $instagram = $this->cleanValue($row['instagramResmi'] ?? '');

        if ($instagram !== '' && filter_var($instagram, FILTER_VALIDATE_URL) === false) {
            $this->errors[] = "{$id}: instagramResmi bukan URL yang valid ({$instagram}).";
        }

        $email = $this->cleanValue($row['emailResmi'] ?? '');

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[] = "{$id}: emailResmi bukan alamat email yang valid ({$email}).";
        }
    }

    private function cleanValue(string $value): string
    {
        $value = trim($value);

        return in_array($value, ['-', '(kosong)'], true) ? '' : $value;
    }

    private function validateCoordinate(string $id, string $value, float $minimum, float $maximum, string $label): void
    {
        $value = $this->cleanValue($value);

        if ($value === '') {
            return;
        }

        if (! is_numeric($value) || (float) $value < $minimum || (float) $value > $maximum) {
            $this->errors[] = "{$id}: {$label} harus berupa angka antara {$minimum} dan {$maximum}.";
        }
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
