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

Migration `BuatAkunPanggilanGuru` membuat atau menyesuaikan seluruh akun guru satu kali sesuai permintaan pemilik aplikasi: nama panggilan tanpa sapaan/spasi dan huruf kecil menjadi username serta password awal, misalnya `Pak Ruby` → `ruby` / `ruby`. Password tetap disimpan sebagai hash. Migration memeriksa benturan username dan membatalkan seluruh transaksi jika ada konflik. Perubahan password berikutnya tidak ditimpa pada deployment ulang. Wali Kelas dapat membuka **Akun Guru**, memilih **Buat Akun**, lalu menyampaikan username dan password awal yang ditampilkan kepada guru. Untuk akun yang sudah ada, pilih **Reset Password**; password lama tidak berlaku setelah reset.

Alternatif melalui CLI menggunakan ID guru dari database lokal:

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

## Notifikasi berdasarkan tanggal

Beranda mengambil maksimal 10 notifikasi terbaru untuk hari ini menurut zona waktu aplikasi (`Asia/Jakarta`). Menu Notifikasi memuat riwayat lewat API tersendiri, sehingga tidak bergantung pada data ringkas Beranda. Pilihan tanggal berada tepat di bawah tombol Tandai Semua Dibaca. Pilih Semua tanggal untuk membuka seluruh riwayat; hasil lebih dari 30 notifikasi dapat dibuka melalui pagination. Tandai Semua Dibaca tetap menandai seluruh notifikasi akun, termasuk tanggal lain, dan mempertahankan pilihan tanggal di layar.

API: `GET api/notifikasi?tanggal=YYYY-MM-DD&page=1`. Tanpa `tanggal` (atau nilai kosong), semua tanggal tersedia. Tanggal tidak valid menghasilkan HTTP 422. Semua hasil tetap dibatasi ke pengguna yang login.

```bash
python3 tests/notifications-local.py
# Jika Chrome pengujian sudah berjalan pada port 9222:
AIWALAS_BROWSER_TEST=1 python3 tests/notifications-local.py
```

Tes berjalan pada localhost:8080 dan hanya menggunakan akun/notifikasi fixture yang dibersihkan setelah selesai.

## Toleransi scan absensi

Scan QR siswa dan scanner wali kelas/sekretaris memakai `AttendanceScanPolicy` dengan satu waktu server dalam zona Asia/Jakarta. Status Hadir berlaku sampai **06.35.59 WIB**, dan Terlambat mulai **06.36.00 WIB** (toleransi 5 menit dari batas 06.30). Tanggal, waktu, dan status scan tidak diambil dari jam perangkat. Scanner wali kelas/sekretaris mengirim `metode: scan_qr`; koreksi manual tanpa penanda ini tetap memakai status yang dipilih wali kelas. Aturan ini berlaku untuk scan baru; histori tidak diubah otomatis.

Uji batas waktu tanpa database: `php tests/attendance-scan-policy.php`. Tes browser `AIWALAS_BROWSER_TEST=1 python3 tests/student-home-local.py` memverifikasi scan siswa/scanner wali kelas, pengabaian waktu kiriman perangkat dan koreksi manual dengan fixture lokal.

## Rekap Absen bulanan siswa

Absensi Harian siswa menampilkan tabel Rekap Absen dengan kolom Hari, Tanggal, Waktu Scan, dan Status. Filter Bulan hanya berisi nama Januari–Desember. Filter Tahun berada di bawahnya, dimulai dari 2026 dan memuat tahun berjalan beserta sepuluh tahun berikutnya. Tanpa perubahan pilihan, bulan dan tahun berjalan mengikuti waktu server Asia/Jakarta. Tabel menampilkan tanggal 1 sampai akhir bulan secara berurutan, sesuai jumlah hari termasuk tahun kabisat. Tanggal tanpa catatan tersimpan ditampilkan dengan waktu “—” dan status “Belum ada catatan”; baris pelengkap ini tidak membuat atau mengubah data absensi. Tombol Muat ulang memperbarui status hari ini dan rekap bulan yang dipilih; setelah scan berhasil, halaman memuat ulang rekap dengan pilihan bulan tetap terjaga.

