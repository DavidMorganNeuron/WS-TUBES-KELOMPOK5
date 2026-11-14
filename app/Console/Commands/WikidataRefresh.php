<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ontology\TurtleParser;
use App\Services\Ontology\WikidataClient;
use App\Services\Ontology\WikidataQueryBuilder;
use App\Services\Ontology\WikidataRegionCatalog;
use App\Services\Ontology\WikidataRegionRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

#[Signature('wikidata:refresh {--allow-stale : Tampilkan snapshot tersimpan jika pembaruan live gagal} {--candidates : Buat laporan kandidat tautan destinasi} {--candidates-only : Buat laporan tanpa memperbarui data wilayah}')]
#[Description('Perbarui snapshot wilayah dari Wikidata dan validasi data live')]
final class WikidataRefresh extends Command
{
    public function handle(
        WikidataRegionRepository $regions,
        WikidataRegionCatalog $catalog,
        WikidataClient $client,
        WikidataQueryBuilder $queryBuilder,
        TurtleParser $parser,
    ): int {
        if (! $this->option('candidates-only')) {
            try {
                $snapshot = $regions->refresh();
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $this->error('Pembaruan Wikidata gagal: '.$exception->getMessage());
                if ($this->option('allow-stale')) {
                    $stale = $regions->loadSnapshot(allowStale: true);
                    if ($stale !== null) {
                        $this->warn('Snapshot lokal lama tetap tersedia, diambil pada '.$stale['retrieved_at'].'.');
                    } else {
                        $this->warn('Tidak ada snapshot lokal yang dapat digunakan.');
                    }
                }

                return self::FAILURE;
            }

            $this->table(['Informasi snapshot', 'Nilai'], [
                ['Wilayah terverifikasi', $snapshot['verification']['regions']],
                ['Pasangan batas', $snapshot['verification']['boundary_pairs']],
                ['Relasi batas berarah live', $snapshot['verification']['directed_boundary_edges']],
                ['Koordinat wilayah', count(array_filter(
                    $snapshot['regions'],
                    static fn (array $region): bool => $region['latitude'] !== null && $region['longitude'] !== null,
                ))],
                ['Gambar Wikimedia aman', count(array_filter(
                    $snapshot['regions'],
                    static fn (array $region): bool => $region['image_url'] !== null,
                ))],
                ['Populasi tersedia', count(array_filter(
                    $snapshot['regions'],
                    static fn (array $region): bool => $region['population'] !== null,
                ))],
                ['Luas tersedia', count(array_filter(
                    $snapshot['regions'],
                    static fn (array $region): bool => $region['area'] !== null,
                ))],
                ['Situs resmi tersedia', count(array_filter(
                    $snapshot['regions'],
                    static fn (array $region): bool => $region['website_url'] !== null,
                ))],
                ['Waktu pengambilan', $snapshot['retrieved_at']],
            ]);
            $this->info('Snapshot detail Wikidata disimpan; dataset v2 tidak diubah.');

            if (! $this->option('candidates')) {
                return self::SUCCESS;
            }
        }

        try {
            $regionCatalog = $catalog->load();
            $interval = (int) config('ontology.wikidata.request_interval_seconds', 1);
            if ($interval > 0) {
                sleep($interval);
            }
            $candidateRows = $client->select(
                $queryBuilder->nearbyEntities(array_column($regionCatalog['regions'], 'qid')),
                refresh: ! $this->option('candidates-only'),
            );
            $sourcePath = base_path((string) config('ontology.v2_source_path'));
            if (! is_file($sourcePath)) {
                throw new RuntimeException("Sumber lokal v2 tidak ditemukan: {$sourcePath}");
            }

            $sourceRows = $parser->parseFile($sourcePath);
            $reportPath = base_path((string) config('ontology.wikidata.candidate_report_path'));
            $directory = dirname($reportPath);
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException("Direktori laporan kandidat tidak dapat dibuat: {$directory}");
            }

            $report = $this->renderCandidateReport($candidateRows, $sourceRows, $regionCatalog);
            if (file_put_contents($reportPath, $report, LOCK_EX) === false) {
                throw new RuntimeException("Laporan kandidat tidak dapat ditulis: {$reportPath}");
            }
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error('Pembuatan laporan kandidat gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Laporan kandidat tautan destinasi dibuat: {$reportPath}");

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, array{type: string, value: string}>>  $candidateRows
     * @param  list<array<string, string>>  $sourceRows
     * @param  array<string, mixed>  $catalog
     */
    private function renderCandidateReport(array $candidateRows, array $sourceRows, array $catalog): string
    {
        $regionNames = [];
        foreach ($catalog['regions'] as $name => $region) {
            $regionNames[$region['qid']] = $name;
        }

        $candidatesByRegion = [];
        foreach ($candidateRows as $row) {
            $regionQid = $this->entityQid($row['region']['value'] ?? null);
            $candidateQid = $this->entityQid($row['candidate']['value'] ?? null);
            $candidateName = $row['candidateLabel']['value'] ?? null;
            $coordinate = $this->coordinate($row['location']['value'] ?? null);
            if ($regionQid === null || ! isset($regionNames[$regionQid])
                || $candidateQid === null || ! is_string($candidateName)
                || $coordinate === null) {
                continue;
            }

            $candidatesByRegion[$regionQid][$candidateQid] = [
                'qid' => $candidateQid,
                'name' => $candidateName,
                'latitude' => $coordinate['latitude'],
                'longitude' => $coordinate['longitude'],
            ];
        }

        $matches = [];
        foreach ($sourceRows as $sourceRow) {
            $destinationId = trim($sourceRow['idDestinasi'] ?? '');
            $destinationName = trim($sourceRow['namaDestinasi'] ?? '');
            $regionName = trim($sourceRow['beradaDi'] ?? '');
            $regionQid = $this->regionQid($regionName, $catalog['regions']);
            $latitude = $this->decimal($sourceRow['lintang'] ?? null);
            $longitude = $this->decimal($sourceRow['bujur'] ?? null);
            if ($destinationId === '' || $destinationName === '' || $regionQid === null
                || $latitude === null || $longitude === null) {
                continue;
            }

            $normalizedDestination = $this->normalizeName($destinationName);
            foreach ($candidatesByRegion[$regionQid] ?? [] as $candidate) {
                $distance = $this->distanceKm(
                    $latitude,
                    $longitude,
                    $candidate['latitude'],
                    $candidate['longitude'],
                );
                $similarity = 0.0;
                similar_text($normalizedDestination, $this->normalizeName($candidate['name']), $similarity);
                if ($distance > 20 || $similarity < 90) {
                    continue;
                }

                $matches[] = [
                    'destination_id' => $destinationId,
                    'destination_name' => $destinationName,
                    'region_name' => $regionNames[$regionQid],
                    'candidate_name' => $candidate['name'],
                    'candidate_qid' => $candidate['qid'],
                    'distance_km' => round($distance, 2),
                    'similarity' => round($similarity, 1),
                ];
            }
        }

        usort($matches, static fn (array $left, array $right): int => [
            $left['destination_id'],
            -$left['similarity'],
            $left['distance_km'],
        ] <=> [
            $right['destination_id'],
            -$right['similarity'],
            $right['distance_km'],
        ]);

        $lines = [
            '# Kandidat tautan destinasi Wikidata',
            '',
            '- Dibuat: '.now()->toIso8601String().'.',
            '- Metode pencarian: `wikibase:around` berbasis koordinat 30 wilayah di snapshot Wikidata.',
            '- Pencocokan nama dan jarak dilakukan lokal; nama maupun ID destinasi tidak dikirim dalam kueri.',
            '- Seluruh baris di bawah hanya kandidat untuk tinjauan manusia. Tidak ada `owl:sameAs` destinasi yang ditambahkan.',
            '- Ambang kandidat: kemiripan nama minimal 90% dan jarak maksimal 20 km. Laporan ini berisi '.count($matches).' kandidat.',
            '',
            '| ID lokal | Destinasi | Kabupaten/Kota | Kandidat Wikidata | QID | Jarak (km) | Kemiripan (%) | Keputusan (SETUJU / TOLAK) |',
            '| --- | --- | --- | --- | ---: | ---: | ---: | --- |',
        ];

        if ($matches === []) {
            $lines[] = '| — | Tidak ada kandidat yang melewati ambang tinjauan | — | — | — | — | — | — |';
        } else {
            foreach ($matches as $match) {
                $lines[] = '| '.implode(' | ', [
                    $this->escapeTableCell($match['destination_id']),
                    $this->escapeTableCell($match['destination_name']),
                    $this->escapeTableCell($match['region_name']),
                    $this->escapeTableCell($match['candidate_name']),
                    '[`'.$match['candidate_qid'].'`](https://www.wikidata.org/entity/'.$match['candidate_qid'].')',
                    (string) $match['distance_km'],
                    (string) $match['similarity'],
                    '',
                ]).' |';
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, array{qid: string, type: string}>  $regions
     */
    private function regionQid(string $destinationRegion, array $regions): ?string
    {
        $normalized = $this->normalizeName($destinationRegion);
        foreach ($regions as $name => $region) {
            if ($this->normalizeName($name) === $normalized) {
                return $region['qid'];
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/^(kabupaten|kota)\s+/u', '', $name) ?? $name;
        $name = preg_replace('/\s+(regency|city)(?:\s+of\s+indonesia)?$/u', '', $name) ?? $name;

        return preg_replace('/[^\pL\pN]+/u', '', $name) ?? '';
    }

    private function entityQid(mixed $value): ?string
    {
        return is_string($value)
            && preg_match('~^https?://www\.wikidata\.org/entity/(Q[0-9]+)$~', $value, $matches) === 1
            ? $matches[1]
            : null;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private function coordinate(mixed $value): ?array
    {
        if (! is_string($value)
            || preg_match('/^Point\(\s*(-?[0-9]+(?:\.[0-9]+)?)\s+(-?[0-9]+(?:\.[0-9]+)?)\s*\)$/i', trim($value), $matches) !== 1) {
            return null;
        }

        return ['longitude' => (float) $matches[1], 'latitude' => (float) $matches[2]];
    }

    private function decimal(mixed $value): ?float
    {
        return is_string($value) && is_numeric($value) && is_finite((float) $value)
            ? (float) $value
            : null;
    }

    private function distanceKm(float $latitude, float $longitude, float $otherLatitude, float $otherLongitude): float
    {
        $earthRadiusKm = 6371.0088;
        $latitudeDelta = deg2rad($otherLatitude - $latitude);
        $longitudeDelta = deg2rad($otherLongitude - $longitude);
        $value = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude)) * cos(deg2rad($otherLatitude)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * asin(min(1, sqrt($value)));
    }

    private function escapeTableCell(string $value): string
    {
        return str_replace(['|', "\r", "\n"], ['\\|', ' ', ' '], $value);
    }
}
