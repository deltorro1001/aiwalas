<!doctype html>
<html lang='id'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1'>
  <meta name='csrf-token' content='<?= csrf_hash() ?>'>
  <title>AIWalas</title>
  <link rel='stylesheet' href='<?= base_url('assets/styles.css?v=20260920-maintenance') ?>'>
</head>
<body>
<?= $this->renderSection('content') ?>
<script src='<?= base_url('assets/student-details.js?v=20260911') ?>'></script>
<script src='<?= base_url('assets/app.js?v=20260916-schedule-form-title') ?>'></script>
<script src='<?= base_url('assets/whatsapp-notifications.js?v=20260918-communication-compose') ?>'></script>
<script src='<?= base_url('assets/students-page.js?v=20260918-import-excel') ?>'></script>
<script src='<?= base_url('assets/school-teachers.js?v=20260913-teachers-export6') ?>'></script>
<script src='<?= base_url('assets/school-teachers-mapel.js?v=20260918-mapel-kelas-imported') ?>'></script>
<script src='<?= base_url('assets/api-ui.js?v=20260913-journal') ?>'></script>
<script src='https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js'></script>
<script src='<?= base_url('assets/ledger-import.js?v=20260914-report-data') ?>'></script>
<script src='<?= base_url('assets/ledger-page.js?v=20260918-name-left') ?>'></script>
<script src='<?= base_url('assets/hud-chart.js?v=20260914-trends4') ?>'></script>
<script src='<?= base_url('assets/dashboard-summary.js?v=20260916-year-label') ?>'></script>
<script src='https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js'></script>
<script src='<?= base_url('assets/attendance-ui.js?v=20260919-qr-realtime') ?>'></script>
<script src='<?= base_url('assets/attendance-live.js?v=20260919') ?>'></script>
<script src='<?= base_url('assets/attendance-recap-detail.js?v=20260914-september-data') ?>'></script>
<script src='<?= base_url('assets/reports-tabs.js?v=20260914c') ?>'></script>
<script src='<?= base_url('assets/schedule-actions.js?v=20260918-observer-fix') ?>'></script>
<script src='<?= base_url('assets/maintenance-ui.js?v=20260920-maintenance') ?>'></script>
<script>
(function(){
 function bindWorkspaceNavigation(){
  document.querySelectorAll('[data-page]').forEach(function(button){
   if(button.dataset.directNavigation)return;
   button.dataset.directNavigation='1';
   button.addEventListener('click',function(event){
    event.preventDefault();
    event.stopImmediatePropagation();
    if(typeof window.render!=='function'){window.alert('AIWalas belum selesai memuat. Silakan tunggu sebentar.');return}
    window.page=button.dataset.page;
    try{window.render()}catch(error){console.error(error);window.alert('Menu gagal dibuka: '+error.message)}
   },true);
  });
 }
 bindWorkspaceNavigation();
 if(window.MutationObserver)new MutationObserver(bindWorkspaceNavigation).observe(document.body,{childList:true,subtree:true});
})();
</script>
</body>
</html>
