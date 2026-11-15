<?php

declare(strict_types=1);

namespace App\Services\Ontology;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class WikidataRegionRepository
{
    public const LIVE_CACHE_KEY = 'wikidata:region-enrichment:fresh';

    public const STALE_CACHE_KEY = 'wikidata:region-enrichment:stale';

    public const FAILURE_COOLDOWN_KEY = 'wikidata:region-enrichment:failure-cooldown';

    private const LIVE_LOCK_KEY = 'wikidata:region-enrichment:lock';

    public function __construct(
        private readonly WikidataClient $client,
        private readonly WikidataQueryBuilder $queryBuilder,
        private readonly WikidataRegionCatalog $catalog,
        private readonly string $snapshotPath,
        private readonly string $staleSnapshotPath,
        private readonly int $snapshotTtl,
        private readonly int $requestIntervalSeconds,
        private readonly int $liveTimeout,
        private readonly int $liveCacheTtl,
        private readonly int $staleCacheTtl,
        private readonly int $liveLockSeconds,
        private readonly int $failureCooldown,
        private readonly ?WikidataDestinationLinkCatalog $destinationLinks = null,
        private readonly string $destinationSnapshotPath = '',
        private readonly string $staleDestinationSnapshotPath = '',
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
        $labelRows = $this->client->select($this->queryBuilder->labelsAndAliases($qids), refresh: true);
        $regions = $this->mergeLabelsAndAliases($regions, $labelRows);

        $this->waitBeforeNextRequest();
        $sitelinkRows = $this->client->select($this->queryBuilder->sitelinks($qids), refresh: true);
        $regions = $this->mergeSitelinks($regions, $sitelinkRows);

        $this->waitBeforeNextRequest();
        $imageRows = $this->client->select($this->queryBuilder->images($qids), refresh: true);
        $regions = $this->mergeImages($regions, $imageRows);

        $this->waitBeforeNextRequest();
        $boundaryRows = $this->client->select($this->queryBuilder->boundaries($qids), refresh: true);
        $liveBoundaries = $this->verifyBoundaries($boundaryRows, $catalog);

        $regionsByName = [];
        foreach ($catalog['regions'] as $name => $region) {
            $regionsByName[$name] = $regions[$region['qid']];
        }

        $snapshot = [
            'schema_version' => 2,
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

        $destinationSnapshot = $this->refreshDestinationSnapshot();
        $this->writeDestinationSnapshot($destinationSnapshot);
        $this->writeSnapshot($snapshot);
        $snapshot['destinations'] = $destinationSnapshot['destinations'];
        $snapshot['destination_verification'] = $destinationSnapshot['verification'];

        return $snapshot;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForRegion(string $regionName, bool $allowStale = false): ?array
    {
        $catalog = $this->catalog->load();
        $qid = null;
        $catalogName = null;
        $expectedName = $this->normalizeRegionName($regionName);
        foreach ($catalog['regions'] as $name => $region) {
            if ($this->normalizeRegionName($name) === $expectedName) {
                $qid = $region['qid'];
                $catalogName = $name;
                break;
            }
        }

        if ($qid === null || $catalogName === null) {
            return null;
        }

        $base = $this->snapshotRegion($catalogName, $allowStale);
        $enrichment = $this->liveEnrichmentFor($qid);

        return $enrichment === null
            ? $base
            : $this->mergeRegionEnrichment($base, $enrichment);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForDestination(string $destinationId, bool $allowStale = false): ?array
    {
        if ($this->destinationLinks === null) {
            return null;
        }

        try {
            $approved = $this->destinationLinks->load()['destinations_by_id'];
        } catch (Throwable $exception) {
            $this->logLiveFailure($exception, null, 'destination_approval');

            return null;
        }
        $qid = $approved[$destinationId] ?? null;
        if (! is_string($qid)) {
            return null;
        }

        $base = $this->snapshotDestination($destinationId, $qid, $allowStale);
        $enrichment = $this->liveEnrichmentFor($qid);
        if ($enrichment === null) {
            return $base;
        }

        $destination = [...($base ?? []), ...$enrichment];
        $destination['wikidata_url'] = OntologyMigrator::WIKIDATA_ENTITY_NS.$qid;

        return $destination;
    }

    /**
     * Resolve related region and destination data through one shared live batch.
     *
     * @return array{region: array<string, mixed>|null, destination: array<string, mixed>|null}
     */
    public function findForDestinationPage(
        string $destinationId,
        string $regionName,
        bool $allowStale = false,
    ): array {
        $region = $this->findForRegion($regionName, $allowStale);
        $destination = $this->findForDestination($destinationId, $allowStale);

        return ['region' => $region, 'destination' => $destination];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function liveEnrichmentFor(string $qid): ?array
    {
        $fresh = $this->readCachedEnrichment(self::LIVE_CACHE_KEY);
        if (isset($fresh[$qid])) {
            return $fresh[$qid];
        }

        if ($this->isInFailureCooldown()) {
            return $this->readCachedEnrichment(self::STALE_CACHE_KEY)[$qid] ?? null;
        }

        $lock = null;
        try {
            $lock = Cache::lock(self::LIVE_LOCK_KEY, $this->liveLockSeconds);
            if (! $lock->get()) {
                return $this->readCachedEnrichment(self::STALE_CACHE_KEY)[$qid] ?? null;
            }
        } catch (Throwable $exception) {
            $this->logLiveFailure($exception, $qid, 'lock');

            return $this->readCachedEnrichment(self::STALE_CACHE_KEY)[$qid] ?? null;
        }

        try {
            $fresh = $this->readCachedEnrichment(self::LIVE_CACHE_KEY);
            if (isset($fresh[$qid])) {
                return $fresh[$qid];
            }
            if ($this->isInFailureCooldown()) {
                return $this->readCachedEnrichment(self::STALE_CACHE_KEY)[$qid] ?? null;
            }

            $qids = $this->allEntityQids();
            if (! in_array($qid, $qids, true)) {
                throw new RuntimeException('QID tidak termasuk dalam kelompok pengayaan yang diizinkan.');
            }

            $rows = $this->client->selectLive($this->queryBuilder->liveEnrichment($qids), $this->liveTimeout);
            $enrichment = $this->mapLiveEnrichment($rows, $qids);
            $this->cacheEnrichment($enrichment);

            return $enrichment[$qid] ?? null;
        } catch (Throwable $exception) {
            $this->logLiveFailure($exception, $qid, 'request');
            $this->markFailureCooldown();

            return $this->readCachedEnrichment(self::STALE_CACHE_KEY)[$qid] ?? null;
        } finally {
            try {
                $lock->release();
            } catch (Throwable $exception) {
                $this->logLiveFailure($exception, $qid, 'unlock');
            }
        }
    }

    /**
     * @return list<string>
     */
    private function allEntityQids(): array
    {
        $qids = array_map(
            static fn (array $region): string => $region['qid'],
            $this->catalog->load()['regions'],
        );
        if ($this->destinationLinks !== null) {
            $qids = [...$qids, ...array_keys($this->destinationLinks->load()['destinations_by_qid'])];
        }
        $qids = array_values(array_unique($qids));
        sort($qids, SORT_STRING);

        return $qids;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshotDestination(string $destinationId, string $qid, bool $allowStale): ?array
    {
        $paths = [$this->destinationSnapshotPath];
        if ($allowStale) {
            $paths[] = $this->staleDestinationSnapshotPath;
        }

        foreach ($paths as $path) {
            if ($path === '' || ! is_file($path)) {
                continue;
            }

            try {
                $contents = file_get_contents($path);
                $snapshot = $contents === false
                    ? null
                    : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                $retrievedAt = is_array($snapshot) ? strtotime((string) ($snapshot['retrieved_at'] ?? '')) : false;
                if (! is_array($snapshot)
                    || ($snapshot['schema_version'] ?? null) !== 1
                    || ! is_array($snapshot['destinations'] ?? null)
                    || $retrievedAt === false
                    || (! $allowStale && now()->timestamp - $retrievedAt > $this->snapshotTtl)) {
                    throw new InvalidArgumentException('Struktur atau usia snapshot detail destinasi tidak valid.');
                }

                $destination = $snapshot['destinations'][$destinationId] ?? null;
                if (is_array($destination)
                    && ($destination['qid'] ?? null) === $qid
                    && $this->validEnrichment($destination, $qid)) {
                    $destination['wikidata_url'] = OntologyMigrator::WIKIDATA_ENTITY_NS.$qid;

                    return $destination;
                }
            } catch (Throwable $exception) {
                $this->logLiveFailure($exception, $qid, 'destination_snapshot');
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function refreshDestinationSnapshot(): array
    {
        $approved = $this->destinationLinks?->load()['setuju'] ?? [];
        $records = [];
        foreach ($approved as $entry) {
            $records[$entry['qid']] = [
                'qid' => $entry['qid'],
                'labels' => ['id' => null, 'en' => null],
                'aliases' => ['id' => null, 'en' => null],
                'image_filename' => null,
                'image_url' => null,
                'image_page_url' => null,
                'wikipedia_url' => null,
                'wikivoyage_url' => null,
                'wikivoyage_language' => null,
                'heritage_statuses' => [],
                'elevation_m' => null,
                'establishment_year' => null,
            ];
        }
        $qids = array_keys($records);
        sort($qids, SORT_STRING);

        if ($qids !== []) {
            $this->waitBeforeNextRequest();
            $records = $this->mergeLabelsAndAliases(
                $records,
                $this->client->select($this->queryBuilder->labelsAndAliases($qids), refresh: true),
            );
            $this->waitBeforeNextRequest();
            $records = $this->mergeSitelinks(
                $records,
                $this->client->select($this->queryBuilder->sitelinks($qids), refresh: true),
            );
            $this->waitBeforeNextRequest();
            $records = $this->mergeImages(
                $records,
                $this->client->select($this->queryBuilder->images($qids), refresh: true),
            );
            $this->waitBeforeNextRequest();
            $records = $this->mergeHeritageStatuses(
                $records,
                $this->client->select($this->queryBuilder->heritageStatuses($qids), refresh: true),
            );
            $this->waitBeforeNextRequest();
            $records = $this->mergeDestinationElevations(
                $records,
                $this->client->select($this->queryBuilder->elevations($qids), refresh: true),
            );
            $this->waitBeforeNextRequest();
            $records = $this->mergeEstablishmentYears(
                $records,
                $this->client->select($this->queryBuilder->establishmentYears($qids), refresh: true),
            );
        }

        $destinations = [];
        foreach ($approved as $entry) {
            $destinations[$entry['id']] = $records[$entry['qid']];
        }
        ksort($destinations, SORT_STRING);

        return [
            'schema_version' => 1,
            'source' => 'Wikidata Query Service',
            'query_url' => WikidataClient::ENDPOINT,
            'retrieved_at' => now()->toIso8601String(),
            'verification' => [
                'approved_destinations' => count($destinations),
                'with_image' => count(array_filter($destinations, static fn (array $row): bool => $row['image_filename'] !== null)),
                'with_wikipedia' => count(array_filter($destinations, static fn (array $row): bool => $row['wikipedia_url'] !== null)),
                'with_wikivoyage' => count(array_filter($destinations, static fn (array $row): bool => $row['wikivoyage_url'] !== null)),
                'with_heritage_status' => count(array_filter($destinations, static fn (array $row): bool => $row['heritage_statuses'] !== [])),
                'with_elevation' => count(array_filter($destinations, static fn (array $row): bool => $row['elevation_m'] !== null)),
                'with_establishment_year' => count(array_filter($destinations, static fn (array $row): bool => $row['establishment_year'] !== null)),
            ],
            'destinations' => $destinations,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $destinations
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function mergeHeritageStatuses(array $destinations, array $rows): array
    {
        $statuses = [];
        foreach ($rows as $row) {
            $qid = $this->entityQid($this->bindingValue($row, 'item'));
            $statusQid = $this->entityQid($this->bindingValue($row, 'status'));
            $label = $this->literalBindingValue($row, 'label');
            $rank = $this->rankName($this->bindingValue($row, 'rank'));
            $language = $row['label']['xml:lang'] ?? null;
            if ($qid === null || ! isset($destinations[$qid])
                || $statusQid === null || ! $this->validText($label)
                || ! in_array($language, ['id', 'en'], true) || $rank === null) {
                throw new RuntimeException('Hasil status cagar budaya Wikidata tidak valid.');
            }
            $statuses[$qid][$statusQid][$language] = ['label' => $label, 'rank' => $rank];
        }

        foreach ($destinations as $qid => &$destination) {
            $values = [];
            foreach ($statuses[$qid] ?? [] as $statusQid => $labels) {
                $label = $labels['id']['label'] ?? $labels['en']['label'] ?? null;
                if (is_string($label)) {
                    $values[] = [
                        'qid' => $statusQid,
                        'label' => $label,
                        'rank' => $labels['id']['rank'] ?? $labels['en']['rank'] ?? 'NormalRank',
                    ];
                }
            }
            usort($values, static fn (array $left, array $right): int => [
                $left['rank'] === 'PreferredRank' ? 0 : 1,
                $left['label'],
                $left['qid'],
            ] <=> [
                $right['rank'] === 'PreferredRank' ? 0 : 1,
                $right['label'],
                $right['qid'],
            ]);
            $destination['heritage_statuses'] = array_slice(array_map(
                static fn (array $status): array => ['qid' => $status['qid'], 'label' => $status['label']],
                $values,
            ), 0, 3);
        }
        unset($destination);

        return $destinations;
    }

    /**
     * @param  array<string, array<string, mixed>>  $destinations
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function mergeDestinationElevations(array $destinations, array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $qid = $this->entityQid($this->bindingValue($row, 'item'));
            $amount = $this->literalBindingValue($row, 'amount');
            $normalizedAmount = $this->literalBindingValue($row, 'normalizedAmount');
            $unitQid = $this->entityQid($this->bindingValue($row, 'unit'));
            $normalizedUnitQid = $this->entityQid($this->bindingValue($row, 'normalizedUnit'));
            $rank = $this->rankName($this->bindingValue($row, 'rank'));
            if ($qid === null || ! isset($destinations[$qid])
                || $amount === null || ! is_numeric($amount) || ! is_finite((float) $amount)
                || $rank === null) {
                throw new RuntimeException('Hasil ketinggian Wikidata tidak valid.');
            }
            $meters = $normalizedUnitQid === 'Q11573' && is_numeric($normalizedAmount)
                ? (float) $normalizedAmount
                : ($unitQid === 'Q11573' ? (float) $amount : null);
            if ($meters !== null) {
                if (! is_finite($meters) || $meters < 0) {
                    throw new RuntimeException('Nilai ketinggian Wikidata di luar rentang.');
                }
                $values[$qid][] = ['meters' => $meters, 'rank' => $rank];
            }
        }

        foreach ($destinations as $qid => &$destination) {
            $destination['elevation_m'] = $this->preferredRankedValue($values[$qid] ?? [], 'meters');
        }
        unset($destination);

        return $destinations;
    }

    /**
     * @param  array<string, array<string, mixed>>  $destinations
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function mergeEstablishmentYears(array $destinations, array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $qid = $this->entityQid($this->bindingValue($row, 'item'));
            $date = $this->literalBindingValue($row, 'date');
            $precision = $this->literalBindingValue($row, 'precision');
            $rank = $this->rankName($this->bindingValue($row, 'rank'));
            if ($qid === null || ! isset($destinations[$qid])
                || $date === null || $precision === null || ! ctype_digit($precision)
                || $rank === null) {
                throw new RuntimeException('Hasil tahun berdiri Wikidata tidak valid.');
            }
            if ((int) $precision < 9) {
                continue;
            }
            $year = $this->yearFromWikidataTime($date);
            if ($year === null) {
                throw new RuntimeException('Tanggal tahun berdiri Wikidata tidak valid.');
            }
            $values[$qid][] = ['year' => $year, 'rank' => $rank];
        }

        foreach ($destinations as $qid => &$destination) {
            $destination['establishment_year'] = $this->preferredRankedValue($values[$qid] ?? [], 'year');
        }
        unset($destination);

        return $destinations;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshotRegion(string $name, bool $allowStale): ?array
    {
        try {
            $snapshot = $this->loadSnapshot($allowStale);
            if ($snapshot === null || ! is_array($snapshot['regions'][$name] ?? null)) {
                return null;
            }

            return $snapshot['regions'][$name];
        } catch (Throwable $exception) {
            $this->logLiveFailure($exception, null, 'snapshot');

            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $snapshotRegion
     * @param  array<string, array<string, mixed>>|null  $stale
     * @return array<string, mixed>|null
     */
    private function fallbackRegion(string $qid, ?array $snapshotRegion, ?array $stale = null): ?array
    {
        $stale ??= $this->readCachedEnrichment(self::STALE_CACHE_KEY);
        if (isset($stale[$qid])) {
            return $this->mergeRegionEnrichment($snapshotRegion, $stale[$qid]);
        }

        return $snapshotRegion;
    }

    /**
     * @param  array<string, mixed>|null  $snapshotRegion
     * @param  array<string, mixed>  $enrichment
     * @return array<string, mixed>
     */
    private function mergeRegionEnrichment(?array $snapshotRegion, array $enrichment): array
    {
        $region = [...($snapshotRegion ?? []), ...$enrichment];
        $region['wikidata_label'] = $region['labels']['id']
            ?? $region['labels']['en']
            ?? $region['wikidata_label']
            ?? null;
        $region['wikidata_url'] = OntologyMigrator::WIKIDATA_ENTITY_NS.$enrichment['qid'];

        return $region;
    }

    /**
     * @param  list<string>  $qids
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function mapLiveEnrichment(array $rows, array $qids): array
    {
        $regions = [];
        $allowedQids = array_fill_keys($qids, true);
        foreach ($qids as $qid) {
            $regions[$qid] = [
                'qid' => $qid,
                'labels' => ['id' => null, 'en' => null],
                'aliases' => ['id' => null, 'en' => null],
                'image_filename' => null,
                'image_url' => null,
                'image_page_url' => null,
                'wikipedia_url' => null,
                'wikivoyage_url' => null,
                'wikivoyage_language' => null,
                'heritage_statuses' => [],
                'elevation_m' => null,
                'establishment_year' => null,
            ];
        }

        $labels = [];
        $aliases = [];
        $images = [];
        $links = [];
        $heritage = [];
        $elevations = [];
        $years = [];
        foreach ($rows as $row) {
            $qid = $this->entityQid($this->bindingValue($row, 'item'));
            $kind = $this->literalBindingValue($row, 'kind');
            $language = $this->literalBindingValue($row, 'language');
            $value = $this->literalBindingValue($row, 'value');
            if ($qid === null || ! isset($allowedQids[$qid]) || ! is_string($kind) || ! is_string($language)) {
                throw new RuntimeException('Hasil pengayaan live Wikidata memuat binding yang tidak dikenal.');
            }

            if (in_array($kind, ['label', 'alias'], true)) {
                if (! in_array($language, ['id', 'en'], true) || ! $this->validText($value)) {
                    throw new RuntimeException('Hasil label atau alias live Wikidata tidak valid.');
                }
                if ($kind === 'label') {
                    $labels[$qid][$language][$value] = $value;
                } else {
                    $aliases[$qid][$language][$value] = $value;
                }

                continue;
            }

            if ($kind === 'image') {
                $filename = $this->imageFilenameFromValue($value);
                if ($language !== '' || ! $this->validImageFilename($filename)) {
                    throw new RuntimeException('Hasil nama berkas Commons live Wikidata tidak valid.');
                }
                $images[$qid][$filename] = $filename;

                continue;
            }

            if ($kind === 'heritage') {
                $statusQid = $this->entityQid($this->bindingValue($row, 'status'));
                $rank = $this->rankName($this->bindingValue($row, 'rank'));
                if (! in_array($language, ['id', 'en'], true)
                    || ! $this->validText($value)
                    || $statusQid === null
                    || $rank === null) {
                    throw new RuntimeException('Hasil status cagar budaya live Wikidata tidak valid.');
                }
                $heritage[$qid][$statusQid][$language] = ['label' => $value, 'rank' => $rank];

                continue;
            }

            if ($kind === 'elevation') {
                $amount = $this->literalBindingValue($row, 'amount');
                $rank = $this->rankName($this->bindingValue($row, 'rank'));
                $normalizedAmount = $this->literalBindingValue($row, 'normalizedAmount');
                $unitQid = $this->entityQid($this->bindingValue($row, 'unit'));
                $normalizedUnitQid = $this->entityQid($this->bindingValue($row, 'normalizedUnit'));
                if ($language !== '' || $amount === null || ! is_numeric($amount) || ! is_finite((float) $amount) || $rank === null) {
                    throw new RuntimeException('Hasil ketinggian live Wikidata tidak valid.');
                }
                $meters = $normalizedUnitQid === 'Q11573' && is_numeric($normalizedAmount)
                    ? (float) $normalizedAmount
                    : ($unitQid === 'Q11573' ? (float) $amount : null);
                if ($meters !== null && (! is_finite($meters) || $meters < 0)) {
                    throw new RuntimeException('Nilai ketinggian live Wikidata di luar rentang.');
                }
                if ($meters !== null) {
                    $elevations[$qid][] = ['meters' => $meters, 'rank' => $rank];
                }

                continue;
            }

            if ($kind === 'establishment') {
                $date = $this->literalBindingValue($row, 'date');
                $precision = $this->literalBindingValue($row, 'precision');
                $rank = $this->rankName($this->bindingValue($row, 'rank'));
                if ($language !== '' || $date === null || $precision === null || ! ctype_digit($precision) || $rank === null) {
                    throw new RuntimeException('Hasil tahun berdiri live Wikidata tidak valid.');
                }
                $year = (int) $precision >= 9 ? $this->yearFromWikidataTime($date) : null;
                if ((int) $precision >= 9 && $year === null) {
                    throw new RuntimeException('Nilai tahun berdiri live Wikidata tidak valid.');
                }
                if ($year !== null) {
                    $years[$qid][] = ['year' => $year, 'rank' => $rank];
                }

                continue;
            }

            if (! in_array($kind, ['wikipedia', 'wikivoyage'], true)
                || ! in_array($language, ['id', 'en'], true)
                || ! $this->validSitelink($kind, $language, $value)) {
                throw new RuntimeException('Hasil sitelink live Wikidata tidak valid.');
            }
            $links[$qid][$kind][$language][$value] = $value;
        }

        foreach ($regions as $qid => &$region) {
            foreach (['id', 'en'] as $language) {
                $labelValues = array_values($labels[$qid][$language] ?? []);
                sort($labelValues, SORT_STRING);
                $region['labels'][$language] = $labelValues[0] ?? null;
                $aliasValues = array_values($aliases[$qid][$language] ?? []);
                sort($aliasValues, SORT_STRING);
                $region['aliases'][$language] = $aliasValues === [] ? null : $aliasValues;
            }

            $imageNames = array_values($images[$qid] ?? []);
            sort($imageNames, SORT_STRING);
            $region['image_filename'] = $imageNames[0] ?? null;
            $imageLinks = $region['image_filename'] === null
                ? null
                : $this->commonsImageLinks($region['image_filename']);
            $region['image_url'] = $imageLinks['image_url'] ?? null;
            $region['image_page_url'] = $imageLinks['image_page_url'] ?? null;

            $wikipediaUrls = array_values($links[$qid]['wikipedia']['id'] ?? []);
            sort($wikipediaUrls, SORT_STRING);
            $region['wikipedia_url'] = $wikipediaUrls[0] ?? null;
            foreach (['id', 'en'] as $language) {
                $wikivoyageUrls = array_values($links[$qid]['wikivoyage'][$language] ?? []);
                sort($wikivoyageUrls, SORT_STRING);
                if ($wikivoyageUrls !== []) {
                    $region['wikivoyage_language'] = $language;
                    $region['wikivoyage_url'] = $wikivoyageUrls[0];
                    break;
                }
            }
            $statuses = [];
            foreach ($heritage[$qid] ?? [] as $statusQid => $statusLabels) {
                $label = $statusLabels['id']['label'] ?? $statusLabels['en']['label'] ?? null;
                if (is_string($label)) {
                    $statuses[] = [
                        'qid' => $statusQid,
                        'label' => $label,
                        'rank' => $statusLabels['id']['rank'] ?? $statusLabels['en']['rank'] ?? 'NormalRank',
                    ];
                }
            }
            usort($statuses, static fn (array $left, array $right): int => [
                $left['rank'] === 'PreferredRank' ? 0 : 1,
                $left['label'],
                $left['qid'],
            ] <=> [
                $right['rank'] === 'PreferredRank' ? 0 : 1,
                $right['label'],
                $right['qid'],
            ]);
            $region['heritage_statuses'] = array_slice(array_map(
                static fn (array $status): array => ['qid' => $status['qid'], 'label' => $status['label']],
                $statuses,
            ), 0, 3);
            $region['elevation_m'] = $this->preferredRankedValue($elevations[$qid] ?? [], 'meters');
            $region['establishment_year'] = $this->preferredRankedValue($years[$qid] ?? [], 'year');
        }
        unset($region);

        return $regions;
    }

    /**
     * @param  array<string, array<string, mixed>>  $enrichment
     */
    private function cacheEnrichment(array $enrichment): void
    {
        $payload = ['retrieved_at' => now()->timestamp, 'regions' => $enrichment];
        try {
            Cache::put(self::STALE_CACHE_KEY, $payload, now()->addSeconds($this->staleCacheTtl));
            Cache::put(self::LIVE_CACHE_KEY, $payload, now()->addSeconds($this->liveCacheTtl));
        } catch (Throwable $exception) {
            Log::warning('Pengayaan Wikidata live tidak dapat disimpan ke cache.', [
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readCachedEnrichment(string $key): array
    {
        try {
            $payload = Cache::get($key);
        } catch (Throwable $exception) {
            Log::warning('Cache pengayaan Wikidata tidak tersedia.', [
                'exception' => $exception::class,
            ]);

            return [];
        }

        if (! is_array($payload) || ! is_array($payload['regions'] ?? null)) {
            return [];
        }

        $regions = $payload['regions'];
        foreach ($regions as $qid => $region) {
            if (! is_string($qid) || ! $this->validEnrichment($region, $qid)) {
                Log::warning('Entri cache pengayaan Wikidata tidak valid.', [
                    'region_qid' => is_string($qid) ? $qid : null,
                ]);

                return [];
            }
        }

        return $regions;
    }

    private function isInFailureCooldown(): bool
    {
        try {
            return Cache::get(self::FAILURE_COOLDOWN_KEY) === true;
        } catch (Throwable $exception) {
            Log::warning('Status cooldown Wikidata tidak dapat dibaca.', [
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    /**
     * The cooldown stores only failure status, never Wikidata response data.
     */
    private function markFailureCooldown(): void
    {
        try {
            Cache::put(
                self::FAILURE_COOLDOWN_KEY,
                true,
                now()->addSeconds($this->failureCooldown),
            );
        } catch (Throwable $exception) {
            Log::warning('Cooldown kegagalan Wikidata tidak dapat disimpan.', [
                'exception' => $exception::class,
            ]);
        }
    }

    private function validEnrichment(mixed $region, string $qid): bool
    {
        if (! is_array($region) || ($region['qid'] ?? null) !== $qid
            || ! $this->validLabels($region['labels'] ?? null)
            || ! $this->validAliases($region['aliases'] ?? null)
            || ! $this->validImageFilename($region['image_filename'] ?? null, allowNull: true)
            || ! $this->validImageUrl($region['image_url'] ?? null)
            || ! $this->validImagePageUrl($region['image_page_url'] ?? null)
            || ! $this->validSitelink('wikipedia', 'id', $region['wikipedia_url'] ?? null, allowNull: true)
            || ! $this->validHeritageStatuses($region['heritage_statuses'] ?? null)
            || ! $this->validElevation($region['elevation_m'] ?? null)
            || ! $this->validEstablishmentYear($region['establishment_year'] ?? null)) {
            return false;
        }

        $filename = $region['image_filename'] ?? null;
        $imageLinks = $filename === null ? null : $this->commonsImageLinks($filename);
        if (($region['image_url'] ?? null) !== ($imageLinks['image_url'] ?? null)
            || ($region['image_page_url'] ?? null) !== ($imageLinks['image_page_url'] ?? null)) {
            return false;
        }

        $wikivoyageLanguage = $region['wikivoyage_language'] ?? null;
        $wikivoyageUrl = $region['wikivoyage_url'] ?? null;
        if (($wikivoyageUrl === null) !== ($wikivoyageLanguage === null)
            || ($wikivoyageLanguage !== null && ! is_string($wikivoyageLanguage))
            || ($wikivoyageUrl !== null && ! is_string($wikivoyageUrl))) {
            return false;
        }

        return $this->validSitelink('wikivoyage', $wikivoyageLanguage, $wikivoyageUrl, allowNull: true);
    }

    private function validHeritageStatuses(mixed $statuses): bool
    {
        if (! is_array($statuses) || ! array_is_list($statuses) || count($statuses) > 3) {
            return false;
        }

        $seen = [];
        foreach ($statuses as $status) {
            if (! is_array($status)
                || ! is_string($status['qid'] ?? null)
                || preg_match('/^Q[0-9]+$/', $status['qid']) !== 1
                || isset($seen[$status['qid']])
                || ! $this->validText($status['label'] ?? null)) {
                return false;
            }
            $seen[$status['qid']] = true;
        }

        return true;
    }

    private function validElevation(mixed $elevation): bool
    {
        return $elevation === null
            || (is_numeric($elevation) && is_finite((float) $elevation) && (float) $elevation >= 0);
    }

    private function validEstablishmentYear(mixed $year): bool
    {
        return $year === null || (is_int($year) && $year >= 1 && $year <= 9999);
    }

    private function validLabels(mixed $labels): bool
    {
        if (! is_array($labels) || array_diff(array_keys($labels), ['id', 'en']) !== []) {
            return false;
        }

        foreach (['id', 'en'] as $language) {
            if (($labels[$language] ?? null) !== null && ! $this->validText($labels[$language])) {
                return false;
            }
        }

        return true;
    }

    private function validAliases(mixed $aliases): bool
    {
        if (! is_array($aliases) || array_diff(array_keys($aliases), ['id', 'en']) !== []) {
            return false;
        }

        foreach (['id', 'en'] as $language) {
            if (($aliases[$language] ?? null) === null) {
                continue;
            }
            if (! is_array($aliases[$language]) || ! array_is_list($aliases[$language])) {
                return false;
            }
            foreach ($aliases[$language] as $alias) {
                if (! $this->validText($alias)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function validText(mixed $value): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value) <= 500
            && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) !== 1;
    }

    private function validImageFilename(mixed $filename, bool $allowNull = false): bool
    {
        if ($filename === null) {
            return $allowNull;
        }

        return is_string($filename)
            && trim($filename) !== ''
            && mb_check_encoding($filename, 'UTF-8')
            && mb_strlen($filename) <= 255
            && preg_match('/[\x00-\x1F\x7F\/\\\\]/u', $filename) !== 1
            && preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $filename) !== 1;
    }

    private function validImageUrl(mixed $url): bool
    {
        return $url === null
            || (is_string($url)
                && $this->imageUrl($url) === $url
                && str_starts_with($url, 'https://'));
    }

    private function validImagePageUrl(mixed $url): bool
    {
        if ($url === null) {
            return true;
        }

        $parts = is_string($url) ? parse_url($url) : false;

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'commons.wikimedia.org'
            && str_starts_with((string) ($parts['path'] ?? ''), '/wiki/File:')
            && ! isset($parts['user'], $parts['pass'], $parts['port'], $parts['query'], $parts['fragment']);
    }

    private function validSitelink(string $kind, mixed $language, mixed $url, bool $allowNull = false): bool
    {
        if ($url === null) {
            return $allowNull;
        }

        if (! is_string($language) || ! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        $host = $kind === 'wikipedia'
            ? 'id.wikipedia.org'
            : ($language === 'id' ? 'id.wikivoyage.org' : 'en.wikivoyage.org');

        return is_array($parts)
            && in_array($kind, ['wikipedia', 'wikivoyage'], true)
            && ($kind !== 'wikipedia' || $language === 'id')
            && in_array($language, ['id', 'en'], true)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === $host
            && str_starts_with((string) ($parts['path'] ?? ''), '/wiki/')
            && ! isset($parts['user'], $parts['pass'], $parts['port'], $parts['query'], $parts['fragment']);
    }

    /**
     * @return array{image_url: string, image_page_url: string}|null
     */
    private function commonsImageLinks(string $filename): ?array
    {
        if (! $this->validImageFilename($filename)) {
            return null;
        }
        $encodedFilename = rawurlencode(trim($filename));

        return [
            'image_url' => 'https://commons.wikimedia.org/wiki/Special:FilePath/'.$encodedFilename,
            'image_page_url' => 'https://commons.wikimedia.org/wiki/File:'.$encodedFilename,
        ];
    }

    private function imageFilenameFromValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $parts = parse_url($value);
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || strtolower((string) ($parts['host'] ?? '')) !== 'commons.wikimedia.org'
            || ! str_starts_with((string) ($parts['path'] ?? ''), '/wiki/Special:FilePath/')
            || isset($parts['user'], $parts['pass'], $parts['port'], $parts['query'], $parts['fragment'])) {
            return null;
        }

        $filename = rawurldecode(substr($parts['path'], strlen('/wiki/Special:FilePath/')));

        return $this->validImageFilename($filename) ? $filename : null;
    }

    private function logLiveFailure(Throwable $exception, ?string $qid, string $phase): void
    {
        Log::warning('Pengayaan Wikidata live gagal; menggunakan cadangan lokal.', [
            'exception' => $exception::class,
            'region_qid' => $qid,
            'phase' => $phase,
        ]);
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
                'wikidata_url' => OntologyMigrator::WIKIDATA_ENTITY_NS.$qid,
                'latitude' => null,
                'longitude' => null,
                'image_filename' => null,
                'image_url' => null,
                'image_page_url' => null,
                'website_url' => null,
                'population' => null,
                'area' => null,
                'wikipedia_url' => null,
                'wikivoyage_url' => null,
                'wikivoyage_language' => null,
                'labels' => ['id' => null, 'en' => null],
                'aliases' => ['id' => null, 'en' => null],
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

            $details[$qid] ??= ['coordinates' => [], 'websites' => [], 'population' => [], 'area' => []];
            $coordinate = $this->coordinate($this->bindingValue($row, 'coordinate'));
            if ($coordinate !== null) {
                $details[$qid]['coordinates'][json_encode($coordinate, JSON_THROW_ON_ERROR)] = $coordinate;
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
            $regionDetails = $details[$qid] ?? ['coordinates' => [], 'websites' => [], 'population' => [], 'area' => []];
            if (count($regionDetails['coordinates']) > 1) {
                throw new RuntimeException("Wikidata mengembalikan koordinat yang saling bertentangan untuk {$qid}.");
            }

            $coordinate = array_values($regionDetails['coordinates'])[0] ?? null;
            $region['latitude'] = $coordinate['latitude'] ?? null;
            $region['longitude'] = $coordinate['longitude'] ?? null;
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
     * @param  array<string, array<string, mixed>>  $regions
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function mergeLabelsAndAliases(array $regions, array $rows): array
    {
        $values = [];
        foreach ($regions as $qid => $_region) {
            $values[$qid] = ['labels' => ['id' => [], 'en' => []], 'aliases' => ['id' => [], 'en' => []]];
        }

        foreach ($rows as $row) {
            $qid = $this->entityQid($this->bindingValue($row, 'item'));
            $kind = $this->literalBindingValue($row, 'kind');
            $language = $this->literalBindingValue($row, 'language');
            $value = $this->literalBindingValue($row, 'value');
            if ($qid === null || ! isset($regions[$qid])
                || ! in_array($kind, ['label', 'alias'], true)
                || ! in_array($language, ['id', 'en'], true)
                || ! $this->validText($value)) {
                throw new RuntimeException('Hasil label atau alias Wikidata tidak valid.');
            }

            $group = $kind === 'label' ? 'labels' : 'aliases';
            $values[$qid][$group][$language][$value] = $value;
        }

        foreach ($regions as $qid => &$region) {
            foreach (['id', 'en'] as $language) {
                $labels = array_values($values[$qid]['labels'][$language]);
                sort($labels, SORT_STRING);
                $aliases = array_values($values[$qid]['aliases'][$language]);
                sort($aliases, SORT_STRING);
                $region['labels'][$language] = $labels[0] ?? null;
                $region['aliases'][$language] = $aliases === [] ? null : $aliases;
            }
        }
        unset($region);

        return $regions;
    }

    /**
     * @param  array<string, array<string, mixed>>  $regions
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function mergeSitelinks(array $regions, array $rows): array
    {
        $links = [];
        foreach ($rows as $row) {
            $qid = $this->entityQid($this->bindingValue($row, 'item'));
            $kind = $this->literalBindingValue($row, 'kind');
            $language = $this->literalBindingValue($row, 'language');
            $url = $this->literalBindingValue($row, 'url');
            if ($qid === null || ! isset($regions[$qid])
                || ! in_array($kind, ['wikipedia', 'wikivoyage'], true)
                || ! in_array($language, ['id', 'en'], true)
                || ! $this->validSitelink($kind, $language, $url)) {
                throw new RuntimeException('Hasil sitelink Wikidata tidak valid.');
            }

            $links[$qid][$kind][$language][$url] = $url;
        }

        foreach ($regions as $qid => &$region) {
            $wikipediaUrls = array_values($links[$qid]['wikipedia']['id'] ?? []);
            sort($wikipediaUrls, SORT_STRING);
            $region['wikipedia_url'] = $wikipediaUrls[0] ?? null;

            $wikivoyageLanguage = null;
            $wikivoyageUrl = null;
            foreach (['id', 'en'] as $language) {
                $urls = array_values($links[$qid]['wikivoyage'][$language] ?? []);
                sort($urls, SORT_STRING);
                if ($urls !== []) {
                    $wikivoyageLanguage = $language;
                    $wikivoyageUrl = $urls[0];
                    break;
                }
            }

            $region['wikivoyage_url'] = $wikivoyageUrl;
            $region['wikivoyage_language'] = $wikivoyageLanguage;
        }
        unset($region);

        return $regions;
    }

    /**
     * @param  array<string, array<string, mixed>>  $regions
     * @param  list<array<string, array{type: string, value: string}>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function mergeImages(array $regions, array $rows): array
    {
        $filenames = [];
        foreach ($rows as $row) {
            $qid = $this->entityQid($this->bindingValue($row, 'item'));
            $filename = $this->imageFilenameFromValue($this->bindingValue($row, 'image'));
            if ($qid === null || ! isset($regions[$qid]) || ! $this->validImageFilename($filename)) {
                throw new RuntimeException('Hasil gambar Commons Wikidata tidak valid.');
            }

            $filenames[$qid][$filename] = $filename;
        }

        foreach ($regions as $qid => &$region) {
            $regionFilenames = array_values($filenames[$qid] ?? []);
            sort($regionFilenames, SORT_STRING);
            $filename = $regionFilenames[0] ?? null;
            $links = $filename === null ? null : $this->commonsImageLinks($filename);
            $region['image_filename'] = $filename;
            $region['image_url'] = $links['image_url'] ?? null;
            $region['image_page_url'] = $links['image_page_url'] ?? null;
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

    /**
     * @param  array<string, array{type: string, value: string}>  $row
     */
    private function literalBindingValue(array $row, string $key): ?string
    {
        $binding = $row[$key] ?? null;

        return is_array($binding)
            && in_array($binding['type'] ?? null, ['literal', 'typed-literal'], true)
            && is_string($binding['value'] ?? null)
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

    /**
     * @param  list<array{rank: string, meters?: float, year?: int}>  $records
     */
    private function preferredRankedValue(array $records, string $field): int|float|null
    {
        if ($records === []) {
            return null;
        }

        usort($records, static fn (array $left, array $right): int => [
            $left['rank'] === 'PreferredRank' ? 0 : 1,
            $left[$field],
        ] <=> [
            $right['rank'] === 'PreferredRank' ? 0 : 1,
            $right[$field],
        ]);

        return $records[0][$field];
    }

    private function rankName(?string $rank): ?string
    {
        if ($rank === null || preg_match('~(?:#|/)(PreferredRank|NormalRank)$~', $rank, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function yearFromWikidataTime(string $date): ?int
    {
        if (preg_match('/^\+?(\d{4,})-/', $date, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[1];

        return $year >= 1 && $year <= 9999 ? $year : null;
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
        $this->writeSnapshotToPath($snapshot, $this->snapshotPath, $this->staleSnapshotPath);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function writeDestinationSnapshot(array $snapshot): void
    {
        if ($this->destinationSnapshotPath !== '') {
            $this->writeSnapshotToPath($snapshot, $this->destinationSnapshotPath, $this->staleDestinationSnapshotPath);
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function writeSnapshotToPath(array $snapshot, string $snapshotPath, string $stalePath): void
    {
        $directory = dirname($snapshotPath);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Direktori snapshot Wikidata tidak dapat dibuat: {$directory}");
        }

        if (is_file($snapshotPath)) {
            $staleDirectory = dirname($stalePath);
            if (! is_dir($staleDirectory) && ! mkdir($staleDirectory, 0755, true) && ! is_dir($staleDirectory)) {
                throw new RuntimeException("Direktori snapshot stale Wikidata tidak dapat dibuat: {$staleDirectory}");
            }
            if (! copy($snapshotPath, $stalePath)) {
                throw new RuntimeException('Snapshot Wikidata sebelumnya tidak dapat dipertahankan sebagai fallback.');
            }
        }

        try {
            $contents = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException('Snapshot Wikidata tidak dapat diserialisasi.', previous: $exception);
        }

        $written = file_put_contents($snapshotPath, $contents, LOCK_EX);
        if ($written !== strlen($contents)) {
            if (is_file($stalePath)) {
                if (! copy($stalePath, $snapshotPath)) {
                    throw new RuntimeException('Penulisan snapshot gagal dan snapshot sebelumnya tidak dapat dipulihkan.');
                }
            } elseif (is_file($snapshotPath) && ! unlink($snapshotPath)) {
                throw new RuntimeException('Penulisan snapshot gagal dan berkas parsial tidak dapat dibersihkan.');
            }

            throw new RuntimeException("Snapshot detail Wikidata tidak dapat ditulis: {$snapshotPath}");
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
            || ! in_array($snapshot['schema_version'] ?? null, [1, 2], true)
            || ! is_string($snapshot['retrieved_at'] ?? null)
            || ! is_array($snapshot['regions'] ?? null)) {
            throw new InvalidArgumentException('Struktur snapshot detail Wikidata tidak valid.');
        }

        foreach ($snapshot['regions'] as $name => $region) {
            if (! is_string($name) || ! is_array($region)) {
                throw new InvalidArgumentException('Entri wilayah pada snapshot detail Wikidata tidak valid.');
            }
            $region += [
                'image_filename' => null,
                'image_page_url' => null,
                'wikipedia_url' => null,
                'wikivoyage_url' => null,
                'wikivoyage_language' => null,
                'labels' => ['id' => null, 'en' => null],
                'aliases' => ['id' => null, 'en' => null],
            ];
            $snapshot['regions'][$name] = $region;

            if (! is_string($region['qid'] ?? null)
                || preg_match('/^Q[0-9]+$/', $region['qid']) !== 1
                || ! in_array($region['type'] ?? null, ['Kabupaten', 'Kota'], true)
                || ! is_string($region['wikidata_label'] ?? null)
                || ($region['wikidata_url'] ?? null) !== OntologyMigrator::WIKIDATA_ENTITY_NS.$region['qid']
                || ! $this->validCoordinatePair($region['latitude'] ?? null, $region['longitude'] ?? null)
                || ! $this->validSnapshotImage($region['image_url'] ?? null)
                || ! $this->validImageFilename($region['image_filename'], allowNull: true)
                || ! $this->validImagePageUrl($region['image_page_url'])
                || ! $this->validLabels($region['labels'])
                || ! $this->validAliases($region['aliases'])
                || ! $this->validSitelink('wikipedia', 'id', $region['wikipedia_url'], allowNull: true)
                || ! $this->validSitelink('wikivoyage', $region['wikivoyage_language'], $region['wikivoyage_url'], allowNull: true)
                || (($region['wikivoyage_url'] === null) !== ($region['wikivoyage_language'] === null))
                || ! $this->validSnapshotWebsite($region['website_url'] ?? null)
                || ! $this->validQuantity($region['population'] ?? null)
                || ! $this->validQuantity($region['area'] ?? null)) {
                throw new InvalidArgumentException('Entri wilayah pada snapshot detail Wikidata tidak valid.');
            }
            if ($region['image_filename'] !== null) {
                $imageLinks = $this->commonsImageLinks($region['image_filename']);
                if ($region['image_url'] !== $imageLinks['image_url']
                    || $region['image_page_url'] !== $imageLinks['image_page_url']) {
                    throw new InvalidArgumentException('Tautan berkas Commons pada snapshot detail Wikidata tidak konsisten.');
                }
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
