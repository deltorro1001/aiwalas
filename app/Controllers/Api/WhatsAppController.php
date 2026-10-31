<?php
namespace App\Controllers\Api;

use App\Models\PesanKomunikasiModel;

class WhatsAppController extends BaseApiController
{
    private function db() { return db_connect(); }
    private function baseUrl(): string { return rtrim((string) env('SIMS_WA_GATEWAY_API_BASE_URL', 'https://ppvs37v.app.neoapp.id/api/v1'), '/'); }
    private function apiKey(): string { return trim((string) env('SIMS_WA_GATEWAY_API_KEY', '')); }
    private function ensureConfig(): array
    {
        $db = $this->db();
        $row = $db->table('whatsapp_gateway_config')->where('id', 1)->get()->getRowArray();
        if ($row) return $row;
        $row = ['id'=>1, 'webhook_token'=>bin2hex(random_bytes(32)), 'status'=>'DISCONNECTED'];
        $db->table('whatsapp_gateway_config')->insert($row);
        return array_merge($row, ['session_id'=>null, 'phone'=>null, 'webhook_url'=>null]);
    }
    private function save(array $data): array
    {
        $this->db()->table('whatsapp_gateway_config')->where('id', 1)->update($data);
        return $this->ensureConfig();
    }
    private function gateway(string $method, string $path, ?array $payload = null): array
    {
        if (!$this->apiKey()) throw new \RuntimeException('SIMS_WA_GATEWAY_API_KEY belum dikonfigurasi di .env.');
        $ch = curl_init($this->baseUrl() . '/' . ltrim($path, '/'));
        $headers = ['Accept: application/json', 'Authorization: Bearer ' . $this->apiKey()];
        $options = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>strtoupper($method), CURLOPT_HTTPHEADER=>$headers, CURLOPT_TIMEOUT=>25];
        if ($payload !== null) { $headers[]='Content-Type: application/json'; $options[CURLOPT_HTTPHEADER]=$headers; $options[CURLOPT_POSTFIELDS]=json_encode($payload); }
        curl_setopt_array($ch, $options); $body=curl_exec($ch); $code=(int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error=curl_error($ch); curl_close($ch);
        $data=json_decode((string)$body,true); if (!is_array($data)) $data=['raw'=>(string)$body];
        if ($error || $code < 200 || $code >= 300) throw new \RuntimeException($data['message'] ?? ($error ?: 'Gateway mengembalikan HTTP '.$code));
        return $data;
    }
    private function publicWebhook(array $config): string
    {
        $public = rtrim((string) env('AIWALAS_PUBLIC_URL', base_url()), '/');
        return $public . '/api/whatsapp/webhook?token=' . rawurlencode($config['webhook_token']);
    }
    private function normalizePhone(string $phone): string
    {
        $phone=preg_replace('/\D+/', '', $phone); return str_starts_with($phone,'0') ? '62'.substr($phone,1) : $phone;
    }
    public function status()
    {
        try { $c=$this->ensureConfig(); $remote=null; if ($c['session_id'] && $this->apiKey()) $remote=$this->gateway('GET','sessions/'.$c['session_id'].'/status'); if ($remote && isset($remote['status'])) { $c=$this->save(['status'=>$remote['status'], 'phone'=>$remote['user']['id']??$c['phone']]); } return $this->respond(['ok'=>true,'data'=>['config'=>$c,'remote'=>$remote]]); }
        catch (\Throwable $e) { return $this->respond(['ok'=>false,'message'=>$e->getMessage(),'data'=>['config'=>$this->ensureConfig()]],503); }
    }
    public function pair()
    {
        try {
            $c=$this->ensureConfig();
            if (!$c['session_id']) { $s=$this->gateway('POST','sessions',['name'=>'AIWalas WhatsApp']); $c=$this->save(['session_id'=>$s['id']??$s['sessionId']??null]); }
            $webhook=$this->publicWebhook($c); $this->gateway('PUT','client/webhook',['webhook_url'=>$webhook]); $c=$this->save(['webhook_url'=>$webhook]);
            $remote=$this->gateway('POST','sessions/'.$c['session_id'].'/start'); $c=$this->save(['status'=>$remote['status']??'INITIALIZING','phone'=>$remote['user']['id']??null]);
            return $this->respond(['ok'=>true,'data'=>['config'=>$c,'remote'=>$remote]]);
        } catch (\Throwable $e) { return $this->respond(['ok'=>false,'message'=>$e->getMessage()],502); }
    }
    public function disconnect()
    {
        try { $c=$this->ensureConfig(); if ($c['session_id']) $this->gateway('DELETE','sessions/'.$c['session_id']); $this->save(['session_id'=>null,'status'=>'DISCONNECTED','phone'=>null,'webhook_url'=>null]); return $this->respond(['ok'=>true,'message'=>'WhatsApp berhasil diputuskan.']); }
        catch (\Throwable $e) { return $this->respond(['ok'=>false,'message'=>$e->getMessage()],502); }
    }
    public function test()
    {
        $p=$this->payload(); $phone=$this->normalizePhone((string)($p['nomor_tujuan']??'')); $message=trim((string)($p['isi_pesan']??'')); if (!$phone || !$message) return $this->respond(['ok'=>false,'message'=>'Nomor tujuan dan pesan wajib diisi.'],422);
        try { $c=$this->ensureConfig(); if (!$c['session_id'] || $c['status']!=='CONNECTED') return $this->respond(['ok'=>false,'message'=>'Hubungkan WhatsApp terlebih dahulu.'],409); $result=$this->gateway('POST','message/send-text',['sessionId'=>$c['session_id'],'to'=>$phone,'text'=>$message]); return $this->respond(['ok'=>true,'message'=>'Pesan uji terkirim.','data'=>$result]); }
        catch (\Throwable $e) { return $this->respond(['ok'=>false,'message'=>$e->getMessage()],502); }
    }
    public function send()
    {
        $p=$this->payload(); $phone=$this->normalizePhone((string)($p['nomor_tujuan']??'')); $message=trim((string)($p['isi_pesan']??'')); $classId=(int)($p['kelas_id']??0);
        if (!$phone || !$message || !$classId) return $this->respond(['ok'=>false,'message'=>'Nomor, pesan, dan kelas wajib diisi.'],422);
        $u=$this->currentUser(); $m=new PesanKomunikasiModel(); $id=$m->insert(['kelas_id'=>$classId,'pengirim'=>$u['nama_lengkap']??'AIWalas','penerima'=>$p['nama_penerima']??$phone,'nomor_tujuan'=>$phone,'isi_pesan'=>$message,'kategori'=>'WhatsApp','status_pengiriman'=>'Disiapkan','dibuat_oleh'=>(int)($u['id']??0)],true);
        try { $c=$this->ensureConfig(); if (!$c['session_id'] || $c['status']!=='CONNECTED') throw new \RuntimeException('WhatsApp belum terhubung. Buka menu WhatsApp Gateway untuk memindai QR.'); $result=$this->gateway('POST','message/send-text',['sessionId'=>$c['session_id'],'to'=>$phone,'text'=>$message]); $m->update($id,['status_pengiriman'=>'Terkirim','dikirim_pada'=>date('Y-m-d H:i:s')]); return $this->respond(['ok'=>true,'message'=>'Pesan WhatsApp terkirim.','data'=>['id'=>$id,'status'=>'Terkirim','response'=>$result]]); }
        catch (\Throwable $e) { $m->update($id,['status_pengiriman'=>'Gagal']); return $this->respond(['ok'=>false,'message'=>$e->getMessage(),'data'=>['id'=>$id,'status'=>'Gagal']],502); }
    }
    public function webhook()
    {
        $c=$this->ensureConfig(); if (!hash_equals((string)$c['webhook_token'], (string)$this->request->getGet('token'))) return $this->response->setStatusCode(401)->setJSON(['ok'=>false]);
        $payload=$this->request->getJSON(true) ?? $this->request->getPost();
        $type=(string)($payload['type']??''); $data=is_array($payload['data']??null)?$payload['data']:[];
        if($type==='session.status.update' && !empty($data['status'])){ $this->save(['status'=>(string)$data['status'],'phone'=>$data['phone']??null]); }
        log_message('info','WhatsApp webhook: '.json_encode(['type'=>$type,'instanceId'=>$payload['instanceId']??null])); return $this->response->setJSON(['ok'=>true]);
    }
}
