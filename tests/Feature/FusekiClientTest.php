<?php

namespace Tests\Feature;

use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\FusekiBackup;
use App\Services\Ontology\FusekiClient;
use App\Services\Ontology\OntologyConfiguration;
use App\Services\Ontology\OntologyDataVersion;
use App\Services\Ontology\OntologyUnavailableException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class FusekiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ontology.driver', 'fuseki');
        config()->set('ontology.fuseki.url', 'http://localhost:3030');
        config()->set('ontology.fuseki.dataset', 'WayFinder');
        config()->set('ontology.fuseki.username', null);
        config()->set('ontology.fuseki.password', null);
        config()->set('ontology.fuseki.write_username', null);
        config()->set('ontology.fuseki.write_password', null);
        app()->forgetInstance(FusekiClient::class);
        app()->forgetInstance(DestinationRepository::class);
        Cache::flush();
    }

    public function test_v3_sparql_bindings_are_mapped_to_the_existing_destination_contract(): void
    {
        Http::fake([
            'http://localhost:3030/WayFinder/sparql' => Http::response($this->sparqlResponse([
                $this->destinationBinding(),
            ])),
        ]);

        $destination = app(DestinationRepository::class)->find('ws-001');

        $this->assertNotNull($destination);
        $this->assertSame('Danau Contoh', $destination->name);
        $this->assertSame(['Alam'], $destination->types);
        $this->assertSame(['Toba', 'Danau'], $destination->keywords);
        $this->assertSame('Kabupaten Contoh', $destination->location);
        $this->assertSame(2.5, $destination->latitude);
        $this->assertSame(98.5, $destination->longitude);
        $this->assertSame(['0', '25000'], $destination->prices);
        $this->assertSame('https://example.test/image.jpg', $destination->media);
        $this->assertSame(4.5, $destination->rating);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://localhost:3030/WayFinder/sparql'
            && str_contains((string) ($request->data()['query'] ?? ''), 'ws:berkategori'));
    }

    public function test_fuseki_unavailability_returns_503_without_falling_back_to_local_turtle(): void
    {
        Http::fake([
            'http://localhost:3030/WayFinder/sparql' => Http::response(['error' => 'private-server-detail'], 503),
        ]);
        Log::spy();

        $this->get('/destinations')
            ->assertStatus(503)
            ->assertSee('Layanan data destinasi sedang tidak tersedia.')
            ->assertDontSee('private-server-detail');

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_json_requests_receive_a_safe_503_response(): void
    {
        config()->set('ontology.fuseki.dataset', 'internal-dataset');

        Http::fake([
            'http://localhost:3030/internal-dataset/sparql' => Http::response(['error' => 'private-server-detail'], 404),
        ]);

        $this->getJson('/destinations')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Layanan data destinasi sedang tidak tersedia. Silakan coba lagi nanti.')
            ->assertDontSee('private-server-detail')
            ->assertDontSee('internal-dataset')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
    }

    public function test_write_credentials_are_separate_from_read_credentials(): void
    {
        config()->set('ontology.fuseki.username', 'reader');
        config()->set('ontology.fuseki.password', 'reader-secret');
        config()->set('ontology.fuseki.write_username', 'writer');
        config()->set('ontology.fuseki.write_password', 'writer-secret');
        app()->forgetInstance(FusekiClient::class);
        Http::fake();

        app(FusekiClient::class)->update('CLEAR ALL');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/update')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('writer:writer-secret')));
    }

    public function test_cache_version_changes_force_a_fresh_fuseki_query(): void
    {
        $name = 'Destination 1';
        Http::fake(function () use (&$name) {
            $binding = $this->destinationBinding();
            $binding['namaDestinasi'] = ['type' => 'literal', 'value' => $name];

            return Http::response($this->sparqlResponse([$binding]));
        });

        $repository = app(DestinationRepository::class);
        $this->assertSame('Destination 1', $repository->all()->first()->name);

        $name = 'Destination 2';
        app(OntologyDataVersion::class)->refresh('updated-source');

        $this->assertSame('Destination 2', $repository->all()->first()->name);
        Http::assertSentCount(4);
    }

    public function test_unsupported_driver_is_rejected_before_services_are_used(): void
    {
        $configuration = config('ontology');
        $configuration['driver'] = 'auto';

        $this->expectException(InvalidArgumentException::class);
        OntologyConfiguration::validate($configuration);
    }

    public function test_dataset_not_found_is_reported_without_creating_a_dataset(): void
    {
        Http::fake([
            'http://localhost:3030/WayFinder/sparql' => Http::response('', 404),
        ]);

        $this->assertFalse(app(FusekiClient::class)->datasetExists());
    }

    public function test_fresh_load_replaces_the_default_graph_and_clears_named_graphs(): void
    {
        Http::fake();

        app(FusekiClient::class)->replaceDataset('@prefix ws: <http://example.test/> .', clearNamedGraphs: true);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/data?default'));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/update')
            && $request->data()['update'] === 'CLEAR NAMED');
    }

    public function test_default_dataset_replacement_preserves_unrelated_named_graphs(): void
    {
        Http::fake();

        app(FusekiClient::class)->replaceDataset('@prefix ws: <http://example.test/> .');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/data?default'));
    }

    public function test_load_dry_run_validates_the_v3_file_without_sending_http_requests(): void
    {
        Http::fake();

        $this->artisan('fuseki:load', ['--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Dry-run lulus');

        Http::assertNothingSent();
    }

    public function test_backup_retention_keeps_the_five_most_recent_backups(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wayfinder-backups-'.Str::uuid();
        File::makeDirectory($root, 0755, true);

        for ($index = 1; $index <= 6; $index++) {
            $directory = $root.DIRECTORY_SEPARATOR.sprintf('20230101-00000%d-000000', $index);
            File::makeDirectory($directory);
        }

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response('@prefix ex: <http://example.test/> .', 200);
            }

            return Http::response($this->sparqlResponse([]), 200);
        });

        try {
            $backup = new FusekiBackup($root);
            $path = $backup->create(new FusekiClient('http://localhost:3030', 'WayFinder'));

            $this->assertDirectoryExists($path);
            $this->assertCount(5, File::directories($root));
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_server_errors_are_domain_exceptions_and_never_include_credentials(): void
    {
        config()->set('ontology.fuseki.username', 'reader');
        config()->set('ontology.fuseki.password', 'super-secret');
        app()->forgetInstance(FusekiClient::class);
        Http::fake([
            'http://localhost:3030/WayFinder/sparql' => Http::response('', 503),
        ]);

        try {
            app(FusekiClient::class)->select('SELECT * WHERE {}');
            $this->fail('A 503 response must raise an ontology availability exception.');
        } catch (OntologyUnavailableException $exception) {
            $this->assertStringNotContainsString('super-secret', $exception->getMessage());
            $this->assertSame(
                'Layanan data destinasi sedang tidak tersedia. Periksa koneksi Fuseki.',
                $exception->getMessage(),
            );
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
     * @return array<string, array{type: string, value: string}>
     */
    private function destinationBinding(): array
    {
        $values = [
            's' => 'http://pariwisatasumut.org/resource/ws-001',
            'idDestinasi' => 'ws-001',
            'namaDestinasi' => 'Danau Contoh',
            'namaAlternatif' => 'Danau',
            'deskripsiSingkat' => 'Danau indah',
            'deskripsiLengkap' => 'Deskripsi lengkap',
            'jenisDestinasi' => 'Alam',
            'kataKunci' => 'Toba | Danau',
            'beradaDi' => 'Kabupaten Contoh',
            'alamat' => 'Alamat Contoh',
            'lintang' => '2.5',
            'bujur' => '98.5',
            'petunjukLokasi' => 'Ikuti jalan utama',
            'jamOperasional' => '09:00 - 17:00',
            'hargaMasuk' => '0 | 25000',
            'noHpResmi' => '08123456789',
            'emailResmi' => 'info@example.test',
            'instagramResmi' => 'https://instagram.com/example',
            'memilikiFasilitas' => 'Toilet | Parkir',
            'dapatMelakukan' => 'Berenang | Berfoto',
            'memilikiMedia' => 'https://example.test/image.jpg',
            'ratingPengunjung' => '4.5',
        ];

        $binding = [];
        foreach ($values as $property => $value) {
            $binding[$property] = [
                'type' => $property === 's' ? 'uri' : 'literal',
                'value' => $value,
            ];
        }

        return $binding;
    }
}
