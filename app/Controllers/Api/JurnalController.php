<?php
namespace App\Controllers\Api;

class JurnalController extends BaseApiController
{
    private const MIN_DATE='2026-01-01';
    private function writer(): bool {return in_array($this->currentUser()['peran']??'',['wali_kelas','guru','sekretaris'],true);}
    private function allowed(int $class, ?int $subject = null): bool
    {
        $db=db_connect();$user=$this->currentUser();
        $row=$db->table('kelas')->where('id',$class)->get()->getRowArray();if(!$row)return false;
        if(($user['peran']??'')==='wali_kelas')return (int)$row['wali_kelas_id']===(int)$user['id'];
        if(in_array($user['peran']??'',['sekretaris','siswa'],true))return $db->table('siswa')->where(['kelas_id'=>$class,'nis'=>$user['nis']??'','aktif'=>1])->countAllResults()>0;
        if(($user['peran']??'')==='guru')return \App\Libraries\TeacherAssignments::has($class,(int)($user['guru_id']??0),$subject);
        return ($user['peran']??'')==='guest'&&$db->table('tahun_ajaran')->where(['id'=>$row['tahun_ajaran_id'],'aktif'=>1])->countAllResults()>0;
    }
    public function index()
    {
        $class=(int)$this->request->getGet('kelas_id');
        if (!$this->allowed($class)) return $this->respond(['ok'=>false,'message'=>'Kelas tidak dapat diakses.'],403);
        $date=$this->request->getGet('tanggal');
        if($date!==null){
            $parsed=is_string($date)?\DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
            if(!$parsed||$parsed->format('Y-m-d')!==$date||$date<self::MIN_DATE)return $this->respond(['message'=>'Pilih tanggal jurnal yang valid mulai 1 Januari 2026.'],422);
        }
        $db=db_connect();$user=$this->currentUser();
        $q=$db->table('jurnal_kelas j')->select('j.*,m.nama_mata_pelajaran,g.nama_lengkap AS nama_guru,p.nama_lengkap AS nama_pembuat')->join('mata_pelajaran m','m.id=j.mata_pelajaran_id')->join('guru g','g.id=j.guru_id')->join('pengguna p','p.id=j.dibuat_oleh')->where('j.kelas_id',$class);
        if ($user['peran']==='guru') $q->where('j.guru_id',$user['guru_id']);
        if($date!==null)$q->where('j.tanggal',$date);
        $subjects=array_map(static fn($r)=>array_intersect_key($r,array_flip(['mata_pelajaran_id','guru_id','nama_mata_pelajaran','nama_guru'])),\App\Libraries\TeacherAssignments::rows($class,$user['peran']==='guru'?(int)$user['guru_id']:null));
        $rows=$q->orderBy('j.tanggal','DESC')->orderBy('j.id','DESC')->get()->getResultArray();
        foreach($rows as &$row)$row['can_edit']=$this->writer()&&($user['peran']!=='guru'||(int)$row['dibuat_oleh']===(int)$user['id']);unset($row);
        $this->response->setHeader('Cache-Control','private, no-store');
        return $this->respond(['data'=>$rows,'assignments'=>$this->writer()?$subjects:[],'can_create'=>$this->writer(),'minimum_date'=>self::MIN_DATE,'tanggal'=>$date]);
    }
    public function create(){return $this->persist();}
    public function update(int $id){return $this->persist($id);}
    private function persist(?int $id=null)
    {
        $p=$this->payload();$db=db_connect();$user=$this->currentUser();
        if(!$this->writer())return $this->respond(['message'=>'Akun ini hanya dapat membaca jurnal.'],403);
        $errors=$this->validatePayload($p,['kelas_id'=>'required|is_natural_no_zero','mata_pelajaran_id'=>'required|is_natural_no_zero','guru_id'=>'required|is_natural_no_zero','tanggal'=>'required|valid_date[Y-m-d]','materi'=>'required|max_length[10000]','catatan'=>'permit_empty|max_length[10000]','status'=>'required|in_list[Draft,Terbit,Dikoreksi]']);
        if ($errors) return $this->respond(['ok'=>false,'message'=>'Data jurnal tidak valid.','errors'=>$errors],422);
        if($p['tanggal']<self::MIN_DATE)return $this->respond(['message'=>'Tanggal jurnal dimulai dari 1 Januari 2026.'],422);
        if (!$this->allowed((int)$p['kelas_id'],(int)$p['mata_pelajaran_id']) || ($user['peran']==='guru' && (int)$p['guru_id']!==(int)$user['guru_id'])) return $this->respond(['ok'=>false,'message'=>'Penugasan tidak dapat diakses.'],403);
        $assignment=['kelas_id'=>(int)$p['kelas_id'],'mata_pelajaran_id'=>(int)$p['mata_pelajaran_id'],'guru_id'=>(int)$p['guru_id']];
        $old=null;
        if ($id) {
            $old=$db->table('jurnal_kelas')->where('id',$id)->get()->getRowArray();
            if (!$old) return $this->respond(['ok'=>false,'message'=>'Jurnal tidak ditemukan.'],404);
            if (!$this->allowed((int)$old['kelas_id'],(int)$old['mata_pelajaran_id']) || ($user['peran']==='guru' && (int)$old['dibuat_oleh']!==(int)$user['id'])) return $this->respond(['ok'=>false,'message'=>'Jurnal ini tidak dapat diubah.'],403);
            if ((int)$old['kelas_id']!==(int)$p['kelas_id']) return $this->respond(['ok'=>false,'message'=>'Kelas jurnal tidak dapat dipindahkan.'],422);
        }
        $unchangedAssignment=$old&&(int)$old['mata_pelajaran_id']===$assignment['mata_pelajaran_id']&&(int)$old['guru_id']===$assignment['guru_id'];
        if (!$unchangedAssignment&&!\App\Libraries\TeacherAssignments::has($assignment['kelas_id'],$assignment['guru_id'],$assignment['mata_pelajaran_id'])) return $this->respond(['ok'=>false,'message'=>'Guru belum ditugaskan pada kelas dan mata pelajaran ini.'],422);
        $data=$assignment+['tanggal'=>$p['tanggal'],'materi'=>trim($p['materi']),'catatan'=>trim($p['catatan']??''),'status'=>$p['status']];
        if ($id) $db->table('jurnal_kelas')->where('id',$id)->update($data);
        else {$data['dibuat_oleh']=$user['id'];$db->table('jurnal_kelas')->insert($data);$id=$db->insertID();}
        return $this->respond(['ok'=>true,'id'=>$id]);
    }
    public function delete(int $id)
    {
        $db=db_connect();$row=$db->table('jurnal_kelas')->where('id',$id)->get()->getRowArray();if(!$row)return $this->respond(['ok'=>false],404);
        if(($this->currentUser()['peran']??'')!=='wali_kelas'||!$this->allowed((int)$row['kelas_id']))return $this->respond(['message'=>'Jurnal ini tidak dapat dihapus.'],403);
        $db->table('jurnal_kelas')->where('id',$id)->delete();return $this->respond(['ok'=>true]);
    }
}
