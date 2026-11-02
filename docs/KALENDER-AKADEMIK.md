# Kalender Akademik

Menu **Kalender Akademik** di sidebar hanya tersedia bagi Wali Kelas. Perubahan berlaku untuk seluruh kelas. Sekretaris, guru, siswa, orangtua, dan tamu menerima status kalender tanpa hak mengubahnya.

## Penggunaan

1. Pilih bulan/tahun, lalu klik tanggal.
2. Isi tanggal mulai dan selesai untuk satu tanggal atau rentang maksimal 366 hari.
3. Pilih PJJ, Hari Libur, Hari Pengambilan Raport, atau Agenda Lainnya. Isi judul dan keterangan sesuai kebijakan sekolah.
4. Tentukan dampak absensi, lalu simpan. PJJ dan Hari Libur selalu memakai dampak yang sesuai jenisnya. Judul otomatis untuk Raport adalah **Pengambilan Raport**. Dampak bawaannya **Hari Sekolah (tanpa absensi)**; pilihan dampak dikunci untuk menjaga konsistensi status Pengambilan Raport. Agenda lainnya menyediakan pilihan yang sama. Dropdown Judul agenda menyediakan **Tanpa Judul Agenda** dan **Judul lainnya…** untuk judul khusus.

**Hari Sekolah** menjalankan absensi biasa, termasuk jika sekolah ingin memakai tanggal akhir pekan/libur sebagai hari pengganti. **Pengambilan Raport** selalu menampilkan status kehadiran **Pengambilan Raport** berwarna biru di seluruh login, tanpa perlu scan dan tanpa menambah ketidakhadiran. Jenis ini memakai dampak **Hari Sekolah (tanpa absensi)**, termasuk untuk agenda lama yang sebelumnya memiliki pilihan dampak lain. Judul boleh dikosongkan atau diubah tanpa mengganti nama status kehadirannya. **Hari Sekolah (tanpa absensi)** pada jenis agenda lainnya menampilkan status **Tanpa absensi**, tetap bukan hari libur, tidak memerlukan scan/koreksi kehadiran, melewati pengisian otomatis pengajuan sakit/izin, dan tidak menambah jumlah ketidakhadiran/keterlambatan. **Libur** dan **PJJ** menampilkan status yang sama bagi semua siswa dan mengosongkan waktu scan pada tampilan; scan tidak diperlukan. Kedua status ini tidak menambah jumlah Sakit, Izin, Alpha, atau Keterlambatan.

Perubahan agenda sekolah pada tanggal yang sama mengganti penetapan sekolah sebelumnya. Tombol **Hapus agenda tanggal ini** hanya menghapus penetapan sekolah; tanggal kembali mengikuti kalender pemerintah/akhir pekan. Data absensi asli tidak ditimpa atau dihapus, sehingga catatan asli kembali terlihat bila tanggal menjadi hari sekolah. Koreksi absensi individu tetap dilakukan melalui Absensi Harian.

Beranda, absensi harian, rekap bulanan siswa/orangtua, rekap kelas/individu dan export-nya, laporan, rekap pertanyaan absensi, serta perhitungan keterlambatan dari absensi membaca aturan yang sama. Pengingat jadwal guru tidak dijadwalkan pada hari Libur; PJJ tetap mengizinkan pengingat pembelajaran. Pengajuan sakit/izin baru melewati tanggal Libur/PJJ. Agenda tersinkron pada sesi lain saat dimuat, saat tab aktif kembali, atau dalam sekitar 30 detik pada tab aktif.

## Dasar pemerintah

Data yang diverifikasi pada 2 November 2026:

- [SKB 2026 melalui Sekretariat Negara](https://www.setneg.go.id/baca/index/inilah_skb_3_menteri_libur_nasional_dan_cuti_bersama_2026): 17 hari libur nasional dan 8 hari cuti bersama; SKB 1497/2025, 2/2025, dan 5/2025.
- [SKB 2027 melalui Sekretariat Negara](https://setneg.go.id/baca/index/inilah_skb_3_menteri_libur_nasional_dan_cuti_bersama_2027): 18 hari libur nasional dan 8 hari cuti bersama; SKB 1205/2026, 3/2026, dan 2/2026.

Libur nasional dan cuti bersama dibedakan pada kalender; keduanya secara default berdampak Libur. Agenda sekolah dapat menyesuaikannya. Tahun berikutnya tidak diisi dengan perkiraan tanggal libur. UI menampilkan pemberitahuan jika data pemerintah tahun tersebut belum tersedia. Tambahkan daftar baru di `NationalHolidays` setelah verifikasi SKB resmi; agenda sekolah untuk tahun tersebut tetap dapat dibuat sekarang.

Penetapan Libur/PJJ sekolah yang telah diminta sebelumnya dipindahkan oleh migration menjadi agenda sekolah yang bisa diedit. Tidak ada perubahan atau pengiriman WhatsApp saat migration dijalankan.

## Pengujian lokal

Jalankan migration dan server development. `tests/school-calendar.php` memeriksa data resmi serta batas tanggal WIB. `tests/academic-calendar-local.py --browser` memakai akun/data sementara untuk menguji API/CSRF, pembatasan peran, rentang tanggal, sinkronisasi dua kelas, rekap, laporan, pemulihan, dan tampilan mobile; Chrome development perlu aktif pada port 9222. Fixture dibersihkan sesudah pengujian. Pengujian scheduler memakai gateway palsu; tidak mengirim WhatsApp nyata.
