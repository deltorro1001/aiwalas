<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

/** Restore Friday slots omitted from the original seed, using the existing weekly timetable. */
class LengkapiJadwalJumat11pf1 extends Migration
{
    public function up()
    {
        $db=$this->db;
        $classes=$db->table('kelas k')->select('k.id')->join('tahun_ajaran t','t.id=k.tahun_ajaran_id')
            ->where(['k.nama_kelas'=>'11PF1','t.aktif'=>1,'t.semester'=>'Ganjil'])
            ->whereIn('t.nama_tahun_ajaran',['2026/2027','2026-2027'])->get()->getResultArray();
        $slots=[
            ['06:30:00','07:30:00','Jumat Pembiasaan',null],
            ['09:30:00','10:10:00','Bahasa Inggris','Ika Inayah'],
            ['10:25:00','11:45:00','Muatan Lokal','Englena Nastaria Purba'],
            ['13:00:00','15:00:00','Tata Kamera & Cahaya','Wahyu Lukman Hakim'],
        ];
        $db->transStart();
        foreach($classes as $class){
            foreach($slots as [$start,$end,$subjectName,$teacherName]){
                // Preserve any timetable edits and avoid duplicate or overlapping sessions.
                if($db->table('jadwal_kelas')->where(['kelas_id'=>$class['id'],'hari'=>'Jumat'])->where('jam_mulai <',$end)->where('jam_selesai >',$start)->countAllResults())continue;
                $teacherId=null;
                if($teacherName!==null){
                    $teachers=$db->table('guru')->select('id')->like('nama_lengkap',$teacherName,'after')->where('aktif',1)->get()->getResultArray();
                    if(count($teachers)!==1)throw new \RuntimeException('Guru jadwal Jumat tidak dapat ditentukan secara unik.');
                    $teacherId=$teachers[0]['id'];
                }
                $subject=$db->table('mata_pelajaran')->where('nama_mata_pelajaran',$subjectName)->get()->getRowArray();
                if(!$subject){$db->table('mata_pelajaran')->insert(['nama_mata_pelajaran'=>$subjectName,'aktif'=>1]);$subject=['id'=>$db->insertID()];}
                $db->table('jadwal_kelas')->insert(['kelas_id'=>$class['id'],'mata_pelajaran_id'=>$subject['id'],'guru_id'=>$teacherId,'hari'=>'Jumat','jam_mulai'=>$start,'jam_selesai'=>$end,'keterangan'=>$teacherName===null?'Aktivitas sekolah':null]);
            }
        }
        $db->transComplete();
        if(!$db->transStatus())throw new \RuntimeException('Jadwal Jumat gagal dilengkapi.');
    }
    public function down()
    {
        // Keep timetable data, including any subsequent edits by the wali kelas.
    }
}
