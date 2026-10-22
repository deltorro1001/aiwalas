<?php
namespace App\Libraries;

/** Shared gateway transport for interactive sends and the server scheduler. */
class WhatsAppGateway
{
    public function request(string $phone,string $message,string $mediaType='',string $mediaUrl=''): array
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
    public function configured(): bool
    {
        return trim((string)env('WHATSAPP_GATEWAY_API_KEY',''))!=='' && $this->phone(env('WHATSAPP_GATEWAY_SENDER','6285810283863'))!=='';
    }
    public static function phone($value): string
    {
        $phone=preg_replace('/\D+/','',(string)$value);
        if(str_starts_with($phone,'0'))$phone='62'.substr($phone,1);
        return preg_match('/^62\d{8,15}$/',$phone)?$phone:'';
    }
}
