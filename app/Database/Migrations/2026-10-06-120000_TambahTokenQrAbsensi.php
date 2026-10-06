<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TambahTokenQrAbsensi extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('qr_absensi_aktif')) {
            return;
        }

        $this->forge->addField([
            'kelas_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'token' => ['type' => 'VARCHAR', 'constraint' => 64],
            'dibuat_oleh' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'dibuat_pada' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('kelas_id', true);
        $this->forge->addUniqueKey('token');
        $this->forge->createTable('qr_absensi_aktif', true);
    }

    public function down()
    {
        $this->forge->dropTable('qr_absensi_aktif', true);
    }
}
