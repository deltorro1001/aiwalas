<?php

namespace App\Models;

use App\Entities\RemedialTugas;
use CodeIgniter\Model;

class RemedialTugasModel extends Model
{
    protected $table = 'remedial_tugas';
    protected $primaryKey = 'id';
    protected $returnType = RemedialTugas::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'kelas_id',
        'siswa_id',
        'mata_pelajaran_id',
        'judul',
        'deskripsi',
        'batas_waktu',
        'status',
        'dibuat_oleh',
        'dibuat_pada',
    ];
}
