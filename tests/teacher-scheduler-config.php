<?php
declare(strict_types=1);
require dirname(__DIR__) . '/scripts/configure-teacher-scheduler.php';
function schedulerAssert(bool $ok,string $message): void {if (!$ok) throw new RuntimeException($message);}
$original = "# fixture\r\ndatabase.default.password = 'keep'\r\n";
$updated = teacherSchedulerEnv($original);
schedulerAssert(str_starts_with($updated,$original),'Unrelated config changed');
schedulerAssert(str_contains($updated,"WHATSAPP_TEACHER_AUTOMATION_ENABLED = true\r\n"),'Activation missing');
schedulerAssert(teacherSchedulerEnv($updated) === $updated,'Activation not idempotent');
$disabled = $original . "WHATSAPP_TEACHER_AUTOMATION_ENABLED = false\r\n";
schedulerAssert(teacherSchedulerEnv($disabled) === $disabled,'Administrator disable overwritten');
$cron = "MAILTO=admin\n0 3 * * * /usr/bin/backup\n";
$next = teacherSchedulerCron($cron,'/var/www/app space', '/usr/bin/php');
schedulerAssert(str_starts_with($next,$cron),'Unrelated cron changed');
schedulerAssert(substr_count($next,'# aiwalas-teacher-scheduler') === 1,'Duplicate scheduler');
schedulerAssert(substr_count($next,'# aiwalas-student-grade-scheduler') === 1,'Missing student grade scheduler');
schedulerAssert(str_contains($next,'spark whatsapp:student-grades --send'),'Missing automatic grade command');
schedulerAssert(str_contains($next,"cd '/var/www/app space'"),'Path not quoted');
schedulerAssert(teacherSchedulerCron($next,'/var/www/app space','/usr/bin/php') === $next,'Cron not idempotent');
schedulerAssert(substr_count(teacherSchedulerCron($next . $next,'/var/www/app space','/usr/bin/php'),'# aiwalas-teacher-scheduler') === 1,'Duplicate scheduler retained');
foreach (["/tmp/app\ninjected",'/tmp/app%bad'] as $path) {
    try {teacherSchedulerCron('', $path,'/usr/bin/php');throw new LogicException('Unsafe path accepted');}
    catch (RuntimeException $e) {if ($e instanceof LogicException) throw $e;}
}
echo "PASS: Scheduler activation once, administrator disable preserved, unrelated settings/crons preserved, quoting and duplicate prevention. No real config or crontab changed.\n";
