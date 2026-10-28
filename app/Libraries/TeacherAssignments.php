<?php
namespace App\Libraries;

/** Teaching assignments include scheduled lessons and subjects awaiting a timetable. */
final class TeacherAssignments
{
    public static function rows(?int $class=null,?int $teacher=null): array
    {
        $db=db_connect();$rows=[];
        foreach(['jadwal_kelas','pengampu_mapel'] as $table){
            $q=$db->table($table.' a')->select('a.kelas_id,a.mata_pelajaran_id,a.guru_id,m.nama_mata_pelajaran,g.nama_lengkap AS nama_guru,g.nomor_hp')->join('mata_pelajaran m','m.id=a.mata_pelajaran_id')->join('guru g','g.id=a.guru_id')->where(['m.aktif'=>1,'g.aktif'=>1]);
            if($class!==null)$q->where('a.kelas_id',$class);
            if($teacher!==null)$q->where('a.guru_id',$teacher);
            foreach($q->get()->getResultArray() as $row)$rows[$row['kelas_id'].':'.$row['mata_pelajaran_id'].':'.$row['guru_id']]=$row;
        }
        $rows=array_values($rows);usort($rows,static fn($a,$b)=>strcasecmp($a['nama_mata_pelajaran'],$b['nama_mata_pelajaran']));return $rows;
    }
    public static function has(int $class,int $teacher,?int $subject=null): bool
    {
        foreach(self::rows($class,$teacher) as $row)if($subject===null||(int)$row['mata_pelajaran_id']===$subject)return true;
        return false;
    }
}
