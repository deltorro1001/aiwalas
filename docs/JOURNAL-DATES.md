# Tanggal dan akses Jurnal Kelas

Login wali kelas, guru, sekretaris, siswa, dan orangtua memiliki pilihan **Tanggal jurnal**, mulai **1 Januari 2026**, dengan tanggal hari ini (WIB) sebagai default. Tombol **Hari ini** kembali ke tanggal berjalan; **Muat ulang** mempertahankan pilihan tanggal. Hanya jurnal pada tanggal terpilih yang ditampilkan. Tanggal tanpa data menampilkan pesan kosong.

Wali kelas dan sekretaris kelas dapat menambah/mengedit jurnal pada kelasnya. Guru hanya membaca jurnal penugasannya dan mengedit yang dibuatnya sendiri. Siswa dan orangtua membaca jurnal kelas anak. Akun tamu hanya memiliki portal informasi publik dan tidak dapat mengakses jurnal. Akun pembaca tidak memperoleh tombol Tambah/Edit dan API menolak seluruh operasi tulisnya. Hapus tetap khusus wali kelas pada kelasnya.

Tambah Jurnal menggunakan tanggal yang sedang dilihat. Edit memuat tanggal asli dan mempertahankan penulis asli. Setelah disimpan, daftar mengikuti tanggal hasil penyimpanan; kembali ke menu atau muat ulang tetap mempertahankan tanggal pada konteks pengguna/kelas yang sama. Wali/sekretaris juga dapat mengoreksi isian jurnal lama dengan guru/mapel asli meskipun penugasan tersebut sudah dihapus dari jadwal sekarang.

`GET api/jurnal?kelas_id=...&tanggal=YYYY-MM-DD` memfilter tanggal secara tepat dan menolak tanggal tidak valid/sebelum 2026. Jika `tanggal` dihilangkan, perilaku API lama (seluruh tanggal yang dapat diakses) tetap tersedia. `can_create` dan `can_edit` berasal dari server. Izin kelas, penugasan, serta pemilik jurnal diperiksa ulang ketika menyimpan.

Pengujian lokal: `AIWALAS_BROWSER_TEST=1 python3 tests/journal-dates-local.py` dan `python3 tests/teacher-assignments-local.py`. Seluruh data uji dibersihkan dan tidak ada pesan gateway yang dikirim.
