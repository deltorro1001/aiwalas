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
        $klass=$db->table('kelas')->select('id,nama_kelas')->where(['id'=>(int)$class,'wali_kelas_id'=>(int)$this->currentUser()['id']])->get()->getRowArray();
        if(!$klass)return $this->respond(['message'=>'Kelas tidak dapat diakses.'],404);
        $empty=static fn()=>['sakit'=>0,'izin'=>0,'alpha'=>0,'terlambat'=>0];
        $months=[];for($month=1;$month<=12;$month++)$months[$month]=['bulan'=>$month]+$empty();
        $students=[];
        foreach($db->table('siswa')->select('id,nis,nama_lengkap')->where(['kelas_id'=>(int)$class,'aktif'=>1])->orderBy('nama_lengkap')->get()->getResultArray() as $row){
            $row['bulanan']=$months;$row['total']=$empty();$students[$row['id']]=$row;
        }
        if($students){
            $rows=$db->table('absensi')->select('siswa_id,MONTH(tanggal) AS bulan,status,COUNT(*) AS jumlah',false)
                ->where(['kelas_id'=>(int)$class,'semester'=>$semester])->where('tanggal >=',$year.'-01-01')->where('tanggal <',((int)$year+1).'-01-01')
                ->whereIn('siswa_id',array_keys($students))->whereIn('status',['Sakit','Izin','Alpha','Terlambat'])->groupBy('siswa_id,MONTH(tanggal),status')->get()->getResultArray();
            foreach($rows as $row){
                $key=['Sakit'=>'sakit','Izin'=>'izin','Alpha'=>'alpha','Terlambat'=>'terlambat'][$row['status']];
                $n=(int)$row['jumlah'];$m=(int)$row['bulan'];
                $students[$row['siswa_id']]['bulanan'][$m][$key]=$n;
                $students[$row['siswa_id']]['total'][$key]+=$n;$months[$m][$key]+=$n;
            }
        }
        $total=$empty();foreach($months as $row)foreach(array_keys($total) as $key)$total[$key]+=$row[$key];
        foreach($students as &$row)$row['bulanan']=array_values($row['bulanan']);unset($row);
        $this->response->setHeader('Cache-Control','private, no-store');
        return $this->respond(['tahun'=>(int)$year,'semester'=>$semester,'kelas'=>$klass,'bulanan'=>array_values($months),'total'=>$total,'siswa'=>array_values($students)]);
    }
}
