<!doctype html>
<html lang='id'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1'>
  <meta name='csrf-token' content='<?= csrf_hash() ?>'>
  <title>AIWalas</title>
  <link rel='stylesheet' href='<?= base_url('assets/photo-uploads.css?v=20261028-single-page') ?>'>
  <link rel='stylesheet' href='<?= base_url('assets/styles.css?v=20261029-tasks-six') ?>'>
<style>.workspace-switch .chevron{display:none!important}</style>
<style>@media print{#sendReportToTeacher,#modalBackdrop{display:none!important}}</style>
<style>.grid-stats .absence-summary-card{min-height:190px!important;padding:15px 16px!important}.grid-stats .absence-summary-card .stat-number{font-size:25px!important;margin-top:6px!important}.grid-stats .absence-summary-card .stat-note{font-size:9px!important;white-space:nowrap}.grid-stats .absence-summary-card .absence-summary-list{margin-top:7px!important;gap:4px!important}.grid-stats .absence-summary-card .absence-summary-row{font-size:8px!important;line-height:1.15!important}.grid-stats .absence-summary-card .absence-summary-row strong{font-size:8px!important;white-space:nowrap!important}</style>
<style>.grid-stats .absence-summary-card{overflow:visible!important}.grid-stats .absence-summary-card .absence-summary-list{display:grid!important;grid-template-columns:1fr!important;max-height:none!important;overflow:visible!important;position:relative!important;z-index:3!important}.grid-stats .absence-summary-card .absence-summary-row{display:flex!important;visibility:visible!important;opacity:1!important;min-height:10px!important}</style>
</head>
<style>
.assessment-table .score-name small{display:block;font-size:10px;font-weight:400;color:var(--muted);line-height:1.25;margin-top:2px}
.student-vertical-grades th:first-child,.student-vertical-grades td:first-child{text-align:left;width:52%;min-width:220px}.student-vertical-grades th:nth-child(2),.student-vertical-grades td:nth-child(2),.student-vertical-grades th:nth-child(3),.student-vertical-grades td:nth-child(3){text-align:center;width:24%;min-width:110px}
.teacher-journal-table{width:100%;min-width:760px;table-layout:fixed}.teacher-journal-table .journal-date-col{width:145px}.teacher-journal-table .journal-wide-col{width:34%}.teacher-journal-table .journal-author-col{width:150px}.teacher-journal-table textarea{width:100%;min-height:58px;resize:vertical;box-sizing:border-box;padding:8px;border:1px solid var(--line);border-radius:6px;font:inherit;font-size:11px;line-height:1.4}.teacher-journal-table input,.teacher-journal-table select{width:100%;box-sizing:border-box;padding:9px 7px;border:1px solid var(--line);border-radius:6px;font:inherit;font-size:11px}
</style>
<style>.teacher-journal-wrap + .assessment-save-row{justify-content:space-between;text-align:left;padding-left:5mm!important;padding-bottom:5mm!important;align-items:flex-end}.teacher-journal-wrap + .assessment-save-row .stat-note{text-align:left;flex:1;position:static;margin-left:0!important;transform:none!important}.teacher-journal-wrap + .assessment-save-row #saveJournal{margin-left:auto;display:block}</style>
<body>
<?= $this->renderSection('content') ?>
<script src='<?= base_url('assets/student-details.js?v=20260911') ?>'></script>
<script>window.AIWalasSchoolHolidays=<?= json_encode(\App\Libraries\SchoolCalendar::SPECIAL_HOLIDAYS, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;window.AIWalasSchoolPjjDates=<?= json_encode(\App\Libraries\SchoolCalendar::PJJ_DATES) ?>;</script>
<script src='<?= base_url('assets/school-calendar.js?v=20261029-august-pjj') ?>'></script>
<script src='<?= base_url('assets/academic-subjects.js?v=20261028-wahyu-grade-source') ?>'></script>
<script>window.AIWalasGradeComponentLabels=<?= json_encode(\App\Libraries\GradeComponents::labels()) ?>;</script>
<script src='<?= base_url('assets/grade-components.js?v=20261029-tasks-six') ?>'></script>
<script src='<?= base_url('assets/app.js?v=20261029-tasks-six') ?>'></script>
<script src='<?= base_url('assets/walas-assessment.js?v=20261029-tasks-six') ?>'></script>
<script src='https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js'></script>
<script src='<?= base_url('assets/student-qr-attendance.js?v=20261029-august-pjj') ?>'></script>
<script src='<?= base_url('assets/teacher-role.js?v=20261018') ?>'></script>
<script src='<?= base_url('assets/whatsapp-notifications.js?v=20261010-mapel-guru-update') ?>'></script>
<script src='<?= base_url('assets/whatsapp-gateway.js?v=20261010-gateway') ?>'></script>
<script src='<?= base_url('assets/teacher-schedule-reminders.js?v=20261022-exclusions') ?>'></script>
<script src='<?= base_url('assets/students-page.js?v=20260918-import-excel') ?>'></script>
<script src='<?= base_url('assets/school-teachers.js?v=20260913-teachers-export6') ?>'></script>
<script src='<?= base_url('assets/school-teachers-mapel.js?v=20260918-mapel-kelas-imported') ?>'></script>
<script src='<?= base_url('assets/school-teachers-actions.js?v=20260919-detail-trash') ?>'></script>
<script src='<?= base_url('assets/school-teachers-cleanup.js?v=20260919') ?>'></script>
<script src='<?= base_url('assets/school-teachers-layout.js?v=20260920') ?>'></script>
<script src='<?= base_url('assets/api-ui.js?v=20261018') ?>'></script>
<script src='https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js'></script>
<script src='<?= base_url('assets/ledger-import.js?v=20261010-mapel-guru-update') ?>'></script>
<script src='<?= base_url('assets/ledger-page.js?v=20260918-name-left') ?>'></script>
<script src='<?= base_url('assets/hud-chart.js?v=20261022-student-summary') ?>'></script>
<script src='<?= base_url('assets/dashboard-summary.js?v=20261029-august-pjj') ?>'></script>
<script src='https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js'></script>
<script src='<?= base_url('assets/attendance-ui.js?v=20261029-august-pjj') ?>'></script>
<script src='<?= base_url('assets/attendance-live.js?v=20261026-attendance-grace') ?>'></script>
<script src='<?= base_url('assets/assistant-attendance-hide.js?v=20260924-hide-controls') ?>'></script>
<script src='<?= base_url('assets/assistant-sidebar.js?v=20261028-secretary-menus') ?>'></script>
<script src='<?= base_url('assets/attendance-recap-detail.js?v=20261028-secretary-menus') ?>'></script>
<script src='<?= base_url('assets/reports-tabs.js?v=20260914c') ?>'></script>
<script src='<?= base_url('assets/reports-teacher-share.js?v=20261011-two-reports') ?>'></script>
<script src='<?= base_url('assets/schedule-actions.js?v=20260918-observer-fix') ?>'></script>
<script src='<?= base_url('assets/maintenance-ui.js?v=20260919-fix') ?>'></script>
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
    try{if(button.dataset.page==='walasAssessment'&&typeof window.renderWalasAssessmentPage==='function'){window.renderWalasAssessmentPage()}else if(button.dataset.page==='assessment'&&typeof window.renderAssessmentPage==='function'){window.renderAssessmentPage()}else{window.render()}}catch(error){console.error(error);window.alert('Menu gagal dibuka: '+error.message)}
   },true);
  });
 }
 bindWorkspaceNavigation();
 if(window.MutationObserver)new MutationObserver(bindWorkspaceNavigation).observe(document.body,{childList:true,subtree:true});
})();
</script>





