# Koreksi Status Kedisiplinan

Pada akun Wali Kelas, tombol **Edit** dapat menambah, mengubah, atau menghapus tanggal kejadian untuk setiap kategori. Tanggal pada tabel dan export ditulis **dd-mm-yyyy**. Catatan lama yang sebelumnya tertimpa tidak dapat dipulihkan; tanggal yang belum tersedia dapat dilengkapi manual.

Untuk **Keterlambatan**, formulir menampilkan seluruh tanggal, termasuk sumber absensi. Jumlah mengikuti perubahan daftar tanggal sampai wali kelas mengoreksi jumlah secara manual. Setiap tanggal keterlambatan memiliki isian alasan sendiri; mengubah tanggal mempertahankan alasan pada kejadian tersebut dan menghapus tanggal ikut menghapus alasannya. Alasan lama yang sebelumnya berlaku umum dipakai sebagai isian awal, lalu dapat diperbaiki per tanggal. Tabel dan export memasangkan tanggal dd-mm-yyyy dengan alasannya pada baris yang sama, tanpa mengulangi tanggal pada kolom alasan. Alasan panjang membungkus dan otomatis menambah tinggi baris tanggal.

**Hapus Catatan** menampilkan konfirmasi di dalam formulir. Mengonfirmasi akan mengosongkan isian kategori tersebut. Untuk keterlambatan, jumlah menjadi nol dan tanggal sumber absensi yang dihapus dikecualikan dari catatan kedisiplinan agar tidak muncul kembali. Riwayat scan absensi tetap tersimpan; perubahan kehadiran dilakukan melalui Absensi Harian. Kejadian keterlambatan baru tetap masuk ke catatan.

Penyimpanan memakai sesi wali kelas dan kepemilikan kelas; siswa, orangtua, atau wali kelas lain tidak dapat melakukan koreksi. API lama yang tidak mengirim `tanggal_kejadian` tetap mempertahankan tanggal yang sudah tersimpan. API formulir keterlambatan mengirim `koreksi_keterlambatan: true` bersama daftar tanggal akhir dan jumlah akhir, sehingga penyimpanan ulang tidak menggandakan kejadian.

Migrasi lokal/produksi: `php spark migrate --all`. Migrasi menambah kolom pada `catatan_kedisiplinan`; tanggal lama dipertahankan. Rollback memerlukan penanganan data secara eksplisit agar riwayat tidak terhapus tanpa sengaja.

Pengujian lokal: `AIWALAS_BROWSER_TEST=1 python3 tests/student-home-local.py` dengan server localhost:8080 dan Chrome DevTools localhost:9222. Tes memakai fixture sementara dan membersihkannya setelah selesai.
