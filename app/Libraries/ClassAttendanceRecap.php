<?php
namespace App\Libraries;

final class ClassAttendanceRecap
{
    public static function build(array $klass,int $year,string $semester,$db=null): array
    {
        $db=$db??db_connect();
        $empty=static fn()=>['sakit'=>0,'izin'=>0,'alpha'=>0,'terlambat'=>0];
        $months=[];for($month=1;$month<=12;$month++)$months[$month]=['bulan'=>$month]+$empty();
        $students=[];
        foreach($db->table('siswa')->select('id,nis,nama_lengkap')->where(['kelas_id'=>(int)$klass['id'],'aktif'=>1])->orderBy('nama_lengkap')->get()->getResultArray() as $row){
            $row['bulanan']=$months;$row['total']=$empty();$students[$row['id']]=$row;
        }
        if($students){
            $rows=$db->table('absensi')->select('siswa_id,MONTH(tanggal) AS bulan,status,COUNT(*) AS jumlah',false)
                ->where(['kelas_id'=>(int)$klass['id'],'semester'=>$semester])->where('tanggal >=',$year.'-01-01')->where('tanggal <',((int)$year+1).'-01-01')
                ->where(\App\Libraries\SchoolCalendar::attendanceFilterSql('tanggal'),null,false)
                ->whereIn('siswa_id',array_keys($students))->whereIn('status',['Sakit','Izin','Alpha','Terlambat'])->groupBy('siswa_id,MONTH(tanggal),status')->get()->getResultArray();
            foreach($rows as $row){
                $key=['Sakit'=>'sakit','Izin'=>'izin','Alpha'=>'alpha','Terlambat'=>'terlambat'][$row['status']];
                $n=(int)$row['jumlah'];$m=(int)$row['bulan'];
                $students[$row['siswa_id']]['bulanan'][$m][$key]=$n;
                $students[$row['siswa_id']]['total'][$key]+=$n;$months[$m][$key]+=$n;
            }
        }
        $total=$empty();foreach($months as $row)foreach(array_keys($total) as $key)$total[$key]+=$row[$key];
        foreach($students as &$row)$row['bulanan']=array_values($row['bulanan']);unset($row);
        return ['tahun'=>$year,'semester'=>$semester,'kelas'=>$klass,'bulanan'=>array_values($months),'total'=>$total,'siswa'=>array_values($students)];
    }
}
