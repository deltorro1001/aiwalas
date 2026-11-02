<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class TambahKalenderAkademik extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE kalender_akademik (
            tanggal DATE NOT NULL PRIMARY KEY,
            jenis VARCHAR(50) NOT NULL,
            judul VARCHAR(160) NOT NULL,
            dampak_absensi VARCHAR(10) NOT NULL,
            keterangan TEXT NULL,
            dibuat_oleh INT UNSIGNED NULL,
            diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Preserve the school's previously requested dates, now editable through the calendar.
        foreach(\App\Libraries\SchoolCalendar::SPECIAL_HOLIDAYS as $date=>$note)$this->db->table('kalender_akademik')->insert(['tanggal'=>$date,'jenis'=>'Hari Libur','judul'=>'Libur sekolah','dampak_absensi'=>'Libur','keterangan'=>$note]);
        foreach(\App\Libraries\SchoolCalendar::PJJ_DATES as $date)$this->db->table('kalender_akademik')->insert(['tanggal'=>$date,'jenis'=>'PJJ','judul'=>'Pembelajaran Jarak Jauh (PJJ)','dampak_absensi'=>'PJJ']);
    }
    public function down(){$this->forge->dropTable('kalender_akademik',true);}
}
