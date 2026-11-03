<?php

namespace App\Services\Ontology;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class FusekiClient
{
    public function __construct(
        private string $url,
        private string $dataset,
        private ?string $username = null,
        private ?string $password = null,
        private int $timeout = 5,
    ) {}

    public function isAvailable(): bool
    {
        try {
            return $this->request()
                ->connectTimeout(min(2, $this->timeout))
                ->get(rtrim($this->url, '/').'/$/ping')
                ->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function fetchDestinations(): array
    {
        return $this->select($this->destinationQuery());
    }

    /**
     * Menjalankan query SELECT dan meratakan setiap binding menjadi pasangan nama => nilai.
     *
     * @return list<array<string, string|null>>
     */
    public function select(string $query): array
    {
        $response = $this->request()
            ->asForm()
            ->post($this->datasetUrl().'/sparql', ['query' => $query]);

        $response->throw();

        $bindings = $response->json('results.bindings', []);

        if (! is_array($bindings)) {
            throw new RuntimeException('Respons SPARQL tidak memiliki results.bindings.');
        }

        $rows = [];

        foreach ($bindings as $binding) {
            if (! is_array($binding)) {
                continue;
            }

            $row = [];

            foreach ($binding as $property => $value) {
                $row[$property] = is_array($value) ? (string) ($value['value'] ?? '') : (string) $value;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function update(string $update): void
    {
        $this->request()
            ->asForm()
            ->post($this->datasetUrl().'/update', ['update' => $update])
            ->throw();
    }

    /**
     * Mengosongkan graph default DAN seluruh graph bernama.
     * PUT ke "?default" saja tidak menyentuh graph bernama.
     */
    public function clearAll(): void
    {
        $this->update('CLEAR ALL');
    }

    public function upload(string $turtle): void
    {
        $response = $this->request()
            ->withBody($turtle, 'text/turtle')
            ->put($this->datasetUrl().'/data?default');

        $response->throw();
    }

    public function datasetUrl(): string
    {
        return rtrim($this->url, '/').'/'.trim($this->dataset, '/');
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout($this->timeout)->acceptJson();

        if ($this->username !== null && $this->username !== '') {
            $request = $request->withBasicAuth($this->username, $this->password ?? '');
        }

        return $request;
    }

    private function destinationQuery(): string
    {
        return <<<'SPARQL'
PREFIX ws: <http://pariwisatasumut.org/ontology/v2#>

SELECT ?s ?idDestinasi ?namaDestinasi ?namaAlternatif ?deskripsiSingkat ?deskripsiLengkap
       ?jenisDestinasi ?kataKunci ?beradaDi ?alamat ?lintang ?bujur
       ?petunjukLokasi ?jamOperasional ?hargaMasuk ?noHpResmi ?emailResmi
       ?instagramResmi ?memilikiFasilitas ?dapatMelakukan ?memilikiMedia
       ?ratingPengunjung
WHERE {
    ?s a ws:DestinasiWisata .
    OPTIONAL { ?s ws:idDestinasi ?idDestinasi }
    OPTIONAL { ?s ws:namaDestinasi ?namaDestinasi }
    OPTIONAL { ?s ws:deskripsiSingkat ?deskripsiSingkat }
    OPTIONAL { ?s ws:deskripsiLengkap ?deskripsiLengkap }
    OPTIONAL { ?s ws:kataKunci ?kataKunci }
    OPTIONAL { ?s ws:beradaDi ?beradaDi }
    OPTIONAL { ?s ws:alamat ?alamat }
    OPTIONAL { ?s ws:lintang ?lintang }
    OPTIONAL { ?s ws:bujur ?bujur }
    OPTIONAL { ?s ws:petunjukLokasi ?petunjukLokasi }
    OPTIONAL { ?s ws:jamOperasional ?jamOperasional }
    OPTIONAL { ?s ws:hargaMasuk ?hargaMasuk }
    OPTIONAL { ?s ws:noHpResmi ?noHpResmi }
    OPTIONAL { ?s ws:emailResmi ?emailResmi }
    OPTIONAL { ?s ws:instagramResmi ?instagramResmi }
    OPTIONAL { ?s ws:memilikiFasilitas ?memilikiFasilitas }
    OPTIONAL { ?s ws:memilikiMedia ?memilikiMedia }
    OPTIONAL { ?s ws:ratingPengunjung ?ratingPengunjung }
    OPTIONAL {
        SELECT ?s (GROUP_CONCAT(DISTINCT STR(?jns); separator=" | ") AS ?jenisDestinasi)
        WHERE { ?s ws:jenisDestinasi ?jns . FILTER(STR(?jns) != "-") }
        GROUP BY ?s
    }
    OPTIONAL {
        SELECT ?s (GROUP_CONCAT(DISTINCT STR(?alt); separator=" | ") AS ?namaAlternatif)
        WHERE { ?s ws:namaAlternatif ?alt . FILTER(STR(?alt) != "-") }
        GROUP BY ?s
    }
    OPTIONAL {
        SELECT ?s (GROUP_CONCAT(DISTINCT STR(?act); separator=" | ") AS ?dapatMelakukan)
        WHERE { ?s ws:dapatMelakukan ?act . FILTER(STR(?act) != "-") }
        GROUP BY ?s
    }
}
ORDER BY ?idDestinasi
SPARQL;
    }
}