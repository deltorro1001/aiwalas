<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TambahKegiatanEkskulSiswa extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('kegiatan_ekskul', 'siswa')) {
            $this->forge->addColumn('siswa', [
                'kegiatan_ekskul' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'hobi',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('kegiatan_ekskul', 'siswa')) {
            $this->forge->dropColumn('siswa', 'kegiatan_ekskul');
        }
    }
}
