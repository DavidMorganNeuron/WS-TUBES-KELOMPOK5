<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Ontology\WikidataClient;
use App\Services\Ontology\WikidataDestinationLinkCatalog;
use App\Services\Ontology\WikidataQuery;
use App\Services\Ontology\WikidataQueryBuilder;
use App\Services\Ontology\WikidataRegionCatalog;
use App\Services\Ontology\WikidataRegionRepository;
use App\Services\Ontology\WikidataUserAgent;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

final class WikidataIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetLiveCache();
    }

    public function test_query_builder_accepts_only_validated_qids(): void
    {
        $builder = new WikidataQueryBuilder;
        $query = $builder->regions(['Q2', 'Q1'], 'Q3', 'Q4');

        $this->assertStringContainsString('wd:Q1 wd:Q2', $query->query);
        $this->assertStringContainsString('wd:Q3', $query->query);
        $this->assertStringContainsString('wd:Q4', $query->query);
        $this->assertStringNotContainsString('Kabupaten', $query->query);
        $aroundQuery = $builder->nearbyEntities(['Q1']);
        $this->assertStringContainsString('wikibase:around', $aroundQuery->query);
        $this->assertStringNotContainsString('ws-', $aroundQuery->query);
        $this->assertStringContainsString('wdt:P856', $builder->details(['Q1'])->query);
        $this->assertStringNotContainsString('P18', $builder->details(['Q1'])->query);
        $this->assertStringContainsString('wdt:P18', $builder->images(['Q1'])->query);
        $this->assertStringContainsString('schema:isPartOf', $builder->sitelinks(['Q1'])->query);
        $this->assertStringContainsString('skos:altLabel', $builder->labelsAndAliases(['Q1'])->query);
        $this->assertStringContainsString('UNION', $builder->liveEnrichment(['Q1'])->query);
        $this->assertStringNotContainsString('airport', strtolower($builder->liveEnrichment(['Q1'])->query));

        $this->expectException(InvalidArgumentException::class);
        $builder->boundaries(['Q1 } SERVICE <https://example.test/> {']);
    }

    public function test_refresh_command_help_is_in_indonesian(): void
    {
        $this->assertSame(0, Artisan::call('wikidata:refresh', ['--help' => true]));
        $output = Artisan::output();

        $this->assertStringContainsString('Perbarui snapshot Wikidata wilayah dan destinasi', $output);
        $this->assertStringContainsString('Tampilkan snapshot tersimpan jika pembaruan live gagal', $output);
        $this->assertStringNotContainsString('Generate a destination', $output);
    }

    public function test_doctor_checks_wikidata_only_when_eksternal_is_requested(): void
    {
        Http::preventStrayRequests();
        config()->set('app.env', 'production');
        config()->set('ontology.wikidata.contact', '');
        Http::fake([
            'http://localhost:3030/*' => Http::response([], 503),
        ]);

        $this->artisan('ontology:doctor')
            ->expectsOutputToContain('D9: EXTERNAL_CONTACT belum diatur pada lingkungan production.')
            ->expectsOutputToContain('https://github.com/DavidMorganNeuron/WS-TUBES-KELOMPOK5')
            ->assertFailed();
        Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), WikidataClient::ENDPOINT));

        Http::fake([
            WikidataClient::ENDPOINT.'*' => Http::response($this->sparqlResponse([
                [
                    'item' => $this->uri('http://www.wikidata.org/entity/Q1795'),
                    'coordinate' => $this->literal('Point(99.1 3.0)'),
                ],
            ]), 200, ['Content-Type' => 'application/sparql-results+json']),
            'http://localhost:3030/*' => Http::response([], 503),
        ]);

        $this->artisan('ontology:doctor', ['--eksternal' => true])->assertFailed();
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), WikidataClient::ENDPOINT));
    }

    public function test_user_agent_uses_normalized_public_fallback_without_reading_env_files(): void
    {
        $this->assertSame(
            'WayFinder/1.0 (https://github.com/DavidMorganNeuron/WS-TUBES-KELOMPOK5)',
            WikidataUserAgent::resolve(
                '',
                'https://github.com/DavidMorganNeuron/WS-TUBES-KELOMPOK5.git',
            ),
        );
        $this->assertSame(
            'WayFinder/1.0 (contact@example.test)',
            WikidataUserAgent::resolve('contact@example.test', 'https://example.test'),
        );
    }

    public function test_client_uses_get_for_small_queries_and_posts_long_queries_with_user_agent(): void
    {
        Http::fake([
            WikidataClient::ENDPOINT => Http::response($this->sparqlResponse([]), 200, [
                'Content-Type' => 'application/sparql-results+json',
            ]),
        ]);
        $query = new WikidataQuery('SELECT * WHERE { wd:Q1 ?p ?o }', ['Q1']);

        $this->client(getQueryMaxBytes: 2000)->select($query, refresh: true);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->hasHeader('User-Agent', 'WayFinder test/1.0')
            && str_contains($request->url(), 'query='));

        Http::fake([
            WikidataClient::ENDPOINT => Http::response($this->sparqlResponse([]), 200, [
                'Content-Type' => 'application/sparql-results+json',
            ]),
        ]);
        $query = new WikidataQuery('SELECT * WHERE { VALUES ?item { wd:Q1 } }', ['Q1']);
        $this->client(getQueryMaxBytes: 20)->select($query, refresh: true);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->hasHeader('User-Agent', 'WayFinder test/1.0')
            && ($request->data()['query'] ?? null) === $query->query);
    }

    public function test_client_retries_a_rate_limit_once_and_rejects_oversized_responses(): void
    {
        $attempt = 0;
        Http::fake(function () use (&$attempt) {
            $attempt++;

            if ($attempt === 1) {
                return Http::response('', 429, ['Retry-After' => '0']);
            }

            return Http::response($this->sparqlResponse([]), 200, [
                'Content-Type' => 'application/sparql-results+json',
            ]);
        });

        $this->client(maxRetries: 1, maxRetryAfter: 0)->select(
            new WikidataQuery('SELECT * WHERE { wd:Q1 ?p ?o }', ['Q1']),
            refresh: true,
        );
        Http::assertSentCount(2);

        Http::fake([
            WikidataClient::ENDPOINT => Http::response(str_repeat('x', 32), 200, [
                'Content-Type' => 'application/sparql-results+json',
            ]),
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('melebihi batas ukuran');
        $this->client(maxResponseBytes: 16)->select(
            new WikidataQuery('SELECT * WHERE { wd:Q1 ?p ?o }', ['Q1']),
            refresh: true,
        );
    }

    public function test_refresh_verifies_live_region_identity_details_and_all_approved_boundaries(): void
    {
        $catalog = new WikidataRegionCatalog(base_path((string) config('ontology.wikidata_regions_path')));
        $source = $catalog->load();
        $responses = [
            'identity' => $this->identityRows($source),
            'details' => $this->detailRows($source),
            'labels' => $this->labelRows($source),
            'sitelinks' => $this->sitelinkRows($source),
            'images' => $this->imageRows($source),
            'boundaries' => $this->boundaryRows($source),
        ];

        Http::fake(function (Request $request) use ($responses) {
            $query = (string) ($request->data()['query'] ?? '');
            if (str_contains($query, '?country')) {
                $rows = $responses['identity'];
            } elseif (str_contains($query, '?coordinate')) {
                $rows = $responses['details'];
            } elseif (str_contains($query, 'schema:isPartOf')) {
                $rows = $responses['sitelinks'];
            } elseif (str_contains($query, '?image')) {
                $rows = $responses['images'];
            } elseif (str_contains($query, 'skos:altLabel')) {
                $rows = $responses['labels'];
            } else {
                $rows = $responses['boundaries'];
            }

            return Http::response($this->sparqlResponse($rows), 200, [
                'Content-Type' => 'application/sparql-results+json',
            ]);
        });

        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wayfinder-wikidata-'.Str::uuid();
        File::makeDirectory($root, 0755, true);

        try {
            $repository = new WikidataRegionRepository(
                client: $this->client(getQueryMaxBytes: 1),
                queryBuilder: new WikidataQueryBuilder,
                catalog: $catalog,
                snapshotPath: $root.DIRECTORY_SEPARATOR.'latest.json',
                staleSnapshotPath: $root.DIRECTORY_SEPARATOR.'stale.json',
                snapshotTtl: 86400,
                requestIntervalSeconds: 0,
                liveTimeout: 3,
                liveCacheTtl: 3600,
                staleCacheTtl: 86400,
                liveLockSeconds: 2,
                failureCooldown: 60,
            );
            $snapshot = $repository->refresh();
            $firstRegion = array_values($snapshot['regions'])[0];

            $this->assertSame(2, $snapshot['schema_version']);
            $this->assertSame(30, $snapshot['verification']['regions']);
            $this->assertSame(51, $snapshot['verification']['boundary_pairs']);
            $this->assertSame(51, $snapshot['verification']['directed_boundary_edges']);
            $this->assertSame(2.5, $firstRegion['latitude']);
            $this->assertSame(98.5, $firstRegion['longitude']);
            $this->assertSame('Foto (1) #1.jpg', $firstRegion['image_filename']);
            $this->assertSame('https://commons.wikimedia.org/wiki/Special:FilePath/Foto%20%281%29%20%231.jpg', $firstRegion['image_url']);
            $this->assertSame('https://commons.wikimedia.org/wiki/File:Foto%20%281%29%20%231.jpg', $firstRegion['image_page_url']);
            $this->assertSame('https://wilayah.example.test/', $firstRegion['website_url']);
            $this->assertSame('Kabupaten Asahan', $firstRegion['labels']['id']);
            $this->assertSame('Asahan Regency', $firstRegion['labels']['en']);
            $this->assertSame(['Kab. Asahan'], $firstRegion['aliases']['id']);
            $this->assertSame('https://id.wikipedia.org/wiki/Kabupaten_Asahan', $firstRegion['wikipedia_url']);
            $this->assertSame('https://en.wikivoyage.org/wiki/Asahan', $firstRegion['wikivoyage_url']);
            $this->assertSame('en', $firstRegion['wikivoyage_language']);
            $secondRegion = array_values($snapshot['regions'])[1];
            $this->assertNull($secondRegion['website_url']);
            $this->assertNull($secondRegion['wikipedia_url']);
            $this->assertSame('https://id.wikivoyage.org/wiki/Batu_Bara', $secondRegion['wikivoyage_url']);
            $this->assertSame('id', $secondRegion['wikivoyage_language']);
            $this->assertSame('100000', $firstRegion['population']['amount']);
            $this->assertSame('700', $firstRegion['area']['normalized']['amount']);
            $snapshot['retrieved_at'] = now()->subDays(2)->toIso8601String();
            File::put($root.DIRECTORY_SEPARATOR.'latest.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
            $this->assertNull($repository->loadSnapshot());
            $this->assertTrue($repository->loadSnapshot(allowStale: true)['is_stale']);

            Http::assertSentCount(6);
            Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent', 'WayFinder test/1.0')
                && ! str_contains((string) ($request->data()['query'] ?? ''), 'ws-'));
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_live_enrichment_uses_one_batch_request_and_caches_all_regions(): void
    {
        Http::preventStrayRequests();
        $this->forgetLiveCache();
        Http::fake([
            WikidataClient::ENDPOINT.'*' => Http::response(
                $this->sparqlResponse($this->liveRows('Q1795')),
                200,
                ['Content-Type' => 'application/sparql-results+json'],
            ),
        ]);

        $repository = $this->repository();
        $region = $repository->findForRegion('Kabupaten Asahan');

        $this->assertSame('Kabupaten Asahan', $region['labels']['id']);
        $this->assertSame('Kab. Asahan', $region['aliases']['id'][0]);
        $this->assertSame('Foto #1.jpg', $region['image_filename']);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:Foto%20%231.jpg', $region['image_page_url']);
        $this->assertSame('https://id.wikipedia.org/wiki/Kabupaten_Asahan', $region['wikipedia_url']);
        $this->assertSame('https://en.wikivoyage.org/wiki/Asahan', $region['wikivoyage_url']);
        $this->assertSame('en', $region['wikivoyage_language']);
        $this->assertSame('Q1795', Cache::get(WikidataRegionRepository::LIVE_CACHE_KEY)['regions']['Q1795']['qid']);

        $this->assertNotNull($repository->findForRegion('Kabupaten Dairi'));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains(
            (string) ($request->data()['query'] ?? ''),
            'VALUES ?item',
        ) && substr_count((string) ($request->data()['query'] ?? ''), 'wd:Q') === 30);
    }

    public function test_destination_and_region_share_one_live_batch_and_map_approved_facts(): void
    {
        Http::preventStrayRequests();
        $this->forgetLiveCache();
        $destinationQid = 'Q136687552';
        $rows = [
            ...$this->liveRows('Q1795'),
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$destinationQid),
                'kind' => $this->literal('label'),
                'language' => $this->literal('id'),
                'value' => $this->literal('Kebun Binatang Medan'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$destinationQid),
                'kind' => $this->literal('alias'),
                'language' => $this->literal('id'),
                'value' => $this->literal('Medan Zoo'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$destinationQid),
                'kind' => $this->literal('heritage'),
                'language' => $this->literal('id'),
                'value' => $this->literal('Cagar budaya'),
                'status' => $this->uri('http://www.wikidata.org/entity/Q1234'),
                'rank' => $this->uri('http://wikiba.se/ontology#PreferredRank'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$destinationQid),
                'kind' => $this->literal('elevation'),
                'language' => $this->literal(''),
                'amount' => $this->literal('2157'),
                'unit' => $this->uri('http://www.wikidata.org/entity/Q11573'),
                'rank' => $this->uri('http://wikiba.se/ontology#PreferredRank'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$destinationQid),
                'kind' => $this->literal('establishment'),
                'language' => $this->literal(''),
                'date' => $this->literal('+1887-00-00T00:00:00Z'),
                'precision' => $this->literal('9'),
                'rank' => $this->uri('http://wikiba.se/ontology#NormalRank'),
            ],
        ];
        Http::fake([
            WikidataClient::ENDPOINT.'*' => Http::response(
                $this->sparqlResponse($rows),
                200,
                ['Content-Type' => 'application/sparql-results+json'],
            ),
        ]);
        $catalog = new WikidataRegionCatalog(base_path((string) config('ontology.wikidata_regions_path')));
        $repository = new WikidataRegionRepository(
            client: $this->client(),
            queryBuilder: new WikidataQueryBuilder,
            catalog: $catalog,
            snapshotPath: base_path((string) config('ontology.wikidata.snapshot_path')),
            staleSnapshotPath: base_path((string) config('ontology.wikidata.stale_snapshot_path')),
            snapshotTtl: (int) config('ontology.wikidata.snapshot_ttl'),
            requestIntervalSeconds: 0,
            liveTimeout: (int) config('ontology.wikidata.live_timeout'),
            liveCacheTtl: (int) config('ontology.wikidata.live_cache_ttl'),
            staleCacheTtl: (int) config('ontology.wikidata.stale_cache_ttl'),
            liveLockSeconds: (int) config('ontology.wikidata.live_lock_seconds'),
            failureCooldown: (int) config('ontology.wikidata.failure_cooldown'),
            destinationLinks: new WikidataDestinationLinkCatalog(
                base_path((string) config('ontology.wikidata.destination_links_path')),
            ),
        );

        $pageData = $repository->findForDestinationPage('ws-049', 'Kabupaten Asahan');

        $this->assertSame('Kebun Binatang Medan', $pageData['destination']['labels']['id']);
        $this->assertSame(2157.0, $pageData['destination']['elevation_m']);
        $this->assertSame(1887, $pageData['destination']['establishment_year']);
        $this->assertSame('Cagar budaya', $pageData['destination']['heritage_statuses'][0]['label']);
        $this->assertSame('http://www.wikidata.org/entity/'.$destinationQid, $pageData['destination']['wikidata_url']);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains(
            (string) ($request->data()['query'] ?? ''),
            'wd:'.$destinationQid,
        ) && str_contains((string) ($request->data()['query'] ?? ''), 'wd:Q1795')
            && str_contains((string) ($request->data()['query'] ?? ''), 'p:P1435')
            && str_contains((string) ($request->data()['query'] ?? ''), 'p:P2044')
            && str_contains((string) ($request->data()['query'] ?? ''), 'p:P571'));
    }

    public function test_live_failure_uses_stale_cache_and_cooldown_prevents_a_second_request(): void
    {
        Http::preventStrayRequests();
        $this->forgetLiveCache();
        Cache::put(WikidataRegionRepository::STALE_CACHE_KEY, [
            'retrieved_at' => now()->subDay()->timestamp,
            'regions' => ['Q1795' => $this->enrichment('Q1795', 'https://id.wikipedia.org/wiki/Cache_Lama')],
        ], now()->addDay());
        Http::fake([
            WikidataClient::ENDPOINT.'*' => Http::response('', 503),
        ]);

        $repository = $this->repository();
        $first = $repository->findForRegion('Kabupaten Asahan');
        $second = $repository->findForRegion('Kabupaten Asahan');

        $this->assertSame('https://id.wikipedia.org/wiki/Cache_Lama', $first['wikipedia_url']);
        $this->assertSame($first['wikipedia_url'], $second['wikipedia_url']);
        $this->assertTrue(Cache::get(WikidataRegionRepository::FAILURE_COOLDOWN_KEY));
        Http::assertSentCount(1);
    }

    public function test_invalid_live_sitelinks_are_rejected_without_caching_response_data(): void
    {
        Http::preventStrayRequests();
        $this->forgetLiveCache();
        $rows = [
            ...$this->liveRows('Q1795'),
            [
                'item' => $this->uri('http://www.wikidata.org/entity/Q1795'),
                'kind' => $this->literal('wikipedia'),
                'language' => $this->literal('id'),
                'value' => $this->literal('javascript:alert(1)'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/Q1795'),
                'kind' => $this->literal('wikivoyage'),
                'language' => $this->literal('en'),
                'value' => $this->literal('https://evil.example/wiki/Travel'),
            ],
        ];
        Http::fake([
            WikidataClient::ENDPOINT.'*' => Http::response(
                $this->sparqlResponse($rows),
                200,
                ['Content-Type' => 'application/sparql-results+json'],
            ),
        ]);

        $region = $this->repository()->findForRegion('Kabupaten Asahan');

        $this->assertNotNull($region);
        $this->assertNull(Cache::get(WikidataRegionRepository::LIVE_CACHE_KEY));
        $this->assertNull(Cache::get(WikidataRegionRepository::STALE_CACHE_KEY));
        $this->assertTrue(Cache::get(WikidataRegionRepository::FAILURE_COOLDOWN_KEY));
        $this->assertSame('http://www.wikidata.org/entity/Q1795', $region['wikidata_url']);
        Http::assertSentCount(1);
    }

    public function test_oversized_live_response_falls_back_without_throwing(): void
    {
        Http::preventStrayRequests();
        $this->forgetLiveCache();
        Http::fake([
            WikidataClient::ENDPOINT.'*' => Http::response(str_repeat('x', 64), 200, [
                'Content-Type' => 'application/sparql-results+json',
            ]),
        ]);
        $repository = $this->repository($this->client(maxResponseBytes: 16));

        $this->assertNotNull($repository->findForRegion('Kabupaten Asahan'));
        $this->assertTrue(Cache::get(WikidataRegionRepository::FAILURE_COOLDOWN_KEY));
        Http::assertSentCount(1);
    }

    public function test_cache_store_failure_does_not_discard_valid_live_data(): void
    {
        Http::preventStrayRequests();
        $this->forgetLiveCache();
        Http::fake([
            WikidataClient::ENDPOINT.'*' => Http::response(
                $this->sparqlResponse($this->liveRows('Q1795')),
                200,
                ['Content-Type' => 'application/sparql-results+json'],
            ),
        ]);
        $lock = \Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once()->andReturn(true);
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('lock')->once()->andReturn($lock);
        Cache::shouldReceive('put')->andThrow(new RuntimeException('cache unavailable'));

        $region = $this->repository()->findForRegion('Kabupaten Asahan');

        $this->assertSame('https://id.wikipedia.org/wiki/Kabupaten_Asahan', $region['wikipedia_url']);
        Http::assertSentCount(1);
    }

    public function test_cache_read_and_lock_failures_fall_back_to_the_snapshot_without_http(): void
    {
        Http::preventStrayRequests();
        Cache::shouldReceive('get')->andThrow(new RuntimeException('cache unavailable'));
        Cache::shouldReceive('lock')->once()->andThrow(new RuntimeException('lock unavailable'));

        $region = $this->repository()->findForRegion('Kabupaten Asahan');

        $this->assertSame('http://www.wikidata.org/entity/Q1795', $region['wikidata_url']);
        Http::assertSentCount(0);
    }

    public function test_live_failure_returns_null_when_no_cache_or_snapshot_exists(): void
    {
        Http::preventStrayRequests();
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wayfinder-wikidata-empty-'.Str::uuid();
        File::makeDirectory($root, 0755, true);
        Http::fake([
            WikidataClient::ENDPOINT.'*' => Http::response('', 503),
        ]);

        try {
            $repository = $this->repository(
                snapshotPath: $root.DIRECTORY_SEPARATOR.'missing.json',
                staleSnapshotPath: $root.DIRECTORY_SEPARATOR.'missing-stale.json',
            );
            $this->assertNull($repository->findForRegion('Kabupaten Asahan'));
            Http::assertSentCount(1);
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_invalid_batch_qids_are_rejected_before_an_http_request(): void
    {
        Http::preventStrayRequests();
        $this->expectException(InvalidArgumentException::class);

        (new WikidataQueryBuilder)->liveEnrichment(['Q1 } SERVICE <https://evil.example/> {']);
    }

    /**
     * @return list<array<string, array{type: string, value: string}>>
     */
    private function liveRows(string $qid): array
    {
        return [
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$qid),
                'kind' => $this->literal('label'),
                'language' => $this->literal('id'),
                'value' => $this->literal('Kabupaten Asahan'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$qid),
                'kind' => $this->literal('label'),
                'language' => $this->literal('en'),
                'value' => $this->literal('Asahan Regency'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$qid),
                'kind' => $this->literal('alias'),
                'language' => $this->literal('id'),
                'value' => $this->literal('Kab. Asahan'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$qid),
                'kind' => $this->literal('image'),
                'language' => $this->literal(''),
                'value' => $this->literal('https://commons.wikimedia.org/wiki/Special:FilePath/Foto%20%231.jpg'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$qid),
                'kind' => $this->literal('wikipedia'),
                'language' => $this->literal('id'),
                'value' => $this->literal('https://id.wikipedia.org/wiki/Kabupaten_Asahan'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$qid),
                'kind' => $this->literal('wikivoyage'),
                'language' => $this->literal('en'),
                'value' => $this->literal('https://en.wikivoyage.org/wiki/Asahan'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichment(string $qid, ?string $wikipediaUrl = null): array
    {
        return [
            'qid' => $qid,
            'labels' => ['id' => null, 'en' => null],
            'aliases' => ['id' => null, 'en' => null],
            'image_filename' => null,
            'image_url' => null,
            'image_page_url' => null,
            'wikipedia_url' => $wikipediaUrl,
            'wikivoyage_url' => null,
            'wikivoyage_language' => null,
            'heritage_statuses' => [],
            'elevation_m' => null,
            'establishment_year' => null,
        ];
    }

    private function forgetLiveCache(): void
    {
        Cache::forget(WikidataRegionRepository::LIVE_CACHE_KEY);
        Cache::forget(WikidataRegionRepository::STALE_CACHE_KEY);
        Cache::forget(WikidataRegionRepository::FAILURE_COOLDOWN_KEY);
    }

    private function repository(
        ?WikidataClient $client = null,
        ?string $snapshotPath = null,
        ?string $staleSnapshotPath = null,
    ): WikidataRegionRepository {
        return new WikidataRegionRepository(
            client: $client ?? $this->client(),
            queryBuilder: new WikidataQueryBuilder,
            catalog: new WikidataRegionCatalog(base_path((string) config('ontology.wikidata_regions_path'))),
            snapshotPath: $snapshotPath ?? base_path((string) config('ontology.wikidata.snapshot_path')),
            staleSnapshotPath: $staleSnapshotPath ?? base_path((string) config('ontology.wikidata.stale_snapshot_path')),
            snapshotTtl: (int) config('ontology.wikidata.snapshot_ttl'),
            requestIntervalSeconds: 0,
            liveTimeout: (int) config('ontology.wikidata.live_timeout'),
            liveCacheTtl: (int) config('ontology.wikidata.live_cache_ttl'),
            staleCacheTtl: (int) config('ontology.wikidata.stale_cache_ttl'),
            liveLockSeconds: (int) config('ontology.wikidata.live_lock_seconds'),
            failureCooldown: (int) config('ontology.wikidata.failure_cooldown'),
        );
    }

    /**
     * @param  list<array<string, array{type: string, value: string}>>  $bindings
     * @return array{head: array{vars: list<string>}, results: array{bindings: list<array<string, array{type: string, value: string}>>}}
     */
    private function sparqlResponse(array $bindings): array
    {
        return [
            'head' => ['vars' => []],
            'results' => ['bindings' => $bindings],
        ];
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return list<array<string, array{type: string, value: string}>>
     */
    private function identityRows(array $catalog): array
    {
        $rows = [];
        foreach ($catalog['regions'] as $name => $region) {
            $rows[] = [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$region['qid']),
                'itemLabel' => $this->literal($name),
                'country' => $this->uri('http://www.wikidata.org/entity/Q252'),
                'parent' => $this->uri('http://www.wikidata.org/entity/Q2140'),
                'instance' => $this->uri('http://www.wikidata.org/entity/Q100'),
                'instanceLabel' => $this->literal($region['type'] === 'Kota'
                    ? 'city of Indonesia'
                    : 'regency of Indonesia'),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return list<array<string, array{type: string, value: string}>>
     */
    private function detailRows(array $catalog): array
    {
        $rows = [];
        foreach (array_values($catalog['regions']) as $index => $region) {
            $row = [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$region['qid']),
                'coordinate' => $this->literal('Point(98.5 2.5)'),
            ];
            if ($index === 0) {
                $row += [
                    'image' => $this->uri('http://commons.wikimedia.org/wiki/Special:FilePath/Example.jpg'),
                    'website' => $this->uri('https://wilayah.example.test/'),
                    'population' => $this->literal('100000'),
                    'populationRank' => $this->uri('http://wikiba.se/ontology#NormalRank'),
                    'areaAmount' => $this->literal('70'),
                    'areaUnit' => $this->uri('http://www.wikidata.org/entity/Q11573'),
                    'areaRank' => $this->uri('http://wikiba.se/ontology#NormalRank'),
                    'normalizedAreaAmount' => $this->literal('700'),
                    'normalizedAreaUnit' => $this->uri('http://www.wikidata.org/entity/Q712226'),
                ];
            } elseif ($index === 1) {
                $row['website'] = $this->uri('javascript:alert(1)');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return list<array<string, array{type: string, value: string}>>
     */
    private function labelRows(array $catalog): array
    {
        $qid = array_values($catalog['regions'])[0]['qid'];

        return [
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$qid),
                'kind' => $this->literal('label'),
                'language' => $this->literal('id'),
                'value' => $this->literal('Kabupaten Asahan'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$qid),
                'kind' => $this->literal('label'),
                'language' => $this->literal('en'),
                'value' => $this->literal('Asahan Regency'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$qid),
                'kind' => $this->literal('alias'),
                'language' => $this->literal('id'),
                'value' => $this->literal('Kab. Asahan'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return list<array<string, array{type: string, value: string}>>
     */
    private function sitelinkRows(array $catalog): array
    {
        $regions = array_values($catalog['regions']);
        $qid = $regions[0]['qid'];
        $preferredQid = $regions[1]['qid'];

        return [
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$qid),
                'kind' => $this->literal('wikipedia'),
                'language' => $this->literal('id'),
                'url' => $this->literal('https://id.wikipedia.org/wiki/Kabupaten_Asahan'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$qid),
                'kind' => $this->literal('wikivoyage'),
                'language' => $this->literal('en'),
                'url' => $this->literal('https://en.wikivoyage.org/wiki/Asahan'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$preferredQid),
                'kind' => $this->literal('wikivoyage'),
                'language' => $this->literal('en'),
                'url' => $this->literal('https://en.wikivoyage.org/wiki/Batu_Bara'),
            ],
            [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$preferredQid),
                'kind' => $this->literal('wikivoyage'),
                'language' => $this->literal('id'),
                'url' => $this->literal('https://id.wikivoyage.org/wiki/Batu_Bara'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return list<array<string, array{type: string, value: string}>>
     */
    private function imageRows(array $catalog): array
    {
        $qid = array_values($catalog['regions'])[0]['qid'];

        return [[
            'item' => $this->uri('http://www.wikidata.org/entity/'.$qid),
            'image' => $this->uri('http://commons.wikimedia.org/wiki/Special:FilePath/Foto%20%281%29%20%231.jpg'),
        ]];
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return list<array<string, array{type: string, value: string}>>
     */
    private function boundaryRows(array $catalog): array
    {
        return array_map(
            fn (array $pair): array => [
                'item' => $this->uri('http://www.wikidata.org/entity/'.$pair[0]),
                'neighbor' => $this->uri('http://www.wikidata.org/entity/'.$pair[1]),
            ],
            $catalog['boundaries'],
        );
    }

    /**
     * @return array{type: string, value: string}
     */
    private function uri(string $value): array
    {
        return ['type' => 'uri', 'value' => $value];
    }

    /**
     * @return array{type: string, value: string}
     */
    private function literal(string $value): array
    {
        return ['type' => 'literal', 'value' => $value];
    }

    private function client(
        int $maxResponseBytes = 2000000,
        int $getQueryMaxBytes = 2000,
        int $maxRetries = 0,
        int $maxRetryAfter = 1,
    ): WikidataClient {
        return new WikidataClient(
            userAgent: 'WayFinder test/1.0',
            requestTimeout: 5,
            refreshTimeout: 5,
            maxRetries: $maxRetries,
            maxRetryAfter: $maxRetryAfter,
            maxResponseBytes: $maxResponseBytes,
            getQueryMaxBytes: $getQueryMaxBytes,
            cacheTtl: 0,
        );
    }
}
