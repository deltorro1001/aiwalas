<?php
namespace App\Controllers\Api;

class MaintenanceController extends BaseApiController
{
    public function upload()
    {
        $file = $this->request->getFile('patch');
        if (!$file || !$file->isValid() || strtolower($file->getExtension()) !== 'zip') {
            return $this->respond(['ok' => false, 'message' => 'Pilih file patch ZIP yang valid.'], 422);
        }
        if ($file->getSize() > 52428800) {
            return $this->respond(['ok' => false, 'message' => 'Ukuran patch maksimal 50 MB.'], 422);
        }
        $dir = WRITEPATH . 'patches';
        if (!is_dir($dir)) mkdir($dir, 0750, true);
        $name = 'patch-' . date('Ymd-His') . '.zip';
        $file->move($dir, $name);
        return $this->respond(['ok' => true, 'name' => $name, 'size' => filesize($dir . DIRECTORY_SEPARATOR . $name)]);
    }

    public function apply()
    {
        $payload = $this->request->getJSON(true) ?? [];
        $name = basename((string) ($payload['name'] ?? ''));
        $path = WRITEPATH . 'patches' . DIRECTORY_SEPARATOR . $name;
        if (!$name || !is_file($path) || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            return $this->respond(['ok' => false, 'message' => 'Patch tidak ditemukan.'], 404);
        }
        if (!class_exists('ZipArchive')) {
            return $this->respond(['ok' => false, 'message' => 'Ekstensi ZIP PHP belum aktif.'], 500);
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return $this->respond(['ok' => false, 'message' => 'ZIP tidak dapat dibaca.'], 422);
        }

        $allowedRoots = ['app/', 'public/', 'composer.json', 'composer.lock', 'PATCH-MANIFEST.txt'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            // ZIP sering menyimpan entry folder. Folder bukan file yang perlu divalidasi.
            if ($entry === '' || substr($entry, -1) === '/') continue;
            $isSafe = !str_contains($entry, '..')
                && !str_starts_with($entry, '/')
                && !preg_match('/^[A-Za-z]:\\//', $entry)
                && array_filter($allowedRoots, static fn ($root) => $entry === $root || str_starts_with($entry, $root));
            if (!$isSafe) {
                $zip->close();
                return $this->respond(['ok' => false, 'message' => 'Isi patch tidak diizinkan: ' . $entry], 422);
            }
        }

        $tmp = WRITEPATH . 'patches' . DIRECTORY_SEPARATOR . 'extract-' . bin2hex(random_bytes(5));
        mkdir($tmp, 0750, true);
        if (!$zip->extractTo($tmp)) {
            $zip->close();
            $this->removeTree($tmp);
            return $this->respond(['ok' => false, 'message' => 'Isi ZIP gagal diekstrak.'], 422);
        }
        $zip->close();

        $backup = WRITEPATH . 'patches' . DIRECTORY_SEPARATOR . 'backup-' . date('Ymd-His');
        mkdir($backup, 0750, true);
        foreach (['app', 'public', 'composer.json', 'composer.lock'] as $item) {
            $source = ROOTPATH . $item;
            if (is_dir($source)) $this->copyTree($source, $backup . DIRECTORY_SEPARATOR . $item);
            elseif (is_file($source)) copy($source, $backup . DIRECTORY_SEPARATOR . $item);
        }
        foreach (['app', 'public', 'composer.json', 'composer.lock'] as $item) {
            $source = $tmp . DIRECTORY_SEPARATOR . $item;
            if (is_dir($source)) $this->copyTree($source, ROOTPATH . $item);
            elseif (is_file($source)) copy($source, ROOTPATH . $item);
        }
        $this->removeTree($tmp);
        return $this->respond(['ok' => true, 'message' => 'Patch diterapkan. Backup tersimpan: ' . basename($backup)]);
    }

    private function copyTree($source, $target)
    {
        if (!is_dir($target)) mkdir($target, 0750, true);
        foreach (scandir($source) as $item) {
            if ($item === '.' || $item === '..') continue;
            $from = $source . DIRECTORY_SEPARATOR . $item;
            $to = $target . DIRECTORY_SEPARATOR . $item;
            if (is_dir($from)) $this->copyTree($from, $to); else copy($from, $to);
        }
    }

    private function removeTree($dir)
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) $this->removeTree($path); else unlink($path);
        }
        rmdir($dir);
    }
}
