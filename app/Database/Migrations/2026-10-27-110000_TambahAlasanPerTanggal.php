<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TambahAlasanPerTanggal extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE catatan_kedisiplinan ADD alasan_per_tanggal MEDIUMTEXT NULL AFTER alasan');
    }
    public function down()
    {
        throw new \RuntimeException('Alasan per tanggal harus dicadangkan sebelum migrasi ini dihapus.');
    }
}
