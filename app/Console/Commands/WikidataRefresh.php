<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ontology\OntologyDestinationIdConsistency;
use App\Services\Ontology\TurtleParser;
use App\Services\Ontology\WikidataClient;
use App\Services\Ontology\WikidataDestinationCandidateMatcher;
use App\Services\Ontology\WikidataDestinationLinkCatalog;
use App\Services\Ontology\WikidataQueryBuilder;
use App\Services\Ontology\WikidataRegionRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

#[Signature('wikidata:refresh {--allow-stale : Tampilkan snapshot tersimpan jika pembaruan live gagal} {--candidates : Cari dan nilai kandidat tautan destinasi} {--candidates-only : Cari kandidat tanpa memperbarui snapshot detail}')]
#[Description('Perbarui snapshot Wikidata wilayah dan destinasi; penemuan kandidat bersifat opsional')]
final class WikidataRefresh extends Command
{
    public function handle(
        WikidataRegionRepository $regions,
        WikidataDestinationLinkCatalog $destinationLinks,
        WikidataDestinationCandidateMatcher $candidateMatcher,
        WikidataClient $client,
        WikidataQueryBuilder $queryBuilder,
        TurtleParser $parser,
        OntologyDestinationIdConsistency $destinationIds,
    ): int {
        if (! $this->option('candidates-only')) {
            try {
                $snapshot = $regions->refresh();
            } catch (InvalidArgumentException|RuntimeException $exception) {
                $this->error('Pembaruan Wikidata gagal: '.$exception->getMessage());
                $this->warn('Data Wikidata terbaru: BELUM TERVERIFIKASI.');
                $this->line('Langkah manual: pulihkan akses jaringan ke query.wikidata.org, lalu jalankan kembali php artisan wikidata:refresh.');
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
                ['Halaman berkas Commons', count(array_filter(
                    $snapshot['regions'],
                    static fn (array $region): bool => $region['image_page_url'] !== null,
                ))],
                ['Artikel Wikipedia Indonesia', count(array_filter(
                    $snapshot['regions'],
                    static fn (array $region): bool => $region['wikipedia_url'] !== null,
                ))],
                ['Panduan Wikivoyage (id)', count(array_filter(
                    $snapshot['regions'],
                    static fn (array $region): bool => $region['wikivoyage_language'] === 'id',
                ))],
                ['Panduan Wikivoyage (en)', count(array_filter(
                    $snapshot['regions'],
                    static fn (array $region): bool => $region['wikivoyage_language'] === 'en',
                ))],
                ['Label Indonesia', count(array_filter(
                    $snapshot['regions'],
                    static fn (array $region): bool => $region['labels']['id'] !== null,
                ))],
                ['Label Inggris', count(array_filter(
                    $snapshot['regions'],
                    static fn (array $region): bool => $region['labels']['en'] !== null,
                ))],
                ['Wilayah dengan alias', count(array_filter(
                    $snapshot['regions'],
                    static fn (array $region): bool => ($region['aliases']['id'] ?? null) !== null
                        || ($region['aliases']['en'] ?? null) !== null,
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
                ['Destinasi disetujui', count($snapshot['destinations'] ?? [])],
                ['Destinasi dengan label Indonesia', count(array_filter(
                    $snapshot['destinations'] ?? [],
                    static fn (array $destination): bool => $destination['labels']['id'] !== null,
                ))],
                ['Destinasi dengan label Inggris', count(array_filter(
                    $snapshot['destinations'] ?? [],
                    static fn (array $destination): bool => $destination['labels']['en'] !== null,
                ))],
                ['Destinasi dengan alias', count(array_filter(
                    $snapshot['destinations'] ?? [],
                    static fn (array $destination): bool => ($destination['aliases']['id'] ?? null) !== null
                        || ($destination['aliases']['en'] ?? null) !== null,
                ))],
                ['Tujuan dengan foto Commons', count(array_filter(
                    $snapshot['destinations'] ?? [],
                    static fn (array $destination): bool => $destination['image_url'] !== null,
                ))],
                ['Tujuan dengan Wikipedia Indonesia', count(array_filter(
                    $snapshot['destinations'] ?? [],
                    static fn (array $destination): bool => $destination['wikipedia_url'] !== null,
                ))],
                ['Tujuan dengan Wikivoyage', count(array_filter(
                    $snapshot['destinations'] ?? [],
                    static fn (array $destination): bool => $destination['wikivoyage_url'] !== null,
                ))],
                ['Tujuan dengan status warisan', count(array_filter(
                    $snapshot['destinations'] ?? [],
                    static fn (array $destination): bool => $destination['heritage_statuses'] !== [],
                ))],
                ['Tujuan dengan ketinggian', count(array_filter(
                    $snapshot['destinations'] ?? [],
                    static fn (array $destination): bool => $destination['elevation_m'] !== null,
                ))],
                ['Tujuan dengan tahun berdiri', count(array_filter(
                    $snapshot['destinations'] ?? [],
                    static fn (array $destination): bool => $destination['establishment_year'] !== null,
                ))],
                ['Waktu pengambilan', $snapshot['retrieved_at']],
            ]);
            $this->info('Snapshot detail Wikidata wilayah dan destinasi disimpan; dataset sumber tidak diubah.');

            if (! $this->option('candidates')) {
                return self::SUCCESS;
            }
        }

        try {
            $this->waitBeforeRequest();
            $tourismClassQid = (string) config('ontology.wikidata.tourism_class_qid');
            $tourismClassRows = $client->select($queryBuilder->tourismClass([$tourismClassQid]), refresh: true);
            $tourismClassLabel = $this->verifiedTourismClassLabel($tourismClassRows, $tourismClassQid);

            $this->waitBeforeRequest();
            $candidateRows = $client->select($queryBuilder->tourismCandidates($tourismClassQid), refresh: true);
            $candidateQids = $this->candidateQids($candidateRows);
            $nameRows = [];
            if ($candidateQids !== []) {
                $this->waitBeforeRequest();
                $nameRows = $client->select($queryBuilder->labelsAndAliases($candidateQids), refresh: true);
            }

            $sourcePath = base_path((string) config('ontology.v2_source_path'));
            if (! is_file($sourcePath)) {
                throw new RuntimeException("Sumber lokal v2 tidak ditemukan: {$sourcePath}");
            }

            $sourceRows = $parser->parseFile($sourcePath);
            if ($sourceRows === []) {
                throw new RuntimeException('Sumber lokal v2 kosong.');
            }

            $v3Path = base_path((string) config('ontology.ttl_path'));
            if (! is_file($v3Path)) {
                throw new RuntimeException("Graph v3 tidak ditemukan: {$v3Path}");
            }
            $destinationIds->validate($sourceRows, $parser->parseTriplesFile($v3Path));

            $matches = $candidateMatcher->match($sourceRows, $candidateRows, $nameRows);
            $existingLinks = $destinationLinks->load();
            $ownerRejectedQids = array_fill_keys(
                array_column($existingLinks['tolak'], 'qid'),
                true,
            );
            foreach ($matches as &$match) {
                if (isset($ownerRejectedQids[$match['qid']])) {
                    $match['status'] = 'DITOLAK';
                    $match['reason'] = 'QID ini ditolak pemilik dan tidak dapat disetujui otomatis.';
                }
            }
            unset($match);
            $approved = array_map(
                static fn (array $candidate): array => [
                    'id' => $candidate['id'],
                    'qid' => $candidate['qid'],
                    'reason' => $candidate['reason'],
                ],
                array_values(array_filter(
                    $matches,
                    static fn (array $candidate): bool => $candidate['status'] === 'AUTO-SETUJU',
                )),
            );
            usort($approved, static fn (array $left, array $right): int => [$left['id'], $left['qid']] <=> [$right['id'], $right['qid']]);
            $rejectedAutomatically = array_values(array_filter(
                $matches,
                static fn (array $candidate): bool => $candidate['status'] === 'DITOLAK',
            ));
            $ambiguous = array_values(array_filter(
                $matches,
                static fn (array $candidate): bool => $candidate['status'] === 'AMBIGU',
            ));
            $linkDocument = [
                'schema_version' => 1,
                'retrieved_at' => now()->toIso8601String(),
                'tourism_class' => [
                    'qid' => $tourismClassQid,
                    'label' => $tourismClassLabel,
                    'label_language' => 'en',
                ],
                'search' => [
                    'south_west' => [0.5, 97.0],
                    'north_east' => [4.5, 100.5],
                ],
                'setuju' => $approved,
                'tolak' => $existingLinks['tolak'],
                'ditolak_otomatis' => array_map(
                    static fn (array $candidate): array => [
                        'id' => $candidate['id'],
                        'qid' => $candidate['qid'],
                        'reason' => $candidate['reason'],
                    ],
                    $rejectedAutomatically,
                ),
                'ambigu' => array_map(
                    static fn (array $candidate): array => [
                        'id' => $candidate['id'],
                        'qid' => $candidate['qid'],
                        'reason' => $candidate['reason'],
                    ],
                    $ambiguous,
                ),
            ];
            $reportPath = base_path((string) config('ontology.wikidata.candidate_report_path'));
            $directory = dirname($reportPath);
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException("Direktori laporan kandidat tidak dapat dibuat: {$directory}");
            }

            $report = $this->renderCandidateReport($matches, $tourismClassQid, $tourismClassLabel);
            if (file_put_contents($reportPath, $report, LOCK_EX) === false) {
                throw new RuntimeException("Laporan kandidat tidak dapat ditulis: {$reportPath}");
            }
            $this->writeLinkDocument($linkDocument);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error('Pembuatan laporan kandidat gagal: '.$exception->getMessage());
            $this->warn('Penemuan kandidat Wikidata: BELUM TERVERIFIKASI.');
            $this->line('Langkah manual: pulihkan akses ke query.wikidata.org, verifikasi kelas wisata, lalu jalankan php artisan wikidata:refresh --candidates-only.');

            return self::FAILURE;
        }

        $this->table(['Hasil penemuan kandidat', 'Jumlah'], [
            ['Kandidat unik di kotak koordinat', count($candidateQids)],
            ['Baris kandidat untuk ditinjau', count($matches)],
            ['AUTO-SETUJU', count($approved)],
            ['DITOLAK', count($rejectedAutomatically)],
            ['AMBIGU', count($ambiguous)],
            ['QID ditolak pemilik', count($existingLinks['tolak'])],
        ]);
        $this->info("Laporan kandidat tautan destinasi dibuat: {$reportPath}");
        $this->info('Berkas persetujuan Wikidata destinasi diperbarui tanpa menghapus penolakan pemilik.');

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $matches
     */
    private function renderCandidateReport(array $matches, string $classQid, string $classLabel): string
    {
        $lines = [
            '# Kandidat tautan Wikidata destinasi',
            '',
            '- Dibuat: '.now()->toIso8601String().'.',
            '- Kelas wisata diverifikasi live dari Query Service: '.$classLabel.' ([`'.$classQid.'`](https://www.wikidata.org/wiki/'.$classQid.')).',
            '- Penemuan memakai `wikibase:box` pada kotak Sumatera Utara: lintang 0,5–4,5 dan bujur 97–100,5; satu kueri batch kelas/koordinat, bukan satu kueri per destinasi.',
            '- Nama lokal dan alternatif dinormalisasi; label serta alias Wikidata Indonesia/Inggris dicocokkan lokal. Jarak dihitung dengan Haversine (jari-jari bumi 6.371,0088 km).',
            '- Skor = 75% kemiripan nama (`similar_text` atas nama ternormalisasi terbaik) + 25% kedekatan; kedekatan = `100 × max(0, 1 − jarak_km/20)`.',
            '- Ambang tinjauan: jarak ≤20 km dan kemiripan nama ≥90%; jika tidak ada pasangan ≥90%, kandidat nama terdekat dengan kemiripan ≥60% ditampilkan sebagai DITOLAK.',
            '- Asumsi konservatif D16 untuk AUTO-SETUJU: nama ternormalisasi identik, jarak ≤0,5 km, dan tautan satu-ke-satu yang tidak berbagi QID. Kasus lain tetap AMBIGU/DITOLAK untuk keputusan manusia.',
            '- Kolom KEPUTUSAN PEMILIK sengaja kosong. QID sameAs hanya berasal dari daftar `setuju` pada berkas JSON; `tolak` dapat ditambahkan pemilik.',
            '- Jumlah kandidat: '.count($matches).'.',
            '',
            '| ID | Destinasi | QID | Label Wikidata | Kelas langsung | Jarak (km) | Skor | Status | Alasan | KEPUTUSAN PEMILIK |',
            '| --- | --- | --- | --- | --- | ---: | ---: | --- | --- | --- |',
        ];

        if ($matches === []) {
            $lines[] = '| — | Tidak ada kandidat yang melewati ambang nama/jarak | — | — | — | — | — | — | — | — |';
        } else {
            foreach ($matches as $match) {
                $lines[] = '| '.implode(' | ', [
                    $this->escapeTableCell($match['id']),
                    $this->escapeTableCell($match['local_name']),
                    '[`'.$match['qid'].'`](https://www.wikidata.org/wiki/'.$match['qid'].')',
                    $this->escapeTableCell($match['label']),
                    $this->escapeTableCell($match['class']),
                    number_format($match['distance_km'], 2, '.', ''),
                    number_format($match['score'], 1, '.', ''),
                    $this->escapeTableCell($match['status']),
                    $this->escapeTableCell($match['reason']),
                    '',
                ]).' |';
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     */
    private function verifiedTourismClassLabel(array $rows, string $expectedQid): string
    {
        $labels = [];
        foreach ($rows as $row) {
            $item = $row['item']['value'] ?? null;
            $label = $row['label']['value'] ?? null;
            if ($item === 'http://www.wikidata.org/entity/'.$expectedQid && is_string($label)) {
                $labels[$label] = true;
            }
        }

        $labels = array_keys($labels);
        sort($labels, SORT_STRING);
        if ($labels !== ['tourist attraction']) {
            throw new RuntimeException('QID kelas wisata tidak terverifikasi live sebagai "tourist attraction".');
        }

        return $labels[0];
    }

    /**
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return list<string>
     */
    private function candidateQids(array $rows): array
    {
        $qids = [];
        foreach ($rows as $row) {
            foreach (['candidate', 'class'] as $property) {
                $value = $row[$property]['value'] ?? null;
                if (! is_string($value)
                    || preg_match('~^http://www\.wikidata\.org/entity/(Q[0-9]+)$~', $value, $matches) !== 1) {
                    throw new RuntimeException('Hasil penemuan kandidat memuat IRI item Wikidata yang tidak valid.');
                }
                if ($property === 'candidate') {
                    $qids[$matches[1]] = true;
                }
            }

            if (($row['tourismClassLabel']['value'] ?? null) !== 'tourist attraction') {
                throw new RuntimeException('Hasil penemuan kandidat tidak membuktikan label kelas wisata yang diharapkan.');
            }
        }

        $qids = array_keys($qids);
        sort($qids, SORT_STRING);

        return $qids;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function writeLinkDocument(array $document): void
    {
        $path = base_path((string) config('ontology.wikidata.destination_links_path'));
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Direktori berkas persetujuan tidak dapat dibuat: {$directory}");
        }

        $contents = json_encode(
            $document,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        )."\n";
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Berkas persetujuan Wikidata destinasi tidak dapat ditulis: {$path}");
        }
    }

    private function waitBeforeRequest(): void
    {
        $interval = (int) config('ontology.wikidata.request_interval_seconds', 1);
        if ($interval > 0) {
            sleep($interval);
        }
    }

    private function escapeTableCell(string $value): string
    {
        return str_replace(['|', "\r", "\n"], ['\\|', ' ', ' '], $value);
    }
}
