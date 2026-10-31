<?php
// No HTTP calls or application database: inject the same settings/transport contract.
require dirname(__DIR__).'/app/Libraries/WhatsAppGateway.php';
use App\Libraries\WhatsAppGateway;
function check($value,$message){if(!$value)throw new RuntimeException($message);}
$settings=['api_base_url'=>'https://gateway.example.test/api/v1','api_key'=>'fake-sims-key','session_id'=>'session-school','status'=>'CONNECTED'];
$raw=['http_code'=>200,'body'=>'{"success":true,"details":{"id":"fake-message"}}','error'=>''];$calls=[];$reads=0;
$gateway=new WhatsAppGateway(function($method,$url,$headers,$payload)use(&$calls,&$raw){$calls[]=compact('method','url','headers','payload');return $raw;},function()use(&$settings,&$reads){$reads++;return $settings;});
check($gateway->configured(),'Connected menu settings not detected');$reads=0;
$r=$gateway->request('081234567890','Test reminder');check($r['ok']&&$r['outcome']==='sent'&&$r['response']['status']===true,'Explicit gateway confirmation rejected');check($reads===1,'Send did not use a single settings snapshot');
$call=end($calls);check($call['url']==='https://gateway.example.test/api/v1/message/send-text','Wrong SIMS text endpoint');check($call['payload']===['sessionId'=>'session-school','to'=>'6281234567890','text'=>'Test reminder'],'Wrong shared session/text payload');check(in_array('Authorization: Bearer fake-sims-key',$call['headers'],true),'Missing bearer auth');
$r=$gateway->request('6281234567890','Caption','document','https://example.test/report.pdf');$call=end($calls);check($r['ok']&&str_ends_with($call['url'],'/message/send-media')&&$call['payload']['filename']==='Laporan-Wali-Kelas.pdf'&&$call['payload']['mimetype']==='application/pdf','PDF transport broken');
$gateway->request('6281234567890','Collage','image','https://example.test/collage.jpg');$call=end($calls);check($call['payload']['mediaType']==='image'&&$call['payload']['caption']==='Collage'&&!isset($call['payload']['filename']),'Collage payload broken');
$settings['session_id']='new-school-session';$gateway->request('6281234567890','New phone');check(end($calls)['payload']['sessionId']==='new-school-session','New menu session ignored');
$raw['body']='{"success":false,"message":"fake-sims-key"}';$r=$gateway->request('6281234567890','Rejected');check(!$r['ok']&&$r['outcome']==='failed'&&!str_contains(json_encode($r),'fake-sims-key'),'Rejection or secret redaction broken');
$raw['body']='{"success":true,"status":"queued"}';check($gateway->request('6281234567890','Accepted')['ok'],'Accepted response with status string misclassified');
$raw['body']='{"message":"ok"}';$r=$gateway->request('6281234567890','Uncertain');check(!$r['ok']&&$r['outcome']==='unknown'&&!isset($r['response']['status']),'Unconfirmed success reported as sent');
$raw['body']='not json';check($gateway->request('6281234567890','Uncertain')['outcome']==='unknown','Malformed response reported as sent');
$raw=['http_code'=>500,'body'=>'{"success":true}','error'=>''];check($gateway->request('6281234567890','Server error')['outcome']==='unknown','HTTP failure reported as sent');
$raw=['http_code'=>401,'body'=>'{"message":"fake-sims-key"}','error'=>''];$r=$gateway->request('6281234567890','Auth failure');check(!$r['ok']&&$r['outcome']==='failed'&&!str_contains(json_encode($r),'fake-sims-key'),'Unauthorized result not sanitized');
$raw=['http_code'=>0,'body'=>'','error'=>'timeout fake-sims-key'];$before=count($calls);$r=$gateway->request('6281234567890','Timeout');check(!$r['ok']&&$r['outcome']==='unknown'&&count($calls)===$before+1&&!str_contains(json_encode($r),'fake-sims-key'),'Timeout retried or leaked credentials');
$before=count($calls);$settings['status']='DISCONNECTED';check(!$gateway->configured()&&!$gateway->request('6281234567890','Offline')['ok']&&count($calls)===$before,'Disconnected menu session contacted gateway');$settings['status']='CONNECTED';$settings['api_key']='';check(!$gateway->request('6281234567890','Missing key')['ok']&&count($calls)===$before,'Missing key contacted gateway');
$settings=['WHATSAPP_GATEWAY_URL'=>'https://retired.example.test','WHATSAPP_GATEWAY_API_KEY'=>'retired-key','WHATSAPP_GATEWAY_SENDER'=>'6281234567890'];check(!$gateway->configured(),'Retired configuration used as a fallback');
$settings=['api_base_url'=>'https://gateway.example.test/api/v1','api_key'=>'fake-sims-key','session_id'=>null,'status'=>'DISCONNECTED'];$raw=['http_code'=>204,'body'=>'','error'=>''];check($gateway->api('DELETE','sessions/old-session')['ok'],'204 logout not accepted');
check(WhatsAppGateway::phone('invalid')===''&&WhatsAppGateway::phone(['x'])==='','Invalid phone accepted');
echo "PASS: One shared SIMS settings snapshot, bearer/session text/media/PDF payloads, session changes, confirmed-only sends, rejection/timeout/204 handling, no retries, no secrets and no retired fallback; no network calls.\n";
