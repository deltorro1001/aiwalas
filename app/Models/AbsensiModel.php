<?php

namespace App\Models;

use App\Entities\Absensi;
use CodeIgniter\Model;

class AbsensiModel extends Model
{
    protected $table = 'absensi';
    protected $primaryKey = 'id';
    protected $returnType = Absensi::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'siswa_id',
        'kelas_id',
        'tanggal',
        'waktu_scan',
        'status',
        'keterangan',
        'dicatat_oleh',
        'dibuat_pada',
    ];
}
