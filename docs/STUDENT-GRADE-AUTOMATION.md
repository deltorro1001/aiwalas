# Notifikasi nilai siswa otomatis

Input nilai guru (`api/akademik`) dan wali kelas (`api/nilai`) mencatat antrean `student_grade_dispatches` dalam transaksi yang sama dengan nilai. Berlaku untuk nilai baru atau perubahan nilai komponen di bawah 80, termasuk 0; UH1–UH6, ATS, ASS maupun komponen pada endpoint nilai. Nilai yang tidak berubah tidak membuat antrean baru. Perbandingan memakai nilai yang benar-benar tersimpan dengan presisi dua desimal. Nilai lama sebelum fitur ini dipasang tidak dikirim massal.

Nomor tujuan berasal dari `siswa.nomor_hp`, tanpa fallback ke nomor orangtua. Narasi menyebut siswa, mapel, komponen, nilai, dan guru pengampu. UH1 ditulis Ulangan Harian 1. Orangtua tetap memiliki tombol Kirim via Gateway dan dialog konfirmasi pada Penilaian wali kelas. Tombol kirim siswa diganti informasi pengiriman otomatis.

## Worker dan konfigurasi

Jalankan migration, lalu helper scheduler yang sama dengan deployment:

```sh
php spark migrate --all
php scripts/configure-teacher-scheduler.php --apply
```

Helper memasang entri cron terpisah `aiwalas-student-grade-scheduler` setiap menit untuk `php spark whatsapp:student-grades --send`, dengan log `writable/logs/student-grade-cron.log`. Pengiriman tetap berjalan ketika browser ditutup. Antrean diproses bertahap; keterlambatan bisa lebih dari satu menit jika gateway lambat atau antrean banyak. Otomatisasi guru tetap menggunakan cron dan pengaturan sendiri.

Default otomatisasi nilai aktif hanya pada `CI_ENVIRONMENT=production`. Lokal/development nonaktif. Override `.env` opsional `WHATSAPP_STUDENT_GRADE_AUTOMATION_ENABLED = false` menonaktifkan worker tanpa menghapus antrean; ketika diaktifkan kembali, antrean yang masih sesuai nilai terkini diproses. Memerlukan konfigurasi gateway wa.sims.id yang sama dengan pengiriman manual. Tanpa `--send`, perintah tidak memproses atau mengirim pesan. Jangan menjalankan `--send` dengan gateway nyata untuk pengujian.

## Histori dan pencegahan duplikasi

Worker memeriksa ulang nilai dan siswa sebelum klaim. Antrean yang belum dicoba dibatalkan ketika nilai berubah/dihapus; koreksi di bawah 80 menambahkan antrean untuk nilai terbaru. Transaksi dan kunci komponen mencegah input bersamaan membuat antrean ganda. Klaim atomik mencegah dua worker mengirim pekerjaan yang sama. Koreksi yang terjadi setelah pengiriman dimulai tidak bisa menarik kembali pesan yang sudah dalam proses.

Histori Komunikasi milik wali kelas dibuat sebelum gateway dipanggil, kategori Nilai Siswa Otomatis, serta terhubung ke siswa. **Terkirim** berarti gateway telah mengonfirmasi; gagal, belum dikonfirmasi, dan nomor belum tersedia ditampilkan **Pending** dengan penjelasan. Nomor kosong/tidak valid tetap menunggu dan diperiksa kembali pada menit berikutnya. Setelah nomor diperbaiki, worker dapat melanjutkan pekerjaan tersebut.

Kegagalan, timeout, respons tanpa konfirmasi, atau worker yang terputus setelah klaim tidak dikirim ulang otomatis, untuk menghindari pesan ganda. Periksa histori gateway sebelum tindakan manual; pesan mungkin telah diterima meskipun respons hilang. Pekerjaan processing lebih dari lima menit ditandai unknown.

## Pengujian lokal

```sh
php tests/student-grade-automation.php
python3 tests/teacher-assignments-local.py
php tests/teacher-scheduler-config.php
```

Pengujian memakai fixture sementara dan gateway tiruan, tanpa pengiriman WhatsApp nyata. Mencakup ambang 80, nilai 0, pembulatan penyimpanan, delapan komponen, semester, input ulang, rollback, koreksi/penghapusan, nomor kosong, pemulihan nomor, klaim bersamaan, dan hasil gateway tidak pasti.
