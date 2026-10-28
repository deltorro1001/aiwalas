<?php
namespace App\Libraries;

final class MergePkkIntoKik
{
    public static function apply(): void
    {
        $db=db_connect();
        $subjects=$db->table('mata_pelajaran')->whereIn('UPPER(TRIM(nama_mata_pelajaran))',['PKK','KIK'])->orderBy('id')->get()->getResultArray();
        if(!$subjects)return;
        $canonical=$subjects[0];foreach($subjects as $subject)if(strtoupper(trim($subject['nama_mata_pelajaran']))==='KIK'){$canonical=$subject;break;}
        $id=(int)$canonical['id'];$ids=array_map('intval',array_column($subjects,'id'));
        $db->transBegin();
        try{
            $db->query('SELECT id FROM mata_pelajaran WHERE id IN ('.implode(',',$ids).') FOR UPDATE');
            $groups=[];
            foreach($db->table('komponen_nilai')->whereIn('mata_pelajaran_id',$ids)->get()->getResultArray() as $component){
                $name=trim($component['nama_komponen']);$token=strtoupper(preg_replace('/\s+/','',$name));
                if(preg_match('/^(UH[1-6]|ATS|ASS)$/D',$token))$name=$token;
                $component['nama_baku']=$name;$groups[$component['kelas_id'].':'.mb_strtolower($name)][]=$component;
            }
            foreach($groups as $components){
                usort($components,static fn($a,$b)=>[(int)$a['mata_pelajaran_id']!==$id,$a['nama_komponen']!==$a['nama_baku'],(int)$a['id']]<=>[(int)$b['mata_pelajaran_id']!==$id,$b['nama_komponen']!==$b['nama_baku'],(int)$b['id']]);
                $target=array_shift($components);$active=(int)$target['aktif'];
                foreach($components as $source){
                    $active=max($active,(int)$source['aktif']);
                    foreach($db->table('nilai_siswa')->where('komponen_nilai_id',$source['id'])->get()->getResultArray() as $grade){
                        $existing=$db->table('nilai_siswa')->where(['komponen_nilai_id'=>$target['id'],'siswa_id'=>$grade['siswa_id'],'semester'=>$grade['semester']])->get()->getRowArray();
                        if(!$existing){$db->table('nilai_siswa')->where('id',$grade['id'])->update(['komponen_nilai_id'=>$target['id'],'diperbarui_pada'=>$grade['diperbarui_pada']]);continue;}
                        // Keep both original records in an internal archive before merging.
                        foreach([[$grade,$source],[$existing,$target]] as [$original,$component])$db->query('INSERT IGNORE INTO arsip_penggabungan_pkk(nilai_id,data_asli) VALUES(?,?)',[$original['id'],json_encode(['nilai'=>$original,'komponen'=>$component])]);
                        $incoming=$grade['diperbarui_pada']?:$grade['dibuat_pada'];$current=$existing['diperbarui_pada']?:$existing['dibuat_pada'];
                        if($incoming>$current)$db->table('nilai_siswa')->where('id',$existing['id'])->update(['nilai'=>$grade['nilai'],'keterangan'=>$grade['keterangan'],'dinilai_oleh'=>$grade['dinilai_oleh'],'dibuat_pada'=>$grade['dibuat_pada'],'diperbarui_pada'=>$grade['diperbarui_pada']]);
                        $db->table('nilai_siswa')->where('id',$grade['id'])->delete();
                    }
                    $db->table('komponen_nilai')->where('id',$source['id'])->delete();
                }
                $db->table('komponen_nilai')->where('id',$target['id'])->update(['mata_pelajaran_id'=>$id,'nama_komponen'=>$target['nama_baku'],'aktif'=>$active]);
            }
            foreach($ids as $old){
                if($old===$id)continue;
                foreach(['jadwal_kelas','jurnal_kelas','remedial_tugas'] as $table)$db->table($table)->where('mata_pelajaran_id',$old)->update(['mata_pelajaran_id'=>$id]);
                $db->query('INSERT IGNORE INTO pengampu_mapel(guru_id,kelas_id,mata_pelajaran_id) SELECT guru_id,kelas_id,? FROM pengampu_mapel WHERE mata_pelajaran_id=?',[$id,$old]);
                $db->table('pengampu_mapel')->where('mata_pelajaran_id',$old)->delete();
                $db->table('mata_pelajaran')->where('id',$old)->delete();
            }
            $db->table('mata_pelajaran')->where('id',$id)->update(['nama_mata_pelajaran'=>'KIK','aktif'=>1]);
            foreach($db->table('guru')->like('mata_pelajaran','PKK')->get()->getResultArray() as $teacher){
                $label=preg_replace('/\bPKK\b/i','KIK',$teacher['mata_pelajaran']);
                $db->table('guru')->where('id',$teacher['id'])->update(['mata_pelajaran'=>$label]);
            }
            if($db->transStatus()===false)throw new \RuntimeException('Penggabungan PKK/KIK gagal.');
            $db->transCommit();
        }catch(\Throwable $error){$db->transRollback();throw $error;}
    }
}
