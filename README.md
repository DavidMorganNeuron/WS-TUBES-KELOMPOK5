<div align="center">

# Sumatera Heritage &amp; Trails

Jelajahi destinasi wisata Sumatera melalui pencarian, peta interaktif, dan panduan AI berbasis data semantik.

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES_Modules-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org/docs/Web/JavaScript)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![Vite](https://img.shields.io/badge/Vite-8-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vite.dev/)
[![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Leaflet](https://img.shields.io/badge/Leaflet-Maps-199900?style=for-the-badge&logo=leaflet&logoColor=white)](https://leafletjs.com/)

</div>

## Tentang proyek

**Sumatera Heritage & Trails** adalah aplikasi web panduan wisata yang menyajikan informasi destinasi dari data berbasis ontologi. Pengunjung dapat menelusuri destinasi, melihat detail dan lokasinya, serta bertanya kepada **WayFinder AI** tentang informasi wisata.

## Fitur

- Jelajahi dan cari destinasi di nama, alamat, wilayah, deskripsi, petunjuk, kata kunci, fasilitas, dan aktivitas; pencarian memakai relevansi berbobot, saran langsung, serta koreksi typo.
- Gunakan `exact=1` pada URL pencarian untuk menonaktifkan koreksi ejaan.
- Lihat detail destinasi, kategori, informasi, dan atribusi gambar.
- Lihat tautan Wikipedia/Wikivoyage, foto Commons, status warisan, ketinggian, atau tahun berdiri bila data tersedia.
- Bagikan halaman detail dengan metadata Open Graph, RDFa, dan ekspor RDF dalam Turtle, RDF/XML, atau JSON-LD.
- Temukan destinasi melalui peta interaktif berbasis Leaflet.
- Tanyakan informasi destinasi melalui WayFinder AI.
- Sajikan data destinasi dari Apache Jena Fuseki; mode file Turtle tersedia untuk pengembangan atau pengujian.
- Validasi data ontologi dan kelola cache melalui perintah Artisan.

## Teknologi

| Area | Teknologi |
| --- | --- |
| Backend | PHP 8.4, Laravel 13 |
| Frontend | JavaScript, Blade, Tailwind CSS 4 |
| Build frontend | Vite 8 |
| Peta | Leaflet, MarkerCluster |
| Data semantik | RDF/Turtle, SPARQL, Apache Jena Fuseki |
| Penyimpanan aplikasi | MySQL (session, cache, dan queue) |
| Chatbot | Google Gemini API (opsional) |

## Persyaratan

- PHP 8.4 dan Composer
- Node.js `^20.19` atau `>=22.12`, beserta npm
- Ekstensi PHP PDO MySQL
- Apache Jena Fuseki untuk data destinasi
- Kunci Gemini hanya jika ingin menggunakan chatbot

## Menjalankan secara lokal

1. Clone repository dan masuk ke folder proyek.
2. Jalankan skrip setup:

   ```bash
   composer run setup
   ```

   Skrip ini memasang dependensi, menyiapkan `.env`, membuat application key, menjalankan migrasi, memasang dependensi frontend, dan membangun aset.

3. Sesuaikan `.env` bila diperlukan. Konfigurasi contoh memakai MySQL untuk session, cache, dan queue, serta Fuseki dengan dataset `WayFinder` untuk data destinasi. Pastikan MySQL dan Fuseki berjalan dan sesuaikan `DB_*` serta `FUSEKI_URL`/kredensialnya dengan lingkungan lokal.
4. Driver data destinasi default adalah Fuseki. Untuk memakai file ontologi v3 lokal pada pengembangan atau pengujian, atur `ONTOLOGY_DRIVER=ttl`. `ONTOLOGY_V3_TTL_PATH` adalah satu-satunya path graph v3 untuk runtime dan pemuatan Fuseki; `ONTOLOGY_V2_TTL_PATH` hanya dipakai sebagai sumber migrasi v2. Untuk mengaktifkan chatbot, isi `GEMINI_API_KEY` di `.env`. Jangan pernah membagikan atau meng-commit kunci API.
5. Jalankan aplikasi:

   ```bash
   php artisan serve
   ```

   Buka URL yang ditampilkan Artisan, biasanya `http://127.0.0.1:8000`.

Untuk pengembangan frontend dengan hot reload, jalankan di terminal terpisah:

```bash
npm run dev
```

## Perintah data ontologi

```bash
# Memvalidasi data destinasi pada file Turtle
php artisan ontology:validate

# Memanaskan cache ontologi
php artisan ontology:warm

# Mendiagnosis konsistensi data antara file Turtle, Fuseki, dan aplikasi
php artisan ontology:doctor

# Uji konektivitas Wikidata secara eksplisit; tanpa opsi ini doctor tidak menghubungi layanan eksternal
php artisan ontology:doctor --eksternal

# Memperbarui snapshot detail wilayah dan destinasi yang telah disetujui
php artisan wikidata:refresh

# Menyusun kandidat tautan destinasi untuk tinjauan pemilik
php artisan wikidata:refresh --candidates

# Hanya mencari kandidat tanpa memperbarui snapshot detail
php artisan wikidata:refresh --candidates-only
```

`ontology:warm` hanya memanaskan cache aplikasi/Fuseki; perintah itu tidak memanggil Wikidata. `wikidata:refresh` menyegarkan snapshot wilayah dan detail destinasi yang QID-nya tercantum di daftar `setuju`. `--candidates` menambahkan penemuan kandidat live; kandidat tidak otomatis masuk graph kecuali memenuhi aturan persetujuan otomatis yang terdokumentasi dan tetap dapat ditolak pemilik pada `tolak`. `EXTERNAL_CONTACT` dipakai untuk identitas User-Agent bila diisi, selain itu aplikasi memakai URL repositori publik. Snapshot basi hanya digunakan pada konfigurasi yang mengizinkannya.

Panduan algoritma, bidang, koreksi typo, dan parameter `exact=1` ada di [docs/fitur-pencarian.md](docs/fitur-pencarian.md). Lisensi, pemetaan properti, arsitektur, dan cakupan snapshot Wikidata ada di [docs/sumber-eksternal.md](docs/sumber-eksternal.md). Tinjauan kandidat dan kolom keputusan pemilik ada di [docs/kandidat-tautan-wikidata.md](docs/kandidat-tautan-wikidata.md).

Jika Fuseki sudah berjalan dan dikonfigurasi, unggah file Turtle dengan:

```bash
# Periksa file dan rencana tanpa mengubah Fuseki
php artisan fuseki:load --dry-run

# Unggah ke graph default; backup otomatis dibuat terlebih dahulu
php artisan fuseki:load

# Ganti graph default dan graph bernama (perlu konfirmasi)
php artisan fuseki:load --fresh
```

Panduan instalasi, pembuatan dataset, akses, dan backup Fuseki tersedia di [docs/SETUP-FUSEKI.md](docs/SETUP-FUSEKI.md).

Panduan metadata RDFa/Open Graph dan contoh endpoint ekspor tersedia di [docs/validasi-rdfa-ogp.md](docs/validasi-rdfa-ogp.md).

## Pengujian

Jalankan pengujian aplikasi dengan:

```bash
php artisan test
```

## Struktur data utama

- `database/ontology/wisata_sumut_v2.ttl` — sumber migrasi v2 dalam format Turtle; tidak ditimpa oleh proses pemuatan.
- `database/ontology/wisata_sumut_v3.ttl` — graph v3 untuk runtime lokal dan Fuseki, ditentukan oleh `ONTOLOGY_V3_TTL_PATH`.
- `database/ontology/external/wikidata-wilayah.json` — snapshot QID dan pasangan batas wilayah yang disetujui.
- `database/ontology/external/wikidata-wilayah-detail.json` — snapshot detail wilayah terakhir yang diverifikasi dari Wikidata.
- `database/ontology/external/wikidata-destinasi-tautan.json` — daftar tautan destinasi yang disetujui/ditolak serta hasil kandidat.
- `database/ontology/external/wikidata-destinasi-detail.json` — snapshot detail hanya untuk QID destinasi yang disetujui.
- `config/ontology.php` — konfigurasi driver, file Turtle, cache, dan koneksi Fuseki.

## Lisensi

Metadata proyek pada `composer.json` mencantumkan lisensi MIT.
