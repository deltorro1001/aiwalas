# Status kedisiplinan pada Beranda siswa

Kartu Notifikasi pada Beranda siswa diganti dengan Status Kedisiplinan. Ringkasan dan rincian hanya memuat catatan siswa yang login. Jika tidak ada catatan, tampilkan **Tidak Ada Catatan Pelanggaran**. Grafik, jadwal mapel, daftar siswa tidak hadir, serta kartu pribadi/ulangan/tugas tetap tersedia.

Menu Status Kedisiplinan wali kelas dan kartu siswa menggunakan `DisciplineRecords`, dengan catatan tersimpan pada tabel `catatan_kedisiplinan`. Migration `BuatCatatanKedisiplinan` harus dijalankan sebelum menggunakan fitur ini. Setiap siswa memiliki satu catatan terbaru per kategori: etika, keterlambatan, dan pakaian/atribut. Menyimpan kategori lain tidak menimpa kategori sebelumnya. Mengosongkan rincian menghilangkan catatan kategori tersebut dari kartu siswa.

Jumlah keterlambatan bersumber dari absensi; koreksi jumlah melalui menu kedisiplinan disimpan terpisah dan dipakai pada menu serta kartu yang sama. Koreksi ini tidak mengubah histori absensi. Catatan pada versi menu lama hanya berupa variabel browser dan tidak otomatis menjadi data database.

API baca/tulis wali kelas memeriksa kepemilikan kelas. API Beranda siswa mengambil identitas dari sesi, sehingga parameter kelas/siswa tidak dapat dipakai untuk membaca catatan siswa lain. Kartu siswa bersifat baca saja. Notifikasi pribadi wali kelas tidak lagi dikirim melalui API Beranda siswa.

Pengujian lokal menggunakan fixture yang dibersihkan:

```bash
php spark migrate --all
python3 tests/student-home-local.py
# Dengan Chrome pengujian terpisah pada port 9222:
AIWALAS_BROWSER_TEST=1 python3 tests/student-home-local.py
```

Pengujian mencakup penyimpanan lewat browser wali kelas, pemuatan ulang, tampilan siswa, kategori terpisah, catatan kosong, validasi, pembatasan akses, escaping, serta regresi grafik dan absensi siswa. Tidak ada pengiriman WhatsApp.
