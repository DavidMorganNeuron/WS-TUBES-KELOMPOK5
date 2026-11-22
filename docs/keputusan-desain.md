# Keputusan desain

## Graph RDF sebagai sumber data wisata

Data destinasi dipertahankan sebagai graph RDF agar tipe, hubungan, ekspor, dan metadata semantik dapat divalidasi bersama. V2 adalah masukan migrasi; v3 merupakan keluaran generator dan tidak diedit manual. `DestinationRepository` memisahkan controller dari dua driver data.

## Dua driver dengan kegagalan eksplisit

Fuseki adalah driver bawaan untuk penggunaan terhubung; Turtle lokal merupakan pilihan eksplisit untuk pengembangan, uji, dan kondisi tanpa server. Fuseki yang tidak tersedia tidak otomatis jatuh ke graph lokal, sebab perbedaan graph dapat menyajikan hasil yang menyesatkan. Respons HTTP tetap aman dan tes memakai `Http::fake`.

## Pencarian konsisten tanpa indeks teks tambahan

Pencarian menggunakan satu antarmuka dan aturan relevansi di driver Fuseki serta Turtle. Token, bidang, bobot global, koreksi typo, dan `exact=1` dijelaskan di [fitur-pencarian](fitur-pencarian.md). `jena-text` tidak dipakai agar aplikasi tidak memerlukan indeks teks tambahan yang perlu disiapkan dan disinkronkan; trade-off-nya adalah pencocokan graph dapat lebih mahal, sehingga endpoint pencarian dan halaman berat dibatasi.

## Identitas Wikidata hanya melalui persetujuan

Kemiripan nama atau jarak tidak cukup untuk menyatakan dua entitas sama. Hanya QID pada daftar `setuju` yang dapat menghasilkan `owl:sameAs` destinasi. Generator memastikan output deterministik dan validator memeriksa IRI kanonik, duplikasi, jumlah, dan kesesuaian dengan katalog. Kelas wisata dan QID kandidat diverifikasi live sebelum penemuan; hasil ambigu memerlukan keputusan pemilik.

IRI entity Wikidata yang dihasilkan mengikuti bentuk `http://www.wikidata.org/entity/Q…`. Pemetaan properti, lisensi, dan bukti sitelink tersedia di [sumber eksternal](sumber-eksternal.md).

## Cakupan koordinat dan Kepulauan Batu

Gerbang kualitas data memakai dua kotak yang dikonfigurasi terpusat: daratan Sumatera Utara pada lintang 0,5–4,5 dan bujur 97,0–100,5, serta Kepulauan Batu pada lintang -0,5–0,5 dan bujur 97,9–98,7. Keputusan ini membolehkan `ws-264 Pulau Telo` tanpa memperluas satu kotak tunggal secara berlebihan. Kotak pencarian kandidat Wikidata tetap memakai cakupan daratan lama dan oleh karena itu tidak mencakup semua Kepulauan Batu; keterbatasan ini memerlukan evaluasi kandidat eksternal secara terpisah.

## Pengayaan live yang gagal secara lunak

Data eksternal menggunakan cache dan snapshot, batch `VALUES`, timeout pendek, tanpa retry pada jalur web, dan fallback sampai `null`. Pemutus sirkuit hanya menyimpan status kegagalan. Kegagalan atau data rusak tidak boleh mengisi cache data maupun menggagalkan halaman. Sumber eksternal dijelaskan dalam dokumentasi, bukan sebagai label asal pada UI.

## Keamanan operasional

- Endpoint Wikidata dan bentuk query ditentukan oleh aplikasi; nilai request pengguna tidak masuk ke query.
- Route yang menjalankan query atau pengayaan diberi throttle 60/menit; chatbot memakai throttle terpisah 10/menit.
- Header keamanan berlaku untuk respons sukses, error, dan ekspor.
- Pesan 503 publik generik; rincian diagnosis hanya dicatat pada jalur log yang sesuai.
- Production harus memakai `APP_ENV=production`, `APP_DEBUG=false`, dan aset hasil `npm run build`. Provider juga mengarahkan pemeriksaan hot-file Vite ke marker nonpublik pada production agar `public/hot` yang tertinggal tidak mengaktifkan HMR.
- `fontaine` dipertahankan karena `laravel-vite-plugin` memuatnya secara dinamis untuk fallback font; penghapusan paket tidak terbukti aman.

## Batas pembaruan dependensi

Audit saat pemeriksaan menemukan satu advisory berisiko rendah pada `laravel/framework` v13.39.0 (CVE-2026-128083; rentang terdampak `<13.40.0`). Tidak ada pembaruan otomatis dilakukan karena perubahan dependensi berada di luar lingkup tanpa persetujuan. Versi perlu dinaikkan ke rilis yang sudah diperbaiki sebelum deployment produksi.
