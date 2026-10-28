<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class BuatUploadFotoSiswa extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE foto_siswa (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            siswa_id INT UNSIGNED NOT NULL, kelas_id INT UNSIGNED NOT NULL,
            tanggal DATE NOT NULL, diunggah_pada DATETIME NOT NULL,
            keterangan TEXT NOT NULL, berkas VARCHAR(80) NOT NULL,
            lebar INT UNSIGNED NOT NULL, tinggi INT UNSIGNED NOT NULL,
            request_key CHAR(36) CHARACTER SET ascii NOT NULL,
            UNIQUE KEY unik_foto_request (siswa_id,request_key),
            KEY indeks_foto_harian (kelas_id,tanggal,id),
            CONSTRAINT fk_foto_siswa FOREIGN KEY(siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
            CONSTRAINT fk_foto_kelas FOREIGN KEY(kelas_id) REFERENCES kelas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE foto_kolase (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kelas_id INT UNSIGNED NOT NULL, tanggal DATE NOT NULL,
            snapshot CHAR(64) CHARACTER SET ascii NOT NULL,
            jumlah_foto INT UNSIGNED NOT NULL, dibuat_pada DATETIME NOT NULL,
            UNIQUE KEY unik_kolase_snapshot (kelas_id,tanggal,snapshot),
            CONSTRAINT fk_kolase_kelas FOREIGN KEY(kelas_id) REFERENCES kelas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE foto_kolase_halaman (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kolase_id BIGINT UNSIGNED NOT NULL, nomor INT UNSIGNED NOT NULL,
            berkas VARCHAR(80) NOT NULL, foto_ids TEXT NOT NULL,
            UNIQUE KEY unik_kolase_halaman (kolase_id,nomor),
            CONSTRAINT fk_halaman_kolase FOREIGN KEY(kolase_id) REFERENCES foto_kolase(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE foto_kolase_pengiriman (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            halaman_id BIGINT UNSIGNED NOT NULL, guru_id INT UNSIGNED NOT NULL,
            status VARCHAR(16) NOT NULL, result_note VARCHAR(255) NOT NULL,
            token_hash CHAR(64) CHARACTER SET ascii NOT NULL, berlaku_sampai DATETIME NOT NULL,
            dicoba_pada DATETIME NOT NULL, message_id BIGINT UNSIGNED NULL,
            UNIQUE KEY unik_kirim_kolase (halaman_id,guru_id), UNIQUE KEY unik_token_kolase (token_hash),
            CONSTRAINT fk_kirim_halaman FOREIGN KEY(halaman_id) REFERENCES foto_kolase_halaman(id) ON DELETE CASCADE,
            CONSTRAINT fk_kirim_guru FOREIGN KEY(guru_id) REFERENCES guru(id) ON DELETE CASCADE,
            CONSTRAINT fk_kirim_pesan FOREIGN KEY(message_id) REFERENCES pesan_komunikasi(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down()
    {
        foreach(['foto_kolase_pengiriman','foto_kolase_halaman','foto_kolase','foto_siswa'] as $table)$this->forge->dropTable($table,true);
    }
}
