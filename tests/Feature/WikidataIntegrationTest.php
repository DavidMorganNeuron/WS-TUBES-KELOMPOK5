<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Ontology\WikidataClient;
use App\Services\Ontology\WikidataQuery;
use App\Services\Ontology\WikidataQueryBuilder;
use App\Services\Ontology\WikidataRegionCatalog;
use App\Services\Ontology\WikidataRegionRepository;
use App\Services\Ontology\WikidataUserAgent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

final class WikidataIntegrationTest extends TestCase
{
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

        $this->expectException(InvalidArgumentException::class);
        $builder->boundaries(['Q1 } SERVICE <https://example.test/> {']);
    }

    public function test_refresh_command_help_is_in_indonesian(): void
    {
        $this->assertSame(0, Artisan::call('wikidata:refresh', ['--help' => true]));
        $output = Artisan::output();

        $this->assertStringContainsString('Perbarui snapshot wilayah dari Wikidata', $output);
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
            'boundaries' => $this->boundaryRows($source),
        ];

        Http::fake(function (Request $request) use ($responses) {
            $query = (string) ($request->data()['query'] ?? '');
            if (str_contains($query, '?country')) {
                $rows = $responses['identity'];
            } elseif (str_contains($query, '?coordinate')) {
                $rows = $responses['details'];
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
            );
            $snapshot = $repository->refresh();
            $firstRegion = array_values($snapshot['regions'])[0];

            $this->assertSame(30, $snapshot['verification']['regions']);
            $this->assertSame(51, $snapshot['verification']['boundary_pairs']);
            $this->assertSame(51, $snapshot['verification']['directed_boundary_edges']);
            $this->assertSame(2.5, $firstRegion['latitude']);
            $this->assertSame(98.5, $firstRegion['longitude']);
            $this->assertSame('https://commons.wikimedia.org/wiki/Special:FilePath/Example.jpg', $firstRegion['image_url']);
            $this->assertSame('https://wilayah.example.test/', $firstRegion['website_url']);
            $secondRegion = array_values($snapshot['regions'])[1];
            $this->assertNull($secondRegion['website_url']);
            $this->assertSame('100000', $firstRegion['population']['amount']);
            $this->assertSame('700', $firstRegion['area']['normalized']['amount']);
            $regionName = array_key_first($catalog->load()['regions']);
            $this->assertNotNull($repository->findForRegion($regionName));

            $snapshot['retrieved_at'] = now()->subDays(2)->toIso8601String();
            File::put($root.DIRECTORY_SEPARATOR.'latest.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
            $this->assertNull($repository->findForRegion($regionName));
            $this->assertTrue($repository->findForRegion($regionName, allowStale: true)['is_stale']);

            Http::assertSentCount(3);
            Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent', 'WayFinder test/1.0')
                && ! str_contains((string) ($request->data()['query'] ?? ''), 'ws-'));
        } finally {
            File::deleteDirectory($root);
        }
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
