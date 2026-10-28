<?php
namespace App\Controllers\Api;

use App\Libraries\PhotoCollageDelivery;
use App\Libraries\StudentPhotoImages;
use App\Libraries\WhatsAppGateway;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

class UploadFotoController extends BaseApiController
{
    private function user(): array {return session()->get('pengguna')??[];}
    private function today(): DateTimeImmutable {return new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta'));}
    private function student(): ?array
    {
        $user=$this->user();if(($user['peran']??'')!=='siswa')return null;
        return db_connect()->table('siswa')->where(['nis'=>$user['nis']??'','aktif'=>1])->get()->getRowArray();
    }
    private function ownedClass(int $id): ?array
    {
        if(($this->user()['peran']??'')!=='wali_kelas')return null;
        return db_connect()->table('kelas')->where(['id'=>$id,'wali_kelas_id'=>$this->user()['id']])->get()->getRowArray();
    }
    private function date($value): ?string
    {
        if(!is_string($value))return null;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('Asia/Jakarta'));
        return $date&&$date->format('Y-m-d')===$value?$value:null;
    }
    private function photos(int $class,string $date)
    {
        return db_connect()->table('foto_siswa f')->select('f.*,s.nama_lengkap,s.nis')->join('siswa s','s.id=f.siswa_id')->where(['f.kelas_id'=>$class,'f.tanggal'=>$date]);
    }
    private function collageData(?array $row,string $snapshot): ?array
    {
        if(!$row)return null;
        $pages=db_connect()->table('foto_kolase_halaman')->select('id,nomor')->where('kolase_id',$row['id'])->orderBy('nomor')->get()->getResultArray();
        foreach($pages as &$page)$page['url']=site_url('api/upload-foto/kolase/halaman/'.$page['id']);unset($page);
        return ['id'=>$row['id'],'jumlah_foto'=>$row['jumlah_foto'],'dibuat_pada'=>$row['dibuat_pada'],'terbaru'=>hash_equals($row['snapshot'],$snapshot),'halaman'=>$pages];
    }
    public function index()
    {
        $db=db_connect();$user=$this->user();$this->response->setHeader('Cache-Control','private, no-store');
        if(($user['peran']??'')==='siswa'){
            $student=$this->student();if(!$student)return $this->respond(['message'=>'Profil siswa tidak ditemukan.'],404);
            $rows=$db->table('foto_siswa')->select('id,keterangan,tanggal,diunggah_pada')->where('siswa_id',$student['id'])->orderBy('id','DESC')->limit(8)->get()->getResultArray();
            foreach($rows as &$row)$row['url']=site_url('api/upload-foto/'.$row['id'].'/gambar');unset($row);
            return $this->respond(['data'=>$rows,'can_process_photos'=>extension_loaded('gd')]);
        }
        $class=$this->ownedClass((int)$this->request->getGet('kelas_id'));if(!$class)return $this->respond(['message'=>'Kelas tidak dapat diakses.'],403);
        $date=$this->date($this->request->getGet('tanggal')??$this->today()->format('Y-m-d'));if(!$date)return $this->respond(['message'=>'Tanggal tidak valid.'],422);
        $page=max(1,min(100000,(int)$this->request->getGet('page')));$total=$this->photos((int)$class['id'],$date)->countAllResults();
        $rows=$this->photos((int)$class['id'],$date)->orderBy('f.id')->limit(24,($page-1)*24)->get()->getResultArray();
        foreach($rows as &$row){unset($row['berkas'],$row['request_key']);$row['url']=site_url('api/upload-foto/'.$row['id'].'/gambar');}unset($row);
        $ids=$db->table('foto_siswa')->select('id')->where(['kelas_id'=>$class['id'],'tanggal'=>$date])->orderBy('id')->get()->getResultArray();
        $collage=$db->table('foto_kolase')->where(['kelas_id'=>$class['id'],'tanggal'=>$date])->orderBy('id','DESC')->get()->getRowArray();
        $teachers=$db->table('guru')->select('id,nama_lengkap,nomor_hp')->where('aktif',1)->orderBy('nama_lengkap')->get()->getResultArray();
        foreach($teachers as &$teacher)$teacher['nomor_valid']=WhatsAppGateway::phone($teacher['nomor_hp'])!=='';unset($teacher);
        return $this->respond(['data'=>$rows,'tanggal'=>$date,'total'=>$total,'page'=>$page,'has_more'=>$page*24<$total,'guru'=>$teachers,'can_process_photos'=>extension_loaded('gd'),'kolase'=>$this->collageData($collage,StudentPhotoImages::snapshot($ids))]);
    }
    public function create()
    {
        $student=$this->student();if(!$student)return $this->respond(['message'=>'Foto hanya dapat diunggah melalui login siswa.'],403);
        $note=$this->request->getPost('keterangan');$key=$this->request->getPost('request_key');
        if(!is_string($note)||trim($note)===''||mb_strlen($note)>2000)return $this->respond(['message'=>'Isi keterangan foto, maksimal 2.000 karakter.'],422);
        if(!is_string($key)||!preg_match('/^[a-f0-9-]{36}$/D',$key))return $this->respond(['message'=>'Form unggah tidak valid. Muat ulang halaman.'],422);
        $db=db_connect();$existing=$db->table('foto_siswa')->where(['siswa_id'=>$student['id'],'request_key'=>$key])->get()->getRowArray();
        if($existing)return $this->respond(['ok'=>true,'id'=>$existing['id'],'message'=>'Foto ini sudah berhasil diunggah.']);
        $file=$this->request->getFile('foto');
        if(!$file||!$file->isValid()||$file->getSize()>2*1024*1024)return $this->respond(['message'=>'Pilih foto JPG, PNG, atau WebP maksimal 2 MB. Foto besar akan diperkecil otomatis melalui formulir.'],422);
        $name=bin2hex(random_bytes(24)).'.jpg';$path=StudentPhotoImages::path($name);$now=$this->today();
        try{
            $size=StudentPhotoImages::prepare($file->getTempName(),$path);
            $db->transBegin();$db->query('SELECT id FROM kelas WHERE id=? FOR UPDATE',[$student['kelas_id']]);$db->query('SELECT id FROM siswa WHERE id=? FOR UPDATE',[$student['id']]);
            $existing=$db->table('foto_siswa')->where(['siswa_id'=>$student['id'],'request_key'=>$key])->get()->getRowArray();
            if($existing){$db->transCommit();unlink($path);return $this->respond(['ok'=>true,'id'=>$existing['id'],'message'=>'Foto ini sudah berhasil diunggah.']);}
            $count=$db->table('foto_siswa')->where(['kelas_id'=>$student['kelas_id'],'tanggal'=>$now->format('Y-m-d')])->countAllResults();
            if($count>=StudentPhotoImages::MAX_PHOTOS){$db->transRollback();unlink($path);return $this->respond(['message'=>'Maksimal 35 foto per kelas pada tanggal yang sama sudah terkumpul.'],409);}
            $db->table('foto_siswa')->insert(['siswa_id'=>$student['id'],'kelas_id'=>$student['kelas_id'],'tanggal'=>$now->format('Y-m-d'),'diunggah_pada'=>$now->format('Y-m-d H:i:s'),'keterangan'=>trim($note),'berkas'=>$name,'request_key'=>$key]+$size);$id=$db->insertID();
            if(!$db->transStatus())throw new RuntimeException('Pencatatan foto gagal.');$db->transCommit();
        }catch(InvalidArgumentException $error){if(is_file($path))unlink($path);return $this->respond(['message'=>$error->getMessage()],422);}
        catch(\Throwable $error){$db->transRollback();if(is_file($path))unlink($path);log_message('error','Photo upload failed: {type}',['type'=>get_class($error)]);return $this->respond(['message'=>'Foto belum tersimpan. Silakan periksa riwayat sebelum mencoba lagi.'],500);}
        return $this->respond(['ok'=>true,'id'=>$id,'message'=>'Foto berhasil diunggah.'],201);
    }
    public function image(int $id)
    {
        $row=db_connect()->table('foto_siswa')->where('id',$id)->get()->getRowArray();$student=$this->student();
        if(!$row||!($this->ownedClass((int)$row['kelas_id'])||($student&&(int)$student['id']===(int)$row['siswa_id'])))return $this->respond(['message'=>'Foto tidak ditemukan.'],404);
        return $this->imageResponse($row['berkas']);
    }
    private function imageResponse(string $file)
    {
        $path=StudentPhotoImages::path($file);if(!is_file($path))return $this->respond(['message'=>'Berkas foto tidak ditemukan.'],404);
        return $this->response->setHeader('Content-Type','image/jpeg')->setHeader('Cache-Control','private, no-store')->setHeader('X-Content-Type-Options','nosniff')->setHeader('Content-Disposition',($this->request->getGet('download')==='1'?'attachment':'inline').'; filename="foto-aiwalas.jpg"')->setBody(file_get_contents($path));
    }
    public function build()
    {
        $p=$this->payload();$class=$this->ownedClass((int)($p['kelas_id']??0));$date=$this->date($p['tanggal']??null);
        if(!$class)return $this->respond(['message'=>'Kelas tidak dapat diakses.'],403);
        if(!$date)return $this->respond(['message'=>'Pilih tanggal kolase dengan benar.'],422);
        $db=db_connect();$lock='aiwalas_foto_'.$class['id'].'_'.$date;
        if(!(int)$db->query('SELECT GET_LOCK(?,0) AS acquired',[$lock])->getRowArray()['acquired'])return $this->respond(['message'=>'Kolase sedang dibuat. Tunggu sebentar, lalu muat ulang.'],409);
        $files=[];
        try{
            $photos=$this->photos((int)$class['id'],$date)->orderBy('f.id')->get()->getResultArray();
            if(!$photos)return $this->respond(['message'=>'Belum ada foto pada tanggal ini.'],422);
            $snapshot=StudentPhotoImages::snapshot($photos);$existing=$db->table('foto_kolase')->where(['kelas_id'=>$class['id'],'tanggal'=>$date,'snapshot'=>$snapshot])->get()->getRowArray();
            if($existing)return $this->respond(['ok'=>true,'data'=>$this->collageData($existing,$snapshot)]);
            if(count($photos)>StudentPhotoImages::MAX_PHOTOS)return $this->respond(['message'=>'Kolase maksimal 35 foto.'],422);
            $created=$this->today();$file=bin2hex(random_bytes(24)).'.jpg';$files[]=$file;
            StudentPhotoImages::collage($photos,$class['nama_kelas'],$date,$created,StudentPhotoImages::path($file));
            $db->transBegin();$db->table('foto_kolase')->insert(['kelas_id'=>$class['id'],'tanggal'=>$date,'snapshot'=>$snapshot,'jumlah_foto'=>count($photos),'dibuat_pada'=>$created->format('Y-m-d H:i:s')]);$id=$db->insertID();
            $db->table('foto_kolase_halaman')->insert(['kolase_id'=>$id,'nomor'=>1,'berkas'=>$file,'foto_ids'=>json_encode(array_column($photos,'id'))]);
            if(!$db->transStatus())throw new RuntimeException('Kolase belum tercatat.');$db->transCommit();
            return $this->respond(['ok'=>true,'data'=>$this->collageData($db->table('foto_kolase')->where('id',$id)->get()->getRowArray(),$snapshot)],201);
        }catch(\Throwable $error){$db->transRollback();foreach($files as $file)if(is_file(StudentPhotoImages::path($file)))unlink(StudentPhotoImages::path($file));log_message('error','Photo collage failed: {type}',['type'=>get_class($error)]);return $this->respond(['message'=>'Kolase belum berhasil dibuat. Muat ulang foto dan coba kembali.'],500);}
        finally{$db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    public function collageImage(int $id)
    {
        $row=db_connect()->table('foto_kolase_halaman h')->select('h.berkas,f.kelas_id')->join('foto_kolase f','f.id=h.kolase_id')->where('h.id',$id)->get()->getRowArray();
        if(!$row||!$this->ownedClass((int)$row['kelas_id']))return $this->respond(['message'=>'Kolase tidak ditemukan.'],404);
        return $this->imageResponse($row['berkas']);
    }
    public function send(int $id)
    {
        if(($this->user()['peran']??'')!=='wali_kelas')return $this->respond(['message'=>'Pengiriman hanya untuk wali kelas.'],403);
        $p=$this->payload();
        try{$result=(new PhotoCollageDelivery())->send($id,(int)($p['guru_id']??0),(int)$this->user()['id']);return $this->respond($result,$result['ok']?200:($result['already']?409:502));}
        catch(InvalidArgumentException $error){return $this->respond(['message'=>$error->getMessage()],422);}
        catch(\Throwable $error){log_message('error','Photo collage send failed: {type}',['type'=>get_class($error)]);return $this->respond(['message'=>'Pengiriman belum terkonfirmasi. Periksa Komunikasi sebelum mencoba lagi.'],500);}
    }
    public function media(string $token)
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))return $this->respond(['message'=>'Kolase tidak ditemukan.'],404);
        $row=db_connect()->table('foto_kolase_pengiriman d')->select('h.berkas')->join('foto_kolase_halaman h','h.id=d.halaman_id')->where('d.token_hash',hash('sha256',$token))->where('d.berlaku_sampai >=',$this->today()->format('Y-m-d H:i:s'))->get()->getRowArray();
        if(!$row)return $this->respond(['message'=>'Tautan kolase tidak tersedia atau kedaluwarsa.'],404);
        return $this->imageResponse($row['berkas']);
    }
}
