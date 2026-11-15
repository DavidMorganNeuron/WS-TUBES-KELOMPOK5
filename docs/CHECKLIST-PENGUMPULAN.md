# Checklist pengumpulan

Checklist ini membedakan hasil yang sudah diperiksa dari hasil yang harus dikumpulkan kembali pada lingkungan akhir.

## Integritas repository

- [x] Perubahan pembersihan dipisah pada commit `ff00831`.
- [x] Perubahan hardening dipisah pada commit `ebbea5d`.
- [x] Proteksi marker HMR production dipisah pada commit `0e55cee`.
- [x] README, aturan agen, arsitektur, keputusan desain, bukti SPARQL, integrasi, dan smoke test tersedia; tautan Markdown lokal diperiksa.
- [x] Tidak membaca atau mengubah `.env`; `.env.example` tetap menjadi contoh.
- [x] `vendor`, `node_modules`, `public/build`, `public/hot`, log, cache, dan backup tidak menjadi bagian dari commit.
- [ ] Setelah semua langkah selesai, pastikan file chatbot/peta milik rekan tetap tidak berubah dan `ChatbotTest` lulus.
- [ ] Jalankan `git status --short`, `git diff --check`, dan simpan daftar commit lokal terakhir.

## Gerbang aplikasi

- [x] `php artisan ontology:validate`: 228 destinasi, 13 sentinel rating, 30 sameAs wilayah, 1 sameAs destinasi, 102 relasi berbatasan, dan 532 relasi dekat; validasi lulus.
- [x] `composer audit` dijalankan sebagai laporan saja.
- [x] `npm audit --omit=dev`: 0 kerentanan.
- [x] Tes PHPUnit lengkap, Pint, build frontend, dan validasi ontologi dijalankan setelah perubahan kode/dokumen.
- [x] Uji arsip bersih `git archive` tanpa `.git`, vendor, atau node_modules; `.env` hanya dibuat dari `.env.example` di direktori temporer.
- [ ] Bila Fuseki tersedia, jalankan `fuseki:load`, `ontology:doctor`, dan uji pencarian pada driver Fuseki.

## Bukti arsip bersih

Arsip `HEAD` diekstrak ke direktori temporer di luar repository, lalu Composer dan npm dipasang dari lockfile. Hasil nyata:

- `composer install`: 115 paket terpasang, 0 update.
- `npm ci`: 80 paket terpasang.
- `php artisan test --compact`: 125 tes, 1.480 assertion lulus.
- `npm run build`: Vite 8.3.4 selesai.
- `php artisan ontology:validate`: 228 destinasi, 30 sameAs wilayah, 1 sameAs destinasi, 102 `berbatasanDengan`, 532 `dekatDengan`; lulus.
- HTTP pada `APP_ENV=production`, `APP_DEBUG=false`, driver TTL: `/`, `/destinations`, `/destinations/ws-001`, `/map`, ekspor Turtle, dan pencarian live semuanya HTTP 200. HTML detail berisi metadata RDFa; ekspor memakai `text/turtle`.
- Marker `public/hot` usang (dibuat hanya di arsip temporer) tidak menyuntikkan HMR; HTML memakai aset `/build/assets`.
- `/destinations/live?q=dnau+toba`: HTTP 200, 9 hasil pada halaman, `correction` bernilai `dnau toba` → `danau toba`.

## Temuan yang harus tetap terlihat

- Keluaran saat ini mencatat 197 sentinel email; acuan terdahulu menyebut 195. Jangan mengubah data hanya untuk menyamakan angka.
- Ada 1 `owl:sameAs` destinasi yang disetujui. Kandidat `ws-108` tetap AMBIGU dan `ws-195` ditolak otomatis; rincian lengkap ada di [kandidat tautan](kandidat-tautan-wikidata.md).
- Audit Composer menemukan advisory berisiko rendah CVE-2026-128083 pada Laravel v13.39.0 (rentang terdampak `<13.40.0`). Tidak ada update otomatis; selesaikan pembaruan sebelum deployment.
- Pemeriksaan ukuran awal dan akhir harus memakai lingkup yang sama. Jangan menyertakan cache runtime, `vendor`, atau `node_modules` saat menghitung artefak sumber.
- `.git` tidak disertakan pada paket sumber hasil `git archive`; riwayat commit tetap tersedia pada repository, bukan paket pengumpulan.

## Keputusan paket

Paket pengumpulan yang dapat direproduksi adalah arsip sumber dari commit, tanpa metadata Git dan dependensi hasil instalasi. Sertakan README, dokumentasi, source code, aset yang dilacak, lockfile, dan data ontology yang memang dilacak. Jangan sertakan file rahasia, backup runtime, cache, build lokal, atau kredensial. Jika format pengumpulan mewajibkan riwayat Git, buat paket terpisah berdasarkan instruksi pengajar.
