(function(){
  var fallback=window.render, ledger={}, requestVersion=0;
  window.ledgerTeacherAssessment=function(subject){return ledger[String(subject).toLowerCase().replace(/[^a-z0-9]/g,'')]||{}};
  function loadLedger(){
    var version=++requestVersion, page=window.page;
    document.getElementById('pageContent').innerHTML='<p class="stat-note">Memuat nilai...</p>';
    authRequest('api/akademik?kelas_id='+window.activeClassId+'&semester='+encodeURIComponent(window.activeSemester||'Ganjil')).then(function(data){
      if(version!==requestVersion||window.page!==page)return;
      ledger={};var names={};(data.subjects||[]).forEach(function(s){names[s.id]=s.nama_mata_pelajaran});
      (data.data||[]).forEach(function(row){var name=String(names[row.mata_pelajaran_id]||'').toLowerCase().replace(/[^a-z0-9]/g,'');if(!ledger[name])ledger[name]={};if(!ledger[name][row.nis])ledger[name][row.nis]={};ledger[name][row.nis][row.nama_komponen.replace(/\s/g,'')]=Number(row.nilai)});
      if(ledger.olahraga)ledger.pjok=ledger.olahraga;
      if(page==='grades')document.getElementById('pageContent').innerHTML=window.grades();
      else fallback();
    }).catch(function(error){if(version===requestVersion&&window.page===page)document.getElementById('pageContent').textContent=error.message;});
  }
  window.AIWalasPages=window.AIWalasPages||{};
  window.AIWalasPages.grades=loadLedger;
  window.AIWalasPages.reports=loadLedger;

  window.render=function(){
    var studentRoutes={dashboard:'studentDashboard',attendance:'studentAttendance'};
    var route=window.currentRole==='student'?(studentRoutes[window.page]||window.page):window.page;
    var handler=(window.AIWalasPages||{})[route];
    if(!handler)return fallback.apply(this,arguments);
    document.querySelectorAll('.nav-item[data-page]').forEach(function(item){item.classList.toggle('active',item.dataset.page===window.page)});
    var crumb=document.getElementById('breadcrumbCurrent');if(crumb)crumb.textContent=({dashboard:'Beranda',attendance:'Absensi Harian',discipline:'Status Kedisiplinan',journal:'Jurnal Kelas',assessment:'Penilaian',studentGrades:'Nilai dan Capaian',studentSchedule:'Jadwal Mapel',studentDiscipline:'Status Kedisiplinan',grades:'Ledger Nilai',reports:'Laporan Wali Kelas',maintenance:'Maintenance',teacherAccounts:'Akun Guru',notifications:'Notifikasi',communication:'Komunikasi',walasAssessment:'Penilaian'})[window.page]||window.page;
    return handler();
  };
  // A restored session may render before the final router finishes loading.
  if(window.currentRole==='student'&&['dashboard','attendance'].indexOf(window.page)>=0)window.render();
})();