`GET api/siswa/rekap-absensi?bulan=01&tahun=2026` hanya tersedia untuk siswa. Profil ditentukan oleh NIS sesi, bukan parameter siswa/kelas. Rentang memakai awal bulan hingga sebelum awal bulan berikutnya; Januari–Juni membaca semester Genap dan Juli–Desember semester Ganjil. Bulan dan tahun divalidasi terpisah; tahun mulai 2026. Riwayat hanya bisa dibaca; waktu scan tidak dibuat untuk hari tanpa catatan.

## Tanggal dan filter Absensi Harian

Wali Kelas dan Sekretaris dapat memilih tanggal pada tabel Absensi Harian. Dropdown **Pilih berdasarkan** berisi **Nama** (seluruh siswa secara alfabetis), **Terlambat**, **Sakit**, **Izin**, dan **Alpha** (siswa dengan status tersebut). Data diambil dari API menggunakan kelas, tanggal, dan semester terpilih; polling lima detik mempertahankan pilihan tersebut. Siswa tanpa catatan pada tanggal itu ditampilkan sebagai Menunggu keterangan, tanpa waktu scan buatan.

Riwayat hanya untuk dilihat; koreksi serta pembuatan/scanner QR tersedia untuk tanggal hari ini. Koreksi tetap mengikuti aturan akses sebelumnya dan tidak mengubah semester lain. Tombol WhatsApp pada baris yang difilter mengacu ke ID siswa dan status yang sedang ditampilkan.

```bash
python3 tests/attendance-filters-local.py
# Dengan Chrome pengujian di port 9222:
AIWALAS_BROWSER_TEST=1 python3 tests/attendance-filters-local.py
```

Tes menggunakan fixture pada localhost:8080 dan membersihkannya setelah selesai. Pemanggilan gateway WhatsApp pada tes browser diganti stub, sehingga tidak mengirim pesan sungguhan.

## Libur akhir pekan

Sabtu dan Minggu dihitung sebagai **Libur** menurut kalender Asia/Jakarta. Semua peran (wali kelas, sekretaris, siswa, orangtua, guru, guest) mendapat keterangan Libur pada halaman aplikasi; saat wali/sekretaris melihat riwayat Absensi Harian, keterangan mengikuti tanggal yang dipilih.

Status untuk hari tanpa catatan pada akhir pekan ditampilkan sebagai Libur di Absensi Harian, ringkasan kelas, dan kalender rekap siswa/orangtua. Jadwal harian siswa yang kosong menampilkan keterangan libur. Riwayat absensi yang sudah tersimpan tetap tampil sesuai status aslinya. Libur merupakan status tampilan kalender, bukan baris absensi baru; tidak menambah Alpha/keterlambatan atau mengubah rekap Excel. Koreksi manual untuk aktivitas khusus tetap tersedia.

`SchoolCalendar` menjadi sumber kalender server dan `school-calendar.js` mengatur tampilan bersama, termasuk pembaruan pada pergantian tanggal/ketika tab dibuka kembali. Tanggal Libur tanpa catatan tidak menawarkan tombol pemberitahuan ketidakhadiran WhatsApp.

Verifikasi: `php tests/school-calendar.php` dan `python3 tests/school-calendar-browser.py` (Chrome DevTools localhost:9222, server localhost:8080). Tes browser memakai simulasi waktu/API tanpa perubahan akun atau data asli.

## Penugasan mapel tanpa jadwal waktu

`TeacherAssignments` menggabungkan penugasan dari `jadwal_kelas` dan `pengampu_mapel`, dengan deduplikasi kelas/mapel/guru. Data ini dipakai untuk kelas dan dropdown mapel guru, penilaian, jurnal, daftar siswa guru, serta kontak pengajar wali kelas. Slot jadwal dan otomatisasi WhatsApp tetap mengikuti `jadwal_kelas`.

Migrasi `TambahMapelAnimasi3d` menambahkan **Animasi 3D**, menautkannya kepada **Sumantoro Kasdhani, S.Kom., M.I.Kom.** pada kelas aktif yang sudah diampunya, serta melengkapi nama guru dan akun gurunya. Penugasan mapel tidak membuat hari/jam pelajaran baru. Pengujian lokal: `AIWALAS_BROWSER_TEST=1 python3 tests/teacher-assignments-local.py`.
