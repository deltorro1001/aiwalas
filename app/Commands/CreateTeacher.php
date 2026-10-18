<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
class CreateTeacher extends BaseCommand
{
    protected $group = 'AIWalas';
    protected $name = 'aiwalas:teacher';
    protected $description = 'Buat akun guru dengan password acak: aiwalas:teacher GURU_ID USERNAME';
    public function run(array $params)
    {
        $id = (int) ($params[0] ?? 0);
        $username = trim($params[1] ?? '');
        $db = db_connect();
        if (!$id || !preg_match('/^[a-zA-Z0-9._-]{3,100}$/', $username)) { CLI::error('Gunakan: aiwalas:teacher GURU_ID USERNAME'); return; }
        $guru = $db->table('guru')->where(['id'=>$id,'aktif'=>1])->get()->getRowArray();
        if (!$guru || $db->table('pengguna')->groupStart()->where('nama_pengguna',$username)->orWhere('guru_id',$id)->groupEnd()->countAllResults()) { CLI::error('Guru tidak ditemukan atau akun sudah tersedia.'); return; }
        $password = bin2hex(random_bytes(12));
        $db->table('pengguna')->insert(['nama_pengguna'=>$username,'nama_lengkap'=>$guru['nama_lengkap'],'peran'=>'guru','guru_id'=>$id,'kata_sandi'=>password_hash($password,PASSWORD_DEFAULT),'aktif'=>1]);
        CLI::write('Username: '.$username);
        CLI::write('Password awal: '.$password);
        CLI::write('Simpan password ini dan ubah melalui Pengaturan setelah login.');
    }
}
