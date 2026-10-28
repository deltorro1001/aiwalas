<?php
namespace App\Controllers\Api;

class KelolaAkunController extends BaseApiController
{
    private function accounts(): array
    {
        $db=db_connect();$wali=(int)$this->currentUser()['id'];
        $rows=$db->table('pengguna p')->select('p.id,p.nama_pengguna,p.nama_lengkap,p.peran,p.aktif,p.nis,p.siswa_id,p.guru_id,s.id AS anak_id,s.nis AS nis_anak,s.nama_lengkap AS nama_anak,s.aktif AS anak_aktif,k.nama_kelas,g.nama_lengkap AS nama_guru,g.aktif AS guru_aktif')
            ->join('siswa s',"(p.peran='orang_tua' AND s.id=p.siswa_id) OR (p.peran IN ('siswa','sekretaris') AND s.nis=p.nis)",'left')->join('kelas k','k.id=s.kelas_id','left')->join('guru g','g.id=p.guru_id','left')
            ->whereIn('p.peran',['sekretaris','siswa','orang_tua','guru'])->groupStart()->where('k.wali_kelas_id',$wali)->orWhere('p.peran','guru')->groupEnd()->orderBy('p.nama_lengkap')->get()->getResultArray();
        $result=[];$linked=[];
        foreach($rows as $row){
            $role=$row['peran'];$parent=$role==='orang_tua';$teacher=$role==='guru';
            $active=(bool)$row['aktif']&&($teacher?(bool)$row['guru_aktif']:(bool)$row['anak_aktif']);
            $result[]=['key'=>'akun:'.$row['id'],'name'=>$parent?'Orangtua/Wali Murid '.$row['nama_anak']:$row['nama_lengkap'],'role'=>$role,'username'=>$parent?$row['nis_anak']:$row['nama_pengguna'],'class'=>$teacher?'Guru mata pelajaran':$row['nama_kelas'],'status'=>$active?'Aktif':'Nonaktif','can_reset'=>$active,'shared_student'=>false];
            if($role==='sekretaris'&&$row['anak_id'])$result[]=['key'=>'siswa:'.$row['anak_id'],'name'=>$row['nama_anak'],'role'=>'siswa','username'=>$row['nis_anak'],'class'=>$row['nama_kelas'],'status'=>$active?'Aktif':'Nonaktif','can_reset'=>$active,'shared_student'=>false];
            if($row['anak_id'])$linked[$row['anak_id']][$parent?'orang_tua':'siswa']=true;
        }
        // Legacy student accounts are created on first login; list their NIS too.
        foreach($db->table('siswa s')->select('s.id,s.nis,s.nama_lengkap,k.nama_kelas')->join('kelas k','k.id=s.kelas_id')->where(['k.wali_kelas_id'=>$wali,'s.aktif'=>1])->get()->getResultArray() as $child){
            foreach(['siswa','orang_tua'] as $role)if(empty($linked[$child['id']][$role]))$result[]=['key'=>$role.':'.$child['id'],'name'=>($role==='orang_tua'?'Orangtua/Wali Murid ':'').$child['nama_lengkap'],'role'=>$role,'username'=>$child['nis'],'class'=>$child['nama_kelas'],'status'=>'Belum diaktifkan','can_reset'=>true,'shared_student'=>false];
        }
        usort($result,static fn($a,$b)=>strcasecmp($a['name'],$b['name']));
        return $result;
    }

    public function index()
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        return $this->respond(['data'=>$this->accounts()]);
    }

    public function resetPassword()
    {
        $p=$this->payload();$key=$p['akun']??null;
        if(!is_string($key)||!preg_match('/^(akun|siswa|orang_tua):([1-9][0-9]*)$/D',$key,$parts)||($p['konfirmasi']??false)!==true)return $this->respond(['message'=>'Pilih akun dan konfirmasikan reset password.'],422);
        $db=db_connect();$wali=(int)$this->currentUser()['id'];$password=bin2hex(random_bytes(10));
        $db->transBegin();
        try{
            $account=null;$username=null;
            if($parts[1]==='akun'){
                $account=$db->query('SELECT * FROM pengguna WHERE id=? FOR UPDATE',[(int)$parts[2]])->getRowArray();
            }else{
                $child=$db->query('SELECT s.* FROM siswa s JOIN kelas k ON k.id=s.kelas_id WHERE s.id=? AND s.aktif=1 AND k.wali_kelas_id=? FOR UPDATE',[(int)$parts[2],$wali])->getRowArray();
                if($child){
                    if($parts[1]==='orang_tua'){
                        $id=\App\Libraries\ParentAccounts::ensure($child);
                        $account=$db->table('pengguna')->where('id',$id)->get()->getRowArray();
                    }else{
                        $account=$db->table('pengguna')->where('nis',$child['nis'])->get()->getRowArray();
                        $username=$child['nis'];
                        if($account&&!in_array($account['peran'],['siswa','sekretaris'],true))throw new \RuntimeException('Invalid linked role');
                        if(!$account){
                            $db->table('pengguna')->insert(['nama_pengguna'=>$child['nis'],'nis'=>$child['nis'],'nama_lengkap'=>$child['nama_lengkap'],'peran'=>'siswa','aktif'=>1,'kata_sandi'=>password_hash($password,PASSWORD_DEFAULT)]);
                            $account=$db->table('pengguna')->where('id',$db->insertID())->get()->getRowArray();
                        }
                    }
                }
            }
            $allowed=false;
            if($account&&(int)$account['aktif']===1){
                if($account['peran']==='guru')$allowed=(bool)$db->table('guru')->where(['id'=>$account['guru_id'],'aktif'=>1])->countAllResults();
                elseif(in_array($account['peran'],['sekretaris','siswa','orang_tua'],true)){
                    $q=$db->table('siswa s')->select('s.nis')->join('kelas k','k.id=s.kelas_id')->where(['k.wali_kelas_id'=>$wali,'s.aktif'=>1]);
                    if($account['peran']==='orang_tua')$q->where('s.id',$account['siswa_id']);else $q->where('s.nis',$account['nis']);
                    $child=$q->get()->getRowArray();$allowed=(bool)$child;
                    if($child&&$account['peran']==='orang_tua')$username=$child['nis'];
                }
            }
            if(!$allowed){$db->transRollback();return $this->respond(['message'=>'Akun aktif tidak ditemukan atau tidak dapat dikelola.'],404);}
            $field=$account['peran']==='sekretaris'&&$parts[1]==='siswa'?'kata_sandi_siswa':'kata_sandi';
            $updates=[$field=>password_hash($password,PASSWORD_DEFAULT)];
            if($account['peran']==='sekretaris'&&$field==='kata_sandi'&&empty($account['kata_sandi_siswa']))$updates['kata_sandi_siswa']=$account['kata_sandi'];
            $db->table('pengguna')->where('id',$account['id'])->update($updates);
            if($db->transStatus()===false)throw new \RuntimeException('Reset failed');
            $db->transCommit();
            $this->response->setHeader('Cache-Control','private, no-store');
            return $this->respond(['ok'=>true,'username'=>$username??$account['nama_pengguna'],'password'=>$password,'role'=>$parts[1]==='siswa'?'siswa':$account['peran']]);
        }catch(\Throwable $error){
            $db->transRollback();return $this->respond(['message'=>'Password belum dapat direset. Muat ulang daftar akun dan coba kembali.'],409);
        }
    }
}
