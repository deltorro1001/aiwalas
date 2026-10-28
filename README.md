# AIWalas

AIWalas adalah sistem informasi wali kelas berbasis CodeIgniter 4.7, PHP 8.2, MySQL, Bootstrap 5, dan JavaScript. Tampilan mempertahankan desain frontend awal, sedangkan data dan autentikasi dipindahkan ke backend MVC.

## Kebutuhan

- PHP 8.2+ dengan ekstensi `mysqli`, `intl`, `mbstring`, dan `gd` (pengolahan foto dan kolase)
- MySQL/MariaDB, Composer, dan Apache dengan `mod_rewrite`

## Instalasi

1. Salin `.env.example` menjadi `.env`, lalu sesuaikan URL dan koneksi database.
2. Buat database MySQL `aiwalas` dengan charset `utf8mb4`.
3. Jalankan:

```bash
composer install
php spark migrate
php spark db:seed InitialDataSeeder
php spark serve
```

Seluruh akun guru awal dibuat melalui migration: username dan password awal memakai nama panggilan huruf kecil tanpa sapaan/spasi, misalnya `Pak Ruby` menjadi `ruby` / `ruby`. Password disimpan sebagai hash.

Wali Kelas dapat membuat akun tambahan atau reset password melalui menu **Akun Guru**. Username ditentukan Wali Kelas dan password acak ditampilkan sekali. Alternatif CLI: `php spark aiwalas:teacher GURU_ID USERNAME`.

Aplikasi pengembangan tersedia di `http://localhost:8080`.

## Apache

Arahkan virtual host hanya ke direktori `public`:

```apache
<VirtualHost *:80>
    ServerName aiwalas.local
    DocumentRoot "D:/aiwalas/public"
    <Directory "D:/aiwalas/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Tambahkan `127.0.0.1 aiwalas.local` ke hosts, sesuaikan `app.baseURL`, dan pastikan `mod_rewrite` aktif. `public/.htaccess` menangani front controller.

## Akun demo

- Wali Kelas: `sumantoro` / `aiwalas123`
- Sekretaris: `kanaya` / `kanaya123`
- Siswa: NIS sebagai username dan password awal
- Guest: `guest` / `guest123`

Password disimpan sebagai hash. Ganti seluruh password awal sebelum penggunaan nyata.

## Keamanan

Aplikasi menggunakan session CI4, CSRF, filter autentikasi/role, validasi server, protected fields model, password hash, payload data berdasarkan role, dan perlindungan formula injection pada CSV. Direktori `public` wajib menjadi document root agar `.env`, migration, seeder, serta sumber impor tidak terekspos.

## Fallback layanan eksternal

Komunikasi manual memakai tautan `wa.me` dengan histori `Disiapkan`/`Dibuka`. Pengiriman langsung dan PDF menggunakan gateway jika konfigurasi tersedia; status gagal tidak dilaporkan sebagai terkirim. PDF memakai dialog cetak browser. CSV dapat dibuka di Excel dan menjadi fallback saat pustaka SheetJS CDN tidak tersedia.

## Pemeriksaan

```bash
composer validate
php spark migrate:status
php spark routes
php spark db:seed InitialDataSeeder
```

Seeder data awal idempotent. Aplikasi belum dianggap siap produksi sebelum seluruh skenario spesifikasi dan pengujian UI selesai.

Nilai dan jurnal disimpan di database. Patch ZIP lewat web dinonaktifkan; pembaruan kode menggunakan Git. Push ke `main` memicu deployment VPS.


## Pengingat guru otomatis

Scheduler server menyiapkan pengingat 10 menit sebelum jadwal mengajar dan ucapan terima kasih saat jadwal selesai melalui gateway WhatsApp. Histori tampil di Komunikasi dengan status Terkirim/Pending. Wahyu Lukman Hakim, Putu Arya Ranesda, dan Ruby Eka Prawira dikecualikan dari otomatisasi. Lokal default nonaktif; deployment memasang cron VPS dan mengaktifkan flag jika belum dikonfigurasi. Simulasi tersedia lewat `php spark whatsapp:teacher-schedule`. Petunjuk konfigurasi dan pengujian ada di [docs/TEACHER-WHATSAPP-AUTOMATION.md](docs/TEACHER-WHATSAPP-AUTOMATION.md).

## Notifikasi nilai siswa otomatis

Nilai komponen baru atau berubah di bawah 80 masuk antrean WhatsApp siswa setelah penyimpanan berhasil. Orangtua tetap menerima pesan melalui konfirmasi dan pengiriman manual wali kelas. Petunjuk antrean, cron, dan penanganan status tersedia di [docs/STUDENT-GRADE-AUTOMATION.md](docs/STUDENT-GRADE-AUTOMATION.md).


## Upload foto siswa dan kolase

Siswa dapat mengunggah foto disertai keterangan pada Absensi Harian, setelah Rekap Absen. Foto diunggah tanpa timestamp tambahan. Kolase memuat maksimal 35 foto per kelas/tanggal dalam satu halaman, dengan teks “created by aiwalas” dan tanggal/waktu pembuatan di kanan bawah. Wali kelas melihat galeri pada **Upload Foto**, tepat di atas Pengaturan, memilih tanggal, membuat kolase seluruh foto hari tersebut, dan mengirimnya ke nomor guru dari Data Guru. Petunjuk penyimpanan, pengiriman, dan pengujian ada di [docs/STUDENT-PHOTOS.md](docs/STUDENT-PHOTOS.md).
