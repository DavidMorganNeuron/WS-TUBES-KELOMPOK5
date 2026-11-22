# Checklist pengumpulan

Checklist ini membedakan hasil yang sudah diperiksa dari hasil yang harus dikumpulkan kembali pada lingkungan akhir.

## Integritas repository

- [x] Perubahan pembersihan dipisah pada commit `ff00831`.
- [x] Perubahan hardening dipisah pada commit `ebbea5d`.
- [x] Proteksi marker HMR production dipisah pada commit `0e55cee`.
- [x] README, aturan agen, arsitektur, keputusan desain, bukti SPARQL, integrasi, dan smoke test tersedia; tautan Markdown lokal diperiksa.
- [x] Tidak membaca atau mengubah `.env`; `.env.example` tetap menjadi contoh.
- [x] `vendor`, `node_modules`, `public/build`, `public/hot`, log, cache, dan backup tidak menjadi bagian dari commit.
- [x] `ChatbotTest` lulus (5 tes, 23 assertion); sejak baseline tugas `4a151c1`, daftar file berubah tidak memuat file chatbot/peta.
- [x] `git status --short` dan `git diff --check` bersih setelah commit hasil verifikasi arsip; cek akhir diulang setelah commit checklist ini.

## Gerbang aplikasi

- [x] `php artisan ontology:validate`: 304 destinasi, 13 sentinel rating, 30 sameAs wilayah, 1 sameAs destinasi, 102 relasi berbatasan, dan 746 relasi dekat; validasi lulus.
- [x] `composer audit` dijalankan sebagai laporan saja.
- [x] `npm audit --omit=dev`: 0 kerentanan.
- [x] Tes PHPUnit lengkap, Pint, build frontend, dan validasi ontologi dijalankan setelah perubahan kode/dokumen.
- [x] Uji arsip bersih `git archive` tanpa `.git`, vendor, atau node_modules; `.env` hanya dibuat dari `.env.example` di direktori temporer.
- [x] Fuseki tersedia; `fuseki:load`, `ontology:doctor`, dan pencarian typo pada driver Fuseki lulus (bukti rinci di bawah).

Dataset WayFinder diperluas dari 228 menjadi 304 destinasi pada graph v3. Perubahan mencakup 76 destinasi baru, relasi `dekatDengan` yang meningkat menjadi 746 triple, 21 kelas fasilitas dengan penambahan `Wi-Fi`, serta cakupan koordinat Kepulauan Batu yang mendukung `ws-264 Pulau Telo`. Nilai nyata yang dapat digunakan untuk ringkasan paper atau slide adalah 304 destinasi, 291 destinasi berating, rata-rata rating 4,39, 30 wilayah, 102 relasi berbatasan, dan 13.469 triple.

## Bukti arsip bersih

Arsip `HEAD` diekstrak ke direktori temporer di luar repository, lalu Composer dan npm dipasang dari lockfile. Hasil nyata:

- `composer install`: 115 paket terpasang, 0 update.
- `npm ci`: 80 paket terpasang.
- `php artisan test --compact`: 128 tes, 1.734 assertion lulus.
- `npm run build`: Vite 8.3.4 selesai.
- `php artisan ontology:validate`: 304 destinasi, 30 sameAs wilayah, 1 sameAs destinasi, 102 `berbatasanDengan`, 746 `dekatDengan`; lulus.
- HTTP pada `APP_ENV=production`, `APP_DEBUG=false`, driver TTL: `/`, `/destinations`, `/destinations/ws-001`, `/map`, ekspor Turtle, dan pencarian live semuanya HTTP 200. HTML detail berisi metadata RDFa; ekspor memakai `text/turtle`.
- Marker `public/hot` usang (dibuat hanya di arsip temporer) tidak menyuntikkan HMR; HTML memakai aset `/build/assets`.
- `/destinations/live?q=dnau+toba`: HTTP 200, 9 hasil pada halaman, `correction` bernilai `dnau toba` → `danau toba`.

## Verifikasi Fuseki langsung

- `curl.exe -sS -i 'http://localhost:3030/$/ping'`: HTTP 200.
- `php artisan fuseki:load --dry-run`: lulus; file `wisata_sumut_v3.ttl`, 304 destinasi, 13.469 triple, SHA256 `994ed7e526da44869f018ece13e1296b344a5edee0e3660d9c7596403228412d`.
- `php artisan fuseki:load --no-interaction`: backup dibuat di `storage/app/ontology-backups/20261120-085241-548698`; dataset berhasil dimuat dan diverifikasi, 304 destinasi dan 13.469 triple.
- `php artisan ontology:doctor --no-interaction`: lulus; file v3, Fuseki, aplikasi/cache masing-masing 304 destinasi; jumlah triple cocok 13.469; sameAs wilayah 30 dan destinasi 1; tidak ada graph bernama.
- `GET /destinations/live?q=dnau+toba` ketika `ontology.driver=fuseki`: HTTP 200; `count=9`, `total=37`, koreksi `dnau toba` → `danau toba`; pemberitahuan koreksi hadir pada HTML.
- `php artisan test --compact tests/Feature/ChatbotTest.php`: 5 tes dan 23 assertion lulus.

## Temuan yang harus tetap terlihat

- Keluaran saat ini mencatat 267 sentinel email. Jangan mengubah data hanya untuk menyamakan angka.
- Ada 1 `owl:sameAs` destinasi yang disetujui. Kandidat `ws-108` tetap AMBIGU dan `ws-195` ditolak otomatis; rincian lengkap ada di [kandidat tautan](kandidat-tautan-wikidata.md).
- Audit Composer menemukan advisory berisiko rendah CVE-2026-128083 pada Laravel v13.39.0 (rentang terdampak `<13.40.0`). Tidak ada update otomatis; selesaikan pembaruan sebelum deployment.
- Pemeriksaan ukuran awal dan akhir harus memakai lingkup yang sama. Jangan menyertakan cache runtime, `vendor`, atau `node_modules` saat menghitung artefak sumber.
- `.git` tidak disertakan pada paket sumber hasil `git archive`; riwayat commit tetap tersedia pada repository, bukan paket pengumpulan.

## Keputusan paket

Paket pengumpulan yang dapat direproduksi adalah arsip sumber dari commit, tanpa metadata Git dan dependensi hasil instalasi. Sertakan README, dokumentasi, source code, aset yang dilacak, lockfile, dan data ontology yang memang dilacak. Jangan sertakan file rahasia, backup runtime, cache, build lokal, atau kredensial. Jika format pengumpulan mewajibkan riwayat Git, buat paket terpisah berdasarkan instruksi pengajar.
