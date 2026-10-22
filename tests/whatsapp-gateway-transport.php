<?php
namespace App\Libraries {
    $settings=['WHATSAPP_GATEWAY_URL'=>'https://gateway.example.test/','WHATSAPP_GATEWAY_API_KEY'=>'fake-key','WHATSAPP_GATEWAY_SENDER'=>'081234567890'];
    $options=[];$url='';$result='{"status":true}';$error='';$code=200;$calls=0;
    function env($key,$default=null){global $settings;return $settings[$key]??$default;}
    function curl_init($value){global $url,$calls;$url=$value;$calls++;return new \stdClass();}
    function curl_setopt_array($handle,$value){global $options;$options=$value;return true;}
    function curl_exec($handle){global $result;return $result;}
    function curl_error($handle){global $error;return $error;}
    function curl_getinfo($handle,$option){global $code;return $code;}
    function curl_close($handle){}
}
namespace {
    require dirname(__DIR__).'/app/Libraries/WhatsAppGateway.php';
    $gateway=new \App\Libraries\WhatsAppGateway();
    function check($value,$message){if(!$value)throw new RuntimeException($message);}
    check($gateway->configured(),'Gateway configuration not detected');
    check($gateway->request('6289999999999','Test reminder')['ok'],'Valid gateway response rejected');
    check($url==='https://gateway.example.test/send-message','Wrong send endpoint');
    $payload=json_decode($options[CURLOPT_POSTFIELDS],true);
    check($payload['sender']==='6281234567890'&&$payload['number']==='6289999999999'&&$payload['message']==='Test reminder','Wrong gateway payload');
    $gateway->request('6289999999999','Caption','document','https://example.test/report.pdf');
    check($url==='https://gateway.example.test/send-media'&&json_decode($options[CURLOPT_POSTFIELDS],true)['caption']==='Caption','Media regression');
    $result='{"status":false}';check(!$gateway->request('6289999999999','Test')['ok'],'Rejected send reported as successful');
    $result='{"status":true}';$code=500;check(!$gateway->request('6289999999999','Test')['ok'],'HTTP failure reported as successful');
    $code=200;$error='test timeout';check(!$gateway->request('6289999999999','Test')['ok'],'Timeout reported as successful');
    $settings['WHATSAPP_GATEWAY_API_KEY']='';$before=$calls;check(!$gateway->request('6289999999999','Test')['ok']&&$calls===$before,'Unconfigured gateway called');
    echo "PASS: Shared gateway transport, phone normalization, text/media payloads, explicit rejection, HTTP errors, timeout and missing credentials; no network calls.\n";
}
