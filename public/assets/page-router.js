(function(){
  var fallback=window.render, ledger={}, ledgerContext='', requestVersion=0;
  function context(){return String((window.currentUser||{}).id)+':'+window.activeClassId+':'+(window.activeSemester||'Ganjil')}
  var subjectKey=window.academicSubjectKey;
  window.ledgerTeacherAssessment=function(subject){return ledgerContext===context()?(ledger[subjectKey(subject)]||{}):{}};
  function loadLedger(){
    var version=++requestVersion, page=window.page, requestedContext=context();
    document.getElementById('pageContent').innerHTML='<p class="stat-note">Memuat nilai...</p>';
    authRequest('api/akademik?kelas_id='+window.activeClassId+'&semester='+encodeURIComponent(window.activeSemester||'Ganjil')).then(function(data){
      if(version!==requestVersion||window.page!==page||context()!==requestedContext)return;
      ledger={};ledgerContext=requestedContext;var names={};(data.subjects||[]).forEach(function(s){names[s.id]=s.nama_mata_pelajaran});
      (data.data||[]).forEach(function(row){var name=subjectKey(names[row.mata_pelajaran_id]);if(!ledger[name])ledger[name]={};if(!ledger[name][row.nis])ledger[name][row.nis]={};ledger[name][row.nis][window.academicComponentKey(row.nama_komponen)]=Number(row.nilai)});
      if(page==='grades')document.getElementById('pageContent').innerHTML=window.grades();
      else fallback();
    }).catch(function(error){if(version===requestVersion&&window.page===page&&context()===requestedContext)document.getElementById('pageContent').textContent=error.message;});
  }
  window.AIWalasPages=window.AIWalasPages||{};
  window.AIWalasPages.grades=loadLedger;
  window.AIWalasPages.reports=loadLedger;
  window.AIWalasPages.teachers=loadLedger;

  window.render=function(){
    var studentRoutes={dashboard:'studentDashboard',attendance:'studentAttendance'};
    var route=window.currentRole==='student'?(studentRoutes[window.page]||window.page):window.page;
    var handler=(window.AIWalasPages||{})[route];
    if(!handler)return fallback.apply(this,arguments);
    document.querySelectorAll('.nav-item[data-page]').forEach(function(item){item.classList.toggle('active',item.dataset.page===window.page)});
    var crumb=document.getElementById('breadcrumbCurrent');if(crumb)crumb.textContent=({dashboard:'Beranda',attendance:'Absensi Harian',attendanceRecapPage:'Rekap Absen',discipline:'Status Kedisiplinan',journal:'Jurnal Kelas',assessment:'Penilaian',studentGrades:'Nilai dan Capaian',studentSchedule:'Jadwal Mapel',studentDiscipline:'Status Kedisiplinan',grades:'Ledger Nilai',teachers:'Guru Mapel',reports:'Laporan Wali Kelas',maintenance:'Maintenance',teacherAccounts:'Akun Guru',accountManagement:'Kelola Akun',notifications:'Notifikasi',communication:'Komunikasi',walasAssessment:'Penilaian'})[window.page]||window.page;
    return handler();
  };
  // A restored session may render before the final router finishes loading.
  if(window.currentRole==='student'&&['dashboard','attendance'].indexOf(window.page)>=0)window.render();
})();
