<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TambahTanggalPelanggaran extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE catatan_kedisiplinan ADD tanggal_kejadian TEXT NULL AFTER tanggal');
        // Keep existing dates; previously overwritten dates cannot be reconstructed.
        $this->db->query('UPDATE catatan_kedisiplinan SET tanggal_kejadian=JSON_ARRAY(DATE_FORMAT(tanggal,"%Y-%m-%d")) WHERE tanggal IS NOT NULL');
    }
    public function down()
    {
        throw new \RuntimeException('Riwayat tanggal pelanggaran harus dicadangkan sebelum migrasi ini dihapus.');
    }
}
