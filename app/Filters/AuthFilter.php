<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->has('pengguna')) {
            return null;
        }

        if ($request->isAJAX() || str_starts_with($request->getUri()->getPath(), '/api/')) {
            return service('response')->setStatusCode(401)->setJSON([
                'ok' => false,
                'message' => 'Silakan masuk terlebih dahulu.',
                'csrfHash' => service('security')->getHash(),
            ]);
        }

        return redirect()->to(base_url('/'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}