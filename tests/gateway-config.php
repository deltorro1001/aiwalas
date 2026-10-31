<?php
declare(strict_types=1);
require dirname(__DIR__) . '/scripts/configure-whatsapp-gateway.php';

$directory = sys_get_temp_dir() . '/aiwalas-gateway-config-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
$path = $directory . '/.env';
function configAssert(bool $condition, string $label): void { if (!$condition) throw new RuntimeException($label); }
$original = "# Config fixture\r\napp.baseURL = 'https://example.test/'\r\ndatabase.default.password = 'retain-me'\r\n# WHATSAPP_GATEWAY_API_KEY = template\r\nWHATSAPP_GATEWAY_API_KEY = obsolete\r\nWHATSAPP_GATEWAY_API_KEY = duplicate\r\nexport WHATSAPP_GATEWAY_SENDER = 081111111111\r\n";
$settings = ['SIMS_WA_GATEWAY_API_BASE_URL'=>'https://example.test/api/v1/','SIMS_WA_GATEWAY_API_KEY'=>'fake-test-key="quoted"\\value'];
try {
    file_put_contents($path, $original);
    configAssert(gatewayUpdateEnv($path, $settings), 'First update should modify file');
    $result = file_get_contents($path);
    $parsed = gatewayReadEnv($path);
    configAssert($parsed['database.default.password'] === 'retain-me', 'Unrelated settings changed');
    configAssert($parsed['app.baseURL'] === 'https://example.test/', 'App URL changed');
    configAssert($parsed['SIMS_WA_GATEWAY_API_KEY'] === $settings['SIMS_WA_GATEWAY_API_KEY'], 'Quoted key failed roundtrip');
    configAssert(!isset($parsed['WHATSAPP_GATEWAY_API_KEY'])&&!isset($parsed['WHATSAPP_GATEWAY_SENDER']), 'Retired provider retained');
    configAssert($parsed['SIMS_WA_GATEWAY_API_BASE_URL'] === 'https://example.test/api/v1', 'URL normalization failed');
    configAssert(substr_count($result, "\r\nSIMS_WA_GATEWAY_API_KEY =") === 1, 'Duplicate key remains');
    configAssert(str_contains($result, '# WHATSAPP_GATEWAY_API_KEY = template'), 'Comment removed');
    $backups = glob($directory . '/writable/config-backups/*.bak');
    configAssert(count($backups) === 1 && file_get_contents($backups[0]) === $original, 'Original backup mismatch');
    configAssert(!gatewayUpdateEnv($path, $settings), 'Repeated update must be idempotent');
    configAssert(count(glob($directory . '/writable/config-backups/*.bak')) === 1, 'Unneeded backup created');
    foreach ([['SIMS_WA_GATEWAY_API_KEY'=>''],['SIMS_WA_GATEWAY_API_KEY'=>"header\nvalue"],['SIMS_WA_GATEWAY_API_BASE_URL'=>'file:///etc/passwd'],['SIMS_WA_GATEWAY_API_BASE_URL'=>'https://key@example.test']] as $invalid) {
        try { gatewayUpdateEnv($path, array_replace($settings, $invalid)); throw new LogicException('Invalid settings accepted'); }
        catch (RuntimeException $error) { if ($error instanceof LogicException) throw $error; }
        configAssert(file_get_contents($path) === $result, 'Invalid settings modified file');
    }
    $connected="SIMS_WA_GATEWAY_API_BASE_URL = 'https://example.test/api/v1'\nSIMS_WA_GATEWAY_API_KEY = 'connected-key'\nWHATSAPP_TEACHER_AUTOMATION_ENABLED = false\nWHATSAPP_GATEWAY_API_KEY = retired\n";
    file_put_contents($path,$connected);
    configAssert(gatewayUpdateEnv($path,[]),'Retired cleanup must modify file');
    $cleaned=gatewayReadEnv($path);
    configAssert($cleaned['SIMS_WA_GATEWAY_API_KEY']==='connected-key'&&$cleaned['WHATSAPP_TEACHER_AUTOMATION_ENABLED']==='false','Connected settings or automation flag changed during cleanup');
    configAssert(!gatewayUpdateEnv($path,[]),'Cleanup must be idempotent');
    echo "PASS: Gateway dotenv roundtrip, unrelated settings preserved, backup, duplicate removal, idempotency and validation.\n";
} finally {
    foreach (glob($directory . '/writable/config-backups/*.bak') ?: [] as $backup) unlink($backup);
    if (is_file($path)) unlink($path);
    foreach ([$directory . '/writable/config-backups', $directory . '/writable', $directory] as $folder) if (is_dir($folder)) rmdir($folder);
}
