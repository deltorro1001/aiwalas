# Pengajuan Izin Orangtua

Kartu Pengajuan Izin tersedia pada Absensi Harian akun orangtua/wali murid, setelah Pengajuan Istirahat Sakit. Formulir berisi:

- Jenis keperluan: keluarga, administrasi, kegiatan di luar sekolah, atau lainnya.
- Lama izin 1–60 hari kalender, tanggal mulai dan selesai yang saling menyesuaikan.
- Alasan izin wajib diisi, maksimal 1.000 karakter.
- Lampiran opsional melalui Choose File/Pilih File: JPEG/JPG/PNG/PDF maksimal 2 MB.

Identitas siswa dan kelas ditentukan dari sesi orangtua. Pengajuan tersimpan di tabel pengajuan_izin, dengan riwayat yang dapat dilihat orangtua terkait dan wali kelas pemilik kelas. Pengajuan baru otomatis mengisi status Izin pada hari sekolah dalam rentang tanggal yang diajukan, dengan waktu scan kosong. Pengajuan sakit baru mengisi Sakit dengan aturan yang sama. Pengajuan lama tidak diterapkan ulang. Libur dan PJJ tetap mengikuti kalender serta catatan yang sudah ada. Wali kelas melihat riwayat dan tetap dapat memperbarui kehadiran melalui Koreksi. Tidak ada pengiriman pesan WhatsApp dari proses ini.

Lampiran disimpan di writable/pengajuan-izin menggunakan nama acak, diunduh melalui endpoint dengan pemeriksaan kepemilikan dan tanpa cache. Tipe MIME diperiksa di server. Pengajuan sakit/izin dengan rentang tanggal tumpang tindih ditolak, termasuk pengiriman ganda. Pengajuan sakit tetap memiliki alur dan riwayat tersendiri.

## Instalasi dan pengujian lokal

```sh
php spark migrate --all
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/parent-attendance-local.py
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/parent-leave-local.py
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/parent-login-local.py
```

Tes menggunakan database development lokal, data sementara, dan Chrome DevTools pada localhost:9222. Data serta berkas uji dibersihkan setelah selesai. Migrasi harus diterapkan saat fitur ini dideploy.

## Konsistensi kehadiran

Pengajuan dan absensi disimpan dalam transaksi yang sama dengan penguncian baris siswa. Semester ditentukan per tanggal: Januari–Juni Genap, Juli–Desember Ganjil. Data absensi tersimpan langsung pada tabel absensi yang dipakai semua login, rekap, dan laporan. Koreksi wali kelas setelah pengajuan tidak diputar ulang atau ditimpa oleh pembacaan riwayat.

Warna label/status Sakit adalah hijau (#15803d), Izin coklat tua (#6b3f1d), termasuk kartu status siswa/orangtua, tabel harian, rekap, pilihan status, dan legenda. PJJ memiliki kelas tampilan tersendiri sehingga tidak ikut berubah menjadi warna Izin.
