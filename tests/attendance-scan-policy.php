<?php
require dirname(__DIR__).'/app/Libraries/AttendanceScanPolicy.php';
use App\Libraries\AttendanceScanPolicy;

foreach([
    ['2026-10-26T05:30:00+07:00','Hadir','05:30:00'],
    ['2026-10-26T06:30:00+07:00','Hadir','06:30:00'],
    ['2026-10-26T06:31:00+07:00','Hadir','06:31:00'],
    ['2026-10-26T06:40:00+07:00','Hadir','06:40:00'],
    ['2026-10-26T06:40:59+07:00','Hadir','06:40:59'],
    ['2026-10-26T06:41:00+07:00','Terlambat','06:41:00'],
    ['2026-10-26T06:41:01+07:00','Terlambat','06:41:01'],
    ['2026-10-26T07:00:00+07:00','Terlambat','07:00:00'],
    ['2026-10-25T23:40:59+00:00','Hadir','06:40:59'],
    ['2026-10-25T23:41:00+00:00','Terlambat','06:41:00'],
] as [$instant,$status,$time]){
    $result=AttendanceScanPolicy::at(new DateTimeImmutable($instant));
    if($result!==['tanggal'=>'2026-10-26','waktu_scan'=>$time,'status'=>$status])throw new RuntimeException('Incorrect scan classification at '.$instant);
}
echo "PASS: Hadir through 06:40:59, Terlambat from 06:41:00, consistent scan date/time and Jakarta timezone across UTC date rollover.\n";
