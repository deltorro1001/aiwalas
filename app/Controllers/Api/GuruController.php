<?php
namespace App\Controllers\Api;
use App\Models\GuruModel;
class GuruController extends BaseApiController
{
 private GuruModel $model;public function __construct(){$this->model=new GuruModel();}
 public function index(){return $this->respond(['data'=>$this->model->where('aktif',1)->orderBy('nama_lengkap')->findAll()]);}
 public function show(int $id){$r=$this->model->find($id);return $r?$this->respond(['data'=>$r]):$this->respond(['ok'=>false,'message'=>'Guru tidak ditemukan.'],404);}
 public function create(){return $this->persist();}
 public function update(int $id){if(!$this->model->find($id))return $this->respond(['ok'=>false,'message'=>'Guru tidak ditemukan.'],404);return $this->persist($id);}
 public function delete(int $id){if(!$this->model->find($id))return $this->respond(['ok'=>false,'message'=>'Guru tidak ditemukan.'],404);$this->model->update($id,['aktif'=>0]);return $this->respond(['ok'=>true,'message'=>'Guru dinonaktifkan; histori tetap tersimpan.']);}
 private function persist(?int $id=null){$p=$this->payload();$e=$this->validatePayload($p,['nama_lengkap'=>'required|max_length[180]','jenis_kelamin'=>'permit_empty|in_list[L,P]','email'=>'permit_empty|valid_email|max_length[150]','nomor_hp'=>'permit_empty|max_length[30]','aktif'=>'permit_empty|in_list[0,1]']);if($e)return $this->respond(['ok'=>false,'errors'=>$e],422);$fields=['nama_lengkap','nama_panggilan','jenis_kelamin','nip_nikki','nrk','nuptk','nik','tempat_tanggal_lahir','jabatan','mata_pelajaran','alamat','nomor_hp','email','aktif'];$d=[];foreach($fields as $f)if(array_key_exists($f,$p))$d[$f]=is_string($p[$f])?trim($p[$f]):$p[$f];$d['aktif']=(int)($p['aktif']??1);if($id)$this->model->update($id,$d);else$id=$this->model->insert($d,true);return $this->respond(['ok'=>true,'data'=>$this->model->find($id)],$this->request->getMethod()==='POST'?201:200);}
}