# Upload foto siswa dan kolase

## Penggunaan

- Login siswa → Absensi Harian → kartu **Upload Foto**, setelah Rekap Absen. Pilih JPG/PNG/WebP, isi keterangan wajib, lalu Upload Foto. Browser memperkecil berkas maksimal 20 MB menjadi JPEG di bawah 1,8 MB sebelum dikirim.
- Foto unggahan tidak diberi timestamp tambahan. Pada sudut kanan bawah kolase terdapat dua baris: **created by aiwalas**, kemudian **tanggal dan waktu pembuatan kolase** dalam format `dd-mm-yyyy HH:mm:ss`. Waktu pembuatan berasal dari server dengan zona Asia/Jakarta.
- Kartu ini hanya untuk siswa, tidak ditampilkan pada login orangtua. Siswa hanya membaca foto miliknya; wali kelas hanya membaca kelas yang dikelolanya.
- Login wali kelas → **Upload Foto**, tepat di atas Pengaturan. Pilih tanggal untuk melihat galeri. Tombol Muat ulang memuat foto terbaru. Galeri dibagi 24 foto per halaman.
- Maksimal **35 foto per kelas pada tanggal yang sama**. Unggahan ke-36 ditolak; tanggal dan kelas lain mempunyai kuota sendiri. Batas dilindungi transaksi dan kunci kelas agar unggahan bersamaan tidak melewatinya.
- **Buat Kolase** memasukkan seluruh foto kelas pada tanggal tersebut, termasuk foto di halaman galeri berikutnya, dalam **satu halaman JPEG 2480 × 3508 piksel**. Jumlah baris/kolom dan ukuran foto menyesuaikan jumlah serta proporsi foto. Foto tetap utuh dengan skala proporsional.
- Semua teks kolase menggunakan **Segoe UI**. Judul kapital tebal berada di tengah blok teal gelap selebar halaman, tanpa tanggal atau jumlah foto di bagian atas. Nama, NIS, keterangan, dan teks pembuat menggunakan Segoe UI Regular.
- Periksa pratinjau kolase satu halaman. Pilih guru dari Data Guru, lalu **Kirim Kolase** dan konfirmasikan. Nomor dibaca ulang dari data guru aktif, tidak berasal dari nomor bebas di formulir. Kolase dikirim sebagai satu gambar melalui gateway yang sudah dikonfigurasi.
- Jika ada foto baru setelah pembuatan kolase, buat kolase kembali. Foto baru tidak ditambahkan secara diam-diam ke dokumen yang sudah dibuat atau dikirim.

## Penyimpanan dan deployment

Jalankan `php spark migrate --all`. Pemrosesan memerlukan PHP GD dengan dukungan FreeType pada **PHP web dan CLI**. Periksa dengan `php -r 'exit(function_exists("imagettftext") ? 0 : 1);'`; pasang ekstensi GD yang sesuai dengan versi PHP server bila belum tersedia. Berkas Segoe UI Regular/Bold disertakan di `resources/fonts/segoe-ui/`, di luar document root, sehingga font tidak bergantung pada instalasi sistem operasi.

Foto disimpan di `writable/uploads/student-photos/`, di luar document root. Direktori harus dapat ditulis oleh proses PHP. Sertakan direktori ini dan tabel `foto_siswa`, `foto_kolase`, `foto_kolase_halaman`, dan `foto_kolase_pengiriman` dalam backup. Pertahankan berkas saat deployment; foto/kolase tidak disimpan dalam Git.

Server memvalidasi gambar asli (JPG/PNG/WebP), maksimal 2 MB dan 12 megapiksel, memproses orientasi JPEG bila tersedia, lalu menyimpan ulang sebagai JPEG. EXIF dan nama berkas unggahan tidak dipublikasikan. Tidak ada timestamp unggah yang dicetak pada foto; waktu unggah tetap dicatat untuk pengelompokan galeri harian. Waktu yang dicetak pada kolase adalah waktu pembuatan dokumen dan sama dengan `foto_kolase.dibuat_pada`. Semua foto hari yang dipilih ikut dalam kolase saat tombol Buat Kolase ditekan; unggahan tidak otomatis mengubah absensi.

## Pengiriman dan akses media

Gateway harus dapat mengakses alamat aplikasi publik; pengiriman dari localhost ditolak sebelum transport dijalankan. Pratinjau kolase tetap dapat dibuat dan dibaca secara lokal.

Sebelum menghubungi gateway, server mencatat histori **Kolase Foto Siswa** di Komunikasi. **Terkirim** berarti gateway mengonfirmasi penerimaan. Ditolak, belum ada konfirmasi, atau timeout tetap terlihat **Pending**, disertai catatan hasil. Tombol konfirmasi tidak otomatis mengulangi pengiriman ketika hasil tidak pasti. Klaim unik per kolase/guru mencegah duplikasi akibat klik berulang atau permintaan bersamaan. Kolase yang sudah terkirim tidak dikirim ulang ke guru yang sama; guru tujuan lain dapat dipilih.

Gateway mengambil hanya gambar kolase melalui URL token acak 256-bit yang berlaku 24 jam. Database menyimpan hash token; foto asli tetap memerlukan login. URL token tidak mengizinkan penelusuran daftar foto, dan tautan kedaluwarsa menghasilkan 404. Penerima WhatsApp memperoleh gambar dari gateway.

Jika proses aplikasi terhenti saat mengirim, periksa gateway dan Komunikasi sebelum tindakan lanjutan. Jangan mengubah status secara manual menjadi terkirim tanpa konfirmasi. Format kolase berubah menjadi satu halaman; snapshot menyertakan versi tata letak agar kolase lama tidak dipakai ulang sebagai hasil format baru.

## Pengujian

```sh
php tests/photo-collage-layout.php
php tests/photo-collage-delivery.php
python3 tests/photo-uploads-local.py
AIWALAS_BROWSER_TEST=1 python3 tests/photo-uploads-local.py
```

Pengujian browser memakai Chrome DevTools localhost port 9222 dan `tests/browser_client.py`. Semua fixture akun, kelas, foto, kolase dan histori dibersihkan. Pengiriman memakai callable tiruan, tanpa menghubungi gateway nyata. Pengujian mencakup kepemilikan, larangan orangtua, validasi gambar, pemalsuan identitas/tanggal, deduplikasi upload, seluruh foto lintas halaman galeri, batas 35 foto, satu halaman untuk jumlah foto berbeda, posisi teks pembuat/waktu, snapshot kolase, token/expiry, timeout, klik berulang, permintaan bersamaan, posisi kartu/menu, dan konfirmasi penerima.
