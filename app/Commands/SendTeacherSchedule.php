<?php
namespace App\Commands;
use App\Libraries\TeacherScheduleAutomation;
use App\Libraries\WhatsAppGateway;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;
use DateTimeZone;

class SendTeacherSchedule extends BaseCommand
{
    protected $group='AIWalas';
    protected $name='whatsapp:teacher-schedule';
    protected $description='Pengingat H-10 menit dan terima kasih saat jadwal selesai. Default hanya simulasi.';
    protected $options=['--send'=>'Kirim melalui gateway jika otomatisasi diaktifkan.','--at'=>'Waktu simulasi YYYY-MM-DD HH:MM:SS (tidak boleh dipakai bersama --send).','--status'=>'Periksa konfigurasi dan heartbeat cron tanpa mengirim pesan.'];
    public function run(array $params)
    {
        $send=CLI::getOption('send')!==null;$at=CLI::getOption('at');$db=db_connect();
        if(CLI::getOption('status')!==null){
            if($send||$at!==null){CLI::error('--status tidak boleh digabung dengan --send atau --at.');exit(1);}
            $settings=TeacherScheduleAutomation::settings();$gateway=(new WhatsAppGateway())->configured();
            $worker=$db->table('teacher_schedule_worker')->where('id',1)->get()->getRowArray();
            $zone=new DateTimeZone('Asia/Jakarta');$now=new DateTimeImmutable('now',$zone);
            $age=$worker?$now->getTimestamp()-(new DateTimeImmutable($worker['last_started'],$zone))->getTimestamp():null;
            $healthy=$age!==null&&$age>=0&&$age<180&&in_array($worker['last_status'],['ok','attention','running'],true);
            CLI::write(json_encode(['enabled'=>$settings['enabled'],'gateway_configured'=>$gateway,'worker_recent'=>$healthy,'worker'=>$worker],JSON_UNESCAPED_SLASHES));
            if($settings['enabled']&&(!$gateway||!$healthy))exit(1);
            return;
        }
        if($send&&$at!==null){CLI::error('--at hanya untuk simulasi, tanpa --send.');exit(1);}
        try{
            $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta'));
            if($at!==null){
                if(!is_string($at))throw new \InvalidArgumentException('Nilai --at wajib diisi.');
                $value=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$at,new DateTimeZone('Asia/Jakarta'));
                if(!$value||$value->format('Y-m-d H:i:s')!==$at)throw new \InvalidArgumentException('Format --at harus YYYY-MM-DD HH:MM:SS.');
                $now=$value;
            }
            $settings=TeacherScheduleAutomation::settings();
            if($send&&!$settings['enabled']){CLI::write('Pengiriman otomatis dinonaktifkan. Tidak ada pesan dikirim.');return;}
            if($send&&!(new WhatsAppGateway())->configured())throw new \RuntimeException('Gateway belum dikonfigurasi; tidak ada pesan dikirim.');
            if($send)$db->query("INSERT INTO teacher_schedule_worker (id,last_started,last_finished,last_status) VALUES (1,?,NULL,'running') ON DUPLICATE KEY UPDATE last_started=VALUES(last_started),last_finished=NULL,last_status='running'",[$now->format('Y-m-d H:i:s')]);
            $result=(new TeacherScheduleAutomation($db))->run($now,!$send);
            if($send)$db->table('teacher_schedule_worker')->where('id',1)->update(['last_finished'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s'),'last_status'=>($result['unknown']||$result['failed'])?'attention':'ok']);
            CLI::write(($send?'Pengiriman':'Simulasi').' '.$now->format('Y-m-d H:i:s').' WIB: '.json_encode($result));
        }catch(\Throwable $error){
            if($send){try{$db->table('teacher_schedule_worker')->where('id',1)->update(['last_status'=>'error']);}catch(\Throwable $ignored){}}
            CLI::error('Proses jadwal guru gagal. Periksa konfigurasi, database dan log aplikasi.');
            log_message('error','Teacher schedule scheduler failed: {type}',['type'=>get_class($error)]);
            exit(1);
        }
    }
}
