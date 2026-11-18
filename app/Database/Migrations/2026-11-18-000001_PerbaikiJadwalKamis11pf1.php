<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PerbaikiJadwalKamis11pf1 extends Migration
{
    public function up()
    {
        $this->db->query("UPDATE jadwal_kelas j
            JOIN kelas k ON k.id=j.kelas_id
            JOIN tahun_ajaran t ON t.id=k.tahun_ajaran_id
            JOIN mata_pelajaran m ON m.id=j.mata_pelajaran_id
            SET j.jam_mulai='08:15:00', j.jam_selesai='10:45:00'
            WHERE k.nama_kelas='11PF1' AND t.aktif=1 AND j.hari='Kamis'
              AND m.nama_mata_pelajaran='Matematika'");

        $this->db->query("UPDATE jadwal_kelas j
            JOIN kelas k ON k.id=j.kelas_id
            JOIN tahun_ajaran t ON t.id=k.tahun_ajaran_id
            JOIN mata_pelajaran m ON m.id=j.mata_pelajaran_id
            SET j.jam_mulai='10:45:00', j.jam_selesai='13:30:00'
            WHERE k.nama_kelas='11PF1' AND t.aktif=1 AND j.hari='Kamis'
              AND m.nama_mata_pelajaran='KIK'");
    }

    public function down()
    {
        // Retain the corrected school timetable when rolling back code.
    }
}
