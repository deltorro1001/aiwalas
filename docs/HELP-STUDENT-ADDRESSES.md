# Pertanyaan alamat siswa pada Bantuan

Wali kelas dapat meminta alamat dari data siswa pada kelas yang sedang dipilih:

- `Tampilkan alamat Khaira`
- `Khaira tinggal dimana?`
- `Dimana alamat rumah Khaira?`
- `Dimana alamat Khaira?`

Kapitalisasi, tanda baca, spasi berlebih, dan penulisan `di mana` juga didukung. Nama panggilan dicocokkan dengan bagian nama lengkap siswa atau isian Nama Panggilan jika tersedia dalam data profil. Nama lengkap yang cocok persis diprioritaskan. Potongan huruf tidak dipakai untuk menebak nama siswa.

Pencarian juga mengenali panggilan dari spreadsheet **Nama Panggilan Siswa.xlsx**, termasuk `weni`, `timoti`, `malika`, dan `bram`. Pemetaan mengikuti NIS siswa pada kelas terpilih. Lihat [kamus panggilan siswa](STUDENT-NICKNAMES.md).

Jawaban mencantumkan nama lengkap, NIS, serta isian Alamat Lengkap tanpa mengubah teks alamat. Jika nama cocok dengan beberapa siswa, jawaban hanya menampilkan daftar nama/NIS dan meminta nama lebih lengkap; alamat tidak ditampilkan sampai siswa yang dimaksud jelas. Alamat yang kosong ditandai belum diisi. Pencarian memakai data kelas saat pertanyaan diajukan, tanpa menyimpan hasil dari kelas sebelumnya.

Handler dijalankan sebelum pencarian profil umum. Variasi pertanyaan lain diteruskan ke alur Bantuan yang sudah ada. Tidak ada pengiriman pesan atau perubahan data. Akses dibatasi ke wali kelas, termasuk saat data profil lama masih berada di memori setelah pergantian peran. Profil lengkap tetap mengikuti pembatasan Bootstrap di server.

Pengujian lokal dengan Chrome DevTools port 9222 dan data sintetis: `python3 tests/address-questions-browser.py`.
