<?php
namespace App\Controllers\Api;

class PengajuanSakitController extends BaseApiController
{
    private function user(): array { return session()->get('pengguna') ?? []; }

    private function visibleQuery()
    {
        $u=$this->user();
        $q=db_connect()->table('pengajuan_sakit p')->join('siswa s','s.id=p.siswa_id')->join('kelas k','k.id=p.kelas_id');
        if(($u['peran']??'')==='orang_tua')return $q->where('p.siswa_id',(int)$u['anak_id']);
        if(($u['peran']??'')==='wali_kelas')return $q->where('k.wali_kelas_id',(int)$u['id']);
        return null;
    }

    public function index()
    {
        $q=$this->visibleQuery();
        if(!$q)return $this->respond(['message'=>'Pengajuan hanya dapat dilihat orangtua dan wali kelas.'],403);
        if(($this->user()['peran']??'')==='wali_kelas')$q->where('p.kelas_id',(int)$this->request->getGet('kelas_id'));
        $rows=$q->select('p.id,p.tanggal_mulai,p.tanggal_selesai,p.jumlah_hari,p.keterangan,p.nama_lampiran,p.dibuat_pada,s.nama_lengkap,s.nis')->orderBy('p.id','DESC')->get()->getResultArray();
        foreach($rows as &$row){
            $row['status']='Terkirim';
            $row['lampiran_url']=$row['nama_lampiran']?site_url('api/pengajuan-sakit/'.$row['id'].'/lampiran'):null;
        }
        $this->response->setHeader('Cache-Control','no-store');
        return $this->respond(['data'=>$rows]);
    }

    public function create()
    {
        $u=$this->user();
        if(($u['peran']??'')!=='orang_tua')return $this->respond(['message'=>'Pengajuan hanya dapat dikirim melalui akun orangtua.'],403);
        // All child/class identifiers come from the verified session, never the form.
        $student=db_connect()->table('siswa')->where(['id'=>(int)$u['anak_id'],'aktif'=>1])->get()->getRowArray();
        if(!$student)return $this->respond(['message'=>'Profil anak tidak ditemukan.'],404);
        $p=$this->request->getPost();
        $zone=new \DateTimeZone('Asia/Jakarta');
        $dates=[];
        foreach(['tanggal_mulai','tanggal_selesai'] as $key){
            $value=$p[$key]??null;
            $date=is_string($value)?\DateTimeImmutable::createFromFormat('!Y-m-d',$value,$zone):false;
            if(!$date||$date->format('Y-m-d')!==$value||(int)$date->format('Y')<2026||(int)$date->format('Y')>9998)return $this->respond(['message'=>'Isi tanggal mulai dan selesai dengan benar.'],422);
            $dates[]=$date;
        }
        [$start,$end]=$dates;
        $days=(int)$start->diff($end)->format('%r%a')+1;
        if($days<1||$days>60)return $this->respond(['message'=>'Rentang istirahat harus 1 sampai 60 hari; tanggal selesai tidak boleh sebelum tanggal mulai.'],422);
        if(!isset($p['jumlah_hari'])||!is_scalar($p['jumlah_hari'])||!preg_match('/^[1-9][0-9]?$/D',(string)$p['jumlah_hari'])||(int)$p['jumlah_hari']!==$days)return $this->respond(['message'=>'Jumlah hari harus sesuai rentang tanggal, termasuk tanggal mulai dan selesai.'],422);
        $note=$p['keterangan']??'';
        if(!is_string($note)||mb_strlen($note)>1000)return $this->respond(['message'=>'Keterangan maksimal 1.000 karakter.'],422);
        $row=['siswa_id'=>$student['id'],'kelas_id'=>$student['kelas_id'],'pengguna_id'=>$u['id'],'tanggal_mulai'=>$start->format('Y-m-d'),'tanggal_selesai'=>$end->format('Y-m-d'),'jumlah_hari'=>$days,'keterangan'=>trim($note)?:null];
        $file=$this->request->getFile('lampiran');$path=null;
        if($file&&$file->getError()!==UPLOAD_ERR_NO_FILE){
            if(!$file->isValid()||$file->getSize()>2*1024*1024)return $this->respond(['message'=>'Lampiran gagal diunggah atau melebihi 2 MB. Pilih kembali berkas.'],422);
            $types=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
            $mime=$file->getMimeType();
            if(!isset($types[$mime]))return $this->respond(['message'=>'Gunakan surat sakit berformat PDF, JPG, atau PNG.'],422);
            $row['nama_lampiran']=mb_substr(preg_replace('/[\x00-\x1F\x7F]/u','',basename(str_replace('\\','/',$file->getClientName()))),0,255);
            $row['berkas_lampiran']=bin2hex(random_bytes(24)).'.'.$types[$mime];
            $row['mime_lampiran']=$mime;
            $path=WRITEPATH.'pengajuan-sakit/'.$row['berkas_lampiran'];
        }
        $db=db_connect();
        try{
            // Serialize submissions per child to reject overlaps and double clicks.
            $db->transBegin();
            $db->query('SELECT id FROM siswa WHERE id=? FOR UPDATE',[$student['id']]);
            $overlap=$db->table('pengajuan_sakit')->where('siswa_id',$student['id'])->where('tanggal_mulai <=',$row['tanggal_selesai'])->where('tanggal_selesai >=',$row['tanggal_mulai'])->countAllResults();
            if($overlap){$db->transRollback();return $this->respond(['message'=>'Sudah ada pengajuan untuk tanggal tersebut. Periksa riwayat pengajuan atau hubungi wali kelas.'],409);}
            if($path)$file->move(WRITEPATH.'pengajuan-sakit',$row['berkas_lampiran']);
            $db->table('pengajuan_sakit')->insert($row);
            $id=$db->insertID();
            if($db->transStatus()===false)throw new \RuntimeException('Pengajuan gagal disimpan.');
            $db->transCommit();
        }catch(\Throwable $error){
            $db->transRollback();if($path&&is_file($path))unlink($path);
            log_message('error','Pengajuan sakit gagal: {error}',['error'=>$error->getMessage()]);
            return $this->respond(['message'=>'Pengajuan belum tersimpan. Silakan coba kembali.'],500);
        }
        return $this->respond(['ok'=>true,'id'=>$id,'message'=>'Pengajuan berhasil dikirim ke wali kelas.'],201);
    }

    public function attachment(int $id)
    {
        $q=$this->visibleQuery();
        if(!$q)return $this->respond(['message'=>'Anda tidak memiliki akses ke lampiran ini.'],403);
        $row=$q->select('p.berkas_lampiran,p.nama_lampiran,p.mime_lampiran')->where('p.id',$id)->get()->getRowArray();
        if(!$row||!$row['berkas_lampiran']||!is_file(WRITEPATH.'pengajuan-sakit/'.$row['berkas_lampiran']))return $this->respond(['message'=>'Lampiran tidak ditemukan.'],404);
        return $this->response->download(WRITEPATH.'pengajuan-sakit/'.$row['berkas_lampiran'],null)->setFileName($row['nama_lampiran'])->setHeader('Cache-Control','private, no-store')->setHeader('X-Content-Type-Options','nosniff');
    }
}
