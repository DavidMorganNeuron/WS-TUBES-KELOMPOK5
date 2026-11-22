# Kandidat tautan Wikidata destinasi

- Dibuat: 2026-11-15T11:57:42+07:00.
- Kelas wisata diverifikasi live dari Query Service: tourist attraction ([`Q570116`](https://www.wikidata.org/wiki/Q570116)).
- Penemuan memakai `wikibase:box` pada kotak Sumatera Utara: lintang 0,5–4,5 dan bujur 97–100,5; satu kueri batch kelas/koordinat, bukan satu kueri per destinasi.
- Nama lokal dan alternatif dinormalisasi; label serta alias Wikidata Indonesia/Inggris dicocokkan lokal. Jarak dihitung dengan Haversine (jari-jari bumi 6.371,0088 km).
- Skor = 75% kemiripan nama (`similar_text` atas nama ternormalisasi terbaik) + 25% kedekatan; kedekatan = `100 × max(0, 1 − jarak_km/20)`.
- Ambang tinjauan: jarak ≤20 km dan kemiripan nama ≥90%; jika tidak ada pasangan ≥90%, kandidat nama terdekat dengan kemiripan ≥60% ditampilkan sebagai DITOLAK.
- Asumsi konservatif D16 untuk AUTO-SETUJU: nama ternormalisasi identik, jarak ≤0,5 km, dan tautan satu-ke-satu yang tidak berbagi QID. Kasus lain tetap AMBIGU/DITOLAK untuk keputusan manusia.
- Kolom KEPUTUSAN PEMILIK sengaja kosong. QID sameAs hanya berasal dari daftar `setuju` pada berkas JSON; `tolak` dapat ditambahkan pemilik.
- Jumlah kandidat: 3.

| ID | Destinasi | QID | Label Wikidata | Kelas langsung | Jarak (km) | Skor | Status | Alasan | KEPUTUSAN PEMILIK |
| --- | --- | --- | --- | --- | ---: | ---: | --- | --- | --- |
| ws-049 | Medan Zoo | [`Q136687552`](https://www.wikidata.org/wiki/Q136687552) | Medan Zoo | kebun binatang (Q43501); zoo (Q43501) | 0.26 | 99.7 | AUTO-SETUJU | Nama ternormalisasi sama persis, jarak paling banyak 0,5 km, dan tautan satu-ke-satu. |  |
| ws-108 | Bukit Sibea-bea | [`Q107339315`](https://www.wikidata.org/wiki/Q107339315) | Bukit Sibea-bea | objek wisata (Q570116); tourist attraction (Q570116) | 2.08 | 97.4 | AMBIGU | Nama sangat mirip, tetapi jarak melebihi ambang persetujuan otomatis 0,5 km. |  |
| ws-195 | Siantar Waterpark | [`Q12490160`](https://www.wikidata.org/wiki/Q12490160) | Kebun Binatang Pematang Siantar | kebun binatang (Q43501); zoo (Q43501) | 3.59 | 74.1 | DITOLAK | Kemiripan nama kurang dari 90%; jarak saja tidak cukup untuk menyetujui identitas. |  |
