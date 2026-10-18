<?php
require dirname(__DIR__).'/app/Libraries/TeacherNicknameAccounts.php';
use App\Libraries\TeacherNicknameAccounts;
foreach ([['Pak Ruby','ruby'],['Bu Erna','erna'],['Pak Torro','torro'],['Bu Sri Muljani','srimuljani'],['Mom Lily','lily'],['Bu nani','nani'],['  Bapak Robert  ','robert']] as [$name,$expected]) {
    $actual=TeacherNicknameAccounts::username(['id'=>1,'nama_panggilan'=>$name,'nama_lengkap'=>'Fallback Name']);
    if($actual!==$expected)throw new RuntimeException('Nickname normalization failed.');
}
if(TeacherNicknameAccounts::username(['id'=>1,'nama_panggilan'=>null,'nama_lengkap'=>'Ruby Eka Prawira'])!=='ruby')throw new RuntimeException('Missing nickname fallback failed.');
echo "PASS: Lowercase teacher nicknames, honorific removal, spaces and missing nickname fallback.\n";
