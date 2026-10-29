# Koreksi absensi 13 Juli–30 September 2026

Migrasi `2026-10-29-050000_KoreksiMenungguAbsensiJuliSeptember2026` menjalankan koreksi satu kali sesuai permintaan wali kelas:

- Pada hari sekolah dalam rentang inklusif tersebut, hanya baris berstatus `Menunggu keterangan` yang berubah menjadi `Hadir`, dengan `waktu_scan = 06:30:00`. Kolom lain tetap.
- Hari sekolah yang belum mempunyai baris absensi juga tampil sebagai `Menunggu keterangan` pada Absensi Harian. Migrasi mengisinya untuk siswa aktif pada kelas tahun ajaran aktif `2026/2027` atau `2026-2027`, semester Ganjil. Pencatatnya adalah wali kelas terkait, dengan keterangan bahwa ini koreksi massal.
- Akhir pekan, tanggal Libur dan PJJ dari `SchoolCalendar` dikecualikan. Status Hadir yang sudah ada, Terlambat, Sakit, Izin, Alpha, dan PJJ tidak diperbarui.
- Tidak mengirim notifikasi atau pesan gateway.

Audit tersimpan pada tabel `audit_koreksi_absensi_jul_sep_2026`: ID absensi, status/waktu sebelumnya, penanda baris baru, dan waktu koreksi. Migrasi tidak melakukan pembatalan otomatis agar koreksi wali kelas berikutnya tidak tertimpa. Sertakan tabel audit dalam backup database.

Semua login membaca tabel absensi yang sama, sehingga Absensi Harian, rekap siswa/orangtua, rekap kelas/individu, dan laporan mengikuti hasil koreksi tanpa aturan tampilan tambahan. Pengulangan migrasi tidak menggandakan baris atau mengubah status lain.

Pengujian lokal: `php tests/pending-attendance-correction.php`. Pengujian berjalan dalam transaksi dan memverifikasi bahwa seluruh data absensi pulih setelah rollback.
