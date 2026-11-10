<?php
namespace App\Controllers\Api;

use App\Models\NotifikasiModel;
use App\Libraries\NotificationClassScope;

class NotifikasiController extends BaseApiController
{
    public function index()
    {
        $user = $this->currentUser();
        $date = $this->request->getGet('tanggal');
        if ($date !== null && !is_string($date)) return $this->respond(['ok' => false, 'message' => 'Tanggal tidak valid.'], 422);

        $classParam = $this->request->getGet('kelas_id');
        if ($classParam !== null && (!is_string($classParam) || !preg_match('/^[1-9][0-9]*$/D', $classParam))) return $this->respond(['ok' => false, 'message' => 'Kelas tidak valid.'], 422);
        $classIds = NotificationClassScope::classIds($user, $classParam === null ? null : (int) $classParam);
        if ($classParam !== null && !in_array((int) $classParam, $classIds, true)) return $this->respond(['ok' => false, 'message' => 'Kelas tidak tersedia.'], 404);
        if (($user['peran'] ?? '') === 'guest' && $classParam === null) return $this->respond(['ok' => false, 'message' => 'Pilih kelas untuk melihat notifikasi.'], 422);

        $query = db_connect()->table('notifikasi');
        $query->groupStart();
        if (($user['peran'] ?? '') !== 'guest') $query->where('pengguna_id', (int) $user['id']);
        if ($classIds) $query->orWhereIn('kelas_id', $classIds);
        $query->groupEnd();
        if ($date !== null && $date !== '') {
            try { [$start, $end] = \App\Libraries\NotificationDate::range($date); }
            catch (\InvalidArgumentException $error) { return $this->respond(['ok' => false, 'message' => $error->getMessage()], 422); }
            $query->where('dibuat_pada >=', $start)->where('dibuat_pada <', $end);
        }

        $page = min(1000000, max(1, (int) $this->request->getGet('page')));
        $limit = 30;
        $total = (clone $query)->countAllResults();
        $rows = $query->orderBy('dibuat_pada', 'DESC')->orderBy('id', 'DESC')->limit($limit, ($page - 1) * $limit)->get()->getResultArray();
        $this->response->setHeader('Cache-Control', 'private, no-store');
        return $this->respond(['data' => $rows, 'tanggal' => $date ?: null, 'today' => \App\Libraries\NotificationDate::today(), 'page' => $page, 'per_page' => $limit, 'total' => $total, 'has_more' => $page * $limit < $total]);
    }

    public function create()
    {
        $p = $this->payload();
        $errors = $this->validatePayload($p, ['pengguna_id' => 'required|is_natural_no_zero', 'judul' => 'required|max_length[180]', 'isi' => 'required|max_length[5000]', 'jenis' => 'required|max_length[80]']);
        if ($errors) return $this->respond(['ok' => false, 'errors' => $errors], 422);
        $model = new NotifikasiModel();
        $id = $model->insert(['pengguna_id' => (int) $p['pengguna_id'], 'judul' => trim($p['judul']), 'isi' => trim($p['isi']), 'jenis' => trim($p['jenis']), 'sudah_dibaca' => 0], true);
        return $this->respond(['ok' => true, 'data' => $model->find($id)], 201);
    }

    public function read(int $id)
    {
        if (($this->currentUser()['peran'] ?? '') !== 'wali_kelas') return $this->respond(['ok' => false, 'message' => 'Notifikasi hanya dapat ditandai oleh Wali Kelas.'], 403);
        $model = new NotifikasiModel();
        $row = $model->where(['id' => $id, 'pengguna_id' => $this->currentUser()['id']])->first();
        if (!$row) return $this->respond(['ok' => false, 'message' => 'Notifikasi tidak ditemukan.'], 404);
        $model->update($id, ['sudah_dibaca' => 1]);
        return $this->respond(['ok' => true]);
    }

    public function readAll()
    {
        if (($this->currentUser()['peran'] ?? '') !== 'wali_kelas') return $this->respond(['ok' => false, 'message' => 'Notifikasi hanya dapat ditandai oleh Wali Kelas.'], 403);
        (new NotifikasiModel())->where('pengguna_id', $this->currentUser()['id'])->set(['sudah_dibaca' => 1])->update();
        return $this->respond(['ok' => true]);
    }
}
