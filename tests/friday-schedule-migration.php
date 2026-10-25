<?php
// Local data regression. All timetable edits are rolled back; no gateway calls.
$root=dirname(__DIR__);
if(!preg_match('/^CI_ENVIRONMENT\s*=\s*development\s*$/m',file_get_contents($root.'/.env')))exit('Development database required.');
define('ENVIRONMENT','development');define('FCPATH',$root.'/public/');require $root.'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
require_once $root.'/app/Database/Migrations/2026-10-23-060000_LengkapiJadwalJumat11pf1.php';
$db=db_connect();
function fridayAssert($condition,$label){if(!$condition)throw new RuntimeException($label);}
$class=$db->table('kelas k')->select('k.id')->join('tahun_ajaran t','t.id=k.tahun_ajaran_id')->where(['k.nama_kelas'=>'11PF1','t.aktif'=>1,'t.semester'=>'Ganjil'])->whereIn('t.nama_tahun_ajaran',['2026/2027','2026-2027'])->get()->getRowArray();
fridayAssert($class,'Seeded 11PF1 class required.');$classId=$class['id'];
$read=static fn()=>$db->table('jadwal_kelas')->where(['kelas_id'=>$classId,'hari'=>'Jumat'])->orderBy('jam_mulai')->get()->getResultArray();
$original=$read();$migration=new App\Database\Migrations\LengkapiJadwalJumat11pf1();
$db->transBegin();
try{
    // Reproduce the incomplete original seed while preserving its Naskah session.
    $db->table('jadwal_kelas')->where(['kelas_id'=>$classId,'hari'=>'Jumat'])->where('jam_mulai !=','07:30:00')->delete();
    $migration->up();$rows=$read();
    fridayAssert(count($rows)===5,'Missing Friday slots were not restored.');
    fridayAssert(array_column($rows,'jam_mulai')===['06:30:00','07:30:00','09:30:00','10:25:00','13:00:00'],'Incorrect Friday times.');
    fridayAssert($rows[0]['guru_id']===null&&$rows[0]['keterangan']==='Aktivitas sekolah','Activity assigned a teacher.');
    foreach(array_slice($rows,1) as $row)fridayAssert($row['guru_id']!==null,'Lesson missing teacher.');
    $migration->up();fridayAssert($read()===$rows,'Repeated restoration duplicated/changed sessions.');
    // A wali's customized slot must take precedence over the restoration template.
    $english=$rows[2];$db->table('jadwal_kelas')->where('id',$english['id'])->update(['jam_selesai'=>'10:20:00','keterangan'=>'Customized slot']);
    $custom=$read();$migration->up();fridayAssert($read()===$custom,'Customized/overlapping session overwritten.');
}finally{$db->transRollback();}
fridayAssert($read()===$original,'Test timetable changes were not rolled back.');
echo "PASS: Restores five Friday sessions with teachers, activity without teacher, idempotency, customized slots preserved; all test edits rolled back.\n";
