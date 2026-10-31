<?php
namespace App\Libraries;

/** Public attendance annotations never expose filenames, reasons, or download URLs. */
final class AttendanceAttachmentDetails
{
    private array $requests=[];
    public function __construct(int $class,array $students,string $start,string $end)
    {
        if(!$students)return;
        $db=db_connect();
        foreach(['Sakit'=>'pengajuan_sakit','Izin'=>'pengajuan_izin'] as $status=>$table){
            $folder=$status==='Sakit'?'pengajuan-sakit':'pengajuan-izin';
            $rows=$db->table($table)->select('siswa_id,tanggal_mulai,tanggal_selesai,berkas_lampiran')
                ->where('kelas_id',$class)->whereIn('siswa_id',array_unique($students))
                ->where('tanggal_mulai <=',$end)->where('tanggal_selesai >=',$start)
                ->where('berkas_lampiran IS NOT NULL',null,false)->get()->getResultArray();
            foreach($rows as $row){
                if(!preg_match('/^[a-f0-9]{48}\.(pdf|jpg|png)$/D',$row['berkas_lampiran'])||!is_file(WRITEPATH.$folder.'/'.$row['berkas_lampiran']))continue;
                $this->requests[$status][(int)$row['siswa_id']][]=$row;
            }
        }
    }
    public function forAttendance(int $student,string $date,string $status): array
    {
        $note='';
        foreach($this->requests[$status][$student]??[] as $request){
            if($date>=$request['tanggal_mulai']&&$date<=$request['tanggal_selesai']){
                $note=$status==='Sakit'?'(surat sakit terlampir)':'(surat izin terlampir)';break;
            }
        }
        return ['surat_terlampir'=>$note!=='','keterangan_lampiran'=>$note];
    }
}
