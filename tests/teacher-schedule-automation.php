<?php
// Integration regression against local development DB. All sends are injected fakes.
$root=dirname(__DIR__);
if(!preg_match('/^CI_ENVIRONMENT\s*=\s*development\s*$/m',file_get_contents($root.'/.env'))){fwrite(STDERR,"Development database required.\n");exit(1);}
define('ENVIRONMENT','development');define('FCPATH',$root.'/public/');require $root.'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
use App\Libraries\TeacherScheduleAutomation;
use DateTimeImmutable as Time;
use DateTimeZone as Zone;
$db=db_connect();$owner=null;$teacher=null;$subject=null;$class=null;$year=null;
function ensure($value,$message){if(!$value)throw new RuntimeException($message);}
$prefix='auto_'.bin2hex(random_bytes(5));$settings=['enabled'=>true,'start'=>'2026-07-01','end'=>'2027-06-30','excluded'=>''];
$zone=new Zone('Asia/Jakarta');$at=static fn($text)=>new Time($text,$zone);
try{
    $db->table('pengguna')->insert(['nama_pengguna'=>$prefix,'kata_sandi'=>password_hash('test-only-password',PASSWORD_DEFAULT),'nama_lengkap'=>'Automation Test','peran'=>'wali_kelas','aktif'=>1]);$owner=$db->insertID();
    $db->table('tahun_ajaran')->insert(['nama_tahun_ajaran'=>$prefix,'semester'=>'Ganjil','aktif'=>1]);$year=$db->insertID();
    $db->table('kelas')->insert(['nama_kelas'=>$prefix,'tingkat'=>11,'jurusan'=>'Test','tahun_ajaran_id'=>$year,'wali_kelas_id'=>$owner]);$class=$db->insertID();
    $db->table('guru')->insert(['nama_lengkap'=>'Ruby Test','nama_panggilan'=>'Pak Ruby','jenis_kelamin'=>'L','nomor_hp'=>'081234567890','aktif'=>1]);$teacher=$db->insertID();
    $db->table('mata_pelajaran')->insert(['nama_mata_pelajaran'=>$prefix,'aktif'=>1]);$subject=$db->insertID();
    $base=['kelas_id'=>$class,'guru_id'=>$teacher,'mata_pelajaran_id'=>$subject,'hari'=>'Kamis','jam_mulai'=>'08:00:00','jam_selesai'=>'09:00:00'];
    $db->table('jadwal_kelas')->insert($base);$schedule=$db->insertID();
    $calls=[];$send=static function($phone,$text)use(&$calls,$db,$class){ensure($db->table('pesan_komunikasi')->where('kelas_id',$class)->orderBy('id','DESC')->get()->getRowArray()['status_pengiriman']==='Disiapkan','Pending history missing before transport');$calls[]=[$phone,$text];return ['ok'=>true,'response'=>['status'=>true]];};
    $service=new TeacherScheduleAutomation($db,$send,$settings);
    ensure($service->run($at('2026-10-22 07:49:59'),false,$class)['sent']===0,'Sent early');
    ensure($service->run($at('2026-10-22 07:50:00'),true,$class)['due']===1&&!$calls,'Dry run sent a message');
    ensure((new TeacherScheduleAutomation($db,$send,array_replace($settings,['enabled'=>false])))->run($at('2026-10-22 07:50:00'),false,$class)['due']===0,'Disabled scheduler ran');
    foreach(['Wahyu Lukman Hakim, S.Kom.','Putu Arya Ranesda, S.Kom.','Ruby Eka Prawira, S.Pd.','  RUBY EKA PRAWIRA  ','Dr. Wahyu Lukman Hakim'] as $excludedName){
        $db->table('guru')->where('id',$teacher)->update(['nama_lengkap'=>$excludedName]);
        ensure($service->run($at('2026-10-22 07:50:00'),false,$class)['due']===0,'Excluded teacher received reminder');
        ensure($service->run($at('2026-10-22 09:00:00'),false,$class)['due']===0,'Excluded teacher received thanks');
    }
    ensure(!$calls&&!$db->table('teacher_schedule_dispatches')->where('kelas_id',$class)->countAllResults(),'Excluded teacher created a dispatch');
    ensure(!TeacherScheduleAutomation::isExcludedTeacher('Ruby Test')&&!TeacherScheduleAutomation::isExcludedTeacher('Wahyu Lukman Hakimah'),'Unrelated teacher excluded');
    $db->table('guru')->where('id',$teacher)->update(['nama_lengkap'=>'Ruby Test']);
    ensure($service->run($at('2026-10-22 07:50:20'),false,$class)['sent']===1,'Reminder not sent');
    ensure($calls[0][0]==='6281234567890'&&str_contains($calls[0][1],'10 menit lagi')&&str_contains($calls[0][1],$prefix),'Wrong reminder recipient or context');
    ensure($service->run($at('2026-10-22 07:50:45'),false,$class)['duplicate']===1&&count($calls)===1,'Repeated reminder sent');
    $db->table('jadwal_kelas')->where('id',$schedule)->delete();$db->table('jadwal_kelas')->insert($base);$schedule=$db->insertID();
    ensure($service->run($at('2026-10-22 07:50:50'),false,$class)['duplicate']===1,'Reimport resent same session');
    ensure($service->run($at('2026-10-22 08:59:59'),false,$class)['sent']===0,'Thanks sent early');
    ensure($service->run($at('2026-10-22 09:00:00'),false,$class)['sent']===1&&str_contains($calls[1][1],'Terima kasih'),'Thanks not sent');
    ensure($service->run($at('2026-10-22 09:00:20'),false,$class)['duplicate']===1,'Thanks repeated');
    ensure($service->run($at('2026-10-29 07:51:00'),false,$class)['due']===0,'Late reminder sent');
    ensure($service->run($at('2026-10-23 07:50:00'),false,$class)['due']===0,'Wrong weekday sent');
    ensure($service->run($at('2027-07-01 07:50:00'),false,$class)['due']===0,'Academic cutoff ignored');
    ensure((new TeacherScheduleAutomation($db,$send,array_replace($settings,['excluded'=>'2026-10-29'])))->run($at('2026-10-29 07:50:00'),false,$class)['due']===0,'Holiday ignored');
    ensure($service->run($at('2026-10-29 00:50:00 UTC'),true,$class)['due']===1,'Jakarta timezone incorrect');
    $db->table('tahun_ajaran')->where('id',$year)->update(['aktif'=>0]);ensure(!$service->run($at('2026-10-29 07:50:00'),true,$class)['due'],'Inactive year sent');$db->table('tahun_ajaran')->where('id',$year)->update(['aktif'=>1]);
    $db->table('guru')->where('id',$teacher)->update(['aktif'=>0]);ensure(!$service->run($at('2026-10-29 07:50:00'),true,$class)['due'],'Inactive teacher sent');$db->table('guru')->where('id',$teacher)->update(['aktif'=>1]);
    $db->table('guru')->where('id',$teacher)->update(['nomor_hp'=>'']);ensure($service->run($at('2026-10-29 07:50:00'),false,$class)['skipped']===1,'Missing phone not skipped');$db->table('guru')->where('id',$teacher)->update(['nomor_hp'=>'081234567890']);
    ensure($service->run($at('2026-10-29 07:50:30'),false,$class)['duplicate']===1,'Skipped event retried');
    $failure=new TeacherScheduleAutomation($db,static fn()=>['ok'=>false,'response'=>['status'=>false]],$settings);
    ensure($failure->run($at('2026-11-05 07:50:00'),false,$class)['failed']===1,'Gateway rejection reported as sent');
    $unknown=new TeacherScheduleAutomation($db,static fn()=>['ok'=>true,'response'=>['body'=>'unrecognized']],$settings);
    ensure($unknown->run($at('2026-11-12 07:50:00'),false,$class)['unknown']===1,'Unknown response reported as sent');
    ensure($service->run($at('2026-11-12 07:50:20'),false,$class)['duplicate']===1,'Unknown outcome retried');
    $throwing=new TeacherScheduleAutomation($db,static function(){throw new RuntimeException('Simulated timeout');},$settings);
    ensure($throwing->run($at('2026-11-19 07:50:00'),false,$class)['unknown']===1,'Timeout was not uncertain');
    $nestedResult=null;$nestedService=new TeacherScheduleAutomation($db,$send,$settings);
    $reentrant=new TeacherScheduleAutomation($db,static function($phone,$text)use(&$nestedResult,$nestedService,$at,$class){$nestedResult=$nestedService->run($at('2026-11-26 07:50:10'),false,$class);return ['ok'=>true,'response'=>['status'=>true]];},$settings);
    ensure($reentrant->run($at('2026-11-26 07:50:00'),false,$class)['sent']===1&&$nestedResult['duplicate']===1,'Concurrent claim duplicated send');
    $db->table('jadwal_kelas')->insert(array_replace($base,['hari'=>'Jumat','jam_mulai'=>'00:05:00','jam_selesai'=>'01:00:00']));
    ensure($service->run($at('2026-10-22 23:55:00'),true,$class)['due']===1,'Midnight reminder missed');
    ensure((new TeacherScheduleAutomation($db,$send,array_replace($settings,['excluded'=>'2026-10-23'])))->run($at('2026-10-22 23:55:00'),true,$class)['due']===0,'Next-day holiday ignored');
    ensure($db->table('pesan_komunikasi')->where(['kelas_id'=>$class,'status_pengiriman'=>'Terkirim'])->countAllResults()===3,'Sent history mismatch');
    ensure($db->table('notifikasi')->where('pengguna_id',$owner)->countAllResults()===6,'Notification audit mismatch');
    $db->table('jadwal_kelas')->insert(array_replace($base,['jam_selesai'=>'10:00:00']));
    $elapsed=0;$slowCalls=0;
    $slow=new TeacherScheduleAutomation($db,static function()use(&$elapsed,&$slowCalls){$slowCalls++;$elapsed=70;return ['ok'=>true,'response'=>['status'=>true]];},$settings,static function()use(&$elapsed){return $elapsed;});
    $slowResult=$slow->run($at('2026-12-03 07:50:00'),false,$class);
    ensure($slowCalls===1&&$slowResult['missed']===1,'Slow gateway caused a stale reminder send');
    echo "PASS: Teacher exclusions, pending-before-send history, timing H-10/end, dry run, disabled state, idempotency/reimport, missed windows, holidays, timezone/midnight, inactive data, missing phone, gateway rejection/unknown/timeout, concurrent claims, history and notifications. No real gateway calls.\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");$failed=true;}
finally{
    if($class){$db->table('teacher_schedule_dispatches')->where('kelas_id',$class)->delete();$db->table('pesan_komunikasi')->where('kelas_id',$class)->delete();$db->table('jadwal_kelas')->where('kelas_id',$class)->delete();$db->table('kelas')->where('id',$class)->delete();}
    if($owner){$db->table('notifikasi')->where('pengguna_id',$owner)->delete();$db->table('pengguna')->where('id',$owner)->delete();}
    if($year)$db->table('tahun_ajaran')->where('id',$year)->delete();
    if($teacher)$db->table('guru')->where('id',$teacher)->delete();
    if($subject)$db->table('mata_pelajaran')->where('id',$subject)->delete();
}
if(isset($failed))exit(1);
