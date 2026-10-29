# Menu kehadiran dan kedisiplinan guru

Login guru memakai tampilan bersama wali kelas untuk Absensi Harian, Rekap Absen, dan Status Kedisiplinan. Data hanya tersedia untuk kelas yang memiliki penugasan guru melalui jadwal atau pengampu mapel.

- Absensi Harian: pilihan tanggal, filter status, statistik, dan waktu scan tersedia; Koreksi serta pemindaian/pembuatan QR dinonaktifkan.
- Rekap Absen: rekap kelas dan individu, tahun/bulan, dan Export Excel mengikuti komponen yang sama dengan wali kelas.
- Status Kedisiplinan: ketiga jenis pelanggaran dan Export tersedia; Edit dinonaktifkan.
- API GET memeriksa penugasan kelas. Rute POST koreksi absensi, pembuatan QR, dan penyimpanan kedisiplinan tetap menolak peran guru.
- Akses tulis wali kelas, sekretaris, dan absensi mandiri siswa mengikuti aturan sebelumnya.

## Pemeriksaan lokal

Dengan aplikasi development dan MySQL lokal aktif:

```sh
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/teacher-attendance-read-local.py
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/class-attendance-recap-local.py
```

Opsi browser memerlukan Chrome DevTools pada localhost:9222. Pengujian memakai data sementara yang dibersihkan setelah selesai, tanpa pengiriman pesan gateway.
