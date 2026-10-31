<?php

namespace App\Controllers\Api;

use App\Models\PesanKomunikasiModel;

class LaporanFileController extends WhatsAppController
{
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const LIFETIME = 86400;
    private const CHUNK_BYTES = 256 * 1024;

    private function mediaUrlIsLocal(): bool
    {
        $host = strtolower((string) parse_url(base_url('laporan/pdf/'), PHP_URL_HOST));
        return in_array($host, ['localhost', '::1', '[::1]', '0.0.0.0'], true) || str_starts_with($host, '127.');
    }

    public function startUpload()
    {
        $payload = $this->payload();
        $size = (int) ($payload['size'] ?? 0);
        $name = $payload['file_name'] ?? '';
        if ($size < 1 || $size > self::MAX_BYTES || !is_string($name) || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') {
            return $this->respond(['ok' => false, 'message' => 'PDF tidak valid atau melebihi batas 10 MB.'], 422);
        }
        if ($this->mediaUrlIsLocal()) {
            return $this->respond(['ok' => false, 'message' => 'Gateway tidak dapat mengunduh PDF dari URL localhost. Pengiriman dokumen perlu URL aplikasi publik yang dapat diakses Gateway.'], 422);
        }
        $token = bin2hex(random_bytes(32));
        $directory = WRITEPATH . 'uploads/laporan-pdf';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            return $this->respond(['ok' => false, 'message' => 'Penyimpanan PDF belum tersedia.'], 500);
        }
        $meta = ['user' => (int) $this->currentUser()['id'], 'size' => $size, 'name' => $name,
            'kelas_id' => (int) ($payload['kelas_id'] ?? 0), 'guru_id' => (int) ($payload['guru_id'] ?? 0),
            'next' => 0, 'created' => time()];
        file_put_contents($directory . '/' . $token . '.json', json_encode($meta), LOCK_EX);
        file_put_contents($directory . '/' . $token . '.part', '', LOCK_EX);
        return $this->respond(['ok' => true, 'token' => $token, 'chunk_bytes' => self::CHUNK_BYTES]);
    }

    public function uploadChunk(string $token)
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) return $this->respond(['ok' => false, 'message' => 'Upload PDF tidak ditemukan.'], 404);
        $path = WRITEPATH . 'uploads/laporan-pdf/' . $token;
        if (!is_file($path . '.json')) return $this->respond(['ok' => false, 'message' => 'Upload PDF tidak ditemukan.'], 404);
        $handle = fopen($path . '.json', 'r+');
        if (!$handle || !flock($handle, LOCK_EX)) return $this->respond(['ok' => false, 'message' => 'Upload sedang digunakan. Coba lagi.'], 409);
        try {
            $meta = json_decode(stream_get_contents($handle), true);
            if (!$meta || $meta['user'] !== (int) $this->currentUser()['id'] || $meta['created'] < time() - 1800) {
                return $this->respond(['ok' => false, 'message' => 'Upload PDF tidak tersedia atau sudah kedaluwarsa.'], 404);
            }
            $payload = $this->payload();
            $encoded = $payload['pdf_base64'] ?? '';
            $index = $payload['index'] ?? null;
            if (!is_int($index) || $index !== $meta['next']) return $this->respond(['ok' => false, 'message' => 'Urutan upload PDF tidak valid.'], 409);
            if (!is_string($encoded) || strlen($encoded) > 4 * (int) ceil(self::CHUNK_BYTES / 3)) {
                return $this->respond(['ok' => false, 'message' => 'Potongan PDF terlalu besar.'], 422);
            }
            $bytes = base64_decode($encoded, true);
            clearstatcache(true, $path . '.part');
            $received = filesize($path . '.part');
            if ($bytes === false || $bytes === '' || strlen($bytes) !== min(self::CHUNK_BYTES, $meta['size'] - $received)) {
                return $this->respond(['ok' => false, 'message' => 'Potongan PDF tidak valid.'], 422);
            }
            if (file_put_contents($path . '.part', $bytes, FILE_APPEND | LOCK_EX) !== strlen($bytes)) {
                return $this->respond(['ok' => false, 'message' => 'Potongan PDF gagal disimpan.'], 500);
            }
            $meta['next']++;
            rewind($handle); ftruncate($handle, 0); fwrite($handle, json_encode($meta)); fflush($handle);
            return $this->respond(['ok' => true, 'received' => $received + strlen($bytes)]);
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    public function preview()
    {
        $user = $this->currentUser();
        return view('reports/preview', ['previewUser' => (string) (($user['id'] ?? 0) ?: ($user['nama_pengguna'] ?? ''))]);
    }

    public function sendReport()
    {
        $payload = $this->payload();
        $classId = (int) ($payload['kelas_id'] ?? 0);
        $teacherId = (int) ($payload['guru_id'] ?? 0);
        $db = db_connect();
        $class = $db->table('kelas')->where('id', $classId)->get()->getRowArray();
        $teacher = $db->table('guru')->where(['id' => $teacherId, 'aktif' => 1])->get()->getRowArray();
        if (!$class || !$teacher) {
            return $this->respond(['ok' => false, 'message' => 'Pilih kelas dan guru penerima yang valid.'], 422);
        }
        // Resolve the destination on the server, never from an editable phone field.
        $phone = $this->phone($teacher['nomor_hp'] ?? '');
        if (!$phone) {
            return $this->respond(['ok' => false, 'message' => 'Nomor WhatsApp guru belum tersedia atau tidak valid.'], 422);
        }
        // Read the assembled PDF; each upload request stays below PHP's temporary-stream threshold.
        $uploadToken = $payload['upload_token'] ?? null;
        $uploadPath = null;
        if ($uploadToken !== null) {
            if (!is_string($uploadToken) || !preg_match('/\A[a-f0-9]{64}\z/', $uploadToken)) {
                return $this->respond(['ok' => false, 'message' => 'Upload PDF tidak valid.'], 422);
            }
            $uploadPath = WRITEPATH . 'uploads/laporan-pdf/' . $uploadToken;
            $meta = is_file($uploadPath . '.json') ? json_decode(file_get_contents($uploadPath . '.json'), true) : null;
            clearstatcache(true, $uploadPath . '.part');
            if (!$meta || $meta['user'] !== (int) $this->currentUser()['id'] || $meta['kelas_id'] !== $classId
                || $meta['guru_id'] !== $teacherId || $meta['created'] < time() - 1800
                || !is_file($uploadPath . '.part') || filesize($uploadPath . '.part') !== $meta['size']) {
                return $this->respond(['ok' => false, 'message' => 'Upload PDF belum lengkap atau sudah kedaluwarsa.'], 422);
            }
            $payload['pdf_base64'] = base64_encode(file_get_contents($uploadPath . '.part'));
            $payload['file_name'] = $meta['name'];
        }
        $encoded = $payload['pdf_base64'] ?? null;
        $name = $payload['file_name'] ?? '';
        if (!is_string($encoded) || !is_string($name) || $encoded === '') {
            return $this->respond(['ok' => false, 'message' => 'Pilih file PDF laporan yang valid.'], 422);
        }
        if (strlen($encoded) > 4 * (int) ceil(self::MAX_BYTES / 3)) {
            return $this->respond(['ok' => false, 'message' => 'Ukuran PDF maksimal 10 MB.'], 422);
        }
        $pdf = base64_decode($encoded, true);
        if ($pdf === false || $pdf === '' || strlen($pdf) > self::MAX_BYTES) {
            return $this->respond(['ok' => false, 'message' => 'Data PDF tidak valid atau melebihi batas 10 MB.'], 422);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($pdf);
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf' || $mime !== 'application/pdf' || substr($pdf, 0, 5) !== '%PDF-') {
            return $this->respond(['ok' => false, 'message' => 'File harus berupa dokumen PDF, bukan hanya file dengan ekstensi .pdf.'], 422);
        }
        if (!$this->gatewayConfigured()) {
            return $this->respond(['ok' => false, 'message' => 'Gateway WhatsApp belum dikonfigurasi di server.'], 503);
        }
        if ($this->mediaUrlIsLocal()) {
            return $this->respond(['ok' => false, 'message' => 'Gateway tidak dapat mengunduh PDF dari URL localhost. Pengiriman dokumen perlu URL aplikasi publik yang dapat diakses Gateway.'], 422);
        }

        $token = bin2hex(random_bytes(32));
        $directory = WRITEPATH . 'uploads/laporan-pdf';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            return $this->respond(['ok' => false, 'message' => 'Penyimpanan PDF belum tersedia.'], 500);
        }
        if (file_put_contents($directory . '/' . $token . '.pdf', $pdf, LOCK_EX) !== strlen($pdf)) {
            return $this->respond(['ok' => false, 'message' => 'PDF gagal disimpan di server.'], 500);
        }
        if ($uploadPath !== null) { unlink($uploadPath . '.part'); unlink($uploadPath . '.json'); }
        $url = base_url('laporan/pdf/' . $token);
        $user = $this->currentUser();
        $message = 'Bapak/Ibu ' . $teacher['nama_lengkap'] . ', berikut file PDF Laporan Wali Kelas '
            . $class['nama_kelas'] . '. Terima kasih.';
        $model = new PesanKomunikasiModel();
        $id = $model->insert([
            'kelas_id' => $classId,
            'pengirim' => $user['nama_lengkap'] ?? 'AIWalas',
            'penerima' => $teacher['nama_lengkap'],
            'nomor_tujuan' => $phone,
            'isi_pesan' => $message,
            'kategori' => 'Laporan PDF',
            'status_pengiriman' => 'Disiapkan',
            'dibuat_oleh' => (int) ($user['id'] ?? 0),
        ], true);
        $result = $this->gatewayRequest($phone, $message, 'document', $url);
        $ok = $result['ok'];
        $model->update($id, [
            'status_pengiriman' => $ok ? 'Terkirim' : (($result['outcome']??'unknown')==='failed'?'Gagal':'Disiapkan'),
            'dikirim_pada' => $ok ? date('Y-m-d H:i:s') : null,
        ]);
        return $this->respond([
            'ok' => $ok,
            'message' => $ok ? 'Laporan PDF dikirim melalui Gateway kepada ' . $teacher['nama_lengkap'] . '.'
                : ($result['message'] ?: 'Gateway gagal mengirim laporan PDF. Silakan coba lagi.'),
            'data' => ['id' => $id, 'status' => $ok ? 'Terkirim' : 'Pending'],
        ], $ok ? 200 : 502);
    }

    public function download(string $token)
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return $this->response->setStatusCode(404)->setBody('PDF tidak ditemukan.');
        }
        $path = WRITEPATH . 'uploads/laporan-pdf/' . $token . '.pdf';
        if (!is_file($path) || filemtime($path) < time() - self::LIFETIME) {
            return $this->response->setStatusCode(404)->setBody('PDF tidak ditemukan atau tautan kedaluwarsa.');
        }
        return $this->response->download($path, null)->setFileName('Laporan-Wali-Kelas.pdf')
            ->setHeader('Cache-Control', 'private, no-store');
    }
}
