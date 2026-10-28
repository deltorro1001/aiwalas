<?php
namespace App\Libraries;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

final class PhotoCollageDelivery
{
    private $db;
    private $send;
    private string $base;
    private bool $real;
    public function __construct($db=null,?callable $send=null,?string $base=null)
    {
        $this->db=$db??db_connect();$this->real=$send===null;
        $this->send=$send??static fn($phone,$text,$url)=>(new WhatsAppGateway())->request($phone,$text,'image',$url);
        $this->base=$base??base_url();
    }
    public function send(int $page,int $teacher,int $owner): array
    {
        $db=$this->db;
        $row=$db->table('foto_kolase_halaman h')->select('h.*,f.kelas_id,f.tanggal,f.snapshot,f.jumlah_foto,f.dibuat_pada,k.nama_kelas')->join('foto_kolase f','f.id=h.kolase_id')->join('kelas k','k.id=f.kelas_id')->where(['h.id'=>$page,'k.wali_kelas_id'=>$owner])->get()->getRowArray();
        if(!$row)throw new InvalidArgumentException('Kolase tidak ditemukan pada kelas Anda.');
        $recipient=$db->table('guru')->where(['id'=>$teacher,'aktif'=>1])->get()->getRowArray();
        $phone=WhatsAppGateway::phone($recipient['nomor_hp']??'');
        if(!$recipient||!$phone)throw new InvalidArgumentException('Pilih guru dengan nomor WhatsApp yang valid.');
        $photos=$db->table('foto_siswa')->select('id')->where(['kelas_id'=>$row['kelas_id'],'tanggal'=>$row['tanggal']])->orderBy('id')->get()->getResultArray();
        if(!hash_equals($row['snapshot'],StudentPhotoImages::snapshot($photos)))throw new InvalidArgumentException('Ada foto baru. Tekan Buat Kolase kembali agar seluruh foto ikut terkirim.');
        if(!is_file(StudentPhotoImages::path($row['berkas'])))throw new InvalidArgumentException('Berkas kolase tidak ditemukan. Hubungi pengelola aplikasi.');
        $host=strtolower((string)parse_url($this->base,PHP_URL_HOST));
        if(!in_array(parse_url($this->base,PHP_URL_SCHEME),['http','https'],true)||!$host||in_array($host,['localhost','::1','[::1]','0.0.0.0'],true)||str_starts_with($host,'127.'))throw new InvalidArgumentException('Gateway tidak dapat mengambil kolase dari localhost. Pengiriman tersedia setelah aplikasi memakai alamat publik.');
        if($this->real&&!(new WhatsAppGateway())->configured())throw new InvalidArgumentException('Gateway WhatsApp belum dikonfigurasi.');
        $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta'));
        $token=bin2hex(random_bytes(32));$stamp=$now->format('Y-m-d H:i:s');
        $count=$db->table('foto_kolase_halaman')->where('kolase_id',$row['kolase_id'])->countAllResults();
        if($count!==1||(int)$row['nomor']!==1||(int)$row['jumlah_foto']>StudentPhotoImages::MAX_PHOTOS)throw new InvalidArgumentException('Buat kolase kembali dalam format satu halaman, maksimal 35 foto.');
        $text='Kolase foto siswa kelas '.$row['nama_kelas'].', tanggal '.(new DateTimeImmutable($row['tanggal']))->format('d-m-Y').'. '.$row['jumlah_foto'].' foto dalam 1 halaman. Dibuat '.(new DateTimeImmutable($row['dibuat_pada']))->format('d-m-Y H:i:s').'.';
        $db->transBegin();
        try{
            $db->query('SELECT id FROM foto_kolase_halaman WHERE id=? FOR UPDATE',[$page]);
            $existing=$db->table('foto_kolase_pengiriman')->where(['halaman_id'=>$page,'guru_id'=>$teacher])->get()->getRowArray();
            if($existing){
                $db->transCommit();
                if($existing['status']==='sent')return ['ok'=>true,'status'=>'sent','already'=>true,'message'=>'Kolase ini sudah diterima gateway untuk guru tersebut.'];
                return ['ok'=>false,'status'=>$existing['status'],'already'=>true,'message'=>'Kolase ini pernah atau sedang diproses. Periksa status di Komunikasi sebelum mengirim ulang.'];
            }
            $db->table('pesan_komunikasi')->insert(['kelas_id'=>$row['kelas_id'],'pengirim'=>'AIWalas','penerima'=>$recipient['nama_lengkap'],'nomor_tujuan'=>$phone,'isi_pesan'=>$text,'kategori'=>'Kolase Foto Siswa','status_pengiriman'=>'Disiapkan','dibuat_oleh'=>$owner]);$message=$db->insertID();
            $db->table('foto_kolase_pengiriman')->insert(['halaman_id'=>$page,'guru_id'=>$teacher,'status'=>'processing','result_note'=>'Sedang dikirim ke gateway.','token_hash'=>hash('sha256',$token),'berlaku_sampai'=>$now->modify('+24 hours')->format('Y-m-d H:i:s'),'dicoba_pada'=>$stamp,'message_id'=>$message]);$dispatch=$db->insertID();
            if(!$db->transStatus())throw new RuntimeException('Pencatatan pengiriman gagal.');
            $db->transCommit();
        }catch(\Throwable $error){$db->transRollback();throw $error;}
        $status='unknown';$note='Belum ada konfirmasi; periksa gateway sebelum mengirim ulang.';
        try{
            $response=($this->send)($phone,$text,rtrim($this->base,'/').'/media/kolase/'.$token);$body=$response['response']??null;$confirmation=is_array($body)?($body['status']??$body['success']??null):null;
            if(($response['ok']??false)&&in_array($confirmation,[true,1,200,'true','success','sent'],true)){$status='sent';$note='Kolase diterima gateway.';}
            elseif(!($response['ok']??false)&&in_array($confirmation,[false,0,'false','error','failed'],true)){$status='failed';$note='Gateway menolak kolase; perlu diperiksa.';}
        }catch(\Throwable $error){/* A timeout may occur after the gateway accepted the media. */}
        $db->transBegin();
        $db->table('pesan_komunikasi')->where('id',$message)->update(['status_pengiriman'=>$status==='sent'?'Terkirim':($status==='failed'?'Gagal':'Disiapkan'),'dikirim_pada'=>$status==='sent'?$stamp:null]);
        $db->table('foto_kolase_pengiriman')->where('id',$dispatch)->update(['status'=>$status,'result_note'=>$note]);
        if(!$db->transStatus()){$db->transRollback();throw new RuntimeException('Hasil pengiriman belum tercatat. Periksa Komunikasi sebelum mencoba lagi.');}
        $db->transCommit();
        return ['ok'=>$status==='sent','status'=>$status,'already'=>false,'message'=>$note];
    }
}
