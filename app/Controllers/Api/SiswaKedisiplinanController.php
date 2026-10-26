<?php
namespace App\Controllers\Api;
use App\Libraries\DisciplineRecords;

class SiswaKedisiplinanController extends BaseApiController
{
    public function index()
    {
        $student=db_connect()->table('siswa')->select('id,kelas_id')->where(['nis'=>$this->currentUser()['nis']??'','aktif'=>1])->get()->getRowArray();
        if(!$student)return $this->respond(['ok'=>false,'message'=>'Profil siswa tidak ditemukan.'],404);
        $rows=(new DisciplineRecords())->forClass((int)$student['kelas_id'],(int)$student['id']);
        if(!$rows)return $this->respond(['ok'=>false,'message'=>'Profil siswa tidak ditemukan.'],404);
        $source=$rows[0];
        $records=[];
        foreach(['etika'=>['pelanggaran','tanggal','saksi','tindakan'],'terlambat'=>['jumlah_terlambat','tanggal','alasan'],'atribut'=>['pelanggaran','perbaikan']] as $kind=>$fields){
            foreach($fields as $field)$records[$kind][$field]=$source['records'][$kind][$field]??null;
        }
        $this->response->setHeader('Cache-Control','no-store');
        return $this->respond(['data'=>['student'=>['nama'=>$source['nama_lengkap'],'nis'=>$source['nis']],'records'=>$records,'keterlambatan'=>$source['keterlambatan'],'kategori_bercatatan'=>array_column($source['catatan'],'kategori')]]);
    }
}
