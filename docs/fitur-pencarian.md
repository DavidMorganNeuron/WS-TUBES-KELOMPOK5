# Fitur pencarian

WayFinder memakai satu rencana pencarian untuk driver Fuseki dan Turtle. Pencarian direktori secara otomatis mengoreksi typo dan menyaring berdasarkan bidang di bawah ini; jalur SPARQL dan Turtle menerapkan aturan pencocokan yang setara.

## Bidang yang dicari

- nama destinasi dan nama alternatif;
- label wilayah/kabupaten-kota;
- alamat, termasuk nama kecamatan bila tercantum di alamat sumber;
- deskripsi singkat dan lengkap serta petunjuk lokasi;
- kata kunci;
- label fasilitas dan aktivitas.

Pencarian tidak hanya membandingkan nama destinasi. Kecamatan bukan properti terpisah: ia ikut dicari sebagai bagian teks alamat yang tersedia.

## Token dan stop word

Query dipangkas, dijadikan huruf kecil, lalu dipisah pada karakter selain huruf atau angka Unicode. Kata stop yang dikonfigurasi adalah `di`, `yang`, `ke`, `dari`, `untuk`, `dengan`, `pada`, `dalam`, `serta`, dan `atau`. Token satu huruf tetap dicari sebagai awalan kata, agar pengetikan satu karakter langsung menghasilkan destinasi yang cocok. Panjang query dibatasi 200 karakter, paling banyak delapan token, dan setiap token paling banyak 40 karakter; input nonkosong di luar batas menghasilkan nol kecocokan, bukan daftar seluruh destinasi.

Direktori juga menyediakan dropdown `dapatMelakukan` dan `memilikiFasilitas` yang diisi dari label aktivitas dan fasilitas unik pada dataset aktif. Pilihan diterapkan tanpa peka huruf besar-kecil dan mencari kecocokan sebagian; destinasi tanpa properti tersebut tetap muncul saat dropdown terkait tidak dipilih.

## Relevansi global

Setiap token harus cocok dengan setidaknya satu bidang. Skor dihitung sekali per token sebagai nilai tertinggi dari kecocokan bidang (tidak dijumlahkan berulang karena satu nilai muncul pada banyak relasi), kemudian skor token dijumlahkan pada destinasi. Bobot bawaan SPARQL:

| Jenis kecocokan | Bobot |
| --- | ---: |
| Nama destinasi sama persis dengan token | 1.100 |
| Awalan nama destinasi | 1.000 |
| Kata pada nama destinasi | 900 |
| Nama alternatif, kata kunci, fasilitas, atau aktivitas | 800 |
| Label wilayah | 600 |
| Alamat | 400 |
| Deskripsi atau petunjuk lokasi | 200 |

Saat ada kata kunci, urutan relevansi diterapkan otomatis dengan skor global tertinggi terlebih dahulu, lalu rating dan nama sebagai pemutus seri. Relevansi bukan lagi pilihan filter tersendiri. Bobot berada di `config/ontology.php`.

## Koreksi typo

Kosakata dibangun dari teks yang memang dapat dicari. Koreksi memakai jarak edit dengan dukungan pertukaran dua karakter bersebelahan, dengan ambang berdasarkan panjang token:

- 1–3 karakter: tidak mengoreksi typo;
- 4–5 karakter: jarak maksimum 1;
- 6 karakter atau lebih: jarak maksimum 2.

Untuk kata terakhir, pencocokan dan koreksi juga mempertimbangkan awalan kosakata, sehingga kata yang masih diketik dapat menemukan bentuk lengkapnya. Bila token tidak dapat dikoreksi, saran terdekat dibatasi sampai tiga. Contoh: `dnau toba` dikoreksi menjadi `danau toba`; nilai `correction` pada respons live tetap berisi teks awal dan teks koreksi agar kontrak JSON tetap eksplisit.

Koreksi typo selalu aktif pada pencarian direktori. Pencarian peta tetap mendukung `exact=1` untuk mempertahankan teks apa adanya; pilihan ini tidak tersedia pada direktori.

## Keamanan dan pilihan implementasi

Query dibatasi panjang dan jumlah token, token divalidasi sebagai huruf/angka Unicode, pola regex dibuat dari token yang di-escape, dan nilai literal SPARQL dikutip oleh helper. Nama properti/predikat tidak berasal dari request. Aturan ini membatasi input pencarian sekaligus mencegahnya mengubah struktur query.

WayFinder tidak memakai `jena-text`: tidak ada indeks teks eksternal yang harus disiapkan atau disinkronkan, dan filter/bobot/koreksi dapat dipertahankan konsisten antara Fuseki dan driver Turtle. Konsekuensinya, pencarian teks memakai pencocokan SPARQL pada graph dan kosakata koreksi lokal, bukan kemampuan ranking/analyzer khusus mesin pencari.
