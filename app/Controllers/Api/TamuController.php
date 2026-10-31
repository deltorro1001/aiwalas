<?php
namespace App\Controllers\Api;
use App\Libraries\SchoolCalendar;
class TamuController extends BaseApiController
{
    public function index()
    {
        $db=db_connect();$now=new \DateTimeImmutable('now',new \DateTimeZone('Asia/Jakarta'));
        $classes=$db->table('kelas k')->select('k.id,k.nama_kelas,t.nama_tahun_ajaran')->join('tahun_ajaran t','t.id=k.tahun_ajaran_id')->where('t.aktif',1)->orderBy('k.nama_kelas')->orderBy('k.id')->get()->getResultArray();
        $selected=$this->request->getGet('kelas_id');
        if($selected!==null&&(!is_string($selected)||!preg_match('/^[1-9][0-9]*$/D',$selected)))return $this->respond(['message'=>'Kelas tidak valid.'],422);
        $class=null;foreach($classes as $row)if($selected===null||$row['id']===$selected||(int)$row['id']===(int)$selected){$class=$row;break;}
        if($selected!==null&&!$class)return $this->respond(['message'=>'Kelas tidak tersedia.'],404);
        $month=$this->request->getGet('bulan')??$now->format('m');$year=$this->request->getGet('tahun')??$now->format('Y');
        if(!is_string($month)||!preg_match('/^(0?[1-9]|1[0-2])$/D',$month)||!is_string($year)||!preg_match('/^[0-9]{4}$/D',$year)||(int)$year<2026||(int)$year>9998)return $this->respond(['message'=>'Pilih bulan dan tahun yang valid mulai 2026.'],422);
        $date=$now->format('Y-m-d');$semester=(int)$now->format('n')<=6?'Genap':'Ganjil';$schedule=[];$announcements=[];$counts=[];$total=0;
        if($class){
            $students=$db->table('siswa')->select('id')->where(['kelas_id'=>$class['id'],'aktif'=>1])->get()->getResultArray();$total=count($students);
            $actual=$total?$db->table('absensi')->select('status,COUNT(*) jumlah')->where(['kelas_id'=>$class['id'],'tanggal'=>$date,'semester'=>$semester])->whereIn('siswa_id',array_column($students,'id'))->groupBy('status')->get()->getResultArray():[];
            $calendar=SchoolCalendar::info($now);
            if($calendar['status']){if($total)$counts[$calendar['status']]=$total;}
            else{foreach($actual as $row)$counts[$row['status']]=(int)$row['jumlah'];$missing=$total-array_sum($counts);if($missing)$counts['Menunggu keterangan']=($counts['Menunggu keterangan']??0)+$missing;}
            $schedule=$db->table('jadwal_kelas j')->select('j.hari,j.jam_mulai,j.jam_selesai,m.nama_mata_pelajaran,g.nama_lengkap AS nama_guru')->join('mata_pelajaran m','m.id=j.mata_pelajaran_id')->join('guru g','g.id=j.guru_id','left')->where('j.kelas_id',$class['id'])->whereIn('j.hari',['Senin','Selasa','Rabu','Kamis','Jumat'])->orderBy('j.jam_mulai')->get()->getResultArray();
            $announcements=$db->table('pengumuman_tamu')->select('id,judul,isi,tanggal_agenda,dibuat_pada')->where(['kelas_id'=>$class['id'],'terbit'=>1])->orderBy('id','DESC')->get()->getResultArray();
        }
        $start=new \DateTimeImmutable(sprintf('%04d-%02d-01',(int)$year,(int)$month),new \DateTimeZone('Asia/Jakarta'));$days=[];
        for($day=$start;$day<$start->modify('+1 month');$day=$day->modify('+1 day')){
            $entry=SchoolCalendar::info($day);$entry['agenda']=[];
            foreach($announcements as $item)if($item['tanggal_agenda']===$entry['tanggal'])$entry['agenda'][]=$item['judul'];
            $days[]=$entry;
        }
        $this->response->setHeader('Cache-Control','private, no-store');
        return $this->respond(['sekolah'=>'SMK Negeri 19 Jakarta','kelas'=>$class,'daftar_kelas'=>$classes,'tanggal'=>$date,'jumlah_siswa'=>$total,'kehadiran'=>$counts,'jadwal'=>$schedule,'pengumuman'=>$announcements,'kalender'=>$days,'bulan'=>(int)$month,'tahun'=>(int)$year]);
    }
}
