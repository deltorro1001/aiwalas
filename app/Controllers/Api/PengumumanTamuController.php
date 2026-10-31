<?php
namespace App\Controllers\Api;
class PengumumanTamuController extends BaseApiController
{
    private function owns(int $class): bool {return db_connect()->table('kelas')->where(['id'=>$class,'wali_kelas_id'=>$this->currentUser()['id']])->countAllResults()>0;}
    public function index(){
        $class=(int)$this->request->getGet('kelas_id');if(!$this->owns($class))return $this->respond(['message'=>'Kelas tidak dapat diakses.'],403);
        $this->response->setHeader('Cache-Control','private, no-store');
        return $this->respond(['data'=>db_connect()->table('pengumuman_tamu')->select('id,judul,isi,tanggal_agenda,terbit')->where('kelas_id',$class)->orderBy('id','DESC')->get()->getResultArray()]);
    }
    public function create(){return $this->save();}
    public function update(int $id){return $this->save($id);}
    private function save(?int $id=null){
        $p=$this->payload();$errors=$this->validatePayload($p,['kelas_id'=>'required|is_natural_no_zero','judul'=>'required|max_length[180]','isi'=>'required|max_length[10000]','tanggal_agenda'=>'permit_empty|valid_date[Y-m-d]']);
        if($errors||!isset($p['terbit'])||!is_bool($p['terbit'])||!is_string($p['judul']??null)||!is_string($p['isi']??null)||trim($p['judul'])===''||trim($p['isi'])==='')return $this->respond(['message'=>'Lengkapi judul, isi, tanggal, dan pilihan publikasi dengan benar.'],422);
        if(!$this->owns((int)$p['kelas_id']))return $this->respond(['message'=>'Kelas tidak dapat diakses.'],403);
        $db=db_connect();if($id&&!$db->table('pengumuman_tamu')->where(['id'=>$id,'kelas_id'=>(int)$p['kelas_id']])->countAllResults())return $this->respond(['message'=>'Pengumuman tidak ditemukan.'],404);
        $row=['kelas_id'=>(int)$p['kelas_id'],'judul'=>trim($p['judul']),'isi'=>trim($p['isi']),'tanggal_agenda'=>empty($p['tanggal_agenda'])?null:$p['tanggal_agenda'],'terbit'=>(int)$p['terbit']];
        if($id)$db->table('pengumuman_tamu')->where('id',$id)->update($row);else{$row['dibuat_oleh']=$this->currentUser()['id'];$db->table('pengumuman_tamu')->insert($row);$id=$db->insertID();}
        return $this->respond(['ok'=>true,'id'=>$id],201);
    }
    public function delete(int $id){
        $db=db_connect();$row=$db->table('pengumuman_tamu')->where('id',$id)->get()->getRowArray();if(!$row||!$this->owns((int)$row['kelas_id']))return $this->respond(['message'=>'Pengumuman tidak dapat diakses.'],403);
        $db->table('pengumuman_tamu')->where('id',$id)->delete();return $this->respond(['ok'=>true]);
    }
}
