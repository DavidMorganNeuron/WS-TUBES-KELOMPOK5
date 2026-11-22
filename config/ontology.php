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

    'coverage_boxes' => [
        'daratan_sumut' => [
            'min_latitude' => 0.5,
            'max_latitude' => 4.5,
            'min_longitude' => 97.0,
            'max_longitude' => 100.5,
        ],
        'kepulauan_batu' => [
            'min_latitude' => -0.5,
            'max_latitude' => 0.5,
            'min_longitude' => 97.9,
            'max_longitude' => 98.7,
        ],
    ],

    'search' => [
        'stop_words' => ['di', 'yang', 'ke', 'dari', 'untuk', 'dengan', 'pada', 'dalam', 'serta', 'atau'],
        'relevance_weights' => [
            'name_exact' => 1100,
            'name_prefix' => 1000,
            'name_word' => 900,
            'alias_keyword' => 800,
            'locality' => 600,
            'address' => 400,
            'description' => 200,
        ],
    ],

    'wikidata_regions_path' => 'database/ontology/external/wikidata-wilayah.json',

    'wikidata' => [
        'contact' => env('EXTERNAL_CONTACT', ''),
        'contact_fallback' => 'https://github.com/DavidMorganNeuron/WS-TUBES-KELOMPOK5',
        'request_timeout' => (int) env('WIKIDATA_TIMEOUT', 10),
        'live_timeout' => (int) env('WIKIDATA_LIVE_TIMEOUT', 3),
        'refresh_timeout' => (int) env('WIKIDATA_REFRESH_TIMEOUT', 30),
        'max_retries' => (int) env('WIKIDATA_MAX_RETRIES', 1),
        'max_retry_after' => (int) env('WIKIDATA_MAX_RETRY_AFTER', 30),
        'max_response_bytes' => (int) env('WIKIDATA_MAX_RESPONSE_BYTES', 2000000),
        'get_query_max_bytes' => (int) env('WIKIDATA_GET_QUERY_MAX_BYTES', 1800),
        'query_cache_ttl' => (int) env('WIKIDATA_QUERY_CACHE_TTL', 86400),
        'live_cache_ttl' => (int) env('WIKIDATA_LIVE_CACHE_TTL', 3600),
        'stale_cache_ttl' => (int) env('WIKIDATA_STALE_CACHE_TTL', 604800),
        'live_lock_seconds' => (int) env('WIKIDATA_LIVE_LOCK_SECONDS', 2),
        'failure_cooldown' => (int) env('WIKIDATA_FAILURE_COOLDOWN', 60),
        'snapshot_ttl' => (int) env('WIKIDATA_SNAPSHOT_TTL', 2592000),
        'request_interval_seconds' => (int) env('WIKIDATA_REQUEST_INTERVAL', 1),
        'tourism_class_qid' => 'Q570116',
        'destination_links_path' => 'database/ontology/external/wikidata-destinasi-tautan.json',
        'destination_snapshot_path' => 'database/ontology/external/wikidata-destinasi-detail.json',
        'stale_destination_snapshot_path' => 'storage/app/wikidata/wikidata-destinasi-detail-stale.json',
        'snapshot_path' => 'database/ontology/external/wikidata-wilayah-detail.json',
        'stale_snapshot_path' => 'storage/app/wikidata/wikidata-wilayah-detail-stale.json',
        'candidate_report_path' => 'docs/kandidat-tautan-wikidata.md',
        'allow_stale' => (bool) env('WIKIDATA_ALLOW_STALE', false),
    ],
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
