# Bukti kueri SPARQL dan validasi

Dokumen ini merangkum jalur kueri aktual aplikasi serta hasil validasi graph yang dapat diulang. Kueri dibuat oleh `DestinationQueryBuilder` dan `DestinationRdfQueryBuilder`; nilai pengguna tidak pernah dipakai sebagai teks query mentah.

## Jalur pencarian Fuseki

`DestinationRepository` memanggil `SparqlDestinationSource`. Query pencarian memilih destinasi, ID, nama untuk urutan, rating, harga, dan skor relevansi. Predicate pencarian mencakup nama, alias, wilayah, alamat, deskripsi, petunjuk lokasi, kata kunci, fasilitas, dan aktivitas.

Bagian query yang menjadi dasar seleksi token pada `DestinationQueryBuilder`:

```sparql
?destination a ws:DestinasiWisata ;
    ws:idDestinasi ?idDestinasi ;
    ws:namaDestinasi ?nameValue .
OPTIONAL { ?destination ws:ratingPengunjung ?ratingValue . FILTER(isNumeric(?ratingValue)) }
OPTIONAL { ?destination ws:memilikiTarif/ws:hargaMasuk ?priceValue . FILTER(isNumeric(?priceValue)) }
```

Potongan tersebut adalah bagian literal dari pola query; untuk query nonkosong, builder menambahkan `FILTER` berisi ekspresi `EXISTS` ter-escape bagi setiap token dan `BIND` berisi bobot tertinggi per token yang dijumlahkan secara global. `DestinationQueryBuilder::page` menggabungkan hitung total dan pemilihan ID berhalaman pada satu request. `SparqlDestinationSource` kemudian mengambil detail ID terpilih pada request berikutnya dan mengembalikan urutan ID dari hasil halaman. `SparqlDestinationSourceTest` memverifikasi jumlah dua request dan urutan hasil.

Untuk pemeriksaan hubungan detail, `DestinationRdfQueryBuilder` memakai `VALUES ?destination { ... }` dengan IRI destinasi yang dibuat oleh `SparqlEscaper`. Endpoint RDF membatasi ID ke pola `ws-NNN` dan format ke daftar konfigurasi.

## Perintah validasi yang dijalankan

Perintah berikut dijalankan pada graph v3 repository:

```text
php artisan ontology:validate
```

Keluaran nyata:

```text
Total destinasi: 228
sameAs wilayah: 30
sameAs destinasi: 1
Total destinasi sumber v2: 228
Destinasi berating: 215
Destinasi tanpa rating: 13
Rata-rata rating: 4.38
Validasi ontologi v3 lulus.
```

Pemeriksaan relasi dan sentinel dari keluaran yang sama:

| Pemeriksaan | Hasil |
| --- | ---: |
| `berbatasanDengan` | 102 |
| `dekatDengan` | 532 |
| Sentinel `ratingPengunjung` | 13 |
| Sentinel `nomorHp` | 150 |
| Sentinel `email` | 197 |

Angka email 197 adalah keluaran aktual. Angka ini berbeda dari acuan 195 yang pernah disebut; dataset tidak diubah untuk menyamakan angka tersebut.

## Perintah pengulangan

```bash
php artisan ontology:validate
php artisan ontology:doctor
php artisan test --compact --filter='SparqlDestinationSourceTest|LiveSearchTest'
```

`ontology:validate` memeriksa file Turtle; `ontology:doctor` membandingkan file v3, Fuseki, dan cache bila Fuseki tersedia. Hasil Fuseki harus dicatat dari eksekusi nyata, bukan disimpulkan dari validasi file.
