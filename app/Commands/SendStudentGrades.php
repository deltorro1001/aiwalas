<?php
namespace App\Commands;
use App\Libraries\StudentGradeAutomation;
use App\Libraries\WhatsAppGateway;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SendStudentGrades extends BaseCommand
{
    protected $group='AIWalas';
    protected $name='whatsapp:student-grades';
    protected $description='Proses antrean notifikasi nilai siswa di bawah 80.';
    protected $options=['--send'=>'Proses pengiriman jika otomatisasi dan gateway aktif.'];
    public function run(array $params)
    {
        if(CLI::getOption('send')===null){CLI::write('Tidak ada pengiriman. Gunakan --send untuk memproses antrean.');return;}
        if(!StudentGradeAutomation::enabled()){CLI::write('Notifikasi nilai siswa otomatis dinonaktifkan.');return;}
        if(!(new WhatsAppGateway())->configured()){CLI::error('Gateway belum dikonfigurasi; antrean tetap tersimpan.');exit(1);}
        try{CLI::write(json_encode((new StudentGradeAutomation())->run()));}
        catch(\Throwable $error){log_message('error','Student grade dispatch failed: {type}',['type'=>get_class($error)]);CLI::error('Pengiriman nilai terhenti; periksa log aplikasi.');exit(1);}
    }
}
