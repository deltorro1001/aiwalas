<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class TautkanNotifikasiDenganPengajuan extends Migration
{
    public function up()
    {
        $this->db->query("UPDATE notifikasi n
            JOIN pengajuan_sakit p ON p.kelas_id=n.kelas_id
            JOIN siswa s ON s.id=p.siswa_id AND n.judul=CONCAT('Pengajuan Istirahat Sakit · ', s.nama_lengkap)
            SET n.jenis=CONCAT('Pengajuan Sakit #',p.id)
            WHERE n.jenis='Pengajuan Orangtua' AND n.isi LIKE CONCAT('Pengajuan istirahat sakit untuk ',s.nama_lengkap,' (',DATE_FORMAT(p.tanggal_mulai,'%d-%m-%Y'),'%')");
        $this->db->query("UPDATE notifikasi n
            JOIN pengajuan_izin p ON p.kelas_id=n.kelas_id
            JOIN siswa s ON s.id=p.siswa_id AND n.judul=CONCAT('Pengajuan Izin · ', s.nama_lengkap)
            SET n.jenis=CONCAT('Pengajuan Izin #',p.id)
            WHERE n.jenis='Pengajuan Orangtua' AND n.isi LIKE CONCAT('Pengajuan izin untuk ',s.nama_lengkap,' (',DATE_FORMAT(p.tanggal_mulai,'%d-%m-%Y'),'%')");
    }

    public function down()
    {
        $this->db->query("UPDATE notifikasi SET jenis='Pengajuan Orangtua' WHERE jenis LIKE 'Pengajuan Sakit #%'");
        $this->db->query("UPDATE notifikasi SET jenis='Pengajuan Orangtua' WHERE jenis LIKE 'Pengajuan Izin #%'");
    }
}
