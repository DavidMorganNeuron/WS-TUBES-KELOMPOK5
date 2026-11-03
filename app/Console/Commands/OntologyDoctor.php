<?php

namespace App\Console\Commands;

use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\FusekiClient;
use App\Services\Ontology\OntologyInspector;
use App\Services\Ontology\TurtleLinter;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class OntologyDoctor extends Command
{
    protected $signature = 'ontology:doctor
        {id=ws-272 : ID destinasi yang diperiksa}
        {--file= : File Turtle yang diperiksa (default: ONTOLOGY_TTL_PATH)}
        {--property=hargaMasuk : Properti yang dibandingkan antara Fuseki dan aplikasi}
        {--forget-cache : Hapus cache ontologi sebelum membandingkan}';

    protected $description = 'Mendiagnosis ketidaksesuaian data antara file Turtle, Fuseki, dan tampilan aplikasi';

    public function handle(
        FusekiClient $client,
        OntologyInspector $inspector,
        DestinationRepository $repository,
        TurtleLinter $linter,
    ): int {
        $this->components->info('Konfigurasi aktif');
        $this->line('  driver   : '.config('ontology.driver'));
        $this->line('  ttl_path : '.config('ontology.ttl_path'));
        $this->line('  fuseki   : '.$client->datasetUrl());

        $fileIssues = $this->lintFile($linter);

        if (! $client->isAvailable()) {
            $this->components->error('Fuseki tidak dapat diakses. Periksa FUSEKI_URL dan pastikan server berjalan.');

            return self::FAILURE;
        }

        if ($this->option('forget-cache')) {
            $repository->forgetCache();
            $this->line('  cache ontologi dihapus.');
        }

        try {
            return $this->diagnose($inspector, $repository, $fileIssues);
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());
        } catch (Throwable $exception) {
            $this->components->error('Pemeriksaan gagal: '.$exception->getMessage());
        }

        return self::FAILURE;
    }

    /**
     * @return int jumlah pelanggaran pada file Turtle
     */
    private function lintFile(TurtleLinter $linter): int
    {
        $path = (string) ($this->option('file') ?: config('ontology.ttl_path'));
        $resolved = is_file($path) ? $path : base_path($path);

        $this->components->info('File Turtle');
        $this->line('  path   : '.$resolved);

        try {
            $result = $linter->lint($resolved);
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return 1;
        }

        $this->line('  sha256 : '.hash_file('sha256', $resolved));
        $this->line("  blok   : {$result['blocks']} destinasi");

        if ($result['violations'] === []) {
            $this->line('  lint   : OK');

            return 0;
        }

        $this->components->error('Lint Turtle menemukan pelanggaran');
        $this->table(['Subjek', 'Properti', 'Baris', 'Nilai'], array_map(
            static fn (array $v): array => [$v['subject'], $v['property'], implode(', ', $v['lines']), implode(' | ', $v['values'])],
            $result['violations'],
        ));

        return count($result['violations']);
    }

    private function diagnose(OntologyInspector $inspector, DestinationRepository $repository, int $fileIssues): int
    {
        $id = (string) $this->argument('id');
        $property = (string) $this->option('property');
        $problems = $fileIssues;

        $graphs = $inspector->graphs();
        $this->components->info('Graph bernama di Fuseki');

        if ($graphs === []) {
            $this->line('  tidak ada (hanya graph default).');
        } else {
            $problems++;
            $this->table(['Graph', 'Jumlah triple'], array_map(
                static fn (array $row): array => [$row['g'] ?? '', $row['triples'] ?? ''],
                $graphs,
            ));
            $this->components->warn('Graph bernama tidak ikut terhapus oleh PUT. Jalankan: php artisan fuseki:load --fresh');
        }

        $triples = $inspector->valuesOf($id, $property);
        $this->components->info("Triple ws:{$property} untuk {$id} di Fuseki");
        $this->table(['Graph', 'Subjek', 'Nilai'], array_map(
            static fn (array $row): array => array_values($row),
            $triples,
        ));

        $seen = $repository->find($id);
        $this->components->info('Yang dilihat aplikasi');
        $this->line('  prices : '.($seen === null ? '(destinasi tidak ditemukan)' : json_encode($seen->prices)));

        $duplicates = $inspector->duplicateIds();
        $violations = $inspector->cardinalityViolations();
        $problems += count($duplicates) + count($violations);

        $this->reportRows('ID dipakai lebih dari satu subjek', ['ID', 'Jumlah', 'Subjek'], $duplicates, ['id', 'jumlah', 'subjek']);
        $this->reportRows('Properti bernilai tunggal yang punya banyak nilai', ['ID', 'Properti', 'Jumlah', 'Nilai'], $violations, ['id', 'p', 'jumlah', 'nilai']);

        if ($problems === 0) {
            $this->components->info('Data konsisten.');

            return self::SUCCESS;
        }

        if ($fileIssues === 0 && ($duplicates !== [] || $violations !== [])) {
            $this->components->warn('File Turtle yang diperiksa bersih, tetapi Fuseki berisi pelanggaran: data di Fuseki berasal dari file atau unggahan lain. Muat ulang file ini dengan --fresh.');
        }

        $this->components->warn("Ditemukan {$problems} masalah. Perbaiki sumber data, lalu muat ulang dengan --fresh.");

        return self::FAILURE;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, string|null>>  $rows
     * @param  list<string>  $keys
     */
    private function reportRows(string $title, array $headers, array $rows, array $keys): void
    {
        if ($rows === []) {
            return;
        }

        $this->components->error($title);
        $this->table($headers, array_map(
            static fn (array $row): array => array_map(static fn (string $key): string => (string) ($row[$key] ?? ''), $keys),
            $rows,
        ));
    }
}