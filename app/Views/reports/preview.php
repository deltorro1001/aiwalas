<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= csrf_hash() ?>">
  <base href="<?= esc(base_url(), 'attr') ?>">
  <title>Preview Laporan Wali Kelas - AIWalas</title>
  <link rel="stylesheet" href="<?= base_url('assets/styles.css?v=20261010-edit-student-scroll-v2') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/reports-preview.css?v=20261011-centered-capture') ?>">
</head>
<body class="report-preview-body">
  <main class="report-preview-page">
    <header class="report-preview-toolbar panel">
      <div><h1>Preview Laporan Wali Kelas</h1><p>Periksa laporan, pilih guru penerima, lalu kirim PDF melalui Gateway.</p></div>
      <div class="report-preview-actions">
        <a class="outline-btn" href="<?= base_url('/') ?>">Kembali</a>
        <label>Guru penerima<select id="reportTeacherRecipient" disabled><option value="">Memuat guru SMKN 19...</option></select></label>
        <label>Nomor WhatsApp<input id="reportTeacherPhone" readonly placeholder="Pilih guru penerima"></label>
        <button type="button" class="primary-btn" id="sendReportPdf" disabled>Kirim via Gateway</button>
      </div>
      <p id="reportPreviewStatus" class="stat-note" role="status">Menyiapkan preview PDF...</p>
      <p id="reportShareError" class="login-error" role="alert"></p>
    </header>
    <div class="report-preview-scroll"><div id="reportPreviewContent" class="report-page"></div></div>
    <iframe id="reportPdfPreview" title="Preview PDF Laporan Wali Kelas" hidden></iframe>
  </main>
  <script>
    window.reportPreviewConfig = <?= json_encode([
        'user' => $previewUser,
        'teachersUrl' => base_url('api/guru'),
        'csrfUrl' => base_url('api/auth/csrf'),
        'sendUrl' => base_url('api/laporan/kirim-pdf'),
        'uploadUrl' => base_url('api/laporan/pdf-upload'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="<?= base_url('assets/reports-preview.js?v=20261011-no-blank-page') ?>"></script>
</body>
</html>
