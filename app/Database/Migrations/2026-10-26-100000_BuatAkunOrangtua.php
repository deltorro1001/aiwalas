<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
use App\Libraries\ParentAccounts;

class BuatAkunOrangtua extends Migration
{
    public function up()
    {
        // Column guard also permits a safe retry if account provisioning was interrupted.
        if(!$this->db->fieldExists('siswa_id','pengguna')){
            $this->db->query("ALTER TABLE pengguna MODIFY peran ENUM('wali_kelas','sekretaris','siswa','guest','guru','orang_tua') NOT NULL, ADD siswa_id INT UNSIGNED NULL, ADD UNIQUE KEY unik_akun_orangtua (siswa_id), ADD CONSTRAINT fk_pengguna_anak FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE RESTRICT");
        }
        $this->db->transBegin();
        try{
            foreach($this->db->table('siswa')->where('aktif',1)->get()->getResultArray() as $student)ParentAccounts::ensure($student);
            if(!$this->db->transStatus())throw new \RuntimeException('Pembuatan akun orangtua gagal.');
            $this->db->transCommit();
        }catch(\Throwable $error){$this->db->transRollback();throw $error;}
    }
    public function down()
    {
        throw new \RuntimeException('Rollback memerlukan pemindahan akun orangtua secara eksplisit agar kredensial tidak dihapus.');
    }
}
