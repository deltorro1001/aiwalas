<?php

namespace App\Controllers\Api;

use App\Models\TahunAjaranModel;

class TahunAjaranController extends BaseApiController
{
    private TahunAjaranModel $model;

    public function __construct()
    {
        $this->model = new TahunAjaranModel();
    }

    public function index()
    {
        return $this->respond(['data' => $this->model->orderBy('nama_tahun_ajaran', 'DESC')->orderBy('semester', 'ASC')->findAll()]);
    }

    public function create()
    {
        $payload = $this->payload();
        $errors = $this->validatePayload($payload, $this->rules());
        if ($errors) return $this->respond(['ok' => false, 'errors' => $errors], 422);
        if ($this->model->where(['nama_tahun_ajaran'=>$payload['nama_tahun_ajaran'], 'semester'=>$payload['semester']])->first()) {
            return $this->respond(['ok'=>false,'message'=>'Tahun ajaran dan semester sudah tersedia.'], 409);
        }
        $this->activateOnlyOne($payload);
        $id = $this->model->insert($this->clean($payload), true);
        return $this->respond(['ok'=>true,'data'=>$this->model->find($id)], 201);
    }

    public function update(int $id)
    {
        if (! $this->model->find($id)) return $this->respond(['ok'=>false,'message'=>'Tahun ajaran tidak ditemukan.'], 404);
        $payload=$this->payload(); $errors=$this->validatePayload($payload,$this->rules());
        if ($errors) return $this->respond(['ok'=>false,'errors'=>$errors],422);
        $duplicate=$this->model->where(['nama_tahun_ajaran'=>$payload['nama_tahun_ajaran'],'semester'=>$payload['semester']])->where('id !=',$id)->first();
        if($duplicate) return $this->respond(['ok'=>false,'message'=>'Tahun ajaran dan semester sudah tersedia.'],409);
        $this->activateOnlyOne($payload,$id); $this->model->update($id,$this->clean($payload));
        return $this->respond(['ok'=>true,'data'=>$this->model->find($id)]);
    }

    public function delete(int $id)
    {
        $row=$this->model->find($id); if(!$row)return $this->respond(['ok'=>false,'message'=>'Tahun ajaran tidak ditemukan.'],404);
        if((int)$row->aktif===1)return $this->respond(['ok'=>false,'message'=>'Tahun ajaran aktif tidak dapat dihapus.'],409);
        if(db_connect()->table('kelas')->where('tahun_ajaran_id',$id)->countAllResults()>0)return $this->respond(['ok'=>false,'message'=>'Tahun ajaran masih digunakan oleh kelas.'],409);
        $this->model->delete($id); return $this->respond(['ok'=>true]);
    }

    private function activateOnlyOne(array $payload, ?int $except=null): void
    {
        if((int)($payload['aktif']??0)!==1)return;
        $builder=db_connect()->table('tahun_ajaran')->set('aktif',0);
        if($except)$builder->where('id !=',$except); $builder->update();
    }

    private function clean(array $payload): array
    {
        return ['nama_tahun_ajaran'=>trim($payload['nama_tahun_ajaran']),'semester'=>$payload['semester'],'aktif'=>(int)($payload['aktif']??0)];
    }

    private function rules(): array
    {
        return ['nama_tahun_ajaran'=>'required|max_length[20]|regex_match[/^\\d{4}\\/\\d{4}$/]','semester'=>'required|in_list[Ganjil,Genap]','aktif'=>'permit_empty|in_list[0,1]'];
    }
}