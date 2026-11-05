<?php
// Local disposable fixtures. Every gateway call is replaced with a fake.
$root=dirname(__DIR__);
if(!preg_match('/^CI_ENVIRONMENT\s*=\s*development\s*$/m',file_get_contents($root.'/.env')))exit(1);
define('ENVIRONMENT','development');define('FCPATH',$root.'/public/');require $root.'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
use App\Libraries\ClassRecapDelivery;
use App\Libraries\ClassAttendanceRecap;
use App\Libraries\ClassAttendancePdf;
$db=db_connect();$owner=null;$year=null;$class=null;$students=[];$calls=[];
function check($value,$message){if(!$value)throw new RuntimeException($message);}
$prefix='pdfrecap_'.bin2hex(random_bytes(5));
try{
    $db->table('pengguna')->insert(['nama_pengguna'=>$prefix,'nama_lengkap'=>'Wali Fixture','peran'=>'wali_kelas','kata_sandi'=>password_hash('test-only',PASSWORD_DEFAULT)]);$owner=$db->insertID();
    $db->table('tahun_ajaran')->insert(['nama_tahun_ajaran'=>$prefix,'semester'=>'Ganjil','aktif'=>1]);$year=$db->insertID();
    $db->table('kelas')->insert(['nama_kelas'=>'11PF1','tingkat'=>11,'jurusan'=>'Fixture','tahun_ajaran_id'=>$year,'wali_kelas_id'=>$owner]);$class=$db->insertID();
    foreach(['Siswa PDF A','=FORMULA','Nama Siswa Panjang '.str_repeat('Uji ',16)] as $i=>$name){$db->table('siswa')->insert(['nis'=>$prefix.$i,'nama_lengkap'=>$name,'jenis_kelamin'=>'L','kelas_id'=>$class,'aktif'=>1]);$students[]=$db->insertID();}
    foreach([[$students[0],'2026-10-01','Sakit','Ganjil'],[$students[0],'2026-10-02','Izin','Ganjil'],[$students[1],'2026-10-05','Terlambat','Ganjil'],[$students[1],'2026-10-06','Alpha','Ganjil'],[$students[0],'2026-11-02','Sakit','Ganjil'],[$students[0],'2026-01-02','Sakit','Genap'],[$students[0],'2026-08-17','Alpha','Ganjil']] as [$student,$date,$status,$semester])$db->table('absensi')->insert(['siswa_id'=>$student,'kelas_id'=>$class,'tanggal'=>$date,'status'=>$status,'semester'=>$semester,'dicatat_oleh'=>$owner]);
    $data=ClassAttendanceRecap::build(['id'=>$class,'nama_kelas'=>'11PF1'],2026,'Ganjil',$db);
    $roster=ClassAttendancePdf::roster($data,10);
    check(array_sum(array_column($roster,'sakit'))===1&&array_sum(array_column($roster,'izin'))===1&&array_sum(array_column($roster,'terlambat'))===1&&array_sum(array_column($roster,'alpha'))===1,'Monthly PDF differs from recap');
    $send=static function($phone,$text,$url,$filename)use(&$calls,$db,$class){
        $calls[]=compact('phone','text','url','filename');
        $history=$db->table('pesan_komunikasi')->where('kelas_id',$class)->orderBy('id','DESC')->get()->getRowArray();
        check($history['status_pengiriman']==='Disiapkan','Pending history missing before transport');
        check(str_contains($filename,'Rekap-Absen-Kelas-11PF1-2026-'),'Document filename missing context');
        $row=$db->table('rekap_absen_pengiriman')->where('token_hash',hash('sha256',basename($url)))->get()->getRowArray();
        check($row&&$row['status']==='processing'&&$row['message_id']===$history['id'],'Dispatch/token metadata missing');
        $pdf=file_get_contents(WRITEPATH.'rekap-absen/'.$row['berkas']);
        check(str_starts_with($pdf,'%PDF-1.4')&&str_ends_with($pdf,"%%EOF\n")&&str_contains($pdf,'/Count 1 /Kids'),'Invalid one-page PDF');
        check((new finfo(FILEINFO_MIME_TYPE))->buffer($pdf)==='application/pdf','Wrong file MIME');
        $ttl=new DateTimeImmutable($row['berlaku_sampai'],new DateTimeZone('Asia/Jakarta'));
        check($ttl->getTimestamp()>time()+23*3600,'Media expires too early');
        return ['ok'=>true,'response'=>['status'=>true]];
    };
    $delivery=new ClassRecapDelivery($db,$send,'https://example.test/');
    check($delivery->send($class,2026,10,'081234567890',$owner,'Ibu Nani')['status']==='sent','First PDF failed');
    check($calls[0]['phone']==='6281234567890'&&str_contains($calls[0]['text'],'Oktober 2026'),'Wrong period/recipient');
    check($delivery->send($class,2026,10,'6281234567890',$owner)['already']===true&&count($calls)===1,'Duplicate sent');
    check($delivery->send($class,2026,11,'081234567890',$owner)['status']==='sent','Different month blocked');
    check($delivery->send($class,2026,1,'081234567890',$owner)['status']==='sent'&&str_contains($calls[2]['text'],'Genap'),'Explicit January used wrong semester');
    $db->table('absensi')->insert(['siswa_id'=>$students[0],'kelas_id'=>$class,'tanggal'=>'2026-10-07','status'=>'Sakit','semester'=>'Ganjil','dicatat_oleh'=>$owner]);
    check($delivery->send($class,2026,10,'081234567890',$owner)['status']==='sent'&&count($calls)===4,'Correction did not refresh PDF');
    $unknown=new ClassRecapDelivery($db,static fn()=>['ok'=>true,'response'=>[]],'https://example.test/');
    check($unknown->send($class,2026,10,'081234567891',$owner)['status']==='unknown','Unknown result marked sent');
    check($unknown->send($class,2026,10,'081234567891',$owner)['already']===true,'Unknown result retried');
    $rejected=new ClassRecapDelivery($db,static fn()=>['ok'=>false,'response'=>['status'=>false]],'https://example.test/');
    check($rejected->send($class,2026,10,'081234567892',$owner)['status']==='failed','Explicit rejection ignored');
    foreach([[$class,2025,10,'081234567890',$owner],[$class,2026,13,'081234567890',$owner],[$class,2026,10,'invalid',$owner],[$class,2026,10,'081234567890',$owner+999999]] as $args){try{$delivery->send(...$args);throw new RuntimeException('Invalid request accepted');}catch(InvalidArgumentException $expected){}}
    try{(new ClassRecapDelivery($db,$send,'http://localhost:8080/'))->send($class,2026,10,'081234567890',$owner);throw new RuntimeException('Localhost media accepted');}catch(InvalidArgumentException $expected){}
    $many=$data;for($i=0;$i<60;$i++)$many['siswa'][]=$data['siswa'][0];
    $pdf=ClassAttendancePdf::create($many,10,new DateTimeImmutable('now'));check(preg_match('#/Count ([2-9]\d*) /Kids#',$pdf)===1,'Long roster truncated');
    file_put_contents(sys_get_temp_dir().'/aiwalas-recap-fixture.pdf',$pdf);
    // The generated first page can be inspected visually without publishing student data.
    preg_match('#stream\n(\xFF\xD8.*?)\nendstream#s',$pdf,$match);if($match)file_put_contents(sys_get_temp_dir().'/aiwalas-recap-fixture.jpg',$match[1]);
    echo "PASS: PDF roster/month/semester accuracy, pagination, token lifetime, pending-before-send history, phone normalization, duplicates/corrections, ownership/validation, localhost guard and gateway outcomes. No real sends.\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");$failed=true;}
finally{
    if($class){foreach($db->table('rekap_absen_pengiriman')->select('berkas')->where('kelas_id',$class)->get()->getResultArray() as $row){$file=WRITEPATH.'rekap-absen/'.$row['berkas'];if(is_file($file))unlink($file);}$db->table('rekap_absen_pengiriman')->where('kelas_id',$class)->delete();$db->table('pesan_komunikasi')->where('kelas_id',$class)->delete();$db->table('siswa')->where('kelas_id',$class)->delete();$db->table('kelas')->where('id',$class)->delete();}
    if($year)$db->table('tahun_ajaran')->where('id',$year)->delete();if($owner)$db->table('pengguna')->where('id',$owner)->delete();
}
if(isset($failed))exit(1);
