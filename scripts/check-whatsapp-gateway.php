<?php
// CLI-only readiness check. Refreshes the existing session with a read-only status request.
// Never pairs, disconnects, sends messages, or prints credentials/session identifiers.
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);
define('ENVIRONMENT',getenv('CI_ENVIRONMENT')?:'production');
define('FCPATH',$root.'/public/');
require $root.'/app/Config/Paths.php';
$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';
CodeIgniter\Boot::bootConsole($paths);

try {
    $gateway=new App\Libraries\WhatsAppGateway();$s=$gateway->settings();
    $session=$s['session_id']??null;
    $validSession=is_string($session)&&preg_match('/^[a-zA-Z0-9_-]{1,36}$/D',$session);
    $diagnostic=['api_key_present'=>!empty($s['api_key']),'session_present'=>!empty($session),'session_format_valid'=>(bool)$validSession,'saved_status'=>$s['status']??'MISSING'];
    if($validSession&&!empty($s['api_key'])) {
        $result=$gateway->api('GET','sessions/'.$session.'/status');
        $diagnostic['status_http_code']=$result['http_code'];
        $data=$result['data'];if(is_array($data['data']??null))$data=$data['data'];
        $status=$data['status']??null;
        if($result['ok']&&in_array($status,['CONNECTED','DISCONNECTED','INITIALIZING','NEED_QR','FAILED'],true)) {
            db_connect()->table('whatsapp_gateway_config')->where('id',1)->where('session_id',$session)->update(['status'=>$status]);
            $diagnostic['remote_status']=$status;
        }
    }
    $diagnostic['gateway_ready']=$gateway->configured();
    echo json_encode($diagnostic,JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit($diagnostic['gateway_ready']?0:1);
}catch(Throwable $error){fwrite(STDERR,'Gateway readiness check failed: '.get_class($error).PHP_EOL);exit(1);}
