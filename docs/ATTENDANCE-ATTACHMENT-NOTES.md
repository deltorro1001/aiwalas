# Keterangan surat pada ketidakhadiran

Data kehadiran tetap berasal dari tabel absensi yang diisi otomatis oleh pengajuan baru orangtua. Status Sakit/Izin ditampilkan dengan keterangan berikut hanya jika terdapat pengajuan untuk siswa, kelas, tanggal, dan jenis status yang sesuai serta berkasnya masih tersimpan:

- Sakit (surat sakit terlampir)
- Izin (surat izin terlampir)

Pengajuan tanpa lampiran tidak menambahkan keterangan dalam kurung. Berkas pengajuan dari tanggal/kelas/jenis lain tidak digunakan. Koreksi menjadi Hadir atau status lainnya menyembunyikan keterangan lampiran. Data status aslinya tetap Sakit atau Izin, sehingga perhitungan statistik dan rekap tidak berubah.

Keterangan muncul pada kartu Siswa Tidak Hadir Hari Ini, tabel Absensi Harian, status siswa/orangtua, rekap individu, dan kartu kehadiran pada Beranda siswa/orangtua. Kartu Notifikasi dan Status Kedisiplinan yang sudah ada dipertahankan. Beranda pengguna berwenang memperbarui data kehadiran setiap 15 detik saat terlihat, tanpa mengubah status atau mengirim pesan.

Metadata yang dibagikan pada tampilan kelas hanya penanda ada/tidaknya lampiran dan teks keterangan tetap. Nama berkas, isi/alasan pengajuan, serta URL unduh tidak disertakan pada ringkasan kelas. Lampiran tetap menggunakan endpoint unduh dengan pemeriksaan akses yang sudah ada. Akun tamu tidak mendapatkan data siswa baru.

Pemeriksaan lokal dengan data sementara dan Chrome DevTools pada localhost:9222:

```sh
PYTHONDONTWRITEBYTECODE=1 AIWALAS_BROWSER_TEST=1 python3 tests/attendance-attachment-notes-local.py
```
