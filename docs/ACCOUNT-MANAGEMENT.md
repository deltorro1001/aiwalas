# Kelola Akun

Menu **Kelola Akun** tersedia khusus Wali Kelas. Daftar meliputi akun sekretaris, siswa, dan orangtua pada kelas perwalian pengguna, serta akun guru yang terdaftar (mengikuti cakupan menu Akun Guru). Tersedia pencarian nama/username, filter peran, status akun dan tombol Reset Password. Akun wali kelas dan guest tidak termasuk cakupan reset ini.

Reset memerlukan konfirmasi pada dialog, membuat password acak 20 karakter dan mempertahankan username. Password hanya dikembalikan pada respons reset, ditampilkan pada dialog hasil dan bisa disalin. Menutup dialog membersihkan password dari input. Password disimpan sebagai hash satu arah; daftar akun dan halaman yang dimuat ulang tidak menampilkan password lama/baru atau hash. Tidak ada pengiriman WhatsApp pada proses ini.

Akun siswa dan orangtua yang sebelumnya belum diaktifkan turut ditampilkan dengan username NIS. Membuka daftar tidak membuat akun; tombol reset memprovisikan akun bila diperlukan. Akun nonaktif atau anak/guru yang nonaktif tidak dapat direset. Wali kelas tidak dapat mereset siswa/sekretaris/orangtua kelas lain melalui API.

Sekretaris yang juga menggunakan login Siswa memakai akun/password yang sama. Kelola Akun menampilkan dua entri: Sekretaris dengan username sekretaris, dan Siswa dengan NIS. Reset melalui entri Siswa menampilkan NIS dan pilihan peran Siswa tanpa mengganti username sekretaris. Setelah reset, login Siswa melalui akun tersebut memverifikasi password terbaru; NIS tidak menjadi jalan pintas untuk memakai password awal setelah diubah.

Pengujian lokal: `AIWALAS_BROWSER_TEST=1 python3 tests/account-management-local.py` dengan server localhost:8080 dan Chrome DevTools localhost:9222. Semua akun pengujian dan data sementara dibersihkan setelah selesai. Tidak ada reset terhadap akun asli selama pengujian.
