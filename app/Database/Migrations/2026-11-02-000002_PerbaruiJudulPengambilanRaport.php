<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class PerbaruiJudulPengambilanRaport extends Migration
{
    public function up()
    {
        $this->db->table('kalender_akademik')->where(['jenis'=>'Hari Pengambilan Raport','judul'=>'Hari Pengambilan Raport'])->update(['judul'=>'Pengambilan Raport']);
    }
    public function down() {} // Preserve edited titles when rolling back.
}
