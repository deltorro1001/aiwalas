<?php
namespace App\Libraries;

final class NotificationClassScope
{
    public static function classIds(array $user, ?int $guestClassId = null): array
    {
        $db = db_connect();
        $role = (string) ($user['peran'] ?? '');
        $ids = [];

        if ($role === 'wali_kelas') {
            $ids = $db->table('kelas')->select('id')->where('wali_kelas_id', (int) ($user['id'] ?? 0))->get()->getResultArray();
            $ids = array_column($ids, 'id');
        } elseif ($role === 'guru') {
            foreach (TeacherAssignments::rows(null, (int) ($user['guru_id'] ?? 0)) as $assignment) $ids[] = $assignment['kelas_id'];
        } elseif ($role === 'guest') {
            if ($guestClassId) {
                $valid = $db->table('kelas k')->join('tahun_ajaran t', 't.id=k.tahun_ajaran_id')->where(['k.id' => $guestClassId, 't.aktif' => 1])->countAllResults();
                if ($valid) $ids[] = $guestClassId;
            }
        } elseif ($role === 'orang_tua') {
            $student = $db->table('siswa')->select('kelas_id')->where(['id' => (int) ($user['anak_id'] ?? 0), 'aktif' => 1])->get()->getRowArray();
            if ($student) $ids[] = $student['kelas_id'];
        } elseif (in_array($role, ['siswa', 'sekretaris'], true)) {
            $student = $db->table('siswa')->select('kelas_id')->where(['nis' => (string) ($user['nis'] ?? ''), 'aktif' => 1])->get()->getRowArray();
            if ($student) $ids[] = $student['kelas_id'];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
