<?php
namespace App\Libraries;

/** Shared wa.sims.id transport for the Gateway menu, manual sends, PDFs, media and jobs. */
class WhatsAppGateway
{
    // wa.sims.id is the portal; its public client uses this API origin.
    public const API_BASE='https://ppvs37v.app.neoapp.id/api/v1';
    public const PORTAL='https://wa.sims.id';
    private $transport;
    private $configuration;
    public function __construct(?callable $transport=null,?callable $configuration=null)
    {
        $this->transport=$transport;
        $this->configuration=$configuration;
    }
    public function settings(): array
    {
        if($this->configuration)return ($this->configuration)();
        $row=db_connect()->table('whatsapp_gateway_config')->where('id',1)->get()->getRowArray()??[];
        return ['api_base_url'=>rtrim(trim((string)env('SIMS_WA_GATEWAY_API_BASE_URL',self::API_BASE)),'/')?:self::API_BASE,
            'api_key'=>trim((string)env('SIMS_WA_GATEWAY_API_KEY',''))]+$row;
    }
    public function configured(): bool
    {
        return $this->ready($this->settings());
    }
    private function ready(array $s): bool
    {
        return $this->validBase($s['api_base_url']??'')&&!empty($s['api_key'])&&!preg_match('/[\x00-\x1f\x7f]/',$s['api_key'])&&is_string($s['session_id']??null)&&preg_match('/^[a-zA-Z0-9_-]{1,36}$/D',$s['session_id'])&&($s['status']??'')==='CONNECTED';
    }
    private function validBase(string $url): bool
    {
        return filter_var($url,FILTER_VALIDATE_URL)&&parse_url($url,PHP_URL_SCHEME)==='https'&&!parse_url($url,PHP_URL_USER)&&!parse_url($url,PHP_URL_PASS)&&!parse_url($url,PHP_URL_QUERY)&&!parse_url($url,PHP_URL_FRAGMENT);
    }
    private function http(string $method,string $url,array $headers,?array $payload): array
    {
        if($this->transport)return ($this->transport)($method,$url,$headers,$payload);
        $ch=curl_init($url);
        $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>$headers];
        if($payload!==null)$options[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        curl_setopt_array($ch,$options);$body=curl_exec($ch);$error=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        return ['http_code'=>$code,'body'=>$body===false?'':$body,'error'=>$error];
    }
    public function api(string $method,string $path,?array $payload=null): array
    {
        return $this->apiWithSettings($this->settings(),$method,$path,$payload);
    }
    private function apiWithSettings(array $s,string $method,string $path,?array $payload): array
    {
        $base=rtrim((string)($s['api_base_url']??''),'/');$key=(string)($s['api_key']??'');
        if(!$this->validBase($base)||$key===''||preg_match('/[\x00-\x1f\x7f]/',$key))return ['ok'=>false,'data'=>null,'http_code'=>0,'outcome'=>'failed','message'=>'Konfigurasi API wa.sims.id belum siap. Periksa pengaturan WhatsApp Gateway.'];
        if(!preg_match('#^[a-zA-Z0-9/_-]+$#',$path))throw new \InvalidArgumentException('Path API gateway tidak valid.');
        try{$raw=$this->http(strtoupper($method),$base.'/'.ltrim($path,'/'),['Accept: application/json','Content-Type: application/json','Authorization: Bearer '.$key],$payload);}
        catch(\Throwable $error){return ['ok'=>false,'data'=>null,'http_code'=>0,'outcome'=>'unknown','message'=>'Koneksi gateway terputus; periksa histori sebelum mengirim ulang.'];}
        $code=(int)($raw['http_code']??0);$data=json_decode((string)($raw['body']??''),true);$error=!empty($raw['error']);
        $negative=is_array($data)&&(($data['success']??null)===false||($data['status']??null)===false);
        $ok=!$error&&$code>=200&&$code<300&&(!$negative)&&(is_array($data)||($code===204));
        if($ok)return ['ok'=>true,'data'=>$data??[],'http_code'=>$code,'outcome'=>'accepted','message'=>''];
        $known=!$error&&($negative||($code>=400&&$code<500));
        $message=$error?'Koneksi gateway terputus; periksa histori sebelum mengirim ulang.':(in_array($code,[401,403],true)?'API key gateway ditolak. Periksa konfigurasi wa.sims.id.':($code===404?'Endpoint atau sesi gateway tidak ditemukan.':($negative?'Gateway menolak permintaan.':'Gateway belum mengonfirmasi permintaan. Periksa histori sebelum mengirim ulang.')));
        return ['ok'=>false,'data'=>$data,'http_code'=>$code,'outcome'=>$known?'failed':'unknown','message'=>$message];
    }
    public function request(string $phone,string $message,string $mediaType='',string $mediaUrl=''): array
    {
        $phone=self::phone($phone);
        if(!$phone||trim($message)==='')return ['ok'=>false,'outcome'=>'failed','message'=>'Nomor WhatsApp atau pesan tidak valid.','response'=>['status'=>false]];
        $s=$this->settings();
        if(!$this->ready($s))return ['ok'=>false,'outcome'=>'failed','message'=>'WhatsApp belum terhubung. Periksa menu WhatsApp Gateway.','response'=>['status'=>false]];
        $payload=['sessionId'=>$s['session_id'],'to'=>$phone];$path='message/send-text';
        if($mediaUrl!==''){
            if(!in_array($mediaType,['image','video','audio','document'],true)||!filter_var($mediaUrl,FILTER_VALIDATE_URL)||!in_array(parse_url($mediaUrl,PHP_URL_SCHEME),['https','http'],true))return ['ok'=>false,'outcome'=>'failed','message'=>'Media pengiriman tidak valid.','response'=>['status'=>false]];
            $payload+=['mediaType'=>$mediaType,'url'=>$mediaUrl,'caption'=>$message];$path='message/send-media';
            if($mediaType==='document'){$payload['filename']='Laporan-Wali-Kelas.pdf';$payload['mimetype']='application/pdf';}
        }else $payload['text']=$message;
        $result=$this->apiWithSettings($s,'POST',$path,$payload);$body=$result['data'];
        $confirmed=is_array($body)&&(($body['success']??null)===true||in_array($body['status']??null,[true,1,200,'true','success','sent'],true));
        $ok=$result['ok']&&$confirmed;
        $outcome=$ok?'sent':($result['ok']?'unknown':$result['outcome']);
        // All callers get the same explicit confirmation, without raw gateway bodies or credentials.
        $response=$ok?['success'=>true,'status'=>true]:($outcome==='failed'?['success'=>false,'status'=>false]:['http_code'=>$result['http_code']]);
        return ['ok'=>$ok,'outcome'=>$outcome,'message'=>$ok?'Diterima gateway wa.sims.id.':($result['message']?:'Gateway belum mengonfirmasi pesan; periksa histori sebelum mengirim ulang.'),'response'=>$response];
    }
    public static function phone($value): string
    {
        if(!is_scalar($value))return '';
        $phone=preg_replace('/\D+/','',(string)$value);if(str_starts_with($phone,'0'))$phone='62'.substr($phone,1);
        return preg_match('/^62\d{8,15}$/',$phone)?$phone:'';
    }
}
