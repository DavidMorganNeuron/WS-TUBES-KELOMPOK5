<div align="center">

# Sumatera Heritage &amp; Trails

Jelajahi destinasi wisata Sumatera melalui pencarian, peta interaktif, dan panduan AI berbasis data semantik.

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES_Modules-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org/docs/Web/JavaScript)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![Vite](https://img.shields.io/badge/Vite-8-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vite.dev/)
[![SQLite](https://img.shields.io/badge/SQLite-Database-003B57?style=for-the-badge&logo=sqlite&logoColor=white)](https://www.sqlite.org/)
[![Leaflet](https://img.shields.io/badge/Leaflet-Maps-199900?style=for-the-badge&logo=leaflet&logoColor=white)](https://leafletjs.com/)

</div>

## Tentang proyek

**Sumatera Heritage & Trails** adalah aplikasi web panduan wisata yang menyajikan informasi destinasi dari data berbasis ontologi. Pengunjung dapat menelusuri destinasi, melihat detail dan lokasinya, serta bertanya kepada **WayFinder AI** tentang informasi wisata.

## Fitur

- Jelajahi dan cari destinasi, dengan saran pencarian langsung.
- Lihat detail destinasi, kategori, informasi, dan atribusi gambar.
- Temukan destinasi melalui peta interaktif berbasis Leaflet.
- Tanyakan informasi destinasi melalui WayFinder AI.
- Sajikan data dari Apache Jena Fuseki atau gunakan file Turtle lokal sebagai fallback.
- Validasi data ontologi dan kelola cache melalui perintah Artisan.

## Teknologi

| Area | Teknologi |
| --- | --- |
| Backend | PHP 8.4, Laravel 13 |
| Frontend | JavaScript, Blade, Tailwind CSS 4 |
| Build frontend | Vite 8 |
| Peta | Leaflet, MarkerCluster |
| Data semantik | RDF/Turtle, SPARQL, Apache Jena Fuseki (opsional) |
| Penyimpanan aplikasi | SQLite secara default |
| Chatbot | Google Gemini API (opsional) |

## Persyaratan

- PHP 8.4 dan Composer
- Node.js `^20.19` atau `>=22.12`, beserta npm
- Ekstensi PHP SQLite/PDO SQLite
- Apache Jena Fuseki dan kunci Gemini hanya jika ingin menggunakan layanan tersebut

## Menjalankan secara lokal

1. Clone repository dan masuk ke folder proyek.
2. Jalankan skrip setup:

   ```bash
   composer run setup
   ```

   Skrip ini memasang dependensi, menyiapkan `.env`, membuat application key, menjalankan migrasi, memasang dependensi frontend, dan membangun aset.

3. Sesuaikan `.env` bila diperlukan. Secara default aplikasi menggunakan SQLite dan driver ontologi `auto`: mencoba Fuseki terlebih dahulu, lalu memakai file Turtle lokal jika Fuseki tidak tersedia.
4. Untuk mengaktifkan chatbot, isi `GEMINI_API_KEY` di `.env`. Untuk menggunakan Fuseki, sesuaikan `FUSEKI_URL`, `FUSEKI_DATASET`, dan kredensialnya bila diperlukan. Jangan pernah membagikan atau meng-commit kunci API.
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
```

Jika Fuseki sudah berjalan dan dikonfigurasi, unggah file Turtle dengan:

```bash
php artisan fuseki:load
```

## Pengujian

Jalankan pengujian aplikasi dengan:

```bash
php artisan test
```

## Struktur data utama

- `database/ontology/wisata_sumut_v2.ttl` — sumber data ontologi dalam format Turtle.
- `wisata_sumut_v2.json` — berkas data destinasi JSON.
- `config/ontology.php` — konfigurasi driver, file Turtle, cache, dan koneksi Fuseki.

## Lisensi

Metadata proyek pada `composer.json` mencantumkan lisensi MIT.
