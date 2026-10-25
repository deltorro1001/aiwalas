<?php
namespace App\Libraries;

class StudentWhatsAppNotifications
{
    public function latest(array $student): array
    {
        $db=db_connect();$owners=[];
        // Older gateway history has no siswa_id. Match only phones unique to this student
        // within the class, so a shared family number cannot expose another child's message.
        $classmates=$db->table('siswa')->select('id,nomor_hp,nomor_hp_orang_tua')->where(['kelas_id'=>$student['kelas_id'],'aktif'=>1])->get()->getResultArray();
        foreach($classmates as $classmate){
            foreach(['nomor_hp','nomor_hp_orang_tua'] as $field){
                $phone=WhatsAppGateway::phone($classmate[$field]??'');
                if($phone!=='')$owners[$phone][(int)$classmate['id']]=true;
            }
        }
        $phones=[];
        foreach($owners as $phone=>$ids)if(count($ids)===1&&isset($ids[(int)$student['id']]))$phones[]=(string)$phone;
        $query=$db->table('pesan_komunikasi')->select('id,pengirim,isi_pesan,kategori,dikirim_pada')
            ->where(['kelas_id'=>$student['kelas_id'],'status_pengiriman'=>'Terkirim'])->groupStart()->where('siswa_id',$student['id']);
        if($phones){
            $query->orGroupStart()->where('siswa_id',null)->whereIn('nomor_tujuan',$phones)
                ->groupStart()->where('kategori',null)->orWhereNotIn('kategori',['Pengingat Guru Otomatis','Terima Kasih Guru Otomatis'])->groupEnd()->groupEnd();
        }
        return $query->groupEnd()->orderBy('dikirim_pada','DESC')->orderBy('id','DESC')->limit(4)->get()->getResultArray();
    }
}
