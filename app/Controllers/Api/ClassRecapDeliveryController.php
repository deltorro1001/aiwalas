<?php
namespace App\Controllers\Api;

class ClassRecapDeliveryController extends BaseApiController
{
    public function send()
    {
        $p=$this->payload();
        $errors=$this->validatePayload($p,['kelas_id'=>'required|is_natural_no_zero','tahun'=>'required|integer|greater_than_equal_to[2026]|less_than_equal_to[9998]','bulan'=>'required|integer|greater_than_equal_to[1]|less_than_equal_to[12]','nomor_tujuan'=>'required|max_length[30]','nama_penerima'=>'permit_empty|max_length[180]']);
        if($errors)return $this->respond(['ok'=>false,'message'=>'Isi kelas, bulan, tahun, dan nomor tujuan dengan benar.','errors'=>$errors],422);
        if(!is_string($p['nomor_tujuan'])||!is_string($p['nama_penerima']??''))return $this->respond(['ok'=>false,'message'=>'Nomor/nama penerima tidak valid.'],422);
        try{
            $result=(new \App\Libraries\ClassRecapDelivery())->send((int)$p['kelas_id'],(int)$p['tahun'],(int)$p['bulan'],$p['nomor_tujuan'],(int)$this->currentUser()['id'],$p['nama_penerima']??'');
            return $this->respond($result,$result['ok']?200:409);
        }catch(\InvalidArgumentException $error){return $this->respond(['ok'=>false,'message'=>$error->getMessage()],422);}
        catch(\Throwable $error){log_message('error','Pengiriman PDF rekap gagal: {error}',['error'=>$error->getMessage()]);return $this->respond(['ok'=>false,'message'=>'PDF rekap belum berhasil dikirim. Periksa Komunikasi sebelum mengirim ulang.'],500);}
    }
    public function media(string $token)
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))return $this->response->setStatusCode(404)->setBody('PDF tidak ditemukan.');
        $now=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
        $row=db_connect()->table('rekap_absen_pengiriman')->where('token_hash',hash('sha256',$token))->where('berlaku_sampai >=',$now)->get()->getRowArray();
        if(!$row||!preg_match('/^[a-f0-9]{64}\.pdf$/D',$row['berkas'])||!is_file(WRITEPATH.'rekap-absen/'.$row['berkas']))return $this->response->setStatusCode(404)->setBody('PDF tidak ditemukan atau tautan kedaluwarsa.');
        return $this->response->download(WRITEPATH.'rekap-absen/'.$row['berkas'],null)->setFileName('Rekap-Absen-Kelas.pdf')->setContentType('application/pdf')->setHeader('Cache-Control','private, no-store')->setHeader('X-Content-Type-Options','nosniff')->setHeader('X-Robots-Tag','noindex, nofollow');
    }
}
