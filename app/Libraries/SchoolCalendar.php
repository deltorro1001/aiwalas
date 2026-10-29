<?php
namespace App\Libraries;

final class SchoolCalendar
{
    public const SPECIAL_HOLIDAYS=['2026-08-17'=>'Libur sekolah.','2026-08-18'=>'Libur sekolah.','2026-08-25'=>'Libur sekolah.'];
    public const PJJ_DATES=['2026-08-27','2026-08-28','2026-09-07','2026-09-08'];

    public static function specialHolidayDates(): array {return array_keys(self::SPECIAL_HOLIDAYS);}
    public static function attendanceOverrideDates(): array {return array_merge(self::specialHolidayDates(),self::PJJ_DATES);}
    public static function isPjj(string $date): bool {return in_array($date,self::PJJ_DATES,true);}
    public static function attendanceOverride(string $date): ?string {return self::isSpecialHoliday($date)?'Libur':(self::isPjj($date)?'PJJ':null);}
    public static function isSpecialHoliday(string $date): bool {return isset(self::SPECIAL_HOLIDAYS[$date]);}
    public static function isHoliday(string $date): bool {return self::isSpecialHoliday($date)||self::isWeekend($date);}
    public static function isWeekend(string $date): bool
    {
        $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('Asia/Jakarta'));
        return $day&&$day->format('Y-m-d')===$date&&(int)$day->format('N')>=6;
    }
    public static function info(\DateTimeInterface $instant): array
    {
        $date=\DateTimeImmutable::createFromInterface($instant)->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
        return ['tanggal'=>$date,'hari_libur'=>self::isHoliday($date),'pjj'=>self::isPjj($date),'status'=>self::attendanceOverride($date)??(self::isWeekend($date)?'Libur':null),'keterangan'=>self::SPECIAL_HOLIDAYS[$date]??(self::isPjj($date)?'Pembelajaran Jarak Jauh (PJJ).':(self::isWeekend($date)?'Libur akhir pekan (Sabtu dan Minggu).':'Hari sekolah'))];
    }
}
