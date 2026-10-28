<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PulihkanLoginSiswaKanaya extends Migration
{
    public function up()
    {
        $student=$this->db->table('siswa')->where(['nis'=>'13062','aktif'=>1])->get()->getRowArray();
        if(!$student||mb_strtolower(trim($student['nama_lengkap']))!=='kanaya safa aulia')return;
        $this->db->transBegin();
        $account=$this->db->query("SELECT id,peran FROM pengguna WHERE nis=? AND peran IN ('siswa','sekretaris') FOR UPDATE",['13062'])->getRowArray();
        $data=['kata_sandi'=>password_hash('13062',PASSWORD_DEFAULT),'aktif'=>1];
        if($account){
            if($account['peran']==='siswa')$data['nama_pengguna']='13062';
            $this->db->table('pengguna')->where('id',$account['id'])->update($data);
        }else{
            $this->db->table('pengguna')->insert($data+['nama_pengguna'=>'13062','nama_lengkap'=>$student['nama_lengkap'],'peran'=>'siswa','nis'=>'13062']);
        }
        if($this->db->transStatus()===false){$this->db->transRollback();throw new \RuntimeException('Login siswa Kanaya belum dapat dipulihkan.');}
        $this->db->transCommit();
    }
    public function down()
    {
        throw new \RuntimeException('Reset password tidak dapat dibatalkan otomatis.');
    }
}
