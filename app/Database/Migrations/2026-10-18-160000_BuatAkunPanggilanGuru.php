<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
use App\Libraries\TeacherNicknameAccounts;

class BuatAkunPanggilanGuru extends Migration
{
    public function up()
    {
        $count=TeacherNicknameAccounts::provision($this->db);
        echo 'Akun guru berdasarkan nama panggilan disiapkan: '.$count.".\n";
    }
    public function down()
    {
        throw new \RuntimeException('Password sebelumnya tidak dapat dikembalikan. Gunakan reset password per akun.');
    }
}
