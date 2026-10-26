# Login Orangtua/Wali Murid

Pilih **Orangtua/Wali Murid** pada formulir login. Username dan password awal adalah NIS anak, tanpa awalan tahun. Contoh NIS `13069`: username `13069`, password awal `13069`.

Konten dan menu sama dengan workspace siswa, termasuk Beranda, Absensi Harian (status dan rekap), Jadwal Mapel, Status Kedisiplinan, Nilai dan Capaian, serta Pengaturan. Nama pada sapaan Beranda, Absensi Harian, dan Pengaturan diawali **Orangtua/Wali Murid**. Data yang tampil tetap milik anak yang terhubung dengan akun.

## Akun dan penyimpanan

Migration `BuatAkunOrangtua` menambahkan peran `orang_tua` dan tautan unik `pengguna.siswa_id`, lalu membuat satu akun terpisah untuk setiap siswa aktif. Username internal menggunakan kunci berdasarkan ID anak untuk menjaga keunikan tabel pengguna; login dan respons frontend tetap menggunakan NIS anak. Kolom `pengguna.nis` akun orangtua kosong sehingga akun siswa dan sekretaris yang sudah ada tetap berfungsi.

Password disimpan sebagai hash. Pengubahan password orangtua tidak memengaruhi akun siswa. Provisioning ulang tidak mereset password atau mengaktifkan kembali akun yang telah dinonaktifkan. Siswa baru yang dibuat melalui API mendapatkan akun orangtua; profil hasil impor lama yang belum memiliki akun dapat dibuatkan akun saat login orangtua pertama dengan kredensial NIS.

Nama dan tautan akun mengikuti profil anak. Jika NIS berubah, username login orangtua mengikuti NIS baru, sementara password yang sudah ada tetap dipertahankan. Penonaktifan siswa menonaktifkan akun orangtua; sesi juga diperiksa terhadap akun dan profil anak aktif pada setiap permintaan.

## Batas akses

Peran server tetap `orang_tua`; frontend memakai workspace `student` dengan penanda `isParent` dan `accountRole: parent` agar menu dan perilaku siswa dipakai bersama. Filter peran memberikan izin setara siswa, sementara pemeriksaan kepemilikan API tetap memakai NIS anak dari sesi. Akun tidak mendapatkan izin guru/wali kelas. Mengganti parameter siswa atau kelas tidak memberikan akses ke data anak lain. Password dan identitas akun kedua peran tetap terpisah.

Kartu dan tombol scanner QR tidak ditampilkan pada akun orangtua. Absensi orangtua bersifat baca saja: status kehadiran, waktu scan anak, serta rekap bulanan tetap tersedia, dan server menolak pencatatan absensi dari akun orangtua. Scanner siswa tetap tersedia.

## Pengujian lokal

```bash
php spark migrate --all
python3 tests/parent-login-local.py
# Opsional dengan browser Chrome pengujian pada port 9222:
AIWALAS_BROWSER_TEST=1 python3 tests/parent-login-local.py
AIWALAS_BROWSER_TEST=1 python3 tests/student-home-local.py
```

Fixture dibersihkan setelah selesai. Pengujian mencakup pemisahan password/identitas, provisioning tanpa reset, akun siswa baru, perubahan nama, penonaktifan akun/anak, kesamaan data/menu, sapaan, Pengaturan, pembatasan akses, absensi orangtua baca saja dan scanner siswa tetap tersedia. Tidak ada WhatsApp nyata yang dikirim.
