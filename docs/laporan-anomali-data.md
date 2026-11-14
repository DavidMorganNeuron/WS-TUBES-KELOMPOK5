# Laporan anomali data wisata Sumatera Utara

- Sumber: `database/ontology/wisata_sumut_v2.ttl` (dibaca saja; tidak diubah).
- Relasi batas wilayah: Wikidata Query Service; diambil 2026-11-14; wilayah diverifikasi sebagai Kabupaten/Kota Indonesia di Sumatera Utara.
- Perbaikan otomatis dibatasi pada trim, deduplikasi, tipe literal, pemecahan kata kunci, dan aturan sentinel.
- Anomali semantik di bawah tidak diperbaiki otomatis.

## Kabupaten/kota pada `beradaDi` tidak ditemukan pada alamat
- ws-028 — Danau Toba: Kabupaten Toba; alamat: Jl. Siborongborong - Parapat, Sibaganding, Kecamatan Girsang Sipangan Bolon, Kabupaten Simalungun, Sumatera Utara 21174
- ws-036 — Gunung Toba: Kabupaten Toba; alamat: Ronggur Nihuta, Kecamatan Ronggur Nihuta, Kabupaten Samosir, Sumatera Utara
- ws-057 — Pea Arung-arung: Kabupaten Samosir; alamat: -
- ws-058 — Pea Horpea: Kabupaten Samosir; alamat: -
- ws-059 — Pea Lintongnihuta: Kabupaten Samosir; alamat: -
- ws-060 — Pea Sosortolong: Kabupaten Samosir; alamat: -
- ws-061 — Pea Taharan: Kabupaten Samosir; alamat: -
- ws-195 — Siantar Waterpark: Kota Pematang Siantar; alamat: Jl. Rakutta Sembiring No. 1, Kel. Naga Pita, Kec. Siantar Martoba, Kota Pematangsiantar, Sumatera Utara

## Koordinat di luar batas Sumatera Utara (lintang 0.5–4.5, bujur 97.0–100.5)
- Tidak ditemukan.

## Nama destinasi duplikat
- ws-052 — Huta Siallagan
- ws-105 — Huta Siallagan
- ws-121 — Air Terjun Linggahara
- ws-205 — Air Terjun Linggahara

## Harga masuk negatif
- Tidak ditemukan.

## Rating numerik di luar rentang 0–5
- Tidak ditemukan.

## Jam operasional yang tidak dapat diparse dengan pasti
- ws-007 — Candi Bahal: Setiap Hari 08.00 - 03.00
- ws-034 — Gunung Sinabung: Ditutup Sementara
- ws-040 — Kuil Shri Mariamman: Setiap Hari 06.00-12.00 dan 16.00-20.00
- ws-079 — Museum Pusaka Karo: Selasa-Jumat 10.00-17.00, Minggu 14.30-17.30
- ws-085 — Candi Bahal II: Setiap Hari 08.00-03.00
- ws-132 — Istana Maimun: Senin-Minggu 08.00-17.00 (Jumat 08.00-12.00 dan 14.00-17.00)
- ws-179 — Pantai Ujung Sibolga: Setiap hari 08.00-24.00
- ws-192 — Pantai Garoga Tigaras: Senin-Jumat 09.00-17.00, Sabtu-Minggu 08.00-17.00
- ws-194 — Jona Garden Binjai: Senin-Jumat 09.00-22.00, Sabtu-Minggu 08.00-22.00
- ws-195 — Siantar Waterpark: Senin-Jumat 09.00-18.00, Sabtu-Minggu 08.00-21.00
- ws-197 — Pemandian Alam Sembahe: Senin-Jumat 09.00-17.00, Sabtu-Minggu 08.00-18.00
- ws-211 — Jungle River Sibolangit: Senin-Jumat 08.00-23.00, Sabtu-Minggu 00.00-23.00
- ws-215 — Singapore Land Waterpark: Senin-Kamis 10.00-18.00, Jumat-Minggu 09.00-18.00
- ws-219 — Pos Bloc Medan: Senin-Kamis 10.00-22.00, Jumat-Minggu 07.00-00.00

