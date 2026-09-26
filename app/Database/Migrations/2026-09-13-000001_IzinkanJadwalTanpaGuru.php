<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class IzinkanJadwalTanpaGuru extends Migration
{
    public function up(){ $this->db->query('ALTER TABLE jadwal_kelas MODIFY guru_id INT UNSIGNED NULL'); }
    public function down(){ $this->db->table('jadwal_kelas')->where('guru_id',null)->delete(); $this->db->query('ALTER TABLE jadwal_kelas MODIFY guru_id INT UNSIGNED NOT NULL'); }
}