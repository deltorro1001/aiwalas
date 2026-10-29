<?php
namespace App\Database\Migrations;

use App\Libraries\SchoolCalendar;
use CodeIgniter\Database\Migration;
use RuntimeException;

class KoreksiMenungguAbsensiJuliSeptember2026 extends Migration
{
    public const AUDIT_TABLE='audit_koreksi_absensi_jul_sep_2026';

    public function ensureAuditTable(): void
    {
        if($this->db->tableExists(self::AUDIT_TABLE,false))return;
        $this->db->query('CREATE TABLE '.self::AUDIT_TABLE.' (
            absensi_id BIGINT UNSIGNED PRIMARY KEY,
            status_sebelumnya VARCHAR(40) NULL,
            waktu_sebelumnya TIME NULL,
            dibuat_baru TINYINT(1) NOT NULL,
            dikoreksi_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function up()
    {
        $this->ensureAuditTable();
        $updated=0;$inserted=0;
        $zone=new \DateTimeZone('Asia/Jakarta');
        $date=new \DateTimeImmutable('2026-07-13',$zone);
        $end=new \DateTimeImmutable('2026-09-30',$zone);
        $this->db->transBegin();
        try{
            for(;$date<=$end;$date=$date->modify('+1 day')){
                $day=$date->format('Y-m-d');
                // Preserve all holiday/PJJ dates, including pending rows hidden by the calendar.
                if(SchoolCalendar::isHoliday($day)||SchoolCalendar::isPjj($day))continue;
                $rows=$this->db->query("SELECT id,status,waktu_scan FROM absensi WHERE tanggal=? AND status='Menunggu keterangan' FOR UPDATE",[$day])->getResultArray();
                foreach($rows as $row){
                    $this->db->table(self::AUDIT_TABLE)->ignore(true)->insert(['absensi_id'=>$row['id'],'status_sebelumnya'=>$row['status'],'waktu_sebelumnya'=>$row['waktu_scan'],'dibuat_baru'=>0]);
                    $this->db->table('absensi')->where(['id'=>$row['id'],'status'=>'Menunggu keterangan'])->update(['status'=>'Hadir','waktu_scan'=>'06:30:00']);
                    $updated+=$this->db->affectedRows();
                }
                // The daily view also labels missing records as pending. Fill only active
                // students in the relevant active school year, in the Ganjil semester.
                $missing=$this->db->query("SELECT s.id siswa_id,s.kelas_id,k.wali_kelas_id
                    FROM siswa s JOIN kelas k ON k.id=s.kelas_id
                    JOIN tahun_ajaran t ON t.id=k.tahun_ajaran_id
                    JOIN pengguna p ON p.id=k.wali_kelas_id
                    LEFT JOIN absensi a ON a.siswa_id=s.id AND a.kelas_id=k.id AND a.tanggal=? AND a.semester='Ganjil'
                    WHERE s.aktif=1 AND t.aktif=1 AND t.nama_tahun_ajaran IN ('2026/2027','2026-2027') AND a.id IS NULL
                    ORDER BY s.id FOR UPDATE",[$day])->getResultArray();
                foreach($missing as $row){
                    $this->db->table('absensi')->insert(['siswa_id'=>$row['siswa_id'],'kelas_id'=>$row['kelas_id'],'tanggal'=>$day,'semester'=>'Ganjil','status'=>'Hadir','waktu_scan'=>'06:30:00','dicatat_oleh'=>$row['wali_kelas_id'],'keterangan'=>'Koreksi massal Menunggu keterangan menjadi Hadir (13-07-2026 s.d. 30-09-2026).']);
                    $id=$this->db->insertID();
                    $this->db->table(self::AUDIT_TABLE)->insert(['absensi_id'=>$id,'status_sebelumnya'=>null,'waktu_sebelumnya'=>null,'dibuat_baru'=>1]);
                    $inserted++;
                }
            }
            if(!$this->db->transStatus())throw new RuntimeException('Koreksi absensi gagal; seluruh perubahan dibatalkan.');
            $this->db->transCommit();
        }catch(\Throwable $error){$this->db->transRollback();throw $error;}
        if(is_cli())\CodeIgniter\CLI\CLI::write('Attendance correction Jul-Sep 2026: '.$updated.' pending rows updated; '.$inserted.' missing school-day rows added at 06:30:00. Other statuses and holiday/PJJ dates preserved.');
    }

    public function down()
    {
        // Historical corrections are not automatically reversed. Keep the audit table
        // so later wali corrections cannot be overwritten by a migration rollback.
    }
}
