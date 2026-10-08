<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TambahSemesterData extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('semester', 'absensi')) {
            return;
        }
        $this->forge->addColumn('absensi', [
            'semester' => ['type' => 'ENUM', 'constraint' => ['Ganjil', 'Genap'], 'default' => 'Ganjil', 'after' => 'tanggal'],
        ]);
        $this->db->query("UPDATE absensi SET semester = 'Ganjil' WHERE semester IS NULL OR semester = ''");
        $this->db->query('ALTER TABLE absensi DROP INDEX unik_absensi_harian, ADD UNIQUE KEY unik_absensi_semester (siswa_id, kelas_id, tanggal, semester)');
    }

    public function down()
    {
        if ($this->db->fieldExists('semester', 'absensi')) {
            $this->db->query('ALTER TABLE absensi DROP INDEX unik_absensi_semester, ADD UNIQUE KEY unik_absensi_harian (siswa_id, kelas_id, tanggal)');
            $this->forge->dropColumn('absensi', 'semester');
        }
    }
}