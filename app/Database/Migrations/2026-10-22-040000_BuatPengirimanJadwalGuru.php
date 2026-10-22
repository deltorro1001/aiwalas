<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class BuatPengirimanJadwalGuru extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE teacher_schedule_dispatches (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            claim_token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            schedule_id INT UNSIGNED NOT NULL,
            kelas_id INT UNSIGNED NOT NULL,
            guru_id INT UNSIGNED NOT NULL,
            lesson_date DATE NOT NULL,
            event_type ENUM('reminder','thanks') NOT NULL,
            due_at DATETIME NOT NULL,
            attempted_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            status VARCHAR(16) NOT NULL,
            message_id BIGINT UNSIGNED NULL,
            result_note VARCHAR(255) NULL,
            UNIQUE KEY unik_sesi_pesan (session_key),
            KEY indeks_dispatch_kelas (kelas_id,lesson_date),
            KEY indeks_dispatch_status (status,due_at),
            CONSTRAINT fk_dispatch_pesan FOREIGN KEY (message_id) REFERENCES pesan_komunikasi(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE teacher_schedule_worker (
            id TINYINT UNSIGNED PRIMARY KEY,
            last_started DATETIME NOT NULL,
            last_finished DATETIME NULL,
            last_status VARCHAR(30) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(){ $this->forge->dropTable('teacher_schedule_worker',true);$this->forge->dropTable('teacher_schedule_dispatches',true); }
}
