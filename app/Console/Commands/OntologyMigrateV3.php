<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ontology\OntologyMigrator;
use App\Services\Ontology\OntologyValidator;
use App\Services\Ontology\TurtleParser;
use App\Services\Ontology\TurtleWriter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

#[Signature('ontology:migrate-v3 {--source=} {--output=} {--dry-run}')]
#[Description('Migrasikan sumber Turtle v2 menjadi ontologi v3')]
final class OntologyMigrateV3 extends Command
{
    public function handle(
        TurtleParser $parser,
        OntologyMigrator $migrator,
        TurtleWriter $writer,
        OntologyValidator $validator,
    ): int {
        $source = (string) ($this->option('source') ?: config('ontology.v2_source_path'));
        $output = (string) ($this->option('output') ?: config('ontology.ttl_path'));
        $sourcePath = $this->resolvePath($source);
        $outputPath = $this->resolvePath($output);
        $boundaryPath = $this->resolvePath((string) config('ontology.wikidata_regions_path'));

        if (! is_file($sourcePath)) {
            $this->error("File sumber Turtle tidak ditemukan: {$sourcePath}");

            return self::FAILURE;
        }

        if (! is_file($boundaryPath)) {
            $this->error("Snapshot batas wilayah Wikidata tidak ditemukan: {$boundaryPath}");

            return self::FAILURE;
        }

        $boundaryJson = file_get_contents($boundaryPath);
        if ($boundaryJson === false) {
            $this->error("Snapshot batas wilayah tidak dapat dibaca: {$boundaryPath}");

            return self::FAILURE;
        }

        try {
            $boundarySource = json_decode($boundaryJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('JSON snapshot batas wilayah tidak valid: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! is_array($boundarySource)) {
            $this->error('JSON snapshot batas wilayah harus berupa objek.');

            return self::FAILURE;
        }

        try {
            $rows = $parser->parseFile($sourcePath);
            $result = $migrator->migrate(
                rows: $rows,
                boundarySource: $boundarySource,
                unknownLabel: (string) config('ontology.unknown_label'),
                nearbyRadiusKm: (float) config('ontology.nearby.radius_km'),
                maxNeighbors: (int) config('ontology.nearby.max_neighbors'),
            );
            $turtle = $writer->write($result['triples']);
            $validation = $validator->validate(
                $parser->parseTriples($turtle),
                (string) config('ontology.unknown_label'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error('Migrasi dibatalkan: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Ringkasan migrasi', 'Jumlah'], [
            ['Destinasi sumber', $result['stats']['destinations']],
            ['Wilayah terverifikasi', $result['stats']['districts']],
            ['Triple keluaran', count($result['triples'])],
            ['Class berinstance', count(array_filter($validation['class_counts']))],
            ['Object property terpakai', count(array_filter($validation['object_property_counts']))],
            ['Data property terpakai', count(array_filter($validation['data_property_counts']))],
            ['Masalah validasi', count($validation['errors'])],
        ]);

        if ($this->option('dry-run')) {
            $this->warn('Dry-run aktif: sumber dan berkas keluaran tidak diubah.');
        } else {
            $outputDirectory = dirname($outputPath);
            if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0755, true) && ! is_dir($outputDirectory)) {
                $this->error("Direktori keluaran tidak dapat dibuat: {$outputDirectory}");

                return self::FAILURE;
            }

            if (file_put_contents($outputPath, $turtle, LOCK_EX) === false) {
                $this->error("Berkas ontologi tidak dapat ditulis: {$outputPath}");

                return self::FAILURE;
            }

            $reportPath = base_path('docs/laporan-anomali-data.md');
            $reportDirectory = dirname($reportPath);
            if (! is_dir($reportDirectory) && ! mkdir($reportDirectory, 0755, true) && ! is_dir($reportDirectory)) {
                $this->error("Direktori laporan tidak dapat dibuat: {$reportDirectory}");

                return self::FAILURE;
            }

            if (file_put_contents($reportPath, $this->renderAnomalyReport($sourcePath, $boundarySource, $result['anomalies']), LOCK_EX) === false) {
                $this->error("Laporan anomali tidak dapat ditulis: {$reportPath}");

                return self::FAILURE;
            }

            $this->info("Ontologi v3 ditulis: {$outputPath}");
            $this->info("Laporan anomali ditulis: {$reportPath}");
        }

        if ($validation['errors'] !== []) {
            $this->newLine();
            $this->error('Validasi ontologi v3 gagal:');
            foreach ($validation['errors'] as $error) {
                $this->line(' - '.$error);
            }

            return self::FAILURE;
        }

        $this->info('Validasi ontologi v3 lulus.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $boundarySource
     * @param  array<string, mixed>  $anomalies
     */
    private function renderAnomalyReport(string $sourcePath, array $boundarySource, array $anomalies): string
    {
        $lines = [
            '# Laporan anomali data wisata Sumatera Utara',
            '',
            '- Sumber: `'.str_replace(base_path().DIRECTORY_SEPARATOR, '', $sourcePath).'` (dibaca saja; tidak diubah).',
            '- Relasi batas wilayah: '.$boundarySource['source'].'; diambil '.$boundarySource['retrieved_at'].'; wilayah diverifikasi sebagai Kabupaten/Kota Indonesia di Sumatera Utara.',
            '- Perbaikan otomatis dibatasi pada trim, deduplikasi, tipe literal, pemecahan kata kunci, dan aturan sentinel.',
            '- Anomali semantik di bawah tidak diperbaiki otomatis.',
            '',
        ];

        $this->appendRecords($lines, '## Kabupaten/kota pada `beradaDi` tidak ditemukan pada alamat', $anomalies['address_region_mismatches'] ?? [], static fn (array $row): string => "{$row['id']} — {$row['name']}: {$row['region']}; alamat: {$row['address']}");
        $this->appendRecords($lines, '## Koordinat di luar batas Sumatera Utara (lintang 0.5–4.5, bujur 97.0–100.5)', $anomalies['coordinates_outside_bounds'] ?? [], static fn (array $row): string => "{$row['id']} — {$row['name']}: {$row['latitude']}, {$row['longitude']}");
        $this->appendGroupedRecords($lines, '## Nama destinasi duplikat', $anomalies['duplicate_names'] ?? [], static fn (array $row): string => "{$row['id']} — {$row['name']}");
        $this->appendRecords($lines, '## Harga masuk negatif', $anomalies['negative_prices'] ?? [], static fn (array $row): string => "{$row['id']} — {$row['name']}: {$row['price']}");
        $this->appendRecords($lines, '## Rating numerik di luar rentang 0–5', $anomalies['ratings_outside_range'] ?? [], static fn (array $row): string => "{$row['id']} — {$row['name']}: {$row['rating']}");
        $this->appendRecords($lines, '## Jam operasional yang tidak dapat diparse dengan pasti', $anomalies['unparsed_hours'] ?? [], static fn (array $row): string => "{$row['id']} — {$row['name']}: {$row['hours']}");

        $lines[] = '## URL gambar dari host tidak stabil';
        foreach (($anomalies['unstable_media'] ?? []) as $host => $records) {
            $lines[] = "- `{$host}`: ".count($records).' URL.';
            foreach ($records as $record) {
                $lines[] = "  - {$record['id']} — {$record['url']}";
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<string>  $lines
     * @param  list<array<string, string>>  $records
     */
    private function appendRecords(array &$lines, string $heading, array $records, callable $formatter): void
    {
        $lines[] = $heading;
        if ($records === []) {
            $lines[] = '- Tidak ditemukan.';
        } else {
            foreach ($records as $record) {
                $lines[] = '- '.$formatter($record);
            }
        }
        $lines[] = '';
    }

    /**
     * @param  list<string>  $lines
     * @param  list<list<array<string, string>>>  $groups
     */
    private function appendGroupedRecords(array &$lines, string $heading, array $groups, callable $formatter): void
    {
        $lines[] = $heading;
        if ($groups === []) {
            $lines[] = '- Tidak ditemukan.';
        } else {
            foreach ($groups as $group) {
                foreach ($group as $record) {
                    $lines[] = '- '.$formatter($record);
                }
            }
        }
        $lines[] = '';
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
