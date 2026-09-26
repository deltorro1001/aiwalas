<?php

namespace App\Models;

use App\Entities\Guru;
use CodeIgniter\Model;

class GuruModel extends Model
{
    protected $table = 'guru';
    protected $primaryKey = 'id';
    protected $returnType = Guru::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'urutan',
        'nama_lengkap',
        'nama_panggilan',
        'jenis_kelamin',
        'nip_nikki',
        'nrk',
        'nuptk',
        'nik',
        'tempat_tanggal_lahir',
        'jabatan',
        'mata_pelajaran',
        'mapel_kelas',
        'alamat',
        'nomor_hp',
        'email',
        'aktif',
    ];
}
