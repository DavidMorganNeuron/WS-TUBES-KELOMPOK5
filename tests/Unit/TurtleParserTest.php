<?php

namespace Tests\Unit;

use App\Data\Destination;
use App\Services\Ontology\OntologyGraphMapper;
use App\Services\Ontology\TurtleParser;
use Tests\TestCase;

class TurtleParserTest extends TestCase
{
    private TurtleParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new TurtleParser;
    }

    public function test_it_parses_destinations_from_the_local_turtle_file(): void
    {
        $rows = $this->parser->parseFile('database/ontology/wisata_sumut_v2.ttl');

        $this->assertNotEmpty($rows);
        $this->assertSame('ws-001', $rows[0]['idDestinasi']);
        $this->assertSame('Air Terjun Bolon', $rows[0]['namaDestinasi']);
    }

    public function test_local_turtle_dataset_has_unique_ids_and_required_fields(): void
    {
        $rows = $this->parser->parseFile('database/ontology/wisata_sumut_v2.ttl');
        $ids = array_column($rows, 'idDestinasi');

        $this->assertNotEmpty($rows);
        $this->assertCount(count(array_unique($ids)), $ids);

        foreach ($rows as $row) {
            $this->assertMatchesRegularExpression('/^ws-\d{3}$/', $row['idDestinasi']);
            $this->assertNotSame('', $row['namaDestinasi']);
        }
    }

    public function test_it_parses_typed_literals_and_coordinates(): void
    {
        $rows = $this->parser->parseFile('database/ontology/wisata_sumut_v2.ttl');
        $destination = Destination::fromArray($rows[0]);

        $this->assertStringContainsString('Turunan Bolon', $destination->fullDescription);
        $this->assertEqualsWithDelta(2.713679166994072, $destination->latitude, 0.0000001);
        $this->assertEqualsWithDelta(99.14826959818836, $destination->longitude, 0.0000001);
        $this->assertSame(4.4, $destination->rating);
        $this->assertSame('Alam', $destination->primaryType());
    }

    public function test_it_splits_multi_value_properties_and_normalizes_empty_fields(): void
    {
        $rows = $this->parser->parseFile('database/ontology/wisata_sumut_v2.ttl');
        $pelaruga = Destination::fromArray(collect($rows)->firstWhere('idDestinasi', 'ws-148'));

        $this->assertSame(['Alam', 'Kuliner'], $pelaruga->types);
        $this->assertSame(['10000'], $pelaruga->prices);
        $this->assertSame(10000.0, $pelaruga->lowestPrice());
        $this->assertContains('Tempat Parkir', $pelaruga->facilities);
        $this->assertSame('', $pelaruga->email);
        $this->assertSame(4.6, $pelaruga->rating);
    }

    public function test_it_adds_a_width_hint_to_wikimedia_file_paths(): void
    {
        $destination = Destination::fromArray([
            'idDestinasi' => 'ws-test',
            'namaDestinasi' => 'Contoh',
            'jenisDestinasi' => 'Alam',
            'memilikiMedia' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Contoh.jpg',
        ]);

        $this->assertSame(
            'https://commons.wikimedia.org/wiki/Special:FilePath/Contoh.jpg?width=600',
            $destination->imageUrl(),
        );
        $this->assertSame(
            'https://commons.wikimedia.org/wiki/Special:FilePath/Contoh.jpg?width=900',
            $destination->imageUrl(900),
        );
    }

    public function test_it_returns_image_attribution_only_when_media_matches_its_source(): void
    {
        $matchingDestination = Destination::fromArray([
            'idDestinasi' => 'ws-148',
            'namaDestinasi' => 'Contoh',
            'memilikiMedia' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Air_terjun_Pelaruga_2.jpg',
        ]);
        $mismatchedDestination = Destination::fromArray([
            'idDestinasi' => 'ws-148',
            'namaDestinasi' => 'Contoh',
            'memilikiMedia' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Kebun Teh Sidamanik, Kabupaten Simalungun.jpg',
        ]);

        $this->assertSame(
            'https://commons.wikimedia.org/wiki/File:Air_terjun_Pelaruga_2.jpg',
            $matchingDestination->imageAttribution()['source_url'] ?? null,
        );
        $this->assertNull($mismatchedDestination->imageAttribution());
    }

    public function test_it_labels_zero_prices_as_free(): void
    {
        $destination = Destination::fromArray([
            'idDestinasi' => 'ws-test',
            'namaDestinasi' => 'Contoh',
            'jenisDestinasi' => 'Alam',
            'hargaMasuk' => '0 | 15000',
        ]);

        $this->assertSame(0.0, $destination->lowestPrice());
        $this->assertSame('Gratis', $destination->priceLabel());
        $this->assertSame(['Gratis', 'Rp 15.000'], $destination->priceLabels());
    }

    public function test_it_uses_configured_unknown_labels_for_missing_rating_and_price(): void
    {
        $destination = Destination::fromArray([
            'idDestinasi' => 'ws-test',
            'namaDestinasi' => 'Contoh',
        ]);

        $this->assertSame(config('ontology.unknown_label'), $destination->ratingLabel());
        $this->assertSame(config('ontology.unknown_label'), $destination->priceLabel());
        $this->assertSame(config('ontology.unknown_label'), $destination->toMapMarker()['rating_label']);
        $this->assertSame(config('ontology.unknown_label'), $destination->toMapMarker()['price']);
    }

    public function test_v3_ttl_schedule_coverage_matches_generated_dataset(): void
    {
        $triples = $this->parser->parseTriplesFile('database/ontology/wisata_sumut_v3.ttl');
        $rows = app(OntologyGraphMapper::class)->destinations($triples);
        $destinations = collect(array_map(
            static fn (array $row): Destination => Destination::fromArray($row),
            $rows,
        ));

        $this->assertCount(304, $destinations);
        $this->assertSame(282, $destinations->where('openingHours', '!=', '')->count());
        $this->assertSame(22, $destinations->where('openingHours', '')->count());
    }

    public function test_it_handles_a_minimal_inline_turtle_document(): void
    {
        $turtle = <<<'TTL'
@prefix ws: <http://pariwisatasumut.org/ontology/v2#> .
@prefix data: <http://pariwisatasumut.org/resource/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

data:DST-9001 a ws:DestinasiWisata ;
    ws:idDestinasi "DST-9001" ;
    ws:namaDestinasi "Contoh \"Destinasi\"" ;
    ws:jenisDestinasi "Alam", "Bahari" ;
    ws:kataKunci "gunung", "air terjun" ;
    ws:hargaMasuk "" ;
    ws:lintang "1.234"^^xsd:decimal ;
    ws:ratingPengunjung "4.5"^^xsd:decimal .
TTL;

        $rows = $this->parser->parse($turtle);
        $destination = Destination::fromArray($rows[0]);

        $this->assertCount(1, $rows);
        $this->assertSame('Contoh "Destinasi"', $destination->name);
        $this->assertSame(['Alam', 'Bahari'], $destination->types);
        $this->assertSame(['gunung', 'air terjun'], $destination->keywords);
        $this->assertSame(1.234, $destination->latitude);
    }

    public function test_it_parses_grouped_fuseki_turtle_statements_and_decimal_terms(): void
    {
        $turtle = <<<'TTL'
PREFIX data: <http://pariwisatasumut.org/resource/>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX ws: <http://pariwisatasumut.org/ontology/v2#>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

data:ws-002 rdf:type ws:DestinasiWisata ;
    ws:bujur 98.34891832517512 ;
    ws:kataKunci "Air Terjun", "Danau" ;
    ws:namaDestinasi "Air Terjun Lae Mbilulu"@id .
TTL;

        $triples = $this->parser->parseTriples($turtle);

        $this->assertCount(5, $triples);
        $this->assertSame(
            'http://pariwisatasumut.org/resource/ws-002',
            $triples[0]['subject'],
        );
        $this->assertSame('http://www.w3.org/2001/XMLSchema#decimal', $triples[1]['object']['datatype']);
        $this->assertSame('98.34891832517512', $triples[1]['object']['value']);
        $this->assertSame('Air Terjun Lae Mbilulu', $triples[4]['object']['value']);
        $this->assertSame('id', $triples[4]['object']['language']);
    }
}
