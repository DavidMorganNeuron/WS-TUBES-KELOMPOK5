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
use App\Services\Ontology\OpeningHoursResolver;
use App\Services\Ontology\SearchPlanner;
use App\Services\Ontology\SearchTokenizer;
use App\Services\Ontology\SearchVocabulary;
use App\Services\Ontology\SparqlDestinationSource;
use App\Services\Ontology\SparqlEscaper;
use App\Services\Ontology\SparqlRowMapper;
use App\Services\Ontology\TurtleParser;
use App\Services\Ontology\TypoCorrector;
use App\Services\Ontology\WikidataClient;
use App\Services\Ontology\WikidataDestinationLinkCatalog;
use App\Services\Ontology\WikidataQueryBuilder;
use App\Services\Ontology\WikidataRegionCatalog;
use App\Services\Ontology\WikidataRegionRepository;
use App\Services\Ontology\WikidataUserAgent;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
        $this->app->singleton(
            OpeningHoursResolver::class,
            fn (): OpeningHoursResolver => new OpeningHoursResolver((string) config('ontology.unknown_label')),
        );
        $this->app->singleton(OntologyGraphMapper::class);
        $this->app->singleton(SparqlEscaper::class);
        $this->app->singleton(SparqlRowMapper::class);
        $this->app->singleton(SearchTokenizer::class);
        $this->app->singleton(SearchVocabulary::class);
        $this->app->singleton(TypoCorrector::class);
        $this->app->singleton(SearchPlanner::class);
        $this->app->singleton(WikidataQueryBuilder::class);

        $this->app->singleton(WikidataDestinationLinkCatalog::class, function (): WikidataDestinationLinkCatalog {
            $ontologyConfig = config('ontology');
            OntologyConfiguration::validate($ontologyConfig);

            return new WikidataDestinationLinkCatalog(base_path($ontologyConfig['wikidata']['destination_links_path']));
        });

        $this->app->singleton(WikidataRegionCatalog::class, function (): WikidataRegionCatalog {
            $ontologyConfig = config('ontology');
            OntologyConfiguration::validate($ontologyConfig);

            return new WikidataRegionCatalog(base_path($ontologyConfig['wikidata_regions_path']));
        });

        $this->app->singleton(WikidataClient::class, function (): WikidataClient {
            $ontologyConfig = config('ontology');
            OntologyConfiguration::validate($ontologyConfig);
            $config = $ontologyConfig['wikidata'];

            return new WikidataClient(
                userAgent: WikidataUserAgent::resolve(
                    $config['contact'],
                    $config['contact_fallback'],
                ),
                requestTimeout: $config['request_timeout'],
                refreshTimeout: $config['refresh_timeout'],
                maxRetries: $config['max_retries'],
                maxRetryAfter: $config['max_retry_after'],
                maxResponseBytes: $config['max_response_bytes'],
                getQueryMaxBytes: $config['get_query_max_bytes'],
                cacheTtl: $config['query_cache_ttl'],
            );
        });

        $this->app->singleton(WikidataRegionRepository::class, function ($app): WikidataRegionRepository {
            $ontologyConfig = config('ontology');
            OntologyConfiguration::validate($ontologyConfig);
            $config = $ontologyConfig['wikidata'];

            return new WikidataRegionRepository(
                client: $app->make(WikidataClient::class),
                queryBuilder: $app->make(WikidataQueryBuilder::class),
                catalog: $app->make(WikidataRegionCatalog::class),
                snapshotPath: base_path($config['snapshot_path']),
                staleSnapshotPath: base_path($config['stale_snapshot_path']),
                snapshotTtl: $config['snapshot_ttl'],
                requestIntervalSeconds: $config['request_interval_seconds'],
                liveTimeout: $config['live_timeout'],
                liveCacheTtl: $config['live_cache_ttl'],
                staleCacheTtl: $config['stale_cache_ttl'],
                liveLockSeconds: $config['live_lock_seconds'],
                failureCooldown: $config['failure_cooldown'],
                destinationLinks: $app->make(WikidataDestinationLinkCatalog::class),
                destinationSnapshotPath: base_path($config['destination_snapshot_path']),
                staleDestinationSnapshotPath: base_path($config['stale_destination_snapshot_path']),
            );
        });

        $this->app->singleton(DestinationRdfQueryBuilder::class, function ($app): DestinationRdfQueryBuilder {
            OntologyConfiguration::validate(config('ontology'));

            return new DestinationRdfQueryBuilder($app->make(SparqlEscaper::class));
        });

        $this->app->singleton(DestinationQueryBuilder::class, function ($app): DestinationQueryBuilder {
            return new DestinationQueryBuilder(
                $app->make(SparqlEscaper::class),
                array_keys($app->make(WikidataRegionCatalog::class)->load()['regions']),
                $app->make(SearchTokenizer::class),
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
                    searchPlanner: $app->make(SearchPlanner::class),
                    cacheTtl: $ontologyConfig['cache_ttl'],
                );
            }

            return new SparqlDestinationSource(
                client: $app->make(FusekiClient::class),
                queryBuilder: $app->make(DestinationQueryBuilder::class),
                rowMapper: $app->make(SparqlRowMapper::class),
                searchPlanner: $app->make(SearchPlanner::class),
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
        if ($this->app->environment('production')) {
            Vite::useHotFile($this->app->storagePath('framework/vite-hot-file-disabled'));
        }
    }
}
