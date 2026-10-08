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

 public function createQr(){
  $p=$this->payload();
  $kelasId=(int)($p['kelas_id']??0);
  if(!$kelasId)return $this->respond(['ok'=>false,'message'=>'Kelas wajib dipilih.'],422);
  $db=db_connect();
  if(!$db->table('kelas')->where('id',$kelasId)->countAllResults())return $this->respond(['ok'=>false,'message'=>'Kelas tidak ditemukan.'],404);
  $token=bin2hex(random_bytes(24));
  $row=['kelas_id'=>$kelasId,'token'=>$token,'dibuat_oleh'=>(int)$this->currentUser()['id'],'dibuat_pada'=>date('Y-m-d H:i:s')];
  $existing=$db->table('qr_absensi_aktif')->where('kelas_id',$kelasId)->countAllResults();
  if($existing)$db->table('qr_absensi_aktif')->where('kelas_id',$kelasId)->update($row);
  else $db->table('qr_absensi_aktif')->insert($row);
  return $this->respond(['ok'=>true,'data'=>['qr_code'=>'AIWALAS|ATTENDANCE|'.$kelasId.'|'.$token,'dibuat_pada'=>$row['dibuat_pada']]]);
 }

 public function save(){
  $p=$this->payload();$u=$this->currentUser();$isStudent=($u['peran']??'')==='siswa';
  if(!$isStudent)$p['tanggal']=date('Y-m-d');
  if($isStudent){
   $parts=explode('|',(string)($p['qr_code']??''));
   $kelasId=(int)($p['kelas_id']??0);
   if(count($parts)!==4||$parts[0]!=='AIWALAS'||$parts[1]!=='ATTENDANCE'||(int)$parts[2]!==$kelasId)
    return $this->respond(['ok'=>false,'message'=>'QR absensi tidak valid.'],422);
   $active=db_connect()->table('qr_absensi_aktif')->where('kelas_id',$kelasId)->get()->getRowArray();
   if(!$active||!hash_equals((string)$active['token'],(string)$parts[3]))
    return $this->respond(['ok'=>false,'message'=>'QR absensi sudah tidak berlaku. Minta QR terbaru dari wali kelas.'],422);
   $own=db_connect()->table('siswa')->where(['nis'=>$u['nis'],'aktif'=>1])->get()->getRowArray();
   $recorder=db_connect()->table('pengguna')->select('id')->where('nis',$u['nis'])->where('aktif',1)->orderBy('id','ASC')->get()->getRowArray();
   if($recorder) $u['id']=(int)$recorder['id'];
   if(!$own||((int)$own['kelas_id']!==$kelasId))return $this->respond(['ok'=>false,'message'=>'Data siswa tidak sesuai dengan kelas.'],422);
   $p['siswa_id']=(int)$own['id'];$p['tanggal']=date('Y-m-d');$p['waktu_scan']=date('H:i:s');$p['status']=date('H:i')>'06:30'?'Terlambat':'Hadir';$p['keterangan']='Scan QR siswa';
  }
  $errors=$this->validatePayload($p,['siswa_id'=>'required|is_natural_no_zero','kelas_id'=>'required|is_natural_no_zero','tanggal'=>'required|valid_date[Y-m-d]','waktu_scan'=>'permit_empty|valid_date[H:i:s]','status'=>'required|in_list[Hadir,Terlambat,Sakit,Izin,Alpha,Menunggu keterangan]','keterangan'=>'permit_empty|max_length[2000]']);
  if($errors)return $this->respond(['ok'=>false,'errors'=>$errors],422);
  $student=db_connect()->table('siswa')->where(['id'=>(int)$p['siswa_id'],'kelas_id'=>(int)$p['kelas_id'],'aktif'=>1])->get()->getRowArray();
  if(!$student)return $this->respond(['ok'=>false,'message'=>'Siswa tidak terdaftar pada kelas tersebut.'],422);
  $data=['siswa_id'=>(int)$p['siswa_id'],'kelas_id'=>(int)$p['kelas_id'],'tanggal'=>$p['tanggal'],'waktu_scan'=>empty($p['waktu_scan'])?null:$p['waktu_scan'],'status'=>$p['status'],'keterangan'=>trim((string)($p['keterangan']??''))?:null,'dicatat_oleh'=>$u['id']];
  $model=new AbsensiModel();$existing=$model->where(['siswa_id'=>$data['siswa_id'],'kelas_id'=>$data['kelas_id'],'tanggal'=>$data['tanggal']])->first();
  if($existing){$model->where(["siswa_id"=>$data["siswa_id"],"kelas_id"=>$data["kelas_id"],"tanggal"=>$data["tanggal"]])->set($data)->update();$id=$existing->id;}else{$id=$model->insert($data,true);}
  return $this->respond(['ok'=>true,'data'=>$model->find($id)],$existing?200:201);
 }
}
