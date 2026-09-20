<?php

namespace App\Models;

use App\Entities\KomponenNilai;
use CodeIgniter\Model;

class KomponenNilaiModel extends Model
{
    protected $table = 'komponen_nilai';
    protected $primaryKey = 'id';
    protected $returnType = KomponenNilai::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'kelas_id',
        'mata_pelajaran_id',
        'nama_komponen',
        'jenis_komponen',
        'urutan',
        'bobot',
        'aktif',
        'dibuat_pada',
    ];
}
