<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class PusatkanAkademik extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE pengguna MODIFY peran ENUM('wali_kelas','sekretaris','siswa','guest','guru') NOT NULL");
        $this->db->query('ALTER TABLE pengguna ADD guru_id INT UNSIGNED NULL, ADD UNIQUE KEY unik_akun_guru (guru_id), ADD CONSTRAINT fk_pengguna_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE RESTRICT');
        $this->db->query("ALTER TABLE nilai_siswa ADD semester ENUM('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil', DROP INDEX unik_nilai_siswa_komponen, ADD UNIQUE KEY unik_nilai_siswa_komponen (siswa_id,komponen_nilai_id,semester)");
    }
    public function down()
    {
        // Rollback requires a data migration when both semesters contain grades.
        throw new \RuntimeException('Migration ini memerlukan rollback data secara eksplisit.');
    }
}
