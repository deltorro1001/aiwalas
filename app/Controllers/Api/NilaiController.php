<?php
namespace App\Controllers\Api;
use App\Models\NilaiSiswaModel;
class NilaiController extends BaseApiController
{
 public function index(){ $kelas=(int)$this->request->getGet('kelas_id');$mapel=(int)$this->request->getGet('mata_pelajaran_id');$semester=$this->request->getGet('semester')?:'Ganjil';if(!in_array($semester,['Ganjil','Genap'],true))return $this->respond(['ok'=>false],422);$q=(new NilaiSiswaModel())->select('nilai_siswa.*,siswa.nis,siswa.nama_lengkap,komponen_nilai.nama_komponen,komponen_nilai.jenis_komponen')->join('siswa','siswa.id=nilai_siswa.siswa_id')->join('komponen_nilai','komponen_nilai.id=nilai_siswa.komponen_nilai_id')->where('komponen_nilai.kelas_id',$kelas)->where('nilai_siswa.semester',$semester);if($mapel)$q->where('komponen_nilai.mata_pelajaran_id',$mapel);return $this->respond(['data'=>$q->orderBy('siswa.nama_lengkap')->orderBy('komponen_nilai.urutan')->findAll()]);}
 public function save()
 {
     $p=$this->payload();$p['semester']=$p['semester']??'Ganjil';
     $errors=$this->validatePayload($p,['semester'=>'required|in_list[Ganjil,Genap]','siswa_id'=>'required|is_natural_no_zero','komponen_nilai_id'=>'required|is_natural_no_zero','nilai'=>'required|decimal|greater_than_equal_to[0]|less_than_equal_to[100]','keterangan'=>'permit_empty|max_length[2000]']);
     if($errors)return $this->respond(['ok'=>false,'errors'=>$errors],422);
     $db=db_connect();
     $valid=$db->table('siswa')->select('siswa.id')->join('komponen_nilai','komponen_nilai.kelas_id=siswa.kelas_id')->where(['siswa.id'=>(int)$p['siswa_id'],'komponen_nilai.id'=>(int)$p['komponen_nilai_id'],'komponen_nilai.aktif'=>1])->get()->getRow();
     if(!$valid)return $this->respond(['ok'=>false,'message'=>'Siswa dan komponen nilai tidak berada pada kelas yang sama.'],422);
     $db->transBegin();
     $db->query('SELECT id FROM komponen_nilai WHERE id=? FOR UPDATE',[(int)$p['komponen_nilai_id']]);
     $model=new NilaiSiswaModel();
     $existing=$model->where(['siswa_id'=>(int)$p['siswa_id'],'komponen_nilai_id'=>(int)$p['komponen_nilai_id'],'semester'=>$p['semester']])->first();
     $data=['semester'=>$p['semester'],'siswa_id'=>(int)$p['siswa_id'],'komponen_nilai_id'=>(int)$p['komponen_nilai_id'],'nilai'=>(float)$p['nilai'],'keterangan'=>trim((string)($p['keterangan']??''))?:null,'dinilai_oleh'=>$this->currentUser()['id']];
     if($existing){$saved=$model->update($existing->id,$data);$id=$existing->id;}
     else{$id=$model->insert($data,true);$saved=(bool)$id;}
     if(!$saved||!$db->transStatus()){$db->transRollback();return $this->respond(['ok'=>false],500);}
     $stored=$model->find($id);
     (new \App\Libraries\StudentGradeAutomation($db))->changed((int)$p['siswa_id'],(int)$p['komponen_nilai_id'],$p['semester'],$existing?$existing->nilai:null,$stored->nilai);
     if(!$db->transStatus()){$db->transRollback();return $this->respond(['ok'=>false],500);}
     $db->transCommit();
     return $this->respond(['ok'=>true,'data'=>$stored],$existing?200:201);
 }
}
