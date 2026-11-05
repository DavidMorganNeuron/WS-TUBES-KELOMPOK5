<?php

namespace Tests\Unit;

use App\Data\Destination;
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

        $this->assertCount(285, $rows);
        $this->assertSame('ws-001', $rows[0]['idDestinasi']);
        $this->assertSame('Air Terjun Bolon', $rows[0]['namaDestinasi']);
    }

    public function test_it_parses_typed_literals_and_coordinates(): void
    {
        $rows = $this->parser->parseFile('database/ontology/wisata_sumut_v2.ttl');
        $destination = Destination::fromArray($rows[0]);

        $this->assertStringContainsString('Air Terjun Bolon', $destination->fullDescription);
        $this->assertSame(2.713693, $destination->latitude);
        $this->assertSame(99.148157, $destination->longitude);
        $this->assertNull($destination->rating);
        $this->assertSame('Alam', $destination->primaryType());
    }

    public function test_it_splits_multi_value_properties_and_normalizes_empty_fields(): void
    {
        $rows = $this->parser->parseFile('database/ontology/wisata_sumut_v2.ttl');
        $pelaruga = Destination::fromArray(collect($rows)->firstWhere('idDestinasi', 'ws-272'));

        $this->assertSame(['Alam', 'Bahari', 'Religi'], $pelaruga->types);
        $this->assertSame(['10000'], $pelaruga->prices);
        $this->assertSame(10000.0, $pelaruga->lowestPrice());
        $this->assertContains('Area Parkir', $pelaruga->facilities);
        $this->assertSame('', $pelaruga->email);
        $this->assertSame(4.5, $pelaruga->rating);
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
    ws:jenisDestinasi "Alam | Bahari" ;
    ws:hargaMasuk "" ;
    ws:lintang "1.234"^^xsd:decimal ;
    ws:ratingPengunjung "4.5"^^xsd:decimal .
TTL;

        $rows = $this->parser->parse($turtle);
        $destination = Destination::fromArray($rows[0]);

        $this->assertCount(1, $rows);
        $this->assertSame('Contoh "Destinasi"', $destination->name);
        $this->assertSame(['Alam', 'Bahari'], $destination->types);
        $this->assertSame(1.234, $destination->latitude);
    }
}
