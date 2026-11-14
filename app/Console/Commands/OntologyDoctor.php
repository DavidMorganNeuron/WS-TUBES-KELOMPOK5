<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\Destination;
use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\FusekiClient;
use App\Services\Ontology\OntologyDataVersion;
use App\Services\Ontology\OntologyGraphMapper;
use App\Services\Ontology\OntologyInspector;
use App\Services\Ontology\OntologyValidator;
use App\Services\Ontology\TurtleParser;
use App\Services\Ontology\WikidataClient;
use App\Services\Ontology\WikidataQueryBuilder;
use App\Services\Ontology\WikidataRegionCatalog;
use App\Services\Ontology\WikidataRegionRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

#[Signature('ontology:doctor {id=ws-148 : ID destinasi yang diperiksa} {--file= : File Turtle v3 yang diperiksa} {--property=hargaMasuk : Properti yang dibandingkan antara Fuseki dan aplikasi} {--forget-cache : Invalidasi cache ontologi sebelum membandingkan} {--eksternal : Periksa konektivitas Wikidata secara langsung}')]
#[Description('Memeriksa konfigurasi, koneksi Fuseki, ontologi v3, cache, dan snapshot Wikidata')]
final class OntologyDoctor extends Command
{
    public function handle(
        FusekiClient $client,
        OntologyInspector $inspector,
        DestinationRepository $repository,
        OntologyDataVersion $dataVersion,
        OntologyGraphMapper $graphMapper,
        TurtleParser $parser,
        OntologyValidator $validator,
        WikidataRegionCatalog $regionCatalog,
        WikidataRegionRepository $wikidataRegions,
        WikidataClient $wikidataClient,
        WikidataQueryBuilder $wikidataQueryBuilder,
    ): int {
        $file = (string) ($this->option('file') ?: config('ontology.ttl_path'));
        $path = $this->resolvePath($file);
        $this->components->info('Konfigurasi ontologi');
        $this->line('  driver        : '.config('ontology.driver'));
        $this->line('  v3 file       : '.$path);
        $this->line('  cache TTL     : '.config('ontology.cache_ttl').' detik');
        $this->line('  cache version : '.($dataVersion->current() ?? '(belum diinisialisasi)'));
        $this->line('  Fuseki        : '.$client->datasetUrl());

        if (! is_file($path)) {
            $this->components->error("File ontologi tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        try {
            $triples = $parser->parseTriplesFile($path);
            $validation = $validator->validate(
                $triples,
                (string) config('ontology.unknown_label'),
                regionQidsByName: $regionCatalog->qidsByRegionName(),
            );
        } catch (Throwable $exception) {
            $this->components->error('Validasi file ontologi gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $fileIssues = count($validation['errors']);
        $this->line('  file SHA256   : '.hash_file('sha256', $path));
        $this->line('  destinasi v3  : '.$validation['destination_count']);
        $this->line('  triple v3     : '.count($triples));
        if ($fileIssues > 0) {
            $this->components->error('File ontologi tidak lolos validasi:');
            foreach ($validation['errors'] as $error) {
                $this->line(' - '.$error);
            }
        } else {
            $this->line('  validasi file : OK');
        }

        $wikidataSnapshotValid = $this->reportWikidata(
            triples: $triples,
            catalog: $regionCatalog,
            regions: $wikidataRegions,
            client: $wikidataClient,
            queryBuilder: $wikidataQueryBuilder,
        );

        if (! $client->isAvailable()) {
            $this->components->error('Fuseki tidak dapat diakses. Periksa FUSEKI_URL dan pastikan server berjalan.');

            return self::FAILURE;
        }

        try {
            if (! $client->datasetExists()) {
                $this->components->error('Dataset Fuseki tidak ditemukan. Buat dataset melalui Fuseki Admin UI.');

                return self::FAILURE;
            }

            if ($this->option('forget-cache')) {
                $dataVersion->refresh(hash_file('sha256', $path) ?: '');
                $repository->forgetCache();
                $this->line('  cache ontologi : diinvalidasi.');
            }

            $remoteRows = $client->fetchDestinations();
            $remoteTripleRows = $client->select('SELECT (COUNT(*) AS ?count) WHERE { ?s ?p ?o }');
            $remoteTripleCount = (int) ($remoteTripleRows[0]['count'] ?? -1);
            $applicationRows = $repository->all();
            $this->components->info('Konektivitas dan jumlah destinasi');
            $this->table(['Sumber', 'Destinasi'], [
                ['File v3', $validation['destination_count']],
                ['Fuseki', count($remoteRows)],
                ['Aplikasi/cache', $applicationRows->count()],
            ]);
            $this->line("  triple graph default Fuseki : {$remoteTripleCount}");
            $this->line('  triple file v3              : '.count($triples));

            $graphs = $inspector->graphs();
            $this->components->info('Graph bernama di Fuseki');
            if ($graphs === []) {
                $this->line('  tidak ada.');
            } else {
                $this->table(['Graph', 'Jumlah triple'], array_map(
                    static fn (array $row): array => [$row['g'] ?? '', $row['triples'] ?? ''],
                    $graphs,
                ));
            }

            $id = (string) $this->argument('id');
            $property = (string) $this->option('property');
            $triplesForDestination = $inspector->valuesOf($id, $property);
            $this->components->info("Nilai {$property} di Fuseki untuk {$id}");
            $this->table(['Graph', 'Subjek', 'Nilai'], array_map(
                static fn (array $row): array => array_values($row),
                $triplesForDestination,
            ));

            $sourceRows = collect($graphMapper->destinations($triples))
                ->map(fn (array $row): Destination => Destination::fromArray($row));
            $fusekiDestinations = collect($remoteRows)
                ->map(fn (array $row): Destination => Destination::fromArray($row));
            $sourceDestination = $sourceRows->firstWhere('id', $id);
            $fusekiDestination = $fusekiDestinations->firstWhere('id', $id);
            $destination = $repository->find($id);
            $this->components->info('Yang dibaca aplikasi');
            $this->line('  prices : '.($destination === null ? '(destinasi tidak ditemukan)' : json_encode($destination->prices)));
            $sourceValues = $this->destinationValues($sourceDestination, $property);
            $fusekiValues = $this->destinationValues($fusekiDestination, $property);
            $applicationValues = $this->destinationValues($destination, $property);
            $comparisonAvailable = $sourceDestination !== null
                && $fusekiDestination !== null
                && $destination !== null
                && $sourceValues !== null
                && $fusekiValues !== null
                && $applicationValues !== null;
            $valuesMatch = $comparisonAvailable
                && $this->normalizeValues($sourceValues) === $this->normalizeValues($fusekiValues)
                && $this->normalizeValues($fusekiValues) === $this->normalizeValues($applicationValues);
            $this->table(['Sumber v3', 'Fuseki', 'Aplikasi'], [[
                $sourceValues === null ? '(tidak tersedia)' : json_encode($sourceValues),
                $fusekiValues === null ? '(tidak tersedia)' : json_encode($fusekiValues),
                $applicationValues === null ? '(tidak tersedia)' : json_encode($applicationValues),
            ]]);
            if ($comparisonAvailable && ! $valuesMatch) {
                $this->components->error('Nilai properti berbeda antara file v3, Fuseki, dan aplikasi.');
            }

            $duplicates = $inspector->duplicateIds();
            $violations = $inspector->cardinalityViolations();
            $this->reportRows('ID dipakai lebih dari satu subjek', ['ID', 'Jumlah', 'Subjek'], $duplicates, ['id', 'jumlah', 'subjek']);
            $this->reportRows('Kardinalitas properti fungsional dilanggar', ['ID', 'Properti', 'Jumlah', 'Nilai'], $violations, ['id', 'p', 'jumlah', 'nilai']);

            $hasIssues = $fileIssues > 0
                || ! $wikidataSnapshotValid
                || count($remoteRows) !== $validation['destination_count']
                || $applicationRows->count() !== $validation['destination_count']
                || $sourceDestination === null
                || $fusekiDestination === null
                || $destination === null
                || ($comparisonAvailable && ! $valuesMatch)
                || $remoteTripleCount < 0
                || $remoteTripleCount !== count($triples)
                || $graphs !== []
                || $duplicates !== []
                || $violations !== [];

            if ($hasIssues) {
                $this->components->error('Pemeriksaan menemukan ketidaksesuaian. Periksa sumber v3, muat ulang, lalu jalankan doctor kembali.');

                return self::FAILURE;
            }
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error('Pemeriksaan Fuseki atau aplikasi gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Konfigurasi, file, Fuseki, dan aplikasi konsisten.');

        return self::SUCCESS;
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string}}>  $triples
     */
    private function reportWikidata(
        array $triples,
        WikidataRegionCatalog $catalog,
        WikidataRegionRepository $regions,
        WikidataClient $client,
        WikidataQueryBuilder $queryBuilder,
    ): bool {
        $contact = trim((string) config('ontology.wikidata.contact', ''));
        $effectiveContact = $contact !== ''
            ? $contact
            : (string) config('ontology.wikidata.contact_fallback', '');
        $this->line('  kontak Wikidata efektif : '.$effectiveContact);
        if (config('app.env') === 'production' && $contact === '') {
            $this->components->warn('D9: EXTERNAL_CONTACT belum diatur pada lingkungan production.');
        }

        try {
            $expectedRegions = $catalog->load()['regions'];
            $snapshot = $regions->loadSnapshot(allowStale: true);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error('Snapshot detail Wikidata gagal dibaca atau tidak valid: '.$exception->getMessage());

            return false;
        }

        if ($snapshot === null) {
            $this->components->error('Snapshot detail Wikidata tidak tersedia.');

            return false;
        }

        $namespaces = config('ontology.namespaces');
        $rdfType = $namespaces['rdf'].'type';
        $regionClass = $namespaces['ws'].'KabupatenKota';
        $sameAs = $namespaces['owl'].'sameAs';
        $regionSubjects = [];
        $datasetQids = [];

        foreach ($triples as $triple) {
            if ($triple['predicate'] === $rdfType
                && $triple['object']['kind'] === 'iri'
                && $triple['object']['value'] === $regionClass) {
                $regionSubjects[$triple['subject']] = true;
            }
        }

        foreach ($triples as $triple) {
            if (isset($regionSubjects[$triple['subject']]) && $triple['predicate'] === $sameAs
                && preg_match('~^https://www\.wikidata\.org/entity/(Q[0-9]+)$~', $triple['object']['value'], $matches) === 1) {
                $datasetQids[] = $matches[1];
            }
        }

        $expectedQids = array_column($expectedRegions, 'qid');
        $snapshotQids = array_column($snapshot['regions'], 'qid');
        sort($expectedQids);
        sort($snapshotQids);
        sort($datasetQids);
        $coverageMatches = count($regionSubjects) === count($expectedRegions)
            && $expectedQids === $snapshotQids
            && $expectedQids === $datasetQids;
        $snapshotRegions = array_values($snapshot['regions']);
        $this->table(['Cakupan snapshot wilayah', 'Jumlah'], [
            ['Kabupaten/Kota dengan QID cocok', count($datasetQids).'/'.count($regionSubjects)],
            ['Gambar', count(array_filter($snapshotRegions, static fn (array $region): bool => $region['image_url'] !== null))],
            ['Populasi', count(array_filter($snapshotRegions, static fn (array $region): bool => $region['population'] !== null))],
            ['Luas', count(array_filter($snapshotRegions, static fn (array $region): bool => $region['area'] !== null))],
            ['Situs resmi', count(array_filter($snapshotRegions, static fn (array $region): bool => ($region['website_url'] ?? null) !== null))],
        ]);
        $this->line('  snapshot Wikidata : '.($snapshot['is_stale'] ? 'lama' : 'segar'));
        if (! $coverageMatches) {
            $this->components->error('Cakupan QID snapshot detail tidak cocok dengan seluruh Kabupaten/Kota pada ontologi.');
        }

        if (! $this->option('eksternal')) {
            $this->line('  konektivitas Wikidata : tidak diuji (gunakan --eksternal untuk menghubungi layanan).');

            return $coverageMatches;
        }

        try {
            $firstQid = $expectedQids[0] ?? null;
            if ($firstQid === null || $client->select($queryBuilder->details([$firstQid]), refresh: true) === []) {
                throw new RuntimeException('Wikidata Query Service tidak mengembalikan hasil untuk QID uji.');
            }
        } catch (RuntimeException $exception) {
            $this->components->error('Konektivitas Wikidata gagal: '.$exception->getMessage());

            return false;
        }

        $this->line('  konektivitas Wikidata : berhasil.');

        return $coverageMatches;
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

    /**
     * @return list<string>|null
     */
    private function destinationValues(?Destination $destination, string $property): ?array
    {
        if ($destination === null) {
            return [];
        }

        $values = match ($property) {
            'idDestinasi' => [$destination->id],
            'namaDestinasi' => [$destination->name],
            'namaAlternatif' => [$destination->alternativeName],
            'deskripsiSingkat' => [$destination->shortDescription],
            'deskripsiLengkap' => [$destination->fullDescription],
            'kataKunci' => $destination->keywords,
            'alamat' => [$destination->address],
            'petunjukLokasi' => [$destination->directions],
            'lintang' => $destination->latitude === null ? [] : [(string) $destination->latitude],
            'bujur' => $destination->longitude === null ? [] : [(string) $destination->longitude],
            'ratingPengunjung' => $destination->rating === null ? [] : [(string) $destination->rating],
            'berkategori' => $destination->types,
            'berlokasiDi' => [$destination->location],
            'menyediakanFasilitas' => $destination->facilities,
            'menawarkanAktivitas' => $destination->activities,
            'memilikiMedia', 'urlMedia' => [$destination->media],
            'nomorHp' => [$destination->phone],
            'email' => [$destination->email],
            'urlInstagram' => [$destination->instagram],
            'jamBuka', 'jamTutup', 'bukaSepanjangWaktu', 'keteranganJadwal' => [$destination->openingHours],
            'hargaMasuk', 'keteranganTarif' => $destination->prices,
            default => null,
        };

        return array_values(array_filter(
            array_map(static fn (string $value): string => trim($value), $values),
            static fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function normalizeValues(array $values): array
    {
        $values = array_map(static function (string $value): string {
            if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $matches) !== 1) {
                return $value;
            }

            $integer = ltrim($matches[2], '0');
            $integer = $integer === '' ? '0' : $integer;
            $fraction = rtrim($matches[3] ?? '', '0');
            $normalized = $integer.($fraction === '' ? '' : '.'.$fraction);

            return $normalized === '0' ? '0' : ($matches[1] === '-' ? '-' : '').$normalized;
        }, $values);
        sort($values, SORT_STRING);

        return array_values(array_unique($values));
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