## URL gambar dari host tidak stabil
- `encrypted-tbn0.gstatic.com`: 61 URL.
  - ws-003 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQE7wOvcID9TwfKkPbBKcm7aZX-82KpsdExisn2A3NJ8pgb_3zNS6EpyhE&s=10
  - ws-004 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSE12qMoDoDqBknnfKaHFevvqxNXPQv2XSrFvXLVy2EKZMBi3JHXRB70AuF&s=10
  - ws-008 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ2gWYgebUo3AUtOA2BcI_Pb-VIGFSZptd-QqLBRKiGciOGlz4ydjW1ZkVX&s=10
  - ws-019 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR1hImsrvdjrk_8BChyDh5YHLGOc74y-L5wg1wj6PNh42E4atvXTd5BV2OK&s=10
  - ws-027 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT37vgxXoZmG9MTK0FZ7gCqFCT_xx3fzo3FZj0P3S41KjilvwQlrS-_vog&s=10
  - ws-029 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTTRzNq6aidK-auXkLP2JmfBpw3pEESGuIIZa5fV9YvoJJ-RbBFcrvotsA&s=10
  - ws-030 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS_BnrXzleFMuKl4f_MlrHYsArZQdTWOghNt5Y7n41gKioTH2r7yt7mNX4&s=10
  - ws-040 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTz8FWXX_amnvYeFHcIlw0a19_rwuRiswHqhpqCpYdR7Q&s=10
  - ws-046 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSockXvthm8BP4TSzZJadVS6jdWWKOGSo8P3f2_jPANMsy0LIXYAP55v6PY&s=10
  - ws-059 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQoPgU-RRJE9cvA3lrGgpida-VoA643n6OJeoPTnJDrOB4j-8NEE9dh3w&s=10
  - ws-060 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSH4pS8KeJl5Mg4wpjObDbxxXuLuA5YncufM9HlZAQx7VFIgxRVok06o-U&s=10
  - ws-064 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRdZ_tvXjnFQ-1IMlksH_0a3SjEf2Ya3rYi6JRhn3guUQ&s=10
  - ws-072 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQKpW9aHkyJ6e691mx3Jy0q9Zs-Y4YFBoQUfBuBOhFtNCPacyWOq42n8AoS&s=10
  - ws-076 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS5ukdtIe6GMhHFNnormoSxjcySJnJxbxCYBC9Soo8vS7V00h9DI3QKV79r&s=10
  - ws-081 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSt0QnX7XuoG9hgssFxAzLBxodBbX-Zg0PUhquYUz8elVWCih4ycujQnetV&s=10
  - ws-082 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR7Jv9HaJJRXjFs3mkk84Z6BixxYaK9iB1nrmQuDVE0A-WHvo6I-BKLZ2Q&s=10
  - ws-088 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTh88D1bp1UVD661IeY1U5Ca0rdDJfhsn7aONqohZTdjJPHUuS5AJREQLQ&s=10
  - ws-092 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTxxjDjvWNsHn7p5QDU1sSuL2JQ1YwizQQB0DSb3fTzPL0anHAwP7L9gY5k&s=10
  - ws-097 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT4W1BmOW96JhsAH-ULHdPnbImnyblI9S7xXsw01t1yM0v_fzQbPyj3dM8&s=10
  - ws-099 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRVdgBU2Z1Mm9STsBW8eHecP6Xx5s-tbSvSEKyJAyEYQKwm6pfz8HvzQVI&s=10
  - ws-100 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcStDZkhQED0dfc5Gt9cXhPbaH5PjLrsPDYsJiqzYdCKqtNpCzC0nYhpHNlS&s=10
  - ws-101 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRWZsAUBxOw8D9mutAwjkMA5KCMC_xgADYn7rTH6fry6xnryRD1DwHSeSlo&s=10
  - ws-103 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRps5QMJLzaQKy6S8BPFpi05KzROr9pzdTKR1xSLUH_LvXkAre5jgZMB2jK&s=10
  - ws-105 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTT2d9ippqWvFRxNzK3PFUpnxtJp_P9mdYW64FuqiYzc5ESPUbcaNBryLMv&s=10
  - ws-106 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQoKR22HT9cUWT4P5SIPAY61Dc8_MaFl0cRfRFzHCS2bg&s=10
  - ws-107 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTraFWdYHlow4dQSykEvxng2cAMaD5fVsmy2i8L0XEOGlnHAznJpj5BChY&s=10
  - ws-108 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSJxAE-WSkqDA4LXui5YEMeVIb0-dnSx4egXPZcceBUuQ&s=10
  - ws-109 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTgFgKsKxEU9zhJDF2HNtwXOl2OwM6llB2AeGk6mEL9BHWgGXqoDjhkDsA&s=10
  - ws-110 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ_HzSOrhso1piDlKcAVREkK9fMVBvp5imRYgw7blVotcPsThkJuaap9z0&s=10
  - ws-111 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS4ZEjrx7qlBO7zKIYRu3qXp1hQdZg3WRtMucGBaZGc6w&s=10
  - ws-112 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQhAUJPEZ23AIsVWgEV1tT0wWan55Omyl-x-tnC2pkt5W9ES7PngfFSPKjv&s=10
  - ws-113 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSWWyGYPr4av0vuNjxMV9TJaC91wEXqHndWWh8LevfrLg&s=10
  - ws-117 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTgY2xtvClJszHOoOoFCkxeZKcI7pPwYJVz7C83FKsLNKRl0NVcEx317ySV&s=10
  - ws-118 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTa1tLOK09ZD4fhB87OpxyBNNEh3kLrIFOOtmRtBsXqJw&s
  - ws-119 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR_d37YJdAQszbt7X8-pj57253MY3mG5EEQ4ZWhRryZ16u6UpYsruzNVME&s=10
  - ws-120 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTvYnisiN5hJ-mU_AfbS4LUBMlB_99olr7ZbNo1YiZ8MA&s=10
  - ws-122 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQtkWGVWDmS0kvMhSD9VG3iwDVPBSkqtbIsuK5At-M7Q3USpu8iaZPswcQJ&s=10
  - ws-123 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRxUzRM88gYQ7dT7pRmVylgazG3QgbRgMuGMG_cHfe39A&s=10
  - ws-126 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSOWWImMzZGiV08UuD5a47ZfXELGqyD9_CS09DMGRYxhWI1nXuO9E7-btZP&s=10
  - ws-127 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSfNH-dIOy_4hdo9lY6HMx4wjNsSaXGWkTg39gVwX1bIfTNBz9WUssl678&s=10
  - ws-128 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQk9Gmwv7Xl58WUh0EKN6wjGTzVcn5iXvxBARdOpC9XxA&s=10
  - ws-129 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRcBxdHi5dfUsDPPB6Rav_KnStKoRdwQbt3uaVTlJD-rxNbHmACQ9M236o&s=10
  - ws-130 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRaPyL8hVyX4WNnRawBvHGbsgBuAk8fLQnKXCtcUa-lJRwc8BN3l8W1e5CR&s=10
  - ws-143 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRlVYDV_8DXbu388rb8S-AhYC_orMmz_9PewfqIokqmG0UdtVDXL1LiXSGS&s=10
  - ws-152 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRrAc_RyJ-XeEh5y6I-FrqwJEEZ-pZAT6YzOr4fAGapKFqPfuwtpp8pRVY&s=10
  - ws-163 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSgA1e5PqCuV_wSOeovKfow-OwcNUtTAtL_WgAj7Yku7YP5z6KNFVnbDShT&s=10
  - ws-164 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQcDAGCiJEi0z0Gt6vemJlGCmXc9Y7vb-d6T-PjvGILIWqcI0dR0F4b78k&s=10
  - ws-165 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQIwp4r6Q5pWFHr2qzY-sScGI061_r8gtvkqfVDb6bUWQMpSczc8BUOvIM&s=10
  - ws-179 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQqtkypa0gzen3Zv-rWdCJ_c0Dl1ARNwBUbeP11diEr5C4eiX-ia9HKEqg&s=10
  - ws-190 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ7AdSWxakQ1IW5-MMRfzUi-eGg6U-_XmPtBqFB9Xtg-o2Ly8AJD0pG7OY&s=10
  - ws-196 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTPOhkAs71hHtTGGllzvbaZ1mW6kSdMXtXi1QBXyR0IHfHvY2vdNqfZAOKT&s=10
  - ws-197 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR8NL0yBlhFASaNpuZ4p0NJyYcwQy4JTpjsOoRxZemQC-yhoqMNsK_9PcQ&s=10
  - ws-200 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRMbXizMOqw96IDEL4I8CrkSfnDm6cjdaH007fiezUDnWCwnnu4_SBh6PfL&s=10
  - ws-203 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQdigJsllt8r9nY56WL02b8bfzknZc7FhYPHFkxaDu0zBT8m9TETxyKyuqs&s=10
  - ws-205 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRK-bHWr8_A-yhw_7XjOr0WDgkN4_y1zcaf6ORQZ7hvB3en-xCamF4eMVg&s=10
  - ws-211 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRJLZMAZsCuqhOEr7s8EambGO8ozvz1DJfHfyI8UOgzykvy9dNmqcnKPQ&s=10
  - ws-215 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR3I8-e5jehDTm9w_iewMIMPDiGahEc9IN1v_idlcRp61dGSca4D33Uav4&s=10
  - ws-222 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTz2D1e2Lt3XenzSig1r3U_fRGZLRloYj2_2dFTrrePtkkZxmWaOqB1Ntk&s=10
  - ws-224 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRZdYjTRToL2qnFrGL8EgwWCWv4mj0a-cWVxlrYBW0DSxS_erjQLKNJzeRk&s=10
  - ws-226 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS_JGGBG_Oq2f3pbW0lRomzehwR6Ty7ymPHUavMSZGVT2Pt2Q9rXv8HSyQ&s=10
  - ws-228 — https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSA3KBZlzX9UZSuXkCE8XdJySDIJk5pfYkECvgp0UFhsQN88cux098SO6Qw&s=10
- `pinimg.com`: 3 URL.
  - ws-002 — https://i.pinimg.com/1200x/6a/7c/f7/6a7cf77bd4e5a5b426a261c5bebed355.jpg
  - ws-124 — https://i.pinimg.com/736x/82/7b/64/827b6402b2b435ce2ff1dc022e58aa93.jpg
  - ws-145 — https://i.pinimg.com/736x/36/a4/4f/36a44fbab89c12e27afb04fcee27e3f2.jpg
