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
        $pages=[];$lines=['STATUS KEDISIPLINAN - KETERLAMBATAN','Kelas: '.$class,'Dibuat: '.$created.' WIB',''];
        foreach($rows as $i=>$row){
            $entry=[($i+1).'. '.$row['nama'].' | NIS '.$row['nis'],'   Jumlah: '.$row['jumlah'].' | Status: '.$row['status']];
            if(!$row['tanggal'])$entry[]='   Tanggal: -';
            foreach($row['tanggal'] as $date)$entry[]='   '.$date['tanggal'].' — '.$date['alasan'];
            $wrapped=[];foreach($entry as $line){$parts=explode("\n",wordwrap($line,78,"\n",true));foreach($parts as $part)$wrapped[]=$part;}
            foreach([...$wrapped,''] as $line){if(count($lines)>=48){$pages[]=$lines;$lines=['STATUS KEDISIPLINAN - KETERLAMBATAN (lanjutan)','',''];} $lines[]=$line;}
        }
        if(!$rows)$lines[]='Belum ada siswa aktif.';$pages[]=$lines;
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>'];$kids=[];
        foreach($pages as $index=>$page){$pageId=4+$index*2;$streamId=$pageId+1;$kids[]=$pageId.' 0 R';$content="BT\n/F1 9 Tf\n42 800 Td\n12 TL\n";foreach($page as $line){$line=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$line);$line=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line?:'');$content.='('.$line.") Tj\nT*\n";}$content.="ET\n";$objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$streamId.' 0 R >>';$objects[$streamId]='<< /Length '.strlen($content).">>\nstream\n".$content.'endstream';}
        $objects[2]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $id=>$body){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$body."\nendobj\n";}$max=max(array_keys($objects));$xref=strlen($pdf);$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($id=1;$id<=$max;$id++)$pdf.=isset($offsets[$id])?sprintf('%010d 00000 n ', $offsets[$id])."\n":"0000000000 00000 f \n";return $pdf.'trailer << /Size '.($max+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
