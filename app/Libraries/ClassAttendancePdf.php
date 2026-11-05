<?php
namespace App\Libraries;

/** Printable monthly roster, rendered with the same installed Segoe UI fonts as collages. */
final class ClassAttendancePdf
{
    public const MONTHS=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    public static function roster(array $data,int $month): array
    {
        return array_map(static function($student)use($month){
            $counts=$student['bulanan'][$month-1];
            return ['nis'=>(string)$student['nis'],'nama'=>$student['nama_lengkap'],'sakit'=>(int)$counts['sakit'],'izin'=>(int)$counts['izin'],'alpha'=>(int)$counts['alpha'],'terlambat'=>(int)$counts['terlambat']];
        },$data['siswa']);
    }
    public static function create(array $data,int $month,\DateTimeImmutable $now): string
    {
        if(!function_exists('imagettftext'))throw new \RuntimeException('GD/FreeType belum tersedia untuk membuat PDF.');
        $font=ROOTPATH.'resources/fonts/segoe-ui/segoeui.ttf';$bold=ROOTPATH.'resources/fonts/segoe-ui/segoeuib.ttf';
        if(!is_readable($font)||!is_readable($bold))throw new \RuntimeException('Font PDF belum tersedia.');
        $rows=self::roster($data,$month);$pages=[];$image=null;$y=0;$number=0;$sum=array_fill(0,8,0);
        $widths=[45,100,410,100,100,100,140,145];
        $newPage=function()use(&$image,&$y,$data,$month,$font,$bold){
            $image=imagecreatetruecolor(1240,1754);imagefill($image,0,0,0xffffff);
            imagefilledrectangle($image,0,0,1240,145,0x173c40);
            imagettftext($image,30,0,50,65,0xffffff,$bold,'Rekap Absen Kelas '.$data['kelas']['nama_kelas']);
            imagettftext($image,20,0,50,110,0xe5f7f4,$font,self::MONTHS[$month-1].' '.$data['tahun'].' · Semester '.$data['semester']);
            imagettftext($image,16,0,50,177,0x566579,$font,'Sumber: Absensi Harian · Siswa aktif · Jumlah catatan pada bulan yang dipilih');
            $y=205;
        };
        $draw=function(array $cells,int $height,bool $strong=false)use(&$image,&$y,$widths,$font,$bold){
            $x=50;
            foreach($cells as $i=>$lines){
                imagefilledrectangle($image,$x,$y,$x+$widths[$i],$y+$height,$strong?0xeaf3f3:0xffffff);
                imagerectangle($image,$x,$y,$x+$widths[$i],$y+$height,0xd5e0e5);
                $color=$i===3?0x15803d:($i===4?0x6b3f1d:($i===5?0xdc2626:0x122033));
                foreach((array)$lines as $j=>$text)imagettftext($image,16,0,$x+7,$y+23+$j*24,$color,$strong?$bold:$font,(string)$text);
                $x+=$widths[$i];
            }
            $y+=$height;
        };
        $finish=function()use(&$image,&$pages,$font,$now){
            imagettftext($image,15,0,50,1720,0x566579,$font,'Dibuat oleh aiwalas · '.$now->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('d-m-Y H:i:s').' WIB · Halaman '.(count($pages)+1));
            ob_start();imagejpeg($image,null,92);$pages[]=ob_get_clean();imagedestroy($image);
        };
        $header=static fn()=>['No.','NIS','Nama Siswa','Sakit','Izin','Alpha','Terlambat','Total S/I/A'];
        $newPage();$draw($header(),38,true);
        foreach($rows as $row){
            $name=trim(preg_replace('/\s+/u',' ',$row['nama']));$lines=[];$line='';
            foreach(preg_split('/\s+/u',$name) as $word){
                // Split unusually long single words too, so no text leaves the name cell.
                foreach(mb_str_split($word,34) as $part){
                    $candidate=trim($line.' '.$part);$box=imagettfbbox(16,0,$font,$candidate);
                    if($line!==''&&$box[2]-$box[0]>392){$lines[]=$line;$line=$part;}else $line=$candidate;
                }
            }
            $lines[]=$line;$nisLines=[];$nisLine='';
            foreach(mb_str_split($row['nis']) as $letter){
                $candidate=$nisLine.$letter;$box=imagettfbbox(16,0,$font,$candidate);
                if($nisLine!==''&&$box[2]-$box[0]>84){$nisLines[]=$nisLine;$nisLine=$letter;}else $nisLine=$candidate;
            }
            $nisLines[]=$nisLine;$height=max(36,max(count($lines),count($nisLines))*24+12);
            if($y+$height>1640){$finish();$newPage();$draw($header(),38,true);}
            $values=[++$number,$nisLines,$lines,$row['sakit'],$row['izin'],$row['alpha'],$row['terlambat'],$row['sakit']+$row['izin']+$row['alpha']];
            foreach([3,4,5,6,7] as $i)$sum[$i]+=$values[$i];
            $draw($values,$height);
        }
        if(!$rows){$draw(['','','Belum ada siswa aktif','','','','',''],38);}
        if($y+38>1640){$finish();$newPage();$draw($header(),38,true);}
        $draw(['','','TOTAL',$sum[3],$sum[4],$sum[5],$sum[6],$sum[7]],38,true);$finish();
        // Each page is a high resolution JPEG embedded directly in a standard PDF.
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>''];$kids=[];
        foreach($pages as $i=>$jpeg){
            $id=3+$i*3;$kids[]=$id.' 0 R';
            $objects[$id]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /XObject << /Image '.($id+1).' 0 R >> >> /Contents '.($id+2).' 0 R >>';
            $objects[$id+1]='<< /Type /XObject /Subtype /Image /Width 1240 /Height 1754 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpeg).' >>' ."\nstream\n".$jpeg."\nendstream";
            $content="q 595.28 0 0 841.89 0 0 cm /Image Do Q\n";
            $objects[$id+2]='<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream';
        }
        $objects[2]='<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ',$kids).'] >>';
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];
        foreach($objects as $id=>$body){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$body."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($id=1;$id<=count($objects);$id++)$pdf.=sprintf('%010d 00000 n ',$offsets[$id])."\n";
        return $pdf.'trailer << /Size '.(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
