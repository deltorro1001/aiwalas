<?php

namespace App\Models;

use App\Entities\Pengguna;
use CodeIgniter\Model;

class PenggunaModel extends Model
{
    protected $table = 'pengguna';
    protected $primaryKey = 'id';
    protected $returnType = Pengguna::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'nama_pengguna',
        'kata_sandi',
        'nama_lengkap',
        'peran',
        'nis',
        'aktif',
        'dibuat_pada',
        'diperbarui_pada',
    ];
}
