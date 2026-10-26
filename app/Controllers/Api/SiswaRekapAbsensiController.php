<?php
namespace App\Controllers\Api;

class SiswaRekapAbsensiController extends BaseApiController
{
    public function index()
    {
        $zone=new \DateTimeZone('Asia/Jakarta');
        $now=new \DateTimeImmutable('now',$zone);
        $month=$this->request->getGet('bulan');$year=$this->request->getGet('tahun');
        if($month===null||$month==='')$month=$now->format('m');
        elseif(!is_string($month)||!preg_match('/^(0?[1-9]|1[0-2])$/D',$month))return $this->respond(['ok'=>false,'message'=>'Pilih bulan yang valid.'],422);
        if($year===null||$year==='')$year=$now->format('Y');
        elseif(!is_string($year)||!preg_match('/^[0-9]{4}$/D',$year)||(int)$year<2026||(int)$year>9998)return $this->respond(['ok'=>false,'message'=>'Pilih tahun yang valid mulai 2026.'],422);
        $month=sprintf('%04d-%02d',(int)$year,(int)$month);
        $db=db_connect();
        $student=$db->table('siswa')->select('id')->where(['nis'=>$this->currentUser()['nis']??'','aktif'=>1])->get()->getRowArray();
        if(!$student)return $this->respond(['ok'=>false,'message'=>'Profil siswa tidak ditemukan.'],404);
        $start=new \DateTimeImmutable($month.'-01 00:00:00',$zone);
        $end=$start->modify('+1 month');
        $semester=(int)$start->format('n')<=6?'Genap':'Ganjil';
        $rows=$db->table('absensi')->select('tanggal,waktu_scan,status')->where(['siswa_id'=>$student['id'],'semester'=>$semester])
            ->where('tanggal >=',$start->format('Y-m-d'))->where('tanggal <',$end->format('Y-m-d'))->orderBy('tanggal','ASC')->orderBy('id','ASC')->get()->getResultArray();
        $months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $this->response->setHeader('Cache-Control','no-store');
        return $this->respond(['data'=>$rows,'bulan'=>$month,'tahun'=>(int)$start->format('Y'),'nomor_bulan'=>(int)$start->format('n'),'jumlah_hari'=>(int)$start->format('t'),'label_bulan'=>$months[(int)$start->format('n')-1].' '.$start->format('Y')]);
    }
}
