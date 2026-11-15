<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class OntologyMigrator
{
    public const RDF_NS = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    public const RDFS_NS = 'http://www.w3.org/2000/01/rdf-schema#';

    public const OWL_NS = 'http://www.w3.org/2002/07/owl#';

    public const XSD_NS = 'http://www.w3.org/2001/XMLSchema#';

    public const WS_NS = 'http://pariwisatasumut.org/ontology/v2#';

    public const DATA_NS = 'http://pariwisatasumut.org/resource/';

    public const WIKIDATA_ENTITY_NS = 'http://www.wikidata.org/entity/';

    /**
     * @var list<string>
     */
    public const CLASSES = [
        'DestinasiWisata',
        'KategoriWisata',
        'KabupatenKota',
        'Fasilitas',
        'Aktivitas',
        'Media',
        'KontakResmi',
        'JadwalOperasional',
        'TarifMasuk',
    ];

    /**
     * @var array<string, array{domain: string, range: string, functional?: bool, symmetric?: bool}>
     */
    public const OBJECT_PROPERTIES = [
        'berkategori' => ['domain' => 'DestinasiWisata', 'range' => 'KategoriWisata'],
        'berlokasiDi' => ['domain' => 'DestinasiWisata', 'range' => 'KabupatenKota', 'functional' => true],
        'menyediakanFasilitas' => ['domain' => 'DestinasiWisata', 'range' => 'Fasilitas'],
        'menawarkanAktivitas' => ['domain' => 'DestinasiWisata', 'range' => 'Aktivitas'],
        'memilikiMedia' => ['domain' => 'DestinasiWisata', 'range' => 'Media'],
        'memilikiKontak' => ['domain' => 'DestinasiWisata', 'range' => 'KontakResmi', 'functional' => true],
        'memilikiJadwal' => ['domain' => 'DestinasiWisata', 'range' => 'JadwalOperasional', 'functional' => true],
        'memilikiTarif' => ['domain' => 'DestinasiWisata', 'range' => 'TarifMasuk'],
        'dekatDengan' => ['domain' => 'DestinasiWisata', 'range' => 'DestinasiWisata', 'symmetric' => true],
        'berbatasanDengan' => ['domain' => 'KabupatenKota', 'range' => 'KabupatenKota', 'symmetric' => true],
    ];

    /**
     * @var array<string, array{domain: string, range: string, functional?: bool}>
     */
    public const DATA_PROPERTIES = [
        'idDestinasi' => ['domain' => 'DestinasiWisata', 'range' => 'xsd:string', 'functional' => true],
        'namaDestinasi' => ['domain' => 'DestinasiWisata', 'range' => 'rdf:langString'],
        'namaAlternatif' => ['domain' => 'DestinasiWisata', 'range' => 'rdf:langString'],
        'deskripsiSingkat' => ['domain' => 'DestinasiWisata', 'range' => 'rdf:langString'],
        'deskripsiLengkap' => ['domain' => 'DestinasiWisata', 'range' => 'rdf:langString'],
        'kataKunci' => ['domain' => 'DestinasiWisata', 'range' => 'rdf:langString'],
        'alamat' => ['domain' => 'DestinasiWisata', 'range' => 'rdf:langString'],
        'petunjukLokasi' => ['domain' => 'DestinasiWisata', 'range' => 'rdf:langString'],
        'lintang' => ['domain' => 'DestinasiWisata', 'range' => 'xsd:decimal', 'functional' => true],
        'bujur' => ['domain' => 'DestinasiWisata', 'range' => 'xsd:decimal', 'functional' => true],
        'ratingPengunjung' => ['domain' => 'DestinasiWisata', 'range' => 'rdfs:Literal', 'functional' => true],
        'tipeWilayah' => ['domain' => 'KabupatenKota', 'range' => 'xsd:string'],
        'urlMedia' => ['domain' => 'Media', 'range' => 'xsd:anyURI'],
        'nomorHp' => ['domain' => 'KontakResmi', 'range' => 'xsd:string'],
        'email' => ['domain' => 'KontakResmi', 'range' => 'xsd:string'],
        'urlInstagram' => ['domain' => 'KontakResmi', 'range' => 'xsd:string'],
        'jamBuka' => ['domain' => 'JadwalOperasional', 'range' => 'xsd:time'],
        'jamTutup' => ['domain' => 'JadwalOperasional', 'range' => 'xsd:time'],
        'bukaSepanjangWaktu' => ['domain' => 'JadwalOperasional', 'range' => 'xsd:boolean'],
        'keteranganJadwal' => ['domain' => 'JadwalOperasional', 'range' => 'rdfs:Literal'],
        'hargaMasuk' => ['domain' => 'TarifMasuk', 'range' => 'xsd:decimal'],
        'keteranganTarif' => ['domain' => 'TarifMasuk', 'range' => 'rdfs:Literal'],
    ];

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array{
     *     source: string,
     *     retrieved_at: string,
     *     province_qid: string,
     *     regions: array<string, array{qid: string, type: string}>,
     *     boundaries: list<array{0: string, 1: string}>
     * }  $boundarySource
     * @param  array<string, string>  $destinationQidsById
     * @return array{
     *     triples: list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>,
     *     anomalies: array<string, mixed>,
     *     stats: array<string, int>
     * }
     */
    public function migrate(
        array $rows,
        array $boundarySource,
        string $unknownLabel,
        float $nearbyRadiusKm,
        int $maxNeighbors,
        array $destinationQidsById = [],
    ): array {
        if ($rows === []) {
            throw new InvalidArgumentException('Dataset sumber tidak memiliki destinasi.');
        }

        if ($unknownLabel === '' || $nearbyRadiusKm <= 0 || $maxNeighbors < 1) {
            throw new InvalidArgumentException('Konfigurasi migrasi ontologi tidak valid.');
        }

        $sourceIds = [];
        foreach ($rows as $row) {
            $id = trim($row['idDestinasi'] ?? '');
            if ($id !== '') {
                $sourceIds[$id] = true;
            }
        }
        $approvedQids = [];
        foreach ($destinationQidsById as $id => $qid) {
            if (! is_string($id) || ! isset($sourceIds[$id])
                || ! is_string($qid) || preg_match('/^Q[0-9]+$/', $qid) !== 1
                || isset($approvedQids[$qid])) {
                throw new InvalidArgumentException('Pemetaan sameAs destinasi harus valid, terdaftar pada sumber, dan satu-ke-satu.');
            }
            $approvedQids[$qid] = true;
        }

        $regions = $this->validatedRegions($boundarySource);
        foreach ($regions as $region) {
            if (isset($approvedQids[$region['qid']])) {
                throw new InvalidArgumentException('QID sameAs destinasi tidak boleh dipakai kembali oleh wilayah.');
            }
        }
        $triples = [];
        $this->addSchema($triples, $unknownLabel);

        $slugOwners = [];
        $resources = [];
        $regionIrisByQid = [];
        foreach ($regions as $name => $region) {
            $regionIrisByQid[$region['qid']] = $this->namedResource(
                $triples,
                $resources,
                'wilayah-',
                $name,
                'KabupatenKota',
                $slugOwners,
            );
            $this->addIri(
                $triples,
                $regionIrisByQid[$region['qid']],
                self::OWL_NS.'sameAs',
                self::WIKIDATA_ENTITY_NS.$region['qid'],
            );
            $this->addLiteral(
                $triples,
                $regionIrisByQid[$region['qid']],
                'tipeWilayah',
                $region['type'],
                self::XSD_NS.'string',
            );
        }

        $destinations = [];
        $seenIds = [];
        $anomalies = $this->collectAnomalies($rows, $unknownLabel);

        foreach ($rows as $row) {
            $id = trim($row['idDestinasi'] ?? '');
            $name = $this->cleanText($row['namaDestinasi'] ?? '');

            if ($id === '' || preg_match('/^[A-Za-z0-9_-]+$/', $id) !== 1) {
                throw new InvalidArgumentException('ID destinasi kosong atau tidak aman untuk IRI data.');
            }

            if (isset($seenIds[$id])) {
                throw new InvalidArgumentException("ID destinasi duplikat pada sumber: {$id}.");
            }
            $seenIds[$id] = true;

            $destinationIri = self::DATA_NS.$id;
            $destinations[$id] = ['iri' => $destinationIri, 'row' => $row];
            $this->addType($triples, $destinationIri, 'DestinasiWisata');
            if (isset($destinationQidsById[$id])) {
                $this->addIri(
                    $triples,
                    $destinationIri,
                    self::OWL_NS.'sameAs',
                    self::WIKIDATA_ENTITY_NS.$destinationQidsById[$id],
                );
            }
            $this->addLiteral($triples, $destinationIri, 'idDestinasi', $id, self::XSD_NS.'string');
            $this->addText($triples, $destinationIri, 'namaDestinasi', $name, $unknownLabel);
            $this->addText($triples, $destinationIri, 'deskripsiSingkat', $row['deskripsiSingkat'] ?? '', $unknownLabel);
            $this->addText($triples, $destinationIri, 'deskripsiLengkap', $row['deskripsiLengkap'] ?? '', $unknownLabel);
            $this->addText($triples, $destinationIri, 'alamat', $row['alamat'] ?? '', $unknownLabel);
            $this->addText($triples, $destinationIri, 'petunjukLokasi', $row['petunjukLokasi'] ?? '', $unknownLabel);

            foreach ($this->splitValues($row['namaAlternatif'] ?? '', $unknownLabel) as $alternativeName) {
                $this->addLiteral($triples, $destinationIri, 'namaAlternatif', $alternativeName, null, 'id');
            }

            foreach ($this->splitValues($row['kataKunci'] ?? '', $unknownLabel, true) as $keyword) {
                $this->addLiteral($triples, $destinationIri, 'kataKunci', $keyword, null, 'id');
            }

            foreach ($this->splitValues($row['jenisDestinasi'] ?? '', $unknownLabel) as $category) {
                if (! in_array($category, DestinationRepository::TYPES, true)) {
                    throw new InvalidArgumentException("Kategori sumber tidak dikenal: {$category} ({$id}).");
                }

                $categoryIri = $this->namedResource(
                    $triples,
                    $resources,
                    'kategori-',
                    $category,
                    'KategoriWisata',
                    $slugOwners,
                );
                $this->addIri($triples, $destinationIri, 'berkategori', $categoryIri);
            }

            $regionName = $this->cleanText($row['beradaDi'] ?? '');
            if (! isset($regions[$regionName])) {
                throw new InvalidArgumentException("Wilayah sumber tidak ditemukan pada data batas Wikidata: {$regionName} ({$id}).");
            }

            $regionIri = $this->namedResource(
                $triples,
                $resources,
                'wilayah-',
                $regionName,
                'KabupatenKota',
                $slugOwners,
            );
            $this->addIri($triples, $destinationIri, 'berlokasiDi', $regionIri);

            foreach ([
                'memilikiFasilitas' => ['Fasilitas', 'fasilitas-', 'menyediakanFasilitas'],
                'dapatMelakukan' => ['Aktivitas', 'aktivitas-', 'menawarkanAktivitas'],
            ] as $sourceProperty => [$class, $prefix, $objectProperty]) {
                foreach ($this->splitValues($row[$sourceProperty] ?? '', $unknownLabel) as $label) {
                    $resourceIri = $this->namedResource(
                        $triples,
                        $resources,
                        $prefix,
                        $label,
                        $class,
                        $slugOwners,
                    );
                    $this->addIri($triples, $destinationIri, $objectProperty, $resourceIri);
                }
            }

            $coordinate = $this->coordinateTriples($triples, $destinationIri, $row);
            if ($coordinate !== null) {
                $destinations[$id]['coordinates'] = $coordinate;
            }

            $ratingValues = $this->splitValues($row['ratingPengunjung'] ?? '', $unknownLabel);
            if ($ratingValues === []) {
                $ratingValues = [$unknownLabel];
            }

            foreach ($ratingValues as $rating) {
                if ($rating === $unknownLabel) {
                    $this->addLiteral($triples, $destinationIri, 'ratingPengunjung', $unknownLabel, self::XSD_NS.'string');
                } elseif ($this->isDecimal($rating)) {
                    $this->addLiteral($triples, $destinationIri, 'ratingPengunjung', $rating, self::XSD_NS.'decimal');
                } else {
                    $this->addLiteral($triples, $destinationIri, 'ratingPengunjung', $rating, self::XSD_NS.'string');
                }
            }

            $this->migrateMedia($triples, $destinationIri, $id, $row, $unknownLabel);
            $this->migrateContact($triples, $destinationIri, $id, $row, $unknownLabel);
            $this->migrateSchedule($triples, $destinationIri, $id, $row, $unknownLabel);
            $this->migratePrice($triples, $destinationIri, $id, $row, $unknownLabel);
        }

        $this->addNearbyRelations($triples, $destinations, $nearbyRadiusKm, $maxNeighbors);
        $this->addBoundaryRelations($triples, $regionIrisByQid, $boundarySource['boundaries']);

        return [
            'triples' => $this->uniqueTriples($triples),
            'anomalies' => $anomalies,
            'stats' => [
                'destinations' => count($destinations),
                'districts' => count($regions),
                'region_same_as' => count($regions),
                'destination_same_as' => count($destinationQidsById),
                'nearby_radius_meters' => (int) round($nearbyRadiusKm * 1000),
                'max_nearby_neighbors' => $maxNeighbors,
            ],
        ];
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     */
    private function addSchema(array &$triples, string $unknownLabel): void
    {
        $ontologyIri = self::WS_NS.'ontology';
        $this->addType($triples, $ontologyIri, self::OWL_NS.'Ontology');
        $this->addLiteral($triples, $ontologyIri, self::OWL_NS.'versionInfo', '3.0.0', self::XSD_NS.'string');
        $this->addLiteral(
            $triples,
            $ontologyIri,
            self::RDFS_NS.'comment',
            'Cakupan ontologi ini hanya mencakup destinasi wisata di Sumatera Utara.',
            null,
            'id',
        );

        foreach (self::CLASSES as $class) {
            $this->addType($triples, self::WS_NS.$class, self::OWL_NS.'Class');
        }

        foreach (self::OBJECT_PROPERTIES as $property => $definition) {
            $propertyIri = self::WS_NS.$property;
            $this->addType($triples, $propertyIri, self::OWL_NS.'ObjectProperty');
            $this->addIri($triples, $propertyIri, self::RDFS_NS.'domain', self::WS_NS.$definition['domain']);
            $this->addIri($triples, $propertyIri, self::RDFS_NS.'range', self::WS_NS.$definition['range']);

            if ($definition['functional'] ?? false) {
                $this->addType($triples, $propertyIri, self::OWL_NS.'FunctionalProperty');
            }

            if ($definition['symmetric'] ?? false) {
                $this->addType($triples, $propertyIri, self::OWL_NS.'SymmetricProperty');
            }
        }

        foreach (self::DATA_PROPERTIES as $property => $definition) {
            $propertyIri = self::WS_NS.$property;
            $this->addType($triples, $propertyIri, self::OWL_NS.'DatatypeProperty');
            $this->addIri($triples, $propertyIri, self::RDFS_NS.'domain', self::WS_NS.$definition['domain']);
            $this->addIri($triples, $propertyIri, self::RDFS_NS.'range', $this->rangeIri($definition['range']));

            if ($definition['functional'] ?? false) {
                $this->addType($triples, $propertyIri, self::OWL_NS.'FunctionalProperty');
            }
        }

        $this->addLiteral(
            $triples,
            self::WS_NS.'ratingPengunjung',
            self::RDFS_NS.'comment',
            'Desimal dari 0 sampai 5; gunakan "'.$unknownLabel.'" bila belum ada rating.',
            null,
            'id',
        );
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @param  array<string, bool>  $resources
     * @param  array<string, string>  $slugOwners
     */
    private function namedResource(
        array &$triples,
        array &$resources,
        string $prefix,
        string $label,
        string $class,
        array &$slugOwners,
    ): string {
        $label = $this->cleanText($label);
        $slug = Str::slug($label);
        if ($slug === '') {
            throw new InvalidArgumentException("Label tidak dapat dijadikan IRI: {$label}");
        }

        $slugKey = $prefix.$slug;
        $normalizedLabel = mb_strtolower($label);
        if (isset($slugOwners[$slugKey]) && $slugOwners[$slugKey] !== $normalizedLabel) {
            throw new InvalidArgumentException("Tabrakan slug untuk label '{$label}' pada {$slugKey}.");
        }
        $slugOwners[$slugKey] = $normalizedLabel;

        $iri = self::DATA_NS.$slugKey;
        if (! isset($resources[$iri])) {
            $resources[$iri] = true;
            $this->addType($triples, $iri, $class);
            $this->addLiteral($triples, $iri, self::RDFS_NS.'label', $label, null, 'id');
        }

        return $iri;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @return array{latitude: float, longitude: float}|null
     */
    private function coordinateTriples(array &$triples, string $subject, array $row): ?array
    {
        $latitude = trim($row['lintang'] ?? '');
        $longitude = trim($row['bujur'] ?? '');
        $coordinate = null;

        if ($this->isDecimal($latitude)) {
            $this->addLiteral($triples, $subject, 'lintang', $latitude, self::XSD_NS.'decimal');
        }
        if ($this->isDecimal($longitude)) {
            $this->addLiteral($triples, $subject, 'bujur', $longitude, self::XSD_NS.'decimal');
        }

        if ($this->isDecimal($latitude) && $this->isDecimal($longitude)) {
            $coordinate = ['latitude' => (float) $latitude, 'longitude' => (float) $longitude];
        }

        return $coordinate;
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @param  array<string, string>  $row
     */
    private function migrateMedia(
        array &$triples,
        string $destinationIri,
        string $id,
        array $row,
        string $unknownLabel,
    ): void {
        $mediaValues = $this->splitValues($row['memilikiMedia'] ?? '', $unknownLabel);
        foreach ($mediaValues as $mediaUrl) {
            $mediaIri = self::DATA_NS.'media-'.$id;
            $this->addType($triples, $mediaIri, 'Media');
            $this->addIri($triples, $destinationIri, 'memilikiMedia', $mediaIri);
            $this->addLiteral($triples, $mediaIri, 'urlMedia', $mediaUrl, self::XSD_NS.'anyURI');
        }
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @param  array<string, string>  $row
     */
    private function migrateContact(
        array &$triples,
        string $destinationIri,
        string $id,
        array $row,
        string $unknownLabel,
    ): void {
        $contactIri = self::DATA_NS.'kontak-'.$id;
        $this->addType($triples, $contactIri, 'KontakResmi');
        $this->addIri($triples, $destinationIri, 'memilikiKontak', $contactIri);

        foreach ([
            'noHpResmi' => 'nomorHp',
            'emailResmi' => 'email',
            'instagramResmi' => 'urlInstagram',
        ] as $sourceProperty => $targetProperty) {
            $value = $this->cleanText($row[$sourceProperty] ?? '');
            $value = $value === '' || $value === $unknownLabel ? $unknownLabel : $value;
            $this->addLiteral($triples, $contactIri, $targetProperty, $value, self::XSD_NS.'string');
        }
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @param  array<string, string>  $row
     */
    private function migrateSchedule(
        array &$triples,
        string $destinationIri,
        string $id,
        array $row,
        string $unknownLabel,
    ): void {
        $scheduleIri = self::DATA_NS.'jadwal-'.$id;
        $this->addType($triples, $scheduleIri, 'JadwalOperasional');
        $this->addIri($triples, $destinationIri, 'memilikiJadwal', $scheduleIri);

        $hours = $this->cleanText($row['jamOperasional'] ?? '');
        $description = $hours === '' || $hours === $unknownLabel ? $unknownLabel : $hours;
        $this->addLiteral($triples, $scheduleIri, 'keteranganJadwal', $description, self::XSD_NS.'string');

        if ($description === $unknownLabel) {
            return;
        }

        if (preg_match('/\b(?:selalu\s+buka|24\s*jam)\b/iu', $hours) === 1) {
            $this->addLiteral($triples, $scheduleIri, 'bukaSepanjangWaktu', 'true', self::XSD_NS.'boolean');

            return;
        }

        $times = $this->timeRange($hours);
        if ($times === null) {
            return;
        }

        $this->addLiteral($triples, $scheduleIri, 'jamBuka', $times[0], self::XSD_NS.'time');
        $this->addLiteral($triples, $scheduleIri, 'jamTutup', $times[1], self::XSD_NS.'time');
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @param  array<string, string>  $row
     */
    private function migratePrice(
        array &$triples,
        string $destinationIri,
        string $id,
        array $row,
        string $unknownLabel,
    ): void {
        $tariffIri = self::DATA_NS.'tarif-'.$id;
        $this->addType($triples, $tariffIri, 'TarifMasuk');
        $this->addIri($triples, $destinationIri, 'memilikiTarif', $tariffIri);

        $prices = $this->splitValues($row['hargaMasuk'] ?? '', $unknownLabel);
        $hasPrice = false;
        $hasFreePrice = false;

        foreach ($prices as $price) {
            if (mb_strtolower($price) === 'gratis') {
                $this->addLiteral($triples, $tariffIri, 'hargaMasuk', '0', self::XSD_NS.'decimal');
                $hasPrice = true;
                $hasFreePrice = true;

                continue;
            }

            if (! $this->isDecimal($price)) {
                continue;
            }

            $this->addLiteral($triples, $tariffIri, 'hargaMasuk', $price, self::XSD_NS.'decimal');
            $hasPrice = true;
            $hasFreePrice = $hasFreePrice || (float) $price === 0.0;
        }

        if (! $hasPrice) {
            $this->addLiteral($triples, $tariffIri, 'keteranganTarif', $unknownLabel, self::XSD_NS.'string');
        } elseif ($hasFreePrice) {
            $this->addLiteral($triples, $tariffIri, 'keteranganTarif', 'Gratis', self::XSD_NS.'string');
        }
    }

    /**
     * @param  array<string, array{iri: string, row: array<string, string>, coordinates?: array{latitude: float, longitude: float}}>  $destinations
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     */
    private function addNearbyRelations(
        array &$triples,
        array $destinations,
        float $radiusKm,
        int $maxNeighbors,
    ): void {
        $candidates = [];
        $ids = array_keys($destinations);
        sort($ids, SORT_STRING);

        for ($leftIndex = 0, $count = count($ids); $leftIndex < $count; $leftIndex++) {
            $leftId = $ids[$leftIndex];
            $left = $destinations[$leftId]['coordinates'] ?? null;
            if ($left === null || ! $this->isWithinNorthSumatra($left['latitude'], $left['longitude'])) {
                continue;
            }

            for ($rightIndex = $leftIndex + 1; $rightIndex < $count; $rightIndex++) {
                $rightId = $ids[$rightIndex];
                $right = $destinations[$rightId]['coordinates'] ?? null;
                if ($right === null || ! $this->isWithinNorthSumatra($right['latitude'], $right['longitude'])) {
                    continue;
                }

                $distance = $this->haversineKm($left['latitude'], $left['longitude'], $right['latitude'], $right['longitude']);
                if ($distance <= $radiusKm) {
                    $candidates[] = ['left' => $leftId, 'right' => $rightId, 'distance' => $distance];
                }
            }
        }

        usort($candidates, static fn (array $left, array $right): int => [
            $left['distance'],
            $left['left'],
            $left['right'],
        ] <=> [
            $right['distance'],
            $right['left'],
            $right['right'],
        ]);

        $degree = [];
        foreach ($candidates as $candidate) {
            $leftId = $candidate['left'];
            $rightId = $candidate['right'];
            if (($degree[$leftId] ?? 0) >= $maxNeighbors || ($degree[$rightId] ?? 0) >= $maxNeighbors) {
                continue;
            }

            $leftIri = $destinations[$leftId]['iri'];
            $rightIri = $destinations[$rightId]['iri'];
            $this->addIri($triples, $leftIri, 'dekatDengan', $rightIri);
            $this->addIri($triples, $rightIri, 'dekatDengan', $leftIri);
            $degree[$leftId] = ($degree[$leftId] ?? 0) + 1;
            $degree[$rightId] = ($degree[$rightId] ?? 0) + 1;
        }
    }

    /**
     * @param  array<string, string>  $regionIrisByQid
     * @param  list<array{0: string, 1: string}>  $boundaries
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     */
    private function addBoundaryRelations(array &$triples, array $regionIrisByQid, array $boundaries): void
    {
        foreach ($boundaries as [$leftQid, $rightQid]) {
            if (! isset($regionIrisByQid[$leftQid], $regionIrisByQid[$rightQid]) || $leftQid === $rightQid) {
                continue;
            }

            $leftIri = $regionIrisByQid[$leftQid];
            $rightIri = $regionIrisByQid[$rightQid];
            $this->addIri($triples, $leftIri, 'berbatasanDengan', $rightIri);
            $this->addIri($triples, $rightIri, 'berbatasanDengan', $leftIri);
        }
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @param  list<array<string, string>>  $rows
     * @return array<string, mixed>
     */
    private function collectAnomalies(array $rows, string $unknownLabel): array
    {
        $nameGroups = [];
        $regionMismatches = [];
        $outsideCoordinates = [];
        $negativePrices = [];
        $invalidRatings = [];
        $unparsedHours = [];
        $unstableMedia = [
            'encrypted-tbn0.gstatic.com' => [],
            'pinimg.com' => [],
        ];

        foreach ($rows as $row) {
            $id = $row['idDestinasi'] ?? '(tanpa ID)';
            $name = $this->cleanText($row['namaDestinasi'] ?? '');
            if ($name !== '') {
                $nameGroups[mb_strtolower($name)][] = ['id' => $id, 'name' => $name];
            }

            $region = $this->cleanText($row['beradaDi'] ?? '');
            $regionName = preg_replace('/^(Kabupaten|Kota)\s+/iu', '', $region) ?? $region;
            $address = $this->cleanText($row['alamat'] ?? '');
            if ($regionName !== '' && $address !== '' && ! str_contains(mb_strtolower($address), mb_strtolower($regionName))) {
                $regionMismatches[] = ['id' => $id, 'name' => $name, 'region' => $region, 'address' => $address];
            }

            $latitude = $row['lintang'] ?? '';
            $longitude = $row['bujur'] ?? '';
            if ($this->isDecimal($latitude) && $this->isDecimal($longitude)
                && ! $this->isWithinNorthSumatra((float) $latitude, (float) $longitude)) {
                $outsideCoordinates[] = ['id' => $id, 'name' => $name, 'latitude' => $latitude, 'longitude' => $longitude];
            }

            foreach ($this->splitValues($row['hargaMasuk'] ?? '', $unknownLabel) as $price) {
                if ($this->isDecimal($price) && (float) $price < 0) {
                    $negativePrices[] = ['id' => $id, 'name' => $name, 'price' => $price];
                }
            }

            foreach ($this->splitValues($row['ratingPengunjung'] ?? '', $unknownLabel) as $rating) {
                if ($rating !== $unknownLabel && $this->isDecimal($rating) && ((float) $rating < 0 || (float) $rating > 5)) {
                    $invalidRatings[] = ['id' => $id, 'name' => $name, 'rating' => $rating];
                }
            }

            $hours = $this->cleanText($row['jamOperasional'] ?? '');
            if ($hours !== '' && $hours !== $unknownLabel && ! $this->isAlwaysOpen($hours) && $this->timeRange($hours) === null) {
                $unparsedHours[] = ['id' => $id, 'name' => $name, 'hours' => $hours];
            }

            foreach ($this->splitValues($row['memilikiMedia'] ?? '', $unknownLabel) as $url) {
                foreach (array_keys($unstableMedia) as $host) {
                    if (str_contains(mb_strtolower($url), $host)) {
                        $unstableMedia[$host][] = ['id' => $id, 'name' => $name, 'url' => $url];
                    }
                }
            }
        }

        $duplicates = array_values(array_filter(
            $nameGroups,
            static fn (array $items): bool => count($items) > 1,
        ));

        return [
            'address_region_mismatches' => $regionMismatches,
            'coordinates_outside_bounds' => $outsideCoordinates,
            'duplicate_names' => $duplicates,
            'negative_prices' => $negativePrices,
            'ratings_outside_range' => $invalidRatings,
            'unparsed_hours' => $unparsedHours,
            'unstable_media' => $unstableMedia,
        ];
    }

    /**
     * @param  array<string, mixed>  $boundarySource
     * @return array<string, array{qid: string, type: string}>
     */
    private function validatedRegions(array $boundarySource): array
    {
        $regions = $boundarySource['regions'] ?? null;
        if (! is_array($regions) || $regions === []) {
            throw new InvalidArgumentException('Snapshot batas wilayah tidak berisi daftar wilayah.');
        }

        $validated = [];
        $qids = [];
        foreach ($regions as $name => $region) {
            if (! is_string($name) || ! is_array($region)
                || ! is_string($region['qid'] ?? null)
                || ! is_string($region['type'] ?? null)
                || preg_match('/^Q\d+$/', $region['qid']) !== 1
                || ! in_array($region['type'], ['Kabupaten', 'Kota'], true)) {
                throw new InvalidArgumentException('Entri wilayah pada snapshot Wikidata tidak valid.');
            }

            $cleanName = $this->cleanText($name);
            if ($cleanName === '' || isset($qids[$region['qid']])) {
                throw new InvalidArgumentException("IRI/QID wilayah duplikat atau kosong: {$name}.");
            }

            $validated[$cleanName] = ['qid' => $region['qid'], 'type' => $region['type']];
            $qids[$region['qid']] = true;
        }

        return $validated;
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     */
    private function addText(array &$triples, string $subject, string $property, string $value, string $unknownLabel): void
    {
        $value = $this->cleanText($value);
        if ($value !== '' && $value !== $unknownLabel) {
            $this->addLiteral($triples, $subject, $property, $value, null, 'id');
        }
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     */
    private function addType(array &$triples, string $subject, string $classOrType): void
    {
        $typeIri = str_contains($classOrType, '#') ? $classOrType : self::WS_NS.$classOrType;
        $this->addIri($triples, $subject, self::RDF_NS.'type', $typeIri);
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     */
    private function addIri(array &$triples, string $subject, string $predicate, string $object): void
    {
        $predicateIri = str_contains($predicate, '#') ? $predicate : self::WS_NS.$predicate;
        $triples[] = [
            'subject' => $subject,
            'predicate' => $predicateIri,
            'object' => ['kind' => 'iri', 'value' => $object, 'datatype' => null, 'language' => null],
        ];
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     */
    private function addLiteral(
        array &$triples,
        string $subject,
        string $predicate,
        string $value,
        ?string $datatype = null,
        ?string $language = null,
    ): void {
        $predicateIri = str_contains($predicate, '#') ? $predicate : self::WS_NS.$predicate;
        $triples[] = [
            'subject' => $subject,
            'predicate' => $predicateIri,
            'object' => [
                'kind' => 'literal',
                'value' => $value,
                'datatype' => $language === null ? ($datatype ?? self::XSD_NS.'string') : self::RDF_NS.'langString',
                'language' => $language,
            ],
        ];
    }

    private function rangeIri(string $range): string
    {
        return match (true) {
            str_starts_with($range, 'xsd:') => self::XSD_NS.substr($range, 4),
            str_starts_with($range, 'rdf:') => self::RDF_NS.substr($range, 4),
            str_starts_with($range, 'rdfs:') => self::RDFS_NS.substr($range, 5),
            default => self::WS_NS.$range,
        };
    }

    /**
     * @return list<string>
     */
    private function splitValues(string $value, string $unknownLabel, bool $splitCommas = false): array
    {
        $segments = preg_split('/\s*\|\s*/u', trim($value)) ?: [];
        $values = [];
        $seen = [];

        foreach ($segments as $segment) {
            $parts = $splitCommas ? explode(',', $segment) : [$segment];
            foreach ($parts as $part) {
                $part = $this->cleanText($part);
                if ($part === '' || $part === $unknownLabel) {
                    continue;
                }

                $key = mb_strtolower($part);
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $values[] = $part;
            }
        }

        return $values;
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/[ \t]+/u', ' ', $value) ?? $value);
    }

    private function isDecimal(string $value): bool
    {
        return preg_match('/^[+-]?\d+(?:\.\d+)?$/', trim($value)) === 1;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function timeRange(string $hours): ?array
    {
        if (preg_match_all('/(?<!\d)(\d{1,2})[.:](\d{2})\s*[-–—]\s*(\d{1,2})[.:](\d{2})(?!\d)/u', $hours, $matches, PREG_SET_ORDER) !== 1) {
            return null;
        }

        $startHour = (int) $matches[0][1];
        $startMinute = (int) $matches[0][2];
        $endHour = (int) $matches[0][3];
        $endMinute = (int) $matches[0][4];

        if ($startHour > 23 || $endHour > 23 || $startMinute > 59 || $endMinute > 59
            || ($endHour * 60 + $endMinute) <= ($startHour * 60 + $startMinute)) {
            return null;
        }

        return [
            sprintf('%02d:%02d:00', $startHour, $startMinute),
            sprintf('%02d:%02d:00', $endHour, $endMinute),
        ];
    }

    private function isAlwaysOpen(string $hours): bool
    {
        return preg_match('/\b(?:selalu\s+buka|24\s*jam)\b/iu', $hours) === 1;
    }

    private function isWithinNorthSumatra(float $latitude, float $longitude): bool
    {
        return $latitude >= 0.5 && $latitude <= 4.5 && $longitude >= 97.0 && $longitude <= 100.5;
    }

    private function haversineKm(float $latitude1, float $longitude1, float $latitude2, float $longitude2): float
    {
        $earthRadiusKm = 6371.0088;
        $latitudeDelta = deg2rad($latitude2 - $latitude1);
        $longitudeDelta = deg2rad($longitude2 - $longitude1);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * asin(min(1, sqrt($a)));
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @return list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>
     */
    private function uniqueTriples(array $triples): array
    {
        $unique = [];
        foreach ($triples as $triple) {
            $key = json_encode($triple, JSON_THROW_ON_ERROR);
            $unique[$key] = $triple;
        }

        return array_values($unique);
    }
}
