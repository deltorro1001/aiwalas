<?php
namespace App\Controllers\Api;

class AkunGuruController extends BaseApiController
{
    public function index()
    {
        $rows = db_connect()->table('guru g')
            ->select('g.id,g.nama_lengkap,g.nama_panggilan,p.nama_pengguna,p.aktif AS akun_aktif')
            ->join('pengguna p', "p.guru_id=g.id AND p.peran='guru'", 'left')
            ->where('g.aktif', 1)->orderBy('g.nama_lengkap')->get()->getResultArray();
        $this->response->setHeader('Cache-Control', 'no-store');
        return $this->respond(['data'=>$rows]);
    }

    public function save(int $id)
    {
        $payload = $this->payload();
        $username = trim((string)($payload['username']??''));
        $reset = ($payload['reset']??false) === true;
        if (!preg_match('/^[a-zA-Z0-9._-]{3,100}$/', $username)) {
            return $this->respond(['ok'=>false,'message'=>'Username harus 3–100 karakter: huruf, angka, titik, garis bawah, atau tanda minus.'],422);
        }
        $db = db_connect();
        $db->transBegin();
        try {
            $teacher = $db->query('SELECT id,nama_lengkap FROM guru WHERE id=? AND aktif=1 FOR UPDATE',[$id])->getRowArray();
            if (!$teacher) { $db->transRollback(); return $this->respond(['ok'=>false,'message'=>'Guru aktif tidak ditemukan.'],404); }
            $account = $db->table('pengguna')->where('guru_id',$id)->get()->getRowArray();
            if ($account && (!$reset || $account['peran']!=='guru' || $account['nama_pengguna']!==$username)) {
                $db->transRollback();
                return $this->respond(['ok'=>false,'message'=>'Akun sudah tersedia. Gunakan Reset Password dengan username yang sama.'],409);
            }
            if (!$account && $reset) { $db->transRollback(); return $this->respond(['ok'=>false,'message'=>'Akun belum dibuat. Muat ulang daftar guru.'],409); }
            $duplicate = $db->table('pengguna')->where('nama_pengguna',$username);
            if ($account) $duplicate->where('id !=',$account['id']);
            if ($duplicate->countAllResults()) { $db->transRollback(); return $this->respond(['ok'=>false,'message'=>'Username sudah digunakan. Pilih username lain.'],409); }
            $password = bin2hex(random_bytes(10));
            $data = ['nama_pengguna'=>$username,'nama_lengkap'=>$teacher['nama_lengkap'],'peran'=>'guru','guru_id'=>$id,'aktif'=>1,'kata_sandi'=>password_hash($password,PASSWORD_DEFAULT)];
            if ($account) $ok=$db->table('pengguna')->where('id',$account['id'])->update($data);
            else $ok=$db->table('pengguna')->insert($data);
            if (!$ok || !$db->transStatus()) throw new \RuntimeException('Account write failed');
            $db->transCommit();
            $this->response->setHeader('Cache-Control','no-store');
            return $this->respond(['ok'=>true,'username'=>$username,'password'=>$password,'message'=>$account?'Password guru berhasil direset.':'Akun guru berhasil dibuat.']);
        } catch (\Throwable $error) {
            $db->transRollback();
            return $this->respond(['ok'=>false,'message'=>'Akun tidak dapat disimpan. Muat ulang daftar dan coba kembali.'],409);
        }
    }
}
