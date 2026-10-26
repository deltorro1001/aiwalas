<?php
// Local regression; all timetable changes remain inside a rolled-back transaction.
$root=dirname(__DIR__);
if(!preg_match('/^CI_ENVIRONMENT\s*=\s*development\s*$/m',file_get_contents($root.'/.env')))exit('Development database required.');
define('ENVIRONMENT','development');define('FCPATH',$root.'/public/');require $root.'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
require_once $root.'/app/Database/Migrations/2026-10-26-070000_LengkapiJadwalSelasaRabu11pf1.php';
$db=db_connect();
function timetableAssert($condition,$label){if(!$condition)throw new RuntimeException($label);}
$class=$db->table('kelas k')->select('k.id')->join('tahun_ajaran t','t.id=k.tahun_ajaran_id')->where(['k.nama_kelas'=>'11PF1','t.aktif'=>1,'t.semester'=>'Ganjil'])->whereIn('t.nama_tahun_ajaran',['2026/2027','2026-2027'])->get()->getRowArray();
timetableAssert($class,'Seeded 11PF1 class required.');$classId=$class['id'];
$read=static fn()=>$db->table('jadwal_kelas')->where('kelas_id',$classId)->whereIn('hari',['Selasa','Rabu'])->orderBy("FIELD(hari,'Selasa','Rabu')",'',false)->orderBy('jam_mulai')->get()->getResultArray();
$original=$read();$otherDays=$db->table('jadwal_kelas')->where('kelas_id',$classId)->whereNotIn('hari',['Selasa','Rabu'])->get()->getResultArray();
$migration=new App\Database\Migrations\LengkapiJadwalSelasaRabu11pf1();
$db->transBegin();
try{
    // Reproduce the three original seed sessions and their incorrect 09:00 end time.
    $db->table('jadwal_kelas')->where(['kelas_id'=>$classId,'hari'=>'Selasa'])->whereNotIn('jam_mulai',['06:30:00','09:00:00'])->delete();
    $db->table('jadwal_kelas')->where(['kelas_id'=>$classId,'hari'=>'Rabu'])->where('jam_mulai !=','06:30:00')->delete();
    $db->table('jadwal_kelas')->where(['kelas_id'=>$classId,'jam_mulai'=>'06:30:00'])->whereIn('hari',['Selasa','Rabu'])->update(['jam_selesai'=>'09:00:00','keterangan'=>null]);
    $migration->up();$rows=$read();
    timetableAssert(count($rows)===8,'Tuesday/Wednesday slots missing.');
    foreach(['Selasa','Rabu'] as $day){
        $sessions=array_values(array_filter($rows,static fn($row)=>$row['hari']===$day));
        timetableAssert(count($sessions)===4,'Expected four sessions per day.');
        timetableAssert(array_column($sessions,'jam_mulai')===['06:30:00','09:00:00','10:45:00','12:45:00'],'Incorrect start times.');
        timetableAssert(array_column($sessions,'jam_selesai')===['08:15:00','10:45:00','12:15:00','15:00:00'],'Incorrect end times.');
        foreach($sessions as $session)timetableAssert($session['guru_id']!==null,'Teacher missing.');
    }
    $migration->up();timetableAssert($read()===$rows,'Repeated migration duplicated/modified sessions.');
    $db->table('jadwal_kelas')->where('id',$rows[0]['id'])->update(['jam_selesai'=>'08:30:00','keterangan'=>'Custom first slot']);
    $db->table('jadwal_kelas')->where('id',$rows[2]['id'])->update(['jam_selesai'=>'12:00:00','keterangan'=>'Custom later slot']);
    $custom=$read();$migration->up();timetableAssert($read()===$custom,'Customized timetable overwritten.');
    timetableAssert($db->table('jadwal_kelas')->where('kelas_id',$classId)->whereNotIn('hari',['Selasa','Rabu'])->get()->getResultArray()===$otherDays,'Unrelated days changed.');
}finally{$db->transRollback();}
timetableAssert($read()===$original,'Test edits not rolled back.');
echo "PASS: Four Tuesday and four Wednesday sessions, corrected seeded times, assigned teachers, idempotency and custom/unrelated schedules preserved. All test edits rolled back.\n";
