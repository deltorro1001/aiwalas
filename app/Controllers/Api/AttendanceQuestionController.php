<?php
namespace App\Controllers\Api;
class AttendanceQuestionController extends BaseApiController
{
    public function index()
    {
        $class=$this->request->getGet('kelas_id');
        if(!is_string($class)||!preg_match('/^[1-9][0-9]*$/D',$class))return $this->respond(['message'=>'Pilih kelas yang valid.'],422);
        $row=db_connect()->table('kelas')->select('id,nama_kelas')->where(['id'=>(int)$class,'wali_kelas_id'=>(int)$this->currentUser()['id']])->get()->getRowArray();
        if(!$row)return $this->respond(['message'=>'Rekap kelas ini tidak dapat diakses.'],403);
        $data=(new \App\Libraries\AttendanceQuestionSummary())->forClass((int)$class,new \DateTimeImmutable('now',new \DateTimeZone('Asia/Jakarta')));
        $this->response->setHeader('Cache-Control','private, no-store');
        return $this->respond(['kelas'=>$row]+$data);
    }
}
