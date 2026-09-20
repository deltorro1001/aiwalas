<?php

namespace App\Models;

use App\Entities\Kelas;
use CodeIgniter\Model;

class KelasModel extends Model
{
    protected $table = 'kelas';
    protected $primaryKey = 'id';
    protected $returnType = Kelas::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'nama_kelas',
        'tingkat',
        'jurusan',
        'tahun_ajaran_id',
        'wali_kelas_id',
    ];
}
