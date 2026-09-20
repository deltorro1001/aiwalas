# AIWalas

AIWalas adalah sistem informasi wali kelas berbasis CodeIgniter 4.7, PHP 8.2, MySQL, Bootstrap 5, dan JavaScript. Tampilan mempertahankan desain frontend awal, sedangkan data dan autentikasi dipindahkan ke backend MVC.

## Kebutuhan

- PHP 8.2+ dengan ekstensi `mysqli`, `intl`, dan `mbstring`
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

WhatsApp menyimpan histori sebagai `Disiapkan`, membuat URL `wa.me`, lalu dapat menandainya `Dibuka`; aplikasi tidak mengklaim pesan terkirim tanpa WhatsApp Business API. PDF memakai dialog cetak browser. CSV dapat dibuka di Excel dan menjadi fallback saat pustaka SheetJS CDN tidak tersedia.

## Pemeriksaan

```bash
composer validate
php spark migrate:status
php spark routes
php spark db:seed InitialDataSeeder
```

Seeder data awal idempotent. Aplikasi belum dianggap siap produksi sebelum seluruh skenario spesifikasi dan pengujian UI selesai.