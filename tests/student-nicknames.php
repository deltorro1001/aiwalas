<?php
// Pure registry validation: no database, filesystem writes or gateway calls.
define('ROOTPATH',dirname(__DIR__).'/');
require ROOTPATH.'app/Libraries/StudentNicknames.php';
$rows=json_decode(file_get_contents(ROOTPATH.'resources/data/student-nicknames.json'),true,512,JSON_THROW_ON_ERROR);
if(count($rows)!==35||count(array_unique(array_column($rows,'nis')))!==35)throw new RuntimeException('Expected 35 distinct students.');
foreach($rows as $row){
    if(!preg_match('/^\d+$/D',$row['nis'])||!$row['panggilan']||array_filter($row['panggilan'],static fn($alias)=>!is_string($alias)||trim($alias)===''))throw new RuntimeException('Invalid nickname entry.');
    if(\App\Libraries\StudentNicknames::forNis($row['nis'])!==$row['panggilan'])throw new RuntimeException('NIS mapping differs from source.');
}
if(!in_array('weni',\App\Libraries\StudentNicknames::forNis('13190'),true)||!in_array('timoti',\App\Libraries\StudentNicknames::forNis('13186'),true)||\App\Libraries\StudentNicknames::forNis('unknown')!==[])throw new RuntimeException('Nickname lookup failed.');
echo "PASS: 35 unique NIS identities, all workbook aliases, phonetic variants and unknown identity.\n";
