<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use InvalidArgumentException;

final class OntologyConfiguration
{
    /**
     * @param  array<string, mixed>  $configuration
     */
    public static function validate(array $configuration): void
    {
        if (! in_array($configuration['driver'] ?? null, ['fuseki', 'ttl'], true)) {
            throw new InvalidArgumentException('ONTOLOGY_DRIVER harus bernilai "fuseki" atau "ttl".');
        }

        foreach (['ttl_path', 'v2_source_path', 'wikidata_regions_path'] as $pathKey) {
            if (! is_string($configuration[$pathKey] ?? null) || trim($configuration[$pathKey]) === '') {
                throw new InvalidArgumentException("Konfigurasi ontologi {$pathKey} wajib berupa path yang tidak kosong.");
            }
        }

        $wikidata = $configuration['wikidata'] ?? null;
        if (! is_array($wikidata)) {
            throw new InvalidArgumentException('Konfigurasi Wikidata tidak lengkap.');
        }

        foreach ([
            'snapshot_path',
            'stale_snapshot_path',
            'candidate_report_path',
            'destination_links_path',
            'destination_snapshot_path',
            'stale_destination_snapshot_path',
        ] as $pathKey) {
            if (! is_string($wikidata[$pathKey] ?? null) || trim($wikidata[$pathKey]) === '') {
                throw new InvalidArgumentException("Konfigurasi Wikidata {$pathKey} wajib berupa path yang tidak kosong.");
            }
        }
        if ($wikidata['snapshot_path'] === $wikidata['stale_snapshot_path']) {
            throw new InvalidArgumentException('Path snapshot terbaru dan snapshot stale Wikidata harus berbeda.');
        }
        if ($wikidata['destination_snapshot_path'] === $wikidata['stale_destination_snapshot_path']
            || ! is_string($wikidata['tourism_class_qid'] ?? null)
            || preg_match('/^Q[0-9]+$/', $wikidata['tourism_class_qid']) !== 1) {
            throw new InvalidArgumentException('Konfigurasi snapshot destinasi atau kelas wisata Wikidata tidak valid.');
        }

        foreach (['request_timeout', 'live_timeout', 'refresh_timeout', 'max_retry_after', 'max_response_bytes', 'get_query_max_bytes', 'live_cache_ttl', 'stale_cache_ttl', 'live_lock_seconds', 'failure_cooldown', 'snapshot_ttl'] as $positiveKey) {
            if (! is_int($wikidata[$positiveKey] ?? null) || $wikidata[$positiveKey] < 1) {
                throw new InvalidArgumentException("Konfigurasi Wikidata {$positiveKey} harus berupa bilangan bulat lebih besar dari nol.");
            }
        }

        foreach (['max_retries', 'query_cache_ttl', 'request_interval_seconds'] as $nonNegativeKey) {
            if (! is_int($wikidata[$nonNegativeKey] ?? null) || $wikidata[$nonNegativeKey] < 0) {
                throw new InvalidArgumentException("Konfigurasi Wikidata {$nonNegativeKey} harus berupa bilangan bulat nol atau lebih.");
            }
        }

        if (! is_bool($wikidata['allow_stale'] ?? null)
            || ! is_string($wikidata['contact'] ?? null)
            || ! is_string($wikidata['contact_fallback'] ?? null)) {
            throw new InvalidArgumentException('Kontak dan opsi snapshot stale Wikidata tidak valid.');
        }
        WikidataUserAgent::resolve($wikidata['contact'], $wikidata['contact_fallback']);

        $cacheTtl = $configuration['cache_ttl'] ?? null;
        if (! is_int($cacheTtl) || $cacheTtl < 0) {
            throw new InvalidArgumentException('ONTOLOGY_CACHE_TTL harus berupa bilangan bulat nol atau lebih.');
        }

        if (! is_string($configuration['unknown_label'] ?? null) || trim($configuration['unknown_label']) === '') {
            throw new InvalidArgumentException('Sentinel ontologi wajib berupa teks yang tidak kosong.');
        }

        $namespaces = $configuration['namespaces'] ?? null;
        $requiredNamespaces = ['ws', 'data', 'schema', 'rdf', 'rdfs', 'xsd', 'owl'];
        if (! is_array($namespaces)) {
            throw new InvalidArgumentException('Peta namespace ontologi tidak valid.');
        }

        foreach ($requiredNamespaces as $prefix) {
            $namespace = $namespaces[$prefix] ?? null;
            $parts = is_string($namespace) ? parse_url($namespace) : false;
            if (! is_string($namespace)
                || ! is_array($parts)
                || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                || ! is_string($parts['host'] ?? null)
                || isset($parts['user'])
                || isset($parts['pass'])
                || preg_match('/[\x00-\x20\x7f]/', $namespace) === 1) {
                throw new InvalidArgumentException("Namespace ontologi {$prefix} harus berupa IRI http/https yang valid.");
            }
        }

        $placeholder = $configuration['og_image_placeholder'] ?? null;
        if (! is_string($placeholder)
            || preg_match('~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*\.(?:png|jpe?g)$~i', $placeholder) !== 1) {
            throw new InvalidArgumentException('Path gambar placeholder Open Graph harus berupa path lokal relatif.');
        }

        $rdfExport = $configuration['rdf_export'] ?? null;
        $expectedFormats = [
            'ttl' => ['media_type' => 'text/turtle', 'extension' => 'ttl'],
            'rdf' => ['media_type' => 'application/rdf+xml', 'extension' => 'rdf'],
            'jsonld' => ['media_type' => 'application/ld+json', 'extension' => 'jsonld'],
        ];
        if (! is_array($rdfExport)
            || ! is_int($rdfExport['cache_ttl'] ?? null)
            || $rdfExport['cache_ttl'] < 0
            || ($rdfExport['formats'] ?? null) !== $expectedFormats) {
            throw new InvalidArgumentException('Konfigurasi format dan cache ekspor RDF tidak valid.');
        }

        $nearby = $configuration['nearby'] ?? null;
        if (! is_array($nearby)
            || ! is_numeric($nearby['radius_km'] ?? null)
            || (float) $nearby['radius_km'] <= 0
            || ! is_int($nearby['max_neighbors'] ?? null)
            || $nearby['max_neighbors'] < 1) {
            throw new InvalidArgumentException('Konfigurasi radius dan jumlah tetangga ontologi tidak valid.');
        }

        $fuseki = $configuration['fuseki'] ?? null;
        if (! is_array($fuseki)) {
            throw new InvalidArgumentException('Konfigurasi Fuseki tidak lengkap.');
        }

        $url = $fuseki['url'] ?? null;
        $urlParts = is_string($url) ? parse_url($url) : false;
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! is_array($urlParts)
            || ! in_array(strtolower((string) ($urlParts['scheme'] ?? '')), ['http', 'https'], true)
            || ! is_string($urlParts['host'] ?? null)
            || isset($urlParts['user'])
            || isset($urlParts['pass'])
            || isset($urlParts['query'])
            || isset($urlParts['fragment'])) {
            throw new InvalidArgumentException('FUSEKI_URL harus berupa URL http/https yang valid tanpa kredensial, query, atau fragment.');
        }

