<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class LengkapiJadwalSelasaRabu11pf1 extends Migration
{
    public function up()
    {
        $db=$this->db;
        $classes=$db->table('kelas k')->select('k.id')->join('tahun_ajaran t','t.id=k.tahun_ajaran_id')
            ->where(['k.nama_kelas'=>'11PF1','t.aktif'=>1,'t.semester'=>'Ganjil'])
            ->whereIn('t.nama_tahun_ajaran',['2026/2027','2026-2027'])->get()->getResultArray();
        // Match the existing weekly timetable in app.js, including the 08:15 break.
        $slots=[
            ['Selasa','06:30:00','08:15:00','Bahasa Indonesia','Erna Cahyani'],
            ['Selasa','09:00:00','10:45:00','Tata Kamera & Cahaya','Wahyu Lukman Hakim'],
            ['Selasa','10:45:00','12:15:00','PPKN','Ngatman'],
            ['Selasa','12:45:00','15:00:00','Manajemen Produksi','Sumantoro Kasdhani'],
            ['Rabu','06:30:00','08:15:00','Tata Kamera & Cahaya','Wahyu Lukman Hakim'],
            ['Rabu','09:00:00','10:45:00','Olahraga','Putu Arya Ranesda'],
            ['Rabu','10:45:00','12:15:00','Tata Kamera & Cahaya','Wahyu Lukman Hakim'],
            ['Rabu','12:45:00','15:00:00','Editing Film','Ruby Eka Prawira'],
        ];
        $db->transBegin();
        try{
            foreach($classes as $class){
                foreach($slots as [$day,$start,$end,$subjectName,$teacherName]){
                    $subject=$db->table('mata_pelajaran')->where('nama_mata_pelajaran',$subjectName)->get()->getRowArray();
                    $teachers=$db->table('guru')->select('id')->like('nama_lengkap',$teacherName,'after')->where('aktif',1)->get()->getResultArray();
                    if(count($teachers)!==1)throw new \RuntimeException('Guru jadwal Selasa/Rabu tidak dapat ditentukan secara unik.');
                    $teacherId=$teachers[0]['id'];
                    // Correct only the exact 06:30–09:00 legacy seed rows; preserve other edits.
                    if($subject&&$start==='06:30:00'){
                        $db->table('jadwal_kelas')->where(['kelas_id'=>$class['id'],'hari'=>$day,'mata_pelajaran_id'=>$subject['id'],'guru_id'=>$teacherId,'jam_mulai'=>$start,'jam_selesai'=>'09:00:00'])
                            ->groupStart()->where('keterangan',null)->orWhere('keterangan','')->groupEnd()->update(['jam_selesai'=>$end]);
                    }
                    if($db->table('jadwal_kelas')->where(['kelas_id'=>$class['id'],'hari'=>$day])->where('jam_mulai <',$end)->where('jam_selesai >',$start)->countAllResults())continue;
                    if(!$subject){$db->table('mata_pelajaran')->insert(['nama_mata_pelajaran'=>$subjectName,'aktif'=>1]);$subject=['id'=>$db->insertID()];}
                    $db->table('jadwal_kelas')->insert(['kelas_id'=>$class['id'],'mata_pelajaran_id'=>$subject['id'],'guru_id'=>$teacherId,'hari'=>$day,'jam_mulai'=>$start,'jam_selesai'=>$end]);
                }
            }
            if(!$db->transStatus())throw new \RuntimeException('Jadwal Selasa/Rabu gagal dilengkapi.');
            $db->transCommit();
        }catch(\Throwable $error){$db->transRollback();throw $error;}
    }
    public function down()
    {
        // Retain restored timetable data and subsequent wali kelas edits.
    }
}
