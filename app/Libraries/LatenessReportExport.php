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

    /** Render the same grouped, alternating table layout without a GD dependency. */
    public static function pdf(array $rows,string $class,string $created): string
    {
        $pageW=841.89;$pageH=595.28;$left=18;$tableTop=80;$headerH=38;$bottom=26;
        $available=$pageW-2*$left;$widths=array_map(static fn($part)=>$available*$part,[.20,.07,.105,.135,.19,.245,.055]);
        $wrap=static function(string $value,float $width,float $size):array{
            $limit=max(4,(int)floor(($width-8)/($size*.52)));$words=preg_split('/\s+/u',trim($value))?:[''];$lines=[];$line='';
            foreach($words as $word){foreach(mb_str_split($word,$limit) as $part){$candidate=trim($line.' '.$part);if($line!==''&&mb_strlen($candidate)>$limit){$lines[]=$line;$line=$part;}else $line=$candidate;}}
            if($line!==''||!$lines)$lines[]=$line;return $lines;
        };
        $rect=static function(float $x,float $top,float $w,float $h,array $color)use($pageH):string{return sprintf("%.3F %.3F %.3F rg %.3F %.3F %.3F %.3F re f\n",$color[0],$color[1],$color[2],$x,$pageH-$top-$h,$w,$h);};
        $stroke=static function(float $x1,float $top1,float $x2,float $top2,array $color,float $width=.45)use($pageH):string{return sprintf("%.3F %.3F %.3F RG %.2F w %.3F %.3F m %.3F %.3F l S\n",$color[0],$color[1],$color[2],$width,$x1,$pageH-$top1,$x2,$pageH-$top2);};
        $text=static function(string $value,float $x,float $baseline,float $size,bool $bold=false,array $color=[.337,.396,.475])use($pageH):string{
            $encoded=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$value);$encoded=$encoded===false?'':$encoded;$encoded=str_replace(["\\","(",")","\r","\n"],["\\\\","\\(","\\)",' ',' '],$encoded);
            return sprintf("%.3F %.3F %.3F rg BT /%s %.2F Tf %.3F %.3F Td (%s) Tj ET\n",$color[0],$color[1],$color[2],$bold?'F2':'F1',$size,$x,$pageH-$baseline,$encoded);
        };
        $pages=[];$content='';$y=$tableTop;
        $startPage=function(bool $continuation=false)use(&$pages,&$content,&$y,$rect,$stroke,$text,$left,$pageW,$tableTop,$headerH,$widths,$class,$created){
            if($content!=='')$pages[]=$content;$content=$rect(0,0,$pageW,5,[.067,.663,.608]);$title='Status Kedisiplinan - Keterlambatan'.($continuation?' (lanjutan)':'');
            $content.=$text($title,$left,23,14,true,[.071,.125,.2]);$content.=$text('Kelas '.$class.' · Dibuat '.$created.' WIB',$left,40,8.5,false,[.337,.396,.475]);$y=$tableTop;
            $headers=['NAMA SISWA','NIS',"JUMLAH\nKETERLAMBATAN",'TANGGAL DILAKUKAN','ALASAN','STATUS KEDISIPLINAN','AKSI'];$x=$left;
            foreach($headers as $i=>$label){$w=$widths[$i];$content.=$rect($x,$y,$w,$headerH,[.969,.976,.984]);$content.=$stroke($x,$y,$x+$w,$y,[.875,.906,.929]);$content.=$stroke($x,$y+$headerH,$x+$w,$y+$headerH,[.875,.906,.929]);$content.=$stroke($x,$y,$x,$y+$headerH,[.875,.906,.929]);$content.=$stroke($x+$w,$y,$x+$w,$y+$headerH,[.875,.906,.929]);foreach(explode("\n",$label) as $j=>$part)$content.=$text($part,$x+5,$y+14+$j*12,7,true,[.529,.576,.639]);$x+=$w;}$y+=$headerH;
        };
        $draw=function(array $row,array $entries,int $index,bool $continuation=false)use(&$content,&$y,$left,$pageW,$widths,$wrap,$rect,$stroke,$text){
            $heights=[];foreach($entries as $entry){$dateLines=$wrap((string)$entry['tanggal'],$widths[3]-10,8);$reasonLines=$wrap((string)$entry['alasan'],$widths[4]-10,8);$heights[]=max(22,max(count($dateLines),count($reasonLines))*10+8);}$height=max(26,array_sum($heights));$fill=$index%2?[.941,.969,.965]:[1,1,1];$x=$left;
            foreach($widths as $w){$content.=$rect($x,$y,$w,$height,$fill);$content.=$stroke($x,$y,$x+$w,$y,[.875,.906,.929]);$content.=$stroke($x+$w,$y,$x+$w,$y+$height,[.875,.906,.929]);$x+=$w;}
            $identity=[$continuation?$row['nama'].' (lanjutan)':$row['nama'],$row['nis'],(string)$row['jumlah']];$x=$left;
            foreach($identity as $i=>$value){$lines=$wrap((string)$value,$widths[$i]-10,8);$base=$y+($height-count($lines)*10)/2+8;foreach($lines as $j=>$part)$content.=$text($part,$x+5,$base+$j*10,8);$x+=$widths[$i];}
            $dateX=$left+$widths[0]+$widths[1]+$widths[2];$cursor=$y;
            foreach($entries as $i=>$entry){$rowHeight=$heights[$i];if($i>0)$content.=$stroke($dateX,$cursor,$dateX+$widths[3]+$widths[4],$cursor,[.875,.906,.929]);$dates=$wrap((string)$entry['tanggal'],$widths[3]-10,8);$reasons=$wrap((string)$entry['alasan'],$widths[4]-10,8);$base=$cursor+($rowHeight-max(count($dates),count($reasons))*10)/2+8;foreach($dates as $j=>$part)$content.=$text($part,$dateX+5,$base+$j*10,8);$reasonX=$dateX+$widths[3];foreach($reasons as $j=>$part)$content.=$text($part,$reasonX+5,$base+$j*10,8);$cursor+=$rowHeight;}
            $statusX=$left+array_sum(array_slice($widths,0,5));$statusLines=$wrap((string)$row['status'],$widths[5]-10,8);$base=$y+($height-count($statusLines)*10)/2+8;foreach($statusLines as $i=>$part)$content.=$text($part,$statusX+5,$base+$i*10,8);$content.=$text('Edit',$statusX+$widths[5]+8,$y+$height/2+3,8,true,[0,.545,.502]);$y+=$height;$content.=$stroke($left,$y,$pageW-$left,$y,[.718,.851,.839],1.1);
        };
        $startPage();$student=0;$maxBlock=$pageH-$bottom-$tableTop-$headerH;
        foreach($rows as $row){$details=$row['tanggal']?:[['tanggal'=>'-','alasan'=>'-']];$chunks=[];$chunk=[];$used=0;
            foreach($details as $entry){$h=max(22,max(count($wrap((string)$entry['tanggal'],$widths[3]-10,8)),count($wrap((string)$entry['alasan'],$widths[4]-10,8)))*10+8);if($chunk&&$used+$h>$maxBlock){$chunks[]=$chunk;$chunk=[];$used=0;}$chunk[]=$entry;$used+=$h;}$chunks[]=$chunk;
            foreach($chunks as $index=>$entries){$height=array_sum(array_map(static fn($entry)=>max(22,max(count($wrap((string)$entry['tanggal'],$widths[3]-10,8)),count($wrap((string)$entry['alasan'],$widths[4]-10,8)))*10+8),$entries));if($y+$height>$pageH-$bottom)$startPage(true);$draw($row,$entries,$student,$index>0);}$student++;
        }
        if(!$rows){$content.=$rect($left,$y,$pageW-2*$left,28,[1,1,1]);$content.=$stroke($left,$y,$pageW-$left,$y,[.875,.906,.929]);$content.=$text('Belum ada siswa aktif.',$left+8,$y+18,8.5);}
        if($content!=='')$pages[]=$content;$objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>''];$kids=[];
        foreach($pages as $i=>$stream){$pageId=5+$i*2;$streamId=$pageId+1;$kids[]=$pageId.' 0 R';$objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$pageW.' '.$pageH.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$streamId.' 0 R >>';$objects[$streamId]='<< /Length '.strlen($stream).">>\nstream\n".$stream.'endstream';}
        $objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';$objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';$objects[2]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);
        $pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $id=>$body){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$body."\nendobj\n";}$max=max(array_keys($objects));$xref=strlen($pdf);$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($id=1;$id<=$max;$id++)$pdf.=isset($offsets[$id])?sprintf('%010d 00000 n ',$offsets[$id])."\n":"0000000000 00000 f \n";return $pdf.'trailer << /Size '.($max+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
