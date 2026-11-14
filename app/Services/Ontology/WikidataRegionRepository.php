<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class WikidataRegionRepository
{
    public function __construct(
        private readonly WikidataClient $client,
        private readonly WikidataQueryBuilder $queryBuilder,
        private readonly WikidataRegionCatalog $catalog,
        private readonly string $snapshotPath,
        private readonly string $staleSnapshotPath,
        private readonly int $snapshotTtl,
        private readonly int $requestIntervalSeconds,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        $catalog = $this->catalog->load();
        $qids = array_values(array_map(
            static fn (array $region): string => $region['qid'],
            $catalog['regions'],
        ));
        $expectedRegions = [];
        foreach ($catalog['regions'] as $name => $region) {
            $expectedRegions[$region['qid']] = ['name' => $name, 'type' => $region['type']];
        }

        $identityRows = $this->client->select(
            $this->queryBuilder->regions(
                $qids,
                $catalog['verification']['country_qid'],
                $catalog['verification']['required_parent'],
            ),
            refresh: true,
        );
        $regions = $this->verifyRegions($identityRows, $expectedRegions, $catalog['verification']);

        $this->waitBeforeNextRequest();
        $detailRows = $this->client->select($this->queryBuilder->details($qids), refresh: true);
        $regions = $this->mergeDetails($regions, $detailRows);

        $this->waitBeforeNextRequest();
        $boundaryRows = $this->client->select($this->queryBuilder->boundaries($qids), refresh: true);
        $liveBoundaries = $this->verifyBoundaries($boundaryRows, $catalog);

        $regionsByName = [];
        foreach ($catalog['regions'] as $name => $region) {
            $regionsByName[$name] = $regions[$region['qid']];
        }

        $snapshot = [
            'schema_version' => 1,
            'source' => 'Wikidata Query Service',
            'query_url' => WikidataClient::ENDPOINT,
            'retrieved_at' => now()->toIso8601String(),
            'verification' => [
                'regions' => count($regions),
                'boundary_pairs' => count($liveBoundaries),
                'directed_boundary_edges' => count($boundaryRows),
                'country_qid' => $catalog['verification']['country_qid'],
                'province_qid' => $catalog['verification']['required_parent'],
            ],
            'regions' => $regionsByName,
        ];

        $this->writeSnapshot($snapshot);

        return $snapshot;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForRegion(string $regionName, bool $allowStale = false): ?array
    {
        $snapshot = $this->loadSnapshot($allowStale);
        if ($snapshot === null) {
            return null;
        }

        $expectedName = $this->normalizeRegionName($regionName);
        foreach ($snapshot['regions'] as $name => $region) {
            if ($this->normalizeRegionName($name) === $expectedName) {
                return [
                    ...$region,
                    'snapshot_retrieved_at' => $snapshot['retrieved_at'],
                    'is_stale' => $snapshot['is_stale'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadSnapshot(bool $allowStale = false): ?array
    {
        $path = is_file($this->snapshotPath) ? $this->snapshotPath : null;
        $isPreviousSnapshot = false;
        if ($path === null && $allowStale && is_file($this->staleSnapshotPath)) {
            $path = $this->staleSnapshotPath;
            $isPreviousSnapshot = true;
        }

        if ($path === null) {
            return null;
        }

        $snapshot = $this->readSnapshot($path);
        $retrievedAt = strtotime($snapshot['retrieved_at']);
        if ($retrievedAt === false) {
            throw new InvalidArgumentException('Waktu pengambilan snapshot detail Wikidata tidak valid.');
        }

        $isStale = $isPreviousSnapshot || now()->timestamp - $retrievedAt > $this->snapshotTtl;
        if ($isStale && ! $allowStale) {
            return null;
        }

        return [...$snapshot, 'is_stale' => $isStale];
    }

    /**
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @param  array<string, array{name: string, type: string}>  $expectedRegions
     * @param  array<string, mixed>  $verification
     * @return array<string, array<string, mixed>>
     */
    private function verifyRegions(array $rows, array $expectedRegions, array $verification): array
    {
        $verified = [];
        $expectedCountry = 'http://www.wikidata.org/entity/'.$verification['country_qid'];
        $expectedParent = 'http://www.wikidata.org/entity/'.$verification['required_parent'];

        foreach ($rows as $row) {
            $qid = $this->entityQid($this->bindingValue($row, 'item'));
            if ($qid === null || ! isset($expectedRegions[$qid])) {
                throw new RuntimeException('Hasil verifikasi live memuat wilayah di luar QID snapshot.');
            }

            $region = $expectedRegions[$qid];
            $label = $this->bindingValue($row, 'itemLabel');
            $instanceLabel = $this->bindingValue($row, 'instanceLabel');
            if ($label === null || $instanceLabel === null
                || $this->normalizeRegionName($label) !== $this->normalizeRegionName($region['name'])
                || $this->bindingValue($row, 'country') !== $expectedCountry
                || $this->bindingValue($row, 'parent') !== $expectedParent) {
                throw new RuntimeException("Verifikasi nama, negara, atau provinsi live gagal untuk {$region['name']} ({$qid}).");
            }

            $expectedTypeLabel = $region['type'] === 'Kota'
                ? 'city of indonesia'
                : 'regency of indonesia';
            if (mb_strtolower(trim($instanceLabel)) !== $expectedTypeLabel) {
                continue;
            }

            $verified[$qid] ??= [
                'qid' => $qid,
                'type' => $region['type'],
                'wikidata_label' => $label,
                'wikidata_url' => 'https://www.wikidata.org/entity/'.$qid,
                'latitude' => null,
                'longitude' => null,
                'image_url' => null,
                'website_url' => null,
                'population' => null,
                'area' => null,
            ];
        }

        foreach ($expectedRegions as $qid => $region) {
            if (! isset($verified[$qid])) {
                throw new RuntimeException("Verifikasi live tidak mengonfirmasi {$region['name']} ({$qid}) dengan tipe wilayah yang diharapkan.");
            }
        }

        return $verified;
    }

    /**
     * @param  array<string, array<string, mixed>>  $regions
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function mergeDetails(array $regions, array $rows): array
    {
        $details = [];
        foreach ($rows as $row) {
            $qid = $this->entityQid($this->bindingValue($row, 'item'));
            if ($qid === null || ! isset($regions[$qid])) {
                throw new RuntimeException('Hasil detail live memuat item di luar QID snapshot.');
            }

            $details[$qid] ??= ['coordinates' => [], 'images' => [], 'websites' => [], 'population' => [], 'area' => []];
            $coordinate = $this->coordinate($this->bindingValue($row, 'coordinate'));
            if ($coordinate !== null) {
                $details[$qid]['coordinates'][json_encode($coordinate, JSON_THROW_ON_ERROR)] = $coordinate;
            }

            $imageUrl = $this->imageUrl($this->bindingValue($row, 'image'));
            if ($imageUrl !== null) {
                $details[$qid]['images'][$imageUrl] = $imageUrl;
            }

            $websiteUrl = $this->websiteUrl($this->bindingValue($row, 'website'));
            if ($websiteUrl !== null) {
                $details[$qid]['websites'][$websiteUrl] = $websiteUrl;
            }

            $population = $this->quantityRecord(
                $this->bindingValue($row, 'population'),
                $this->bindingValue($row, 'populationRank'),
                $this->bindingValue($row, 'populationTime'),
                null,
                null,
            );
            if ($population !== null) {
                $details[$qid]['population'][] = $population;
            }

            $area = $this->quantityRecord(
                $this->bindingValue($row, 'areaAmount'),
                $this->bindingValue($row, 'areaRank'),
                $this->bindingValue($row, 'areaTime'),
                $this->entityQid($this->bindingValue($row, 'areaUnit')),
                $this->quantityRecord(
                    $this->bindingValue($row, 'normalizedAreaAmount'),
                    $this->bindingValue($row, 'areaRank'),
                    $this->bindingValue($row, 'areaTime'),
                    $this->entityQid($this->bindingValue($row, 'normalizedAreaUnit')),
                    null,
                ),
            );
            if ($area !== null) {
                $details[$qid]['area'][] = $area;
            }
        }

        foreach ($regions as $qid => &$region) {
            $regionDetails = $details[$qid] ?? ['coordinates' => [], 'images' => [], 'websites' => [], 'population' => [], 'area' => []];
            if (count($regionDetails['coordinates']) > 1) {
                throw new RuntimeException("Wikidata mengembalikan koordinat yang saling bertentangan untuk {$qid}.");
            }

            $coordinate = array_values($regionDetails['coordinates'])[0] ?? null;
            $region['latitude'] = $coordinate['latitude'] ?? null;
            $region['longitude'] = $coordinate['longitude'] ?? null;
            $images = array_values($regionDetails['images']);
            sort($images);
            $region['image_url'] = $images[0] ?? null;
            $websites = array_values($regionDetails['websites']);
            sort($websites);
            $region['website_url'] = $websites[0] ?? null;
            $region['population'] = $this->preferredQuantity($regionDetails['population']);
            $region['area'] = $this->preferredQuantity($regionDetails['area']);
        }
        unset($region);

        return $regions;
    }

    /**
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @param  array<string, mixed>  $catalog
     * @return list<array{0: string, 1: string}>
     */
    private function verifyBoundaries(array $rows, array $catalog): array
    {
        $regionQids = array_fill_keys(array_column($catalog['regions'], 'qid'), true);
        $live = [];
        foreach ($rows as $row) {
            $left = $this->entityQid($this->bindingValue($row, 'item'));
            $right = $this->entityQid($this->bindingValue($row, 'neighbor'));
            if ($left === null || $right === null || ! isset($regionQids[$left], $regionQids[$right]) || $left === $right) {
                throw new RuntimeException('Relasi batas live memuat QID di luar wilayah yang disetujui.');
            }

            $pair = [$left, $right];
            sort($pair);
            $live[implode('|', $pair)] = $pair;
        }

        $expected = [];
        foreach ($catalog['boundaries'] as $pair) {
            $expected[implode('|', $pair)] = $pair;
        }

        $missing = array_diff_key($expected, $live);
        $unapproved = array_diff_key($live, $expected);
        if ($missing !== [] || $unapproved !== []) {
            $describe = static fn (array $pairs): string => implode(', ', array_keys($pairs));
            throw new RuntimeException(
                'Relasi batas live berbeda dari snapshot; belum diubah tanpa persetujuan. Hilang: '
                .$describe($missing).'; tambahan: '.$describe($unapproved).'.',
            );
        }

        ksort($live);

        return array_values($live);
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private function coordinate(?string $value): ?array
    {
        if ($value === null || preg_match('/^Point\(\s*(-?[0-9]+(?:\.[0-9]+)?)\s+(-?[0-9]+(?:\.[0-9]+)?)\s*\)$/i', trim($value), $matches) !== 1) {
            return null;
        }

        $longitude = (float) $matches[1];
        $latitude = (float) $matches[2];
        if ($longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
            return null;
        }

        return ['latitude' => $latitude, 'longitude' => $longitude];
    }

    private function imageUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            $url = 'https://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode($value);
        } else {
            $parts = parse_url($value);
            if (! is_array($parts)
                || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                || ! in_array(strtolower((string) ($parts['host'] ?? '')), ['commons.wikimedia.org', 'upload.wikimedia.org'], true)
                || isset($parts['user'])
                || isset($parts['pass'])
                || isset($parts['port'])
                || isset($parts['query'])
                || isset($parts['fragment'])) {
                return null;
            }

            $host = strtolower($parts['host']);
            $path = (string) ($parts['path'] ?? '');
            if (($host === 'commons.wikimedia.org' && ! str_starts_with($path, '/wiki/Special:FilePath/'))
                || ($host === 'upload.wikimedia.org' && ! preg_match('~^/wikipedia/commons/[A-Za-z0-9/_-]+$~', $path))) {
                return null;
            }

            $url = 'https://'.$host.$path;
        }

        return filter_var($url, FILTER_VALIDATE_URL) === false ? null : $url;
    }

    private function websiteUrl(?string $value): ?string
    {
        if ($value === null || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($value);
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || ! is_string($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, array{type: string, value: string}>  $row
     */
    private function bindingValue(array $row, string $key): ?string
    {
        $binding = $row[$key] ?? null;

        return is_array($binding) && is_string($binding['value'] ?? null)
            ? $binding['value']
            : null;
    }

    private function entityQid(?string $value): ?string
    {
        if ($value === null || preg_match('~^https?://www\.wikidata\.org/entity/(Q[0-9]+)$~', $value, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @param  array<string, mixed>|null  $normalized
     * @return array{amount: string, rank: string, as_of: string|null, unit_qid: string|null, normalized: array<string, mixed>|null}|null
     */
    private function quantityRecord(
        ?string $amount,
        ?string $rank,
        ?string $asOf,
        ?string $unitQid,
        ?array $normalized,
    ): ?array {
        if ($amount === null || ! is_numeric($amount) || ! is_finite((float) $amount) || $rank === null) {
            return null;
        }

        if (preg_match('~(?:#|/)(PreferredRank|NormalRank)$~', $rank, $matches) !== 1) {
            return null;
        }
        $rankName = $matches[1];

        $normalizedValue = null;
        if (is_array($normalized)) {
            $normalizedValue = $normalized;
        }

        return [
            'amount' => $amount,
            'rank' => $rankName,
            'as_of' => $asOf,
            'unit_qid' => $unitQid,
            'normalized' => $normalizedValue,
        ];
    }

    /**
     * @param  list<array{amount: string, rank: string, as_of: string|null, unit_qid: string|null, normalized: array<string, mixed>|null}>  $records
     * @return array<string, mixed>|null
     */
    private function preferredQuantity(array $records): ?array
    {
        if ($records === []) {
            return null;
        }

        usort($records, static function (array $left, array $right): int {
            $rankOrder = ['PreferredRank' => 0, 'NormalRank' => 1];
            $rankComparison = $rankOrder[$left['rank']] <=> $rankOrder[$right['rank']];
            if ($rankComparison !== 0) {
                return $rankComparison;
            }

            return strcmp($right['as_of'] ?? '', $left['as_of'] ?? '');
        });

        return $records[0];
    }

    private function normalizeRegionName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/^(kabupaten|kota)\s+/u', '', $name) ?? $name;
        $name = preg_replace('/\s+(regency|city)(?:\s+of\s+indonesia)?$/u', '', $name) ?? $name;
        $name = preg_replace('/^(city|regency)\s+of\s+/u', '', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function waitBeforeNextRequest(): void
    {
        if ($this->requestIntervalSeconds > 0) {
            sleep($this->requestIntervalSeconds);
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function writeSnapshot(array $snapshot): void
    {
        $directory = dirname($this->snapshotPath);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Direktori snapshot Wikidata tidak dapat dibuat: {$directory}");
        }

        if (is_file($this->snapshotPath)) {
            $staleDirectory = dirname($this->staleSnapshotPath);
            if (! is_dir($staleDirectory) && ! mkdir($staleDirectory, 0755, true) && ! is_dir($staleDirectory)) {
                throw new RuntimeException("Direktori snapshot stale Wikidata tidak dapat dibuat: {$staleDirectory}");
            }
            if (! copy($this->snapshotPath, $this->staleSnapshotPath)) {
                throw new RuntimeException('Snapshot Wikidata sebelumnya tidak dapat dipertahankan sebagai fallback.');
            }
        }

        try {
            $contents = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException('Snapshot Wikidata tidak dapat diserialisasi.', previous: $exception);
        }

        $written = file_put_contents($this->snapshotPath, $contents, LOCK_EX);
        if ($written !== strlen($contents)) {
            if (is_file($this->staleSnapshotPath)) {
                if (! copy($this->staleSnapshotPath, $this->snapshotPath)) {
                    throw new RuntimeException('Penulisan snapshot gagal dan snapshot sebelumnya tidak dapat dipulihkan.');
                }
            } elseif (is_file($this->snapshotPath) && ! unlink($this->snapshotPath)) {
                throw new RuntimeException('Penulisan snapshot gagal dan berkas parsial tidak dapat dibersihkan.');
            }

            throw new RuntimeException("Snapshot detail Wikidata tidak dapat ditulis: {$this->snapshotPath}");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readSnapshot(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Snapshot detail Wikidata tidak dapat dibaca: {$path}");
        }

        try {
            $snapshot = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Snapshot detail Wikidata bukan JSON yang valid.', previous: $exception);
        }

        if (! is_array($snapshot)
            || ($snapshot['schema_version'] ?? null) !== 1
            || ! is_string($snapshot['retrieved_at'] ?? null)
            || ! is_array($snapshot['regions'] ?? null)) {
            throw new InvalidArgumentException('Struktur snapshot detail Wikidata tidak valid.');
        }

        foreach ($snapshot['regions'] as $name => $region) {
            if (! is_string($name) || ! is_array($region)
                || ! is_string($region['qid'] ?? null)
                || preg_match('/^Q[0-9]+$/', $region['qid']) !== 1
                || ! in_array($region['type'] ?? null, ['Kabupaten', 'Kota'], true)
                || ! is_string($region['wikidata_label'] ?? null)
                || ($region['wikidata_url'] ?? null) !== 'https://www.wikidata.org/entity/'.$region['qid']
                || ! $this->validCoordinatePair($region['latitude'] ?? null, $region['longitude'] ?? null)
                || ! $this->validSnapshotImage($region['image_url'] ?? null)
                || ! $this->validSnapshotWebsite($region['website_url'] ?? null)
                || ! $this->validQuantity($region['population'] ?? null)
                || ! $this->validQuantity($region['area'] ?? null)) {
                throw new InvalidArgumentException('Entri wilayah pada snapshot detail Wikidata tidak valid.');
            }
        }

        return $snapshot;
    }

    private function validCoordinatePair(mixed $latitude, mixed $longitude): bool
    {
        if ($latitude === null && $longitude === null) {
            return true;
        }

        return is_numeric($latitude)
            && is_numeric($longitude)
            && is_finite((float) $latitude)
            && is_finite((float) $longitude)
            && (float) $latitude >= -90
            && (float) $latitude <= 90
            && (float) $longitude >= -180
            && (float) $longitude <= 180;
    }

    private function validSnapshotImage(mixed $imageUrl): bool
    {
        if ($imageUrl === null) {
            return true;
        }

        return is_string($imageUrl) && $this->imageUrl($imageUrl) === $imageUrl;
    }

    private function validSnapshotWebsite(mixed $websiteUrl): bool
    {
        return $websiteUrl === null
            || (is_string($websiteUrl) && $this->websiteUrl($websiteUrl) === $websiteUrl);
    }

    private function validQuantity(mixed $quantity): bool
    {
        if ($quantity === null) {
            return true;
        }

        if (! is_array($quantity)
            || ! is_string($quantity['amount'] ?? null)
            || ! is_numeric($quantity['amount'])
            || ! is_finite((float) $quantity['amount'])
            || ! in_array($quantity['rank'] ?? null, ['PreferredRank', 'NormalRank'], true)
            || (($quantity['as_of'] ?? null) !== null && ! is_string($quantity['as_of']))
            || (($quantity['unit_qid'] ?? null) !== null
                && (! is_string($quantity['unit_qid']) || preg_match('/^Q[0-9]+$/', $quantity['unit_qid']) !== 1))) {
            return false;
        }

        $normalized = $quantity['normalized'] ?? null;

        return $normalized === null
            || (is_array($normalized)
                && is_string($normalized['amount'] ?? null)
                && is_numeric($normalized['amount'])
                && is_finite((float) $normalized['amount'])
                && (($normalized['unit_qid'] ?? null) === null
                    || (is_string($normalized['unit_qid'])
                        && preg_match('/^Q[0-9]+$/', $normalized['unit_qid']) === 1)));
    }
}
