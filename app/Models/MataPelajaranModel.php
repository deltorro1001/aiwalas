<?php

namespace App\Models;

use App\Entities\MataPelajaran;
use CodeIgniter\Model;

class MataPelajaranModel extends Model
{
    protected $table = 'mata_pelajaran';
    protected $primaryKey = 'id';
    protected $returnType = MataPelajaran::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'nama_mata_pelajaran',
        'aktif',
    ];
}
