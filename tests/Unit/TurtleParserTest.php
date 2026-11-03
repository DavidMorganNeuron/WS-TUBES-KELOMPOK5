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
        $rows = $this->parser->parseFile('database/ontology/pariwisata-sumut.ttl');

        $this->assertCount(18, $rows);
        $this->assertSame('DST-0001', $rows[0]['idDestinasi']);
        $this->assertSame('Air Terjun Sipiso-Piso', $rows[0]['namaDestinasi']);
    }

    public function test_it_preserves_multiline_descriptions_and_typed_literals(): void
    {
        $rows = $this->parser->parseFile('database/ontology/pariwisata-sumut.ttl');
        $destination = Destination::fromArray($rows[0]);

        $this->assertStringContainsString("\n\n", $destination->fullDescription);
        $this->assertSame(2.9186, $destination->latitude);
        $this->assertSame(98.5204, $destination->longitude);
        $this->assertSame(4.9, $destination->rating);
        $this->assertSame('Alam', $destination->primaryType());
    }

    public function test_it_splits_multi_value_properties_and_normalizes_empty_fields(): void
    {
        $rows = $this->parser->parseFile('database/ontology/pariwisata-sumut.ttl');
        $sipiso = Destination::fromArray($rows[0]);
        $lauKawar = Destination::fromArray($rows[5]);

        $this->assertSame(
            ['Domestik: Rp 10.000', 'Mancanegara: Rp 50.000', 'Parkir motor: Rp 5.000'],
            $sipiso->prices,
        );
        $this->assertContains('Area parkir', $sipiso->facilities);
        $this->assertSame('', $sipiso->email);
        $this->assertNull($lauKawar->rating);
        $this->assertSame([], $lauKawar->prices);
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
