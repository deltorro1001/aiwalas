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
        foreach($db->table('absensi')->select('siswa_id,COUNT(*) jumlah,MAX(tanggal) tanggal')->where(['kelas_id'=>$classId,'status'=>'Terlambat'])->where('tanggal <=',date('Y-m-d'))->whereIn('siswa_id',$ids)->groupBy('siswa_id')->get()->getResultArray() as $row)$late[$row['siswa_id']]=$row;
        foreach($students as &$student){
            $saved=$records[$student['id']]??[];$auto=$late[$student['id']]??[];
            $count=(int)($saved['terlambat']['jumlah_terlambat']??$auto['jumlah']??0);
            $student['records']=$saved;
            $student['keterlambatan']=['jumlah'=>$count,'tanggal'=>$saved['terlambat']['tanggal']??$auto['tanggal']??null,'status'=>self::status($count)];
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
