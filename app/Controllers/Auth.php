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

        return $this->json([
            'authenticated' => $pengguna !== null,
            'user' => $pengguna === null ? null : $this->frontendUser($pengguna),
        ]);
    }

    public function attempt()
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $rules = [
            'role' => 'required|in_list[walas,assistant,student,teacher,guest]',
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

        $roleMap = ['walas' => 'wali_kelas', 'assistant' => 'sekretaris', 'student' => 'siswa', 'teacher' => 'guru', 'guest' => 'guest'];
        if ($payload['role'] === 'teacher') {
            $needle = strtolower(trim((string)$payload['username']));
            $guru = db_connect()->table('guru')->where('aktif',1)->get()->getResultArray();
            foreach ($guru as $g) {
                $u = strtolower(preg_replace('/[^a-z0-9]/i','',preg_replace('/^(pak|bu|bapak|ibu)\s+/i','',trim((string)($g['nama_panggilan']??'')))));
                if ($u !== '' && $u === $needle && (string)$payload['password'] === $u) {
                    session()->regenerate(); session()->set('pengguna',['id'=>0,'guru_id'=>(int)$g['id'],'nama_pengguna'=>$u,'nama_lengkap'=>$g['nama_lengkap'],'peran'=>'guru','nis'=>null]);
                    return $this->json(['ok'=>true,'user'=>$this->frontendUser(['id'=>0,'nama_pengguna'=>$u,'nama_lengkap'=>$g['nama_lengkap'],'peran'=>'guru','nis'=>null])]);
                }
            }
        }
        $model = new PenggunaModel();
        $pengguna = $model->where('nama_pengguna', trim((string) $payload['username']))
            ->where('peran', $roleMap[$payload['role']])
            ->where('aktif', 1)
            ->first();

        // Akun siswa mengikuti kredensial NIS. Untuk data siswa lama yang
        // belum memiliki baris pengguna, buat akun otomatis saat login pertama.
        if ($pengguna === null && $payload['role'] === 'student') {
            $nis = trim((string) $payload['username']);
            $student = db_connect()->table('siswa')
                ->where('nis', $nis)
                ->where('aktif', 1)
                ->get()->getRowArray();
            if ($student && hash_equals($nis, (string) $payload['password'])) {
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

        if ($pengguna === null || ! password_verify((string) $payload['password'], $pengguna->kata_sandi)) {
            return $this->json(['ok' => false, 'message' => 'Username atau password tidak sesuai.'], 401);
        }

        session()->regenerate();
        $sessionUser = [
            'id' => (int) $pengguna->id,
            'nama_pengguna' => $pengguna->nama_pengguna,
            'nama_lengkap' => $pengguna->nama_lengkap,
            'peran' => $pengguna->peran,
            'nis' => $pengguna->nis,
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
        $roleMap = ['wali_kelas' => 'walas', 'sekretaris' => 'assistant', 'siswa' => 'student', 'guru' => 'teacher', 'guest' => 'guest'];

        return [
            'id' => (int) $pengguna['id'],
            'role' => $roleMap[$pengguna['peran']],
            'name' => $pengguna['nama_lengkap'],
            'username' => $pengguna['nama_pengguna'],
            'nis' => $pengguna['nis'],
        ];
    }

    private function json(array $data, int $status = 200)
    {
        $data['csrfHash'] = service('security')->getHash();
        return $this->response->setStatusCode($status)->setJSON($data);
    }
}
