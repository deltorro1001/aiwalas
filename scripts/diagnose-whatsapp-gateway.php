<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
require __DIR__ . '/configure-whatsapp-gateway.php';
$project = dirname(__DIR__);
$values = gatewayReadEnv($project . '/.env');
$settings = gatewaySettings($values);
$redactions = array_values($settings);
foreach ($values as $name => $value) {
    if (preg_match('/password|secret|key|token/i', $name) && strlen($value) > 2) $redactions[] = $value;
}
function safeDiagnosticText(string $text): string
{
    global $redactions;
    $text = str_replace($redactions, '[REDACTED]', $text);
    $text = preg_replace('~https?://\S+~i', '[URL]', $text);
    $text = preg_replace('/[a-f0-9]{24,}/i', '[TOKEN]', $text);
    $text = preg_replace('/\b\d{8,}\b/', '[NUMBER]', $text);
    $text = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[EMAIL]', $text);
    $text = preg_replace('/([\'"])(?:\\\\.|(?!\1).)*\1/', '[QUOTED]', $text);
    return substr($text, 0, 400);
}
function diagnosticGet(string $label, string $url, bool $pdf = false): void
{
    if (!function_exists('curl_init')) { echo "$label: PHP curl extension missing.\n"; return; }
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 12]);
    $body = curl_exec($curl);
    $info = curl_getinfo($curl);
    echo $label . ': ' . json_encode(['http' => $info['http_code'], 'curl_errno' => curl_errno($curl),
        'seconds' => round($info['total_time'], 2), 'content_type' => $info['content_type'],
        'pdf_signature' => $pdf ? str_starts_with((string) $body, '%PDF-') : null]) . PHP_EOL;
    curl_close($curl);
}
echo "READ-ONLY DIAGNOSTICS: no message sends, no configuration writes.\n";
echo 'PHP CLI: ' . PHP_VERSION . '; curl=' . (extension_loaded('curl') ? 'yes' : 'no') . PHP_EOL;
diagnosticGet('Gateway root', $settings['WHATSAPP_GATEWAY_URL']);

$logs = glob($project . '/writable/logs/log-*.log') ?: [];
rsort($logs);
$found = 0;
foreach (array_slice($logs, 0, 3) as $log) {
    $contents = file_get_contents($log);
    $blocks = preg_split('/(?=^(?:CRITICAL|ERROR|WARNING|INFO|DEBUG|NOTICE|ALERT|EMERGENCY)\s+-)/m', $contents);
    foreach (array_reverse($blocks) as $block) {
        if (!preg_match('/^CRITICAL|^ERROR|LaporanFileController|WhatsAppController|curl_|Gateway|gateway/i', $block)) continue;
        $lines = explode("\n", $block);
        echo 'Application event: ' . safeDiagnosticText($lines[0]) . PHP_EOL;
        // Only include stack frame function/file names, never arguments or payloads.
        foreach ($lines as $line) {
            if (preg_match('~([^/\\\\\s]+\.php)\((\d+)\).*?(LaporanFileController|WhatsAppController|HTTPException|setJSON|format|json_encode)~', $line, $match)) {
                echo 'Related frame: ' . $match[1] . ':' . $match[2] . ' ' . $match[3] . PHP_EOL;
            }
        }
        if (++$found >= 8) break 2;
    }
}
if (!$found) echo "No recent application error found.\n";

foreach (['/var/log/nginx/error.log', '/var/log/apache2/error.log'] as $webLog) {
    if (!is_readable($webLog)) continue;
    $handle = fopen($webLog, 'r');
    fseek($handle, max(0, filesize($webLog) - 1048576));
    $tail = stream_get_contents($handle); fclose($handle);
    $events = array_values(array_filter(explode("\n", $tail), static fn($line) => preg_match('~api/laporan|laporan/preview|api/csrf~', $line)));
    foreach (array_slice($events, -8) as $event) echo 'Web-server event: ' . safeDiagnosticText($event) . PHP_EOL;
    echo 'Matching web-server errors: ' . count($events) . PHP_EOL;
}
foreach (['pdf', 'part', 'json'] as $extension) {
    $uploads = glob($project . '/writable/uploads/laporan-pdf/*.' . $extension) ?: [];
    echo 'Upload files (' . $extension . '): ' . count($uploads) . PHP_EOL;
    if ($uploads) {
        usort($uploads, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        echo 'Latest upload (' . $extension . '): ' . json_encode(['age_seconds' => time() - filemtime($uploads[0]), 'bytes' => filesize($uploads[0])]) . PHP_EOL;
    }
}

$files = glob($project . '/writable/uploads/laporan-pdf/*.pdf') ?: [];
usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
echo 'Saved report PDF count: ' . count($files) . PHP_EOL;
if ($files) {
    $latest = $files[0];
    echo 'Latest saved PDF: ' . json_encode(['age_seconds' => time() - filemtime($latest),
        'bytes' => filesize($latest), 'readable_cli' => is_readable($latest)]) . PHP_EOL;
    $base = rtrim((string) ($values['app.baseURL'] ?? ''), '/');
    $token = pathinfo($latest, PATHINFO_FILENAME);
    if (filter_var($base, FILTER_VALIDATE_URL) && preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        diagnosticGet('Public PDF GET (token hidden)', $base . '/laporan/pdf/' . $token, true);
    } else { echo "Public PDF URL cannot be derived from app.baseURL.\n"; }
}
