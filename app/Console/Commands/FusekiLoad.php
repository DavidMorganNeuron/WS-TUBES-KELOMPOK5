<?php

namespace App\Console\Commands;

use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\FusekiClient;
use App\Services\Ontology\OntologyInspector;
use App\Services\Ontology\TurtleLinter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FusekiLoad extends Command
{
    protected $signature = 'fuseki:load
        {--file= : Lokasi file Turtle yang akan diunggah}
        {--fresh : Kosongkan SELURUH graph (default dan bernama) sebelum mengunggah}
        {--force : Lewati konfirmasi untuk --fresh}
        {--skip-lint : Unggah walau file Turtle melanggar aturan kardinalitas}';

    protected $description = 'Unggah file Turtle ke dataset Fuseki melalui Graph Store Protocol';

    public function handle(
        FusekiClient $client,
        DestinationRepository $repository,
        OntologyInspector $inspector,
        TurtleLinter $linter,
    ): int
    {
        $path = $this->option('file') ?: config('ontology.ttl_path');
        $resolvedPath = $this->resolvePath((string) $path);

        if (! is_file($resolvedPath)) {
            $this->error("File Turtle tidak ditemukan: {$resolvedPath}");

            return self::FAILURE;
        }

        $this->line('File   : '.$resolvedPath);
        $this->line('SHA256 : '.hash_file('sha256', $resolvedPath));

        if (! $this->passesLint($linter, $resolvedPath)) {
            return self::FAILURE;
        }

        if (! $client->isAvailable()) {
            $this->error('Fuseki tidak dapat diakses. Jalankan Fuseki terlebih dahulu dan periksa FUSEKI_URL.');

            return self::FAILURE;
        }

        if ($this->option('fresh') && ! $this->option('force')
            && ! $this->confirm('Semua graph di dataset '.$client->datasetUrl().' akan dikosongkan. Lanjutkan?', false)) {
            $this->warn('Dibatalkan.');

            return self::FAILURE;
        }

        try {
            if ($this->option('fresh')) {
                $client->clearAll();
            }

            $client->upload((string) file_get_contents($resolvedPath));
        } catch (\Throwable $exception) {
            $this->error('Gagal mengunggah data: '.$exception->getMessage());

            return self::FAILURE;
        }

        $repository->forgetCache();
        Cache::forget('ontology.destinations');

        $this->info('Dataset berhasil diunggah ke '.$client->datasetUrl());

        return $this->verify($inspector);
    }

    private function passesLint(TurtleLinter $linter, string $path): bool
    {
        try {
            $result = $linter->lint($path);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return false;
        }

        if ($result['violations'] === []) {
            $this->info("Lint Turtle: OK ({$result['blocks']} destinasi).");

            return true;
        }

        $this->error('Lint Turtle: file melanggar aturan data. Perbaiki baris berikut:');
        $this->table(['Subjek', 'Properti', 'Baris', 'Nilai'], array_map(
            static fn (array $v): array => [$v['subject'], $v['property'], implode(', ', $v['lines']), implode(' | ', $v['values'])],
            $result['violations'],
        ));

        if ($this->option('skip-lint')) {
            $this->warn('--skip-lint aktif: unggah tetap dilanjutkan.');

            return true;
        }

        $this->line('Unggah dibatalkan. Perbaiki file, atau gunakan --skip-lint bila disengaja.');

        return false;
    }

    private function verify(OntologyInspector $inspector): int
    {
        try {
            $issues = count($inspector->duplicateIds()) + count($inspector->cardinalityViolations()) + count($inspector->graphs());
        } catch (\Throwable $exception) {
            $this->warn('Verifikasi pasca-unggah dilewati: '.$exception->getMessage());

            return self::SUCCESS;
        }

        if ($issues > 0) {
            $this->warn("Verifikasi menemukan {$issues} masalah. Jalankan: php artisan ontology:doctor");

            return self::FAILURE;
        }

        $this->info('Verifikasi: data konsisten.');

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}