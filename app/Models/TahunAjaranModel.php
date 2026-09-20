<?php

namespace App\Models;

use App\Entities\TahunAjaran;
use CodeIgniter\Model;

class TahunAjaranModel extends Model
{
    protected $table = 'tahun_ajaran';
    protected $primaryKey = 'id';
    protected $returnType = TahunAjaran::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'nama_tahun_ajaran',
        'semester',
        'aktif',
        'dibuat_pada',
    ];
}
