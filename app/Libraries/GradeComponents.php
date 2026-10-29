<?php
namespace App\Libraries;

/** Shared identities for templates, grade writers and all grade displays. */
final class GradeComponents
{
    public static function labels(): array
    {
        $labels=[];
        for($i=1;$i<=6;$i++)$labels['UH'.$i]='UH'.$i;
        for($i=1;$i<=6;$i++)$labels['Tugas'.$i]='Tugas '.$i;
        return $labels+['ATS'=>'ATS','ASS'=>'ASS'];
    }
    public static function key($value): ?string
    {
        if(!is_string($value))return null;
        $value=trim($value);
        if(preg_match('/^(UH|Tugas)\s*([1-6])$/i',$value,$match))return (strtoupper($match[1])==='UH'?'UH':'Tugas').$match[2];
        return in_array(strtoupper($value),['ATS','ASS'],true)?strtoupper($value):null;
    }
    public static function label(string $value): string {return self::labels()[self::key($value)]??$value;}
    public static function order(string $value): int {return (int)array_search(self::key($value),array_keys(self::labels()),true)+1;}
}
