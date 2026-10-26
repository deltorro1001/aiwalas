<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TambahStatusPjjAbsensi extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE absensi MODIFY status ENUM('Hadir','Terlambat','Sakit','Izin','Alpha','Menunggu keterangan','PJJ') NOT NULL");
    }

    public function down()
    {
        if ($this->db->table('absensi')->where('status', 'PJJ')->countAllResults()) {
            throw new \RuntimeException('Status PJJ masih digunakan pada absensi. Migrasi dibatalkan agar data tetap tersimpan.');
        }
        $this->db->query("ALTER TABLE absensi MODIFY status ENUM('Hadir','Terlambat','Sakit','Izin','Alpha','Menunggu keterangan') NOT NULL");
    }
}
