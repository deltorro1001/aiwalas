<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class SatukanPkkMenjadiKik extends Migration
{
    public function up()
    {
        $this->db->query('CREATE TABLE IF NOT EXISTS arsip_penggabungan_pkk (nilai_id BIGINT UNSIGNED PRIMARY KEY,data_asli MEDIUMTEXT NOT NULL,diarsipkan_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        \App\Libraries\MergePkkIntoKik::apply();
    }
    public function down(){throw new \RuntimeException('Referensi dan arsip nilai PKK/KIK harus ditangani secara eksplisit sebelum rollback.');}
}
