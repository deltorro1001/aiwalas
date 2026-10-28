# Rekap absen kelas dan individu

Menu **Rekap Absen** tersedia pada login Wali Kelas dan Sekretaris Kelas, serta melalui tombol **Rekap Absen** dan **Rekap Individu** di Absensi Harian. Rekap mengambil tabel `absensi` yang sama dengan Absensi Harian; tidak memakai contoh data atau catatan Status Kedisiplinan.

- Pilih kelas dan semester melalui panel samping, lalu tahun pada halaman rekap (default tahun berjalan WIB).
- **Rekap Kelas** menampilkan 12 bulan dengan jumlah Sakit, Izin, Alpha dan Keterlambatan seluruh siswa aktif, serta totalnya. Tabel berikutnya menampilkan jumlah per siswa untuk bulan yang dipilih (default bulan berjalan).
- **Lihat Individu** atau tab **Rekap Individu** menampilkan 12 bulan untuk siswa yang dipilih, beserta total pada tahun/semester tersebut.
- Keterlambatan dihitung dari status `Terlambat` yang sudah tercatat, tanpa mengklasifikasi ulang catatan lama dari waktu scan. Hari tanpa catatan tidak dianggap Alpha. Status Hadir, PJJ dan Menunggu keterangan tidak dimasukkan ke empat jumlah tersebut. Total Tidak Hadir hanya Sakit + Izin + Alpha.
- **Muat ulang** mengambil koreksi Absensi Harian terbaru. **Export Excel** mengunduh file `.xlsx`. Rekap kelas berisi sheet Rekap Kelas, Rincian Bulan yang dipilih, serta Data Bulanan Siswa untuk seluruh siswa dan 12 bulan. Rekap individu berisi satu sheet untuk siswa tersebut. Setiap sheet memiliki judul, identitas kelas/tahun/semester, lebar kolom, filter, dan rumus SUM pada baris total. Nilai jumlah berupa angka untuk analisis, NIS berupa teks agar nol di depan tetap tersimpan, dan nama siswa tidak dijadikan formula.

API `GET api/absensi/rekap-kelas?kelas_id=ID&tahun=2026&semester=Ganjil` dapat diakses wali kelas pemilik kelas atau sekretaris aktif yang terdaftar sebagai siswa pada kelas tersebut. Sekretaris hanya melihat kelasnya sendiri; tampilan rekap dan export sama dengan wali kelas. Tahun menerima 2026–9998, semester Ganjil/Genap; query dibatasi tanggal 1 Januari sampai sebelum 1 Januari tahun berikutnya. Setiap siswa aktif tetap memiliki 12 bulan, dengan nol bila tidak ada catatan. Data siswa nonaktif dan kelas lain tidak dihitung. Tidak ada migrasi database.

Pengujian lokal: `AIWALAS_BROWSER_TEST=1 python3 tests/class-attendance-recap-local.py`, dengan localhost:8080 dan Chrome DevTools localhost:9222. Fixture sementara dibersihkan sesudah pengujian.
