<?php

namespace App\Services\Ontology;

use InvalidArgumentException;

/**
 * Pemeriksa integritas data di Fuseki: mendeteksi duplikasi subjek, pelanggaran
 * kardinalitas, dan data "sisa" pada graph bernama yang tidak ikut terhapus saat PUT.
 */
final class OntologyInspector
{
    private const NAMESPACE_IRI = 'http://pariwisatasumut.org/ontology/v2#';

    /** Properti yang secara desain hanya boleh punya satu nilai per destinasi. */
    public const SINGLE_VALUED = [
        'idDestinasi', 'namaDestinasi', 'deskripsiSingkat', 'deskripsiLengkap',
        'beradaDi', 'alamat', 'lintang', 'bujur', 'petunjukLokasi', 'jamOperasional',
        'hargaMasuk', 'noHpResmi', 'emailResmi', 'instagramResmi',
        'memilikiMedia', 'ratingPengunjung',
    ];

    private const MULTI_VALUED = [
        'jenisDestinasi', 'namaAlternatif', 'kataKunci', 'memilikiFasilitas', 'dapatMelakukan',
    ];

    public function __construct(private FusekiClient $fuseki) {}

    /**
     * @return list<array<string, string|null>>
     */
    public function graphs(): array
    {
        $rows = $this->fuseki->select(<<<'SPARQL'
SELECT ?g (COUNT(*) AS ?triples)
WHERE { GRAPH ?g { ?s ?p ?o } }
GROUP BY ?g
ORDER BY ?g
SPARQL);

        return array_values(array_filter($rows, static fn (array $row): bool => ($row['g'] ?? '') !== ''));
    }

    /**
     * Semua triple suatu properti untuk satu destinasi, di graph default maupun graph bernama.
     *
     * @return list<array{graph: string, subject: string, value: string}>
     */
    public function valuesOf(string $id, string $property): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1) {
            throw new InvalidArgumentException('Format ID destinasi tidak valid.');
        }

        if (! in_array($property, [...self::SINGLE_VALUED, ...self::MULTI_VALUED], true)) {
            throw new InvalidArgumentException("Properti tidak dikenal: {$property}");
        }

        $namespace = self::NAMESPACE_IRI;

        $rows = $this->fuseki->select(<<<SPARQL
PREFIX ws: <{$namespace}>
SELECT DISTINCT ?g ?s ?o
WHERE {
    { ?s ws:idDestinasi ?id } UNION { GRAPH ?gid { ?s ws:idDestinasi ?id } }
    FILTER(STR(?id) = "{$id}")
    { ?s ws:{$property} ?o } UNION { GRAPH ?g { ?s ws:{$property} ?o } }
}
SPARQL);

        return array_map(static fn (array $row): array => [
            'graph' => ($row['g'] ?? '') !== '' ? $row['g'] : '(default)',
            'subject' => $row['s'] ?? '',
            'value' => $row['o'] ?? '',
        ], $rows);
    }

    /**
     * Satu ID dipakai oleh lebih dari satu subjek (aplikasi akan menggabungkannya).
     *
     * @return list<array<string, string|null>>
     */
    public function duplicateIds(): array
    {
        $namespace = self::NAMESPACE_IRI;

        return $this->fuseki->select(<<<SPARQL
PREFIX ws: <{$namespace}>
SELECT ?id (COUNT(DISTINCT ?s) AS ?jumlah) (GROUP_CONCAT(DISTINCT STR(?s); separator=" , ") AS ?subjek)
WHERE { ?s ws:idDestinasi ?id }
GROUP BY ?id
HAVING (COUNT(DISTINCT ?s) > 1)
SPARQL);
    }

    /**
     * Properti bernilai tunggal yang ternyata memiliki lebih dari satu nilai.
     *
     * @return list<array<string, string|null>>
     */
    public function cardinalityViolations(): array
    {
        $namespace = self::NAMESPACE_IRI;
        $properties = implode(' ', array_map(static fn (string $p): string => "ws:{$p}", self::SINGLE_VALUED));

        return $this->fuseki->select(<<<SPARQL
PREFIX ws: <{$namespace}>
SELECT ?id ?p (COUNT(DISTINCT ?o) AS ?jumlah) (GROUP_CONCAT(DISTINCT STR(?o); separator=" | ") AS ?nilai)
WHERE {
    VALUES ?p { {$properties} }
    ?s ?p ?o .
    OPTIONAL { ?s ws:idDestinasi ?id }
}
GROUP BY ?s ?id ?p
HAVING (COUNT(DISTINCT ?o) > 1)
ORDER BY ?id ?p
SPARQL);
    }
}