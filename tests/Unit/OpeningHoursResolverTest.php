<?php

namespace Tests\Unit;

use App\Data\Destination;
use App\Services\Ontology\OntologyGraphMapper;
use App\Services\Ontology\OpeningHoursResolver;
use App\Services\Ontology\SparqlRowMapper;
use App\Services\Ontology\TurtleParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpeningHoursResolverTest extends TestCase
{
    #[DataProvider('resolutionCases')]
    public function test_it_resolves_schedule_text_times_and_always_open_values(
        ?string $description,
        ?string $opening,
        ?string $closing,
        bool|string|null $alwaysOpen,
        ?string $expected,
    ): void {
        $resolver = new OpeningHoursResolver('-');

        $this->assertSame($expected, $resolver->resolve($description, $opening, $closing, $alwaysOpen));
    }

    public static function resolutionCases(): array
    {
        return [
            'description has priority' => [' Setiap Hari 08.00-18.00 ', '09:00:00', '17:00:00', 'true', 'Setiap Hari 08.00-18.00'],
            'description surrounding whitespace is trimmed' => [' Setiap   Hari  08.00-18.00 ', null, null, null, 'Setiap   Hari  08.00-18.00'],
            'valid time seconds are formatted as Indonesian time' => ['-', '08:30:59', '19:15:00', false, '08.30 - 19.15'],
            'valid time without seconds' => [null, '08:30', '19:15', false, '08.30 - 19.15'],
            'invalid hour does not become a schedule' => [null, '24:00', '19:15', false, null],
            'invalid minute does not become a schedule' => [null, '08:60', '19:15', false, null],
            'one time does not become a schedule' => [null, '08:30', null, false, null],
            'always open boolean' => ['-', null, null, true, 'Selalu Buka'],
            'always open string' => ['-', null, null, 'true', 'Selalu Buka'],
            'always open numeric string' => ['-', null, null, '1', 'Selalu Buka'],
            'false is not always open' => ['-', null, null, 'false', null],
            'missing data remains unknown' => [null, null, null, null, null],
        ];
    }

    public function test_schedule_sentinel_is_unknown_even_if_the_display_label_is_custom(): void
    {
        $resolver = new OpeningHoursResolver('Tidak tersedia');

        $this->assertNull($resolver->resolve('-', null, null, false));
    }

    public function test_ttl_and_sparql_schedule_mapping_resolve_the_same_values(): void
    {
        $rows = [
            [
                'idDestinasi' => 'ws-001',
                'namaDestinasi' => 'Teks mengalahkan fallback',
                'keteranganJadwal' => ' Setiap Hari 08.00-18.00 ',
                'jamBuka' => '09:00:00',
                'jamTutup' => '17:00:00',
                'bukaSepanjangWaktu' => 'true',
            ],
            [
                'idDestinasi' => 'ws-002',
                'namaDestinasi' => 'Menggunakan jam',
                'keteranganJadwal' => '-',
                'jamBuka' => '08:00:00',
                'jamTutup' => '19:00:00',
                'bukaSepanjangWaktu' => 'false',
            ],
            [
                'idDestinasi' => 'ws-003',
                'namaDestinasi' => 'Selalu buka',
                'keteranganJadwal' => '-',
                'jamBuka' => null,
                'jamTutup' => null,
                'bukaSepanjangWaktu' => 'true',
            ],
        ];

        $turtle = <<<'TTL'
@prefix data: <http://pariwisatasumut.org/resource/> .
@prefix ws: <http://pariwisatasumut.org/ontology/v2#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

data:ws-001 a ws:DestinasiWisata ;
    ws:idDestinasi "ws-001" ;
    ws:namaDestinasi "Teks mengalahkan fallback" ;
    ws:memilikiJadwal data:jadwal-ws-001 .
data:jadwal-ws-001 a ws:JadwalOperasional ;
    ws:keteranganJadwal " Setiap Hari 08.00-18.00 " ;
    ws:jamBuka "09:00:00"^^xsd:time ;
    ws:jamTutup "17:00:00"^^xsd:time ;
    ws:bukaSepanjangWaktu "true"^^xsd:boolean .
data:ws-002 a ws:DestinasiWisata ;
    ws:idDestinasi "ws-002" ;
    ws:namaDestinasi "Menggunakan jam" ;
    ws:memilikiJadwal data:jadwal-ws-002 .
data:jadwal-ws-002 a ws:JadwalOperasional ;
    ws:keteranganJadwal "-" ;
    ws:jamBuka "08:00:00"^^xsd:time ;
    ws:jamTutup "19:00:00"^^xsd:time ;
    ws:bukaSepanjangWaktu "false"^^xsd:boolean .
data:ws-003 a ws:DestinasiWisata ;
    ws:idDestinasi "ws-003" ;
    ws:namaDestinasi "Selalu buka" ;
    ws:memilikiJadwal data:jadwal-ws-003 .
data:jadwal-ws-003 a ws:JadwalOperasional ;
    ws:keteranganJadwal "-" ;
    ws:bukaSepanjangWaktu "true"^^xsd:boolean .
TTL;
        $resolver = new OpeningHoursResolver('-');
        $triples = (new TurtleParser)->parseTriples($turtle);
        $ttlRows = app(OntologyGraphMapper::class)->destinations($triples);
        $ttlDestinations = collect(array_map(
            static fn (array $row): Destination => Destination::fromArray($row),
            $ttlRows,
        ));
        $sparqlDestinations = (new SparqlRowMapper($resolver))->destinations($rows);

        $this->assertSame(
            $sparqlDestinations->pluck('openingHours')->all(),
            $ttlDestinations->pluck('openingHours')->all(),
        );
        $this->assertSame(
            ['Setiap Hari 08.00-18.00', '08.00 - 19.00', 'Selalu Buka'],
            $sparqlDestinations->pluck('openingHours')->all(),
        );
    }
}
