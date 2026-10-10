<?php

namespace App\Controllers\Api;

use App\Models\PesanKomunikasiModel;

class WhatsAppController extends BaseApiController
{
    public function send()
    {
        $p = $this->payload();
        $phone = $this->phone($p['nomor_tujuan'] ?? '');
        $message = trim((string) ($p['isi_pesan'] ?? ''));
        $classId = (int) ($p['kelas_id'] ?? 0);
        $mediaType = strtolower(trim((string) ($p['media_type'] ?? '')));
        $mediaUrl = trim((string) ($p['media_url'] ?? $p['url'] ?? ''));
        if (!$phone || !$classId || (!$message && !$mediaUrl)) return $this->respond(['ok' => false, 'message' => 'Nomor, kelas, dan pesan atau media wajib diisi.'], 422);
        if ($mediaUrl && !in_array($mediaType, ['image', 'video', 'audio', 'document'], true)) return $this->respond(['ok' => false, 'message' => 'Jenis media tidak didukung.'], 422);
        if ($mediaUrl && !filter_var($mediaUrl, FILTER_VALIDATE_URL)) return $this->respond(['ok' => false, 'message' => 'URL media tidak valid.'], 422);
        $user = $this->currentUser();
        $model = new PesanKomunikasiModel();
        $id = $model->insert(['kelas_id' => $classId, 'pengirim' => $user['nama_lengkap'] ?? 'AIWalas', 'penerima' => $p['nama_penerima'] ?? $phone, 'nomor_tujuan' => $phone, 'isi_pesan' => $message ?: '[Media WhatsApp]', 'kategori' => $mediaUrl ? 'WhatsApp Media' : 'WhatsApp', 'status_pengiriman' => 'Disiapkan', 'dibuat_oleh' => (int) ($user['id'] ?? 0)], true);
        $result = $this->gatewayRequest($phone, $message, $mediaType, $mediaUrl);
        $ok = $result['ok'];
        $model->update($id, ['status_pengiriman' => $ok ? 'Terkirim' : 'Gagal', 'dikirim_pada' => $ok ? date('Y-m-d H:i:s') : null]);
        return $this->respond(['ok' => $ok, 'message' => $ok ? 'Pesan WhatsApp terkirim.' : ($result['message'] ?: 'Gateway WhatsApp gagal mengirim pesan.'), 'data' => ['id' => $id, 'status' => $ok ? 'Terkirim' : 'Gagal', 'response' => $result['response']]], $ok ? 200 : 502);
    }

    private function gatewayRequest(string $phone, string $message, string $mediaType = '', string $mediaUrl = ''): array
    {
        $base = rtrim((string) env('WHATSAPP_GATEWAY_URL', 'https://wa.ats.co.id'), '/');
        $key = (string) env('WHATSAPP_GATEWAY_API_KEY');
        $sender = $this->phone(env('WHATSAPP_GATEWAY_SENDER', '6285810283863'));
        if (!$key || !$sender) return ['ok' => false, 'message' => 'Gateway WhatsApp belum dikonfigurasi di server.', 'response' => null];
        $payload = ['api_key' => $key, 'sender' => $sender, 'number' => $phone];
        if ($mediaUrl) { $payload += ['media_type' => $mediaType, 'caption' => $message, 'url' => $mediaUrl]; $endpoint = '/send-media'; }
        else { $payload['message'] = $message; $endpoint = '/send-message'; }
        $ch = curl_init($base . $endpoint);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'], CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $body = curl_exec($ch); $error = curl_error($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $response = json_decode((string) $body, true);
        $ok = !$error && $code >= 200 && $code < 300 && (!is_array($response) || ($response['status'] ?? true) !== false);
        return ['ok' => $ok, 'message' => $error ?: (is_array($response) ? (string) ($response['message'] ?? $response['msg'] ?? '') : ''), 'response' => $response ?: ['http_code' => $code, 'body' => (string) $body]];
    }

    private function phone($value): string
    {
        $phone = preg_replace('/\D+/', '', (string) $value);
        if (str_starts_with($phone, '0')) $phone = '62' . substr($phone, 1);
        return preg_match('/^62\d{8,15}$/', $phone) ? $phone : '';
    }
}