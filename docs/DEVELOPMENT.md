# Panduan Development AIWalas

Panduan ini dipakai oleh dua developer pada dua komputer yang berbagi satu repository GitHub.

## 1. Persiapan komputer baru

Kebutuhan:

- PHP 8.2 atau lebih baru dengan ekstensi `mysqli`, `intl`, dan `mbstring`
- Composer
- Git
- MySQL/MariaDB
- Node.js opsional untuk pemeriksaan JavaScript

Clone repository private:

```bash
git clone git@github.com:rahmad3501/aiwalas.git
cd aiwalas
composer install
```

Buat konfigurasi lokal dari template:

```bash
cp .env.example .env
```

Edit `.env` dan gunakan database lokal masing-masing. Jangan menyalin `.env` ke GitHub.

```bash
php spark migrate
php spark db:seed InitialDataSeeder
php spark serve
```

Buka `http://localhost:8080`.

## 2. Akses GitHub untuk developer kedua

Setiap komputer sebaiknya memiliki SSH key sendiri. Di komputer baru:

```bash
ssh-keygen -t ed25519 -C "email-github-developer"
cat ~/.ssh/id_ed25519.pub
```

Tambahkan public key ke akun GitHub yang memiliki akses ke repository `rahmad3501/aiwalas`, lalu uji:

```bash
ssh -T git@github.com
git remote -v
```

Private key (`~/.ssh/id_ed25519`) tidak boleh dibagikan.

## 3. Alur kerja harian

Jangan mengerjakan langsung di `master`. Buat branch berdasarkan fitur atau perbaikan:

```bash
git switch master
git pull --ff-only origin master
git switch -c feature/nama-fitur
```

Kerjakan perubahan, lalu periksa:

```bash
php -l app/Controllers/NamaController.php
composer validate
php spark routes
```

Commit dengan pesan yang jelas:

```bash
git add app public composer.json composer.lock README.md docs
git commit -m "Perbaiki menu Maintenance"
git push -u origin feature/nama-fitur
```

Buat Pull Request ke `master`. Developer lain meninjau perubahan dan menjalankan pengujian sebelum merge.

## 4. Sinkronisasi sebelum mulai bekerja

```bash
git switch master
git pull --ff-only origin master
git switch feature/nama-fitur
```

Jika branch sudah lama dan ingin diperbarui:

```bash
git fetch origin
git rebase origin/master
```

Jika konflik muncul, selesaikan file yang ditandai Git, lalu:

```bash
git add file-yang-sudah-diperbaiki
git rebase --continue
```

Batalkan rebase jika diperlukan:

```bash
git rebase --abort
```

## 5. Aturan penting dua developer

- Satu fitur dikerjakan dalam satu branch.
- Jangan mengubah `.env`, `writable/`, atau `vendor/` lalu meng-commit-nya.
- Migration baru harus memiliki nama timestamp unik.
- Setelah mengubah database, sertakan migration pada commit yang sama.
- Jangan melakukan `git push --force` ke `master`.
- Sebelum Pull Request, jalankan `git pull --ff-only origin master` atau rebase branch.
- Jangan menghapus migration yang sudah pernah dijalankan di VPS.
