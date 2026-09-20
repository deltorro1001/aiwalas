<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Pengguna extends Entity
{
    protected $dates = ['dibuat_pada', 'diperbarui_pada', 'tanggal', 'tanggal_lahir', 'dikirim_pada', 'batas_waktu'];
}
