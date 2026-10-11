<?php
declare(strict_types=1);
require dirname(__DIR__) . '/scripts/configure-whatsapp-gateway.php';

$directory = sys_get_temp_dir() . '/aiwalas-gateway-config-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
$path = $directory . '/.env';
function configAssert(bool $condition, string $label): void { if (!$condition) throw new RuntimeException($label); }
$original = "# Config fixture\r\napp.baseURL = 'https://example.test/'\r\ndatabase.default.password = 'retain-me'\r\n# WHATSAPP_GATEWAY_API_KEY = template\r\nWHATSAPP_GATEWAY_API_KEY = obsolete\r\nWHATSAPP_GATEWAY_API_KEY = duplicate\r\nexport WHATSAPP_GATEWAY_SENDER = 081111111111\r\n";
$settings = ['WHATSAPP_GATEWAY_URL' => 'https://example.test/gateway/', 'WHATSAPP_GATEWAY_API_KEY' => 'fake-test-key="quoted"\\value', 'WHATSAPP_GATEWAY_SENDER' => '081234567890'];
try {
    file_put_contents($path, $original);
    configAssert(gatewayUpdateEnv($path, $settings), 'First update should modify file');
    $result = file_get_contents($path);
    $parsed = gatewayReadEnv($path);
    configAssert($parsed['database.default.password'] === 'retain-me', 'Unrelated settings changed');
    configAssert($parsed['app.baseURL'] === 'https://example.test/', 'App URL changed');
    configAssert($parsed['WHATSAPP_GATEWAY_API_KEY'] === $settings['WHATSAPP_GATEWAY_API_KEY'], 'Quoted key failed roundtrip');
    configAssert($parsed['WHATSAPP_GATEWAY_SENDER'] === '6281234567890', 'Sender normalization failed');
    configAssert($parsed['WHATSAPP_GATEWAY_URL'] === 'https://example.test/gateway', 'URL normalization failed');
    configAssert(substr_count($result, "\r\nWHATSAPP_GATEWAY_API_KEY =") === 1, 'Duplicate key remains');
    configAssert(str_contains($result, '# WHATSAPP_GATEWAY_API_KEY = template'), 'Comment removed');
    $backups = glob($directory . '/writable/config-backups/*.bak');
    configAssert(count($backups) === 1 && file_get_contents($backups[0]) === $original, 'Original backup mismatch');
    configAssert(!gatewayUpdateEnv($path, $settings), 'Repeated update must be idempotent');
    configAssert(count(glob($directory . '/writable/config-backups/*.bak')) === 1, 'Unneeded backup created');
    foreach ([['WHATSAPP_GATEWAY_API_KEY' => ''], ['WHATSAPP_GATEWAY_SENDER' => '123'], ['WHATSAPP_GATEWAY_URL' => 'file:///etc/passwd']] as $invalid) {
        try { gatewayUpdateEnv($path, array_replace($settings, $invalid)); throw new LogicException('Invalid settings accepted'); }
        catch (RuntimeException $error) { if ($error instanceof LogicException) throw $error; }
        configAssert(file_get_contents($path) === $result, 'Invalid settings modified file');
    }
    echo "PASS: Gateway dotenv roundtrip, unrelated settings preserved, backup, duplicate removal, idempotency and validation.\n";
} finally {
    foreach (glob($directory . '/writable/config-backups/*.bak') ?: [] as $backup) unlink($backup);
    if (is_file($path)) unlink($path);
    foreach ([$directory . '/writable/config-backups', $directory . '/writable', $directory] as $folder) if (is_dir($folder)) rmdir($folder);
}
