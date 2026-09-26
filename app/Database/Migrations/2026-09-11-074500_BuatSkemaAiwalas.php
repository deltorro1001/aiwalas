<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BuatSkemaAiwalas extends Migration
{
    public function up()
    {
        $this->db->disableForeignKeyChecks();

        foreach ($this->statements() as $sql) {
            $this->db->query($sql);
        }

        $this->db->enableForeignKeyChecks();
    }

    public function down()
    {
        $this->db->disableForeignKeyChecks();

        foreach (['notifikasi', 'remedial_tugas', 'pesan_komunikasi', 'nilai_siswa', 'komponen_nilai', 'jurnal_kelas', 'absensi', 'jadwal_kelas', 'mata_pelajaran', 'guru', 'siswa', 'kelas', 'pengguna', 'tahun_ajaran'] as $table) {
            $this->forge->dropTable($table, true);
        }

        $this->db->enableForeignKeyChecks();
    }

    private function statements(): array
    {
        return [
            <<<'SQL'
CREATE TABLE tahun_ajaran (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nama_tahun_ajaran VARCHAR(20) NOT NULL,
 semester ENUM('Ganjil','Genap') NOT NULL,
 aktif TINYINT(1) NOT NULL DEFAULT 0,
 dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unik_tahun_semester (nama_tahun_ajaran, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE pengguna (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nama_pengguna VARCHAR(100) NOT NULL,
 kata_sandi VARCHAR(255) NOT NULL,
 nama_lengkap VARCHAR(180) NOT NULL,
 peran ENUM('wali_kelas','sekretaris','siswa','guest') NOT NULL,
 nis VARCHAR(30) NULL,
 aktif TINYINT(1) NOT NULL DEFAULT 1,
 dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 diperbarui_pada DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY unik_nama_pengguna (nama_pengguna),
 UNIQUE KEY unik_pengguna_nis (nis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE kelas (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nama_kelas VARCHAR(50) NOT NULL,
 tingkat TINYINT UNSIGNED NOT NULL,
 jurusan VARCHAR(100) NOT NULL,
 tahun_ajaran_id INT UNSIGNED NOT NULL,
 wali_kelas_id INT UNSIGNED NULL,
 UNIQUE KEY unik_kelas_tahun (nama_kelas, tahun_ajaran_id),
 KEY indeks_kelas_tahun (tahun_ajaran_id),
 KEY indeks_wali_kelas (wali_kelas_id),
 CONSTRAINT fk_kelas_tahun FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_kelas_wali FOREIGN KEY (wali_kelas_id) REFERENCES pengguna(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE siswa (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nis VARCHAR(30) NOT NULL,
 nisn VARCHAR(30) NULL,
 nama_lengkap VARCHAR(180) NOT NULL,
 jenis_kelamin ENUM('L','P') NOT NULL,
 tempat_lahir VARCHAR(100) NULL,
 tanggal_lahir DATE NULL,
 agama VARCHAR(40) NULL,
 alamat_lengkap TEXT NULL,
 status_tempat_tinggal VARCHAR(100) NULL,
 transportasi VARCHAR(100) NULL,
 nama_ayah VARCHAR(180) NULL,
 nama_ibu VARCHAR(180) NULL,
 nama_wali VARCHAR(180) NULL,
 hubungan_wali VARCHAR(100) NULL,
 kerabat_yang_bisa_dihubungi VARCHAR(180) NULL,
 jumlah_saudara TINYINT UNSIGNED NULL,
 anak_ke TINYINT UNSIGNED NULL,
 pekerjaan_ayah VARCHAR(120) NULL,
 pekerjaan_ibu VARCHAR(120) NULL,
 menerima_bantuan TINYINT(1) NOT NULL DEFAULT 0,
 memiliki_penyakit TINYINT(1) NOT NULL DEFAULT 0,
 catatan_penyakit TEXT NULL,
 hobi TEXT NULL,
 prestasi TEXT NULL,
 kelas_id INT UNSIGNED NOT NULL,
 nomor_hp VARCHAR(30) NULL,
 nomor_hp_orang_tua VARCHAR(30) NULL,
 aktif TINYINT(1) NOT NULL DEFAULT 1,
 dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 diperbarui_pada DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY unik_siswa_nis (nis),
 UNIQUE KEY unik_siswa_nisn (nisn),
 KEY indeks_siswa_kelas (kelas_id),
 CONSTRAINT fk_siswa_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE guru (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nama_lengkap VARCHAR(180) NOT NULL,
 nama_panggilan VARCHAR(100) NULL,
 jenis_kelamin ENUM('L','P') NULL,
 nip_nikki VARCHAR(60) NULL,
 nrk VARCHAR(60) NULL,
 nuptk VARCHAR(60) NULL,
 nik VARCHAR(60) NULL,
 tempat_tanggal_lahir VARCHAR(180) NULL,
 jabatan VARCHAR(150) NULL,
 mata_pelajaran VARCHAR(180) NULL,
 alamat TEXT NULL,
 nomor_hp VARCHAR(30) NULL,
 email VARCHAR(150) NULL,
 aktif TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY unik_guru_nip_nikki (nip_nikki),
 KEY indeks_nama_guru (nama_lengkap)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE mata_pelajaran (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nama_mata_pelajaran VARCHAR(150) NOT NULL,
 aktif TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY unik_mata_pelajaran (nama_mata_pelajaran)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE jadwal_kelas (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 kelas_id INT UNSIGNED NOT NULL,
 mata_pelajaran_id INT UNSIGNED NOT NULL,
 guru_id INT UNSIGNED NOT NULL,
 hari ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') NOT NULL,
 jam_mulai TIME NOT NULL,
 jam_selesai TIME NOT NULL,
 keterangan VARCHAR(255) NULL,
 KEY indeks_jadwal_kelas_hari (kelas_id, hari),
 CONSTRAINT fk_jadwal_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_jadwal_mapel FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_jadwal_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE absensi (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 siswa_id INT UNSIGNED NOT NULL,
 kelas_id INT UNSIGNED NOT NULL,
 tanggal DATE NOT NULL,
 waktu_scan TIME NULL,
 status ENUM('Hadir','Terlambat','Sakit','Izin','Alpha','Menunggu keterangan') NOT NULL,
 keterangan TEXT NULL,
 dicatat_oleh INT UNSIGNED NOT NULL,
 dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unik_absensi_harian (siswa_id, kelas_id, tanggal),
 KEY indeks_absensi_kelas_tanggal (kelas_id, tanggal),
 CONSTRAINT fk_absensi_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_absensi_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_absensi_pencatat FOREIGN KEY (dicatat_oleh) REFERENCES pengguna(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE jurnal_kelas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 kelas_id INT UNSIGNED NOT NULL,
 mata_pelajaran_id INT UNSIGNED NOT NULL,
 guru_id INT UNSIGNED NOT NULL,
 tanggal DATE NOT NULL,
 materi TEXT NOT NULL,
 catatan TEXT NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'Draft',
 dibuat_oleh INT UNSIGNED NOT NULL,
 dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY indeks_jurnal_kelas_tanggal (kelas_id, tanggal),
 CONSTRAINT fk_jurnal_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_jurnal_mapel FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_jurnal_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_jurnal_pembuat FOREIGN KEY (dibuat_oleh) REFERENCES pengguna(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE komponen_nilai (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 kelas_id INT UNSIGNED NOT NULL,
 mata_pelajaran_id INT UNSIGNED NOT NULL,
 nama_komponen VARCHAR(100) NOT NULL,
 jenis_komponen ENUM('UH','Tugas') NOT NULL,
 urutan SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 bobot DECIMAL(5,2) NOT NULL DEFAULT 0,
 aktif TINYINT(1) NOT NULL DEFAULT 1,
 dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unik_komponen (kelas_id, mata_pelajaran_id, nama_komponen),
 KEY indeks_komponen_urutan (kelas_id, mata_pelajaran_id, urutan),
 CONSTRAINT fk_komponen_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_komponen_mapel FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE nilai_siswa (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 siswa_id INT UNSIGNED NOT NULL,
 komponen_nilai_id INT UNSIGNED NOT NULL,
 nilai DECIMAL(5,2) NOT NULL,
 keterangan TEXT NULL,
 dinilai_oleh INT UNSIGNED NOT NULL,
 dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 diperbarui_pada DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY unik_nilai_siswa_komponen (siswa_id, komponen_nilai_id),
 CONSTRAINT fk_nilai_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_nilai_komponen FOREIGN KEY (komponen_nilai_id) REFERENCES komponen_nilai(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_nilai_penilai FOREIGN KEY (dinilai_oleh) REFERENCES pengguna(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT cek_rentang_nilai CHECK (nilai >= 0 AND nilai <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE pesan_komunikasi (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 siswa_id INT UNSIGNED NULL,
 kelas_id INT UNSIGNED NOT NULL,
 pengirim VARCHAR(180) NOT NULL,
 penerima VARCHAR(180) NOT NULL,
 nomor_tujuan VARCHAR(30) NOT NULL,
 isi_pesan TEXT NOT NULL,
 kategori VARCHAR(80) NULL,
 status_pengiriman ENUM('Disiapkan','Dibuka','Terkirim','Gagal') NOT NULL DEFAULT 'Disiapkan',
 dikirim_pada DATETIME NULL,
 dibuat_oleh INT UNSIGNED NOT NULL,
 KEY indeks_pesan_kelas (kelas_id, dibuat_oleh),
 CONSTRAINT fk_pesan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_pesan_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_pesan_pembuat FOREIGN KEY (dibuat_oleh) REFERENCES pengguna(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE remedial_tugas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 kelas_id INT UNSIGNED NOT NULL,
 siswa_id INT UNSIGNED NULL,
 mata_pelajaran_id INT UNSIGNED NOT NULL,
 judul VARCHAR(180) NOT NULL,
 deskripsi TEXT NULL,
 batas_waktu DATETIME NULL,
 status VARCHAR(50) NOT NULL DEFAULT 'Draft',
 dibuat_oleh INT UNSIGNED NOT NULL,
 dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY indeks_remedial_kelas_status (kelas_id, status),
 CONSTRAINT fk_remedial_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_remedial_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_remedial_mapel FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_remedial_pembuat FOREIGN KEY (dibuat_oleh) REFERENCES pengguna(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE notifikasi (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pengguna_id INT UNSIGNED NOT NULL,
 judul VARCHAR(180) NOT NULL,
 isi TEXT NOT NULL,
 jenis VARCHAR(80) NOT NULL,
 sudah_dibaca TINYINT(1) NOT NULL DEFAULT 0,
 dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY indeks_notifikasi_pengguna (pengguna_id, sudah_dibaca, dibuat_pada),
 CONSTRAINT fk_notifikasi_pengguna FOREIGN KEY (pengguna_id) REFERENCES pengguna(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        ];
    }
}