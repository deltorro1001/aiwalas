<?php
namespace App\Controllers\Api;

class SiswaDashboardController extends BaseApiController
{
    public function index()
    {
        $user = $this->currentUser();
        $nis = trim((string) ($user['nis'] ?? ''));
        $db = db_connect();
        $student = $db->table('siswa')->where(['nis' => $nis, 'aktif' => 1])->get()->getRowArray();
        if (!$student) return $this->respond(['ok' => false, 'message' => 'Profil siswa tidak ditemukan.'], 404);
        $semester=$this->request->getGet('semester')?:'Ganjil';
        if(!in_array($semester,['Ganjil','Genap'],true))return $this->respond(['ok'=>false],422);
        $calendar=\App\Libraries\SchoolCalendar::info(new \DateTimeImmutable('now'));
        $today = $calendar['tanggal'];
        $missingStatus=$calendar['hari_libur']?'Libur':'Menunggu keterangan';
        $classId = (int) $student['kelas_id'];
        // Class overview contains only names/statuses, scoped to the authenticated student's class.
        $class = $db->table('kelas')->select('nama_kelas')->where('id', $classId)->get()->getRowArray();
        $classRows = $db->table('siswa s')->select("s.nama_lengkap,COALESCE(a.status,".$db->escape($missingStatus).") AS status")
            ->join('absensi a', "a.siswa_id=s.id AND a.tanggal=" . $db->escape($today) . " AND a.semester=" . $db->escape($semester), 'left')
            ->where(['s.kelas_id' => $classId, 's.aktif' => 1])->orderBy('s.nama_lengkap')->get()->getResultArray();
        $classSummary = ['nama_kelas' => $class['nama_kelas'] ?? '', 'status' => [], 'siswa_tidak_hadir' => [], 'menunggu_keterangan' => [], 'tren_absensi' => []];
        foreach ($classRows as $row) {
            $classSummary['status'][$row['status']] = ($classSummary['status'][$row['status']] ?? 0) + 1;
            if (in_array($row['status'], ['Sakit', 'Izin', 'Alpha'], true)) $classSummary['siswa_tidak_hadir'][] = $row;
            if ($row['status'] === 'Menunggu keterangan') $classSummary['menunggu_keterangan'][] = $row;
        }
        $weekStart = (new \DateTimeImmutable($today))->modify('monday this week');
        $weekRows = $db->table('absensi a')->select('a.tanggal,a.status,COUNT(*) AS jumlah')
            ->join('siswa s', 's.id=a.siswa_id')->where(['s.kelas_id' => $classId, 's.aktif' => 1, 'a.semester' => $semester])
            ->where('a.tanggal >=', $weekStart->format('Y-m-d'))->where('a.tanggal <=', $today)
            ->whereIn('a.status', ['Sakit', 'Izin', 'Terlambat'])->groupBy('a.tanggal,a.status')->get()->getResultArray();
        for ($i = 0; $i < 5; $i++) {
            $date = $weekStart->modify('+' . $i . ' days')->format('Y-m-d');
            $values = ['tanggal' => $date, 'Sakit' => 0, 'Izin' => 0, 'Terlambat' => 0];
            foreach ($weekRows as $row) if ($row['tanggal'] === $date) $values[$row['status']] = (int) $row['jumlah'];
            $classSummary['tren_absensi'][] = $values;
        }
        $attendance = $db->table('absensi')->where(['siswa_id' => $student['id'], 'tanggal' => $today])->get()->getRowArray();
        $counts = $db->table('absensi')->select('status, COUNT(*) jumlah')->where('siswa_id', $student['id'])->where('tanggal >=', '2026-07-13')->where('tanggal <=', $today)->groupBy('status')->get()->getResultArray();
        $summary = ['Sakit' => 0, 'Izin' => 0, 'Alpha' => 0];
        foreach ($counts as $row) if (array_key_exists($row['status'], $summary)) $summary[$row['status']] = (int) $row['jumlah'];
        $lateRows = $db->table('absensi')->select('tanggal,keterangan')->where(['siswa_id' => $student['id'], 'status' => 'Terlambat'])->where('tanggal >=', '2026-07-13')->where('tanggal <=', $today)->orderBy('tanggal', 'DESC')->get()->getResultArray();
        $day = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'][date('l')] ?? 'Senin';
        $schedule = $db->table('jadwal_kelas')->select('jadwal_kelas.jam_mulai,jadwal_kelas.jam_selesai,jadwal_kelas.keterangan,mata_pelajaran.nama_mata_pelajaran,guru.nama_lengkap')->join('mata_pelajaran', 'mata_pelajaran.id=jadwal_kelas.mata_pelajaran_id')->join('guru', 'guru.id=jadwal_kelas.guru_id', 'left')->where(['jadwal_kelas.kelas_id' => $classId, 'jadwal_kelas.hari' => $day])->orderBy('jadwal_kelas.jam_mulai')->get()->getResultArray();
        $scores = $db->table('nilai_siswa')->select('mata_pelajaran.nama_mata_pelajaran,komponen_nilai.nama_komponen,komponen_nilai.jenis_komponen,nilai_siswa.nilai')->join('komponen_nilai', 'komponen_nilai.id=nilai_siswa.komponen_nilai_id')->join('mata_pelajaran', 'mata_pelajaran.id=komponen_nilai.mata_pelajaran_id')->where('nilai_siswa.semester',$semester)->where('nilai_siswa.siswa_id', $student['id'])->orderBy('mata_pelajaran.nama_mata_pelajaran')->orderBy('komponen_nilai.urutan')->get()->getResultArray();
        $classScores = $db->table('nilai_siswa')->select('mata_pelajaran.nama_mata_pelajaran,ROUND(AVG(nilai_siswa.nilai),0) rata_rata_kelas')->join('komponen_nilai', 'komponen_nilai.id=nilai_siswa.komponen_nilai_id')->join('mata_pelajaran', 'mata_pelajaran.id=komponen_nilai.mata_pelajaran_id')->where('nilai_siswa.semester',$semester)->where('komponen_nilai.kelas_id', $classId)->groupBy('mata_pelajaran.id,mata_pelajaran.nama_mata_pelajaran')->orderBy('mata_pelajaran.nama_mata_pelajaran')->get()->getResultArray();
        $tasks = $db->table('remedial_tugas')->select('remedial_tugas.judul,remedial_tugas.deskripsi,remedial_tugas.batas_waktu,remedial_tugas.status,mata_pelajaran.nama_mata_pelajaran')->join('mata_pelajaran', 'mata_pelajaran.id=remedial_tugas.mata_pelajaran_id')->groupStart()->where('remedial_tugas.siswa_id', $student['id'])->orWhere(['remedial_tugas.siswa_id' => null, 'remedial_tugas.kelas_id' => $classId])->groupEnd()->whereNotIn('remedial_tugas.status', ['Selesai', 'Dibatalkan'])->orderBy('remedial_tugas.batas_waktu')->limit(10)->get()->getResultArray();
        $discipline = (new \App\Libraries\DisciplineRecords())->forClass($classId, (int) $student['id']);
        $whatsappNotifications = (new \App\Libraries\StudentWhatsAppNotifications())->latest($student);
        $this->response->setHeader('Cache-Control', 'no-store');
        return $this->respond(['ok' => true, 'data' => ['student' => ['nama' => $student['nama_lengkap'], 'nis' => $student['nis']], 'ringkasan_kelas' => $classSummary, 'scan' => ['tanggal' => $today, 'waktu' => $attendance['waktu_scan'] ?? null, 'status' => $attendance['status'] ?? ($calendar['hari_libur']?'Libur':'Belum scan')], 'keterlambatan' => ['sejak' => '2026-07-13', 'jumlah' => count($lateRows), 'riwayat' => $lateRows], 'absensi' => $summary, 'kedisiplinan' => ['catatan' => $discipline[0]['catatan'] ?? []], 'notifikasi_whatsapp' => $whatsappNotifications, 'kalender'=>$calendar,'jadwal_hari_ini' => $schedule, 'capaian_nilai' => $scores, 'capaian_mapel' => $classScores, 'ulangan_harian' => [], 'tugas_belum_selesai' => $tasks]]);
    }
}
