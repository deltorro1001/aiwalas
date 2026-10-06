<?php
namespace App\Controllers\Api;
use App\Models\AbsensiModel;

class AbsensiController extends BaseApiController
{
 public function index(){
  $kelas=(int)$this->request->getGet('kelas_id');
  $tanggal=$this->request->getGet('tanggal')?:date('Y-m-d');
  $q=(new AbsensiModel())->select('absensi.*,siswa.nis,siswa.nama_lengkap')->join('siswa','siswa.id=absensi.siswa_id')->where('absensi.kelas_id',$kelas)->where('absensi.tanggal',$tanggal)->orderBy('siswa.nama_lengkap');
  $u=$this->currentUser();
  if(($u['peran']??'')==='siswa')$q->where('siswa.nis',$u['nis']);
  return $this->respond(['data'=>$q->findAll()]);
 }
 public function save(){
  $p=$this->payload(); $u=$this->currentUser(); $isStudent=($u['peran']??'')==='siswa';
  if($isStudent){
   $parts=explode('|',(string)($p['qr_code']??''));
   if(count($parts)!==4||$parts[0]!=='AIWALAS'||$parts[1]!=='ATTENDANCE'||(int)$parts[2]!=(int)($p['kelas_id']??0)||$parts[3]!==date('Y-m-d'))
    return $this->respond(['ok'=>false,'message'=>'QR absensi tidak berlaku untuk kelas atau tanggal ini.'],422);
   $own=db_connect()->table('siswa')->where(['nis'=>$u['nis'],'aktif'=>1])->get()->getRowArray();
   if(!$own||((int)$own['kelas_id']!==(int)$p['kelas_id']))return $this->respond(['ok'=>false,'message'=>'Data siswa tidak sesuai dengan kelas.'],422);
   $p['siswa_id']=(int)$own['id']; $p['tanggal']=date('Y-m-d'); $p['status']=$p['status']??'Hadir'; $p['keterangan']='Scan QR siswa';
  }
  $errors=$this->validatePayload($p,['siswa_id'=>'required|is_natural_no_zero','kelas_id'=>'required|is_natural_no_zero','tanggal'=>'required|valid_date[Y-m-d]','waktu_scan'=>'permit_empty|valid_date[H:i:s]','status'=>'required|in_list[Hadir,Terlambat,Sakit,Izin,Alpha,Menunggu keterangan]','keterangan'=>'permit_empty|max_length[2000]']);
  if($errors)return $this->respond(['ok'=>false,'errors'=>$errors],422);
  $student=db_connect()->table('siswa')->where(['id'=>(int)$p['siswa_id'],'kelas_id'=>(int)$p['kelas_id'],'aktif'=>1])->get()->getRowArray();
  if(!$student)return $this->respond(['ok'=>false,'message'=>'Siswa tidak terdaftar pada kelas tersebut.'],422);
  $data=['siswa_id'=>(int)$p['siswa_id'],'kelas_id'=>(int)$p['kelas_id'],'tanggal'=>$p['tanggal'],'waktu_scan'=>empty($p['waktu_scan'])?null:$p['waktu_scan'],'status'=>$p['status'],'keterangan'=>trim((string)($p['keterangan']??''))?:null,'dicatat_oleh'=>$u['id']];
  $model=new AbsensiModel();$existing=$model->where(['siswa_id'=>$data['siswa_id'],'kelas_id'=>$data['kelas_id'],'tanggal'=>$data['tanggal']])->first();
  if($existing){$model->update($existing->id,$data);$id=$existing->id;}else{$id=$model->insert($data,true);}
  return $this->respond(['ok'=>true,'data'=>$model->find($id)],$existing?200:201);
 }
}
