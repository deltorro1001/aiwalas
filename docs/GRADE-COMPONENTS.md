# Komponen nilai dan nilai tugas

Daftar komponen standar bersumber dari `App\Libraries\GradeComponents`: UH1–UH6, Tugas 1–6, ATS, dan ASS. Daftar yang sama dipakai template Excel, dropdown input guru, rekap, Guru Mapel, dan tampilan siswa/orangtua.

Template mempunyai kolom NIS, Nama Siswa, dan 14 komponen. Impor mencocokkan NIS, sehingga urutan baris Excel tidak harus sama dengan urutan siswa. Pilih komponen yang ingin diimpor sebelum memilih berkas. Nilai 0 sah; nilai kosong tidak dianggap 0. Nilai yang tersimpan harus berada dalam rentang 0–100.

API menormalkan `Tugas1` dan `Tugas 1` ke identitas komponen yang sama, serta menggunakan kembali ID komponen lama jika sudah ada. Komponen baru dibuat saat guru menyimpan nilai; data dan nilai semester lain tidak dibuat ulang.

**Rerata UH = jumlah semua nilai UH dan Tugas yang terisi / jumlah nilai UH dan Tugas yang terisi.** Contoh tiga UH dan dua Tugas dibagi lima. Nilai 0 termasuk dalam hitungan; kolom kosong dikecualikan. Tampilan rata-rata dibulatkan seperti sebelumnya.

Rerata gabungan ini digunakan pada rekap guru, detail Guru Mapel, dan sebagai Rerata UH dalam rumus rapor yang dipakai Ledger serta laporan. Bobot ujian tetap: dengan ATS, `(3 × Rerata UH + ATS) / 4`; dengan ATS dan ASS, `(3 × Rerata UH + ATS + 2 × ASS) / 6`. Satu nilai yang tersedia tetap mengikuti perilaku rapor sebelumnya. Rata-rata direktori guru mencakup seluruh nilai komponen yang terisi.

Nilai di bawah 80 berwarna merah. Notifikasi nilai Tugas di bawah 80 mengikuti antrean otomatis siswa yang sudah ada; pesan orangtua tetap memerlukan konfirmasi manual wali kelas.

Uji lokal: `python3 tests/task-grades-local.py`, `python3 tests/camera-ledger-local.py`, dan `php tests/student-grade-automation.php`. Uji memakai data sementara; tidak mengirim pesan gateway nyata.
