<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Ontology\OntologyMigrator;
use App\Services\Ontology\OntologyValidator;
use App\Services\Ontology\TurtleParser;
use App\Services\Ontology\TurtleWriter;
use PHPUnit\Framework\TestCase;

class OntologyMigratorTest extends TestCase
{
    public function test_it_migrates_the_first_destination_after_a_tbox_comment_block(): void
    {
        $parser = new TurtleParser;
        $rows = $parser->parse($this->firstDestinationFixture());

        $this->assertSame(['ws-001'], array_column($rows, 'idDestinasi'));

        $boundarySource = $this->boundarySource();
        $boundarySource['regions']['Kabupaten Asahan'] = ['qid' => 'Q3', 'type' => 'Kabupaten'];
        $result = (new OntologyMigrator)->migrate($rows, $boundarySource, '-', 15.0, 3);

        $this->assertSame(1, $result['stats']['destinations']);
        $this->assertSame(
            [
                'https://www.wikidata.org/entity/Q1',
                'https://www.wikidata.org/entity/Q2',
                'https://www.wikidata.org/entity/Q3',
            ],
            array_map(static fn (array $triple): string => $triple['object']['value'], array_values(array_filter(
                $result['triples'],
                static fn (array $triple): bool => $triple['predicate'] === OntologyMigrator::OWL_NS.'sameAs',
            ))),
        );
        $this->assertSame(
            ['ws-001'],
            $this->literalValues(
                $result['triples'],
                OntologyMigrator::DATA_NS.'ws-001',
                'idDestinasi',
            ),
        );
    }

    public function test_it_migrates_sentinels_prices_and_deduplicated_multi_values(): void
    {
        $unknownLabel = 'belum diketahui';
        $rows = (new TurtleParser)->parse(str_replace('"-"', '"belum diketahui"', $this->fixtureTurtle()));
        $originalRows = $rows;
        $migrator = new OntologyMigrator;
        $result = $migrator->migrate($rows, $this->boundarySource(), $unknownLabel, 15.0, 3);
        $triples = $result['triples'];
        $writer = new TurtleWriter;
        $turtle = $writer->write($triples);
        $parsedTriples = (new TurtleParser)->parseTriples($turtle);
        $validator = new OntologyValidator;
        $validation = $validator->validate(
            $parsedTriples,
            $unknownLabel,
            regionQidsByName: [
                'Kabupaten Samosir' => 'Q1',
                'Kabupaten Toba' => 'Q2',
            ],
        );

        $unknownTariff = OntologyMigrator::DATA_NS.'tarif-ws-005';
        $freeTariff = OntologyMigrator::DATA_NS.'tarif-ws-006';
        $toiletIri = OntologyMigrator::DATA_NS.'fasilitas-toilet';
        $destinationIri = OntologyMigrator::DATA_NS.'ws-005';
        $keywordValues = $this->literalValues($parsedTriples, $destinationIri, 'kataKunci');

        $this->assertSame($originalRows, $rows);
        $this->assertSame(2, $this->countType($parsedTriples, 'Fasilitas'));
        $this->assertCount(2, array_filter(
            $parsedTriples,
            static fn (array $triple): bool => $triple['predicate'] === OntologyMigrator::WS_NS.'menyediakanFasilitas'
                && $triple['object']['value'] === $toiletIri,
        ));
        $this->assertContains('danau', $keywordValues);
        $this->assertContains('air terjun', $keywordValues);
        $this->assertSame(2, count($keywordValues));
        $this->assertSame([], array_values(array_filter(
            $parsedTriples,
            static fn (array $triple): bool => $triple['subject'] === $unknownTariff
                && $triple['predicate'] === OntologyMigrator::WS_NS.'hargaMasuk',
        )));
        $this->assertSame(
            [['kind' => 'literal', 'value' => $unknownLabel, 'datatype' => OntologyMigrator::XSD_NS.'string', 'language' => null]],
            array_values(array_map(
                static fn (array $triple): array => $triple['object'],
                array_filter(
                    $parsedTriples,
                    static fn (array $triple): bool => $triple['subject'] === $unknownTariff
                        && $triple['predicate'] === OntologyMigrator::WS_NS.'keteranganTarif',
                ),
            )),
        );
        $this->assertSame(
            ['0'],
            $this->literalValues($parsedTriples, $freeTariff, 'hargaMasuk'),
        );
        $this->assertSame(['Gratis'], $this->literalValues($parsedTriples, $freeTariff, 'keteranganTarif'));
        $this->assertSame([], $validation['errors']);
        $this->assertSame(2, $validation['destination_count']);
        $this->assertSame([
            'rated' => 1,
            'unrated' => 1,
            'average' => 4.5,
        ], $validation['rating_stats']);

        $comparison = $validator->validate(
            $parsedTriples,
            $unknownLabel,
            ['ws-005', 'ws-006', 'ws-007'],
        );
        $this->assertSame([
            'destination_count' => 3,
            'missing_ids' => ['ws-007'],
            'extra_ids' => [],
        ], $comparison['source_comparison']);
        $this->assertContains('Jumlah destinasi v2 dan v3 berbeda: 3 != 2.', $comparison['errors']);
        $this->assertContains('Destinasi sumber v2 tidak ditemukan di v3: ws-007.', $comparison['errors']);
    }

