<?php
namespace App\Controllers\Api;
class GuruSiswaController extends BaseApiController
{
 public function index(){ $u=$this->currentUser();$kelas=(int)$this->request->getGet('kelas_id');$guru=(int)($u['guru_id']??0);if(!$guru||!$kelas)return $this->respond(['data'=>[]]);$ok=db_connect()->table('jadwal_kelas')->where(['guru_id'=>$guru,'kelas_id'=>$kelas])->countAllResults();if(!$ok)return $this->respond(['data'=>[]]);$rows=db_connect()->table('siswa')->select('id,nis,nama_lengkap,nomor_hp,nomor_hp_orang_tua')->where(['kelas_id'=>$kelas,'aktif'=>1])->orderBy('nama_lengkap')->get()->getResultArray();return $this->respond(['data'=>$rows]); }
}
