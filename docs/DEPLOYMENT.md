# Panduan Deployment AIWalas ke VPS

Dokumen ini menggunakan repository private `rahmad3501/aiwalas` dan aplikasi pada `/var/www/html/aiwalas`.

## 1. Struktur VPS

- Document root Apache: `/var/www/html/aiwalas/public`
- Source aplikasi: `/var/www/html/aiwalas`
- Konfigurasi production: `/var/www/html/aiwalas/.env`
- Runtime CI4: `/var/www/html/aiwalas/writable`

`.env` dan `writable/` adalah milik VPS. Keduanya tidak diambil dari GitHub.

## 2. Setup pertama kali

Pastikan SSH key VPS sudah terdaftar pada akun GitHub yang memiliki akses repository:

```bash
ssh -T git@github.com
```

Clone repository:

```bash
cd /var/www/html
git clone git@github.com:rahmad3501/aiwalas.git aiwalas
cd aiwalas
```

Buat `.env` production:

```bash
cp .env.example .env
nano .env
```

Isi database production, `app.baseURL`, dan secret lainnya. Jangan commit file ini.

Pasang dependency dan database:

```bash
composer install --no-dev --optimize-autoloader
php spark migrate
php spark db:seed InitialDataSeeder
php spark cache:clear
```

## 3. Permission

Gunakan user aplikasi sebagai pemilik source dan group web server untuk membaca source:

```bash
sudo chown -R deltorro1001:www-data /var/www/html/aiwalas

sudo find /var/www/html/aiwalas/app /var/www/html/aiwalas/public \
  -type d -exec chmod 750 {} \;
sudo find /var/www/html/aiwalas/app /var/www/html/aiwalas/public \
  -type f -exec chmod 640 {} \;

sudo find /var/www/html/aiwalas/writable \
  -type d -exec chmod 770 {} \;
sudo find /var/www/html/aiwalas/writable \
  -type f -exec chmod 660 {} \;

sudo chmod 640 /var/www/html/aiwalas/.env
```

`app/` dan `public/` tidak perlu writable oleh `www-data` untuk deployment Git biasa. Folder `writable/` harus writable oleh proses PHP.

## 4. Update normal dari GitHub

Jalankan setelah Pull Request sudah di-merge ke `master`:

```bash
cd /var/www/html/aiwalas
git pull --ff-only origin master
composer install --no-dev --optimize-autoloader
php spark migrate
php spark cache:clear
sudo systemctl reload php8.2-fpm
```

Perintah tersebut tidak menimpa `.env` atau menghapus `writable/`.

## 5. Backup sebelum update besar

```bash
cd /var/www/html
sudo tar -czf aiwalas-backup-$(date +%Y%m%d-%H%M%S).tar.gz \
  --exclude='aiwalas/vendor' \
  --exclude='aiwalas/writable/cache' \
  --exclude='aiwalas/writable/debugbar' \
  --exclude='aiwalas/writable/session' \
  aiwalas
```

Backup database juga dianjurkan:

```bash
mysqldump -u USER_DB -p DATABASE_DB > aiwalas-db-$(date +%Y%m%d-%H%M%S).sql
```

## 6. Verifikasi setelah deploy

```bash
cd /var/www/html/aiwalas
php spark migrate:status
php spark routes
curl -I https://aiwalas.deltorro1001.com
```

Uji di browser:

- Login Wali Kelas
- Dashboard
- Absensi
- Menu Maintenance
- Upload patch hanya jika memang diperlukan

## 7. Rollback sederhana

Jika update bermasalah dan commit sebelumnya masih sehat:

```bash
cd /var/www/html/aiwalas
git log --oneline -5
git checkout master
git reset --hard COMMIT_SEBELUMNYA
composer install --no-dev --optimize-autoloader
php spark cache:clear
sudo systemctl reload php8.2-fpm
```

Jangan melakukan rollback migration secara sembarangan. Migration database perlu rollback khusus dan backup database.
