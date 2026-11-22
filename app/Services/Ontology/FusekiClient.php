<?php

namespace App\Services\Ontology;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class FusekiClient
{
    public function __construct(
        private string $url,
        private string $dataset,
        private ?string $username = null,
        private ?string $password = null,
        private int $timeout = 5,
        private ?string $writeUsername = null,
        private ?string $writePassword = null,
        private int $loadTimeout = 60,
    ) {}

    public function isAvailable(): bool
    {
        try {
            return $this->request()
                ->connectTimeout(min(2, $this->timeout))
                ->get(rtrim($this->url, '/').'/$/ping')
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function datasetExists(): bool
    {
        try {
            $response = $this->request()
                ->asForm()
                ->post($this->datasetUrl().'/sparql', ['query' => 'ASK {}']);
        } catch (ConnectionException $exception) {
            throw OntologyUnavailableException::serverUnavailable($exception);
        }

        if ($response->status() === 404) {
            return false;
        }

        $this->ensureSuccessful($response);
        $exists = $response->json('boolean');
        if (! is_bool($exists)) {
            throw OntologyUnavailableException::badResponse();
        }

        return true;
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
        try {
            $response = $this->request()
                ->asForm()
                ->post($this->datasetUrl().'/sparql', ['query' => $query]);
        } catch (ConnectionException $exception) {
            throw OntologyUnavailableException::serverUnavailable($exception);
        }

        $this->ensureSuccessful($response);
        $bindings = $response->json('results.bindings');
        if (! is_array($bindings)) {
            throw OntologyUnavailableException::badResponse();
        }

        $rows = [];
        foreach ($bindings as $binding) {
            if (! is_array($binding)) {
                throw OntologyUnavailableException::badResponse();
            }

            $row = [];
            foreach ($binding as $property => $value) {
                if (! is_string($property) || ! is_array($value)
                    || (isset($value['value']) && ! is_string($value['value']))) {
                    throw OntologyUnavailableException::badResponse();
                }

                $row[$property] = $value['value'] ?? null;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function construct(string $query, string $accept): string
    {
        if (! in_array($accept, ['text/turtle', 'application/rdf+xml', 'application/ld+json'], true)) {
            throw new \InvalidArgumentException('Media type ekspor RDF tidak didukung.');
        }

        try {
            $response = $this->request()
                ->withHeaders(['Accept' => $accept])
                ->asForm()
                ->post($this->datasetUrl().'/sparql', ['query' => $query]);
        } catch (ConnectionException $exception) {
            throw OntologyUnavailableException::serverUnavailable($exception);
        }

        $this->ensureSuccessful($response);
        $contentType = strtolower(trim(strtok((string) $response->header('Content-Type'), ';')));
        if ($contentType !== $accept) {
            throw OntologyUnavailableException::badResponse();
        }

        return $response->body();
    }

    public function update(string $update, ?int $timeout = null): void
    {
        $this->postUpdate($update, $timeout ?? $this->timeout);
    }

    /**
     * Mengosongkan graph default dan seluruh graph bernama.
     */
    public function clearAll(): void
    {
        $this->update('CLEAR ALL');
    }

    public function upload(string $turtle): void
    {
        $this->putGraph($turtle, null);
    }

    public function replaceDataset(string $turtle, bool $clearNamedGraphs = false): void
    {
        $this->upload($turtle);

        if ($clearNamedGraphs) {
            $this->update('CLEAR NAMED', $this->loadTimeout);
        }
    }

    public function downloadDefaultGraph(): string
    {
        return $this->downloadGraph(null);
    }

    public function downloadNamedGraph(string $graph): string
    {
        return $this->downloadGraph($graph);
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function namedGraphs(): array
    {
        return $this->select('SELECT DISTINCT ?g WHERE { GRAPH ?g { ?s ?p ?o } } ORDER BY ?g');
    }

    public function datasetUrl(): string
    {
        return rtrim($this->url, '/').'/'.trim($this->dataset, '/');
    }

    private function putGraph(string $turtle, ?string $graph): void
    {
        $url = $this->datasetUrl().'/data';
        if ($graph === null) {
            $url .= '?default';
        } else {
            $url .= '?'.http_build_query(['graph' => $graph]);
        }

        try {
            $response = $this->request(write: true, timeout: $this->loadTimeout)
                ->withBody($turtle, 'text/turtle')
                ->put($url);
        } catch (ConnectionException $exception) {
            throw OntologyUnavailableException::serverUnavailable($exception);
        }

        $this->ensureSuccessful($response);
    }

    private function downloadGraph(?string $graph): string
    {
        $url = $graph === null
            ? $this->datasetUrl().'/data?default'
            : $this->datasetUrl().'/data?'.http_build_query(['graph' => $graph]);

        try {
            $response = $this->request(timeout: $this->loadTimeout)
                ->accept('text/turtle, application/n-triples;q=0.9')
                ->get($url);
        } catch (ConnectionException $exception) {
            throw OntologyUnavailableException::serverUnavailable($exception);
        }

        $this->ensureSuccessful($response);

        return $response->body();
    }

    private function postUpdate(string $update, int $timeout): void
    {
        try {
            $response = $this->request(write: true, timeout: $timeout)
                ->asForm()
                ->post($this->datasetUrl().'/update', ['update' => $update]);
        } catch (ConnectionException $exception) {
            throw OntologyUnavailableException::serverUnavailable($exception);
        }

        $this->ensureSuccessful($response);
    }

    private function request(bool $write = false, ?int $timeout = null): PendingRequest
    {
        $request = Http::timeout($timeout ?? $this->timeout)->acceptJson();
        $useWriteCredentials = $write && $this->writeUsername !== null && $this->writeUsername !== '';
        $username = $useWriteCredentials ? $this->writeUsername : $this->username;
        $password = $useWriteCredentials ? $this->writePassword : $this->password;

        if ($username !== null && $username !== '') {
            $request = $request->withBasicAuth($username, $password ?? '');
        }

        return $request;
    }

    private function ensureSuccessful(Response $response): void
    {
        $status = $response->status();

        if ($response->successful()) {
            return;
        }

        if (in_array($status, [401, 403], true)) {
            throw OntologyUnavailableException::accessDenied($status);
        }

        if ($status === 404) {
            throw OntologyUnavailableException::datasetMissing($this->dataset);
        }

        if ($status >= 500) {
            throw OntologyUnavailableException::serverUnavailable();
        }

        throw OntologyUnavailableException::requestRejected($status);
    }

    private function destinationQuery(): string
    {
        return <<<'SPARQL'
PREFIX ws: <http://pariwisatasumut.org/ontology/v2#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

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
    OPTIONAL { ?s ws:alamat ?alamat }
    OPTIONAL { ?s ws:lintang ?lintang }
    OPTIONAL { ?s ws:bujur ?bujur }
    OPTIONAL { ?s ws:petunjukLokasi ?petunjukLokasi }
    OPTIONAL { ?s ws:ratingPengunjung ?ratingPengunjung }
    OPTIONAL {
        SELECT ?s (GROUP_CONCAT(DISTINCT STR(?label); separator=" | ") AS ?jenisDestinasi)
        WHERE {
            ?s ws:berkategori ?category .
            ?category rdfs:label ?label .
            FILTER(STR(?label) != "-")
        }
        GROUP BY ?s
    }
    OPTIONAL {
        SELECT ?s (GROUP_CONCAT(DISTINCT STR(?alternative); separator=" | ") AS ?namaAlternatif)
        WHERE {
            ?s ws:namaAlternatif ?alternative .
            FILTER(STR(?alternative) != "-")
        }
        GROUP BY ?s
    }
    OPTIONAL {
        SELECT ?s (GROUP_CONCAT(DISTINCT STR(?keyword); separator=" | ") AS ?kataKunci)
        WHERE {
            ?s ws:kataKunci ?keyword .
            FILTER(STR(?keyword) != "-")
        }
        GROUP BY ?s
    }
    OPTIONAL {
        SELECT ?s (SAMPLE(STR(?label)) AS ?beradaDi)
        WHERE {
            ?s ws:berlokasiDi ?region .
            ?region rdfs:label ?label .
        }
        GROUP BY ?s
    }
    OPTIONAL {
        SELECT ?s (GROUP_CONCAT(DISTINCT STR(?label); separator=" | ") AS ?memilikiFasilitas)
        WHERE {
            ?s ws:menyediakanFasilitas ?facility .
            ?facility rdfs:label ?label .
        }
        GROUP BY ?s
    }
    OPTIONAL {
        SELECT ?s (GROUP_CONCAT(DISTINCT STR(?label); separator=" | ") AS ?dapatMelakukan)
        WHERE {
            ?s ws:menawarkanAktivitas ?activity .
            ?activity rdfs:label ?label .
        }
        GROUP BY ?s
    }
    OPTIONAL {
        SELECT ?s (SAMPLE(STR(?url)) AS ?memilikiMedia)
        WHERE {
            ?s ws:memilikiMedia ?media .
            ?media ws:urlMedia ?url .
            FILTER(STR(?url) != "-")
        }
        GROUP BY ?s
    }
    OPTIONAL {
        SELECT ?s (SAMPLE(STR(?phone)) AS ?noHpResmi)
               (SAMPLE(STR(?email)) AS ?emailResmi)
               (SAMPLE(STR(?instagram)) AS ?instagramResmi)
        WHERE {
            ?s ws:memilikiKontak ?contact .
            OPTIONAL { ?contact ws:nomorHp ?phone }
            OPTIONAL { ?contact ws:email ?email }
            OPTIONAL { ?contact ws:urlInstagram ?instagram }
        }
        GROUP BY ?s
    }
    OPTIONAL {
        SELECT ?s (SAMPLE(?hours) AS ?jamOperasional)
        WHERE {
            ?s ws:memilikiJadwal ?schedule .
            OPTIONAL { ?schedule ws:keteranganJadwal ?description }
            OPTIONAL { ?schedule ws:jamBuka ?opening }
            OPTIONAL { ?schedule ws:jamTutup ?closing }
            BIND(
                IF(
                    BOUND(?description) && STR(?description) != "-",
                    STR(?description),
                    IF(
                        BOUND(?opening) && BOUND(?closing),
                        CONCAT(STR(?opening), " - ", STR(?closing)),
                        ""
                    )
                ) AS ?hours
            )
        }
        GROUP BY ?s
    }
    OPTIONAL {
        SELECT ?s (GROUP_CONCAT(DISTINCT ?priceLabel; separator=" | ") AS ?hargaMasuk)
        WHERE {
            ?s ws:memilikiTarif ?tariff .
            OPTIONAL { ?tariff ws:hargaMasuk ?price }
            OPTIONAL { ?tariff ws:keteranganTarif ?description }
            BIND(
                IF(
                    BOUND(?price) && STR(?price) != "-",
                    STR(?price),
                    IF(BOUND(?description) && STR(?description) != "-", STR(?description), "")
                ) AS ?priceLabel
            )
            FILTER(?priceLabel != "")
        }
        GROUP BY ?s
    }
}
ORDER BY ?idDestinasi
SPARQL;
    }
}
