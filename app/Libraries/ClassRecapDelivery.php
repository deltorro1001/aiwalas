<?php
namespace App\Libraries;

final class ClassRecapDelivery
{
    private $db;
    private $send;
    private bool $real;
    private string $base;
    public function __construct($db=null,?callable $send=null,?string $base=null)
    {
        $this->db=$db??db_connect();$this->real=$send===null;$this->base=$base??base_url();
        $this->send=$send??static fn($phone,$text,$url,$filename)=>(new WhatsAppGateway())->request($phone,$text,'document',$url,$filename);
    }
    public function send(int $class,int $year,int $month,string $number,int $owner,string $recipient=''): array
    {
        if($year<2026||$year>9998||$month<1||$month>12)throw new \InvalidArgumentException('Bulan dan tahun rekap tidak valid.');
        $phone=WhatsAppGateway::phone($number);if(!$phone)throw new \InvalidArgumentException('Nomor WhatsApp tujuan tidak valid.');
        $recipient=trim($recipient);if(mb_strlen($recipient)>180)throw new \InvalidArgumentException('Nama penerima terlalu panjang.');
        $db=$this->db;$klass=$db->table('kelas')->select('id,nama_kelas')->where(['id'=>$class,'wali_kelas_id'=>$owner])->get()->getRowArray();
        if(!$klass)throw new \InvalidArgumentException('Kelas tidak dapat diakses.');
        $host=strtolower((string)parse_url($this->base,PHP_URL_HOST));
        if(!in_array(parse_url($this->base,PHP_URL_SCHEME),['http','https'],true)||!$host||in_array($host,['localhost','::1','[::1]','0.0.0.0'],true)||str_starts_with($host,'127.'))throw new \InvalidArgumentException('Gateway memerlukan alamat aplikasi publik untuk mengambil PDF. Pengiriman tersedia pada VPS.');
        if($this->real&&!(new WhatsAppGateway())->configured())throw new \InvalidArgumentException('Gateway WhatsApp belum siap. Periksa menu WhatsApp Gateway.');
        $semester=$month<=6?'Genap':'Ganjil';$data=ClassAttendanceRecap::build($klass,$year,$semester,$db);
        if(!$data['siswa'])throw new \InvalidArgumentException('Belum ada siswa aktif pada kelas ini.');
        $snapshot=hash('sha256',json_encode([$klass,$year,$month,$semester,ClassAttendancePdf::roster($data,$month)],JSON_THROW_ON_ERROR));
        $key=hash('sha256',implode('|',[$owner,$class,$year,$month,$phone,$snapshot]));
        $now=new \DateTimeImmutable('now',new \DateTimeZone('Asia/Jakarta'));$token=bin2hex(random_bytes(32));$file=bin2hex(random_bytes(32)).'.pdf';$path=WRITEPATH.'rekap-absen/'.$file;
        $period=ClassAttendancePdf::MONTHS[$month-1].' '.$year;
        $filename='Rekap-Absen-Kelas-'.preg_replace('/[^A-Za-z0-9_-]/','-',$klass['nama_kelas']).'-'.$year.'-'.sprintf('%02d',$month).'.pdf';
        $text='Berikut PDF Rekap Absen Kelas '.$klass['nama_kelas'].' untuk bulan '.$period.' (semester '.$semester.'). Berisi jumlah Sakit, Izin, Alpha, dan Keterlambatan setiap siswa berdasarkan Absensi Harian. Terima kasih.';
        $db->transBegin();
        try{
            $locked=$db->query('SELECT id FROM kelas WHERE id=? AND wali_kelas_id=? FOR UPDATE',[$class,$owner])->getRowArray();
            if(!$locked)throw new \InvalidArgumentException('Kelas tidak dapat diakses.');
            $existing=$db->table('rekap_absen_pengiriman')->where('session_key',$key)->get()->getRowArray();
            if($existing){$db->transCommit();return ['ok'=>$existing['status']==='sent','status'=>$existing['status'],'already'=>true,'message'=>$existing['status']==='sent'?'Rekap yang sama sudah diterima gateway untuk nomor ini.':'Rekap pernah atau sedang diproses. Periksa Komunikasi sebelum mengirim ulang.'];}
            $pdf=ClassAttendancePdf::create($data,$month,$now);
            if(!is_dir(dirname($path))&&!mkdir(dirname($path),0770,true)&&!is_dir(dirname($path)))throw new \RuntimeException('Penyimpanan PDF belum tersedia.');
            if(file_put_contents($path,$pdf,LOCK_EX)!==strlen($pdf))throw new \RuntimeException('PDF gagal disimpan.');
            $db->table('pesan_komunikasi')->insert(['kelas_id'=>$class,'pengirim'=>'AIWalas','penerima'=>$recipient?:$phone,'nomor_tujuan'=>$phone,'isi_pesan'=>$text,'kategori'=>'Rekap Absen Kelas PDF','status_pengiriman'=>'Disiapkan','dibuat_oleh'=>$owner]);$message=$db->insertID();
            $db->table('rekap_absen_pengiriman')->insert(['session_key'=>$key,'kelas_id'=>$class,'owner_id'=>$owner,'tahun'=>$year,'bulan'=>$month,'nomor_tujuan'=>$phone,'berkas'=>$file,'token_hash'=>hash('sha256',$token),'berlaku_sampai'=>$now->modify('+24 hours')->format('Y-m-d H:i:s'),'message_id'=>$message,'status'=>'processing','dibuat_pada'=>$now->format('Y-m-d H:i:s')]);$dispatch=$db->insertID();
            if(!$db->transStatus())throw new \RuntimeException('Pengiriman rekap belum dapat dicatat.');
            $db->transCommit();
        }catch(\Throwable $error){$db->transRollback();if(is_file($path))unlink($path);throw $error;}
        $status='unknown';$note='Gateway belum mengonfirmasi hasil. Periksa Komunikasi sebelum mengirim ulang.';
        try{
            $response=($this->send)($phone,$text,rtrim($this->base,'/').'/media/rekap-absen/'.$token,$filename);$body=$response['response']??[];$confirmation=$body['status']??$body['success']??null;
            if(($response['ok']??false)&&in_array($confirmation,[true,1,200,'true','success','sent'],true)){$status='sent';$note='PDF rekap diterima gateway.';}
            elseif(!($response['ok']??false)&&in_array($confirmation,[false,0,'false','error','failed'],true)){$status='failed';$note='Gateway menolak pengiriman rekap.';}
        }catch(\Throwable $error){$note='Koneksi gateway terputus. Periksa Komunikasi sebelum mengirim ulang.';}
        $db->transBegin();
        try{
            $db->table('rekap_absen_pengiriman')->where('id',$dispatch)->update(['status'=>$status,'result_note'=>$note]);
            $db->table('pesan_komunikasi')->where('id',$message)->update(['status_pengiriman'=>$status==='sent'?'Terkirim':($status==='failed'?'Gagal':'Disiapkan'),'dikirim_pada'=>$status==='sent'?$now->format('Y-m-d H:i:s'):null]);
            if(!$db->transStatus())throw new \RuntimeException('Hasil gateway belum dapat disimpan. Periksa Komunikasi.');
            $db->transCommit();
        }catch(\Throwable $error){$db->transRollback();throw $error;}
        return ['ok'=>$status==='sent','status'=>$status,'already'=>false,'message'=>$note];
    }
}
