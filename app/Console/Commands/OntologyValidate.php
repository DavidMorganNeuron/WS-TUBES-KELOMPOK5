<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ontology\OntologyValidator;
use App\Services\Ontology\TurtleParser;
use App\Services\Ontology\WikidataDestinationLinkCatalog;
use App\Services\Ontology\WikidataRegionCatalog;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class OntologyValidate extends Command
{
    protected $signature = 'ontology:validate {--file= : File Turtle v3 yang divalidasi}';

    protected $description = 'Validasi skema dan data ontologi v3';

    public function handle(
        TurtleParser $parser,
        OntologyValidator $validator,
        WikidataDestinationLinkCatalog $destinationLinks,
        WikidataRegionCatalog $regionCatalog,
    ): int {
        $path = (string) ($this->option('file') ?: config('ontology.ttl_path'));
        $resolvedPath = $this->resolvePath($path);

        if (! is_file($resolvedPath)) {
            $this->error("File Turtle tidak ditemukan: {$resolvedPath}");

            return self::FAILURE;
        }

        $contents = file_get_contents($resolvedPath);
        if ($contents === false) {
            $this->error("File Turtle tidak dapat dibaca: {$resolvedPath}");

            return self::FAILURE;
        }

        $sourcePath = $this->resolvePath((string) config('ontology.v2_source_path'));
        $sourceIds = null;
        if (is_file($sourcePath)) {
            try {
                $sourceIds = array_column($parser->parseFile($sourcePath), 'idDestinasi');
            } catch (InvalidArgumentException $exception) {
                $this->error('Sumber Turtle v2 tidak dapat dibaca untuk perbandingan: '.$exception->getMessage());

                return self::FAILURE;
            }
        } else {
            $this->warn("Sumber Turtle v2 tidak ditemukan; pemeriksaan jumlah dan ID dilewati: {$sourcePath}");
        }

        try {
            $result = $validator->validate(
                $parser->parseTriples($contents),
                (string) config('ontology.unknown_label'),
                $sourceIds,
                $regionCatalog->qidsByRegionName(),
                $destinationLinks->load()['destinations_by_id'],
            );
        } catch (InvalidArgumentException $exception) {
            $this->error('Turtle tidak dapat divalidasi: '.$exception->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($result['class_counts'] as $class => $count) {
            $rows[] = ['Class', $class, $count];
        }
        foreach ($result['object_property_counts'] as $property => $count) {
            $rows[] = ['Object property', $property, $count];
        }
        foreach ($result['data_property_counts'] as $property => $count) {
            $rows[] = ['Data property', $property, $count];
        }

        $this->table(['Jenis', 'Nama', 'Jumlah instance/pemakaian'], $rows);
        $this->line("Total destinasi: {$result['destination_count']}");
        $this->line("sameAs wilayah: {$result['same_as_counts']['regions']}");
        $this->line("sameAs destinasi: {$result['same_as_counts']['destinations']}");
        if ($result['source_comparison'] !== null) {
            $this->line("Total destinasi sumber v2: {$result['source_comparison']['destination_count']}");
        }
        $this->newLine();
        $this->table(['Properti', 'Jumlah sentinel "'.config('ontology.unknown_label').'"'], array_map(
            static fn (string $property, int $count): array => [$property, $count],
            array_keys($result['sentinel_counts']),
            array_values($result['sentinel_counts']),
        ));
        $this->newLine();
        $this->table(['Statistik rating', 'Nilai'], [
            ['Destinasi berating', $result['rating_stats']['rated']],
            ['Destinasi tanpa rating', $result['rating_stats']['unrated']],
            ['Rata-rata rating', $result['rating_stats']['average'] === null
                ? (string) config('ontology.unknown_label')
                : number_format($result['rating_stats']['average'], 2)],
        ]);

        if ($result['errors'] !== []) {
            $this->newLine();
            $this->error(count($result['errors']).' masalah ditemukan:');
            foreach ($result['errors'] as $error) {
                $this->line(' - '.$error);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Validasi ontologi v3 lulus.');

        return self::SUCCESS;
    }

<<<<<<< HEAD
=======
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

        if (preg_match('/^ws-\d{3}$/', $id) !== 1) {
            $this->errors[] = "{$id}: format ID harus ws-000.";
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

>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
