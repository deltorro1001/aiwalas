<?php
namespace App\Controllers\Api;

class RekapAbsenKelasController extends BaseApiController
{
    public function index()
    {
        $now=new \DateTimeImmutable('now',new \DateTimeZone('Asia/Jakarta'));
        $year=$this->request->getGet('tahun')??$now->format('Y');
        $class=$this->request->getGet('kelas_id');
        $semester=$this->request->getGet('semester')??'Ganjil';
        if(!is_string($year)||!preg_match('/^[0-9]{4}$/D',$year)||(int)$year<2026||(int)$year>9998)return $this->respond(['message'=>'Pilih tahun yang valid mulai 2026.'],422);
        if(!is_string($class)||!preg_match('/^[1-9][0-9]*$/D',$class))return $this->respond(['message'=>'Kelas wajib dipilih.'],422);
        if(!in_array($semester,['Ganjil','Genap'],true))return $this->respond(['message'=>'Semester tidak valid.'],422);
        $db=db_connect();
        $user=$this->currentUser();
        $query=$db->table('kelas')->select('id,nama_kelas')->where('id',(int)$class);
        if(($user['peran']??'')==='sekretaris'){
            $own=$db->table('siswa')->where(['nis'=>$user['nis']??'','kelas_id'=>(int)$class,'aktif'=>1])->countAllResults();
            if(!$own)return $this->respond(['message'=>'Kelas tidak dapat diakses.'],404);
        }elseif(($user['peran']??'')==='guru'){
            if(!\App\Libraries\TeacherAssignments::has((int)$class,(int)($user['guru_id']??0)))return $this->respond(['message'=>'Kelas tidak dapat diakses.'],404);
        }else $query->where('wali_kelas_id',(int)$user['id']);
        $klass=$query->get()->getRowArray();
        if(!$klass)return $this->respond(['message'=>'Kelas tidak dapat diakses.'],404);
        $this->response->setHeader('Cache-Control','private, no-store');
        return $this->respond(\App\Libraries\ClassAttendanceRecap::build($klass,(int)$year,$semester,$db));
    }
}
