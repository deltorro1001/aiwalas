<?php
namespace App\Libraries;

/** Applied once inside the submission transaction, after locking the child row. */
final class ParentRequestAttendance
{
    public static function apply(array $request,string $status,int $requestId): array
    {
        if(!in_array($status,['Sakit','Izin'],true))throw new \InvalidArgumentException('Status pengajuan tidak valid.');
        $db=db_connect();$zone=new \DateTimeZone('Asia/Jakarta');
        $start=new \DateTimeImmutable($request['tanggal_mulai'],$zone);
        $end=new \DateTimeImmutable($request['tanggal_selesai'],$zone);
        $result=['diisi'=>0,'dilewati'=>0];
        for($day=$start;$day<=$end;$day=$day->modify('+1 day')){
            $date=$day->format('Y-m-d');
            if(SchoolCalendar::isHoliday($date)||SchoolCalendar::isPjj($date)){$result['dilewati']++;continue;}
            $semester=(int)$day->format('n')<=6?'Genap':'Ganjil';
            $keys=['siswa_id'=>(int)$request['siswa_id'],'kelas_id'=>(int)$request['kelas_id'],'tanggal'=>$date,'semester'=>$semester];
            $existing=$db->table('absensi')->where($keys)->get()->getRowArray();
            if($existing&&in_array($existing['status'],['Libur','PJJ'],true)){$result['dilewati']++;continue;}
            $values=['status'=>$status,'waktu_scan'=>null,'dicatat_oleh'=>(int)$request['pengguna_id'],
                'keterangan'=>'Pengajuan '.strtolower($status).' orangtua #'.$requestId.': '.trim($request['keterangan']??'')];
            if($existing)$db->table('absensi')->where('id',$existing['id'])->update($values);
            else $db->table('absensi')->insert($keys+$values);
            $result['diisi']++;
        }
        return $result;
    }
}
