<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class BuatCatatanKedisiplinan extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE catatan_kedisiplinan (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            siswa_id INT UNSIGNED NOT NULL,
            kelas_id INT UNSIGNED NOT NULL,
            kategori ENUM('etika','terlambat','atribut') NOT NULL,
            pelanggaran TEXT NULL,
            tanggal DATE NULL,
            saksi VARCHAR(180) NULL,
            alasan TEXT NULL,
            tindakan TEXT NULL,
            perbaikan TEXT NULL,
            jumlah_terlambat INT UNSIGNED NULL,
            dicatat_oleh INT UNSIGNED NOT NULL,
            diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unik_catatan_siswa_kategori (siswa_id,kategori),
            KEY indeks_catatan_kelas (kelas_id),
            CONSTRAINT fk_disiplin_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
            CONSTRAINT fk_disiplin_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
            CONSTRAINT fk_disiplin_pencatat FOREIGN KEY (dicatat_oleh) REFERENCES pengguna(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(){ $this->forge->dropTable('catatan_kedisiplinan',true); }
}
