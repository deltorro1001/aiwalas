<?php
namespace App\Libraries;

/** Excel-compatible and PDF exports for the lateness view in DisciplineRecords. */
final class LatenessReportExport
{
    public static function rows(int $class): array
    {
        $rows=(new DisciplineRecords())->forClass($class);
        return array_values(array_map(static function(array $student): array {
            $dates=$student['tanggal_pelanggaran']['terlambat']??[];$reasons=$student['alasan_keterlambatan']??[];
            return ['nama'=>(string)$student['nama_lengkap'],'nis'=>(string)$student['nis'],'jumlah'=>(int)($student['keterlambatan']['jumlah']??0),'tanggal'=>array_map(static fn($date)=>['tanggal'=>date('d-m-Y',strtotime($date)),'alasan'=>trim((string)($reasons[$date]??''))?:'Belum diisi'],$dates),'status'=>(string)($student['status_kedisiplinan']['terlambat']??'Baik')];
        },$rows));
    }

    public static function excel(array $rows,string $class,string $created): string
    {
        $e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $html="\xEF\xBB\xBF".'<html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif}table{border-collapse:collapse}th,td{border:1px solid #596579;padding:7px 9px;vertical-align:top;mso-height-source:auto}th{background:#d9efec;color:#123b3d;font-weight:bold}tr:nth-child(even) td{background:#f2f6f8}.dates{white-space:pre-line}</style></head><body><h2>Rekap Status Kedisiplinan Keterlambatan - '.$e($class).'</h2><p>Dibuat '.$e($created).' WIB</p><table><thead><tr><th>No.</th><th>Nama Siswa</th><th>NIS</th><th>Jumlah Keterlambatan</th><th>Tanggal Dilakukan</th><th>Alasan</th><th>Status Kedisiplinan</th></tr></thead><tbody>';
        foreach($rows as $i=>$row){$dates=array_column($row['tanggal'],'tanggal');$reasons=array_column($row['tanggal'],'alasan');$html.='<tr><td>'.($i+1).'</td><td>'.$e($row['nama']).'</td><td>'.$e($row['nis']).'</td><td>'.$row['jumlah'].'</td><td class="dates">'.$e(implode("\n",$dates)?:'-').'</td><td class="dates">'.$e(implode("\n",$reasons)?:'-').'</td><td>'.$e($row['status']).'</td></tr>';}
        return $html.'</tbody></table></body></html>';
    }