    public function test_validator_rejects_destination_same_as_and_non_snapshot_region_qids(): void
    {
        $rows = (new TurtleParser)->parse(str_replace('"-"', '"-"', $this->fixtureTurtle()));
        $result = (new OntologyMigrator)->migrate($rows, $this->boundarySource(), '-', 15.0, 3);
        $validator = new OntologyValidator;
        $expectedRegions = [
            'Kabupaten Samosir' => 'Q1',
            'Kabupaten Toba' => 'Q2',
        ];
        $validTriples = $result['triples'];
        $destinationIri = OntologyMigrator::DATA_NS.'ws-005';
        $validTriples[] = [
            'subject' => $destinationIri,
            'predicate' => OntologyMigrator::OWL_NS.'sameAs',
            'object' => [
                'kind' => 'iri',
                'value' => 'https://www.wikidata.org/entity/Q999',
                'datatype' => null,
                'language' => null,
            ],
        ];

        $invalid = $validator->validate(
            $validTriples,
            '-',
            regionQidsByName: $expectedRegions,
        );

        $this->assertContains("{$destinationIri}: owl:sameAs destinasi tidak diizinkan.", $invalid['errors']);

        $wrongRegion = array_values(array_filter(
            $result['triples'],
            static fn (array $triple): bool => ! (
                $triple['subject'] === OntologyMigrator::DATA_NS.'wilayah-kabupaten-toba'
                && $triple['predicate'] === OntologyMigrator::OWL_NS.'sameAs'
            ),
        ));
        $wrongRegion[] = [
            'subject' => OntologyMigrator::DATA_NS.'wilayah-kabupaten-toba',
            'predicate' => OntologyMigrator::OWL_NS.'sameAs',
            'object' => [
                'kind' => 'iri',
                'value' => 'https://www.wikidata.org/entity/Q999',
                'datatype' => null,
                'language' => null,
            ],
        ];
        $invalidMapping = $validator->validate(
            $wrongRegion,
            '-',
            regionQidsByName: $expectedRegions,
        );

        $this->assertContains(
            'Wilayah Kabupaten Toba: owl:sameAs harus menunjuk tepat ke https://www.wikidata.org/entity/Q2.',
            $invalidMapping['errors'],
        );
    }

    private function firstDestinationFixture(): string
    {
        return <<<'TTL'
@prefix ws: <http://pariwisatasumut.org/ontology/v2#> .
@prefix data: <http://pariwisatasumut.org/resource/> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ws:ratingPengunjung a owl:DatatypeProperty ;
    rdfs:domain ws:DestinasiWisata ;
    rdfs:range xsd:decimal .

#################################################################
# DESTINASI WISATA
#################################################################

data:ws-001 a ws:DestinasiWisata ;
    ws:idDestinasi "ws-001"^^xsd:string ;
    ws:namaDestinasi "Air Terjun Bolon"@id ;
    ws:jenisDestinasi "Alam"@id ;
    ws:beradaDi "Kabupaten Asahan"@id ;
    ws:ratingPengunjung "4.4"^^xsd:decimal .
TTL;
    }

