<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class BuatPortalTamu extends Migration
{
    public function up()
    {
        $account=$this->db->table('pengguna')->where('nama_pengguna','tamu')->get()->getRowArray();
        if($account&&$account['peran']!=='guest')throw new \RuntimeException('Username tamu sudah dipakai oleh peran lain.');
        $this->db->query("CREATE TABLE pengumuman_tamu (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kelas_id INT UNSIGNED NOT NULL,
            judul VARCHAR(180) NOT NULL,
            isi TEXT NOT NULL,
            tanggal_agenda DATE NULL,
            terbit TINYINT(1) NOT NULL DEFAULT 0,
            dibuat_oleh INT UNSIGNED NOT NULL,
            dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY indeks_pengumuman_tamu (kelas_id,terbit,tanggal_agenda),
            CONSTRAINT fk_pengumuman_tamu_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
            CONSTRAINT fk_pengumuman_tamu_pengguna FOREIGN KEY (dibuat_oleh) REFERENCES pengguna(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $data=['nama_pengguna'=>'tamu','kata_sandi'=>password_hash('tamu123',PASSWORD_DEFAULT),'nama_lengkap'=>'Tamu AIWalas','peran'=>'guest','aktif'=>1];
        if($account)$this->db->table('pengguna')->where('id',$account['id'])->update($data);
        else $this->db->table('pengguna')->insert($data);
    }
    public function down(){ $this->forge->dropTable('pengumuman_tamu',true); }
}
