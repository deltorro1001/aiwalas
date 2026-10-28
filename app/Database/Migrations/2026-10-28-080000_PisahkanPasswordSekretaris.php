<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class PisahkanPasswordSekretaris extends Migration
{
    public function up()
    {
        if(!$this->db->fieldExists('kata_sandi_siswa','pengguna'))$this->db->query('ALTER TABLE pengguna ADD kata_sandi_siswa VARCHAR(255) NULL AFTER kata_sandi');
        // Preserve the student's existing password before changing the secretary password.
        $this->db->query("UPDATE pengguna SET kata_sandi_siswa=kata_sandi WHERE peran='sekretaris' AND nis IS NOT NULL AND kata_sandi_siswa IS NULL");
        $student=$this->db->table('siswa')->where(['nis'=>'13062','aktif'=>1])->get()->getRowArray();
        if(!$student||mb_strtolower(trim($student['nama_lengkap']))!=='kanaya safa aulia')return;
        $account=$this->db->table('pengguna')->where(['nis'=>'13062','peran'=>'sekretaris'])->get()->getRowArray();
        if(!$account)throw new \RuntimeException('Akun sekretaris Kanaya tidak ditemukan.');
        $this->db->table('pengguna')->where('id',$account['id'])->update(['nama_pengguna'=>'kanaya','kata_sandi'=>password_hash('kanaya123',PASSWORD_DEFAULT),'aktif'=>1]);
    }
    public function down(){throw new \RuntimeException('Password siswa dan sekretaris tidak boleh digabungkan kembali secara otomatis.');}
}
