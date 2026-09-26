<?php

namespace App\Models;

use App\Entities\PesanKomunikasi;
use CodeIgniter\Model;

class PesanKomunikasiModel extends Model
{
    protected $table = 'pesan_komunikasi';
    protected $primaryKey = 'id';
    protected $returnType = PesanKomunikasi::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'siswa_id',
        'kelas_id',
        'pengirim',
        'penerima',
        'nomor_tujuan',
        'isi_pesan',
        'kategori',
        'status_pengiriman',
        'dikirim_pada',
        'dibuat_oleh',
    ];
}
