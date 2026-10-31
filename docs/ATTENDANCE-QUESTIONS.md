# Tanya AIWalas: rekap absensi historis

Pada login Wali Kelas, Tanya AIWalas dapat menjawab pertanyaan kehadiran berdasarkan catatan Absensi Harian untuk kelas yang sedang dipilih.

Periode default selalu 13 Juli 2026 sampai tanggal pertanyaan diajukan dalam WIB, tanpa dibatasi semester pada panel samping. Setiap pertanyaan membaca ulang data terkini. Tanggal mendatang dan catatan sebelum periode tidak ikut dihitung.

## Penggolongan

- Per siswa aktif: nama, NIS, jumlah tiap status, riwayat tanggal dan waktu scan, keterangan yang tersimpan.
- Per status: jumlah siswa dan jumlah catatan; fokus utama Sakit, Izin, dan Terlambat. Alpha dan Hadir juga dapat ditanyakan.
- Per bulan: jumlah tiap status dari catatan tanggal yang relevan.
- Per siswa/kelompok: nama atau NIS, kriteria jumlah, belum pernah tercatat, serta peringkat jumlah terbanyak.

Sakit dan Izin dihitung dalam hari tercatat, bukan jumlah surat atau jumlah permohonan. Terlambat dihitung sebagai kejadian harian sesuai status tersimpan, bukan dihitung ulang dari jam scan atau jumlah pada menu kedisiplinan. Hari tanpa catatan tidak dianggap Alpha. Status kalender Libur/PJJ khusus tetap berlaku sehingga tidak dihitung sebagai Sakit/Izin/Terlambat. Satu tanggal memakai semester kalender yang sesuai: Januari–Juni Genap, Juli–Desember Ganjil, untuk menghindari catatan ganda antarsemester.

## Contoh pertanyaan

- Berapa kali Kanaya terlambat?
- Rekap sakit izin keterlambatan seluruh siswa sejak 13 Juli 2026.
- Siapa saja yang izin lebih dari 2 kali?
- Siapa paling sering terlambat?
- Siapa belum pernah terlambat?
- Kapan Kanaya izin?
- Rekap izin Kanaya per bulan.
- Jumlah sakit Kanaya bulan Agustus 2026.
- Berapa sakit NIS 13062?

Nama parsial yang cocok dengan beberapa siswa meminta nama lengkap atau NIS. Nama yang belum dikenali tidak diartikan sebagai permintaan seluruh kelas. Nama dan catatan ditampilkan sebagai teks, termasuk karakter HTML. Pertanyaan lain tetap memakai fitur Tanya AIWalas sebelumnya.

Rentang bulan/tahun yang diminta beririsan dengan periode dasar; rentang di luar periode atau masa depan tidak ditampilkan sebagai data tersedia. Endpoint `GET api/absensi/tanya?kelas_id=...` hanya dapat dibaca wali kelas pemilik kelas dan tidak memakai cache. Tidak ada perubahan absensi atau pengiriman pesan dari fitur ini.

## Pengujian lokal

```sh
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/attendance-questions-local.py
```

Menggunakan data sementara pada database development dan Chrome DevTools localhost:9222. Memeriksa batas periode, pengelompokan, kecocokan rekap bulanan, duplikasi semester, penolakan kelas/peran lain, interpretasi pertanyaan, data baru setelah koreksi, dan tampilan ponsel. Seluruh data uji dibersihkan setelah selesai.