        $dataset = $fuseki['dataset'] ?? null;
        if (! is_string($dataset) || preg_match('/^[A-Za-z0-9_-]+$/', $dataset) !== 1) {
            throw new InvalidArgumentException('FUSEKI_DATASET wajib berisi hanya huruf, angka, garis bawah, atau tanda hubung.');
        }

        foreach (['timeout', 'load_timeout'] as $timeoutKey) {
            if (! is_int($fuseki[$timeoutKey] ?? null) || $fuseki[$timeoutKey] < 1) {
                $environmentKey = $timeoutKey === 'load_timeout' ? 'FUSEKI_LOAD_TIMEOUT' : 'FUSEKI_TIMEOUT';
                throw new InvalidArgumentException("{$environmentKey} harus berupa bilangan bulat lebih besar dari nol.");
            }
        }

        self::validateCredentials($fuseki, 'username', 'password', 'FUSEKI_USERNAME', 'FUSEKI_PASSWORD');
        self::validateCredentials(
            $fuseki,
            'write_username',
            'write_password',
            'FUSEKI_WRITE_USERNAME',
            'FUSEKI_WRITE_PASSWORD',
        );
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private static function validateCredentials(
        array $configuration,
        string $usernameKey,
        string $passwordKey,
        string $usernameName,
        string $passwordName,
    ): void {
        $username = $configuration[$usernameKey] ?? null;
        $password = $configuration[$passwordKey] ?? null;
        if (($username !== null && ! is_string($username))
            || ($password !== null && ! is_string($password))) {
            throw new InvalidArgumentException("{$usernameName} dan {$passwordName} harus berupa teks.");
        }

        $hasUsername = is_string($username) && $username !== '';
        $hasPassword = is_string($password) && $password !== '';
        if ($hasUsername !== $hasPassword) {
            throw new InvalidArgumentException("{$usernameName} dan {$passwordName} harus diisi bersama.");
        }
    }
}
