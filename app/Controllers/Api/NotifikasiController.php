<?php
namespace App\Controllers\Api;
use App\Models\NotifikasiModel;
class NotifikasiController extends BaseApiController
{
 public function index(){return $this->respond(['data'=>(new NotifikasiModel())->where('pengguna_id',$this->currentUser()['id'])->orderBy('id','DESC')->paginate(30)]);}
 public function create(){ $p=$this->payload();$e=$this->validatePayload($p,['pengguna_id'=>'required|is_natural_no_zero','judul'=>'required|max_length[180]','isi'=>'required|max_length[5000]','jenis'=>'required|max_length[80]']);if($e)return $this->respond(['ok'=>false,'errors'=>$e],422);$m=new NotifikasiModel();$id=$m->insert(['pengguna_id'=>(int)$p['pengguna_id'],'judul'=>trim($p['judul']),'isi'=>trim($p['isi']),'jenis'=>trim($p['jenis']),'sudah_dibaca'=>0],true);return $this->respond(['ok'=>true,'data'=>$m->find($id)],201);}
 public function read(int $id){$m=new NotifikasiModel();$row=$m->where(['id'=>$id,'pengguna_id'=>$this->currentUser()['id']])->first();if(!$row)return $this->respond(['ok'=>false,'message'=>'Notifikasi tidak ditemukan.'],404);$m->update($id,['sudah_dibaca'=>1]);return $this->respond(['ok'=>true]);}
 public function readAll(){(new NotifikasiModel())->where('pengguna_id',$this->currentUser()['id'])->set(['sudah_dibaca'=>1])->update();return $this->respond(['ok'=>true]);}
}