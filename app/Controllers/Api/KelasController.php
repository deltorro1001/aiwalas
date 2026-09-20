<?php

namespace App\Controllers\Api;

use App\Models\KelasModel;

class KelasController extends BaseApiController
{
    private KelasModel $model;
    public function __construct(){ $this->model=new KelasModel(); }

    public function index()
    {
        $data=$this->model->select('kelas.*, tahun_ajaran.nama_tahun_ajaran, tahun_ajaran.semester, pengguna.nama_lengkap AS nama_wali_kelas')->join('tahun_ajaran','tahun_ajaran.id=kelas.tahun_ajaran_id')->join('pengguna','pengguna.id=kelas.wali_kelas_id','left')->orderBy('kelas.tingkat')->orderBy('kelas.nama_kelas')->findAll();
        return $this->respond(['data'=>$data]);
    }

    public function create()
    {
        $payload=$this->payload();$errors=$this->validatePayload($payload,$this->rules());if($errors)return $this->respond(['ok'=>false,'errors'=>$errors],422);
        if($this->duplicate($payload))return $this->respond(['ok'=>false,'message'=>'Nama kelas sudah ada pada tahun ajaran tersebut.'],409);
        $id=$this->model->insert($this->clean($payload),true);return $this->respond(['ok'=>true,'data'=>$this->model->find($id)],201);
    }

    public function update(int $id)
    {
        if(!$this->model->find($id))return $this->respond(['ok'=>false,'message'=>'Kelas tidak ditemukan.'],404);
        $payload=$this->payload();$errors=$this->validatePayload($payload,$this->rules());if($errors)return $this->respond(['ok'=>false,'errors'=>$errors],422);
        if($this->duplicate($payload,$id))return $this->respond(['ok'=>false,'message'=>'Nama kelas sudah ada pada tahun ajaran tersebut.'],409);
        $this->model->update($id,$this->clean($payload));return $this->respond(['ok'=>true,'data'=>$this->model->find($id)]);
    }

    public function delete(int $id)
    {
        if(!$this->model->find($id))return $this->respond(['ok'=>false,'message'=>'Kelas tidak ditemukan.'],404);
        if(db_connect()->table('siswa')->where('kelas_id',$id)->countAllResults()>0)return $this->respond(['ok'=>false,'message'=>'Kelas masih memiliki siswa dan tidak dapat dihapus.'],409);
        $this->model->delete($id);return $this->respond(['ok'=>true]);
    }

    private function duplicate(array $payload,?int $except=null): bool
    {
        $query=$this->model->where(['nama_kelas'=>trim($payload['nama_kelas']),'tahun_ajaran_id'=>(int)$payload['tahun_ajaran_id']]);if($except)$query->where('id !=',$except);return $query->first()!==null;
    }
    private function clean(array $p): array{return ['nama_kelas'=>trim($p['nama_kelas']),'tingkat'=>(int)$p['tingkat'],'jurusan'=>trim($p['jurusan']),'tahun_ajaran_id'=>(int)$p['tahun_ajaran_id'],'wali_kelas_id'=>empty($p['wali_kelas_id'])?null:(int)$p['wali_kelas_id']];}
    private function rules(): array{return ['nama_kelas'=>'required|max_length[50]','tingkat'=>'required|integer|greater_than_equal_to[1]|less_than_equal_to[12]','jurusan'=>'required|max_length[100]','tahun_ajaran_id'=>'required|is_natural_no_zero','wali_kelas_id'=>'permit_empty|is_natural_no_zero'];}
}