<?php
namespace App\Controllers\Api;
use App\Models\NotifikasiModel;
class NotifikasiController extends BaseApiController
{
 public function index()
 {
     $date=$this->request->getGet('tanggal');
     if($date!==null&&!is_string($date))return $this->respond(['ok'=>false,'message'=>'Tanggal tidak valid.'],422);
     $query=db_connect()->table('notifikasi')->where('pengguna_id',$this->currentUser()['id']);
     if($date!==null&&$date!==''){
         try{[$start,$end]=\App\Libraries\NotificationDate::range($date);}
         catch(\InvalidArgumentException $error){return $this->respond(['ok'=>false,'message'=>$error->getMessage()],422);}
         $query->where('dibuat_pada >=',$start)->where('dibuat_pada <',$end);
     }
     $page=min(1000000,max(1,(int)$this->request->getGet('page')));$limit=30;
     $total=(clone $query)->countAllResults();
     $rows=$query->orderBy('dibuat_pada','DESC')->orderBy('id','DESC')->limit($limit,($page-1)*$limit)->get()->getResultArray();
     $this->response->setHeader('Cache-Control','no-store');
     return $this->respond(['data'=>$rows,'tanggal'=>$date?:null,'today'=>\App\Libraries\NotificationDate::today(),'page'=>$page,'per_page'=>$limit,'total'=>$total,'has_more'=>$page*$limit<$total]);
 }
 public function create(){ $p=$this->payload();$e=$this->validatePayload($p,['pengguna_id'=>'required|is_natural_no_zero','judul'=>'required|max_length[180]','isi'=>'required|max_length[5000]','jenis'=>'required|max_length[80]']);if($e)return $this->respond(['ok'=>false,'errors'=>$e],422);$m=new NotifikasiModel();$id=$m->insert(['pengguna_id'=>(int)$p['pengguna_id'],'judul'=>trim($p['judul']),'isi'=>trim($p['isi']),'jenis'=>trim($p['jenis']),'sudah_dibaca'=>0],true);return $this->respond(['ok'=>true,'data'=>$m->find($id)],201);}
 public function read(int $id){$m=new NotifikasiModel();$row=$m->where(['id'=>$id,'pengguna_id'=>$this->currentUser()['id']])->first();if(!$row)return $this->respond(['ok'=>false,'message'=>'Notifikasi tidak ditemukan.'],404);$m->update($id,['sudah_dibaca'=>1]);return $this->respond(['ok'=>true]);}
 public function readAll(){(new NotifikasiModel())->where('pengguna_id',$this->currentUser()['id'])->set(['sudah_dibaca'=>1])->update();return $this->respond(['ok'=>true]);}
}