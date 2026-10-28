<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class TambahMapelAnimasi3d extends Migration
{
    public function up()
    {
        $this->db->query('CREATE TABLE pengampu_mapel (guru_id INT UNSIGNED NOT NULL,kelas_id INT UNSIGNED NOT NULL,mata_pelajaran_id INT UNSIGNED NOT NULL,PRIMARY KEY(guru_id,kelas_id,mata_pelajaran_id),CONSTRAINT fk_pengampu_guru FOREIGN KEY(guru_id) REFERENCES guru(id) ON DELETE CASCADE,CONSTRAINT fk_pengampu_kelas FOREIGN KEY(kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,CONSTRAINT fk_pengampu_mapel FOREIGN KEY(mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->db->transBegin();
        $subject=$this->db->table('mata_pelajaran')->where('nama_mata_pelajaran','Animasi 3D')->get()->getRowArray();
        if(!$subject){$this->db->table('mata_pelajaran')->insert(['nama_mata_pelajaran'=>'Animasi 3D','aktif'=>1]);$subject=['id'=>$this->db->insertID()];}
        else $this->db->table('mata_pelajaran')->where('id',$subject['id'])->update(['aktif'=>1]);
        $teacher=$this->db->table('guru')->like('nama_lengkap','Sumantoro Kasdhani','after')->where('aktif',1)->orderBy('id')->get()->getRowArray();
        if($teacher){
            $name='Sumantoro Kasdhani, S.Kom., M.I.Kom.';
            $list=trim((string)$teacher['mata_pelajaran']);
            if(stripos($list,'Animasi 3D')===false)$list=($list?$list.', ':'').'Animasi 3D';
            $this->db->table('guru')->where('id',$teacher['id'])->update(['nama_lengkap'=>$name,'mata_pelajaran'=>$list]);
            $this->db->table('pengguna')->where(['guru_id'=>$teacher['id'],'peran'=>'guru'])->update(['nama_lengkap'=>$name]);
            $classes=$this->db->table('jadwal_kelas j')->distinct()->select('j.kelas_id')->join('kelas k','k.id=j.kelas_id')->join('tahun_ajaran t','t.id=k.tahun_ajaran_id')->where(['j.guru_id'=>$teacher['id'],'t.aktif'=>1])->get()->getResultArray();
            foreach($classes as $class)$this->db->query('INSERT IGNORE INTO pengampu_mapel(guru_id,kelas_id,mata_pelajaran_id) VALUES(?,?,?)',[$teacher['id'],$class['kelas_id'],$subject['id']]);
        }
        if($this->db->transStatus()===false){$this->db->transRollback();throw new \RuntimeException('Penambahan mapel Animasi 3D gagal.');}
        $this->db->transCommit();
    }
    public function down(){throw new \RuntimeException('Penugasan dan nilai mapel perlu dicadangkan sebelum migrasi dihapus.');}
}
