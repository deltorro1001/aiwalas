<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class BuatAntreanNotifikasiNilaiSiswa extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE student_grade_dispatches (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            siswa_id INT UNSIGNED NOT NULL,
            komponen_nilai_id INT UNSIGNED NOT NULL,
            semester VARCHAR(10) NOT NULL,
            nilai DECIMAL(5,2) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            attempted_at DATETIME NULL,
            message_id BIGINT UNSIGNED NULL,
            result_note VARCHAR(255) NULL,
            KEY indeks_grade_pending (status,id),
            KEY indeks_grade_revision (komponen_nilai_id,siswa_id,semester),
            UNIQUE KEY unik_grade_message (message_id),
            CONSTRAINT fk_grade_dispatch_student FOREIGN KEY(siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
            CONSTRAINT fk_grade_dispatch_component FOREIGN KEY(komponen_nilai_id) REFERENCES komponen_nilai(id) ON DELETE CASCADE,
            CONSTRAINT fk_grade_dispatch_message FOREIGN KEY(message_id) REFERENCES pesan_komunikasi(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(){ $this->forge->dropTable('student_grade_dispatches',true); }
}
