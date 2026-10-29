<?php
namespace App\Libraries;

/** Durable outbox. Grade writers must hold the component lock in the same transaction. */
final class StudentGradeAutomation
{
    private $db;
    private $send;
    public function __construct($db=null, ?callable $send=null)
    {
        $this->db=$db??db_connect();
        $this->send=$send??static fn($phone,$text)=>(new WhatsAppGateway())->request($phone,$text);
    }
    public static function enabled(): bool
    {
        return filter_var(env('WHATSAPP_STUDENT_GRADE_AUTOMATION_ENABLED',ENVIRONMENT==='production'),FILTER_VALIDATE_BOOLEAN);
    }
    public function changed(int $student,int $component,string $semester,$before,$after): void
    {
        if(($before===null&&$after===null)||($before!==null&&$after!==null&&(float)$before===(float)$after))return;
        $key=['siswa_id'=>$student,'komponen_nilai_id'=>$component,'semester'=>$semester];
        $this->db->table('student_grade_dispatches')->where($key)->where('status','pending')->update(['status'=>'cancelled','result_note'=>'Dibatalkan karena nilai telah dikoreksi.']);
        if($after!==null&&(float)$after<80)$this->db->table('student_grade_dispatches')->insert($key+['nilai'=>$after]);
    }
    private function text(array $row): string
    {
        $greeting='Assalamualaikum';
        if(preg_match('/^timothy\s+frans(?:\s|$)/i',trim($row['nama_lengkap']))){
            $hour=(int)(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Jakarta')))->format('H');
            $greeting=$hour<11?'Selamat pagi':($hour<15?'Selamat siang':($hour<18?'Selamat sore':'Selamat malam'));
        }
        $component=preg_replace('/^UH\s*(\d+)$/i','Ulangan Harian $1',GradeComponents::label($row['nama_komponen']));
        $teacher='guru mata pelajaran';
        $author=$this->db->table('pengguna')->select('guru_id')->where('id',$row['dinilai_oleh'])->get()->getRowArray();
        $assignments=TeacherAssignments::rows((int)$row['kelas_id']);
        $matches=array_values(array_filter($assignments,static fn($a)=>(int)$a['mata_pelajaran_id']===(int)$row['mata_pelajaran_id']));
        usort($matches,static fn($a,$b)=>((int)$b['guru_id']===(int)($author['guru_id']??0))<=>((int)$a['guru_id']===(int)($author['guru_id']??0)));
        if($matches){
            $record=$this->db->table('guru')->where('id',$matches[0]['guru_id'])->get()->getRowArray();
            $teacher=($record['jenis_kelamin']==='L'?'Bpk ':'Ibu ').$record['nama_lengkap'];
        }
        $score=rtrim(rtrim(number_format((float)$row['nilai'],2,'.',''),'0'),'.');
        return $greeting.', '.$row['nama_lengkap'].'. Nilai anda pada mata pelajaran '.$row['nama_mata_pelajaran'].', komponen '.$component.', sebesar '.$score.' belum mencukupi sehingga anda berstatus Tidak Lulus. Mohon menghubungi '.$teacher.' untuk mengikuti ulangan perbaikan atau tugas tambahan.';
    }
    /** Explicit invocation only; CLI checks enablement/configuration before calling this. */
    public function run(?int $onlyStudent=null): array
    {
        $db=$this->db;$result=['sent'=>0,'failed'=>0,'unknown'=>0,'pending'=>0,'cancelled'=>0];$start=microtime(true);
        // A crashed/terminated send is uncertain; it must never be retried automatically.
        $stale=$db->table('student_grade_dispatches')->where('status','processing')->where('attempted_at <',date('Y-m-d H:i:s',time()-300));
        if($onlyStudent!==null)$stale->where('siswa_id',$onlyStudent);
        $stale->update(['status'=>'unknown','result_note'=>'Pengiriman terputus; periksa gateway sebelum mengirim ulang.']);
        $q=$db->table('student_grade_dispatches')->where('status','pending');
        if($onlyStudent!==null)$q->where('siswa_id',$onlyStudent);
        $jobs=$q->orderBy('id')->get()->getResultArray();
        foreach($jobs as $job){
            if(microtime(true)-$start>15)break; // Reserve 30 seconds for one gateway request; next minute resumes.
            $db->transBegin();
            // Same lock order as both grade writers; serialize recheck/claim with corrections.
            $db->query('SELECT id FROM komponen_nilai WHERE id=? FOR UPDATE',[$job['komponen_nilai_id']]);
            $current=$db->query('SELECT * FROM student_grade_dispatches WHERE id=? FOR UPDATE',[$job['id']])->getRowArray();
            if(!$current||$current['status']!=='pending'){$db->transCommit();continue;}
            $row=$db->table('nilai_siswa n')->select('n.nilai,n.dinilai_oleh,s.nama_lengkap,s.nomor_hp,s.aktif,s.kelas_id,k.mata_pelajaran_id,k.nama_komponen,c.wali_kelas_id,m.nama_mata_pelajaran')
                ->join('siswa s','s.id=n.siswa_id')->join('komponen_nilai k','k.id=n.komponen_nilai_id AND k.kelas_id=s.kelas_id AND k.aktif=1')->join('kelas c','c.id=s.kelas_id')->join('mata_pelajaran m','m.id=k.mata_pelajaran_id AND m.aktif=1')
                ->where(['n.siswa_id'=>$job['siswa_id'],'n.komponen_nilai_id'=>$job['komponen_nilai_id'],'n.semester'=>$job['semester']])->get()->getRowArray();
            if(!$row||!$row['aktif']||(float)$row['nilai']>=80||(float)$row['nilai']!==(float)$job['nilai']){
                $db->table('student_grade_dispatches')->where('id',$job['id'])->update(['status'=>'cancelled','result_note'=>'Nilai atau data siswa telah berubah.']);$db->transCommit();$result['cancelled']++;continue;
            }
            $phone=WhatsAppGateway::phone($row['nomor_hp']);
            if(!$row['wali_kelas_id']){$db->transCommit();$result['pending']++;continue;}
            $text=$this->text($row);$messageId=$current['message_id'];
            if(!$messageId){
                $db->table('pesan_komunikasi')->insert(['siswa_id'=>$job['siswa_id'],'kelas_id'=>$row['kelas_id'],'pengirim'=>'AIWalas Otomatis','penerima'=>$row['nama_lengkap'],'nomor_tujuan'=>$phone,'isi_pesan'=>$text,'kategori'=>'Nilai Siswa Otomatis','status_pengiriman'=>'Disiapkan','dibuat_oleh'=>$row['wali_kelas_id']]);
                $messageId=$db->insertID();
            }else $db->table('pesan_komunikasi')->where('id',$messageId)->update(['nomor_tujuan'=>$phone,'isi_pesan'=>$text]);
            $db->table('student_grade_dispatches')->where('id',$job['id'])->update(['message_id'=>$messageId,'status'=>$phone?'processing':'pending','attempted_at'=>$phone?date('Y-m-d H:i:s'):null,'result_note'=>$phone?'Sedang dikirim.':'Nomor WhatsApp siswa belum tersedia atau tidak valid.']);
            if(!$db->transStatus()){$db->transRollback();throw new \RuntimeException('Gagal mencatat antrean nilai.');}
            $db->transCommit();
            if(!$phone){$result['pending']++;continue;}
            $status='unknown';$note='Belum ada konfirmasi; periksa gateway sebelum mengirim ulang.';
            try{
                $response=($this->send)($phone,$text);$body=$response['response']??null;$confirmation=is_array($body)?($body['status']??$body['success']??null):null;
                if(($response['ok']??false)&&in_array($confirmation,[true,1,200,'true','success','sent'],true)){$status='sent';$note='Diterima gateway.';}
                elseif(!($response['ok']??false)&&in_array($confirmation,[false,0,'false','error','failed'],true)){$status='failed';$note='Gateway menolak pengiriman; perlu diperiksa.';}
            }catch(\Throwable $error){/* Delivery may have occurred before a timeout. */}
            $db->transBegin();
            $db->table('pesan_komunikasi')->where('id',$messageId)->update(['status_pengiriman'=>$status==='sent'?'Terkirim':($status==='failed'?'Gagal':'Disiapkan'),'dikirim_pada'=>$status==='sent'?date('Y-m-d H:i:s'):null]);
            $db->table('student_grade_dispatches')->where('id',$job['id'])->update(['status'=>$status,'result_note'=>$note]);
            if(!$db->transStatus()){$db->transRollback();throw new \RuntimeException('Gagal mencatat hasil pengiriman nilai.');}
            $db->transCommit();$result[$status]++;
        }
        return $result;
    }
}
