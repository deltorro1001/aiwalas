<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class BuatPengirimanRekapAbsen extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE rekap_absen_pengiriman (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_key CHAR(64) NOT NULL UNIQUE,
            kelas_id INT UNSIGNED NOT NULL,
            owner_id INT UNSIGNED NOT NULL,
            tahun SMALLINT UNSIGNED NOT NULL,
            bulan TINYINT UNSIGNED NOT NULL,
            nomor_tujuan VARCHAR(20) NOT NULL,
            berkas VARCHAR(80) NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            berlaku_sampai DATETIME NOT NULL,
            message_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'processing',
            dibuat_pada DATETIME NOT NULL,
            result_note VARCHAR(255) NULL,
            CONSTRAINT fk_rekap_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
            CONSTRAINT fk_rekap_owner FOREIGN KEY (owner_id) REFERENCES pengguna(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(){$this->forge->dropTable('rekap_absen_pengiriman',true);}
}