    private function fixtureTurtle(): string
    {
        return <<<'TTL'
@prefix ws: <http://pariwisatasumut.org/ontology/v2#> .
@prefix data: <http://pariwisatasumut.org/resource/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

data:ws-005 a ws:DestinasiWisata ;
    ws:idDestinasi "ws-005" ;
    ws:namaDestinasi "Destinasi Satu"@id ;
    ws:namaAlternatif "Satu"@id ;
    ws:deskripsiSingkat "Deskripsi singkat"@id ;
    ws:deskripsiLengkap "Deskripsi lengkap"@id ;
    ws:jenisDestinasi "Alam"@id ;
    ws:kataKunci "danau, air terjun, danau"@id ;
    ws:beradaDi "Kabupaten Samosir"@id ;
    ws:alamat "Desa Samosir"@id ;
    ws:lintang "2.5"^^xsd:decimal ;
    ws:bujur "98.7"^^xsd:decimal ;
    ws:petunjukLokasi "Petunjuk"@id ;
    ws:jamOperasional "Selalu Buka"@id ;
    ws:hargaMasuk "-" ;
    ws:noHpResmi "-" ;
    ws:emailResmi "kontak@example.test" ;
    ws:instagramResmi "-" ;
    ws:memilikiFasilitas " Toilet "@id, "toilet"@id, "Tempat Parkir"@id ;
    ws:dapatMelakukan "Berenang"@id ;
    ws:memilikiMedia "https://example.test/1.jpg"^^xsd:anyURI ;
    ws:ratingPengunjung "-" .

data:ws-006 a ws:DestinasiWisata ;
    ws:idDestinasi "ws-006" ;
    ws:namaDestinasi "Destinasi Dua"@id ;
    ws:deskripsiSingkat "Ringkasan"@id ;
    ws:deskripsiLengkap "Keterangan"@id ;
    ws:jenisDestinasi "Bahari"@id ;
    ws:kataKunci "pantai"@id ;
    ws:beradaDi "Kabupaten Toba"@id ;
    ws:alamat "Desa Toba"@id ;
    ws:lintang "2.51"^^xsd:decimal ;
    ws:bujur "98.71"^^xsd:decimal ;
    ws:petunjukLokasi "Petunjuk"@id ;
    ws:jamOperasional "Setiap Hari 08.00-17.00"@id ;
    ws:hargaMasuk "0"^^xsd:decimal ;
    ws:noHpResmi "08123456789" ;
    ws:emailResmi "-" ;
    ws:instagramResmi "https://instagram.com/contoh" ;
    ws:memilikiFasilitas "Toilet"@id ;
    ws:dapatMelakukan "Berfoto"@id ;
    ws:memilikiMedia "https://example.test/2.jpg"^^xsd:anyURI ;
    ws:ratingPengunjung "4.5"^^xsd:decimal .
TTL;
    }

    /**
     * @return array{
     *     source: string,
     *     retrieved_at: string,
     *     province_qid: string,
     *     regions: array<string, array{qid: string, type: string}>,
     *     boundaries: list<array{0: string, 1: string}>
     * }
     */
    private function boundarySource(): array
    {
        return [
            'source' => 'fixture',
            'retrieved_at' => '2026-01-01',
            'province_qid' => 'Q2140',
            'regions' => [
                'Kabupaten Samosir' => ['qid' => 'Q1', 'type' => 'Kabupaten'],
                'Kabupaten Toba' => ['qid' => 'Q2', 'type' => 'Kabupaten'],
            ],
            'boundaries' => [['Q1', 'Q2']],
        ];
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     * @return list<string>
     */
    private function literalValues(array $triples, string $subject, string $property): array
    {
        $values = [];
        foreach ($triples as $triple) {
            if ($triple['subject'] === $subject
                && $triple['predicate'] === OntologyMigrator::WS_NS.$property
                && $triple['object']['kind'] === 'literal') {
                $values[] = $triple['object']['value'];
            }
        }

        return $values;
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: array{kind: string, value: string, datatype: ?string, language: ?string}}>  $triples
     */
    private function countType(array $triples, string $class): int
    {
        $typeIri = OntologyMigrator::WS_NS.$class;
        $subjects = [];
        foreach ($triples as $triple) {
            if ($triple['predicate'] === OntologyMigrator::RDF_NS.'type'
                && $triple['object']['kind'] === 'iri'
                && $triple['object']['value'] === $typeIri) {
                $subjects[$triple['subject']] = true;
            }
        }

        return count($subjects);
    }
}
