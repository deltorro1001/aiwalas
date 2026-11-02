<?php
namespace App\Controllers\Api;
use App\Libraries\SchoolCalendar;

class KalenderAkademikController extends BaseApiController
{
    public function index()
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        return $this->respond(['ok'=>true,'data'=>SchoolCalendar::clientData()]);
    }
    public function save()
    {
        $p=$this->payload();
        $errors=$this->validatePayload($p,[
            'tanggal_mulai'=>'required|valid_date[Y-m-d]',
            'tanggal_selesai'=>'required|valid_date[Y-m-d]',
            'jenis'=>'required|in_list[PJJ,Hari Libur,Hari Pengambilan Raport,Agenda Lainnya]',
            'judul'=>'permit_empty|max_length[160]',
            'dampak_absensi'=>'required|in_list[PJJ,Libur,Normal,TanpaAbsen]',
            'keterangan'=>'permit_empty|max_length[2000]',
        ]);
        if($errors)return $this->respond(['ok'=>false,'message'=>'Periksa tanggal, jenis agenda, judul, dan dampak absensi.','errors'=>$errors],422);
        $start=$p['tanggal_mulai'];$end=$p['tanggal_selesai'];
        $title=trim($p['judul']??'');
        if($title===''&&($p['tanpa_judul']??false)!==true)return $this->respond(['ok'=>false,'message'=>'Isi judul atau pilih Tanpa Judul Agenda.'],422);
        if($p['jenis']==='Hari Pengambilan Raport'&&$title==='Hari Pengambilan Raport')$title='Pengambilan Raport';
        if($start<'2026-01-01'||$end>'2100-12-31'||$end<$start||(new \DateTimeImmutable($start))->diff(new \DateTimeImmutable($end))->days>365)return $this->respond(['ok'=>false,'message'=>'Pilih rentang maksimal 366 hari, mulai tahun 2026 hingga 2100. '],422);
        $effect=$p['jenis']==='Hari Pengambilan Raport'?'TanpaAbsen':($p['jenis']==='PJJ'?'PJJ':($p['jenis']==='Hari Libur'?'Libur':$p['dampak_absensi']));
        $db=db_connect();$db->transBegin();
        try{
            for($day=new \DateTimeImmutable($start);$day->format('Y-m-d')<=$end;$day=$day->modify('+1 day')){
                $db->query('INSERT INTO kalender_akademik (tanggal,jenis,judul,dampak_absensi,keterangan,dibuat_oleh,diperbarui_pada) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE jenis=VALUES(jenis),judul=VALUES(judul),dampak_absensi=VALUES(dampak_absensi),keterangan=VALUES(keterangan),dibuat_oleh=VALUES(dibuat_oleh),diperbarui_pada=VALUES(diperbarui_pada)',[$day->format('Y-m-d'),$p['jenis'],$title,$effect,trim($p['keterangan']??''),(int)$this->currentUser()['id'],date('Y-m-d H:i:s')]);
            }
            if($db->transStatus()===false)throw new \RuntimeException('Transaksi gagal.');
            $db->transCommit();SchoolCalendar::reset();
            return $this->respond(['ok'=>true,'message'=>'Kalender akademik disimpan. Status absensi terkait mengikuti kalender di seluruh login.','data'=>SchoolCalendar::clientData()]);
        }catch(\Throwable $error){$db->transRollback();throw $error;}
    }
    public function delete(string $date)
    {
        $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$day||$day->format('Y-m-d')!==$date)return $this->respond(['ok'=>false,'message'=>'Tanggal tidak valid.'],422);
        db_connect()->table('kalender_akademik')->where('tanggal',$date)->delete();SchoolCalendar::reset();
        return $this->respond(['ok'=>true,'message'=>'Agenda sekolah dihapus. Tanggal kembali mengikuti kalender pemerintah dan akhir pekan.','data'=>SchoolCalendar::clientData()]);
    }
}
