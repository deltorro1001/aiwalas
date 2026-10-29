<?php
// Real database regression; all fixtures and correction writes are rolled back.
$root=dirname(__DIR__);
if(!preg_match('/^CI_ENVIRONMENT\s*=\s*development\s*$/m',file_get_contents($root.'/.env')))exit('Development database required.');
define('ENVIRONMENT','development');define('FCPATH',$root.'/public/');require $root.'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
require_once $root.'/app/Database/Migrations/2026-10-29-050000_KoreksiMenungguAbsensiJuliSeptember2026.php';
use App\Database\Migrations\KoreksiMenungguAbsensiJuliSeptember2026 as Correction;
use App\Libraries\SchoolCalendar;
function correctionAssert($ok,$message){if(!$ok)throw new RuntimeException($message);}
$db=db_connect();$migration=new Correction();$auditExisted=$db->tableExists(Correction::AUDIT_TABLE,false);$migration->ensureAuditTable();
$originalRecords=$db->table('absensi')->orderBy('id')->get()->getResultArray();
$db->transBegin();
try{
    $tag='pending_fix_'.bin2hex(random_bytes(5));
    $year=$db->table('tahun_ajaran')->where('aktif',1)->whereIn('nama_tahun_ajaran',['2026/2027','2026-2027'])->get()->getRowArray();
    correctionAssert($year,'Active 2026/2027 school year required.');
    $db->table('pengguna')->insert(['nama_pengguna'=>$tag,'nama_lengkap'=>'Wali Uji Koreksi','kata_sandi'=>password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),'peran'=>'wali_kelas']);$owner=$db->insertID();
    $db->table('kelas')->insert(['nama_kelas'=>$tag,'tingkat'=>11,'jurusan'=>'Test','tahun_ajaran_id'=>$year['id'],'wali_kelas_id'=>$owner]);$class=$db->insertID();
    $students=[];
    foreach([1,1,0] as $i=>$active){$db->table('siswa')->insert(['nis'=>$tag.'_'.$i,'nama_lengkap'=>'Siswa Uji '.$i,'kelas_id'=>$class,'jenis_kelamin'=>'L','aktif'=>$active]);$students[]=$db->insertID();}
    $db->table('tahun_ajaran')->insert(['nama_tahun_ajaran'=>$tag,'semester'=>'Ganjil','aktif'=>0]);$oldYear=$db->insertID();
    $db->table('kelas')->insert(['nama_kelas'=>$tag.'_old','tingkat'=>11,'jurusan'=>'Test','tahun_ajaran_id'=>$oldYear,'wali_kelas_id'=>$owner]);$oldClass=$db->insertID();
    $db->table('siswa')->insert(['nis'=>$tag.'_old','nama_lengkap'=>'Siswa Tahun Lain','kelas_id'=>$oldClass,'jenis_kelamin'=>'L','aktif'=>1]);$oldStudent=$db->insertID();
    $seed=[
        ['2026-07-10','Menunggu keterangan'],['2026-07-13','Menunggu keterangan'],
        ['2026-07-14','Terlambat'],['2026-07-15','Sakit'],['2026-07-16','Izin'],['2026-07-17','Alpha'],
        ['2026-07-18','Menunggu keterangan'],['2026-07-20','PJJ'],['2026-07-21','Hadir'],
        ['2026-08-17','Menunggu keterangan'],['2026-08-18','Terlambat'],['2026-08-25','Sakit'],
        ['2026-08-27','Menunggu keterangan'],['2026-08-28','Alpha'],['2026-09-07','Izin'],['2026-09-08','Menunggu keterangan'],
        ['2026-09-30','Menunggu keterangan'],['2026-10-01','Menunggu keterangan'],
    ];
    foreach($seed as [$date,$status])$db->table('absensi')->insert(['siswa_id'=>$students[0],'kelas_id'=>$class,'tanggal'=>$date,'semester'=>'Ganjil','status'=>$status,'waktu_scan'=>'09:10:00','keterangan'=>'Catatan asli','dicatat_oleh'=>$owner]);
    $db->table('absensi')->insert(['siswa_id'=>$students[0],'kelas_id'=>$class,'tanggal'=>'2026-07-13','semester'=>'Genap','status'=>'Menunggu keterangan','waktu_scan'=>null,'dicatat_oleh'=>$owner]);
    $db->table('absensi')->insert(['siswa_id'=>$students[2],'kelas_id'=>$class,'tanggal'=>'2026-08-03','semester'=>'Ganjil','status'=>'Menunggu keterangan','waktu_scan'=>null,'dicatat_oleh'=>$owner]);
    $read=fn()=>$db->table('absensi')->where('kelas_id',$class)->orderBy('id')->get()->getResultArray();
    $before=$read();$migration->up();$after=$read();$byId=array_column($after,null,'id');
    foreach($before as $row){
        $expected=$row;
        if($row['status']==='Menunggu keterangan'&&$row['tanggal']>='2026-07-13'&&$row['tanggal']<='2026-09-30'&&!SchoolCalendar::isHoliday($row['tanggal'])&&!SchoolCalendar::isPjj($row['tanggal'])){
            $expected['status']='Hadir';$expected['waktu_scan']='06:30:00';
            $audit=$db->table(Correction::AUDIT_TABLE)->where('absensi_id',$row['id'])->get()->getRowArray();
            correctionAssert($audit&&$audit['status_sebelumnya']===$row['status']&&$audit['waktu_sebelumnya']===$row['waktu_scan']&&!(int)$audit['dibuat_baru'],'Original pending values not audited.');
        }
        correctionAssert($byId[$row['id']]===$expected,'An unrelated field, status or date changed.');
    }
    $dates=[];for($d=new DateTimeImmutable('2026-07-13');$d<=new DateTimeImmutable('2026-09-30');$d=$d->modify('+1 day'))if(!SchoolCalendar::isHoliday($d->format('Y-m-d'))&&!SchoolCalendar::isPjj($d->format('Y-m-d')))$dates[]=$d->format('Y-m-d');
    $new=$db->table('absensi')->where('siswa_id',$students[1])->orderBy('tanggal')->get()->getResultArray();
    correctionAssert(array_column($new,'tanggal')===$dates,'Missing days incorrectly filled or boundary/holiday/PJJ dates inserted.');
    foreach($new as $row){
        correctionAssert($row['status']==='Hadir'&&$row['waktu_scan']==='06:30:00'&&$row['semester']==='Ganjil'&&(int)$row['dicatat_oleh']===(int)$owner,'Incorrect new attendance values.');
        correctionAssert((int)$db->table(Correction::AUDIT_TABLE)->where('absensi_id',$row['id'])->get()->getRowArray()['dibuat_baru']===1,'New row not audited.');
    }
    correctionAssert($db->table('absensi')->where('siswa_id',$students[2])->countAllResults()===1,'Missing days inserted for an inactive student.');
    correctionAssert($db->table('absensi')->where('siswa_id',$oldStudent)->countAllResults()===0,'Another school year was filled.');
    $migration->up();correctionAssert($read()===$after,'Repeated correction changed or duplicated rows.');
    $db->table('absensi')->where('id',$new[0]['id'])->update(['status'=>'Izin','waktu_scan'=>null]);$custom=$read();
    $migration->up();correctionAssert($read()===$custom,'A later wali correction was overwritten.');
    echo "PASS: Inclusive Jul 13–Sep 30 boundaries, only pending rows changed to Hadir at 06:30, missing school-day records filled, all other fields/statuses and holiday/PJJ/weekends preserved, no inactive/other-year backfill, audit and idempotency. All test writes rolled back.\n";
}finally{
    $db->transRollback();
    correctionAssert($db->table('absensi')->orderBy('id')->get()->getResultArray()===$originalRecords,'Database records not restored by test rollback; retain audit for recovery.');
    if(!$auditExisted)$db->query('DROP TABLE '.Correction::AUDIT_TABLE);
}
