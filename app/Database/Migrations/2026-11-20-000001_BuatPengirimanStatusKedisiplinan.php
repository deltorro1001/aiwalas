<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class BuatPengirimanStatusKedisiplinan extends Migration
{
    public function up(){
        $this->db->query("CREATE TABLE status_kedisiplinan_pengiriman (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_key CHAR(64) NOT NULL UNIQUE,
            kelas_id INT UNSIGNED NOT NULL,
            owner_id INT UNSIGNED NOT NULL,
            format VARCHAR(8) NOT NULL,
            nomor_tujuan VARCHAR(20) NOT NULL,
            berkas VARCHAR(80) NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            berlaku_sampai DATETIME NOT NULL,
            message_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'processing',
            dibuat_pada DATETIME NOT NULL,
            result_note VARCHAR(255) NULL,
            CONSTRAINT fk_status_kedisiplinan_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
            CONSTRAINT fk_status_kedisiplinan_owner FOREIGN KEY (owner_id) REFERENCES pengguna(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(){$this->forge->dropTable('status_kedisiplinan_pengiriman',true);}
}
