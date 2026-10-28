<?php
namespace App\Controllers\Api;
use App\Models\PesanKomunikasiModel;
use App\Models\NotifikasiModel;
class PesanKomunikasiController extends BaseApiController
{
 public function index()
 {
     $class=(int)$this->request->getGet('kelas_id');$user=$this->currentUser();$db=db_connect();
     if(!$db->table('kelas')->where(['id'=>$class,'wali_kelas_id'=>$user['id']])->countAllResults())return $this->respond(['ok'=>false,'message'=>'Kelas tidak dapat diakses.'],404);
     $status=$this->request->getGet('status')?:'all';
     if(!in_array($status,['all','sent','pending'],true))return $this->respond(['ok'=>false,'message'=>'Status komunikasi tidak valid.'],422);
     $query=$db->table('pesan_komunikasi p')->select('p.*,s.nis,s.nama_lengkap,COALESCE(g.status,d.status) AS automation_status,COALESCE(g.attempted_at,d.attempted_at) AS attempted_at,COALESCE(g.result_note,d.result_note) AS result_note')->join('siswa s','s.id=p.siswa_id','left')->join('teacher_schedule_dispatches d','d.message_id=p.id','left')->join('student_grade_dispatches g','g.message_id=p.id','left')->where(['p.kelas_id'=>$class,'p.dibuat_oleh'=>$user['id']]);
     if($status==='sent')$query->where('p.status_pengiriman','Terkirim');
     if($status==='pending')$query->where('p.status_pengiriman !=','Terkirim');
     $page=min(1000000,max(1,(int)$this->request->getGet('page')));$limit=25;
     $total=(clone $query)->countAllResults();$rows=$query->orderBy('p.id','DESC')->limit($limit,($page-1)*$limit)->get()->getResultArray();
     foreach($rows as &$row){
         $row['delivery_status']=$row['status_pengiriman']==='Terkirim'?'Terkirim':'Pending';
         $row['delivery_note']=$row['delivery_status']==='Terkirim'?'Diterima gateway.':($row['result_note']?:($row['status_pengiriman']==='Gagal'?'Pengiriman gagal; perlu diperiksa.':'Belum ada konfirmasi pengiriman.'));
     }
     unset($row);$this->response->setHeader('Cache-Control','no-store');
     return $this->respond(['data'=>$rows,'total'=>$total,'page'=>$page,'has_more'=>$page*$limit<$total]);
 }
 public function prepare(){ $p=$this->payload();$errors=$this->validatePayload($p,['siswa_id'=>'permit_empty|is_natural_no_zero','kelas_id'=>'required|is_natural_no_zero','pengirim'=>'required|max_length[180]','penerima'=>'required|max_length[180]','nomor_tujuan'=>'required|max_length[30]','isi_pesan'=>'required|max_length[5000]','kategori'=>'permit_empty|max_length[80]']);if($errors)return $this->respond(['ok'=>false,'errors'=>$errors],422);$phone=preg_replace('/\D+/','',(string)$p['nomor_tujuan']);if(str_starts_with($phone,'0'))$phone='62'.substr($phone,1);if(!preg_match('/^62\d{8,15}$/',$phone))return $this->respond(['ok'=>false,'message'=>'Nomor WhatsApp tidak valid.'],422);$data=['siswa_id'=>empty($p['siswa_id'])?null:(int)$p['siswa_id'],'kelas_id'=>(int)$p['kelas_id'],'pengirim'=>trim($p['pengirim']),'penerima'=>trim($p['penerima']),'nomor_tujuan'=>$phone,'isi_pesan'=>trim($p['isi_pesan']),'kategori'=>trim((string)($p['kategori']??''))?:null,'status_pengiriman'=>'Disiapkan','dikirim_pada'=>null,'dibuat_oleh'=>$this->currentUser()['id']];$model=new PesanKomunikasiModel();$id=$model->insert($data,true);return $this->respond(['ok'=>true,'data'=>$model->find($id),'whatsappUrl'=>'https://wa.me/'.$phone.'?text='.rawurlencode($data['isi_pesan'])],201);}
 public function incoming(){ $p=$this->payload();$errors=$this->validatePayload($p,['siswa_id'=>'required|is_natural_no_zero','kelas_id'=>'required|is_natural_no_zero','nomor_tujuan'=>'required|max_length[30]','isi_pesan'=>'required|max_length[5000]']);if($errors)return $this->respond(['ok'=>false,'errors'=>$errors],422);$phone=preg_replace('/\D+/','',(string)$p['nomor_tujuan']);if(str_starts_with($phone,'0'))$phone='62'.substr($phone,1);if(!preg_match('/^62\d{8,15}$/',$phone))return $this->respond(['ok'=>false,'message'=>'Nomor WhatsApp tidak valid.'],422);$student=db_connect()->table('siswa')->select('nama_lengkap')->where(['id'=>(int)$p['siswa_id'],'kelas_id'=>(int)$p['kelas_id']])->get()->getRowArray();if(!$student)return $this->respond(['ok'=>false,'message'=>'Siswa tidak ditemukan pada kelas aktif.'],404);$u=$this->currentUser();$model=new PesanKomunikasiModel();$id=$model->insert(['siswa_id'=>(int)$p['siswa_id'],'kelas_id'=>(int)$p['kelas_id'],'pengirim'=>'Orangtua '.$student['nama_lengkap'],'penerima'=>$u['nama_lengkap']??'Wali Kelas','nomor_tujuan'=>$phone,'isi_pesan'=>(string)$p['isi_pesan'],'kategori'=>'Pesan Masuk WhatsApp','status_pengiriman'=>'Diterima','dikirim_pada'=>date('Y-m-d H:i:s'),'dibuat_oleh'=>(int)$u['id']],true);$receivedAt=date('Y-m-d H:i:s');(new NotifikasiModel())->insert(['pengguna_id'=>(int)$u['id'],'judul'=>'Pesan masuk dari Orangtua '.$student['nama_lengkap'],'isi'=>(string)$p['isi_pesan'],'jenis'=>'WhatsApp Masuk','sudah_dibaca'=>0,'dibuat_pada'=>$receivedAt]);return $this->respond(['ok'=>true,'data'=>$model->find($id)],201);}
 public function opened(int $id)
 {
     $model=new PesanKomunikasiModel();$row=$model->where(['id'=>$id,'dibuat_oleh'=>$this->currentUser()['id']])->first();
     if(!$row)return $this->respond(['ok'=>false,'message'=>'Histori pesan tidak ditemukan.'],404);
     // Opening WhatsApp is not proof of delivery and must not downgrade a sent message.
     if($row->status_pengiriman==='Disiapkan')$model->update($id,['status_pengiriman'=>'Dibuka']);
     return $this->respond(['ok'=>true,'data'=>$model->find($id)]);
 }
}
