# Menyiapkan Apache Jena Fuseki

WayFinder menggunakan Apache Jena Fuseki sebagai penyimpanan destinasi bawaan. Berkas Turtle v3 lokal dapat digunakan tanpa server Fuseki dengan mengatur `ONTOLOGY_DRIVER=ttl`.

## Persyaratan dan instalasi

1. Pasang Java 17 atau yang lebih baru untuk Apache Jena Fuseki 5.1.0. [POM resmi Jena 5.1.0](https://github.com/apache/jena/blob/jena-5.1.0/pom.xml) menetapkan `java.version` ke 17.
2. Unduh [distribusi biner Apache Jena Fuseki 5.1.0](https://archive.apache.org/dist/jena/binaries/apache-jena-fuseki-5.1.0.zip) dari arsip resmi Apache.
3. Gunakan Fuseki Full jika memerlukan Admin UI berbasis peramban. Fuseki Main adalah server command-line dan tidak menyediakan UI atau fungsi administrasi HTTP untuk membuat dataset.

Dokumentasi resmi Fuseki:

- [Konfigurasi Fuseki](https://jena.apache.org/documentation/fuseki2/fuseki-configuration.html)
- [Operasi endpoint dan padanan command-line](https://jena.apache.org/documentation/fuseki2/fuseki-config-endpoint.html)
- [Protokol administrasi HTTP Fuseki](https://jena.apache.org/documentation/fuseki2/fuseki-server-protocol.html)
- [Perintah TDB2](https://jena.apache.org/documentation/tdb2/tdb2_cmds.html)
- [Keamanan Fuseki](https://jena.apache.org/documentation/fuseki2/fuseki-security.html)

## Menjalankan dataset TDB2 persisten di Windows

Dari direktori distribusi Fuseki yang sudah diekstrak, jalankan:

```powershell
.\fuseki-server.bat --update --tdb2 --loc .\run\wayfinder /WayFinder
```

Opsi `--update` mengaktifkan SPARQL Update dan penulisan Graph Store. TDB2 menyimpan dataset di direktori yang diberikan melalui `--loc`; pertahankan direktori tersebut dan sertakan dalam kebijakan pencadangan server. Jangan gunakan alat TDB1 pada penyimpanan TDB2.

Untuk Fuseki Full, buka `http://localhost:3030/`, buat dataset TDB2 persisten bernama `WayFinder` melalui Admin UI jika belum ada, lalu pastikan `http://localhost:3030/$/ping` mengembalikan HTTP 200. Nama dataset peka huruf besar-kecil. WayFinder tidak membuat dataset Fuseki yang belum ada secara otomatis.

## Mengonfigurasi WayFinder

Atur nilai berikut di berkas aplikasi `.env` yang tidak dilacak Git:

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

`FUSEKI_URL` hanya berisi URL dasar server: jangan tambahkan dataset atau kredensial, dan jangan masukkan kredensial ke dalam URL. Kredensial baca digunakan untuk kueri SPARQL dan ekspor cadangan. Jika pasangan kredensial tulis opsional kosong, operasi tulis menggunakan pasangan kredensial baca. Isi kedua nilai dalam setiap pasangan kredensial secara bersamaan. Untuk deployment nonlokal, gunakan TLS dan kredensial dengan hak akses yang dibatasi; jangan mengandalkan pengaturan pengembangan bawaan Fuseki.

## Memvalidasi dan memuat ontologi

Jalankan perintah berikut dari direktori utama proyek Laravel:

```powershell
php artisan ontology:validate
php artisan fuseki:load --dry-run
php artisan fuseki:load
php artisan ontology:doctor
php artisan ontology:warm
```

`fuseki:load` memvalidasi berkas v3 sebelum mengubah apa pun dan menyimpan cadangan di `storage/app/ontology-backups`. Lima cadangan terbaru dipertahankan. Pemuatan biasa mengganti graph default menggunakan Graph Store Protocol `PUT`. Gunakan `--fresh` hanya jika graph bernama juga harus dihapus; opsi ini meminta konfirmasi lalu mengosongkan graph bernama setelah graph default diganti.

Cadangan aplikasi adalah salinan pemulihan lokal, terpisah dari penyimpanan TDB2 Fuseki dan cadangan sisi server. Pertahankan keduanya saat menjalankan layanan persisten. Periksa hasil perintah dan jalankan `ontology:doctor` sebelum melayani trafik setelah pemuatan yang gagal.

## Beralih ke mode Turtle lokal

Untuk menjalankan tanpa Fuseki, atur:

```dotenv
ONTOLOGY_DRIVER=ttl
ONTOLOGY_V3_TTL_PATH=database/ontology/wisata_sumut_v3.ttl
```

Mode ini membaca berkas v3 hasil generator yang dipilih oleh `ONTOLOGY_V3_TTL_PATH`. `ONTOLOGY_V2_TTL_PATH` hanya digunakan sebagai masukan migrasi v2 dan tidak ditimpa oleh pemuatan Fuseki.
