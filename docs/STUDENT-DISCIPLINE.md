# Status kedisiplinan pada Beranda siswa

Kartu Notifikasi pada Beranda siswa diganti dengan Status Kedisiplinan. Ringkasan dan rincian hanya memuat catatan siswa yang login. Jika tidak ada catatan, tampilkan **Tidak Ada Catatan Pelanggaran**. Kartu jadwal mapel hari ini menampilkan waktu dan nama guru dengan ukuran yang sama seperti kartu Ringkasan Kelas sebelumnya. Di kolom kanan, Status Kedisiplinan berada di atas kartu Notifikasi. Kartu pribadi/ulangan/tugas tetap tersedia.

Menu Status Kedisiplinan wali kelas dan kartu siswa menggunakan `DisciplineRecords`, dengan catatan tersimpan pada tabel `catatan_kedisiplinan`. Migration `BuatCatatanKedisiplinan` harus dijalankan sebelum menggunakan fitur ini. Setiap siswa memiliki satu catatan terbaru per kategori: etika, keterlambatan, dan pakaian/atribut. Menyimpan kategori lain tidak menimpa kategori sebelumnya. Mengosongkan rincian menghilangkan catatan kategori tersebut dari kartu siswa.

Jumlah keterlambatan bersumber dari absensi; koreksi jumlah melalui menu kedisiplinan disimpan terpisah dan dipakai pada menu serta kartu yang sama. Koreksi ini tidak mengubah histori absensi. Catatan pada versi menu lama hanya berupa variabel browser dan tidak otomatis menjadi data database.

API baca/tulis wali kelas memeriksa kepemilikan kelas. API Beranda siswa mengambil identitas dari sesi, sehingga parameter kelas/siswa tidak dapat dipakai untuk membaca catatan siswa lain. Kartu siswa bersifat baca saja. Notifikasi pribadi wali kelas tidak lagi dikirim melalui API Beranda siswa.

Kartu Notifikasi menggantikan daftar siswa tidak hadir dan menampilkan maksimal empat pesan WhatsApp berstatus Terkirim, diurutkan menurut waktu kirim terbaru lalu ID terbaru. Pesan lebih lama tidak ditampilkan. Pesan dibatasi pada kelas dan siswa yang login. Untuk histori gateway lama tanpa `siswa_id`, pencocokan nomor siswa/orang tua hanya dipakai jika nomor tersebut unik dalam kelas; pesan guru otomatis dan nomor keluarga yang ambigu tidak ditampilkan melalui pencocokan ini. Nomor penerima tidak dikirim ke browser. Jika kosong, tampilkan “Belum ada pesan WhatsApp terkirim.”

Menu **Status Kedisiplinan** pada sidebar siswa menampilkan tiga tabel baca saja dari sumber wali kelas yang sama. Pelanggaran Etika memuat Nomor, Pelanggaran Yang Dilakukan, Tanggal Dilakukan, Disaksikan Oleh, dan Tindakan. Keterlambatan serta Pakaian & Atribut mengikuti data dan status pada tabel wali kelas, tanpa kolom aksi edit. Keterlambatan tetap menampilkan jumlah 0 dan status Baik jika belum ada keterlambatan. Kategori pelanggaran yang kosong menampilkan “Tidak Ada Catatan Pelanggaran”. Tombol Muat ulang mengambil perubahan wali kelas terbaru.

Endpoint `GET api/siswa/kedisiplinan` hanya menerima sesi siswa dan mengambil profil dari NIS sesi; parameter siswa/kelas tidak menentukan akses. Respons berisi hanya identitas siswa sendiri, kolom tabel yang diperlukan, dan ringkasan keterlambatan, tanpa metadata pencatat.

Pengujian lokal menggunakan fixture yang dibersihkan:

```bash
php spark migrate --all
python3 tests/student-home-local.py
# Dengan Chrome pengujian terpisah pada port 9222:
AIWALAS_BROWSER_TEST=1 python3 tests/student-home-local.py
```

Pengujian mencakup penyimpanan lewat browser wali kelas, pemuatan ulang, tampilan siswa, kategori terpisah, catatan kosong, validasi, pembatasan akses, escaping, serta regresi grafik dan absensi siswa. Tidak ada pengiriman WhatsApp.
