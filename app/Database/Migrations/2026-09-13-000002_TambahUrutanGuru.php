<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class TambahUrutanGuru extends Migration
{
    public function up(){ $this->forge->addColumn('guru',['urutan'=>['type'=>'SMALLINT','unsigned'=>true,'null'=>true,'after'=>'id']]); }
    public function down(){ $this->forge->dropColumn('guru','urutan'); }
}