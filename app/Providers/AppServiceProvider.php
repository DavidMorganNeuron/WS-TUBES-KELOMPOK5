<?php

namespace App\Providers;

use App\Services\Ontology\DestinationQueryBuilder;
use App\Services\Ontology\DestinationRdfQueryBuilder;
use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\DestinationSource;
use App\Services\Ontology\FusekiClient;
use App\Services\Ontology\InMemoryDestinationSource;
use App\Services\Ontology\OntologyConfiguration;
use App\Services\Ontology\OntologyGraphMapper;
use App\Services\Ontology\SparqlDestinationSource;
use App\Services\Ontology\SparqlEscaper;
use App\Services\Ontology\SparqlRowMapper;
use App\Services\Ontology\TurtleParser;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use JsonException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FusekiClient::class, function (): FusekiClient {
            $ontologyConfig = config('ontology');
            OntologyConfiguration::validate($ontologyConfig);
            $config = $ontologyConfig['fuseki'];

            return new FusekiClient(
                url: $config['url'],
                dataset: $config['dataset'],
                username: $config['username'],
                password: $config['password'],
                timeout: $config['timeout'],
                writeUsername: $config['write_username'],
                writePassword: $config['write_password'],
                loadTimeout: $config['load_timeout'],
            );
        });

        $this->app->singleton(TurtleParser::class);
        $this->app->singleton(OntologyGraphMapper::class);
        $this->app->singleton(SparqlEscaper::class);
        $this->app->singleton(SparqlRowMapper::class);

        $this->app->singleton(DestinationRdfQueryBuilder::class, function ($app): DestinationRdfQueryBuilder {
            OntologyConfiguration::validate(config('ontology'));

            return new DestinationRdfQueryBuilder($app->make(SparqlEscaper::class));
        });

        $this->app->singleton(DestinationQueryBuilder::class, function ($app): DestinationQueryBuilder {
            $ontologyConfig = config('ontology');
            OntologyConfiguration::validate($ontologyConfig);
            $path = base_path($ontologyConfig['wikidata_regions_path']);
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new InvalidArgumentException("Snapshot wilayah ontologi tidak dapat dibaca: {$path}");
            }

            try {
                $snapshot = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('Snapshot wilayah ontologi bukan JSON yang valid.', previous: $exception);
            }

            if (! is_array($snapshot)) {
                throw new InvalidArgumentException('Snapshot wilayah ontologi harus berupa objek JSON.');
            }

            $regions = $snapshot['regions'] ?? null;
            if (! is_array($regions) || $regions === []) {
                throw new InvalidArgumentException('Snapshot wilayah ontologi tidak memiliki daftar kabupaten/kota.');
            }

            return new DestinationQueryBuilder(
                $app->make(SparqlEscaper::class),
                array_keys($regions),
            );
        });

        $this->app->singleton(DestinationSource::class, function ($app): DestinationSource {
            $ontologyConfig = config('ontology');
            OntologyConfiguration::validate($ontologyConfig);

            if ($ontologyConfig['driver'] === 'ttl') {
                return new InMemoryDestinationSource(
                    parser: $app->make(TurtleParser::class),
                    graphMapper: $app->make(OntologyGraphMapper::class),
                    ttlPath: $ontologyConfig['ttl_path'],
                    cacheTtl: $ontologyConfig['cache_ttl'],
                );
            }

            return new SparqlDestinationSource(
                client: $app->make(FusekiClient::class),
                queryBuilder: $app->make(DestinationQueryBuilder::class),
                rowMapper: $app->make(SparqlRowMapper::class),
                cacheTtl: $ontologyConfig['cache_ttl'],
            );
        });

        $this->app->singleton(
            DestinationRepository::class,
            fn ($app): DestinationRepository => new DestinationRepository($app->make(DestinationSource::class)),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
