<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class InitialDataSeeder extends Seeder
{
    public function run()
    {
        if ($this->db->table('tahun_ajaran')->where(['nama_tahun_ajaran' => '2026/2027', 'semester' => 'Ganjil'])->countAllResults() > 0) {
            echo "Data awal AIWalas sudah tersedia.\n";
            return;
        }

        $this->db->transStart();
        $this->seedInti();
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('Seeder AIWalas gagal dan transaksi dibatalkan.');
        }
    }

    private function seedInti(): void
    {
        $this->db->table('tahun_ajaran')->insert([
            'nama_tahun_ajaran' => '2026/2027', 'semester' => 'Ganjil', 'aktif' => 1,
        ]);
        $tahunId = (int) $this->db->insertID();

        $akun = [
            ['sumantoro', 'aiwalas123', 'Sumantoro Kasdhani, S.Kom., M.I.Kom.', 'wali_kelas', null],
            ['kanaya', 'kanaya123', 'KANAYA SAFA AULIA', 'sekretaris', '13062'],
            ['guest', 'guest123', 'Pengunjung AIWalas', 'guest', null],
        ];
        foreach ($akun as [$username, $password, $nama, $peran, $nis]) {
            $this->db->table('pengguna')->insert([
                'nama_pengguna' => $username, 'kata_sandi' => password_hash($password, PASSWORD_DEFAULT),
                'nama_lengkap' => $nama, 'peran' => $peran, 'nis' => $nis, 'aktif' => 1,
            ]);
        }
        $waliId = (int) $this->db->table('pengguna')->where('nama_pengguna', 'sumantoro')->get()->getRow()->id;

        $this->db->table('kelas')->insert([
            'nama_kelas' => '11PF1', 'tingkat' => 11, 'jurusan' => 'Produksi Film',
            'tahun_ajaran_id' => $tahunId, 'wali_kelas_id' => $waliId,
        ]);
        $kelasId = (int) $this->db->insertID();

        $this->seedSiswa($kelasId);
        $this->seedGuruDanAkademik($kelasId, $waliId);
    }

    private function seedSiswa(int $kelasId): void
    {
        $appJs = file_get_contents(ROOTPATH . 'app.js');
        $detailJs = file_get_contents(ROOTPATH . 'student-details.js');
        if ($appJs === false || $detailJs === false) {
            throw new RuntimeException('Berkas sumber siswa tidak ditemukan.');
        }

        preg_match('/var students=\[(.*?)\];/s', $appJs, $studentBlock);
        preg_match_all("/\\['((?:\\\\.|[^'])*)','(\\d+)','[^']*','([^']*)','([LP])'\\]/", $studentBlock[1] ?? '', $rows, PREG_SET_ORDER);
        preg_match_all("/'(\\d+)':\\{phone:'([^']*)',parentPhone:'([^']*)'\\}/", $appJs, $contactRows, PREG_SET_ORDER);
        $contacts = [];
        foreach ($contactRows as $row) {
            $contacts[$row[1]] = ['nomor_hp' => $row[2], 'nomor_hp_orang_tua' => $row[3]];
        }

        $json = preg_replace('/^\s*var studentDetails\s*=\s*/', '', trim($detailJs));
        $json = preg_replace('/;\s*$/', '', $json);
        $details = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            $nama = stripslashes($row[1]);
            $nis = $row[2];
            $detail = $details[$nis] ?? [];
            $contact = $contacts[$nis] ?? [];
            $data = [
                'nis' => $nis,
                'nisn' => $this->nullValue($detail['NISN'] ?? null),
                'nama_lengkap' => trim((string) ($detail['Nama Siswa'] ?? $nama)),
                'jenis_kelamin' => $row[4],
                'tempat_lahir' => $this->nullValue($detail['Tempat Lahir'] ?? null),
                'tanggal_lahir' => $this->validDate($detail['Tanggal Lahir'] ?? null),
                'agama' => $this->nullValue($detail['Agama'] ?? null),
                'alamat_lengkap' => $this->nullValue($detail['Alamat Lengkap'] ?? null),
                'status_tempat_tinggal' => $this->nullValue($detail['Status tempat tinggal saat ini'] ?? null),
                'transportasi' => $this->nullValue($detail['Transportasi utama ke sekolah'] ?? null),
                'nama_ayah' => $this->nullValue($detail['Nama Ayah'] ?? null),
                'nama_ibu' => $this->nullValue($detail['Nama Ibu'] ?? null),
                'nama_wali' => $this->nullValue($detail['Nama wali (jika tinggal bersama wali) '] ?? null),
                'hubungan_wali' => $this->nullValue($detail['Hubungan wali dengan siswa'] ?? null),
                'kerabat_yang_bisa_dihubungi' => $this->nullValue($detail['Kerabat yang bisa dihubungi'] ?? null),
                'jumlah_saudara' => $this->numberValue($detail['Jumlah saudara kandung'] ?? null),
                'anak_ke' => $this->numberValue($detail['Anak ke '] ?? null),
                'pekerjaan_ayah' => $this->nullValue($detail['Pekerjaan ayah'] ?? null),
                'pekerjaan_ibu' => $this->nullValue($detail['Pekerjaan ibu'] ?? null),
                'menerima_bantuan' => $this->yesValue($detail['Apakah siswa menerima bantuan pendidikan?'] ?? ''),
                'memiliki_penyakit' => $this->yesValue($detail['Apakah siswa mengidap penyakit/penyakit bawaan'] ?? ''),
                'catatan_penyakit' => $this->nullValue($detail['Jika jawaban YA, penyakit apa yang harus diketahui Wali Kelas'] ?? null),
                'hobi' => $this->nullValue($detail['Hobi/kegiatan yang sering dilakukan'] ?? null),
                'prestasi' => $this->nullValue($detail['Prestasi atau kelebihan yang pernah diraih '] ?? null),
                'kelas_id' => $kelasId,
                'nomor_hp' => $this->nullValue($contact['nomor_hp'] ?? null),
                'nomor_hp_orang_tua' => $this->nullValue($contact['nomor_hp_orang_tua'] ?? null),
                'aktif' => 1,
            ];
            $this->db->table('siswa')->insert($data);
            if ($nis !== '13062') {
                $this->db->table('pengguna')->insert([
                    'nama_pengguna' => $nis, 'kata_sandi' => password_hash($nis, PASSWORD_DEFAULT),
                    'nama_lengkap' => $data['nama_lengkap'], 'peran' => 'siswa', 'nis' => $nis, 'aktif' => 1,
                ]);
            }
        }
    }

    private function seedGuruDanAkademik(int $kelasId, int $waliId): void
    {
        $source = file_get_contents(ROOTPATH . 'school-teachers.js');
        preg_match('/var schoolTeachers=(\[.*?\]);\s*function/s', (string) $source, $match);
        $teachers = json_decode($match[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
        if (preg_match('/schoolTeachers\.push\((\{.*?\})\);/s', (string) $source, $extra)) {
            $additional = json_decode('[' . $extra[1] . ']', true, 512, JSON_THROW_ON_ERROR);
            $teachers = array_merge($teachers, $additional);
        }
        foreach ($teachers as $teacher) {
            $this->db->table('guru')->insert([
                'nama_lengkap' => trim((string) $teacher['name']), 'nama_panggilan' => $this->nullValue($teacher['call'] ?? null),
                'jenis_kelamin' => in_array($teacher['gender'] ?? null, ['L', 'P'], true) ? $teacher['gender'] : null,
                'nip_nikki' => $this->nullValue($teacher['id'] ?? null), 'nrk' => $this->nullValue($teacher['nrk'] ?? null),
                'nuptk' => $this->nullValue($teacher['nuptk'] ?? null), 'nik' => $this->nullValue($teacher['nik'] ?? null),
                'tempat_tanggal_lahir' => $this->nullValue($teacher['birth'] ?? null), 'jabatan' => $this->nullValue($teacher['position'] ?? null),
                'mata_pelajaran' => $this->nullValue($teacher['subject'] ?? null), 'alamat' => $this->nullValue($teacher['address'] ?? null),
                'nomor_hp' => $this->nullValue($teacher['phone'] ?? null), 'email' => $this->nullValue($teacher['email'] ?? null), 'aktif' => 1,
            ]);
        }

        $subjects = ['PAI','Matematika','PKK','Kokurikuler','Bahasa Indonesia','Tata Cahaya','PPKN','Manajemen Produksi','Tata Kamera','Olahraga','Tata Artistik','Bahasa Inggris','Sejarah','Muatan Lokal','Naskah & Penyutradaraan'];
        foreach ($subjects as $subject) {
            $this->db->table('mata_pelajaran')->insert(['nama_mata_pelajaran' => $subject, 'aktif' => 1]);
        }
        $mapel = array_column($this->db->table('mata_pelajaran')->get()->getResultArray(), 'id', 'nama_mata_pelajaran');
        $guru = $this->db->table('guru')->get()->getResultArray();
        $guruMap = [];
        foreach ($guru as $item) {
            $guruMap[strtolower($item['nama_lengkap'])] = (int) $item['id'];
        }

        $schedule = [
            ['Senin','07:30','09:00','PKK','Nani Aminah'], ['Senin','09:00','10:45','Sejarah','Englena Nastaria Purba'],
            ['Senin','10:45','13:30','Tata Artistik','Ruby Eka Prawira'], ['Senin','13:30','15:00','Bahasa Inggris','Ika Inayah'],
            ['Selasa','06:30','09:00','Bahasa Indonesia','Erna Cahyani'], ['Selasa','09:00','10:45','Tata Cahaya','Wahyu Lukman Hakim'],
            ['Rabu','06:30','09:00','Tata Kamera','Putu Arya Ranesda'], ['Kamis','06:30','08:15','PAI','Lutfi Faridil Aftros'], ['Kamis','08:15','10:45','Matematika','Robert Henry Hutapea'], ['Kamis','10:45','13:30','PKK','Nani Aminah'], ['Kamis','13:30','15:00','Kokurikuler','Englena Nastaria Purba'],
            ['Jumat','07:30','09:30','Naskah & Penyutradaraan','Sumantoro Kasdhani'],
        ];
        foreach ($schedule as [$hari, $mulai, $selesai, $subject, $teacherName]) {
            $guruId = $this->findGuru($guruMap, $teacherName);
            if ($guruId === null) {
                $this->db->table('guru')->insert(['nama_lengkap' => $teacherName, 'mata_pelajaran' => $subject, 'aktif' => 1]);
                $guruId = (int) $this->db->insertID();
                $guruMap[strtolower($teacherName)] = $guruId;
            }
            $this->db->table('jadwal_kelas')->insert([
                'kelas_id' => $kelasId, 'mata_pelajaran_id' => $mapel[$subject], 'guru_id' => $guruId,
                'hari' => $hari, 'jam_mulai' => $mulai, 'jam_selesai' => $selesai,
            ]);
        }

        foreach ($mapel as $mapelId) {
            foreach (['UH' => 4, 'Tugas' => 4] as $jenis => $jumlah) {
                for ($i = 1; $i <= $jumlah; $i++) {
                    $this->db->table('komponen_nilai')->insert([
                        'kelas_id' => $kelasId, 'mata_pelajaran_id' => $mapelId,
                        'nama_komponen' => $jenis . ' ' . $i, 'jenis_komponen' => $jenis,
                        'urutan' => $jenis === 'UH' ? $i : 4 + $i, 'bobot' => 12.5, 'aktif' => 1,
                    ]);
                }
            }
        }

        $siswa = $this->db->table('siswa')->where('kelas_id', $kelasId)->get()->getResultArray();
        foreach ($siswa as $index => $student) {
            $status = in_array($student['nis'], ['12994','13047','13069','13086','13176','13198'], true) ? 'Sakit' : 'Hadir';
            $this->db->table('absensi')->insert([
                'siswa_id' => $student['id'], 'kelas_id' => $kelasId, 'tanggal' => date('Y-m-d'),
                'waktu_scan' => $status === 'Hadir' ? sprintf('06:%02d:00', 5 + ($index % 25)) : null,
                'status' => $status, 'keterangan' => $status === 'Sakit' ? 'Data awal izin sakit' : null, 'dicatat_oleh' => $waliId,
            ]);
        }

        $firstStudent = (int) $siswa[0]['id'];
        $firstGuru = (int) ($guru[0]['id'] ?? 1);
        $firstMapel = (int) reset($mapel);
        $this->db->table('jurnal_kelas')->insert(['kelas_id'=>$kelasId,'mata_pelajaran_id'=>$firstMapel,'guru_id'=>$firstGuru,'tanggal'=>date('Y-m-d'),'materi'=>'Kegiatan pembelajaran awal semester','catatan'=>'Data awal seeder','status'=>'Terbit','dibuat_oleh'=>$waliId]);
        $this->db->table('pesan_komunikasi')->insert(['siswa_id'=>$firstStudent,'kelas_id'=>$kelasId,'pengirim'=>'Wali Kelas','penerima'=>'Orang Tua Siswa','nomor_tujuan'=>'08118304400','isi_pesan'=>'Informasi kehadiran siswa telah disiapkan.','kategori'=>'Absensi','status_pengiriman'=>'Disiapkan','dibuat_oleh'=>$waliId]);
        $this->db->table('remedial_tugas')->insert(['kelas_id'=>$kelasId,'siswa_id'=>$firstStudent,'mata_pelajaran_id'=>$firstMapel,'judul'=>'Tugas pengganti','deskripsi'=>'Contoh tindak lanjut akademik.','batas_waktu'=>date('Y-m-d H:i:s', strtotime('+7 days')),'status'=>'Draft','dibuat_oleh'=>$waliId]);
        $this->db->table('notifikasi')->insert(['pengguna_id'=>$waliId,'judul'=>'Data awal siap','isi'=>'Data kelas 11PF1 berhasil dimuat.','jenis'=>'Sistem','sudah_dibaca'=>0]);
    }

    private function findGuru(array $map, string $name): ?int
    {
        foreach ($map as $fullName => $id) {
            if (str_contains($fullName, strtolower($name))) return $id;
        }
        return null;
    }

    private function nullValue($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' || $value === '-' ? null : $value;
    }

    private function numberValue($value): ?int
    {
        return preg_match('/\d+/', (string) $value, $match) ? (int) $match[0] : null;
    }

    private function yesValue($value): int
    {
        return str_starts_with(strtolower(trim((string) $value)), 'ya') ? 1 : 0;
    }

    private function validDate($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}

