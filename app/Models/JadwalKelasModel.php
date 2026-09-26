<?php

namespace App\Models;

use App\Entities\JadwalKelas;
use CodeIgniter\Model;

class JadwalKelasModel extends Model
{
    protected $table = 'jadwal_kelas';
    protected $primaryKey = 'id';
    protected $returnType = JadwalKelas::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'kelas_id',
        'mata_pelajaran_id',
        'guru_id',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'keterangan',
    ];
}
