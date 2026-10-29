<?php
namespace App\Libraries;

use InvalidArgumentException;
use RuntimeException;

final class StudentPhotoImages
{
    public const MAX_PHOTOS=35;
    public static function directory(): string
    {
        $path=WRITEPATH.'uploads/student-photos/';
        if(!is_dir($path)&&!mkdir($path,0770,true)&&!is_dir($path))throw new RuntimeException('Penyimpanan foto belum tersedia.');
        return $path;
    }
    public static function path(string $file): string
    {
        if(!preg_match('/^[a-f0-9]{48}\.jpg$/D',$file))throw new RuntimeException('Berkas foto tidak valid.');
        return self::directory().$file;
    }
    public static function snapshot(array $rows): string {return hash('sha256','single-page-segoe-ui-v5:'.implode(',',array_column($rows,'id')));}
    private static function ready(): void
    {
        if(!extension_loaded('gd'))throw new RuntimeException('Pemrosesan foto belum tersedia di server.');
        if(!function_exists('imagettftext')||!is_readable(self::font())||!is_readable(self::font(true)))throw new RuntimeException('Font Segoe UI untuk kolase belum tersedia di server.');
    }
    private static function font(bool $bold=false): string
    {
        return ROOTPATH.'resources/fonts/segoe-ui/'.($bold?'segoeuib.ttf':'segoeui.ttf');
    }
    private static function bounds(string $text,int $scale,bool $bold=false): array
    {
        $box=imagettfbbox(10*$scale,0,self::font($bold),$text);
        if($box===false)throw new RuntimeException('Font Segoe UI tidak dapat dibaca.');
        $xs=[$box[0],$box[2],$box[4],$box[6]];$ys=[$box[1],$box[3],$box[5],$box[7]];
        return ['x'=>min($xs),'y'=>min($ys),'width'=>max($xs)-min($xs),'height'=>max($ys)-min($ys)];
    }
    private static function fitText(string $text,int $scale,int $width,bool $bold=false): string
    {
        $text=preg_replace('/[\x00-\x1f\x7f]/',' ',$text);
        if($text===''||self::bounds($text,$scale,$bold)['width']<=$width)return $text;
        $low=0;$high=mb_strlen($text);
        while($low<$high){
            $mid=(int)ceil(($low+$high)/2);
            if(self::bounds(mb_substr($text,0,$mid),$scale,$bold)['width']<=$width)$low=$mid;
            else $high=$mid-1;
        }
        return mb_substr($text,0,$low);
    }
    private static function text($image,string $text,int $x,int $y,int $scale,int $color,int $width,bool $bold=false): void
    {
        $text=self::fitText($text,$scale,$width,$bold);if($text==='')return;
        $box=self::bounds($text,$scale,$bold);
        imagettftext($image,10*$scale,0,$x-$box['x'],$y-$box['y'],$color,self::font($bold),$text);
    }
    private static function contain($target,$source,int $x,int $y,int $width,int $height): void
    {
        $ratio=min($width/imagesx($source),$height/imagesy($source));$w=max(1,(int)round(imagesx($source)*$ratio));$h=max(1,(int)round(imagesy($source)*$ratio));
        imagecopyresampled($target,$source,$x+(int)(($width-$w)/2),$y+(int)(($height-$h)/2),0,0,$w,$h,imagesx($source),imagesy($source));
    }
    public static function prepare(string $source,string $destination): array
    {
        self::ready();$info=@getimagesize($source);
        if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)||$info[0]*$info[1]>12000000||min($info[0],$info[1])<1)throw new InvalidArgumentException('Gunakan foto JPG, PNG, atau WebP maksimal 12 megapiksel.');
        $image=@imagecreatefromstring(file_get_contents($source));if(!$image)throw new InvalidArgumentException('Foto tidak dapat dibaca. Pilih berkas foto lain.');
        try{
            if($info[2]===IMAGETYPE_JPEG&&function_exists('exif_read_data')){
                $exif=@exif_read_data($source);$orientation=(int)($exif['Orientation']??1);
                if(in_array($orientation,[2,4,5,7],true))imageflip($image,IMG_FLIP_HORIZONTAL);
                $degrees=match($orientation){3,4=>180,5,6=>-90,7,8=>90,default=>0};
                if($degrees){$rotated=imagerotate($image,$degrees,0);imagedestroy($image);$image=$rotated;}
            }
            $w=max(640,min(1600,imagesx($image)));$h=max(1,(int)round(imagesy($image)*$w/imagesx($image)));
            // Very tall images are constrained before allocating a new canvas.
            $h=min(2400,$h);
            $out=imagecreatetruecolor($w,$h);$white=imagecolorallocate($out,255,255,255);imagefill($out,0,0,$white);
            self::contain($out,$image,0,0,$w,$h);
            try{if(!imagejpeg($out,$destination,88))throw new RuntimeException('Foto belum tersimpan.');return ['lebar'=>$w,'tinggi'=>$h];}
            finally{imagedestroy($out);}
        }finally{imagedestroy($image);}
    }
    public static function collage(array $photos,string $class,string $date,\DateTimeImmutable $created,string $destination): void
    {
        self::ready();$count=count($photos);
        if($count<1||$count>self::MAX_PHOTOS)throw new InvalidArgumentException('Kolase memuat 1 sampai 35 foto dalam satu halaman.');
        $w=2480;$h=3508;$margin=40;$gap=20;$top=140;$bottom=160;$availableW=$w-2*$margin;$availableH=$h-$top-$bottom;
        // Pick the grid that gives the actual photo proportions the largest combined area.
        $best=null;
        for($cols=1;$cols<=min(7,$count);$cols++){
            $rows=(int)ceil($count/$cols);$cellW=(int)floor(($availableW-($cols-1)*$gap)/$cols);
            $scale=$cellW>=1000?2:1;$caption=4*15*$scale+20;$cellH=(int)floor(($availableH-($rows-1)*$gap)/$rows);$photoH=$cellH-$caption;
            if($photoH<1)continue;
            $area=0;
            foreach($photos as $photo){$pw=max(1,(int)$photo['lebar']);$ph=max(1,(int)$photo['tinggi']);$ratio=min($cellW/$pw,$photoH/$ph);$area+=$pw*$ph*$ratio*$ratio;}
            if($best===null||$area>$best['area'])$best=compact('cols','rows','cellW','cellH','photoH','scale','area');
        }
        $out=imagecreatetruecolor($w,$h);$white=imagecolorallocate($out,255,255,255);$ink=imagecolorallocate($out,20,38,50);$teal=imagecolorallocate($out,0,115,108);imagefill($out,0,0,$white);
        try{
            $banner=imagecolorallocate($out,18,62,60);$titleColor=imagecolorallocate($out,241,250,247);$bannerHeight=100;
            imagefilledrectangle($out,0,0,$w-1,$bannerHeight-1,$banner);
            $title=mb_strtoupper('Kolase Kelas '.$class);$titleScale=4;
            while($titleScale>1&&self::bounds($title,$titleScale,true)['width']>$availableW)$titleScale--;
            $title=self::fitText($title,$titleScale,$availableW,true);$titleBox=self::bounds($title,$titleScale,true);
            self::text($out,$title,(int)(($w-$titleBox['width'])/2),(int)(($bannerHeight-$titleBox['height'])/2),$titleScale,$titleColor,$availableW,true);
            foreach($photos as $i=>$photo){
                $x=$margin+($i%$best['cols'])*($best['cellW']+$gap);$y=$top+(int)floor($i/$best['cols'])*($best['cellH']+$gap);
                $source=@imagecreatefromjpeg(self::path($photo['berkas']));if(!$source)throw new RuntimeException('Salah satu foto tidak tersedia. Muat ulang dan coba kembali.');
                try{self::contain($out,$source,$x,$y,$best['cellW'],$best['photoH']);}finally{imagedestroy($source);}
                $scale=$best['scale'];$line=15*$scale;$captionY=$y+$best['photoH']+6;
                $limit=max(1,(int)floor($best['cellW']/(9*$scale)));$names=explode("\n",wordwrap($photo['nama_lengkap'],$limit,"\n",true));
                foreach(array_slice($names,0,2) as $n=>$name)self::text($out,$name,$x,$captionY+$n*$line,$scale,$ink,$best['cellW']);
                self::text($out,'NIS '.$photo['nis'],$x,$captionY+2*$line,$scale,$teal,$best['cellW']);
                self::text($out,preg_replace('/\s+/u',' ',trim($photo['keterangan'])),$x,$captionY+3*$line,$scale,$ink,$best['cellW']);
            }
            $created=$created->setTimezone(new \DateTimeZone('Asia/Jakarta'));$signature='created by aiwalas';$time=$created->format('d-m-Y H:i:s');$scale=3;$line=15*$scale;
            self::text($out,$signature,$w-$margin-self::bounds($signature,$scale)['width'],$h-$margin-2*$line-10,$scale,$ink,$availableW);
            self::text($out,$time,$w-$margin-self::bounds($time,$scale)['width'],$h-$margin-$line,$scale,$ink,$availableW);
            if(!imagejpeg($out,$destination,85))throw new RuntimeException('Kolase belum tersimpan.');
        }finally{imagedestroy($out);}
    }
}
