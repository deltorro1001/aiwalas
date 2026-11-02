<?php
require dirname(__DIR__).'/app/Libraries/SchoolCalendar.php';
use App\Libraries\SchoolCalendar;
foreach([
    ['2026-10-30T16:59:59+00:00','2026-10-30',false],
    ['2026-10-30T17:00:00+00:00','2026-10-31',true],
    ['2026-10-31T12:00:00+07:00','2026-10-31',true],
    ['2026-11-01T23:59:59+07:00','2026-11-01',true],
    ['2026-11-01T17:00:00+00:00','2026-11-02',false],
    ['2026-08-16T17:00:00+00:00','2026-08-17',true],
    ['2026-08-17T17:00:00+00:00','2026-08-18',true],
    ['2026-08-18T17:00:00+00:00','2026-08-19',false],
    ['2027-08-17T08:00:00+07:00','2027-08-17',true],
    ['2026-08-24T16:59:59+00:00','2026-08-24',false],
    ['2026-08-24T17:00:00+00:00','2026-08-25',true],
    ['2026-08-25T17:00:00+00:00','2026-08-26',false],
    ['2027-08-25T08:00:00+07:00','2027-08-25',false],
] as [$instant,$date,$holiday]){
    $info=SchoolCalendar::info(new DateTimeImmutable($instant));
    if($info['tanggal']!==$date||$info['hari_libur']!==$holiday)throw new RuntimeException('Incorrect school calendar at '.$instant);
}
if(SchoolCalendar::isWeekend('2026-02-31'))throw new RuntimeException('Invalid date accepted');
foreach(['2026-08-27','2026-08-28','2026-09-07','2026-09-08'] as $date){
    $info=SchoolCalendar::info(new DateTimeImmutable($date.'T08:00:00+07:00'));
    if($info['hari_libur']||!$info['pjj']||$info['status']!=='PJJ'||SchoolCalendar::attendanceOverride($date)!=='PJJ')throw new RuntimeException('PJJ date inconsistent');
}
foreach(['2026-08-26','2026-08-31','2027-08-27','2027-08-28','2026-09-06','2026-09-09','2027-09-07','2027-09-08'] as $date)if(SchoolCalendar::isPjj($date))throw new RuntimeException('PJJ applied to another day/year');
if(SchoolCalendar::info(new DateTimeImmutable('2026-09-06T17:00:00+00:00'))['status']!=='PJJ')throw new RuntimeException('September PJJ Jakarta midnight incorrect');
if(SchoolCalendar::info(new DateTimeImmutable('2026-09-08T17:00:00+00:00'))['status']!==null)throw new RuntimeException('September PJJ did not end at Jakarta midnight');
if(SchoolCalendar::info(new DateTimeImmutable('2026-08-26T17:00:00+00:00'))['status']!=='PJJ')throw new RuntimeException('PJJ Jakarta midnight incorrect');
if(SchoolCalendar::info(new DateTimeImmutable('2026-08-28T17:00:00+00:00'))['status']!=='Libur')throw new RuntimeException('PJJ did not end at Jakarta midnight');
foreach(['2026-08-17','2026-08-18','2026-08-25'] as $date)if(!SchoolCalendar::isSpecialHoliday($date)||SchoolCalendar::isWeekend($date))throw new RuntimeException('Special holiday treated as weekend');
echo "PASS: Weekend and 17, 18, 25 August 2026 holidays, 27-28 August PJJ, adjacent school days/year, exact Jakarta midnight across UTC and invalid dates.\n";

foreach([2026=>17,2027=>18] as $year=>$expected){$national=0;$leave=0;foreach(\App\Libraries\NationalHolidays::dates() as $date=>$entry)if(str_starts_with($date,(string)$year)){$national+=$entry['jenis']==='Libur Nasional';$leave+=$entry['jenis']==='Cuti Bersama';}if($national!==$expected||$leave!==8)throw new RuntimeException('Official SKB totals incorrect');}
foreach(['2026-01-01','2026-03-23','2027-12-26'] as $date)if(SchoolCalendar::attendanceOverride($date)!=='Libur')throw new RuntimeException('National/collective holiday missing');
if(isset(SchoolCalendar::entries()['2028-08-17']))throw new RuntimeException('Unpublished holidays invented');
echo "PASS: Official 2026/2027 SKB dates, 17/18 national holidays and 8 collective leave days per year; no guessed future dates.\n";
