<?php
namespace App\Libraries;

/** Deterministic attendance facts for Wali questions, never estimates missing days. */
final class AttendanceQuestionSummary
{
    public const START='2026-07-13';
    public const STATUSES=['Hadir','Sakit','Izin','Alpha','Terlambat','PJJ','Libur','Menunggu keterangan'];
    public function forClass(int $class,\DateTimeImmutable $now): array
    {
        $now=$now->setTimezone(new \DateTimeZone('Asia/Jakarta'));$end=$now->format('Y-m-d');$db=db_connect();
        $empty=array_fill_keys(self::STATUSES,0);$students=[];
        foreach($db->table('siswa')->select('id,nis,nama_lengkap')->where(['kelas_id'=>$class,'aktif'=>1])->orderBy('nama_lengkap')->get()->getResultArray() as $row){
            $students[(int)$row['id']]=['id'=>(int)$row['id'],'nis'=>$row['nis'],'nama'=>$row['nama_lengkap'],'jumlah'=>$empty,'bulanan'=>[],'riwayat'=>[]];
        }
        if($students&&$end>=self::START){
            $rows=$db->table('absensi')->select('siswa_id,tanggal,semester,status,waktu_scan,keterangan')->where('kelas_id',$class)->whereIn('siswa_id',array_keys($students))->where('tanggal >=',self::START)->where('tanggal <=',$end)->orderBy('tanggal')->orderBy('id')->get()->getResultArray();
            foreach($rows as $row){
                // A date belongs to one semester, even if a legacy duplicate exists in the other.
                $semester=(int)substr($row['tanggal'],5,2)<=6?'Genap':'Ganjil';
                if($row['semester']!==$semester)continue;
                $status=SchoolCalendar::attendanceOverride($row['tanggal'])??$row['status'];
                if(!array_key_exists($status,$empty))continue;
                $id=(int)$row['siswa_id'];$month=substr($row['tanggal'],0,7);
                $students[$id]['jumlah'][$status]++;
                if(!isset($students[$id]['bulanan'][$month]))$students[$id]['bulanan'][$month]=$empty;
                $students[$id]['bulanan'][$month][$status]++;
                $students[$id]['riwayat'][]=['tanggal'=>$row['tanggal'],'status'=>$status,'waktu_scan'=>in_array($status,['Libur','PJJ'],true)?null:$row['waktu_scan'],'keterangan'=>$row['keterangan']??''];
            }
        }
        $total=$empty;$groups=[];
        foreach(self::STATUSES as $status)$groups[$status]=['jumlah_siswa'=>0,'jumlah_catatan'=>0,'siswa_ids'=>[]];
        foreach($students as $row)foreach($row['jumlah'] as $status=>$n){$total[$status]+=$n;if($n){$groups[$status]['jumlah_siswa']++;$groups[$status]['jumlah_catatan']+=$n;$groups[$status]['siswa_ids'][]=$row['id'];}}
        foreach($students as &$row)$row['bulanan']=(object)$row['bulanan'];unset($row);
        return ['periode'=>['mulai'=>self::START,'sampai'=>$end,'diperiksa_pada'=>$now->format('Y-m-d H:i:s'),'zona'=>'Asia/Jakarta'],'total'=>$total,'kelompok'=>$groups,'siswa'=>array_values($students)];
    }
}
