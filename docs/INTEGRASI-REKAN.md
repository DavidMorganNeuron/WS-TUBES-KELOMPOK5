# Panduan integrasi rekan

Dokumen ini mencatat batas integrasi agar perubahan dapat digabung tanpa menimpa pekerjaan anggota lain.

## Batas kepemilikan

- Koordinasikan setiap perubahan pada implementasi chatbot atau peta milik rekan sebelum mengubah file tersebut. Jangan mengubah `ChatbotTest` sebagai jalan pintas untuk meluluskan tes.
- Perubahan bersama pada route atau konfigurasi middleware harus tetap menjaga batas chatbot 10 permintaan/menit dan kontrak endpoint peta yang ada.
- Jangan melakukan format massal atau penggantian nama file pada area yang tidak terkait.
- Perubahan integrasi tahap ini menambahkan middleware pada route SSR; controller dan JavaScript peta/chatbot tidak diubah.

## Integrasi data ontologi

1. Pertahankan `database/ontology/wisata_sumut_v2.ttl` sebagai sumber migrasi yang tidak ditimpa.
2. Bila graph perlu diubah, perbarui masukan/generator yang relevan lalu jalankan `php artisan ontology:migrate-v3`.
3. Jalankan `php artisan ontology:validate`; jangan menambahkan triple secara manual ke graph hasil generator.
4. QID destinasi hanya boleh disetujui melalui `database/ontology/external/wikidata-destinasi-tautan.json`. Baris ambigu menunggu keputusan pemilik; penolakan disimpan pada kunci `tolak`.
5. Untuk perubahan UI eksternal, jangan tampilkan teks asal data; pertahankan tautan foto ke halaman berkas Commons agar lisensi per berkas dapat diperiksa.

## Integrasi kode

- Pertahankan kontrak `DestinationSource` agar driver Fuseki dan Turtle menghasilkan perilaku pencarian yang sepadan.
- Gunakan `DestinationRepository`, komponen Blade, dan pembangun query yang sudah ada sebelum menambahkan jalur baru.
- Jangan merangkai input request ke SPARQL Wikidata. QID harus berasal dari katalog wilayah atau daftar persetujuan.
- Pertahankan pesan error publik generik, header keamanan, dan throttle route yang ada.
- Perubahan eksternal harus tetap memiliki tes terfokus dengan HTTP palsu; tes tidak boleh membuat panggilan jaringan tanpa sengaja.

## Sebelum menggabungkan perubahan

```bash
php artisan test --compact
php artisan ontology:validate
vendor/bin/pint --dirty --format agent
git diff --check
git status --short
```

Pastikan `ChatbotTest` lulus, tidak ada berkas `.env`/kredensial/cache/backup pada diff, dan file milik rekan yang dilindungi tidak berubah. Jangan push atau membuat commit atas nama rekan tanpa persetujuan.
