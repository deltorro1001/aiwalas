<?php

namespace App\Models;

use App\Entities\JurnalKelas;
use CodeIgniter\Model;

class JurnalKelasModel extends Model
{
    protected $table = 'jurnal_kelas';
    protected $primaryKey = 'id';
    protected $returnType = JurnalKelas::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'kelas_id',
        'mata_pelajaran_id',
        'guru_id',
        'tanggal',
        'materi',
        'catatan',
        'status',
        'dibuat_oleh',
        'dibuat_pada',
    ];
}
