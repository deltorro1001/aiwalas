<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class TambahStatusKedisiplinanPerKategori extends Migration
{
    public function up()
    {
        $this->forge->addColumn('catatan_kedisiplinan',['status_kedisiplinan'=>['type'=>'VARCHAR','constraint'=>160,'null'=>true]]);
    }
    public function down(){ $this->forge->dropColumn('catatan_kedisiplinan','status_kedisiplinan'); }
}
