<?php
namespace App\Libraries;
require_once __DIR__.'/NationalHolidays.php';

final class SchoolCalendar
{
    public const SPECIAL_HOLIDAYS=['2026-08-17'=>'Libur sekolah.','2026-08-18'=>'Libur sekolah.','2026-08-25'=>'Libur sekolah.'];
    public const PJJ_DATES=['2026-08-27','2026-08-28','2026-09-07','2026-09-08'];

    private static ?array $entries=null;
    public static function reset(): void {self::$entries=null;}
    public static function entries(): array
    {
        if(self::$entries!==null)return self::$entries;
        $entries=NationalHolidays::dates();$rows=null;
        if(function_exists('db_connect')){
            $db=db_connect();
            if($db->tableExists('kalender_akademik'))$rows=$db->table('kalender_akademik')->get()->getResultArray();
        }
        if($rows===null){
            $rows=[];
            foreach(self::SPECIAL_HOLIDAYS as $date=>$note)$rows[]=['tanggal'=>$date,'judul'=>'Libur sekolah','jenis'=>'Hari Libur','dampak_absensi'=>'Libur','keterangan'=>$note];
            foreach(self::PJJ_DATES as $date)$rows[]=['tanggal'=>$date,'judul'=>'Pembelajaran Jarak Jauh (PJJ)','jenis'=>'PJJ','dampak_absensi'=>'PJJ','keterangan'=>''];
        }
        foreach($rows as $row){if($row['jenis']==='Hari Pengambilan Raport')$row['dampak_absensi']='TanpaAbsen';$entries[$row['tanggal']]=['judul'=>$row['judul'],'jenis'=>$row['jenis'],'dampak_absensi'=>$row['dampak_absensi'],'keterangan'=>$row['keterangan']??'','sumber'=>'Sekolah','dapat_diubah'=>true];}
        ksort($entries);return self::$entries=$entries;
    }
    public static function clientData(): array {return ['entries'=>self::entries(),'sources'=>NationalHolidays::SOURCES];}
    public static function specialHolidayDates(): array {return array_keys(array_filter(self::entries(),static fn($row)=>$row['dampak_absensi']==='Libur'));}
    public static function attendanceOverrideDates(): array {return array_keys(array_filter(self::entries(),static fn($row)=>in_array($row['dampak_absensi'],['Libur','PJJ','TanpaAbsen'],true)));}
    public static function isPjj(string $date): bool {return (self::entries()[$date]['dampak_absensi']??'')==='PJJ';}
    public static function attendanceOverride(string $date): ?string
    {
        $entry=self::entries()[$date]??null;
        if(self::isReportDay($date))return 'Pengambilan Raport';
        if($entry)return $entry['dampak_absensi']==='Normal'?null:($entry['dampak_absensi']==='TanpaAbsen'?'Tanpa absensi':$entry['dampak_absensi']);
        return self::isWeekend($date)?'Libur':null;
    }
    public static function isReportDay(string $date): bool {return (self::entries()[$date]['jenis']??'')==='Hari Pengambilan Raport';}
    public static function isWithoutAttendance(string $date): bool {return (self::entries()[$date]['dampak_absensi']??'')==='TanpaAbsen';}
    public static function isSpecialHoliday(string $date): bool {return (self::entries()[$date]['dampak_absensi']??'')==='Libur';}
    public static function isHoliday(string $date): bool {return self::attendanceOverride($date)==='Libur';}
    /** Effective attendance only: calendar overrides and weekends cannot inflate absence totals. */
    public static function attendanceFilterSql(string $column='tanggal'): string
    {
        if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/D',$column))throw new \InvalidArgumentException('Kolom tanggal tidak valid.');
        $quote=static fn($date)=>"'".$date."'";
        $exclude=implode(',',array_map($quote,self::attendanceOverrideDates()));
        $normal=array_keys(array_filter(self::entries(),static fn($row)=>$row['dampak_absensi']==='Normal'));
        $weekday='DAYOFWEEK('.$column.') NOT IN (1,7)';
        if($normal)$weekday='('.$weekday.' OR '.$column.' IN ('.implode(',',array_map($quote,$normal)).'))';
        return '('.($exclude?$column.' NOT IN ('.$exclude.') AND ':'').$weekday.')';
    }
    public static function isWeekend(string $date): bool
    {
        $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('Asia/Jakarta'));
        return $day&&$day->format('Y-m-d')===$date&&(int)$day->format('N')>=6;
    }
    public static function info(\DateTimeInterface $instant): array
    {
        $date=\DateTimeImmutable::createFromInterface($instant)->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
        $entry=self::entries()[$date]??null;$status=self::attendanceOverride($date);
        return ['tanggal'=>$date,'hari_libur'=>$status==='Libur','pjj'=>$status==='PJJ','tanpa_absensi'=>self::isWithoutAttendance($date),'status'=>$status,
            'judul'=>$entry['judul']??($status==='Libur'?'Libur akhir pekan':'Hari sekolah'),
            'jenis'=>$entry['jenis']??($status==='Libur'?'Akhir Pekan':'Hari Sekolah'),
            'keterangan'=>$entry?implode('. ',array_filter([$entry['judul'],$entry['keterangan']??''],static fn($value)=>$value!=='')):($status==='Libur'?'Libur akhir pekan (Sabtu dan Minggu).':'Hari sekolah'),
            'agenda'=>$entry,'dampak_absensi'=>$entry['dampak_absensi']??($status??'Normal')];
    }
}
