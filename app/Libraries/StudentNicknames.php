<?php
namespace App\Libraries;

/** Curated spellings from Nama Panggilan Siswa.xlsx; identity is always the NIS. */
final class StudentNicknames
{
    private static ?array $names=null;
    public static function forNis(string $nis): array
    {
        if(self::$names===null){
            self::$names=[];
            $rows=json_decode(file_get_contents(ROOTPATH.'resources/data/student-nicknames.json'),true,512,JSON_THROW_ON_ERROR);
            foreach($rows as $row)self::$names[(string)$row['nis']]=array_values(array_unique($row['panggilan']));
        }
        return self::$names[$nis]??[];
    }
}
