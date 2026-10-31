# Portal Tamu AIWalas

Pilih peran **Tamu** pada halaman login. Akun bersama yang disediakan migrasi:

- Username: `tamu`
- Password: `tamu123`

Menu tamu hanya Beranda, Jadwal Mapel, Pengumuman, dan Kalender Akademik. Beranda berisi jumlah siswa dan agregat status kehadiran pada tanggal WIB saat ini, tanpa identitas siswa. Jadwal memuat Senin–Jumat, waktu, mata pelajaran, serta nama guru tanpa nomor telepon. Kalender memuat hari Libur, PJJ, dan tanggal agenda dari pengumuman yang diterbitkan.

Pengumuman tamu disimpan terpisah dari notifikasi internal. Wali kelas mengelola menu **Pengumuman Tamu** untuk kelasnya, dapat menyimpan draf, menerbitkan, menarik kembali publikasi, atau menghapus. Pengumuman baru berupa draf secara default. Tanggal agenda opsional dan tidak mengubah kalender absensi. Tamu hanya memperoleh pengumuman terbit untuk kelas aktif yang dipilih.

Pembatasan dilakukan pada server melalui AuthFilter: tamu hanya dapat membaca `api/bootstrap` yang sudah menyaring data pribadi dan `api/tamu`. Autentikasi, CSRF, dan logout tetap tersedia; perubahan password akun bersama ditolak. API jurnal, nilai, absensi individu, akun, foto, surat sakit/izin, komunikasi, dan notifikasi internal tidak dapat diakses tamu. Menyembunyikan tombol bukan satu-satunya pembatasan. Sesi tamu yang akunnya dinonaktifkan juga ditolak.

Migrasi membuat tabel `pengumuman_tamu` dan akun tamu menggunakan hash password. Jika username tamu sudah dipakai peran lain, migrasi berhenti tanpa mengganti peran tersebut. Akun guest lama tetap menggunakan kredensialnya tetapi memperoleh pembatasan yang sama. Migrasi tidak mengubah akun siswa/guru/wali kelas.

## Pemeriksaan lokal

```sh
php spark migrate --all
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/guest-portal-local.py
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/journal-dates-local.py
```

Tes menggunakan data sementara pada database development dan Chrome DevTools localhost:9222. Test data dibersihkan; akun bersama tamu tetap tersedia. Tidak ada pengiriman pesan atau perubahan VPS saat pengujian lokal.
