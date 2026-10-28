<?php
// Real local database; all gateway calls are injected fakes, never actual sends.
$root=dirname(__DIR__);
if(!preg_match('/^CI_ENVIRONMENT\s*=\s*development\s*$/m',file_get_contents($root.'/.env')))exit(1);
define('ENVIRONMENT','development');define('FCPATH',$root.'/public/');require $root.'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
use App\Libraries\StudentGradeAutomation;
$db=db_connect();$owner=$year=$class=$student=$subject=$component=$teacher=null;
function checkGrade($value,$message){if(!$value)throw new RuntimeException($message);}
$prefix='grade_'.bin2hex(random_bytes(5));$calls=[];
try{
    $db->table('pengguna')->insert(['nama_pengguna'=>$prefix,'kata_sandi'=>password_hash('fixture',PASSWORD_DEFAULT),'nama_lengkap'=>'Fixture Wali','peran'=>'wali_kelas']);$owner=$db->insertID();
    $db->table('tahun_ajaran')->insert(['nama_tahun_ajaran'=>$prefix,'semester'=>'Ganjil','aktif'=>1]);$year=$db->insertID();
    $db->table('kelas')->insert(['nama_kelas'=>$prefix,'tingkat'=>11,'jurusan'=>'Test','tahun_ajaran_id'=>$year,'wali_kelas_id'=>$owner]);$class=$db->insertID();
    $db->table('siswa')->insert(['nis'=>$prefix,'nama_lengkap'=>'Siswa Fixture','jenis_kelamin'=>'L','kelas_id'=>$class,'nomor_hp'=>'081234567890','nomor_hp_orang_tua'=>'089876543210']);$student=$db->insertID();
    $db->table('mata_pelajaran')->insert(['nama_mata_pelajaran'=>$prefix]);$subject=$db->insertID();
    $db->table('guru')->insert(['nama_lengkap'=>'Guru Fixture','jenis_kelamin'=>'L','aktif'=>1]);$teacher=$db->insertID();
    $db->table('pengampu_mapel')->insert(['guru_id'=>$teacher,'kelas_id'=>$class,'mata_pelajaran_id'=>$subject]);
    $db->table('komponen_nilai')->insert(['kelas_id'=>$class,'mata_pelajaran_id'=>$subject,'nama_komponen'=>'UH1','jenis_komponen'=>'UH']);$component=$db->insertID();
    $sender=static function($phone,$text)use(&$calls,$db,$class){
        checkGrade($phone==='6281234567890','Recipient must be student, never parent');
        checkGrade(str_contains($text,'Ulangan Harian 1')&&str_contains($text,'Bpk Guru Fixture')&&str_contains($text,'ulangan perbaikan atau tugas tambahan'),'Wrong message context');
        checkGrade($db->table('pesan_komunikasi')->where('kelas_id',$class)->orderBy('id','DESC')->get()->getRowArray()['status_pengiriman']==='Disiapkan','History must precede sending');
        $calls[]=[$phone,$text];return ['ok'=>true,'response'=>['status'=>true]];
    };
    $service=new StudentGradeAutomation($db,$sender);
    $save=static function($value,bool $rollback=false)use($db,$student,$component,$owner,$service){
        $db->transBegin();$db->query('SELECT id FROM komponen_nilai WHERE id=? FOR UPDATE',[$component]);
        $before=$db->table('nilai_siswa')->where(['siswa_id'=>$student,'komponen_nilai_id'=>$component,'semester'=>'Ganjil'])->get()->getRowArray();
        if($value===null)$db->table('nilai_siswa')->where(['siswa_id'=>$student,'komponen_nilai_id'=>$component,'semester'=>'Ganjil'])->delete();
        else $db->query("INSERT INTO nilai_siswa(siswa_id,komponen_nilai_id,semester,nilai,dinilai_oleh) VALUES (?,?,'Ganjil',?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)",[$student,$component,$value,$owner]);
        $service->changed($student,$component,'Ganjil',$before['nilai']??null,$value);
        if($rollback)$db->transRollback();else $db->transCommit();
    };
    $count=static fn()=>$db->table('student_grade_dispatches')->where('siswa_id',$student)->countAllResults();
    $save(80);$save(100);$save(null);checkGrade($count()===0,'Passing/deleted grades queued');
    $save(0,true);checkGrade($count()===0,'Rolled back grade queued');
    $save(0);$save(0);checkGrade($count()===1,'Zero missing or unchanged grade duplicated');
    $nested=null;
    $reentrant=new StudentGradeAutomation($db,static function($phone,$text)use(&$nested,$service,$student,$sender){$nested=$service->run($student);return $sender($phone,$text);});
    checkGrade($reentrant->run($student)['sent']===1&&count($calls)===1&&$nested['sent']===0,'Concurrent claim sent duplicate');
    checkGrade(str_contains($calls[0][1],'sebesar 0 belum'),'Zero formatted incorrectly');
    $save(0);$service->run($student);checkGrade(count($calls)===1,'Unchanged sent grade retriggered');
    $save(79.99);$save(70);checkGrade($service->run($student)['sent']===1&&count($calls)===2&&str_contains($calls[1][1],'sebesar 70'),'Superseded grade dispatched');
    $save(60);$save(80);checkGrade($service->run($student)['sent']===0&&count($calls)===2,'Passing correction not cancelled');
    $save(50);$save(null);checkGrade($service->run($student)['sent']===0,'Deleted grade dispatched');
    $db->table('siswa')->where('id',$student)->update(['nomor_hp'=>'']);$save(40);
    checkGrade($service->run($student)['pending']===1&&count($calls)===2,'Missing student phone used parent fallback');
    checkGrade($service->run($student)['pending']===1,'Missing phone lost queue');
    $db->table('siswa')->where('id',$student)->update(['nomor_hp'=>'081234567890']);
    checkGrade($service->run($student)['sent']===1&&count($calls)===3,'Phone fix did not resume pending job');
    $save(30);$failure=new StudentGradeAutomation($db,static fn()=>['ok'=>false,'response'=>['status'=>false]]);
    checkGrade($failure->run($student)['failed']===1,'Rejection reported as delivered');
    checkGrade($service->run($student)['sent']===0,'Rejected send automatically retried');
    $save(20);$unknown=new StudentGradeAutomation($db,static fn()=>['ok'=>true,'response'=>['body'=>'unexpected']]);
    checkGrade($unknown->run($student)['unknown']===1,'Unknown response reported as sent');
    $save(10);$timeout=new StudentGradeAutomation($db,static function(){throw new RuntimeException('Fake timeout');});
    checkGrade($timeout->run($student)['unknown']===1,'Timeout not marked uncertain');
    checkGrade($service->run($student)['sent']===0,'Uncertain sends automatically retried');
    $save(5);$db->table('siswa')->where('id',$student)->update(['aktif'=>0]);checkGrade($service->run($student)['cancelled']===1,'Inactive student messaged');
    $db->table('siswa')->where('id',$student)->update(['aktif'=>1]);$save(3);
    $db->table('student_grade_dispatches')->where(['siswa_id'=>$student,'status'=>'pending'])->update(['status'=>'processing','attempted_at'=>'2020-01-01 00:00:00']);
    checkGrade($service->run($student)['sent']===0,'Interrupted worker retried uncertain delivery');
    checkGrade($db->table('student_grade_dispatches')->where(['siswa_id'=>$student,'status'=>'unknown'])->countAllResults()===3,'Stale processing not marked unknown');
    checkGrade($db->table('pesan_komunikasi')->where(['kelas_id'=>$class,'status_pengiriman'=>'Terkirim'])->countAllResults()===3,'Sent history mismatch');
    checkGrade(!StudentGradeAutomation::enabled(),'Local automation enabled by default');
    echo "PASS: Below-80/zero, unchanged saves, rollback, superseded/corrected/deleted grades, atomic claims, student-only phone, missing-phone recovery, subject/component/teacher message, sent/pending history, rejection/unknown/timeout without retries. No real WhatsApp sent.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");$failed=true;}
finally{
    if($class){$db->table('pesan_komunikasi')->where('kelas_id',$class)->delete();$db->table('pengampu_mapel')->where('kelas_id',$class)->delete();$db->table('nilai_siswa')->where('siswa_id',$student)->delete();$db->table('komponen_nilai')->where('kelas_id',$class)->delete();$db->table('siswa')->where('kelas_id',$class)->delete();$db->table('kelas')->where('id',$class)->delete();}
    if($subject)$db->table('mata_pelajaran')->where('id',$subject)->delete();
    if($teacher)$db->table('guru')->where('id',$teacher)->delete();
    if($year)$db->table('tahun_ajaran')->where('id',$year)->delete();
    if($owner)$db->table('pengguna')->where('id',$owner)->delete();
}
if(isset($failed))exit(1);
