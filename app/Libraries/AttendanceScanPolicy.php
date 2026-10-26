<?php
namespace App\Libraries;

final class AttendanceScanPolicy
{
    public static function at(\DateTimeInterface $scannedAt): array
    {
        $local=\DateTimeImmutable::createFromInterface($scannedAt)->setTimezone(new \DateTimeZone('Asia/Jakarta'));
        $time=$local->format('H:i:s');
        return ['tanggal'=>$local->format('Y-m-d'),'waktu_scan'=>$time,'status'=>$time>='06:41:00'?'Terlambat':'Hadir'];
    }
}
