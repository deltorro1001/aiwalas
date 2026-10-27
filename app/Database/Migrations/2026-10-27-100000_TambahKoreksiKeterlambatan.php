<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TambahKoreksiKeterlambatan extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE catatan_kedisiplinan ADD tanggal_dikecualikan TEXT NULL AFTER tanggal_kejadian, ADD jumlah_dasar_absensi INT UNSIGNED NULL AFTER jumlah_terlambat, ADD status_notifikasi_orangtua VARCHAR(40) NULL, ADD notifikasi_orangtua TEXT NULL');
    }
    public function down()
    {
        throw new \RuntimeException('Koreksi keterlambatan dan catatan pemberitahuan harus dicadangkan sebelum migrasi ini dihapus.');
    }
}
