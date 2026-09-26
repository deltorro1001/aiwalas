<?php

namespace App\Models;

use App\Entities\Notifikasi;
use CodeIgniter\Model;

class NotifikasiModel extends Model
{
    protected $table = 'notifikasi';
    protected $primaryKey = 'id';
    protected $returnType = Notifikasi::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'pengguna_id',
        'judul',
        'isi',
        'jenis',
        'sudah_dibaca',
        'dibuat_pada',
    ];
}
