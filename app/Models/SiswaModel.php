<?php

namespace App\Models;

use App\Entities\Siswa;
use CodeIgniter\Model;

class SiswaModel extends Model
{
    protected $table = 'siswa';
    protected $primaryKey = 'id';
    protected $returnType = Siswa::class;
    protected $protectFields = true;
    protected $allowedFields = [
        'nis',
        'nisn',
        'nama_lengkap',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'alamat_lengkap',
        'status_tempat_tinggal',
        'transportasi',
        'nama_ayah',
        'nama_ibu',
        'nama_wali',
        'hubungan_wali',
        'kerabat_yang_bisa_dihubungi',
        'jumlah_saudara',
        'anak_ke',
        'pekerjaan_ayah',
        'pekerjaan_ibu',
        'menerima_bantuan',
        'memiliki_penyakit',
        'catatan_penyakit',
        'hobi',
        'prestasi',
        'kegiatan_ekskul',
        'kelas_id',
        'nomor_hp',
        'nomor_hp_orang_tua',
        'aktif',
        'dibuat_pada',
        'diperbarui_pada',
    ];
}
