<?php
namespace App\Libraries;

/** Server-side transport shared by AIWalas notifications and scheduled jobs. */
class WhatsAppGateway
{
    public function request(string $phone, string $message, string $mediaType = '', string $mediaUrl = ''): array
    {
        $key=trim((string)env('SIMS_WA_GATEWAY_API_KEY','')); $base=rtrim((string)env('SIMS_WA_GATEWAY_API_BASE_URL','https://ppvs37v.app.neoapp.id/api/v1'),'/');
        if(!$key)return ['ok'=>false,'message'=>'SIMS_WA_GATEWAY_API_KEY belum dikonfigurasi di server.','response'=>null];
        $row=db_connect()->table('whatsapp_gateway_config')->where('id',1)->get()->getRowArray(); $session=$row['session_id']??'';
        if(!$session || ($row['status']??'')!=='CONNECTED')return ['ok'=>false,'message'=>'WhatsApp Gateway belum terhubung.','response'=>null];
        $payload=['sessionId'=>$session,'to'=>self::phone($phone)];
        $endpoint='/message/send-text';
        if($mediaUrl){
            if(!in_array($mediaType,['image','video','audio','document'],true))return ['ok'=>false,'message'=>'Jenis media tidak didukung.','response'=>null];
            $payload += ['mediaType'=>$mediaType,'url'=>$mediaUrl,'caption'=>$message]; $endpoint='/message/send-media';
        } else { $payload['text']=$message; }
        $ch=curl_init($base.$endpoint); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        $body=curl_exec($ch);$error=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$response=json_decode((string)$body,true);$ok=!$error&&$code>=200&&$code<300&&is_array($response)&&($response['success']??true)!==false;
        return ['ok'=>$ok,'message'=>$error?:($response['message']??($ok?'':'Gateway menolak pesan.')),'response'=>$response?:['http_code'=>$code,'body'=>(string)$body]];
    }
    public function configured(): bool { $row=db_connect()->table('whatsapp_gateway_config')->where('id',1)->get()->getRowArray(); return !empty(env('SIMS_WA_GATEWAY_API_KEY'))&&!empty($row['session_id'])&&($row['status']??'')==='CONNECTED'; }
    public static function phone($value): string { $phone=preg_replace('/\D+/','',(string)$value); if(str_starts_with($phone,'0'))$phone='62'.substr($phone,1); return preg_match('/^62\d{8,15}$/',$phone)?$phone:''; }
}
