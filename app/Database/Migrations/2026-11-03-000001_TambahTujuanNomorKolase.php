<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class TambahTujuanNomorKolase extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE foto_kolase_pengiriman MODIFY guru_id INT UNSIGNED NULL, ADD nomor_tujuan VARCHAR(20) NULL AFTER guru_id, ADD INDEX idx_kolase_nomor (halaman_id,nomor_tujuan)');
        // Retain historical attempts, including different teachers sharing a phone.
        $rows=$this->db->table('foto_kolase_pengiriman d')->select('d.id,p.nomor_tujuan AS pesan_nomor,g.nomor_hp AS guru_nomor')->join('pesan_komunikasi p','p.id=d.message_id','left')->join('guru g','g.id=d.guru_id','left')->get()->getResultArray();
        foreach($rows as $row){$phone=\App\Libraries\WhatsAppGateway::phone($row['pesan_nomor']?:$row['guru_nomor']);if($phone!=='')$this->db->table('foto_kolase_pengiriman')->where('id',$row['id'])->update(['nomor_tujuan'=>$phone]);}
    }
    public function down()
    {
        if($this->db->table('foto_kolase_pengiriman')->where('guru_id',null)->countAllResults())throw new \RuntimeException('Pengiriman ke nomor manual sudah tersimpan; rollback tidak boleh menghapus riwayat tersebut.');
        $this->db->query('ALTER TABLE foto_kolase_pengiriman DROP INDEX idx_kolase_nomor, DROP COLUMN nomor_tujuan, MODIFY guru_id INT UNSIGNED NOT NULL');
    }
}
