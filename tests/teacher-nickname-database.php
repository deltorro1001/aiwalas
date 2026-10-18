<?php
// Run after the nickname account migration, against a local development database.
$root=dirname(__DIR__);
if(!preg_match('/^CI_ENVIRONMENT\s*=\s*development\s*$/m',file_get_contents($root.'/.env'))){fwrite(STDERR,"Development database required.\n");exit(1);}
define('ENVIRONMENT','development');
define('FCPATH',$root.'/public/');
require $root.'/app/Config/Paths.php';
$paths=new Config\Paths();
require $paths->systemDirectory.'/Boot.php';
CodeIgniter\Boot::bootConsole($paths);
use App\Libraries\TeacherNicknameAccounts;
if(ENVIRONMENT!=='development'){fwrite(STDERR,"Development database required.\n");exit(1);}
$db=db_connect();$fixture=null;
try {
    $teachers=$db->table('guru')->get()->getResultArray();
    foreach($teachers as $teacher){
        $account=$db->table('pengguna')->where('guru_id',$teacher['id'])->get()->getRowArray();
        $expected=TeacherNicknameAccounts::username($teacher);
        if(!$account||$account['nama_pengguna']!==$expected||!password_verify($expected,$account['kata_sandi']))throw new RuntimeException('Teacher nickname account mismatch.');
    }
    $snapshot=$db->table('pengguna')->orderBy('id')->get()->getResultArray();
    foreach(['Pak Ruby','sumantoro'] as $nickname){
        $db->table('guru')->insert(['nama_lengkap'=>'Nickname collision regression','nama_panggilan'=>$nickname,'aktif'=>1]);$fixture=$db->insertID();
        $failed=false;
        try{TeacherNicknameAccounts::provision($db);}catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'bentrok')||str_contains($e->getMessage(),'non-guru');}
        if(!$failed)throw new RuntimeException('Nickname collision was not blocked.');
        if($snapshot!==$db->table('pengguna')->orderBy('id')->get()->getResultArray())throw new RuntimeException('Collision changed existing accounts.');
        $db->table('guru')->where('id',$fixture)->delete();$fixture=null;
    }
    echo 'PASS: '.count($teachers)." teacher usernames and password hashes; duplicate nicknames and non-teacher username collisions leave all accounts unchanged.\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");exit(1);}
finally{if($fixture)$db->table('guru')->where('id',$fixture)->delete();}