    public static function pdf(array $rows,string $class,string $created): string
    {
        if(!function_exists('imagettftext'))throw new \RuntimeException('GD/FreeType belum tersedia untuk membuat PDF.');
        $font=ROOTPATH.'resources/fonts/segoe-ui/segoeui.ttf';$bold=ROOTPATH.'resources/fonts/segoe-ui/segoeuib.ttf';if(!is_readable($font)||!is_readable($bold))throw new \RuntimeException('Font PDF belum tersedia.');
        $pages=[];$image=null;$y=0;$number=0;$widths=[360,115,165,220,360,365,110];$pageWidth=1754;$pageHeight=1240;
        $newPage=function()use(&$image,&$y,$pageWidth,$pageHeight,$font,$bold,$class,$created){
            $image=imagecreatetruecolor($pageWidth,$pageHeight);imagefill($image,0,0,0xffffff);imagefilledrectangle($image,0,0,$pageWidth,8,0x11a99b);
            imagettftext($image,34,0,32,58,0x122033,$bold,'Status Kedisiplinan - Keterlambatan');imagettftext($image,20,0,32,92,0x566579,$font,'Kelas '.$class.' · Dibuat '.$created.' WIB');$y=125;
            $headers=['NAMA SISWA','NIS',"JUMLAH\nKETERLAMBATAN",'TANGGAL DILAKUKAN','ALASAN','STATUS KEDISIPLINAN','AKSI'];$x=30;$height=58;
            foreach($headers as $i=>$label){$w=$widths[$i];imagefilledrectangle($image,$x,$y,$x+$w,$y+$height,0xf7f9fb);imagerectangle($image,$x,$y,$x+$w,$y+$height,0xdfe7ed);$lines=explode("\n",$label);foreach($lines as $line)imagettftext($image,15,0,$x+10,$y+23+22*array_search($line,$lines),0x8793a3,$bold,$line);$x+=$w;}$y+=$height;
        };
        $wrap=function(string $text,int $maxWidth,int $size)use($font):array{$words=preg_split('/\s+/u',trim($text))?:[''];$lines=[];$line='';foreach($words as $word){$candidate=trim($line.' '.$word);$box=imagettfbbox($size,0,$font,$candidate);if($line!==''&&$box[2]-$box[0]>$maxWidth){$lines[]=$line;$line=$word;}else $line=$candidate;}if($line!==''||!$lines)$lines[]=$line;return $lines;};
        $finish=function()use(&$image,&$pages,$pageWidth,$pageHeight){ob_start();imagejpeg($image,null,92);$pages[]=ob_get_clean();imagedestroy($image);};
        $newPage();
        foreach($rows as $row){
            $dateRows=$row['tanggal']?:[['tanggal'=>'-','alasan'=>'-']];$height=max(46,count($dateRows)*44);if($y+$height>$pageHeight-30){$finish();$newPage();}
            $bg=($number%2===1)?0xf1f7f6:0xffffff;$x=30;
            foreach($widths as $i=>$w){imagefilledrectangle($image,$x,$y,$x+$w,$y+$height,$bg);imagerectangle($image,$x,$y,$x+$w,$y+$height,0xe0e8ed);$x+=$w;}
            $texts=[$row['nama'],$row['nis'],(string)$row['jumlah']];$x=30;
            foreach($texts as $i=>$text){$w=$widths[$i];$lines=$wrap((string)$text,$w-20,16);$block=count($lines)*22;$base=$y+(int)(($height-$block)/2)+19;foreach($lines as $line){imagettftext($image,16,0,$x+10,$base,0x566579,$font,$line);$base+=22;}$x+=$w;}
            $x=30+$widths[0]+$widths[1]+$widths[2];
            foreach($dateRows as $j=>$entry){$lineY=$y+$j*44;if($j>0){imageline($image,$x,$lineY,$x+$widths[3]+$widths[4],$lineY,0xe0e8ed);} $dateLines=$wrap((string)$entry['tanggal'],$widths[3]-18,16);$reasonLines=$wrap((string)$entry['alasan'],$widths[4]-18,16);foreach($dateLines as $k=>$line)imagettftext($image,16,0,$x+10,$lineY+27+20*$k,0x566579,$font,$line);$rx=$x+$widths[3];foreach($reasonLines as $k=>$line)imagettftext($image,16,0,$rx+10,$lineY+27+20*$k,0x566579,$font,$line);$x=$rx+$widths[4];}
            $statusX=30+array_sum(array_slice($widths,0,5));$statusLines=$wrap((string)$row['status'],$widths[5]-20,16);$base=$y+(int)(($height-count($statusLines)*22)/2)+19;foreach($statusLines as $line){imagettftext($image,16,0,$statusX+10,$base,0x566579,$font,$line);$base+=22;}
            $actionX=$statusX+$widths[5];imagettftext($image,16,0,$actionX+16,$y+(int)($height/2)+7,0x008b80,$bold,'Edit');$y+=$height;imageline($image,30,$y,$pageWidth-30,$y,0xb7d9d6);$number++;
        }
        if(!$rows){$x=30;$w=array_sum($widths);imagefilledrectangle($image,$x,$y,$x+$w,$y+52,0xffffff);imagerectangle($image,$x,$y,$x+$w,$y+52,0xe0e8ed);imagettftext($image,16,0,$x+18,$y+32,0x566579,$font,'Belum ada siswa aktif.');$y+=52;}
        $finish();
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>''];$kids=[];
        foreach($pages as $i=>$jpeg){$id=3+$i*3;$kids[]=$id.' 0 R';$objects[$id]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 841.89 595.28] /Resources << /XObject << /Image '.($id+1).' 0 R >> >> /Contents '.($id+2).' 0 R >>';$objects[$id+1]='<< /Type /XObject /Subtype /Image /Width '.$pageWidth.' /Height '.$pageHeight.' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpeg)." >>\nstream\n".$jpeg."\nendstream";$content="q 841.89 0 0 595.28 0 0 cm /Image Do Q\n";$objects[$id+2]='<< /Length '.strlen($content).">>\nstream\n".$content.'endstream';}
        $objects[2]='<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ',$kids).'] >>';
        $objects[2]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $id=>$body){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$body."\nendobj\n";}$max=max(array_keys($objects));$xref=strlen($pdf);$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($id=1;$id<=$max;$id++)$pdf.=isset($offsets[$id])?sprintf('%010d 00000 n ', $offsets[$id])."\n":"0000000000 00000 f \n";return $pdf.'trailer << /Size '.($max+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
