# Validasi RDFa, Open Graph, dan ekspor RDF

Halaman detail destinasi menyediakan metadata untuk mesin pencari dan aplikasi berbagi:

- RDFa 1.1 menandai destinasi sebagai `schema:TouristAttraction` dan `ws:DestinasiWisata`, termasuk nama, deskripsi, koordinat, kontak, kategori, wilayah, fasilitas, aktivitas, dan hubungan destinasi terdekat yang tersedia.
- Metadata Open Graph mencakup judul, deskripsi, gambar, URL kanonis, tipe inti `website`, dan koordinat melalui `place:location:latitude` / `place:location:longitude` untuk detail destinasi. Gambar placeholder tersedia pada `public/images/wayfinder-og.png` dengan ukuran 1200 × 630 piksel.
- Halaman pencarian dengan filter atau pagination memakai URL kanonis yang dinormalisasi dan `noindex`; halaman tanpa filter memakai URL dasar.
- Tiga tautan `rel="alternate"` pada halaman detail menyediakan representasi Turtle (`.ttl`), RDF/XML (`.rdf`), dan JSON-LD (`.jsonld`).

## Menguji secara lokal

1. Pastikan Fuseki berjalan dan `.env` menunjuk ke dataset yang berisi ontologi v3. Alternatifnya, atur `ONTOLOGY_DRIVER=ttl` untuk memakai file Turtle lokal.
2. Jalankan aplikasi dengan `php artisan serve`, lalu buka halaman detail destinasi, misalnya `http://127.0.0.1:8000/destinations/ws-001`.
3. Periksa sumber HTML untuk elemen `link[rel="canonical"]`, `meta[property^="og:"]`, atribut `prefix`, `typeof`, `property`, `resource`, dan `rel`.
4. Buka URL ekspor yang ditautkan pada halaman. Pastikan respons memakai media type yang sesuai. Driver TTL lokal hanya menyediakan Turtle; format lainnya merespons HTTP 501. Destinasi tanpa graph RDF merespons HTTP 404, sedangkan kegagalan Fuseki dilaporkan sebagai HTTP 503.
5. Untuk pemeriksaan RDFa tambahan, salin HTML hasil render (tanpa kredensial atau data privat) ke alat validator RDFa seperti <https://rdfa.info/play/>. Aplikasi lokal tidak dapat diambil langsung oleh validator publik.

## Perintah pemeriksaan

```bash
php artisan test --compact tests/Feature/DestinationPagesTest.php
php artisan test --compact tests/Feature/DestinationRdfExportTest.php
php artisan test --compact tests/Unit/SeoDataFactoryTest.php
```

Ekspor RDF memakai `ETag` dan `Cache-Control` untuk mendukung cache klien. Permintaan ulang dengan `If-None-Match` yang cocok akan mendapatkan HTTP 304.
