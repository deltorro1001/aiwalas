<?php
namespace App\Controllers\Api;

class JurnalController extends BaseApiController
{
    private function allowed(int $class, ?int $subject = null): bool
    {
        $db=db_connect();$user=$this->currentUser();
        if (!$db->table('kelas')->where('id',$class)->countAllResults()) return false;
        if ($user['peran']!=='guru') return true;
        $q=$db->table('jadwal_kelas')->where(['kelas_id'=>$class,'guru_id'=>(int)($user['guru_id']??0)]);
        if ($subject) $q->where('mata_pelajaran_id',$subject);
        return $q->countAllResults()>0;
    }
    public function index()
    {
        $class=(int)$this->request->getGet('kelas_id');
        if (!$this->allowed($class)) return $this->respond(['ok'=>false,'message'=>'Kelas tidak dapat diakses.'],403);
        $db=db_connect();$user=$this->currentUser();
        $q=$db->table('jurnal_kelas j')->select('j.*,m.nama_mata_pelajaran,g.nama_lengkap AS nama_guru,p.nama_lengkap AS nama_pembuat')->join('mata_pelajaran m','m.id=j.mata_pelajaran_id')->join('guru g','g.id=j.guru_id')->join('pengguna p','p.id=j.dibuat_oleh')->where('j.kelas_id',$class);
        if ($user['peran']==='guru') $q->where('j.guru_id',$user['guru_id']);
        $subjects=$db->table('jadwal_kelas j')->distinct()->select('j.mata_pelajaran_id,j.guru_id,m.nama_mata_pelajaran,g.nama_lengkap AS nama_guru')->join('mata_pelajaran m','m.id=j.mata_pelajaran_id')->join('guru g','g.id=j.guru_id')->where('j.kelas_id',$class);
        if ($user['peran']==='guru') $subjects->where('j.guru_id',$user['guru_id']);
        return $this->respond(['data'=>$q->orderBy('j.tanggal','DESC')->orderBy('j.id','DESC')->get()->getResultArray(),'assignments'=>$subjects->get()->getResultArray()]);
    }
    public function create(){return $this->persist();}
    public function update(int $id){return $this->persist($id);}
    private function persist(?int $id=null)
    {
        $p=$this->payload();$db=db_connect();$user=$this->currentUser();
        $errors=$this->validatePayload($p,['kelas_id'=>'required|is_natural_no_zero','mata_pelajaran_id'=>'required|is_natural_no_zero','guru_id'=>'required|is_natural_no_zero','tanggal'=>'required|valid_date[Y-m-d]','materi'=>'required|max_length[10000]','catatan'=>'permit_empty|max_length[10000]','status'=>'required|in_list[Draft,Terbit,Dikoreksi]']);
        if ($errors) return $this->respond(['ok'=>false,'message'=>'Data jurnal tidak valid.','errors'=>$errors],422);
        if (!$this->allowed((int)$p['kelas_id'],(int)$p['mata_pelajaran_id']) || ($user['peran']==='guru' && (int)$p['guru_id']!==(int)$user['guru_id'])) return $this->respond(['ok'=>false,'message'=>'Penugasan tidak dapat diakses.'],403);
        $assignment=['kelas_id'=>(int)$p['kelas_id'],'mata_pelajaran_id'=>(int)$p['mata_pelajaran_id'],'guru_id'=>(int)$p['guru_id']];
        if (!$db->table('jadwal_kelas')->where($assignment)->countAllResults()) return $this->respond(['ok'=>false,'message'=>'Guru belum ditugaskan pada kelas dan mata pelajaran ini.'],422);
        if ($id) {
            $old=$db->table('jurnal_kelas')->where('id',$id)->get()->getRowArray();
            if (!$old) return $this->respond(['ok'=>false,'message'=>'Jurnal tidak ditemukan.'],404);
            if (!$this->allowed((int)$old['kelas_id'],(int)$old['mata_pelajaran_id']) || ($user['peran']==='guru' && (int)$old['dibuat_oleh']!==(int)$user['id'])) return $this->respond(['ok'=>false,'message'=>'Jurnal ini tidak dapat diubah.'],403);
            if ((int)$old['kelas_id']!==(int)$p['kelas_id']) return $this->respond(['ok'=>false,'message'=>'Kelas jurnal tidak dapat dipindahkan.'],422);
        }
        $data=$assignment+['tanggal'=>$p['tanggal'],'materi'=>trim($p['materi']),'catatan'=>trim($p['catatan']??''),'status'=>$p['status']];
        if ($id) $db->table('jurnal_kelas')->where('id',$id)->update($data);
        else {$data['dibuat_oleh']=$user['id'];$db->table('jurnal_kelas')->insert($data);$id=$db->insertID();}
        return $this->respond(['ok'=>true,'id'=>$id]);
    }
    public function delete(int $id)
    {
        $db=db_connect();if(!$db->table('jurnal_kelas')->where('id',$id)->countAllResults())return $this->respond(['ok'=>false],404);
        $db->table('jurnal_kelas')->where('id',$id)->delete();return $this->respond(['ok'=>true]);
    }
}
