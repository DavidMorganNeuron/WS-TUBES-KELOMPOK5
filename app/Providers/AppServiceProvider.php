<?php

namespace App\Providers;

use App\Services\Ontology\DestinationRepository;
use App\Services\Ontology\FusekiClient;
use App\Services\Ontology\TurtleParser;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FusekiClient::class, function (): FusekiClient {
            $config = config('ontology.fuseki');

            return new FusekiClient(
                url: $config['url'],
                dataset: $config['dataset'],
                username: $config['username'],
                password: $config['password'],
                timeout: $config['timeout'],
            );
        });

        $this->app->singleton(TurtleParser::class);

        $this->app->singleton(DestinationRepository::class, function ($app): DestinationRepository {
            return new DestinationRepository(
                fuseki: $app->make(FusekiClient::class),
                parser: $app->make(TurtleParser::class),
                driver: config('ontology.driver'),
                ttlPath: config('ontology.ttl_path'),
                cacheTtl: config('ontology.cache_ttl'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
