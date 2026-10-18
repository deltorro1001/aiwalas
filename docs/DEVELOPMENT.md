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
git clone git@github.com:deltorro1001/aiwalas.git
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

Tambahkan public key ke akun GitHub yang memiliki akses ke repository `deltorro1001/aiwalas`, lalu uji:

```bash
ssh -T git@github.com
git remote -v
```

Private key (`~/.ssh/id_ed25519`) tidak boleh dibagikan.

## 3. Alur kerja harian

Buat branch fitur dari `main`, kerjakan dan uji lokal, lalu push branch fitur dan buka Pull Request jika perubahan sudah disetujui. Push atau merge ke `main` memicu deployment VPS.

```bash
git switch main
git pull --ff-only origin main
git switch -c feature/nama-fitur
php spark migrate --all
```

Untuk perubahan lokal saja, jangan commit atau push sampai diminta.

## 4. Sinkronisasi sebelum mulai bekerja

```bash
git switch main
git pull --ff-only origin main
git switch feature/nama-fitur
```

Jika branch sudah lama dan ingin diperbarui:

```bash
git fetch origin
git rebase origin/main
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
- Jangan melakukan `git push --force` ke `main`.
- Sebelum Pull Request, jalankan `git pull --ff-only origin main` atau rebase branch.
- Jangan menghapus migration yang sudah pernah dijalankan di VPS.

## Akun guru

Login nama panggilan sebagai password telah dihapus. Buat akun menggunakan ID guru dari database lokal:

```bash
php spark aiwalas:teacher GURU_ID USERNAME
```

Perintah menampilkan password acak sekali. Guru dapat menggantinya melalui Pengaturan. Akun dibatasi pada kelas dan mapel di `jadwal_kelas`.

Nilai guru dan jurnal baru disimpan ke database. Data lama di localStorage tidak dihapus dan tidak diimpor otomatis karena identitas guru pada penyimpanan lama tidak dapat dipercaya. Tinjau dan impor kembali nilai melalui template Excel bila diperlukan.

## Tes regresi lokal

```bash
php tests/gateway-config.php
php tests/report-gateway.php
python3 tests/academic-local.py
```

Tes akademik membutuhkan server localhost:8080, database development `aiwalas` dan MySQL lokal dengan user root tanpa password sesuai template. Fixture dibuat terpisah dan dihapus setelah tes. Tes mencakup login guru, hak akses, persistensi lintas sesi, semester, jurnal dan penolakan patch ZIP. Untuk tes Chrome, jalankan Chrome headless dengan profil sementara, port debugging 9222 dan `--remote-allow-origins=http://localhost:9222`, kemudian gunakan `AIWALAS_BROWSER_TEST=1 python3 tests/academic-local.py`.

Routing halaman penilaian, jurnal, ledger, laporan, maintenance dan nilai siswa dikelola melalui `window.AIWalasPages` di `public/assets/page-router.js`. Modul halaman baru mendaftarkan handler di sana, tanpa menimpa `window.render` lagi. Halaman lama lainnya masih memakai render sebelumnya sebagai fallback.
