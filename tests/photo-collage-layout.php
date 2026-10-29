<?php
// Render real JPEG collages with synthetic photos; no database changes or gateway calls.
$root=dirname(__DIR__);define('ENVIRONMENT','development');define('FCPATH',$root.'/public/');require $root.'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
use App\Libraries\StudentPhotoImages;
$files=[];$photos=[];$created=new DateTimeImmutable('2026-10-28 13:15:16',new DateTimeZone('Asia/Jakarta'));
function layoutAssert($ok,$message){if(!$ok)throw new RuntimeException($message);}
try{
    for($i=0;$i<35;$i++){
        $file=bin2hex(random_bytes(24)).'.jpg';$files[]=$file;
        $r=40+($i%4)*55;$g=40+((int)floor($i/4)%3)*65;$b=40+((int)floor($i/12)%3)*65;
        $image=imagecreatetruecolor(320,240);imagefill($image,0,0,imagecolorallocate($image,$r,$g,$b));imagejpeg($image,StudentPhotoImages::path($file),95);imagedestroy($image);
        $photos[]=['id'=>$i+1,'berkas'=>$file,'lebar'=>320,'tinggi'=>240,'nama_lengkap'=>'Siswa '.($i+1),'nis'=>13000+$i,'keterangan'=>'Kegiatan praktik'];
    }
    $normalized=bin2hex(random_bytes(24)).'.jpg';$files[]=$normalized;
    $size=StudentPhotoImages::prepare(StudentPhotoImages::path($photos[0]['berkas']),StudentPhotoImages::path($normalized));
    layoutAssert($size===['lebar'=>640,'tinggi'=>480],'Upload image gained a timestamp/footer');
    $image=imagecreatefromjpeg(StudentPhotoImages::path($normalized));$pixel=imagecolorat($image,320,470);imagedestroy($image);
    foreach([($pixel>>16)&255,($pixel>>8)&255,$pixel&255] as $channel)layoutAssert(abs($channel-40)<12,'Upload photo has an added timestamp stripe');
    foreach([1,2,7,20,35] as $count){
        $output=bin2hex(random_bytes(24)).'.jpg';$files[]=$output;
        StudentPhotoImages::collage(array_slice($photos,0,$count),'11PF1','2026-10-28',$created,StudentPhotoImages::path($output));
        $image=imagecreatefromjpeg(StudentPhotoImages::path($output));layoutAssert(imagesx($image)===2480&&imagesy($image)===3508,'Page dimensions changed with photo count');
        $seen=array_fill(0,$count,0);
        for($y=140;$y<3348;$y+=10)for($x=40;$x<2440;$x+=10){
            $pixel=imagecolorat($image,$x,$y);$r=($pixel>>16)&255;$g=($pixel>>8)&255;$b=$pixel&255;
            $ri=(int)round(($r-40)/55);$gi=(int)round(($g-40)/65);$bi=(int)round(($b-40)/65);$i=$ri+4*$gi+12*$bi;
            if($ri>=0&&$ri<4&&$gi>=0&&$gi<3&&$bi>=0&&$bi<3&&$i<$count&&abs($r-(40+$ri*55))<12&&abs($g-(40+$gi*65))<12&&abs($b-(40+$bi*65))<12)$seen[$i]++;
        }
        foreach($seen as $i=>$samples)layoutAssert($samples>200,'Photo '.($i+1).' missing or cropped out of '.$count.' photo collage');
        $left=2480;$right=0;$line1=$line2=0;
        for($y=3355;$y<3508;$y++)for($x=0;$x<2480;$x++){
            $pixel=imagecolorat($image,$x,$y);if((($pixel>>16)&255)<110&&(($pixel>>8)&255)<110&&($pixel&255)<110){$left=min($left,$x);$right=max($right,$x);if($y<3420)$line1++;else $line2++;}
        }
        layoutAssert($left>1850&&$right>2400&&$right<2450&&$line1>100&&$line2>100,'Creator/date footer is not two lines aligned at lower right');
        imagedestroy($image);
        if($count===35&&isset($argv[1]))copy(StudentPhotoImages::path($output),$argv[1]);
    }
    foreach([[],array_merge($photos,[$photos[0]])] as $invalid){
        try{StudentPhotoImages::collage($invalid,'Test','2026-10-28',$created,StudentPhotoImages::path($normalized));throw new RuntimeException('Invalid photo count accepted');}catch(InvalidArgumentException $expected){}
    }
    echo "PASS: 1, 2, 7, 20 and 35 distinct photos all remain on one fixed page, automatic resizing, two-line lower-right creator/date footer, and no upload timestamp burned into source images.\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");$failed=true;}
finally{foreach($files as $file)if(is_file(StudentPhotoImages::path($file)))unlink(StudentPhotoImages::path($file));}
if(isset($failed))exit(1);
