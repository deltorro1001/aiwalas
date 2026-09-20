<?php

namespace App\Models;

use App\Entities\NilaiSiswa;
use CodeIgniter\Model;

class NilaiSiswaModel extends Model
{
    protected $table = 'nilai_siswa';
    protected $primaryKey = 'id';
    protected $returnType = NilaiSiswa::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'siswa_id',
        'komponen_nilai_id',
        'nilai',
        'keterangan',
        'dinilai_oleh',
        'dibuat_pada',
        'diperbarui_pada',
    ];
}
