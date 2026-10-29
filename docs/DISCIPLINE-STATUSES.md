# Status kedisiplinan per kategori

Etika dan Pakaian & Atribut mempunyai status tersimpan masing-masing. Wali kelas memilihnya melalui **Edit → Status Kedisiplinan**:

1. Baik
2. Peringatan 1
3. Peringatan 2 dan Pemanggilan Orangtua/Wali Murid ke-1
4. Peringatan 3 dan Pemanggilan Orangtua/Wali Murid ke-2
5. Peringatan Keras dan Pemanggilan Orangtua/Wali Murid ke-3

Tanpa catatan, statusnya Baik. Catatan lama tanpa pilihan status menggunakan Peringatan 1; wali kelas dapat mengubah tingkatannya. Baik tidak boleh dipilih bersama isian pelanggaran; tombol Hapus Catatan mengosongkan catatan dan mengembalikan kategorinya ke Baik. Pilihan peringatan sendiri tetap dapat disimpan sebagai catatan status.

Keterlambatan tetap dihitung dengan aturan sebelumnya. Status keterlambatan tidak dipakai untuk Etika/Pakaian & Atribut dan tidak dapat diganti melalui pilihan status manual. Tidak ada pesan atau pemanggilan otomatis yang dikirim saat status disimpan.

Kolom `catatan_kedisiplinan.status_kedisiplinan` ditambahkan oleh migration `2026-10-29-070000_TambahStatusKedisiplinanPerKategori`. API wali kelas, ekspor, serta tampilan siswa/orangtua membaca hasil per kategori dari `DisciplineRecords`.

Pengujian lokal: `AIWALAS_BROWSER_TEST=1 python3 tests/student-home-local.py`. Menggunakan akun dan data sementara, tanpa mengirim pesan gateway.
