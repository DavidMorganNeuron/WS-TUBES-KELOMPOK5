# Smoke test WayFinder

Gunakan prosedur ini setelah instalasi baru atau pemuatan graph. Jangan menaruh kunci atau kredensial di command line, log, tangkapan layar, atau laporan.

## 1. Konfigurasi dan kesiapan

1. Ikuti [setup Fuseki](SETUP-FUSEKI.md), atau pilih driver Turtle lokal dengan `ONTOLOGY_DRIVER=ttl`.
2. Salin `.env.example` menjadi `.env` secara lokal dan sesuaikan konfigurasi tanpa melacak berkas tersebut.
3. Untuk production pastikan `APP_ENV=production`, `APP_DEBUG=false`, aset sudah dibangun dengan `npm run build`, dan jangan menyalin `public/hot`. Provider production mengabaikan marker hot-file publik yang tertinggal.
4. Pada mode Fuseki, pastikan ping `http://localhost:3030/$/ping` merespons HTTP 200.

## 2. Pemeriksaan graph

```powershell
php artisan ontology:validate
php artisan fuseki:load --dry-run
php artisan ontology:doctor
```

`ontology:validate` harus melaporkan 228 destinasi, 30 sameAs wilayah, jumlah sameAs destinasi sesuai daftar persetujuan, 102 relasi `berbatasanDengan`, dan validasi lulus. `fuseki:load --dry-run` tidak mengubah dataset. Jalankan `php artisan fuseki:load` hanya setelah memastikan URL/dataset benar dan backup tersedia.

## 3. Halaman dan pencarian

Jalankan server Laravel, lalu buka:

- `/` — beranda dan kartu wilayah;
- `/destinations` — daftar, filter, dan pencarian;
- `/destinations/ws-001` — detail, kartu pengayaan yang memiliki data, dan RDFa;
- `/map` — peta serta daftar hasil.

Cari `dnau toba` pada `/destinations?q=dnau+toba`. Hasil yang diharapkan adalah query yang dikoreksi menjadi `danau toba`; pada endpoint live JSON, field `correction` memuat nilai awal dan koreksinya. Coba `exact=1` untuk menonaktifkan koreksi.

Untuk pemeriksaan terminal:

```powershell
curl.exe -i "http://127.0.0.1:8000/destinations?q=dnau+toba"
curl.exe -i "http://127.0.0.1:8000/destinations/live?q=dnau+toba"
curl.exe -i "http://127.0.0.1:8000/destinations/ws-001/rdf.ttl"
```

JSON pencarian harus tetap berisi struktur hasil, pagination, `correction`, dan `suggestions`. Ekspor Turtle menggunakan `text/turtle`; ekspor RDF/XML dan JSON-LD tersedia pada driver Fuseki. Driver Turtle lokal hanya mendukung Turtle.

## 4. Kegagalan dan batas permintaan

- Dengan Fuseki tidak tersedia, request destinasi harus memberi respons aman (HTTP 503), bukan halaman error PHP atau detail server.
- Coba route yang tidak ada untuk melihat halaman 404 dengan header keamanan.
- Pastikan `php artisan route:list --except-vendor --verbose` menunjukkan throttle 60/menit untuk halaman publik dan endpoint pencarian/ekspor, serta 10/menit untuk chatbot.
- Pada mode Fuseki dan `APP_DEBUG=false`, error tidak boleh menampilkan dataset, query, kredensial, atau isi respons server.

## 5. Wikidata dan pemulihan

Refresh snapshot memerlukan jaringan dan dapat memakan waktu:

```powershell
php artisan wikidata:refresh
php artisan wikidata:refresh --candidates-only
```

Sebelum refresh kandidat, pastikan jaringan ke Query Service tersedia. Jika layanan tidak terjangkau, catat `BELUM TERVERIFIKASI`, jangan menandai cakupan sebagai lengkap, dan jangan menyetujui kandidat berdasarkan tebakan. Untuk menguji fallback tanpa jaringan, gunakan tes `WikidataIntegrationTest` yang memakai `Http::fake`; jangan memutus koneksi layanan produksi.

## 6. Gerbang akhir

```powershell
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
php artisan ontology:validate
```

Jika Fuseki tersedia, jalankan juga `php artisan fuseki:load` bila graph perlu diperbarui, lalu `php artisan ontology:doctor` dan uji `/destinations?q=dnau+toba` dengan `ONTOLOGY_DRIVER=fuseki`. Catat perintah dan keluaran nyata pada laporan; kegagalan jaringan atau Fuseki harus ditandai `BELUM TERVERIFIKASI` beserta langkah manualnya.
