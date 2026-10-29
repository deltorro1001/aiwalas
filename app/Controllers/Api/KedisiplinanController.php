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
        return $this->respond(['data'=>(new DisciplineRecords())->forClass($classId),'status_options'=>DisciplineRecords::MANUAL_STATUSES]);
    }
    public function save()
    {
        $p=$this->payload();$errors=$this->validatePayload($p,['siswa_id'=>'required|is_natural_no_zero','kategori'=>'required|in_list[etika,terlambat,atribut]','pelanggaran'=>'permit_empty|max_length[2000]','tanggal'=>'permit_empty|valid_date[Y-m-d]','saksi'=>'permit_empty|max_length[180]','alasan'=>'permit_empty|max_length[2000]','tindakan'=>'permit_empty|max_length[2000]','perbaikan'=>'permit_empty|max_length[2000]','jumlah_terlambat'=>'permit_empty|is_natural|less_than_equal_to[10000]']);
        if($errors)return $this->respond(['ok'=>false,'errors'=>$errors],422);
        if(array_key_exists('status_kedisiplinan',$p)){
            if($p['kategori']==='terlambat')return $this->respond(['message'=>'Status keterlambatan dihitung otomatis dari jumlah keterlambatan.'],422);
            if(!is_string($p['status_kedisiplinan'])||!in_array($p['status_kedisiplinan'],DisciplineRecords::MANUAL_STATUSES,true))return $this->respond(['message'=>'Pilih status kedisiplinan yang valid.'],422);
        }
        $dates=null;
        if(array_key_exists('tanggal_kejadian',$p)){
            if(!is_array($p['tanggal_kejadian'])||count($p['tanggal_kejadian'])>500)return $this->respond(['message'=>'Maksimal 500 tanggal kejadian.'],422);
            $dates=[];
            foreach($p['tanggal_kejadian'] as $value){
                $date=is_string($value)?\DateTimeImmutable::createFromFormat('!Y-m-d',$value):false;
                if(!$date||$date->format('Y-m-d')!==$value||(int)$date->format('Y')<1000)return $this->respond(['message'=>'Tanggal kejadian tidak valid.'],422);
                $dates[]=$value;
            }
            $dates=array_values(array_unique($dates));sort($dates);
        }
        $reasons=null;
        if(array_key_exists('alasan_per_tanggal',$p)){
            if($p['kategori']!=='terlambat'||!is_array($p['alasan_per_tanggal'])||count($p['alasan_per_tanggal'])>500||$dates===null)return $this->respond(['message'=>'Isi alasan untuk tanggal keterlambatan yang dipilih.'],422);
            $reasons=[];
            foreach($p['alasan_per_tanggal'] as $date=>$reason){
                if(!in_array($date,$dates,true)||!is_string($reason)||mb_strlen($reason)>2000)return $this->respond(['message'=>'Alasan harus sesuai tanggal keterlambatan dan maksimal 2.000 karakter per kejadian.'],422);
                $reasons[$date]=trim($reason);
            }
        }
        $correctLate=$p['kategori']==='terlambat'&&($p['koreksi_keterlambatan']??false)===true;
        if($correctLate&&($dates===null||!isset($p['jumlah_terlambat'])||$p['jumlah_terlambat']===''))return $this->respond(['message'=>'Isi jumlah dan tanggal keterlambatan yang ingin disimpan.'],422);
        if($p['kategori']==='terlambat'){
            if(isset($p['status_notifikasi_orangtua'])&&(!is_string($p['status_notifikasi_orangtua'])||!in_array($p['status_notifikasi_orangtua'],['','Belum diberitahu','Sudah diberitahu','Perlu tindak lanjut'],true)))return $this->respond(['message'=>'Status pemberitahuan orangtua tidak valid.'],422);
            if(isset($p['notifikasi_orangtua'])&&(!is_string($p['notifikasi_orangtua'])||mb_strlen($p['notifikasi_orangtua'])>2000))return $this->respond(['message'=>'Catatan pemberitahuan maksimal 2.000 karakter.'],422);
        }
        $db=db_connect();$student=$db->table('siswa s')->select('s.id,s.kelas_id')->join('kelas k','k.id=s.kelas_id')->where(['s.id'=>(int)$p['siswa_id'],'s.aktif'=>1,'k.wali_kelas_id'=>$this->currentUser()['id']])->get()->getRowArray();
        if(!$student)return $this->respond(['ok'=>false,'message'=>'Siswa tidak dapat diakses.'],404);
        $record=['siswa_id'=>$student['id'],'kelas_id'=>$student['kelas_id'],'kategori'=>$p['kategori'],'dicatat_oleh'=>$this->currentUser()['id']];
        foreach(DisciplineRecords::FIELDS[$p['kategori']] as $field){$value=$p[$field]??null;$record[$field]=$value===null||trim((string)$value)===''?null:($field==='jumlah_terlambat'?(int)$value:trim($value));}
        $db->transBegin();
        // Lock per student so concurrent corrections do not lose earlier dates.
        $db->query('SELECT id FROM siswa WHERE id=? FOR UPDATE',[$student['id']]);
        $existing=$db->table('catatan_kedisiplinan')->where(['siswa_id'=>$student['id'],'kategori'=>$p['kategori']])->get()->getRowArray();
        if($dates===null){
            $dates=json_decode($existing['tanggal_kejadian']??'[]',true);
            if(!is_array($dates))$dates=[];
            if(!empty($existing['tanggal']))$dates[]=$existing['tanggal'];
            if(!empty($p['tanggal']))$dates[]=$p['tanggal'];
            $dates=array_values(array_unique($dates));sort($dates);
        }
        $record['tanggal']=$dates?end($dates):null;
        if($correctLate){
            $auto=$db->table('absensi')->select('tanggal')->where(['siswa_id'=>$student['id'],'kelas_id'=>$student['kelas_id'],'status'=>'Terlambat'])->whereNotIn('tanggal',\App\Libraries\SchoolCalendar::attendanceOverrideDates())->where('tanggal <=',date('Y-m-d'))->get()->getResultArray();
            $autoDates=array_column($auto,'tanggal');
            // Preserve the scan records; remember which dates were removed from discipline.
            $record['tanggal_dikecualikan']=json_encode(array_values(array_diff(array_unique($autoDates),$dates)));
            $record['jumlah_dasar_absensi']=count(array_intersect($autoDates,$dates));
            $dates=array_values(array_diff($dates,$autoDates));
        }
        $record['tanggal_kejadian']=json_encode($dates);
        if($p['kategori']!=='terlambat'){
            $requested=$p['status_kedisiplinan']??(DisciplineRecords::hasDetails($record)?($existing['status_kedisiplinan']??null):'Baik');
            if(array_key_exists('status_kedisiplinan',$p)&&$requested==='Baik'&&DisciplineRecords::hasDetails($record)){
                $db->transRollback();return $this->respond(['message'=>'Pilih status peringatan untuk catatan pelanggaran. Status Baik berlaku jika tidak ada catatan pelanggaran.'],422);
            }
            $record['status_kedisiplinan']=DisciplineRecords::manualStatus($record+['status_kedisiplinan'=>$requested]);
        }
        if($reasons!==null){
            $record['alasan_per_tanggal']=json_encode($reasons);
            $record['alasan']=$record['tanggal']?($reasons[$record['tanggal']]??null):null;
        }

        if($p['kategori']==='terlambat')foreach(['status_notifikasi_orangtua','notifikasi_orangtua'] as $field){
            if(array_key_exists($field,$p))$record[$field]=trim((string)($p[$field]??''))?:null;
        }
        $columns=array_keys($record);$updates=array_filter($columns,static fn($name)=>!in_array($name,['siswa_id','kategori'],true));
        $db->query('INSERT INTO catatan_kedisiplinan ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).') ON DUPLICATE KEY UPDATE '.implode(',',array_map(static fn($name)=>$name.'=VALUES('.$name.')',$updates)),array_values($record));
        if($db->transStatus()===false){$db->transRollback();return $this->respond(['message'=>'Catatan belum tersimpan. Silakan coba kembali.'],500);}
        $db->transCommit();
        return $this->respond(['ok'=>true]);
    }
}
