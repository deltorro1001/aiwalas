<?php
namespace App\Libraries;

/** Shared source for the wali's discipline page and the student's own home card. */
class DisciplineRecords
{
    public const CATEGORIES=['etika'=>'Pelanggaran Etika','terlambat'=>'Keterlambatan','atribut'=>'Pakaian & Atribut'];
    public const FIELDS=['etika'=>['pelanggaran','tanggal','saksi','alasan','tindakan'],'terlambat'=>['jumlah_terlambat','tanggal','alasan'],'atribut'=>['pelanggaran','perbaikan']];
    public const LABELS=['pelanggaran'=>'Pelanggaran','tanggal'=>'Tanggal','saksi'=>'Disaksikan oleh','alasan'=>'Alasan','tindakan'=>'Tindakan','perbaikan'=>'Perbaikan','jumlah_terlambat'=>'Jumlah keterlambatan'];

    public static function status(int $count): string
    {
        return $count>=3?'Pemanggilan Orangtua (Terlambat 3x)':($count===2?'Dalam Peringatan (Terlambat 2x)':($count===1?'Terlambat 1x':'Baik'));
    }
    public function forClass(int $classId,?int $studentId=null): array
    {
        $db=db_connect();
        $query=$db->table('siswa')->select('id,nis,nama_lengkap')->where(['kelas_id'=>$classId,'aktif'=>1]);
        if($studentId!==null)$query->where('id',$studentId);
        $students=$query->orderBy('nama_lengkap')->get()->getResultArray();
        if(!$students)return [];
        $ids=array_column($students,'id');$records=[];$late=[];
        foreach($db->table('catatan_kedisiplinan')->where('kelas_id',$classId)->whereIn('siswa_id',$ids)->get()->getResultArray() as $row)$records[$row['siswa_id']][$row['kategori']]=$row;
        foreach($db->table('absensi')->select('siswa_id,tanggal')->where(['kelas_id'=>$classId,'status'=>'Terlambat'])->whereNotIn('tanggal',\App\Libraries\SchoolCalendar::attendanceOverrideDates())->where('tanggal <=',date('Y-m-d'))->whereIn('siswa_id',$ids)->orderBy('tanggal')->get()->getResultArray() as $row){
            $id=$row['siswa_id'];
            $late[$id]['jumlah']=($late[$id]['jumlah']??0)+1;
            $late[$id]['tanggal']=$row['tanggal'];
            $late[$id]['tanggal_kejadian'][]=$row['tanggal'];
        }
        foreach($students as &$student){
            $saved=$records[$student['id']]??[];$auto=$late[$student['id']]??[];
            $count=(int)($saved['terlambat']['jumlah_terlambat']??$auto['jumlah']??0);
            if(isset($saved['terlambat']['jumlah_dasar_absensi'])){
                $excluded=json_decode($saved['terlambat']['tanggal_dikecualikan']??'[]',true)?:[];
                $included=count(array_diff($auto['tanggal_kejadian']??[],$excluded));
                $count=max(0,$count+$included-(int)$saved['terlambat']['jumlah_dasar_absensi']);
            }
            $student['tanggal_pelanggaran']=[];
            foreach(self::CATEGORIES as $kind=>$title){
                $dates=json_decode($saved[$kind]['tanggal_kejadian']??'[]',true);
                if(!is_array($dates))$dates=[];
                $corrected=$kind==='terlambat'&&isset($saved[$kind]['tanggal_dikecualikan']);
                if(!$corrected&&!empty($saved[$kind]['tanggal']))$dates[]=$saved[$kind]['tanggal'];
                if($kind==='terlambat'){
                    $excluded=json_decode($saved[$kind]['tanggal_dikecualikan']??'[]',true)?:[];
                    $dates=array_merge($dates,array_diff($auto['tanggal_kejadian']??[],$excluded));
                }
                $dates=array_values(array_unique($dates));sort($dates);
                $student['tanggal_pelanggaran'][$kind]=$dates;
            }
            if(isset($saved['terlambat']['tanggal_dikecualikan'])){
                $dates=$student['tanggal_pelanggaran']['terlambat'];
                $saved['terlambat']['tanggal']=$dates?end($dates):null;
            }
            $reasons=json_decode($saved['terlambat']['alasan_per_tanggal']??'null',true);
            $student['alasan_keterlambatan']=[];
            foreach($student['tanggal_pelanggaran']['terlambat'] as $date)$student['alasan_keterlambatan'][$date]=is_array($reasons)?($reasons[$date]??''):($saved['terlambat']['alasan']??'');
            if(is_array($reasons)&&isset($saved['terlambat'])){
                $last=$student['tanggal_pelanggaran']['terlambat'];$date=$last?end($last):null;
                $saved['terlambat']['alasan']=$date?($reasons[$date]??null):null;
            }
            $student['records']=$saved;
            $lastDate=isset($saved['terlambat']['tanggal_dikecualikan'])?$saved['terlambat']['tanggal']:($saved['terlambat']['tanggal']??$auto['tanggal']??null);
            $student['keterlambatan']=['jumlah'=>$count,'tanggal'=>$lastDate,'status'=>self::status($count)];
            $notes=[];
            foreach(self::CATEGORIES as $kind=>$title){
                $record=$saved[$kind]??[];
                if($kind==='terlambat'&&$count>0)$record=array_replace($record,['jumlah_terlambat'=>$count,'tanggal'=>$student['keterlambatan']['tanggal']]);
                $details=[];
                foreach(self::FIELDS[$kind] as $field){$value=$record[$field]??null;if($value===null||trim((string)$value)===''||($field==='jumlah_terlambat'&&(int)$value===0))continue;$details[]=['label'=>self::LABELS[$field],'nilai'=>(string)$value];}
                if($details)$notes[]=['kategori'=>$kind,'judul'=>$title,'rincian'=>$details,'status'=>$kind==='terlambat'?self::status($count):null];
            }
            $student['catatan']=$notes;
        }
        unset($student);return $students;
    }
}
