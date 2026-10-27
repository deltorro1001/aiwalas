<?php

namespace App\Controllers;

use App\Models\PenggunaModel;

class Auth extends BaseController
{
    public function csrf()
    {
        return $this->json(['ok' => true]);
    }

    public function session()
    {
        $pengguna = session()->get('pengguna');
        if (($pengguna['peran']??'')==='guru' && ((int)($pengguna['id']??0)<1 || !(int)($pengguna['guru_id']??0))) {session()->remove('pengguna');$pengguna=null;}

        if(($pengguna['peran']??'')==='orang_tua'&&!\App\Libraries\ParentAccounts::sessionValid($pengguna)){session()->remove('pengguna');$pengguna=null;}

        return $this->json([
            'authenticated' => $pengguna !== null,
            'user' => $pengguna === null ? null : $this->frontendUser($pengguna),
        ]);
    }

    public function attempt()
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $rules = [
            'role' => 'required|in_list[walas,assistant,student,parent,teacher,guest]',
            'username' => 'required|max_length[100]',
            'password' => 'required|max_length[255]',
        ];
        $validation = service('validation');
        $validation->setRules($rules, [
            'role' => ['required' => 'Peran wajib dipilih.', 'in_list' => 'Peran tidak valid.'],
            'username' => ['required' => 'Username wajib diisi.'],
            'password' => ['required' => 'Password wajib diisi.'],
        ]);

        if (! $validation->run($payload)) {
            return $this->json(['ok' => false, 'message' => 'Data login belum lengkap.', 'errors' => $validation->getErrors()], 422);
        }

        $roleMap = ['walas' => 'wali_kelas', 'assistant' => 'sekretaris', 'student' => 'siswa', 'parent' => 'orang_tua', 'teacher' => 'guru', 'guest' => 'guest'];
        $model = new PenggunaModel();
        $pengguna = $model->where('nama_pengguna', trim((string) $payload['username']))
            ->where('peran', $roleMap[$payload['role']])
            ->where('aktif', 1)
            ->first();

        if($payload['role']==='parent'){
            $child=db_connect()->table('siswa')->where(['nis'=>trim((string)$payload['username']),'aktif'=>1])->get()->getRowArray();
            $pengguna=$child?$model->where(['peran'=>'orang_tua','siswa_id'=>$child['id'],'aktif'=>1])->first():null;
            if($child&&$pengguna===null&&hash_equals((string)$child['nis'],(string)$payload['password'])){
                $parentId=\App\Libraries\ParentAccounts::ensure($child);
                $pengguna=$model->where(['id'=>$parentId,'peran'=>'orang_tua','aktif'=>1])->first();
            }
        }

        // Akun siswa mengikuti kredensial NIS. Untuk data siswa lama yang
        // belum memiliki baris pengguna, buat akun otomatis saat login pertama.
        if ($pengguna === null && $payload['role'] === 'student') {
            $nis = trim((string) $payload['username']);
            $student = db_connect()->table('siswa')
                ->where('nis', $nis)
                ->where('aktif', 1)
                ->get()->getRowArray();
            $existingLinked = $student ? $model->where('nis', $nis)->where('aktif', 1)->first() : null;
            if ($student && ! $existingLinked && hash_equals($nis, (string) $payload['password'])) {
                $userId = $model->insert([
                    'nama_pengguna' => $nis,
                    'kata_sandi' => password_hash($nis, PASSWORD_DEFAULT),
                    'nama_lengkap' => $student['nama_lengkap'],
                    'peran' => 'siswa',
                    'nis' => $nis,
                    'aktif' => 1,
                ], true);
                $pengguna = $model->find($userId);
            }
        }

