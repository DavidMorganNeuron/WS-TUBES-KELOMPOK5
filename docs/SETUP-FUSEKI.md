# Apache Jena Fuseki setup

WayFinder uses Apache Jena Fuseki as its default destination store. The local v3 Turtle file can be used without a Fuseki server by setting `ONTOLOGY_DRIVER=ttl`.

## Requirements and installation

1. Install Java 17 or later for Apache Jena Fuseki 5.1.0.
2. Download the [Apache Jena Fuseki 5.1.0 binary distribution](https://archive.apache.org/dist/jena/binaries/apache-jena-fuseki-5.1.0.zip) from the official Apache archive.
3. Use Fuseki Full if you need the browser-based Admin UI. Fuseki Main is a command-line server and does not provide the UI or HTTP administration functions for creating datasets.

The official Fuseki documentation:

- [Fuseki configuration](https://jena.apache.org/documentation/fuseki2/fuseki-configuration.html)
- [Endpoint operations and command-line equivalents](https://jena.apache.org/documentation/fuseki2/fuseki-config-endpoint.html)
- [Fuseki HTTP administration protocol](https://jena.apache.org/documentation/fuseki2/fuseki-server-protocol.html)
- [TDB2 command-line tools](https://jena.apache.org/documentation/tdb2/tdb2_cmds.html)
- [Fuseki security](https://jena.apache.org/documentation/fuseki2/fuseki-security.html)

## Start a persistent TDB2 dataset on Windows

From the extracted Fuseki distribution directory, run:

```powershell
.\fuseki-server.bat --update --tdb2 --loc .\run\wayfinder /WayFinder
```

The `--update` option enables SPARQL Update and Graph Store writes. TDB2 stores the dataset in the directory passed to `--loc`; keep that directory persistent and include it in the server's own backup policy. Do not use TDB1 tools on a TDB2 store.

For Fuseki Full, open `http://localhost:3030/`, create a persistent TDB2 dataset named `WayFinder` through the Admin UI if it does not already exist, then verify `http://localhost:3030/$/ping` returns HTTP 200. Dataset names are case-sensitive. WayFinder does not create missing Fuseki datasets automatically.

## Configure WayFinder

Set these values in the untracked application `.env` file:

```dotenv
ONTOLOGY_DRIVER=fuseki
ONTOLOGY_V2_TTL_PATH=database/ontology/wisata_sumut_v2.ttl
ONTOLOGY_V3_TTL_PATH=database/ontology/wisata_sumut_v3.ttl
FUSEKI_URL=http://localhost:3030
FUSEKI_DATASET=WayFinder
FUSEKI_TIMEOUT=5
FUSEKI_LOAD_TIMEOUT=60
FUSEKI_USERNAME=
FUSEKI_PASSWORD=
FUSEKI_WRITE_USERNAME=
FUSEKI_WRITE_PASSWORD=
```

`FUSEKI_URL` is the server base URL only: do not append the dataset or credentials, and do not put credentials in the URL. Read credentials are used for SPARQL queries and backup exports. If the optional write-credential pair is blank, writes use the read-credential pair. Configure both values of a credential pair together. Use TLS and appropriately restricted credentials for non-local deployments; do not rely on Fuseki's development defaults.

## Validate and load the ontology

Run these commands from the Laravel project root:

```powershell
php artisan ontology:validate
php artisan fuseki:load --dry-run
php artisan fuseki:load
php artisan ontology:doctor
php artisan ontology:warm
```

`fuseki:load` validates the v3 file before making a change and saves a backup under `storage/app/ontology-backups`. The newest five backups are retained. The regular load replaces the default graph with a Graph Store Protocol `PUT`. Use `--fresh` only when named graphs should also be removed; it requests confirmation and clears named graphs after replacing the default graph.

The application backup is a local recovery copy, separate from Fuseki's TDB2 storage and its server-side backups. Keep both when operating a persistent service. Check the command result and run `ontology:doctor` before serving traffic after any failed load.

## Switch to local Turtle mode

To run without Fuseki, configure:

```dotenv
ONTOLOGY_DRIVER=ttl
ONTOLOGY_V3_TTL_PATH=database/ontology/wisata_sumut_v3.ttl
```

This mode reads the generated v3 file selected by `ONTOLOGY_V3_TTL_PATH`. `ONTOLOGY_V2_TTL_PATH` is only the v2 migration input and is not overwritten by Fuseki loads.
