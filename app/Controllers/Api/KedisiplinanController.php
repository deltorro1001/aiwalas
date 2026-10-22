<?php
namespace App\Controllers\Api;
use App\Libraries\DisciplineRecords;

class KedisiplinanController extends BaseApiController
{
    public function index()
    {
        $classId=(int)$this->request->getGet('kelas_id');
        if(!db_connect()->table('kelas')->where(['id'=>$classId,'wali_kelas_id'=>$this->currentUser()['id']])->countAllResults())return $this->respond(['ok'=>false,'message'=>'Kelas tidak dapat diakses.'],404);
        $this->response->setHeader('Cache-Control','no-store');
        return $this->respond(['data'=>(new DisciplineRecords())->forClass($classId)]);
    }
    public function save()
    {
        $p=$this->payload();$errors=$this->validatePayload($p,['siswa_id'=>'required|is_natural_no_zero','kategori'=>'required|in_list[etika,terlambat,atribut]','pelanggaran'=>'permit_empty|max_length[2000]','tanggal'=>'permit_empty|valid_date[Y-m-d]','saksi'=>'permit_empty|max_length[180]','alasan'=>'permit_empty|max_length[2000]','tindakan'=>'permit_empty|max_length[2000]','perbaikan'=>'permit_empty|max_length[2000]','jumlah_terlambat'=>'permit_empty|is_natural|less_than_equal_to[10000]']);
        if($errors)return $this->respond(['ok'=>false,'errors'=>$errors],422);
        $db=db_connect();$student=$db->table('siswa s')->select('s.id,s.kelas_id')->join('kelas k','k.id=s.kelas_id')->where(['s.id'=>(int)$p['siswa_id'],'s.aktif'=>1,'k.wali_kelas_id'=>$this->currentUser()['id']])->get()->getRowArray();
        if(!$student)return $this->respond(['ok'=>false,'message'=>'Siswa tidak dapat diakses.'],404);
        $record=['siswa_id'=>$student['id'],'kelas_id'=>$student['kelas_id'],'kategori'=>$p['kategori'],'dicatat_oleh'=>$this->currentUser()['id']];
        foreach(DisciplineRecords::FIELDS[$p['kategori']] as $field){$value=$p[$field]??null;$record[$field]=$value===null||trim((string)$value)===''?null:($field==='jumlah_terlambat'?(int)$value:trim($value));}
        $columns=array_keys($record);$updates=array_filter($columns,static fn($name)=>!in_array($name,['siswa_id','kategori'],true));
        $db->query('INSERT INTO catatan_kedisiplinan ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).') ON DUPLICATE KEY UPDATE '.implode(',',array_map(static fn($name)=>$name.'=VALUES('.$name.')',$updates)),array_values($record));
        return $this->respond(['ok'=>true]);
    }
}
