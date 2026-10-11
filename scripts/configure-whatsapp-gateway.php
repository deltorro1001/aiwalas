<?php
declare(strict_types=1);

// CLI-only deployment helper. Never print credentials or expose this via HTTP.
function gatewaySettings(array $settings): array
{
    $url = rtrim(trim((string) ($settings['WHATSAPP_GATEWAY_URL'] ?? '')), '/');
    $key = trim((string) ($settings['WHATSAPP_GATEWAY_API_KEY'] ?? ''));
    $sender = preg_replace('/\D+/', '', (string) ($settings['WHATSAPP_GATEWAY_SENDER'] ?? ''));
    if (str_starts_with($sender, '0')) $sender = '62' . substr($sender, 1);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true)) {
        throw new RuntimeException('URL Gateway tidak valid.');
    }
    if ($key === '' || preg_match('/[\x00-\x1f\x7f]/', $key) || str_contains($key, '${')) {
        throw new RuntimeException('API key Gateway kosong atau formatnya tidak didukung.');
    }
    if (!preg_match('/^62\d{8,15}$/', $sender)) throw new RuntimeException('Nomor pengirim Gateway tidak valid.');
    return ['WHATSAPP_GATEWAY_URL' => $url, 'WHATSAPP_GATEWAY_API_KEY' => $key, 'WHATSAPP_GATEWAY_SENDER' => $sender];
}

function gatewayReadEnv(string $path): array
{
    require_once dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Config/DotEnv.php';
    return (new \CodeIgniter\Config\DotEnv(dirname($path), basename($path)))->parse() ?? [];
}

function gatewayUpdateEnv(string $path, array $settings): bool
{
    $settings = gatewaySettings($settings);
    if (!is_file($path) || is_link($path)) throw new RuntimeException('File .env VPS harus ada dan bukan symlink.');
    $original = file_get_contents($path);
    if ($original === false) throw new RuntimeException('File .env tidak dapat dibaca.');
    $newline = str_contains($original, "\r\n") ? "\r\n" : "\n";
    $updated = $original;
    foreach ($settings as $name => $value) {
        // Remove active duplicates while retaining comments and unrelated settings.
        $updated = preg_replace('/^[ \t]*(?:export[ \t]+)?' . preg_quote($name, '/') . '[ \t]*=.*(?:\r?\n|$)/m', '', $updated);
    }
    $updated = rtrim($updated, "\r\n") . $newline;
    foreach ($settings as $name => $value) {
        $updated .= $name . ' = "' . addcslashes($value, "\\\"") . '"' . $newline;
    }
    if ($original === $updated) return false;

    $backupDir = dirname($path) . '/writable/config-backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) throw new RuntimeException('Direktori backup gagal dibuat.');
    if (!chmod($backupDir, 0700)) throw new RuntimeException('Direktori backup gagal dilindungi.');
    $backup = $backupDir . '/env-before-gateway-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.bak';
    $backupHandle = fopen($backup, 'x');
    if (!$backupHandle) throw new RuntimeException('Backup .env gagal dibuat.');
    try {
        if (!chmod($backup, 0600) || fwrite($backupHandle, $original) !== strlen($original)) throw new RuntimeException('Backup .env gagal disimpan dengan aman.');
    } finally { fclose($backupHandle); }

    $stat = stat($path);
    $temp = tempnam(dirname($path), '.gateway-env-');
    if ($temp === false) throw new RuntimeException('File sementara .env gagal dibuat.');
    try {
        if (file_put_contents($temp, $updated, LOCK_EX) !== strlen($updated)) throw new RuntimeException('Konfigurasi sementara gagal ditulis.');
        if (PHP_OS_FAMILY !== 'Windows') {
            // Keep the original ownership so the web server can still read .env.
            $tempStat = stat($temp);
            if ($tempStat['uid'] !== $stat['uid'] && !chown($temp, $stat['uid'])) throw new RuntimeException('Owner .env tidak dapat dipertahankan.');
            if ($tempStat['gid'] !== $stat['gid'] && !chgrp($temp, $stat['gid'])) throw new RuntimeException('Group .env tidak dapat dipertahankan.');
        }
        if (!chmod($temp, $stat['mode'] & 0777)) throw new RuntimeException('Permission .env tidak dapat dipertahankan.');
        // Verify the file with the application's actual dotenv parser before replacing it.
        $parsed = gatewayReadEnv($temp);
        foreach ($settings as $name => $value) {
            if (($parsed[$name] ?? null) !== $value) throw new RuntimeException('Verifikasi konfigurasi Gateway gagal.');
        }
        if (!rename($temp, $path)) throw new RuntimeException('File .env gagal diperbarui. Backup tetap tersedia.');
    } finally { if (is_file($temp)) unlink($temp); }
    return true;
}

function gatewayMain(array $args): void
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Hanya boleh dijalankan melalui CLI.');
    $envPath = dirname(__DIR__) . '/.env';
    if (($args[1] ?? '') === '--sync-github-secret') {
        $settings = gatewaySettings(gatewayReadEnv($envPath));
        $encoded = base64_encode(json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        // Send through stdin, never command arguments or terminal output.
        $process = proc_open(['gh', 'secret', 'set', 'WHATSAPP_GATEWAY_CONFIG_B64', '--repo', 'deltorro1001/aiwalas'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('GitHub CLI gagal dijalankan.');
        fwrite($pipes[0], $encoded); fclose($pipes[0]);
        stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Penyimpanan GitHub Secret gagal.');
        echo "GitHub Secret Gateway tersimpan; nilai tidak ditampilkan.\n";
        return;
    }
    if (($args[1] ?? '') !== '--apply') throw new RuntimeException('Gunakan --apply atau --sync-github-secret.');
    $encoded = getenv('WHATSAPP_GATEWAY_CONFIG_B64');
    if (!$encoded) throw new RuntimeException('GitHub Secret konfigurasi Gateway belum tersedia.');
    $decoded = base64_decode($encoded, true);
    if ($decoded === false) throw new RuntimeException('Format secret konfigurasi Gateway tidak valid.');
    $settings = gatewaySettings(json_decode($decoded, true, 512, JSON_THROW_ON_ERROR));
    $changed = gatewayUpdateEnv($envPath, $settings);
    $saved = gatewayReadEnv($envPath);
    foreach ($settings as $name => $value) {
        if (($saved[$name] ?? null) !== $value) throw new RuntimeException('Konfigurasi Gateway VPS tidak cocok.');
    }
    echo $changed ? "Konfigurasi Gateway dipasang; backup .env tersimpan di writable/config-backups.\n" : "Konfigurasi Gateway sudah sesuai; .env tidak diubah.\n";
    echo "Verifikasi dotenv aplikasi: URL, API key, dan nomor pengirim valid. Tidak ada pesan yang dikirim.\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try { gatewayMain($argv); } catch (Throwable $error) {
        // Do not echo parser exceptions, which could include configuration data.
        fwrite(STDERR, "Konfigurasi Gateway gagal. Periksa izin file dan format secret; nilai rahasia tidak ditampilkan.\n");
        exit(1);
    }
}
