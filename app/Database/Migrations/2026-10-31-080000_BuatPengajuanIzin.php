<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BuatPengajuanIzin extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE pengajuan_izin (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            siswa_id INT UNSIGNED NOT NULL,
            kelas_id INT UNSIGNED NOT NULL,
            pengguna_id INT UNSIGNED NOT NULL,
            tanggal_mulai DATE NOT NULL,
            tanggal_selesai DATE NOT NULL,
            jumlah_hari SMALLINT UNSIGNED NOT NULL,
            jenis_izin VARCHAR(80) NOT NULL,
            keterangan VARCHAR(1000) NULL,
            nama_lampiran VARCHAR(255) NULL,
            berkas_lampiran VARCHAR(80) NULL,
            mime_lampiran VARCHAR(80) NULL,
            dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY indeks_pengajuan_kelas (kelas_id,dibuat_pada),
            CONSTRAINT fk_pengajuan_izin_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
            CONSTRAINT fk_pengajuan_izin_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
            CONSTRAINT fk_pengajuan_izin_pengguna FOREIGN KEY (pengguna_id) REFERENCES pengguna(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down() { $this->forge->dropTable('pengajuan_izin', true); }
}