        // Allow a student who also has a secretary account (for example Kanaya) to use student login.
        if ($pengguna === null && $payload['role'] === 'student') {
            $student = db_connect()->table('siswa')->where('aktif', 1)->groupStart()
                ->where('nis', trim((string) $payload['username']))
                ->orWhere('nis', function ($builder) use ($payload) {
                    return $builder->select('nis')->from('pengguna')->where('nama_pengguna', trim((string) $payload['username']))->where('peran', 'sekretaris')->where('aktif', 1)->limit(1);
                })->groupEnd()->get()->getRowArray();
            if ($student) {
                $linked = $model->where('nis', $student['nis'])->where('aktif', 1)->first();
                if ($linked && password_verify((string) $payload['password'], $linked->kata_sandi)) {
                    $sessionUser = ['id' => 0, 'nama_pengguna' => $student['nis'], 'nama_lengkap' => $student['nama_lengkap'], 'peran' => 'siswa', 'nis' => $student['nis']];
                    session()->regenerate(); session()->set('pengguna', $sessionUser);
                    return $this->json(['ok' => true, 'user' => $this->frontendUser($sessionUser)]);
                }
            }
        }
        if ($pengguna === null || ! password_verify((string) $payload['password'], $pengguna->kata_sandi)) {
            return $this->json(['ok' => false, 'message' => 'Username atau password tidak sesuai.'], 401);
        }

        session()->regenerate();
        $sessionUser = [
            'id' => (int) $pengguna->id,
            'nama_pengguna' => $pengguna->nama_pengguna,
            'nama_lengkap' => $payload['role']==='parent'?$child['nama_lengkap']:$pengguna->nama_lengkap,
            'peran' => $pengguna->peran,
            'nis' => $payload['role']==='parent'?$child['nis']:$pengguna->nis,
            'anak_id' => $payload['role']==='parent'?(int)$pengguna->siswa_id:null,
            'guru_id' => $pengguna->guru_id,
        ];
        session()->set('pengguna', $sessionUser);

        return $this->json(['ok' => true, 'user' => $this->frontendUser($sessionUser)]);
    }

    public function logout()
    {
        session()->remove('pengguna');
        session()->regenerate(true);

        return $this->json(['ok' => true]);
    }

    public function changePassword()
    {
        $user = session()->get('pengguna');
        if(($user['peran']??'')==='orang_tua'&&!\App\Libraries\ParentAccounts::sessionValid($user)){session()->remove('pengguna');return $this->json(['ok'=>false,'message'=>'Silakan masuk kembali.'],401);}
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $password = (string) ($payload['password'] ?? '');
        $confirmation = (string) ($payload['confirmation'] ?? '');
        if (! $user || (int) ($user['id'] ?? 0) < 1) return $this->json(['ok' => false, 'message' => 'Akun ini tidak dapat mengubah password melalui halaman ini.'], 422);
        if (strlen($password) < 8 || $password !== $confirmation) return $this->json(['ok' => false, 'message' => 'Password minimal 8 karakter dan konfirmasi harus sama.'], 422);
        $model = new PenggunaModel();
        $model->update((int) $user['id'], ['kata_sandi' => password_hash($password, PASSWORD_DEFAULT)]);
        return $this->json(['ok' => true, 'message' => 'Password berhasil diubah.']);
    }
    private function frontendUser(array $pengguna): array
    {
        $roleMap = ['wali_kelas' => 'walas', 'sekretaris' => 'assistant', 'siswa' => 'student', 'orang_tua' => 'student', 'guru' => 'teacher', 'guest' => 'guest'];

        return [
            'id' => (int) $pengguna['id'],
            'role' => $roleMap[$pengguna['peran']],
            'isParent' => $pengguna['peran']==='orang_tua',
            'accountRole' => $pengguna['peran']==='orang_tua'?'parent':$roleMap[$pengguna['peran']],
            'name' => ($pengguna['peran']==='orang_tua'?'Orangtua/Wali Murid ':'').$pengguna['nama_lengkap'],
            'username' => $pengguna['peran']==='orang_tua'?$pengguna['nis']:$pengguna['nama_pengguna'],
            'nis' => $pengguna['nis'],
        ];
    }

    private function json(array $data, int $status = 200)
    {
        $data['csrfHash'] = service('security')->getHash();
        return $this->response->setStatusCode($status)->setJSON($data);
    }
}
