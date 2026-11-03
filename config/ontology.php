<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ontology Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "auto", "fuseki", "ttl". The "auto" driver queries the
    | Fuseki SPARQL endpoint first and falls back to the local Turtle
    | file when the endpoint is unreachable.
    |
    */

    'driver' => env('ONTOLOGY_DRIVER', 'auto'),

    'ttl_path' => env('ONTOLOGY_TTL_PATH', 'database/ontology/pariwisata-sumut.ttl'),

    'cache_ttl' => (int) env('ONTOLOGY_CACHE_TTL', 600),

    'fuseki' => [
        'url' => env('FUSEKI_URL', 'http://localhost:3030'),
        'dataset' => env('FUSEKI_DATASET', 'pariwisata-sumut'),
        'username' => env('FUSEKI_USERNAME'),
        'password' => env('FUSEKI_PASSWORD'),
        'timeout' => (int) env('FUSEKI_TIMEOUT', 5),
    ],

];
