<?php
// Standalone controller regression checks. No database or real Gateway requests.
namespace App\Models {
    class PesanKomunikasiModel
    {
        public static array $updates = [];
        public function insert($data, $returnId) { return 1; }
        public function update($id, $data) { self::$updates[] = $data; }
    }
}
namespace App\Controllers\Api {
    class WhatsAppController
    {
        public $request;
        public $response;
        public bool $gatewayOk = true;
        public array $sent = [];
        protected function respond($data, $status = 200) { return ['status' => $status, 'body' => $data]; }
        protected function payload() { return $this->request->getJSON(true); }
        protected function currentUser() { return ['id' => 1, 'nama_lengkap' => 'Wali Kelas']; }
        protected function phone($value) {
            $phone = preg_replace('/\D+/', '', (string) $value);
            if (str_starts_with($phone, '0')) $phone = '62' . substr($phone, 1);
            return preg_match('/^62\d{8,15}$/', $phone) ? $phone : '';
        }
        protected function gatewayRequest($phone, $message, $type = '', $url = '') {
            $this->sent[] = compact('phone', 'message', 'type', 'url');
            return ['ok' => $this->gatewayOk, 'message' => 'Gateway test failure'];
        }
    }
}
namespace {
    $temporaryRoot = sys_get_temp_dir() . '/aiwalas-report-test-' . bin2hex(random_bytes(8)) . '/';
    define('WRITEPATH', $temporaryRoot);
    $paths = [];
    $configured = true;
    $teacherPhone = '081234567890';
    $testBaseUrl = 'https://aiwalas.test/';
    function env($key, $default = null) {
        global $configured;
        if ($key === 'WHATSAPP_GATEWAY_API_KEY') return $configured ? 'fake-test-key' : '';
        return $default;
    }
    function base_url($path) { global $testBaseUrl; return $testBaseUrl . $path; }
    class FakeQuery {
        private array $filters = [];
        public function __construct(private string $table) {}
        public function where(...$args) {
            $this->filters += is_array($args[0]) ? $args[0] : [$args[0] => $args[1]];
            return $this;
        }
        public function get() { return $this; }
        public function getRowArray() {
            global $teacherPhone;
            $expected = $this->table === 'guru' ? 2 : 1;
            if (($this->filters['id'] ?? 0) !== $expected) return null;
            return $this->table === 'guru' ? ['id' => 2, 'nama_lengkap' => 'Ruby Eka Prawira', 'nomor_hp' => $teacherPhone]
                : ['id' => 1, 'nama_kelas' => '11PF1'];
        }
    }
    class FakeDatabase { public function table($name) { return new FakeQuery($name); } }
    function db_connect() { return new FakeDatabase(); }
    class FakeUpload {
        public string $extension = 'pdf';
        public string $mime = 'application/pdf';
        public int $size = 100;
        public bool $valid = true;
        public function __construct(public string $temp) {}
        public function isValid() { return $this->valid; }
        public function hasMoved() { return false; }
        public function getSize() { return $this->size; }
        public function getTempName() { return $this->temp; }
        public function getClientExtension() { return $this->extension; }
        public function getMimeType() { return $this->mime; }
        public function move($directory, $name) {
            global $paths;
            $target = $directory . '/' . $name;
            copy($this->temp, $target);
            $paths[] = $target;
        }
    }
    class FakeRequest {
        public ?array $override = null;
        public int $teacherId = 2;
        public int $classId = 1;
        public function __construct(public $file) {}
        public function getPost($key) { return $key === 'guru_id' ? $this->teacherId : $this->classId; }
        public function getFile($key) { return $this->file; }
        public function getJSON($assoc) {
            if ($this->override !== null) return $this->override;
            $content = $this->file->mime === 'application/pdf' ? file_get_contents($this->file->temp) : 'not a PDF';
            if ($this->file->size > 10 * 1024 * 1024) $content = str_repeat('x', $this->file->size);
            return ['kelas_id' => $this->classId, 'guru_id' => $this->teacherId,
                'file_name' => 'report.' . $this->file->extension, 'pdf_base64' => base64_encode($content)];
        }
    }
    class FakeResponse {
        public int $status = 200;
        public ?string $file = null;
        public function setStatusCode($status) { $this->status = $status; return $this; }
        public function setBody($body) { return $this; }
        public function download($path, $data) { $this->file = $path; return $this; }
        public function setFileName($name) { return $this; }
        public function setHeader($name, $value) { return $this; }
    }
    require __DIR__ . '/../app/Controllers/Api/LaporanFileController.php';
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    mkdir($temporaryRoot, 0770, true);
    $fixture = $temporaryRoot . 'fixture';
    $paths[] = $fixture;
    // Only a signature fixture, not a generated report or a deliverable PDF.
    file_put_contents($fixture, "%PDF-1.7\nTest fixture");
    try {
        $controller = new \App\Controllers\Api\LaporanFileController();
        $upload = new FakeUpload($fixture);
        $controller->request = new FakeRequest($upload);
        $controller->response = new FakeResponse();
        $result = $controller->sendReport();
        check($result['status'] === 200, 'Valid PDF rejected');
        check($controller->sent[0]['type'] === 'document', 'Wrong Gateway media type');
        check($controller->sent[0]['phone'] === '6281234567890', 'Wrong teacher phone');
        $token = basename($controller->sent[0]['url']);
        check((bool) preg_match('/^[a-f0-9]{64}$/', $token), 'Invalid access token');
        check($controller->download($token)->file !== null, 'Gateway cannot download PDF');
        touch($controller->response->file, time() - 86401);
        clearstatcache();
        $controller->response = new FakeResponse();
        check($controller->download($token)->status === 404, 'Expired PDF accessible');
        $controller->response = new FakeResponse();
        check($controller->download('../fixture')->status === 404, 'Path traversal accepted');

        $controller->request->teacherId = 999;
        check($controller->sendReport()['status'] === 422, 'Unknown teacher accepted');
        $controller->request->teacherId = 2;
        $controller->request->classId = 999;
        check($controller->sendReport()['status'] === 422, 'Unknown class accepted');
        $controller->request->classId = 1;

        $upload->extension = 'txt';
        check($controller->sendReport()['status'] === 422, 'Non-PDF extension accepted');
        $upload->extension = 'pdf';
        $upload->mime = 'text/plain';
        check($controller->sendReport()['status'] === 422, 'Non-PDF MIME accepted');
        $upload->mime = 'application/pdf';
        file_put_contents($fixture, 'not a PDF');
        check($controller->sendReport()['status'] === 422, 'Fake PDF accepted');
        file_put_contents($fixture, "%PDF-1.7\nTest fixture");
        $upload->size = 11 * 1024 * 1024;
        check($controller->sendReport()['status'] === 422, 'Oversize PDF accepted');
        $upload->size = 100;
        $teacherPhone = '';
        check($controller->sendReport()['status'] === 422, 'Missing phone accepted');
        $teacherPhone = '081234567890';
        $configured = false;
        check($controller->sendReport()['status'] === 503, 'Unconfigured Gateway accepted');
        $configured = true;
        $controller->gatewayOk = false;
        check($controller->sendReport()['status'] === 502, 'Gateway failure reported as success');
        check(end(\App\Models\PesanKomunikasiModel::$updates)['status_pengiriman'] === 'Gagal', 'Failure not recorded');
        $controller->gatewayOk = true;
        $largePdf = "%PDF-1.7\n" . str_repeat('x', 3 * 1024 * 1024);
        $controller->request->override = ['size' => strlen($largePdf), 'file_name' => 'report.pdf', 'kelas_id' => 1, 'guru_id' => 2];
        $started = $controller->startUpload();
        check($started['status'] === 200, 'Chunk initialization failed');
        $token = $started['body']['token'];
        $controller->request->override = ['index' => 1, 'pdf_base64' => base64_encode(substr($largePdf, 0, 262144))];
        check($controller->uploadChunk($token)['status'] === 409, 'Out-of-order chunk accepted');
        $controller->request->override = ['kelas_id' => 1, 'guru_id' => 2, 'upload_token' => $token];
        check($controller->sendReport()['status'] === 422, 'Incomplete PDF sent');
        for ($offset = 0, $index = 0; $offset < strlen($largePdf); $offset += 262144, $index++) {
            $controller->request->override = ['index' => $index, 'pdf_base64' => base64_encode(substr($largePdf, $offset, 262144))];
            check(strlen(json_encode($controller->request->override)) < 400000, 'Chunk request too large');
            check($controller->uploadChunk($token)['status'] === 200, 'Valid chunk rejected');
        }
        $controller->request->override = ['kelas_id' => 1, 'guru_id' => 2, 'upload_token' => $token];
        check($controller->sendReport()['status'] === 200, 'Assembled PDF rejected');
        check($controller->sendReport()['status'] === 422, 'Consumed upload sent twice');
        $testBaseUrl = 'http://localhost:8080/';
        $controller->request->override = ['size' => 100, 'file_name' => 'report.pdf', 'kelas_id' => 1, 'guru_id' => 2];
        $sentBefore = count($controller->sent);
        $blockedLocal = $controller->startUpload();
        check($blockedLocal['status'] === 422 && str_contains($blockedLocal['body']['message'], 'localhost'), 'Local media URL not explained');
        check(count($controller->sent) === $sentBefore, 'Gateway called with local media URL');
        echo "PASS: 3 MB PDF assembled using requests below 400 KB; order, incomplete upload and duplicate-finalize checks.\n";
        echo "PASS: PDF validation, recipient phone, document payload, expiring download, traversal protection, Gateway failure and history.\n";
    } finally {
        foreach (glob($temporaryRoot . 'uploads/laporan-pdf/*') ?: [] as $path) $paths[] = $path;
        foreach ($paths as $path) if (is_file($path)) unlink($path);
        if (is_dir($temporaryRoot . 'uploads/laporan-pdf')) rmdir($temporaryRoot . 'uploads/laporan-pdf');
        if (is_dir($temporaryRoot . 'uploads')) rmdir($temporaryRoot . 'uploads');
        rmdir($temporaryRoot);
    }
}
