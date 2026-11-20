<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class IzinkanPercobaanUlangKolase extends Migration
{
    public function up()
    {
        // A collage may have several explicit send attempts to the same teacher.
        // Keep an index for history lookups, but do not make the first attempt final.
        $this->db->query('ALTER TABLE foto_kolase_pengiriman DROP INDEX unik_kirim_kolase, ADD INDEX idx_kirim_kolase_guru (halaman_id,guru_id)');
    }

    public function down()
    {
        $duplicates=$this->db->query('SELECT halaman_id,guru_id,COUNT(*) AS jumlah FROM foto_kolase_pengiriman WHERE guru_id IS NOT NULL GROUP BY halaman_id,guru_id HAVING COUNT(*)>1 LIMIT 1')->getRowArray();
        if($duplicates)throw new \RuntimeException('Riwayat percobaan ulang kolase tersimpan; rollback akan menghapus riwayat dan tidak diizinkan.');
        $this->db->query('ALTER TABLE foto_kolase_pengiriman DROP INDEX idx_kirim_kolase_guru, ADD UNIQUE KEY unik_kirim_kolase (halaman_id,guru_id)');
    }
}
