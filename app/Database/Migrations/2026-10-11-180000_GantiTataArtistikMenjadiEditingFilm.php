<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class GantiTataArtistikMenjadiEditingFilm extends Migration
{
    public function up()
    {
        $db = $this->db;
        $old = $db->table('mata_pelajaran')->where('nama_mata_pelajaran', 'Tata Artistik')->get()->getRowArray();
        $new = $db->table('mata_pelajaran')->where('nama_mata_pelajaran', 'Editing Film')->get()->getRowArray();

        if ($old && $new && (int) $old['id'] !== (int) $new['id']) {
            $oldId = (int) $old['id'];
            $newId = (int) $new['id'];
            $db->table('komponen_nilai')->where('mata_pelajaran_id', $oldId)->update(['mata_pelajaran_id' => $newId]);
            $db->table('jadwal_kelas')->where('mata_pelajaran_id', $oldId)->update(['mata_pelajaran_id' => $newId]);
            $db->table('jurnal_kelas')->where('mata_pelajaran_id', $oldId)->update(['mata_pelajaran_id' => $newId]);
            $db->table('remedial_tugas')->where('mata_pelajaran_id', $oldId)->update(['mata_pelajaran_id' => $newId]);
            $db->table('mata_pelajaran')->where('id', $oldId)->delete();
        } elseif ($old && !$new) {
            $db->table('mata_pelajaran')->where('id', (int) $old['id'])->update(['nama_mata_pelajaran' => 'Editing Film']);
        }

        $db->table('guru')->like('nama_lengkap', 'Ruby Eka Prawira', 'both')->update(['mata_pelajaran' => 'Editing Film']);
    }

    public function down()
    {
        $this->db->table('guru')->like('nama_lengkap', 'Ruby Eka Prawira', 'both')->update(['mata_pelajaran' => 'Tata Artistik']);
    }
}
