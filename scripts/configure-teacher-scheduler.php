<?php
declare(strict_types=1);
require_once __DIR__ . '/configure-whatsapp-gateway.php';

function teacherSchedulerEnv(string $original): string
{
    // Initialize once; preserve an administrator's later choice to disable sending.
    if (preg_match('/^[ \t]*(?:export[ \t]+)?WHATSAPP_TEACHER_AUTOMATION_ENABLED[ \t]*=/m', $original)) return $original;
    $newline = str_contains($original, "\r\n") ? "\r\n" : "\n";
    return rtrim($original, "\r\n") . $newline . 'WHATSAPP_TEACHER_AUTOMATION_ENABLED = true' . $newline;
}

function teacherSchedulerCron(string $original, string $root, string $php): string
{
    if (preg_match('/[\r\n%]/', $root . $php)) throw new RuntimeException('Lokasi scheduler tidak didukung.');
    $lines = preg_split('/\r?\n/', $original);
    $lines = array_filter($lines, static fn($line) => !str_contains($line, '# aiwalas-teacher-scheduler') && !str_contains($line, '# aiwalas-student-grade-scheduler'));
    $content = rtrim(implode("\n", $lines), "\n");
    $entry = '* * * * * cd ' . escapeshellarg($root) . ' && ' . escapeshellarg($php) . ' spark whatsapp:teacher-schedule --send >> ' . escapeshellarg($root . '/writable/logs/teacher-schedule-cron.log') . ' 2>&1 # aiwalas-teacher-scheduler';
    $gradeEntry = '* * * * * cd ' . escapeshellarg($root) . ' && ' . escapeshellarg($php) . ' spark whatsapp:student-grades --send >> ' . escapeshellarg($root . '/writable/logs/student-grade-cron.log') . ' 2>&1 # aiwalas-student-grade-scheduler';
    return ($content === '' ? '' : $content . "\n") . $entry . "\n" . $gradeEntry . "\n";
}

function teacherSchedulerProcess(array $command, string $input = ''): array
{
    $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Perintah scheduler gagal dijalankan.');
    fwrite($pipes[0], $input); fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    return [proc_close($process), $output, $error];
}

function teacherSchedulerMain(array $args): void
{
    if (PHP_SAPI !== 'cli' || ($args[1] ?? '') !== '--apply') throw new RuntimeException('Gunakan CLI --apply.');
    $root = dirname(__DIR__); $path = $root . '/.env';
    if (!is_file($path) || is_link($path)) throw new RuntimeException('.env harus tersedia dan bukan symlink.');
    gatewaySettings(gatewayReadEnv($path)); // Validate without revealing or contacting the gateway.
    $original = file_get_contents($path);
    if ($original === false) throw new RuntimeException('.env tidak dapat dibaca.');
    $updated = teacherSchedulerEnv($original);
    [$code,$cron,$error] = teacherSchedulerProcess(['crontab','-l']);
    if ($code !== 0 && !($code === 1 && str_contains(strtolower($error), 'no crontab'))) throw new RuntimeException('Crontab tidak dapat dibaca.');
    $cron = $code === 0 ? $cron : '';
    $nextCron = teacherSchedulerCron($cron, $root, PHP_BINARY);
    // Install before enabling; a failed installation must not activate a new scheduler.
    if ($nextCron !== $cron && teacherSchedulerProcess(['crontab','-'], $nextCron)[0] !== 0) throw new RuntimeException('Crontab gagal dipasang.');
    if ($updated !== $original) {
        $directory = $root . '/writable/config-backups';
        if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new RuntimeException('Direktori backup gagal dibuat.');
        if (!chmod($directory,0700)) throw new RuntimeException('Direktori backup gagal dilindungi.');
        $backup = $directory . '/env-before-teacher-scheduler-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.bak';
        $handle = fopen($backup,'x');
        if (!$handle) throw new RuntimeException('Backup gagal dibuat.');
        try {
            if (!chmod($backup,0600) || fwrite($handle,$original) !== strlen($original)) throw new RuntimeException('Backup gagal disimpan.');
        } finally { fclose($handle); }
        $stat = stat($path); $temp = tempnam($root,'.teacher-env-');
        if ($temp === false) throw new RuntimeException('File sementara gagal dibuat.');
        try {
            if (file_put_contents($temp,$updated,LOCK_EX) !== strlen($updated)) throw new RuntimeException('Konfigurasi gagal ditulis.');
            $tempStat = stat($temp);
            if ($tempStat['uid'] !== $stat['uid'] && !chown($temp,$stat['uid'])) throw new RuntimeException('Owner gagal dipertahankan.');
            if ($tempStat['gid'] !== $stat['gid'] && !chgrp($temp,$stat['gid'])) throw new RuntimeException('Group gagal dipertahankan.');
            if (!chmod($temp,$stat['mode'] & 0777)) throw new RuntimeException('Permission gagal dipertahankan.');
            if ((gatewayReadEnv($temp)['WHATSAPP_TEACHER_AUTOMATION_ENABLED'] ?? '') !== 'true') throw new RuntimeException('Verifikasi dotenv gagal.');
            if (!rename($temp,$path)) throw new RuntimeException('Konfigurasi gagal dipasang.');
        } finally { if (is_file($temp)) unlink($temp); }
    }
    if (teacherSchedulerProcess(['crontab','-l'])[1] !== $nextCron) throw new RuntimeException('Verifikasi crontab gagal.');
    $enabled = filter_var(gatewayReadEnv($path)['WHATSAPP_TEACHER_AUTOMATION_ENABLED'] ?? false,FILTER_VALIDATE_BOOLEAN);
    echo 'Scheduler setiap menit terpasang; otomatisasi ' . ($enabled ? 'aktif' : 'nonaktif sesuai konfigurasi tersimpan') . ". Tidak ada pengiriman uji.\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try { teacherSchedulerMain($argv); } catch (Throwable $error) {
        fwrite(STDERR,"Pemasangan scheduler gagal. Periksa crontab, konfigurasi gateway dan izin .env; nilai rahasia tidak ditampilkan.\n");
        exit(1);
    }
}
