<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PerbaikiAbsensiPutriAisyah extends Migration
{
    public function up()
    {
        $student = $this->db->table('siswa')
            ->select('siswa.id, siswa.kelas_id, kelas.wali_kelas_id')
            ->join('kelas', 'kelas.id = siswa.kelas_id')
            ->where('siswa.nis', '13133')
            ->get()
            ->getRowArray();

        if (! $student) {
            return;
        }

        $key = [
            'siswa_id' => (int) $student['id'],
            'kelas_id' => (int) $student['kelas_id'],
            'tanggal' => '2026-10-08',
        ];
        $data = [
            'waktu_scan' => null,
            'status' => 'Sakit',
            'keterangan' => 'Koreksi wali kelas: sakit',
            'dicatat_oleh' => (int) $student['wali_kelas_id'],
        ];
        $attendance = $this->db->table('absensi');

        if ($attendance->where($key)->countAllResults() > 0) {
            $this->db->table('absensi')->where($key)->update($data);
            return;
        }

        $this->db->table('absensi')->insert($key + $data);
    }

    public function down()
    {
        // Koreksi data kehadiran tidak dikembalikan otomatis.
    }
}