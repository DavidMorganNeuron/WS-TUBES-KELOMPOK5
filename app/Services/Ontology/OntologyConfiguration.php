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

        $cacheTtl = $configuration['cache_ttl'] ?? null;
        if (! is_int($cacheTtl) || $cacheTtl < 0) {
            throw new InvalidArgumentException('ONTOLOGY_CACHE_TTL harus berupa bilangan bulat nol atau lebih.');
        }

        if (! is_string($configuration['unknown_label'] ?? null) || trim($configuration['unknown_label']) === '') {
            throw new InvalidArgumentException('Sentinel ontologi wajib berupa teks yang tidak kosong.');
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
