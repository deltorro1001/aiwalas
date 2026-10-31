<?php
namespace App\Controllers\Api;
use App\Libraries\WhatsAppGateway;
use App\Models\PesanKomunikasiModel;

class WhatsAppController extends BaseApiController
{
    private function ensureConfig(): array
    {
        $db=db_connect();$row=$db->table('whatsapp_gateway_config')->where('id',1)->get()->getRowArray();
        if($row)return $row;
        $db->query("INSERT IGNORE INTO whatsapp_gateway_config (id,webhook_token,status) VALUES (1,?,'DISCONNECTED')",[bin2hex(random_bytes(32))]);
        return $db->table('whatsapp_gateway_config')->where('id',1)->get()->getRowArray();
    }
    private function saveConfig(array $values,?string $session=null): array
    {
        $query=db_connect()->table('whatsapp_gateway_config')->where('id',1);if($session!==null)$query->where('session_id',$session);$query->update($values);return $this->ensureConfig();
    }
    protected function phone($value): string {return WhatsAppGateway::phone($value);}
    protected function gatewayConfigured(): bool {return (new WhatsAppGateway())->configured();}
    protected function gatewayRequest($phone,$message,$type='',$url=''): array {return (new WhatsAppGateway())->request($phone,$message,$type,$url);}
    private function api(string $method,string $path,?array $payload=null): array
    {
        $result=(new WhatsAppGateway())->api($method,$path,$payload);
        if(!$result['ok'])throw new \RuntimeException($result['message']);
        $data=$result['data'];return isset($data['data'])&&is_array($data['data'])?$data['data']:$data;
    }
    private function publicConfig(array $row): array
    {
        $settings=(new WhatsAppGateway())->settings();
        return ['session_id'=>$row['session_id']??null,'status'=>$row['status'],'phone'=>$row['phone']??null,'webhook_url'=>$row['webhook_url']??null,'portal_url'=>WhatsAppGateway::PORTAL,'api_base_url'=>$settings['api_base_url'],'has_api_key'=>!empty($settings['api_key'])];
    }
    private function publicWebhook(array $row): string
    {
        $public=trim((string)env('AIWALAS_PUBLIC_URL',''))?:rtrim(base_url(),'/');
        return rtrim($public,'/').'/api/whatsapp/webhook?token='.rawurlencode($row['webhook_token']);
    }
    public function status()
    {
        $row=$this->ensureConfig();$settings=(new WhatsAppGateway())->settings();$remote=null;
        try{
            if($row['session_id']&&!empty($settings['api_key'])){
                $remote=$this->api('GET','sessions/'.$row['session_id'].'/status');
                if(isset($remote['status']))$row=$this->saveConfig(['status'=>(string)$remote['status'],'phone'=>$remote['user']['id']??$row['phone']],$row['session_id']);
            }
            $this->response->setHeader('Cache-Control','private, no-store');
            return $this->respond(['ok'=>true,'data'=>['config'=>$this->publicConfig($row),'remote'=>$remote]]);
        }catch(\Throwable $error){return $this->respond(['ok'=>false,'message'=>$error->getMessage(),'data'=>['config'=>$this->publicConfig($row)]],503);}
    }
    public function pair()
    {
        try{
            $row=$this->ensureConfig();
            if(!$row['session_id']){
                $session=$this->api('POST','sessions',['name'=>'AIWalas WhatsApp']);$id=$session['id']??$session['sessionId']??null;
                if(!is_string($id)||!preg_match('/^[a-zA-Z0-9_-]{1,36}$/D',$id))throw new \RuntimeException('Gateway tidak memberikan ID sesi yang valid.');
                $row=$this->saveConfig(['session_id'=>$id]);
            }
            $url=$this->publicWebhook($row);$this->api('PUT','client/webhook',['webhook_url'=>$url]);$row=$this->saveConfig(['webhook_url'=>$url],$row['session_id']);
            $remote=$this->api('POST','sessions/'.$row['session_id'].'/start');$row=$this->saveConfig(['status'=>$remote['status']??'INITIALIZING','phone'=>$remote['user']['id']??$row['phone']],$row['session_id']);
            return $this->respond(['ok'=>true,'data'=>['config'=>$this->publicConfig($row),'remote'=>$remote]]);
        }catch(\Throwable $error){return $this->respond(['ok'=>false,'message'=>$error->getMessage()],502);}
    }
    public function disconnect()
    {
        try{$row=$this->ensureConfig();if($row['session_id'])$this->api('DELETE','sessions/'.$row['session_id']);$this->saveConfig(['session_id'=>null,'status'=>'DISCONNECTED','phone'=>null,'webhook_url'=>null],$row['session_id']??null);return $this->respond(['ok'=>true,'message'=>'WhatsApp berhasil diputuskan.']);}
        catch(\Throwable $error){return $this->respond(['ok'=>false,'message'=>$error->getMessage()],502);}
    }
    public function test()
    {
        $p=$this->payload();$phone=$this->phone($p['nomor_tujuan']??'');$message=$p['isi_pesan']??'';
        if(!$phone||!is_string($message)||trim($message)===''||mb_strlen($message)>5000)return $this->respond(['ok'=>false,'message'=>'Nomor tujuan dan pesan wajib diisi dengan benar, maksimal 5.000 karakter.'],422);
        $result=$this->gatewayRequest($phone,trim($message));
        return $this->respond(['ok'=>$result['ok'],'message'=>$result['message']],$result['ok']?200:502);
    }
    public function send()
    {
        $p=$this->payload();$phone=$this->phone($p['nomor_tujuan']??'');$message=$p['isi_pesan']??'';$class=$p['kelas_id']??null;$recipient=$p['nama_penerima']??'';
        if(!$phone||!is_string($message)||trim($message)===''||mb_strlen($message)>5000||!is_scalar($class)||!ctype_digit((string)$class)||(int)$class<1||!is_string($recipient)||mb_strlen($recipient)>180)return $this->respond(['ok'=>false,'message'=>'Nomor, pesan, penerima, dan kelas tidak valid.'],422);
        $user=$this->currentUser();$db=db_connect();
        if(!$db->table('kelas')->where(['id'=>(int)$class,'wali_kelas_id'=>(int)$user['id']])->countAllResults())return $this->respond(['ok'=>false,'message'=>'Kelas tidak dapat diakses.'],403);
        $student=null;
        if(isset($p['siswa_id'])){
            if(!is_scalar($p['siswa_id'])||!ctype_digit((string)$p['siswa_id']))return $this->respond(['message'=>'Siswa tidak valid.'],422);
            $student=$db->table('siswa')->where(['id'=>(int)$p['siswa_id'],'kelas_id'=>(int)$class,'aktif'=>1])->get()->getRowArray();
            if(!$student)return $this->respond(['message'=>'Siswa tidak dapat diakses.'],403);
        }
        $model=new PesanKomunikasiModel();$id=$model->insert(['kelas_id'=>(int)$class,'siswa_id'=>$student['id']??null,'pengirim'=>$user['nama_lengkap']??'AIWalas','penerima'=>trim($recipient)?:$phone,'nomor_tujuan'=>$phone,'isi_pesan'=>trim($message),'kategori'=>'WhatsApp','status_pengiriman'=>'Disiapkan','dibuat_oleh'=>(int)$user['id']],true);
        $result=$this->gatewayRequest($phone,trim($message));$ok=$result['ok'];$model->update($id,['status_pengiriman'=>$ok?'Terkirim':(($result['outcome']??'unknown')==='failed'?'Gagal':'Disiapkan'),'dikirim_pada'=>$ok?date('Y-m-d H:i:s'):null]);
        return $this->respond(['ok'=>$ok,'message'=>$result['message'],'data'=>['id'=>$id,'status'=>$ok?'Terkirim':'Pending']],$ok?200:502);
    }
    public function webhook()
    {
        $row=$this->ensureConfig();$token=$this->request->getGet('token');
        if(!is_string($token)||!hash_equals((string)$row['webhook_token'],$token))return $this->response->setStatusCode(401)->setJSON(['ok'=>false]);
        $payload=$this->request->getJSON(true)??$this->request->getPost();$type=$payload['type']??'';$data=is_array($payload['data']??null)?$payload['data']:[];
        $session=$data['sessionId']??$payload['instanceId']??null;
        if($session!==null&&(!is_string($session)||$session!==$row['session_id']))return $this->response->setJSON(['ok'=>true]);
        if($type==='session.status.update'&&is_string($data['status']??null)&&in_array($data['status'],['CONNECTED','DISCONNECTED','INITIALIZING','NEED_QR','FAILED'],true))$this->saveConfig(['status'=>$data['status'],'phone'=>is_string($data['phone']??null)?$data['phone']:$row['phone']],$row['session_id']??null);
        return $this->response->setJSON(['ok'=>true]);
    }
}
