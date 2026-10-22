<?php
namespace App\Controllers\Api;
use App\Libraries\TeacherScheduleAutomation;
use App\Libraries\WhatsAppGateway;
use DateTimeImmutable;
use DateTimeZone;

class TeacherScheduleAutomationController extends BaseApiController
{
    public function index()
    {
        $class=(int)$this->request->getGet('kelas_id');$db=db_connect();$user=$this->currentUser();
        if(!$db->table('kelas')->where(['id'=>$class,'wali_kelas_id'=>$user['id']])->countAllResults())return $this->respond(['ok'=>false,'message'=>'Kelas tidak dapat diakses.'],404);
        $settings=TeacherScheduleAutomation::settings();$now=new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta'));
        try{$events=(new TeacherScheduleAutomation($db))->candidates($now,$class);}catch(\InvalidArgumentException $error){return $this->respond(['ok'=>false,'message'=>'Konfigurasi tanggal pengiriman otomatis perlu diperiksa.'],422);}
        $logs=$db->table('teacher_schedule_dispatches')->where('kelas_id',$class)->where('lesson_date >=',$now->format('Y-m-d'))->where('lesson_date <=',$now->modify('+1 day')->format('Y-m-d'))->get()->getResultArray();$states=array_column($logs,'status','session_key');
        $rows=[];
        foreach($events as $event){
            if($event['due']->format('Y-m-d')!==$now->format('Y-m-d'))continue;
            $row=$event['schedule'];$rows[]=['teacher'=>$row['teacher_name'],'subject'=>$row['nama_mata_pelajaran'],'class'=>$row['nama_kelas'],'lesson_date'=>$event['date'],'start'=>$row['jam_mulai'],'end'=>$row['jam_selesai'],'type'=>$event['kind'],'due_at'=>$event['due']->format('Y-m-d H:i:s'),'status'=>$states[$event['key']]??($now->getTimestamp()-$event['due']->getTimestamp()>=60?'missed':'pending'),'phone_available'=>$event['phone']!==''];
        }
        $worker=$db->table('teacher_schedule_worker')->where('id',1)->get()->getRowArray();
        $recent=false;if($worker){$last=new DateTimeImmutable($worker['last_started'],new DateTimeZone('Asia/Jakarta'));$age=$now->getTimestamp()-$last->getTimestamp();$recent=$age>=0&&$age<=180&&in_array($worker['last_status'],['ok','attention','running'],true);}
        $schedule=$db->table('jadwal_kelas j')->select('j.hari,j.jam_mulai,j.jam_selesai,m.nama_mata_pelajaran,g.nama_lengkap AS nama_guru')->join('mata_pelajaran m','m.id=j.mata_pelajaran_id')->join('guru g','g.id=j.guru_id','left')->where('j.kelas_id',$class)->orderBy('j.jam_mulai')->get()->getResultArray();
        $this->response->setHeader('Cache-Control','no-store');
        return $this->respond(['excluded_teachers'=>TeacherScheduleAutomation::EXCLUDED_TEACHERS,'schedule'=>$schedule,'enabled'=>$settings['enabled'],'gateway_configured'=>(new WhatsAppGateway())->configured(),'worker_recent'=>$recent,'last_run'=>$worker['last_finished']??null,'timezone'=>'Asia/Jakarta','data'=>$rows]);
    }
}
