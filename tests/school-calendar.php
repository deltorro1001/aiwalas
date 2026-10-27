<?php
require dirname(__DIR__).'/app/Libraries/SchoolCalendar.php';
use App\Libraries\SchoolCalendar;
foreach([
    ['2026-10-30T16:59:59+00:00','2026-10-30',false],
    ['2026-10-30T17:00:00+00:00','2026-10-31',true],
    ['2026-10-31T12:00:00+07:00','2026-10-31',true],
    ['2026-11-01T23:59:59+07:00','2026-11-01',true],
    ['2026-11-01T17:00:00+00:00','2026-11-02',false],
] as [$instant,$date,$holiday]){
    $info=SchoolCalendar::info(new DateTimeImmutable($instant));
    if($info['tanggal']!==$date||$info['hari_libur']!==$holiday)throw new RuntimeException('Incorrect school calendar at '.$instant);
}
if(SchoolCalendar::isWeekend('2026-02-31'))throw new RuntimeException('Invalid date accepted');
echo "PASS: Saturday/Sunday holidays, Friday/Monday school days, exact Jakarta midnight across UTC and invalid dates.\n";
