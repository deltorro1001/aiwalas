<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class TambahkanCakupanKelasNotifikasi extends Migration
{
    public function up()
    {
        $this->forge->addColumn('notifikasi', [
            'kelas_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'pengguna_id'],
        ]);
        $this->db->query('ALTER TABLE notifikasi ADD KEY indeks_notifikasi_kelas (kelas_id, dibuat_pada), ADD CONSTRAINT fk_notifikasi_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->db->query("INSERT INTO notifikasi (pengguna_id, kelas_id, judul, isi, jenis, sudah_dibaca, dibuat_pada)
            SELECT COALESCE(k.wali_kelas_id, p.pengguna_id), p.kelas_id,
                CONCAT('Pengajuan Istirahat Sakit · ', s.nama_lengkap),
                CONCAT('Pengajuan istirahat sakit untuk ', s.nama_lengkap, ' (', DATE_FORMAT(p.tanggal_mulai, '%d-%m-%Y'), IF(p.tanggal_selesai <> p.tanggal_mulai, CONCAT(' s.d. ', DATE_FORMAT(p.tanggal_selesai, '%d-%m-%Y')), ''), ').',
                    IF(COALESCE(p.keterangan, '') <> '', CONCAT(CHAR(10), 'Keterangan orangtua: ', p.keterangan), ''),
                    IF(COALESCE(p.nama_lampiran, '') <> '', CONCAT(CHAR(10), 'Surat sakit terlampir.'), '')),
                'Pengajuan Orangtua', 0, p.dibuat_pada
            FROM pengajuan_sakit p
            JOIN siswa s ON s.id = p.siswa_id
            JOIN kelas k ON k.id = p.kelas_id");
        $this->db->query("INSERT INTO notifikasi (pengguna_id, kelas_id, judul, isi, jenis, sudah_dibaca, dibuat_pada)
            SELECT COALESCE(k.wali_kelas_id, p.pengguna_id), p.kelas_id,
                CONCAT('Pengajuan Izin · ', s.nama_lengkap),
                CONCAT('Pengajuan izin untuk ', s.nama_lengkap, ' (', DATE_FORMAT(p.tanggal_mulai, '%d-%m-%Y'), IF(p.tanggal_selesai <> p.tanggal_mulai, CONCAT(' s.d. ', DATE_FORMAT(p.tanggal_selesai, '%d-%m-%Y')), ''), ') · ', p.jenis_izin, '.',
                    IF(COALESCE(p.keterangan, '') <> '', CONCAT(CHAR(10), 'Keterangan orangtua: ', p.keterangan), ''),
                    IF(COALESCE(p.nama_lampiran, '') <> '', CONCAT(CHAR(10), 'Surat izin terlampir.'), '')),
                'Pengajuan Orangtua', 0, p.dibuat_pada
            FROM pengajuan_izin p
            JOIN siswa s ON s.id = p.siswa_id
            JOIN kelas k ON k.id = p.kelas_id");
    }

    public function down()
    {
        $this->db->query('ALTER TABLE notifikasi DROP FOREIGN KEY fk_notifikasi_kelas, DROP INDEX indeks_notifikasi_kelas');
        $this->forge->dropColumn('notifikasi', 'kelas_id');
    }
}
