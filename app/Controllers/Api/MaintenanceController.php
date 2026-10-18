<?php
namespace App\Controllers\Api;

class MaintenanceController extends BaseApiController
{
    public function upload(){return $this->disabled();}
    public function apply(){return $this->disabled();}
    private function disabled()
    {
        return $this->respond(['ok'=>false,'message'=>'Patch ZIP melalui web dinonaktifkan. Gunakan deployment Git.'],403);
    }
}
