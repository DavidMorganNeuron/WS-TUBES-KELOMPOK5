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
- [ ] Jalankan kembali tes PHPUnit lengkap, Pint, build frontend, serta validasi ontologi setelah seluruh dokumen/perubahan selesai.
- [ ] Uji arsip bersih `git archive` tanpa `.git`, `.env`, vendor, atau node_modules.
- [ ] Bila Fuseki tersedia, jalankan `fuseki:load`, `ontology:doctor`, dan uji pencarian pada driver Fuseki.

## Temuan yang harus tetap terlihat

- Keluaran saat ini mencatat 197 sentinel email; acuan terdahulu menyebut 195. Jangan mengubah data hanya untuk menyamakan angka.
- Ada 1 `owl:sameAs` destinasi yang disetujui. Kandidat `ws-108` tetap AMBIGU dan `ws-195` ditolak otomatis; rincian lengkap ada di [kandidat tautan](kandidat-tautan-wikidata.md).
- Audit Composer menemukan advisory berisiko rendah CVE-2026-128083 pada Laravel v13.39.0 (rentang terdampak `<13.40.0`). Tidak ada update otomatis; selesaikan pembaruan sebelum deployment.
- Pemeriksaan ukuran awal dan akhir harus memakai lingkup yang sama. Jangan menyertakan cache runtime, `vendor`, atau `node_modules` saat menghitung artefak sumber.
- `.git` tidak disertakan pada paket sumber hasil `git archive`; riwayat commit tetap tersedia pada repository, bukan paket pengumpulan.

## Keputusan paket

Paket pengumpulan yang dapat direproduksi adalah arsip sumber dari commit, tanpa metadata Git dan dependensi hasil instalasi. Sertakan README, dokumentasi, source code, aset yang dilacak, lockfile, dan data ontology yang memang dilacak. Jangan sertakan file rahasia, backup runtime, cache, build lokal, atau kredensial. Jika format pengumpulan mewajibkan riwayat Git, buat paket terpisah berdasarkan instruksi pengajar.
