<?php
// Local DB regression with injected transports; never contacts wa.ats.co.id.
$root=dirname(__DIR__);
if(!preg_match('/^CI_ENVIRONMENT\s*=\s*development\s*$/m',file_get_contents($root.'/.env')))exit(1);
define('ENVIRONMENT','development');define('FCPATH',$root.'/public/');require $root.'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
use App\Libraries\PhotoCollageDelivery;
use App\Libraries\StudentPhotoImages;
$db=db_connect();$owner=$year=$class=$student=$group=$page=null;$teachers=[];$files=[];$calls=0;$prefix='collage_'.bin2hex(random_bytes(5));
function photoAssert($condition,$text){if(!$condition)throw new RuntimeException($text);}
try{
    $db->table('pengguna')->insert(['nama_pengguna'=>$prefix,'kata_sandi'=>password_hash('fixture',PASSWORD_DEFAULT),'nama_lengkap'=>'Fixture Wali','peran'=>'wali_kelas']);$owner=$db->insertID();
    $db->table('tahun_ajaran')->insert(['nama_tahun_ajaran'=>$prefix,'semester'=>'Ganjil','aktif'=>1]);$year=$db->insertID();
    $db->table('kelas')->insert(['nama_kelas'=>$prefix,'tingkat'=>11,'jurusan'=>'Test','tahun_ajaran_id'=>$year,'wali_kelas_id'=>$owner]);$class=$db->insertID();
    $db->table('siswa')->insert(['nis'=>$prefix,'nama_lengkap'=>'Fixture Siswa','jenis_kelamin'=>'L','kelas_id'=>$class]);$student=$db->insertID();
    for($i=0;$i<6;$i++){$db->table('guru')->insert(['nama_lengkap'=>$prefix.' Guru '.$i,'jenis_kelamin'=>'L','nomor_hp'=>$i===5?'':'081234567890','aktif'=>1]);$teachers[]=$db->insertID();}
    $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta'));$date=$now->format('Y-m-d');
    $file=bin2hex(random_bytes(24)).'.jpg';$files[]=$file;$image=imagecreatetruecolor(320,240);imagefill($image,0,0,imagecolorallocate($image,0,125,120));imagejpeg($image,StudentPhotoImages::path($file));imagedestroy($image);
    $db->table('foto_siswa')->insert(['siswa_id'=>$student,'kelas_id'=>$class,'tanggal'=>$date,'diunggah_pada'=>$now->format('Y-m-d H:i:s'),'keterangan'=>'Kegiatan fixture','berkas'=>$file,'lebar'=>320,'tinggi'=>240,'request_key'=>'11111111-1111-4111-8111-111111111111']);$photo=$db->insertID();
    $snapshot=StudentPhotoImages::snapshot([['id'=>$photo]]);
    $db->table('foto_kolase')->insert(['kelas_id'=>$class,'tanggal'=>$date,'snapshot'=>$snapshot,'jumlah_foto'=>1,'dibuat_pada'=>$now->format('Y-m-d H:i:s')]);$group=$db->insertID();
    $db->table('foto_kolase_halaman')->insert(['kolase_id'=>$group,'nomor'=>1,'berkas'=>$file,'foto_ids'=>json_encode([$photo])]);$page=$db->insertID();
    $token=null;
    $transport=static function($phone,$text,$url)use(&$calls,&$token,$db,$class){
        $calls++;photoAssert($phone==='6281234567890','Wrong teacher phone');photoAssert(str_contains($text,'1 halaman')&&str_contains($text,'Dibuat')&&!str_contains($text,'Timestamp'),'Wrong collage caption');
        photoAssert($db->table('pesan_komunikasi')->where('kelas_id',$class)->orderBy('id','DESC')->get()->getRowArray()['status_pengiriman']==='Disiapkan','Pending history not recorded before request');
        $token=basename(parse_url($url,PHP_URL_PATH));photoAssert((bool)preg_match('/^[a-f0-9]{64}$/D',$token),'Temporary token missing');
        photoAssert($db->table('foto_kolase_pengiriman')->where('token_hash',hash('sha256',$token))->countAllResults()===1,'Stored token hash mismatch');
        return ['ok'=>true,'response'=>['status'=>true]];
    };
    foreach([[$page,$teachers[0],$owner+1000000],[$page,$teachers[5],$owner]] as $args){
        try{(new PhotoCollageDelivery($db,$transport,'https://aiwalas.test/'))->send(...$args);throw new RuntimeException('Invalid recipient/owner accepted');}catch(InvalidArgumentException $expected){}
    }
    try{(new PhotoCollageDelivery($db,$transport,'http://localhost:8080/'))->send($page,$teachers[0],$owner);throw new RuntimeException('Local URL sent');}catch(InvalidArgumentException $expected){}
    photoAssert($calls===0&&!$db->table('pesan_komunikasi')->where('kelas_id',$class)->countAllResults(),'Validation contacted gateway');
    $service=new PhotoCollageDelivery($db,$transport,'https://aiwalas.test/');
    photoAssert($service->send($page,$teachers[0],$owner)['status']==='sent','Confirmed send not recorded');
    photoAssert($service->send($page,$teachers[0],$owner)['already']&&$calls===1,'Double click resent collage');
    // The capability URL serves only the generated page and expires, without exposing source photos.
    $url='http://localhost:8080/media/kolase/'.$token;
    $body=file_get_contents($url);photoAssert(str_starts_with($body,"\xff\xd8"),'Gateway cannot fetch temporary image');
    $db->table('foto_kolase_pengiriman')->where('token_hash',hash('sha256',$token))->update(['berlaku_sampai'=>'2020-01-01 00:00:00']);
    $body=file_get_contents($url,false,stream_context_create(['http'=>['ignore_errors'=>true]]));photoAssert(str_contains($http_response_header[0],'404'),'Expired token still works');
    $failure=new PhotoCollageDelivery($db,static fn()=>['ok'=>false,'response'=>['status'=>false]],'https://aiwalas.test/');photoAssert($failure->send($page,$teachers[1],$owner)['status']==='failed','Failure reported as sent');
    $unknown=new PhotoCollageDelivery($db,static fn()=>['ok'=>true,'response'=>['body'=>'unrecognized']],'https://aiwalas.test/');photoAssert($unknown->send($page,$teachers[2],$owner)['status']==='unknown','Ambiguous response reported as sent');
    $timeout=new PhotoCollageDelivery($db,static function(){throw new RuntimeException('Fake timeout');},'https://aiwalas.test/');photoAssert($timeout->send($page,$teachers[3],$owner)['status']==='unknown','Timeout not uncertain');
    $nested=null;$reentrant=new PhotoCollageDelivery($db,static function()use(&$nested,$service,$page,$teachers,$owner){$nested=$service->send($page,$teachers[4],$owner);return ['ok'=>true,'response'=>['status'=>true]];},'https://aiwalas.test/');
    photoAssert($reentrant->send($page,$teachers[4],$owner)['ok']&&$nested['already']&&!$nested['ok'],'Concurrent request duplicated transport');
    foreach([1,2,3] as $i)photoAssert($service->send($page,$teachers[$i],$owner)['already'],'Uncertain or rejected result automatically retried');
    photoAssert($calls===1,'Blocked dispatch contacted transport');
    photoAssert($db->table('pesan_komunikasi')->where(['kelas_id'=>$class,'status_pengiriman'=>'Terkirim'])->countAllResults()===2,'Sent history mismatch');
    $jar=tempnam(sys_get_temp_dir(),'photo-history-');$csrf='';$authenticated=false;
    $api=static function($path,$payload=null)use($jar,&$csrf){
        $curl=curl_init('http://localhost:8080/'.$path);$options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>15];
        if($payload!==null)$options+=[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-CSRF-TOKEN: '.$csrf]];
        curl_setopt_array($curl,$options);$body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);photoAssert($status===200,'History API request failed');
        if($path===''){preg_match('/name=[\'\"]csrf-token[\'\"] content=[\'\"]([^\'\"]+)/',$body,$match);$csrf=$match[1];return [];}
        $data=json_decode($body,true);$csrf=$data['csrfHash']??$csrf;return $data;
    };
    try{
        $api('');$api('api/auth/login',['role'=>'walas','username'=>$prefix,'password'=>'fixture']);$authenticated=true;
        $sent=$api('api/pesan?kelas_id='.$class.'&status=sent')['data'];$pending=$api('api/pesan?kelas_id='.$class.'&status=pending')['data'];
        photoAssert(count($sent)===2&&count($pending)===3,'Communication sent/pending history missing');
        foreach($pending as $entry)photoAssert($entry['delivery_status']==='Pending'&&$entry['result_note']&&$entry['attempted_at'],'Photo delivery note/timestamp missing from communication');
    }finally{if($authenticated)$api('api/auth/logout',[]);unlink($jar);}
    $db->table('foto_siswa')->insert(['siswa_id'=>$student,'kelas_id'=>$class,'tanggal'=>$date,'diunggah_pada'=>$now->format('Y-m-d H:i:s'),'keterangan'=>'New photo','berkas'=>$file,'lebar'=>320,'tinggi'=>240,'request_key'=>'22222222-2222-4222-8222-222222222222']);
    try{$service->send($page,$teachers[0],$owner);throw new RuntimeException('Stale collage accepted');}catch(InvalidArgumentException $expected){}
    echo "PASS: Teacher phone validation, ownership, private-image token/hash/expiry, pending-before-send history, confirmed/rejected/unknown/timeout responses, double-click and concurrent claims, no blind retries, stale snapshot rejected. All gateway requests injected fakes.\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");$failed=true;}
finally{
    if($class){$db->table('pesan_komunikasi')->where('kelas_id',$class)->delete();$db->table('siswa')->where('kelas_id',$class)->delete();$db->table('kelas')->where('id',$class)->delete();}
    foreach($teachers as $id)$db->table('guru')->where('id',$id)->delete();
    if($year)$db->table('tahun_ajaran')->where('id',$year)->delete();if($owner)$db->table('pengguna')->where('id',$owner)->delete();
    foreach($files as $file)if(is_file(StudentPhotoImages::path($file)))unlink(StudentPhotoImages::path($file));
}
if(isset($failed))exit(1);
