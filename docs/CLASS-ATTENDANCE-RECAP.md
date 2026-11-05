# Rekap absen kelas dan individu

Menu **Rekap Absen** tersedia pada login Wali Kelas dan Sekretaris Kelas, serta melalui tombol **Rekap Absen** dan **Rekap Individu** di Absensi Harian. Rekap mengambil tabel `absensi` yang sama dengan Absensi Harian; tidak memakai contoh data atau catatan Status Kedisiplinan.

- Pilih kelas dan semester melalui panel samping, lalu tahun pada halaman rekap (default tahun berjalan WIB).
- **Rekap Kelas** menampilkan tabel **Rekap Absen Kelas 11PF1** (nama mengikuti kelas aktif) di bagian atas, dengan jumlah per siswa pada bulan yang dipilih (default bulan berjalan). Di bawahnya, **Rekap Absen Kelas Tahunan** menampilkan 12 bulan dengan jumlah Sakit, Izin, Alpha dan Keterlambatan seluruh siswa aktif, serta totalnya.
- **Lihat Individu** atau tab **Rekap Individu** menampilkan 12 bulan untuk siswa yang dipilih, beserta total pada tahun/semester tersebut.
- Keterlambatan dihitung dari status `Terlambat` yang sudah tercatat, tanpa mengklasifikasi ulang catatan lama dari waktu scan. Hari tanpa catatan tidak dianggap Alpha. Status Hadir, PJJ dan Menunggu keterangan tidak dimasukkan ke empat jumlah tersebut. Total Tidak Hadir hanya Sakit + Izin + Alpha.
- **Muat ulang** mengambil koreksi Absensi Harian terbaru. **Export Excel** mengunduh file `.xlsx`. Rekap kelas berisi sheet Rekap Kelas, Rincian Bulan yang dipilih, serta Data Bulanan Siswa untuk seluruh siswa dan 12 bulan. Rekap individu berisi satu sheet untuk siswa tersebut. Setiap sheet memiliki judul, identitas kelas/tahun/semester, lebar kolom, filter, dan rumus SUM pada baris total. Nilai jumlah berupa angka untuk analisis, NIS berupa teks agar nol di depan tetap tersimpan, dan nama siswa tidak dijadikan formula.

API `GET api/absensi/rekap-kelas?kelas_id=ID&tahun=2026&semester=Ganjil` dapat diakses wali kelas pemilik kelas atau sekretaris aktif yang terdaftar sebagai siswa pada kelas tersebut. Sekretaris hanya melihat kelasnya sendiri; tampilan rekap dan export sama dengan wali kelas. Tahun menerima 2026–9998, semester Ganjil/Genap; query dibatasi tanggal 1 Januari sampai sebelum 1 Januari tahun berikutnya. Setiap siswa aktif tetap memiliki 12 bulan, dengan nol bila tidak ada catatan. Data siswa nonaktif dan kelas lain tidak dihitung. Tidak ada migrasi database.

Pengujian lokal: `AIWALAS_BROWSER_TEST=1 python3 tests/class-attendance-recap-local.py`, dengan localhost:8080 dan Chrome DevTools localhost:9222. Fixture sementara dibersihkan sesudah pengujian.

## Kirim PDF melalui Bantuan

Perintah tersedia pada wali kelas; kata **Kelas** opsional, dan **Kirim**/**Kirimkan** sama-sama diterima:

- `Kirim Rekap Absen Oktober 2026 ke Ibu Nani`
- `Kirimkan Rekap Absen Kelas November 2026 ke guru Nani`
- `Kirim Rekap Absen Oktober 2026 ke siswa weni`
- `Kirim Rekap Absen Kelas Oktober 2026 ke orangtua timoti`
- `Kirim Rekap Absen Oktober 2026 ke 081234567890`

Penerima memakai data guru, siswa, orangtua, serta nama panggilan yang sudah tersimpan. Nomor langsung tidak perlu ada pada kontak. Nama ambigu menampilkan pilihan penerima; nomor yang tidak valid atau belum tersedia tidak dikirim. Kontak yang sudah jelas dikirim langsung seperti perintah perkenalan dan kolase, tanpa pratinjau atau konfirmasi tambahan.

Bulan/tahun eksplisit selalu diutamakan. Jika periode tidak disebutkan, mengikuti pilihan pada Rekap Absen untuk kelas/akun/semester aktif; bila belum dipilih, memakai bulan berjalan WIB. Semester pada PDF ditentukan dari bulan rekap (Januari–Juni Genap, Juli–Desember Ganjil) sehingga periode lampau tidak terpengaruh semester UI yang sedang aktif.

Server membangun PDF **Rekap Absen Kelas [nama kelas]** dari sumber agregasi yang sama dengan tabel, berisi semua siswa aktif serta jumlah Sakit, Izin, Alpha, Keterlambatan, dan Total Tidak Hadir (S/I/A). PDF menggunakan halaman A4, font Segoe UI dan tabel yang dirender sebagai gambar beresolusi tinggi; daftar panjang berlanjut ke halaman berikutnya. Nama kelas, bulan/tahun, semester, total, serta waktu pembuatan WIB dicantumkan. File Excel tetap tersedia melalui tombol Export Excel.

`POST api/absensi/rekap-kelas/kirim-pdf` hanya untuk wali kelas pemilik kelas. Data jumlah tidak diterima dari klien. Setiap proses dicatat Pending di Komunikasi sebelum gateway dipanggil, lalu Terkirim hanya ketika gateway mengonfirmasi. Ketidakpastian/kegagalan tidak dicoba ulang otomatis. Kunci pengiriman mencakup pemilik, kelas, bulan/tahun, nomor dan snapshot data; permintaan identik tidak mengirim ulang, sedangkan koreksi absensi menghasilkan PDF baru. Tujuan berbeda atau periode berbeda tetap dapat dikirim.

Gateway membaca pengaturan WhatsApp Gateway yang sama dengan fitur lainnya. File disimpan pada `writable/rekap-absen`, dengan nama acak. URL `media/rekap-absen/[token]` memakai token acak 256 bit (hanya hash tersimpan), berlaku 24 jam, dan respons tanpa cache; token salah/kedaluwarsa memberikan 404. File membutuhkan alamat publik aplikasi; pengiriman media tidak tersedia dari localhost. Sertakan folder dan tabel audit `rekap_absen_pengiriman` dalam backup. Berkas dari audit yang sudah kedaluwarsa dapat dibersihkan tanpa menghapus riwayat/auditnya.

Deployment memerlukan `php spark migrate --all` untuk tabel audit baru. Migration lokal sudah diterapkan. Pengujian tambahan, semuanya memakai gateway palsu:

```sh
php tests/class-recap-delivery.php
php tests/whatsapp-gateway-transport.php
python3 tests/help-class-recap-browser.py
```
