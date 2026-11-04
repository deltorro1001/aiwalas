# Portal Tamu AIWalas

Pilih peran **Tamu** pada halaman login. Akun bersama yang disediakan migrasi:

- Username: `tamu`
- Password: `tamu123`

Menu tamu hanya Beranda, Jadwal Mapel, Pengumuman, dan Kalender Akademik. Beranda menggunakan komponen yang sama dengan wali kelas: empat kartu statistik, Ringkasan Kelas beserta grafik/tab jadwal/tab menunggu keterangan, Siswa Tidak Hadir Hari Ini beserta keterangan surat terlampir, dan Notifikasi terbaru hari ini. Ucapan pada Beranda tepat **Selamat Datang di aiwalas**, tanpa nama pengguna atau sapaan tambahan. Semua kartu hanya dapat dilihat; tombol menuju detail/notifikasi dan tombol tanggal dinonaktifkan. Tab ringkasan dan pilihan kelas tetap dapat dipakai untuk melihat informasi. Data diperbarui setiap 15 detik selama Beranda tamu terlihat.

Beranda menampilkan nama/NIS siswa sesuai kebutuhan komponen wali kelas serta status kehadiran tanggal WIB saat ini. API tamu hanya memilih kolom yang dibutuhkan tampilan, tanpa nomor telepon siswa/orangtua, data keluarga, password, isi berkas surat, atau tautan lampiran. Notifikasi Beranda mengikuti notifikasi hari ini milik wali kelas dari kelas yang dipilih, maksimal 10, hanya judul/isi/waktu. Jadwal memuat Senin–Jumat, waktu, mata pelajaran, serta nama guru tanpa nomor telepon. Kalender memuat hari Libur, PJJ, dan tanggal agenda dari pengumuman yang diterbitkan.

Pengumuman tamu disimpan terpisah dari notifikasi internal. Wali kelas mengelola menu **Pengumuman Tamu** untuk kelasnya, dapat menyimpan draf, menerbitkan, menarik kembali publikasi, atau menghapus. Pengumuman baru berupa draf secara default. Tanggal agenda opsional dan tidak mengubah kalender absensi. Tamu hanya memperoleh pengumuman terbit untuk kelas aktif yang dipilih.

Pembatasan dilakukan pada server melalui AuthFilter: tamu hanya dapat membaca `api/bootstrap` yang sudah menyaring data pribadi dan `api/tamu`. Autentikasi, CSRF, dan logout tetap tersedia; perubahan password akun bersama ditolak. API jurnal, nilai, absensi individu, akun, foto, surat sakit/izin, komunikasi, serta endpoint pengelolaan notifikasi tetap tidak dapat diakses tamu. Ringkasan Beranda dibaca melalui `api/tamu`, tanpa membuka endpoint tulis atau mengubah peran akun menjadi wali kelas. Sesi tamu yang akunnya dinonaktifkan juga ditolak.

Migrasi membuat tabel `pengumuman_tamu` dan akun tamu menggunakan hash password. Jika username tamu sudah dipakai peran lain, migrasi berhenti tanpa mengganti peran tersebut. Akun guest lama tetap menggunakan kredensialnya tetapi memperoleh pembatasan yang sama. Migrasi tidak mengubah akun siswa/guru/wali kelas.

## Pemeriksaan lokal

```sh
php spark migrate --all
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/guest-portal-local.py
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/journal-dates-local.py
```

Tes menggunakan data sementara pada database development dan Chrome DevTools localhost:9222. Test data dibersihkan; akun bersama tamu tetap tersedia. Tidak ada pengiriman pesan atau perubahan VPS saat pengujian lokal.