<script src='<?= base_url('assets/teacher-journal-ui.js?v=20261018') ?>'></script>
<script src='<?= base_url('assets/settings-page.js?v=20261026-parent-login') ?>'></script>
<script src='<?= base_url('assets/student-home.js?v=20261026-parent-login') ?>'></script>
<script src='<?= base_url('assets/student-sidebar.js?v=20261026-discipline') ?>'></script>
<script src='<?= base_url('assets/teacher-sidebar.js?v=20261028-camera-label') ?>'></script>
<script src='<?= base_url('assets/teacher-schedule-card.js?v=20260923-weekly-subject') ?>'></script>
<script src='<?= base_url('assets/student-home-force.js?v=20261026-parent-login') ?>'></script>
<script src='<?= base_url('assets/student-grades-access.js?v=20260921-access') ?>'></script>
<script src='<?= base_url('assets/student-grades-vertical.js?v=20261029-tasks-six') ?>'></script>
<script src='<?= base_url('assets/student-welcome-name.js?v=20260922-name') ?>'></script>
<script>
(function(){
  var form=document.getElementById('loginForm');
  if(!form||form.dataset.assistantFallback==='1')return;
  form.dataset.assistantFallback='1';
  form.addEventListener('submit',function(e){
    var role=document.getElementById('loginRole');
    if(!role||role.value!=='assistant')return;
    e.preventDefault();e.stopImmediatePropagation();
    var error=document.getElementById('loginError'),button=form.querySelector('[type="submit"]');
    if(button){button.disabled=true;button.textContent='Memeriksa akun...'}
    var meta=document.querySelector('meta[name="csrf-token"]'),headers={'Accept':'application/json','Content-Type':'application/json'};
    if(meta)headers['X-CSRF-TOKEN']=meta.content;
    fetch('api/auth/login',{method:'POST',credentials:'same-origin',headers:headers,body:JSON.stringify({role:'assistant',username:document.getElementById('loginUsername').value.trim(),password:document.getElementById('loginPassword').value})})
      .then(function(r){return r.json().then(function(d){if(d.csrfHash&&meta)meta.content=d.csrfHash;if(!r.ok||!d.ok)throw new Error(d.message||'Login sekretaris gagal.');return d})})
      .then(function(d){return typeof window.finishLogin==='function'?window.finishLogin(d.user):window.location.reload()})
      .catch(function(err){if(error)error.textContent=err.message})
      .finally(function(){if(button){button.disabled=false;button.textContent='Masuk ke AIWalas'}});
  },true);
})();
</script><script src='<?= base_url('assets/assessment-redesign.js?v=20261029-tasks-six') ?>'></script>
<script src='<?= base_url('assets/semester-context.js?v=20261028-secretary-menus') ?>'></script>
<script src='<?= base_url('assets/ledger-live.js?v=20260930-final2') ?>'></script>
<script src='<?= base_url('assets/attendance-realtime.js?v=20261021-date-filter') ?>'></script>
<script src='<?= base_url('assets/attendance-whatsapp.js?v=20261028-weekend') ?>'></script>
<script>
(function(){
  function ensureWalasAssessment(){
    if(window.currentRole!=='walas')return;
    var button=document.querySelector('.sidebar .nav-item[data-page="walasAssessment"]');
    if(button)button.style.display='flex';
  }
  document.addEventListener('DOMContentLoaded',ensureWalasAssessment);
  setTimeout(ensureWalasAssessment,500);
  setTimeout(ensureWalasAssessment,1500);
})();
</script>
<script src='<?= base_url('assets/class-attendance-recap.js?v=20261028-secretary-menus') ?>'></script>
<script src='<?= base_url('assets/account-management.js?v=20261028-student-alias') ?>'></script>
<script src='<?= base_url('assets/teacher-accounts.js?v=20261018') ?>'></script>
<script src='<?= base_url('assets/notifications-page.js?v=20261021') ?>'></script>
<script src='<?= base_url('assets/communication-history.js?v=20261022') ?>'></script>
<script src='<?= base_url('assets/discipline-page.js?v=20261029-category-status') ?>'></script>
<script src='<?= base_url('assets/student-schedule.js?v=20261026-complete') ?>'></script>
<script src='<?= base_url('assets/student-discipline.js?v=20261029-category-status') ?>'></script>
<script src='<?= base_url('assets/student-attendance-recap.js?v=20261029-august-pjj') ?>'></script>
<script src='<?= base_url('assets/parent-sick-request.js?v=20261027') ?>'></script>
<script src='<?= base_url('assets/photo-uploads.js?v=20261028-single-page') ?>'></script>
<script src='<?= base_url('assets/page-router.js?v=20261029-tasks-six') ?>'></script>
</body>
</html>


































































