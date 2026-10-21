<?php
namespace App\Libraries;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class NotificationDate
{
    public static function today(): string
    {
        return (new DateTimeImmutable('now',new DateTimeZone(config('App')->appTimezone)))->format('Y-m-d');
    }
    public static function range(string $date): array
    {
        $start=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone(config('App')->appTimezone));
        if(!$start||$start->format('Y-m-d')!==$date)throw new InvalidArgumentException('Tanggal harus valid dengan format YYYY-MM-DD.');
        return [$start->format('Y-m-d H:i:s'),$start->modify('+1 day')->format('Y-m-d H:i:s')];
    }
}
