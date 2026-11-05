# Nama panggilan siswa pada Bantuan

Sumber: **Nama Panggilan Siswa.xlsx** yang diberikan pengguna, berisi 35 siswa dan 43 variasi panggilan. Pasangan nama dipetakan ke NIS pada `resources/data/student-nicknames.json`. Nama resmi, akun, nomor telepon, dan data siswa tidak diubah. Perbedaan penulisan Satriya pada sumber dan Satria pada database dipadankan dengan NIS yang sama, bukan mengganti nama siswa.

Contoh panggilan yang didukung:

| Siswa | Panggilan |
| --- | --- |
| Abdullah Syafi'ie | Abdul |
| Bramantio Pratama | Bram |
| Khairatunnisa Azmi Kirana | Khairatunisa |
| Malikha Intan Madinah | Malika |
| Mourin Machrani | Mourin, Morin |
| Muhamad Furqon Panatagama | Furqon, Furkon |
| Muhammad Faqih Ihsan | Faqih, Fakih |
| Putri Aisyah Aprillia | Aisyah, Aisah |
| Seruni Aletha Kami Djuhaeni | Ale |
| Syafii Qotu Aqiilah Qurrotu Ainii | Aqila, Akila |
| Thalita Zafirah Isnaini | Thalita, Talita |
| Timothy Frans Wuri | Timothy, Timoti |
| Wenny Alistantia Wijaya | Wenny, Weni |

Semua baris spreadsheet, termasuk yang tidak dicontohkan di atas, tersedia dalam registry. `Riski` ditetapkan untuk Mohammad Rizky; `Riski fatur` untuk Rizki Faturrahman; `Fahrezi` untuk Muhammad Rizki Fahrezi. Ini adalah pemetaan eksplisit dari sumber, bukan penggantian fonetik umum untuk semua nama.

Contoh Bantuan:

- `Tampilkan alamat weni`
- `Timoti tinggal dimana?`
- `Apa agama malika?`
- `Berapa kali fakih sakit?`
- `Rekap izin riski fatur`
- `Kirim perkenalan ke morin`
- `Kirim kolase 5 November 2026 ke orangtua timoti`

Pencocokan nama mengabaikan kapitalisasi/tanda baca dan memakai batas kata. Nama lengkap/NIS yang cocok persis diprioritaskan, diikuti panggilan terdaftar, kemudian bagian nama resmi. Panggilan yang bertumpang tindih diperiksa pada posisi kemunculannya sehingga `riski` dan `riski fatur` dapat ditanyakan bersamaan. Nama yang masih ambigu menghasilkan permintaan nama lengkap/NIS atau pilihan penerima, tanpa menebak alamat/tujuan pengiriman.

`StudentNicknames::forNis()` memasok alias hanya untuk siswa pada Bootstrap wali kelas dan API pertanyaan absensi yang sudah memeriksa kepemilikan kelas. Klien memakai pencocokan bersama untuk alamat, pertanyaan profil, rekap absensi, dan kontak penerima perkenalan/kolase. Registry lengkap tidak dimuat sebagai aset publik. Pergantian Bootstrap mengganti kamus alias, bukan menggabungkannya dengan konteks sebelumnya. Perintah kirim tetap mengikuti pemeriksaan dan alur gateway yang sudah ada.

Pengujian tanpa pengiriman nyata:

```sh
php tests/student-nicknames.php
python3 tests/student-nicknames-browser.py
AIWALAS_BROWSER_TEST=1 python3 tests/attendance-questions-local.py
```

Tes browser memerlukan Chrome pengujian port 9222. Tes panggilan memakai profil/alamat sintetis dan gateway palsu. Tes absensi memakai fixture development yang dibersihkan setelah selesai.
