# Pengajuan istirahat sakit

Kartu **Pengajuan Istirahat Sakit** muncul pada Absensi Harian akun Orangtua/Wali Murid. Akun siswa tidak menampilkan kartu ini.

- Isi lama istirahat 1–60 hari kalender dan tanggal mulai. Tanggal selesai dihitung otomatis; mengubah tanggal selesai akan menghitung ulang jumlah hari. Kedua tanggal termasuk dalam hitungan.
- Lampiran opsional: PDF, JPG, PNG, maksimal 2 MB. Tersedia keterangan tambahan maksimal 1.000 karakter.
- Tombol **Kirim Pengajuan** menyimpan pengajuan dengan status **Terkirim** dan menampilkannya dalam riwayat. Rentang tanggal yang beririsan dengan pengajuan anak yang sama ditolak agar tidak ganda.
- Wali kelas melihat pengajuan pada Absensi Harian kelasnya, termasuk tanggal dan unduhan surat. Jika ada berkas surat, tombol **Preview** membuka gambar/PDF dalam jendela pratinjau sebelum diunduh. Tersedia **Unduh Surat** dan **Buka di tab baru** di jendela tersebut. Tanpa lampiran, tombol Preview tidak ditampilkan. Daftar pengajuan ditampilkan terlepas dari tanggal filter absensi agar pengajuan mendatang tetap terlihat. Gunakan **Muat ulang** untuk memuat pengajuan baru.
- Pengajuan tidak mengubah absensi secara otomatis. Wali kelas memeriksa pengajuan kemudian menggunakan **Koreksi** pada tanggal yang sesuai. Tidak ada pengiriman WhatsApp pada alur ini.

Jalankan `php spark migrate --all` untuk membuat tabel `pengajuan_sakit`. Lampiran disimpan pada `writable/pengajuan-sakit` dengan nama acak; sertakan direktori tersebut dalam backup bersama database. Akses unduhan memerlukan sesi orangtua anak tersebut atau wali kelas pemilik kelas; siswa dan pengguna lain tidak diberi akses. Identitas anak dan kelas diambil dari sesi, bukan input formulir.

Verifikasi lokal (fixture sementara dibersihkan setelah pengujian):

```sh
AIWALAS_BROWSER_TEST=1 python3 tests/parent-login-local.py
```

Pengujian browser memerlukan Chrome DevTools pada localhost:9222, sesuai `tests/browser_client.py`.

Pengujian preview sakit dan izin: `python3 tests/request-attachment-preview-local.py`. Mode preview memakai endpoint lampiran yang sama dengan parameter `?preview=1`, respons inline PDF/JPG/PNG, pemeriksaan kepemilikan yang sama, dan header tanpa cache. Unduhan biasa tetap menggunakan respons attachment. Pratinjau ditutup dan konten berkas dilepas saat jendela ditutup atau pengguna berpindah halaman/kelas/akun.
