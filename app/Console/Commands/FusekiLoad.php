<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\FusekiBackup;
use App\Services\Ontology\FusekiClient;
use App\Services\Ontology\OntologyDataVersion;
use App\Services\Ontology\OntologyValidator;
use App\Services\Ontology\TurtleParser;
use Illuminate\Console\Command;
use Throwable;

final class FusekiLoad extends Command
{
    protected $signature = 'fuseki:load
        {--file= : File Turtle ontologi v3 yang akan dimuat}
        {--dry-run : Validasi dan tampilkan rencana tanpa mengubah Fuseki}
        {--fresh : Ganti seluruh graph pada dataset Fuseki}
        {--force : Lewati konfirmasi untuk --fresh}';

    protected $description = 'Validasi, backup, dan muat ontologi v3 ke dataset Fuseki';

    public function handle(
        FusekiClient $client,
        FusekiBackup $backup,
        DestinationRepository $repository,
        OntologyDataVersion $dataVersion,
        TurtleParser $parser,
        OntologyValidator $validator,
    ): int {
        $file = (string) ($this->option('file') ?: config('ontology.ttl_path'));
        $path = $this->resolvePath($file);
        if (! is_file($path)) {
            $this->error("File Turtle tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $turtle = file_get_contents($path);
        if ($turtle === false) {
            $this->error("File Turtle tidak dapat dibaca: {$path}");

            return self::FAILURE;
        }

        try {
            $triples = $parser->parseTriples($turtle);
            $validation = $validator->validate($triples, (string) config('ontology.unknown_label'));
        } catch (Throwable $exception) {
            $this->error('Validasi ontologi gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($validation['errors'] !== []) {
            $this->error('File ontologi v3 tidak lolos validasi:');
            foreach ($validation['errors'] as $error) {
                $this->line(' - '.$error);
            }

            return self::FAILURE;
        }

        $hash = hash('sha256', $turtle);
        $this->table(['Informasi', 'Nilai'], [
            ['File', $path],
            ['Dataset', $client->datasetUrl()],
            ['SHA256', $hash],
            ['Destinasi', $validation['destination_count']],
            ['Triple', count($triples)],
            ['Mode', $this->option('fresh') ? 'ganti seluruh graph' : 'ganti graph default'],
        ]);

        if ($this->option('dry-run')) {
            $this->info('Dry-run lulus. Tidak ada koneksi tulis, backup, atau perubahan Fuseki.');

            return self::SUCCESS;
        }

        if (! $client->isAvailable()) {
            $this->error('Fuseki tidak dapat diakses. Periksa server dan FUSEKI_URL.');

            return self::FAILURE;
        }

        try {
            if (! $client->datasetExists()) {
                $this->error('Dataset Fuseki tidak ditemukan. Buat dataset melalui Fuseki Admin UI, lalu ulangi perintah.');

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            $this->error('Pemeriksaan dataset Fuseki gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('fresh') && ! $this->option('force')
            && ! $this->confirm(
                'Semua graph di dataset '.$client->datasetUrl().' akan diganti. Lanjutkan?',
                false,
            )) {
            $this->warn('Dibatalkan; Fuseki tidak diubah.');

            return self::FAILURE;
        }

        try {
            $backupPath = $backup->create($client);
            $this->info("Backup dataset tersimpan: {$backupPath}");

            if ($this->option('fresh')) {
                $client->replaceDataset($turtle, clearNamedGraphs: true);
            } else {
                $client->upload($turtle);
            }

            $dataVersion->refresh($hash);
            $repository->forgetCache();
            $loadedCount = $repository->all()->count();
            if ($loadedCount !== $validation['destination_count']) {
                $this->error(
                    "Verifikasi jumlah destinasi gagal: file berisi {$validation['destination_count']}, Fuseki terbaca {$loadedCount}.",
                );

                return self::FAILURE;
            }

            $tripleCountRows = $client->select('SELECT (COUNT(*) AS ?count) WHERE { ?s ?p ?o }');
            $loadedTripleCount = (int) ($tripleCountRows[0]['count'] ?? -1);
            if ($loadedTripleCount !== count($triples)) {
                $this->error(
                    'Verifikasi jumlah triple gagal: file berisi '.count($triples).", Fuseki terbaca {$loadedTripleCount}. Backup dipertahankan.",
                );

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            $this->error('Pemuatan atau verifikasi Fuseki gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Dataset berhasil dimuat dan diverifikasi ({$loadedCount} destinasi, {$loadedTripleCount} triple).");

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
