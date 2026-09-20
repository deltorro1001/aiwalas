<?php

namespace App\Controllers;

use App\Models\PenggunaModel;

class Auth extends BaseController
{
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
            'role' => 'required|in_list[walas,assistant,student,guest]',
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

        $roleMap = ['walas' => 'wali_kelas', 'assistant' => 'sekretaris', 'student' => 'siswa', 'guest' => 'guest'];
        $model = new PenggunaModel();
        $pengguna = $model->where('nama_pengguna', trim((string) $payload['username']))
            ->where('peran', $roleMap[$payload['role']])
            ->where('aktif', 1)
            ->first();

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

    private function frontendUser(array $pengguna): array
    {
        $roleMap = ['wali_kelas' => 'walas', 'sekretaris' => 'assistant', 'siswa' => 'student', 'guest' => 'guest'];

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