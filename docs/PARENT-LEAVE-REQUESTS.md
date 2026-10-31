# Pengajuan Izin Orangtua

Kartu Pengajuan Izin tersedia pada Absensi Harian akun orangtua/wali murid, setelah Pengajuan Istirahat Sakit. Formulir berisi:

- Jenis keperluan: keluarga, administrasi, kegiatan di luar sekolah, atau lainnya.
- Lama izin 1–60 hari kalender, tanggal mulai dan selesai yang saling menyesuaikan.
- Alasan izin wajib diisi, maksimal 1.000 karakter.
- Lampiran opsional melalui Choose File/Pilih File: JPEG/JPG/PNG/PDF maksimal 2 MB.

Identitas siswa dan kelas ditentukan dari sesi orangtua. Pengajuan tersimpan di tabel pengajuan_izin, dengan riwayat yang dapat dilihat orangtua terkait dan wali kelas pemilik kelas. Wali kelas melihat kartu riwayat pada Absensi Harian dan memperbarui kehadiran melalui Koreksi setelah meninjau pengajuan. Pengajuan tidak langsung mengubah absensi atau mengirim pesan WhatsApp.

Lampiran disimpan di writable/pengajuan-izin menggunakan nama acak, diunduh melalui endpoint dengan pemeriksaan kepemilikan dan tanpa cache. Tipe MIME diperiksa di server. Pengajuan dengan rentang tanggal tumpang tindih ditolak, termasuk pengiriman ganda. Pengajuan sakit tetap memiliki alur dan riwayat tersendiri.

## Instalasi dan pengujian lokal

```sh
php spark migrate --all
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/parent-leave-local.py
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/parent-login-local.py
```

Tes menggunakan database development lokal, data sementara, dan Chrome DevTools pada localhost:9222. Data serta berkas uji dibersihkan setelah selesai. Migrasi harus diterapkan saat fitur ini dideploy.
