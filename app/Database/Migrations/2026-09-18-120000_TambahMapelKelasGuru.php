<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class TambahMapelKelasGuru extends Migration
{
 public function up(){if(!$this->db->fieldExists('mapel_kelas','guru'))$this->forge->addColumn('guru',['mapel_kelas'=>['type'=>'VARCHAR','constraint'=>180,'null'=>true,'after'=>'mata_pelajaran']]);}
 public function down(){if($this->db->fieldExists('mapel_kelas','guru'))$this->forge->dropColumn('guru','mapel_kelas');}
}
