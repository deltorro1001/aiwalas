<?php
// Integration regression against local development DB. All sends are injected fakes.
$root=dirname(__DIR__);
if(!preg_match('/^CI_ENVIRONMENT\s*=\s*development\s*$/m',file_get_contents($root.'/.env'))){fwrite(STDERR,"Development database required.\n");exit(1);}
define('ENVIRONMENT','development');define('FCPATH',$root.'/public/');require $root.'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
use App\Libraries\TeacherScheduleAutomation;
use DateTimeImmutable as Time;
use DateTimeZone as Zone;
$db=db_connect();$owner=null;$teacher=null;$subject=null;$class=null;$year=null;$calendarFixture=null;$attachmentFiles=[];
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
    $fixtureStudents=[];
    foreach(['Sakit Lampiran'=>'Sakit','Izin Lampiran'=>'Izin','Alpha Fixture'=>'Alpha','Sakit Tanpa Surat'=>'Sakit','Izin Tanpa Surat'=>'Izin','Hadir Fixture'=>'Hadir','Terlambat Fixture'=>'Terlambat','Menunggu Fixture'=>'Menunggu keterangan','Tanpa Absen'=>null,'Beda Semester'=>'Sakit','Beda Tanggal'=>'Sakit','Tidak Aktif'=>'Sakit','Lampiran Salah Status'=>'Izin','Lampiran Hilang'=>'Sakit'] as $name=>$status){
        $db->table('siswa')->insert(['nis'=>$prefix.'_'.count($fixtureStudents),'nama_lengkap'=>$name,'jenis_kelamin'=>'L','kelas_id'=>$class,'aktif'=>$name==='Tidak Aktif'?0:1]);
        $id=$db->insertID();$fixtureStudents[$name]=$id;
        if($status!==null)$db->table('absensi')->insert(['siswa_id'=>$id,'kelas_id'=>$class,'tanggal'=>$name==='Beda Tanggal'?'2026-10-21':'2026-10-22','semester'=>$name==='Beda Semester'?'Genap':'Ganjil','status'=>$status,'dicatat_oleh'=>$owner]);
    }
    foreach(['Sakit Lampiran'=>'Sakit','Izin Lampiran'=>'Izin','Lampiran Salah Status'=>'Sakit','Lampiran Hilang'=>'Sakit'] as $name=>$status){
        $folder=WRITEPATH.($status==='Sakit'?'pengajuan-sakit':'pengajuan-izin');
        if(!is_dir($folder))mkdir($folder,0775,true);
        $filename=bin2hex(random_bytes(24)).'.pdf';
        if($name!=='Lampiran Hilang'){$attachmentFiles[]=$folder.'/'.$filename;file_put_contents($folder.'/'.$filename,'Synthetic attachment fixture');}
        $request=['siswa_id'=>$fixtureStudents[$name],'kelas_id'=>$class,'pengguna_id'=>$owner,'tanggal_mulai'=>'2026-10-21','tanggal_selesai'=>'2026-10-23','jumlah_hari'=>3,'berkas_lampiran'=>$filename,'keterangan'=>'PRIVATE_REASON_NOT_FOR_TEACHER'];
        if($status==='Izin')$request['jenis_izin']='Keperluan keluarga';
        $db->table($status==='Sakit'?'pengajuan_sakit':'pengajuan_izin')->insert($request);
    }
    ensure($service->run($at('2026-10-22 07:50:20'),false,$class)['sent']===1,'Reminder not sent');
    ensure($calls[0][0]==='6281234567890'&&str_contains($calls[0][1],'10 menit lagi')&&str_contains($calls[0][1],$prefix),'Wrong reminder recipient or context');
    $text=$calls[0][1];
    ensure(str_contains($text,'Kami menginformasikan bahwa 10 menit lagi')&&!str_contains($text,'Kami mengingatkan')&&!str_contains($text,'1. Sakit:'),'Reminder wording not updated');
    foreach(['2. Izin:','3. Alpha:','4. Menunggu keterangan:','- Sakit Lampiran (surat sakit terlampir)','- Izin Lampiran (surat izin terlampir)','- Alpha Fixture','- Sakit Tanpa Surat','- Izin Tanpa Surat','- Menunggu Fixture'] as $expected)ensure(str_contains($text,$expected),'Missing absence detail: '.$expected);
    ensure(substr_count($text,'(surat sakit terlampir)')===1&&substr_count($text,'(surat izin terlampir)')===1,'Attachment matched wrong status or missing file');
    foreach(['Hadir Fixture','Terlambat Fixture','Tanpa Absen','Beda Semester','Beda Tanggal','Tidak Aktif','PRIVATE_REASON_NOT_FOR_TEACHER'] as $excluded)ensure(!str_contains($text,$excluded),'Unrelated/private attendance leaked: '.$excluded);
    ensure(str_contains($text,'22-10-2026'),'Wrong lesson date');
    ensure($db->table('pesan_komunikasi')->where('kelas_id',$class)->orderBy('id','DESC')->get()->getRowArray()['isi_pesan']===$text,'Stored history differs from actual reminder');
    ensure($service->run($at('2026-10-22 07:50:45'),false,$class)['duplicate']===1&&count($calls)===1,'Repeated reminder sent');
    $db->table('jadwal_kelas')->where('id',$schedule)->delete();$db->table('jadwal_kelas')->insert($base);$schedule=$db->insertID();
    ensure($service->run($at('2026-10-22 07:50:50'),false,$class)['duplicate']===1,'Reimport resent same session');
    ensure($service->run($at('2026-10-22 08:59:59'),false,$class)['sent']===0,'Thanks sent early');
    ensure($service->run($at('2026-10-22 09:00:00'),false,$class)['sent']===1&&str_contains($calls[1][1],'Terima kasih'),'Thanks not sent');
    ensure(!str_contains($calls[1][1],'Siswa tidak hadir'),'Absences appended to thanks');
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
    $failure=new TeacherScheduleAutomation($db,static function($phone,$text){ensure(!str_contains($text,'Status kehadiran siswa')&&!str_contains($text,'Seluruh siswa'),'Empty attendance incorrectly implies all present');return ['ok'=>false,'response'=>['status'=>false]];},$settings);
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
    foreach(['2027-01-07'=>'all','2027-01-14'=>'pending','2027-01-21'=>'sick','2027-01-28'=>'incomplete'] as $date=>$scenario){
        if($scenario==='all'||$scenario==='incomplete'){
            foreach($fixtureStudents as $name=>$student){
                if($name==='Tidak Aktif'||($scenario==='incomplete'&&$name==='Tanpa Absen'))continue;
                $db->table('absensi')->insert(['siswa_id'=>$student,'kelas_id'=>$class,'tanggal'=>$date,'semester'=>'Ganjil','status'=>'Hadir','dicatat_oleh'=>$owner]);
            }
        }else{
            $db->table('absensi')->insert(['siswa_id'=>$fixtureStudents['Menunggu Fixture'],'kelas_id'=>$class,'tanggal'=>$date,'semester'=>'Ganjil','status'=>$scenario==='pending'?'Menunggu keterangan':'Sakit','dicatat_oleh'=>$owner]);
        }
        $checked=false;
        $scenarioService=new TeacherScheduleAutomation($db,static function($phone,$text)use($scenario,&$checked){
            if($scenario==='all')ensure(str_contains($text,'Seluruh siswa berstatus Hadir pada hari ini')&&!str_contains($text,'Status kehadiran siswa'),'All-present narrative incorrect');
            elseif($scenario==='incomplete')ensure(!str_contains($text,'Seluruh siswa')&&!str_contains($text,'Status kehadiran siswa'),'Incomplete attendance reported as all present');
            else{
                $status=$scenario==='pending'?'Menunggu keterangan':'Sakit';
                ensure(($scenario==='sick'?!str_contains($text,'1. Sakit:'):str_contains($text,'1. '.$status.':'))&&str_contains($text,'- Menunggu Fixture')&&!str_contains($text,'2. ')&&!str_contains($text,'Seluruh siswa'),'Empty categories were included');
                ensure(!str_contains($text,'tidak dihitung sebagai Alpha'),'Old disclaimer remains');
            }
            $checked=true;return ['ok'=>true,'response'=>['status'=>true]];
        },$settings);
        ensure($scenarioService->run($at($date.' 07:50:00'),false,$class)['sent']===1&&$checked,'Attendance scenario failed: '.$scenario);
    }
    $db->table('jadwal_kelas')->insert(array_replace($base,['jam_selesai'=>'10:00:00']));
    $elapsed=0;$slowCalls=0;
    $slow=new TeacherScheduleAutomation($db,static function()use(&$elapsed,&$slowCalls){$slowCalls++;$elapsed=70;return ['ok'=>true,'response'=>['status'=>true]];},$settings,static function()use(&$elapsed){return $elapsed;});
    $slowResult=$slow->run($at('2026-12-03 07:50:00'),false,$class);
    ensure($slowCalls===1&&$slowResult['missed']===1,'Slow gateway caused a stale reminder send');
    $calendarDate='2099-02-05';
    ensure(!$db->table('kalender_akademik')->where('tanggal',$calendarDate)->countAllResults(),'Calendar fixture date already configured');
    $future=new TeacherScheduleAutomation($db,$send,array_replace($settings,['end'=>'2099-12-31']));
    $normalDue=$future->run($at($calendarDate.' 07:50:00'),true,$class)['due'];
    ensure($normalDue>0,'Fixture schedule missing');
    $db->table('kalender_akademik')->insert(['tanggal'=>$calendarDate,'jenis'=>'Hari Libur','judul'=>'Fixture scheduler calendar','dampak_absensi'=>'Libur']);$calendarFixture=$calendarDate;
    \App\Libraries\SchoolCalendar::reset();
    ensure($future->run($at($calendarDate.' 07:50:00'),true,$class)['due']===0,'Calendar holiday triggered teacher reminder');
    $db->table('kalender_akademik')->where('tanggal',$calendarDate)->update(['jenis'=>'PJJ','dampak_absensi'=>'PJJ']);\App\Libraries\SchoolCalendar::reset();
    ensure($future->run($at($calendarDate.' 07:50:00'),true,$class)['due']===$normalDue,'PJJ unexpectedly cancelled online teaching');
    $db->table('absensi')->insert(['siswa_id'=>$fixtureStudents['Sakit Lampiran'],'kelas_id'=>$class,'tanggal'=>$calendarDate,'semester'=>'Ganjil','status'=>'Sakit','dicatat_oleh'=>$owner]);
    $pjjCalls=0;
    $pjjService=new TeacherScheduleAutomation($db,static function($phone,$text)use(&$pjjCalls){$pjjCalls++;ensure(!str_contains($text,'Sakit Lampiran')&&!str_contains($text,'Status kehadiran siswa')&&!str_contains($text,'Seluruh siswa'),'PJJ override ignored in reminder');return ['ok'=>true,'response'=>['status'=>true]];},array_replace($settings,['end'=>'2099-12-31']));
    ensure($pjjService->run($at($calendarDate.' 07:50:00'),false,$class)['sent']===$normalDue&&$pjjCalls===$normalDue,'PJJ reminder failed');
    $db->table('kalender_akademik')->where('tanggal',$calendarDate)->delete();$calendarFixture=null;\App\Libraries\SchoolCalendar::reset();
    echo "PASS: Same-day/class/semester absences, matching real attachments, pending lists, empty categories, all-present/incomplete attendance, PJJ overrides, reminder-only history, teacher exclusions, timing H-10/end, dry run, idempotency, gateway outcomes and notifications. No real gateway calls.\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");$failed=true;}
finally{
    foreach($attachmentFiles as $file)if(is_file($file))unlink($file);
    if($calendarFixture){$db->table('kalender_akademik')->where('tanggal',$calendarFixture)->delete();\App\Libraries\SchoolCalendar::reset();}
    if($class){$db->table('siswa')->where('kelas_id',$class)->delete();$db->table('teacher_schedule_dispatches')->where('kelas_id',$class)->delete();$db->table('pesan_komunikasi')->where('kelas_id',$class)->delete();$db->table('jadwal_kelas')->where('kelas_id',$class)->delete();$db->table('kelas')->where('id',$class)->delete();}
    if($owner){$db->table('notifikasi')->where('pengguna_id',$owner)->delete();$db->table('pengguna')->where('id',$owner)->delete();}
    if($year)$db->table('tahun_ajaran')->where('id',$year)->delete();
    if($teacher)$db->table('guru')->where('id',$teacher)->delete();
    if($subject)$db->table('mata_pelajaran')->where('id',$subject)->delete();
}
if(isset($failed))exit(1);
