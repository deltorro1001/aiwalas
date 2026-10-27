<?php
namespace App\Libraries;

final class SchoolCalendar
{
    public static function isWeekend(string $date): bool
    {
        $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('Asia/Jakarta'));
        return $day&&$day->format('Y-m-d')===$date&&(int)$day->format('N')>=6;
    }
    public static function info(\DateTimeInterface $instant): array
    {
        $date=\DateTimeImmutable::createFromInterface($instant)->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
        return ['tanggal'=>$date,'hari_libur'=>self::isWeekend($date),'keterangan'=>self::isWeekend($date)?'Libur akhir pekan (Sabtu dan Minggu).':'Hari sekolah'];
    }
}
