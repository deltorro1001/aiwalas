<?php
namespace App\Controllers\Api;
use App\Models\MataPelajaranModel;
class MataPelajaranController extends BaseApiController
{
 private MataPelajaranModel $model;public function __construct(){$this->model=new MataPelajaranModel();}
 public function index(){return $this->respond(['data'=>$this->model->orderBy('nama_mata_pelajaran')->findAll()]);}
 public function create(){return $this->persist();}
 public function update(int $id){if(!$this->model->find($id))return $this->respond(['ok'=>false,'message'=>'Mata pelajaran tidak ditemukan.'],404);return $this->persist($id);}
 public function delete(int $id){if(!$this->model->find($id))return $this->respond(['ok'=>false,'message'=>'Mata pelajaran tidak ditemukan.'],404);$used=db_connect()->table('jadwal_kelas')->where('mata_pelajaran_id',$id)->countAllResults()+db_connect()->table('komponen_nilai')->where('mata_pelajaran_id',$id)->countAllResults();if($used){$this->model->update($id,['aktif'=>0]);return $this->respond(['ok'=>true,'message'=>'Mata pelajaran dinonaktifkan karena memiliki histori.']);}$this->model->delete($id);return $this->respond(['ok'=>true]);}
 private function persist(?int $id=null){$p=$this->payload();$e=$this->validatePayload($p,['nama_mata_pelajaran'=>'required|max_length[150]','aktif'=>'permit_empty|in_list[0,1]']);if($e)return $this->respond(['ok'=>false,'errors'=>$e],422);$name=trim($p['nama_mata_pelajaran']);$q=$this->model->where('nama_mata_pelajaran',$name);if($id)$q->where('id !=',$id);if($q->first())return $this->respond(['ok'=>false,'message'=>'Mata pelajaran sudah tersedia.'],409);$d=['nama_mata_pelajaran'=>$name,'aktif'=>(int)($p['aktif']??1)];if($id)$this->model->update($id,$d);else$id=$this->model->insert($d,true);return $this->respond(['ok'=>true,'data'=>$this->model->find($id)],$this->request->getMethod()==='POST'?201:200);}
}