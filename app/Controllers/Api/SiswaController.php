<?php

namespace App\Controllers\Api;

use App\Models\PenggunaModel;
use App\Models\SiswaModel;

class SiswaController extends BaseApiController
{
    private SiswaModel $model;
    public function __construct(){ $this->model=new SiswaModel(); }

    public function index()
    {
        $perPage=max(5,min(100,(int)($this->request->getGet('per_page')??20)));
        $search=trim((string)$this->request->getGet('q'));
        $kelasId=(int)$this->request->getGet('kelas_id');
        $query=$this->model->select('siswa.*, kelas.nama_kelas')->join('kelas','kelas.id=siswa.kelas_id')->where('siswa.aktif',1);
        if($kelasId>0)$query->where('siswa.kelas_id',$kelasId);
        if($search!=='')$query->groupStart()->like('siswa.nama_lengkap',$search)->orLike('siswa.nis',$search)->orLike('siswa.nisn',$search)->groupEnd();
        $data=$query->orderBy('siswa.nama_lengkap')->paginate($perPage);
        return $this->respond(['data'=>$data,'pagination'=>['currentPage'=>$this->model->pager->getCurrentPage(),'perPage'=>$perPage,'total'=>$this->model->pager->getTotal()]]);
    }

    public function show(int $id)
    {
        $student=$this->model->select('siswa.*, kelas.nama_kelas')->join('kelas','kelas.id=siswa.kelas_id')->find($id);
        if(!$student)return $this->respond(['ok'=>false,'message'=>'Siswa tidak ditemukan.'],404);
        $user=$this->currentUser();
        if(($user['peran']??'')==='siswa'&&($user['nis']??'')!==$student->nis)return $this->respond(['ok'=>false,'message'=>'Akses data siswa lain ditolak.'],403);
        return $this->respond(['data'=>$student]);
    }

    public function me()
    {
        $user=$this->currentUser();$student=$this->model->where('nis',$user['nis']??'')->first();
        if(!$student)return $this->respond(['ok'=>false,'message'=>'Profil siswa tidak ditemukan.'],404);
        return $this->respond(['data'=>$student]);
    }

    public function create()
    {
        $payload=$this->payload();$errors=$this->validatePayload($payload,$this->rules());if($errors)return $this->respond(['ok'=>false,'errors'=>$errors],422);
        if($this->model->where('nis',trim($payload['nis']))->first())return $this->respond(['ok'=>false,'message'=>'NIS sudah digunakan.'],409);
        if(!empty($payload['nisn'])&&$this->model->where('nisn',trim($payload['nisn']))->first())return $this->respond(['ok'=>false,'message'=>'NISN sudah digunakan.'],409);
        $db=db_connect();$db->transStart();$id=$this->model->insert($this->clean($payload),true);(new PenggunaModel())->insert(['nama_pengguna'=>trim($payload['nis']),'kata_sandi'=>password_hash(trim($payload['nis']),PASSWORD_DEFAULT),'nama_lengkap'=>trim($payload['nama_lengkap']),'peran'=>'siswa','nis'=>trim($payload['nis']),'aktif'=>1]);$db->transComplete();
        if(!$db->transStatus())return $this->respond(['ok'=>false,'message'=>'Data siswa gagal disimpan.'],500);
        return $this->respond(['ok'=>true,'data'=>$this->model->find($id)],201);
    }

    public function update(int $id)
    {
        $existing=$this->model->find($id);if(!$existing)return $this->respond(['ok'=>false,'message'=>'Siswa tidak ditemukan.'],404);
        $payload=$this->payload();$errors=$this->validatePayload($payload,$this->rules());if($errors)return $this->respond(['ok'=>false,'errors'=>$errors],422);
        if($this->model->where('nis',trim($payload['nis']))->where('id !=',$id)->first())return $this->respond(['ok'=>false,'message'=>'NIS sudah digunakan.'],409);
        if(!empty($payload['nisn'])&&$this->model->where('nisn',trim($payload['nisn']))->where('id !=',$id)->first())return $this->respond(['ok'=>false,'message'=>'NISN sudah digunakan.'],409);
        $db=db_connect();$db->transStart();$this->model->update($id,$this->clean($payload));$account=(new PenggunaModel())->where('nis',$existing->nis)->first();if($account)(new PenggunaModel())->update($account->id,['nama_pengguna'=>trim($payload['nis']),'nama_lengkap'=>trim($payload['nama_lengkap']),'nis'=>trim($payload['nis'])]);$db->transComplete();
        if(!$db->transStatus())return $this->respond(['ok'=>false,'message'=>'Data siswa gagal diperbarui.'],500);
        return $this->respond(['ok'=>true,'data'=>$this->model->find($id)]);
    }

    public function delete(int $id)
    {
        $student=$this->model->find($id);if(!$student)return $this->respond(['ok'=>false,'message'=>'Siswa tidak ditemukan.'],404);
        $db=db_connect();$db->transStart();$this->model->update($id,['aktif'=>0]);$account=(new PenggunaModel())->where('nis',$student->nis)->first();if($account)(new PenggunaModel())->update($account->id,['aktif'=>0]);$db->transComplete();
        return $this->respond(['ok'=>$db->transStatus(),'message'=>'Siswa dan akun dinonaktifkan; histori tetap tersimpan.']);
    }

    private function rules(): array
    {
        return ['nis'=>'required|max_length[30]','nisn'=>'permit_empty|max_length[30]','nama_lengkap'=>'required|max_length[180]','jenis_kelamin'=>'required|in_list[L,P]','tanggal_lahir'=>'permit_empty|valid_date[Y-m-d]','kelas_id'=>'required|is_natural_no_zero','nomor_hp'=>'permit_empty|max_length[30]','nomor_hp_orang_tua'=>'permit_empty|max_length[30]','aktif'=>'permit_empty|in_list[0,1]'];
    }

    private function clean(array $p): array
    {
        $fields=['nis','nisn','nama_lengkap','jenis_kelamin','tempat_lahir','tanggal_lahir','agama','alamat_lengkap','status_tempat_tinggal','transportasi','nama_ayah','nama_ibu','nama_wali','hubungan_wali','kerabat_yang_bisa_dihubungi','jumlah_saudara','anak_ke','pekerjaan_ayah','pekerjaan_ibu','menerima_bantuan','memiliki_penyakit','catatan_penyakit','hobi','prestasi','kelas_id','nomor_hp','nomor_hp_orang_tua','aktif'];
        $data=[];foreach($fields as $field){if(array_key_exists($field,$p))$data[$field]=is_string($p[$field])?trim($p[$field]):$p[$field];}
        foreach(['nisn','tempat_lahir','tanggal_lahir','agama','alamat_lengkap','nama_wali','nomor_hp','nomor_hp_orang_tua'] as $nullable){if(($data[$nullable]??null)==='')$data[$nullable]=null;}
        $data['aktif']=(int)($p['aktif']??1);return $data;
    }
}