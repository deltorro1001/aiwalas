<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

abstract class BaseApiController extends BaseController
{
    protected function payload(): array
    {
        return $this->request->getJSON(true) ?? $this->request->getPost();
    }

    protected function respond(array $data, int $status = 200)
    {
        $data['csrfHash'] = service('security')->getHash();
        return $this->response->setStatusCode($status)->setJSON($data);
    }

    protected function validatePayload(array $payload, array $rules): ?array
    {
        $validation = service('validation');
        $validation->setRules($rules);
        return $validation->run($payload) ? null : $validation->getErrors();
    }

    protected function currentUser(): array
    {
        return session()->get('pengguna') ?? [];
    }
}