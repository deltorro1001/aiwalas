<?php
namespace App\Libraries;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

class TeacherScheduleAutomation
{
    private $db;
    private $send;
    private array $settings;
    private $elapsed;
    public function __construct($db=null,?callable $send=null,?array $settings=null,?callable $elapsed=null)
    {
        $this->db=$db??db_connect();
        $this->send=$send??static fn($phone,$text)=>(new WhatsAppGateway())->request($phone,$text);
        $this->settings=$settings??self::settings();
        $this->elapsed=$elapsed;
    }
    public static function settings(): array
    {
        return ['enabled'=>filter_var(env('WHATSAPP_TEACHER_AUTOMATION_ENABLED',false),FILTER_VALIDATE_BOOLEAN),
            'start'=>(string)env('WHATSAPP_TEACHER_AUTOMATION_START_DATE',''),
            'end'=>(string)env('WHATSAPP_TEACHER_AUTOMATION_END_DATE','2027-06-30'),
            'excluded'=>(string)env('WHATSAPP_TEACHER_AUTOMATION_EXCLUDED_DATES','')];
    }
    public const EXCLUDED_TEACHERS=['Wahyu Lukman Hakim','Putu Arya Ranesda','Ruby Eka Prawira'];
    public static function isExcludedTeacher(string $name): bool
    {
        $name=preg_replace('/^(?:(?:pak|bu|bpk|bapak|ibu|drs?|dra|prof)\.?\s+)+/i','',trim($name));
        $name=trim(preg_replace('/[^\p{L}\p{N}]+/u',' ',mb_strtolower($name)));
        foreach(self::EXCLUDED_TEACHERS as $teacher){$target=mb_strtolower($teacher);if($name===$target||str_starts_with($name,$target.' '))return true;}
        return false;
    }
    private static function zone(): DateTimeZone{return new DateTimeZone('Asia/Jakarta');}
    private static function date(string $date): void
    {
        $value=DateTimeImmutable::createFromFormat('!Y-m-d',$date,self::zone());
        if(!$value||$value->format('Y-m-d')!==$date)throw new InvalidArgumentException('Tanggal konfigurasi otomatis tidak valid.');
    }
    public function candidates(DateTimeImmutable $now,?int $classId=null): array
    {
        $now=$now->setTimezone(self::zone());
        $start=$this->settings['start'];$end=$this->settings['end'];
        if($start!=='')self::date($start);
        if($end==='')throw new InvalidArgumentException('Batas akhir pengiriman otomatis wajib diisi.');
        self::date($end);if($start!==''&&$start>$end)throw new InvalidArgumentException('Rentang tanggal otomatis tidak valid.');
        $excluded=array_values(array_filter(array_map('trim',explode(',',$this->settings['excluded']))));
        foreach($excluded as $date)self::date($date);
        $events=[];$days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        foreach([$now->setTime(0,0),$now->modify('+1 day')->setTime(0,0)] as $day){
            $date=$day->format('Y-m-d');
            if(($start!==''&&$date<$start)||$date>$end||in_array($date,$excluded,true))continue;
            $query=$this->db->table('jadwal_kelas j')->select('j.*,k.nama_kelas,k.wali_kelas_id,g.nama_lengkap AS teacher_name,g.nama_panggilan,g.nomor_hp,g.jenis_kelamin,m.nama_mata_pelajaran,u.aktif AS owner_active,u.peran AS owner_role')
                ->join('kelas k','k.id=j.kelas_id')->join('tahun_ajaran t','t.id=k.tahun_ajaran_id')
                ->join('guru g','g.id=j.guru_id')->join('mata_pelajaran m','m.id=j.mata_pelajaran_id')->join('pengguna u','u.id=k.wali_kelas_id','left')
                ->where(['t.aktif'=>1,'g.aktif'=>1,'m.aktif'=>1,'j.hari'=>$days[(int)$day->format('w')]]);
            if($classId!==null)$query->where('j.kelas_id',$classId);
            foreach($query->orderBy('j.jam_mulai')->get()->getResultArray() as $row){
                if(self::isExcludedTeacher($row['teacher_name']))continue;
                if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/',$row['jam_mulai'])||!preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/',$row['jam_selesai'])||$row['jam_mulai']>=$row['jam_selesai'])continue;
                $lessonStart=new DateTimeImmutable($date.' '.$row['jam_mulai'],self::zone());
                $lessonEnd=new DateTimeImmutable($date.' '.$row['jam_selesai'],self::zone());
                foreach(['reminder'=>$lessonStart->modify('-10 minutes'),'thanks'=>$lessonEnd] as $kind=>$due){
                    $identity=implode('|',[$row['kelas_id'],$row['guru_id'],$row['mata_pelajaran_id'],$date,$row['jam_mulai'],$row['jam_selesai'],$kind]);
                    $events[]=['key'=>hash('sha256',$identity),'schedule'=>$row,'date'=>$date,'kind'=>$kind,'due'=>$due,'phone'=>WhatsAppGateway::phone($row['nomor_hp']),'text'=>self::message($row,$kind,$due)];
                }
            }
        }
        usort($events,static fn($a,$b)=>$a['due']<=>$b['due']);return $events;
    }
    public static function message(array $row,string $kind,DateTimeImmutable $due): string
    {
        $hour=(int)$due->setTimezone(self::zone())->format('H');
        $greeting=$hour<11?'Selamat pagi':($hour<15?'Selamat siang':($hour<18?'Selamat sore':'Selamat malam'));
        $name=trim(preg_replace('/^(pak|bu|bapak|ibu|mom)\s+/i','',trim($row['nama_panggilan']?:$row['teacher_name'])));
        $title=$row['jenis_kelamin']==='L'?'Bpk.':($row['jenis_kelamin']==='P'?'Ibu':'Bpk./Ibu');
        $time=substr($row['jam_mulai'],0,5).'–'.substr($row['jam_selesai'],0,5).' WIB';
        $intro=$greeting.', '.$title.' '.$name.'. ';
        if($kind==='reminder')return $intro.'Kami mengingatkan bahwa 10 menit lagi '.$title.' dijadwalkan mengajar mata pelajaran '.$row['nama_mata_pelajaran'].' di kelas '.$row['nama_kelas'].', pukul '.$time.'. Jika berhalangan, mohon informasikan kepada Wali Kelas. Terima kasih.';
        return $intro.'Terima kasih atas waktu dan pendampingan '.$title.' pada jadwal mata pelajaran '.$row['nama_mata_pelajaran'].' di kelas '.$row['nama_kelas'].', pukul '.$time.'. Jika ada tugas atau informasi pembelajaran untuk siswa, mohon disampaikan kepada Wali Kelas. Terima kasih.';
    }
    public function run(DateTimeImmutable $now,bool $dryRun=true,?int $classId=null): array
    {
        $now=$now->setTimezone(self::zone());
        $result=['enabled'=>(bool)$this->settings['enabled'],'dry_run'=>$dryRun,'due'=>0,'sent'=>0,'failed'=>0,'unknown'=>0,'skipped'=>0,'duplicate'=>0,'missed'=>0];
        if(!$dryRun&&!$this->settings['enabled'])return $result;
        $started=hrtime(true);
        foreach($this->candidates($now,$classId) as $event){
            $age=$now->getTimestamp()-$event['due']->getTimestamp();
            if($age<0||$age>=60)continue;
            $result['due']++;
            if($dryRun)continue;
            $elapsed=$this->elapsed?($this->elapsed)():intdiv(hrtime(true)-$started,1000000000);
            if($age+$elapsed>=60){$result['missed']++;continue;}
            $status=$this->dispatch($event,$now);$result[$status]++;
        }
        return $result;
    }
    private function dispatch(array $event,DateTimeImmutable $now): string
    {
        $db=$this->db;$row=$event['schedule'];$token=bin2hex(random_bytes(16));$messageId=null;
        $db->transBegin();
        try{
            $db->query("INSERT INTO teacher_schedule_dispatches (session_key,claim_token,schedule_id,kelas_id,guru_id,lesson_date,event_type,due_at,attempted_at,status) VALUES (?,?,?,?,?,?,?,?,?,'processing') ON DUPLICATE KEY UPDATE id=id",[$event['key'],$token,$row['id'],$row['kelas_id'],$row['guru_id'],$event['date'],$event['kind'],$event['due']->format('Y-m-d H:i:s'),$now->format('Y-m-d H:i:s')]);
            $claim=$db->table('teacher_schedule_dispatches')->where('session_key',$event['key'])->get()->getRowArray();
            if(!$claim||!hash_equals($token,$claim['claim_token'])){$db->transRollback();return 'duplicate';}
            if(!$event['phone']||!(int)$row['wali_kelas_id']||!(int)$row['owner_active']||$row['owner_role']!=='wali_kelas'){
                $db->table('teacher_schedule_dispatches')->where('id',$claim['id'])->update(['status'=>'skipped','finished_at'=>$now->format('Y-m-d H:i:s'),'result_note'=>'Nomor guru atau Wali Kelas aktif tidak tersedia.']);
                $db->transCommit();return 'skipped';
            }
            $db->table('pesan_komunikasi')->insert(['kelas_id'=>$row['kelas_id'],'pengirim'=>'AIWalas Otomatis','penerima'=>$row['teacher_name'],'nomor_tujuan'=>$event['phone'],'isi_pesan'=>$event['text'],'kategori'=>$event['kind']==='reminder'?'Pengingat Guru Otomatis':'Terima Kasih Guru Otomatis','status_pengiriman'=>'Disiapkan','dibuat_oleh'=>$row['wali_kelas_id']]);
            $messageId=$db->insertID();
            $db->table('teacher_schedule_dispatches')->where('id',$claim['id'])->update(['message_id'=>$messageId]);
            if(!$db->transStatus())throw new RuntimeException('Gagal mencatat pengiriman otomatis.');
            $db->transCommit();
        }catch(\Throwable $error){$db->transRollback();throw $error;}
        try{
            $response=($this->send)($event['phone'],$event['text']);
            $body=$response['response']??null;
            $confirmation=is_array($body)?($body['status']??$body['success']??null):null;
            if(($response['ok']??false)&&in_array($confirmation,[true,1,200,'true','success','sent'],true)){$status='sent';$note='Diterima gateway.';}
            elseif(($response['ok']??false)===false&&is_array($body)&&in_array($confirmation,[false,0,'false','error','failed'],true)){$status='failed';$note='Gateway menolak pengiriman.';}
            else{$status='unknown';$note='Hasil gateway belum terkonfirmasi; periksa histori sebelum mengirim ulang.';}
        }catch(\Throwable $error){$status='unknown';$note='Koneksi gateway terputus; periksa histori sebelum mengirim ulang.';}
        $finished=(new DateTimeImmutable('now',self::zone()))->format('Y-m-d H:i:s');
        $db->transBegin();
        try{
            $db->table('teacher_schedule_dispatches')->where('id',$claim['id'])->update(['status'=>$status,'finished_at'=>$finished,'result_note'=>$note]);
            $db->table('pesan_komunikasi')->where('id',$messageId)->update(['status_pengiriman'=>$status==='sent'?'Terkirim':($status==='failed'?'Gagal':'Disiapkan'),'dikirim_pada'=>$status==='sent'?$finished:null]);
            $db->table('notifikasi')->insert(['pengguna_id'=>$row['wali_kelas_id'],'judul'=>mb_substr(($event['kind']==='reminder'?'Pengingat guru':'Ucapan terima kasih guru').' · '.$row['teacher_name'],0,180),'isi'=>$note.' '.$row['nama_mata_pelajaran'].' — '.$row['nama_kelas'],'jenis'=>'WhatsApp Otomatis','sudah_dibaca'=>0]);
            if(!$db->transStatus())throw new RuntimeException('Gagal menyimpan hasil pengiriman otomatis.');
            $db->transCommit();
        }catch(\Throwable $error){$db->transRollback();throw $error;}
        return $status;
    }
}
