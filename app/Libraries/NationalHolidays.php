<?php
namespace App\Libraries;

/** Verified SKB dates, not calculated estimates. New years require an official publication. */
final class NationalHolidays
{
    public const SOURCES=[
        2026=>'https://www.setneg.go.id/baca/index/inilah_skb_3_menteri_libur_nasional_dan_cuti_bersama_2026',
        2027=>'https://setneg.go.id/baca/index/inilah_skb_3_menteri_libur_nasional_dan_cuti_bersama_2027',
    ];
    public static function dates(): array
    {
        $national=[
            '2026-01-01'=>'Tahun Baru 2026 Masehi','2026-01-16'=>'Isra Mikraj Nabi Muhammad saw.',
            '2026-02-17'=>'Tahun Baru Imlek 2577 Kongzili','2026-03-19'=>'Hari Suci Nyepi (Tahun Baru Saka 1948)',
            '2026-03-21'=>'Idulfitri 1447 H','2026-03-22'=>'Idulfitri 1447 H',
            '2026-04-03'=>'Wafat Yesus Kristus','2026-04-05'=>'Kebangkitan Yesus Kristus (Paskah)',
            '2026-05-01'=>'Hari Buruh Internasional','2026-05-14'=>'Kenaikan Yesus Kristus',
            '2026-05-27'=>'Iduladha 1447 H','2026-05-31'=>'Hari Raya Waisak 2570 BE',
            '2026-06-01'=>'Hari Lahir Pancasila','2026-06-16'=>'Tahun Baru Islam 1448 H',
            '2026-08-17'=>'Proklamasi Kemerdekaan','2026-08-25'=>'Maulid Nabi Muhammad saw.',
            '2026-12-25'=>'Kelahiran Yesus Kristus (Natal)',
            '2027-01-01'=>'Tahun Baru 2027 Masehi','2027-01-05'=>'Isra Mikraj Nabi Muhammad saw.',
            '2027-02-06'=>'Tahun Baru Imlek 2578 Kongzili','2027-03-08'=>'Hari Suci Nyepi (Tahun Baru Saka 1949)',
            '2027-03-10'=>'Idulfitri 1448 H','2027-03-11'=>'Idulfitri 1448 H',
            '2027-03-26'=>'Wafat Yesus Kristus','2027-03-28'=>'Kebangkitan Yesus Kristus (Paskah)',
            '2027-05-01'=>'Hari Buruh Internasional','2027-05-06'=>'Kenaikan Yesus Kristus',
            '2027-05-17'=>'Iduladha 1448 H','2027-05-20'=>'Hari Raya Waisak 2571 BE',
            '2027-06-01'=>'Hari Lahir Pancasila','2027-06-06'=>'Tahun Baru Islam 1449 H',
            '2027-08-15'=>'Maulid Nabi Muhammad saw.','2027-08-17'=>'Proklamasi Kemerdekaan',
            '2027-12-25'=>'Kelahiran Yesus Kristus (Natal)','2027-12-26'=>'Isra Mikraj Nabi Muhammad saw.',
        ];
        $leave=[
            '2026-02-16'=>'Tahun Baru Imlek','2026-03-18'=>'Hari Suci Nyepi',
            '2026-03-20'=>'Idulfitri','2026-03-23'=>'Idulfitri','2026-03-24'=>'Idulfitri',
            '2026-05-15'=>'Kenaikan Yesus Kristus','2026-05-28'=>'Iduladha','2026-12-24'=>'Natal',
            '2027-02-05'=>'Tahun Baru Imlek','2027-03-09'=>'Idulfitri','2027-03-12'=>'Idulfitri','2027-03-15'=>'Idulfitri',
            '2027-03-25'=>'Wafat Yesus Kristus','2027-05-18'=>'Iduladha','2027-05-19'=>'Waisak','2027-12-24'=>'Natal',
        ];
        $result=[];
        foreach(['Libur Nasional'=>$national,'Cuti Bersama'=>$leave] as $kind=>$dates)foreach($dates as $date=>$title)$result[$date]=['judul'=>$title,'jenis'=>$kind,'dampak_absensi'=>'Libur','keterangan'=>'','sumber'=>self::SOURCES[(int)substr($date,0,4)]];
        ksort($result);return $result;
    }
}
