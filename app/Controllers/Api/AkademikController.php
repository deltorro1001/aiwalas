<?php
namespace App\Controllers\Api;

class AkademikController extends BaseApiController
{
    private function context(array $p): ?array
    {
        $db = db_connect();
        $user = $this->currentUser();
        $class = (int) ($p['kelas_id'] ?? 0);
        $semester = $p['semester'] ?? 'Ganjil';
        if (!in_array($semester, ['Ganjil','Genap'], true) || !$db->table('kelas')->where('id',$class)->countAllResults()) return null;
        $subjects = $db->table('mata_pelajaran')->where('aktif',1)->orderBy('nama_mata_pelajaran')->get()->getResultArray();
        if ($user['peran'] === 'guru') {
            $assigned = \App\Libraries\TeacherAssignments::rows($class,(int)($user['guru_id']??0));
            $ids = array_column($assigned,'mata_pelajaran_id');
            $subjects = array_values(array_filter($subjects,static fn($row)=>in_array($row['id'],$ids)));
            if (!$subjects) return null;
        }
        if ($user['peran'] === 'siswa') {
            $own = $db->table('siswa')->where(['nis'=>$user['nis'],'kelas_id'=>$class])->get()->getRowArray();
            if (!$own) return null;
        }
        return [$class,$semester,$subjects];
    }

    public function index()
    {
        $ctx = $this->context($this->request->getGet());
        if (!$ctx) return $this->respond(['ok'=>false,'message'=>'Kelas atau semester tidak dapat diakses.'],403);
        [$class,$semester,$subjects] = $ctx;
        $db = db_connect();
        $rows = [];
        if ($subjects) {
            $q = $db->table('nilai_siswa n')->select('s.nis,n.siswa_id,n.komponen_nilai_id,n.nilai,k.mata_pelajaran_id,k.nama_komponen')->join('siswa s','s.id=n.siswa_id')->join('komponen_nilai k','k.id=n.komponen_nilai_id')->where('k.kelas_id',$class)->where('n.semester',$semester)->whereIn('k.mata_pelajaran_id',array_column($subjects,'id'));
            if ($this->currentUser()['peran']==='siswa') $q->where('s.nis',$this->currentUser()['nis']);
            $rows = $q->get()->getResultArray();
        }
        return $this->respond(['subjects'=>$subjects,'data'=>$rows]);
    }

    public function save()
    {
        $p = $this->payload();
        $ctx = $this->context($p);
        if (!$ctx) return $this->respond(['ok'=>false,'message'=>'Kelas atau semester tidak dapat diakses.'],403);
        [$class,$semester,$subjects] = $ctx;
        $subject = (int)($p['mata_pelajaran_id']??0);
        $component = \App\Libraries\GradeComponents::key($p['komponen']??null);
        if (!in_array($subject,array_column($subjects,'id')) || $component===null || !is_array($p['values']??null) || !$p['values']) return $this->respond(['ok'=>false,'message'=>'Komponen atau nilai tidak valid.'],422);
        $db = db_connect();
        $students = array_column($db->table('siswa')->where(['kelas_id'=>$class,'aktif'=>1])->get()->getResultArray(),'id','nis');
        foreach ($p['values'] as $nis=>$value) {
            if (!isset($students[$nis]) || ($value!==null && (!is_numeric($value) || !is_finite((float)$value) || $value<0 || $value>100))) return $this->respond(['ok'=>false,'message'=>'NIS atau rentang nilai tidak valid.'],422);
        }
        $db->transStart();
        $db->query('SELECT id FROM kelas WHERE id=? FOR UPDATE',[$class]);
        // Reuse legacy names such as "Tugas 1" so existing grades are not split
        // across a second component named "Tugas1".
        $existing=$db->table('komponen_nilai')->where(['kelas_id'=>$class,'mata_pelajaran_id'=>$subject])->orderBy('id')->get()->getResultArray();
        foreach($existing as $row)if(\App\Libraries\GradeComponents::key($row['nama_komponen'])===$component){$component=$row['nama_komponen'];break;}
        $key = ['kelas_id'=>$class,'mata_pelajaran_id'=>$subject,'nama_komponen'=>$component];
        $componentKey=\App\Libraries\GradeComponents::key($component);
        $db->query("INSERT INTO komponen_nilai (kelas_id,mata_pelajaran_id,nama_komponen,jenis_komponen,urutan,bobot,aktif) VALUES (?,?,?,?,?,0,1) ON DUPLICATE KEY UPDATE aktif=1",[$class,$subject,$component,str_starts_with($componentKey,'UH')?'UH':'Tugas',\App\Libraries\GradeComponents::order($component)]);
        $id = $db->table('komponen_nilai')->where($key)->get()->getRow()->id;
        $notifications = new \App\Libraries\StudentGradeAutomation($db);
        foreach ($p['values'] as $nis=>$value) {
            $previous=$db->table('nilai_siswa')->where(['siswa_id'=>$students[$nis],'komponen_nilai_id'=>$id,'semester'=>$semester])->get()->getRowArray();
            if ($value===null) $db->table('nilai_siswa')->where(['siswa_id'=>$students[$nis],'komponen_nilai_id'=>$id,'semester'=>$semester])->delete();
            else $db->query('INSERT INTO nilai_siswa (siswa_id,komponen_nilai_id,semester,nilai,dinilai_oleh) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai),dinilai_oleh=VALUES(dinilai_oleh)',[$students[$nis],$id,$semester,$value,$this->currentUser()['id']]);
            $stored=$value===null?null:$db->table('nilai_siswa')->where(['siswa_id'=>$students[$nis],'komponen_nilai_id'=>$id,'semester'=>$semester])->get()->getRowArray()['nilai'];
            $notifications->changed((int)$students[$nis],(int)$id,$semester,$previous['nilai']??null,$stored);
        }
        $db->transComplete();
        return $this->respond(['ok'=>$db->transStatus()],$db->transStatus()?200:500);
    }
}
