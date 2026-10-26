<?php
namespace App\Libraries;

class ParentAccounts
{
    public static function ensure(array $student): int
    {
        $db=db_connect();
        $existing=$db->table('pengguna')->select('id')->where(['peran'=>'orang_tua','siswa_id'=>$student['id']])->get()->getRowArray();
        if($existing)return (int)$existing['id'];
        // The internal key avoids colliding with the student's NIS username.
        // Login resolves the child's NIS to this linked account, not to the internal key.
        $db->query("INSERT INTO pengguna (nama_pengguna,kata_sandi,nama_lengkap,peran,siswa_id,aktif) VALUES (?,?,?,'orang_tua',?,1) ON DUPLICATE KEY UPDATE id=id",['orangtua:'.$student['id'],password_hash((string)$student['nis'],PASSWORD_DEFAULT),$student['nama_lengkap'],$student['id']]);
        $account=$db->table('pengguna')->select('id')->where(['peran'=>'orang_tua','siswa_id'=>$student['id']])->get()->getRowArray();
        if(!$account)throw new \RuntimeException('Akun orangtua tidak dapat dibuat.');
        return (int)$account['id'];
    }
    public static function sessionValid(array $user): bool
    {
        return (bool)db_connect()->table('pengguna p')->join('siswa s','s.id=p.siswa_id')->where(['p.id'=>(int)($user['id']??0),'p.peran'=>'orang_tua','p.aktif'=>1,'s.id'=>(int)($user['anak_id']??0),'s.nis'=>$user['nis']??'','s.aktif'=>1])->countAllResults();
    }
}
