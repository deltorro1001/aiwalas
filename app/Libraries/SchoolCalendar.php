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
    public static function isNoCeremonyMonday(string $date): bool
    {
        $entry=self::entries()[$date]??null;
        if(!$entry||$entry['jenis']!=='Senin Tanpa Upacara')return false;
        $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('Asia/Jakarta'));
        return $day&&$day->format('Y-m-d')===$date&&$day->format('N')==='1';
    }
    /** Apply the alternate Monday timetable while leaving both recess periods fixed. */
    public static function applyNoCeremonyMonday(array $rows,string $date,bool $includeBreaks=false): array
    {
        if(!self::isNoCeremonyMonday($date))return $rows;
        $times=['KIK'=>['06:45:00','08:15:00'],'Sejarah'=>['08:15:00','09:45:00'],'Editing Film'=>['10:00:00','12:15:00'],'Bahasa Inggris'=>['12:45:00','14:15:00']];
        foreach($rows as &$row){
            if(($row['hari']??'Senin')!=='Senin')continue;
            $subject=trim((string)($row['nama_mata_pelajaran']??''));
            if(isset($times[$subject])){
                [$row['jam_mulai'],$row['jam_selesai']]=$times[$subject];
            }else{
                foreach(['jam_mulai','jam_selesai'] as $field){
                    if(empty($row[$field]))continue;
                    $time=substr((string)$row[$field],0,8);
                    $parsed=\DateTimeImmutable::createFromFormat('!H:i:s',$time,new \DateTimeZone('Asia/Jakarta'));
                    if(!$parsed)$parsed=\DateTimeImmutable::createFromFormat('!H:i',$time,new \DateTimeZone('Asia/Jakarta'));
                    if($parsed)$row[$field]=$parsed->modify('-45 minutes')->format(strlen((string)$row[$field])>5?'H:i:s':'H:i');
                }
            }
        }
        unset($row);
        if($includeBreaks){
            $rows[]=['hari'=>'Senin','jam_mulai'=>'09:45:00','jam_selesai'=>'10:00:00','nama_mata_pelajaran'=>'Istirahat Pertama','nama_lengkap'=>'Waktu istirahat','keterangan'=>'Waktu istirahat'];
            $rows[]=['hari'=>'Senin','jam_mulai'=>'12:15:00','jam_selesai'=>'12:45:00','nama_mata_pelajaran'=>'Istirahat Kedua','nama_lengkap'=>'Waktu istirahat','keterangan'=>'Waktu istirahat'];
        }
        usort($rows,static fn($a,$b)=>strcmp((string)($a['jam_mulai']??''),(string)($b['jam_mulai']??'')));
        return $rows;
    }
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
