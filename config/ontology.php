<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ontology Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "fuseki" or "ttl". Fuseki failures are reported explicitly;
    | the local Turtle file is selected only when the ttl driver is configured.
    |
    */

    'driver' => env('ONTOLOGY_DRIVER', 'fuseki'),

    'ttl_path' => env('ONTOLOGY_V3_TTL_PATH', 'database/ontology/wisata_sumut_v3.ttl'),

    'v2_source_path' => env('ONTOLOGY_V2_TTL_PATH', env('ONTOLOGY_TTL_PATH', 'database/ontology/wisata_sumut_v2.ttl')),

    'unknown_label' => '-',

    'namespaces' => [
        'ws' => 'http://pariwisatasumut.org/ontology/v2#',
        'data' => 'http://pariwisatasumut.org/resource/',
        'schema' => 'https://schema.org/',
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#',
        'owl' => 'http://www.w3.org/2002/07/owl#',
    ],

    'og_image_placeholder' => 'images/wayfinder-og.png',

    'rdf_export' => [
        'cache_ttl' => (int) env('ONTOLOGY_RDF_EXPORT_CACHE_TTL', 600),
        'formats' => [
            'ttl' => [
                'media_type' => 'text/turtle',
                'extension' => 'ttl',
            ],
            'rdf' => [
                'media_type' => 'application/rdf+xml',
                'extension' => 'rdf',
            ],
            'jsonld' => [
                'media_type' => 'application/ld+json',
                'extension' => 'jsonld',
            ],
        ],
    ],

    'nearby' => [
        'radius_km' => 15.0,
        'max_neighbors' => 3,
    ],

    'wikidata_regions_path' => 'database/ontology/external/wikidata-wilayah.json',

    'cache_ttl' => (int) env('ONTOLOGY_CACHE_TTL', 600),

    'fuseki' => [
        'url' => env('FUSEKI_URL', 'http://localhost:3030'),
        'dataset' => env('FUSEKI_DATASET', 'WayFinder'),
        'username' => env('FUSEKI_USERNAME'),
        'password' => env('FUSEKI_PASSWORD'),
        'timeout' => (int) env('FUSEKI_TIMEOUT', 5),
        'write_username' => env('FUSEKI_WRITE_USERNAME'),
        'write_password' => env('FUSEKI_WRITE_PASSWORD'),
        'load_timeout' => (int) env('FUSEKI_LOAD_TIMEOUT', 60),
    ],

];
