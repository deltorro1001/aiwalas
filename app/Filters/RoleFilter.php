<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $pengguna = session()->get('pengguna');
        $roles = $arguments ?? [];

        if ($pengguna !== null && in_array($pengguna['peran'] ?? '', $roles, true)) {
            return null;
        }

        if ($request->isAJAX() || str_starts_with($request->getUri()->getPath(), '/api/')) {
            return service('response')->setStatusCode(403)->setJSON([
                'ok' => false,
                'message' => 'Anda tidak memiliki izin untuk mengakses fitur ini.',
                'csrfHash' => service('security')->getHash(),
            ]);
        }

        return redirect()->to(base_url('/'))->with('error', 'Akses ditolak.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}