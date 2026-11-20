<?php
namespace App\Controllers\Api;

class DisciplineReportDeliveryController extends BaseApiController
{
    public function send(){
        $p=$this->payload();$errors=$this->validatePayload($p,['kelas_id'=>'required|is_natural_no_zero','format'=>'required|in_list[excel,pdf]','nomor_tujuan'=>'required|max_length[30]','nama_penerima'=>'permit_empty|max_length[180]']);
        if($errors)return $this->respond(['ok'=>false,'message'=>'Isi kelas, format, dan nomor tujuan dengan benar.','errors'=>$errors],422);
        if(!is_string($p['nomor_tujuan'])||!is_string($p['nama_penerima']??''))return $this->respond(['ok'=>false,'message'=>'Nomor atau nama penerima tidak valid.'],422);
        try{$result=(new \App\Libraries\LatenessReportDelivery())->send((int)$p['kelas_id'],$p['format'],$p['nomor_tujuan'],(int)$this->currentUser()['id'],$p['nama_penerima']??'');return $this->respond($result,$result['ok']?200:409);}
        catch(\InvalidArgumentException $error){return $this->respond(['ok'=>false,'message'=>$error->getMessage()],422);}
        catch(\Throwable $error){log_message('error','Pengiriman export keterlambatan gagal: {error}',['error'=>$error->getMessage()]);return $this->respond(['ok'=>false,'message'=>'Export keterlambatan belum berhasil dikirim. Periksa Komunikasi sebelum mengirim ulang.'],500);}
    }
    public function media(string $token){
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))return $this->response->setStatusCode(404)->setBody('Berkas tidak ditemukan.');$now=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');$row=db_connect()->table('status_kedisiplinan_pengiriman')->where('token_hash',hash('sha256',$token))->where('berlaku_sampai >=',$now)->get()->getRowArray();
        if(!$row||!preg_match('/^[a-f0-9]{64}\.(?:pdf|xls)$/D',$row['berkas'])||!in_array($row['format'],['excel','pdf'],true)||!is_file(WRITEPATH.'status-kedisiplinan/'.$row['berkas']))return $this->response->setStatusCode(404)->setBody('Berkas tidak ditemukan atau tautan kedaluwarsa.');$excel=$row['format']==='excel';$filename='Status-Keterlambatan.'.($excel?'xls':'pdf');$mime=$excel?'application/vnd.ms-excel':'application/pdf';return $this->response->download(WRITEPATH.'status-kedisiplinan/'.$row['berkas'],null)->setFileName($filename)->setContentType($mime)->setHeader('Cache-Control','private, no-store')->setHeader('X-Content-Type-Options','nosniff')->setHeader('X-Robots-Tag','noindex, nofollow');
    }
}
