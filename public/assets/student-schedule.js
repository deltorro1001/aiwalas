(function(){
  var version=0,days=['Senin','Selasa','Rabu','Kamis','Jumat'];
  function esc(value){return escapeHtml(value==null?'':value)}
  function renderStudentSchedule(){
    if(window.currentRole!=='student')return;
    var request=++version,userId=(window.currentUser||{}).id,classId=window.activeClassId;
    document.getElementById('pageContent').innerHTML='<div class="page student-weekly-schedule"><div class="page-heading"><div><div class="eyebrow">Jadwal siswa</div><h1>Jadwal Mapel</h1><p>Jadwal Senin–Jumat · Seluruh waktu dalam WIB</p></div><button type="button" class="outline-btn" id="refreshStudentSchedule">Muat ulang</button></div><p id="studentScheduleError" class="login-error" role="alert"></p><div id="studentWeeklyDays" aria-live="polite"><p class="stat-note">Memuat jadwal mapel...</p></div></div>';
    document.getElementById('refreshStudentSchedule').onclick=renderStudentSchedule;
    authRequest('api/jadwal?kelas_id='+encodeURIComponent(classId),{cache:'no-store'}).then(function(result){
      if(request!==version||window.currentRole!=='student'||window.page!=='studentSchedule'||(window.currentUser||{}).id!==userId||window.activeClassId!==classId)return;
      var rows=result.data||[];
      document.getElementById('studentWeeklyDays').innerHTML=days.map(function(day){
        var sessions=rows.filter(function(row){return row.hari===day}).sort(function(a,b){return String(a.jam_mulai).localeCompare(String(b.jam_mulai))});
        return '<section class="panel" style="margin-bottom:16px" data-student-schedule-day="'+day+'"><div class="panel-head"><h2>'+day+'</h2><span class="pill teal">'+sessions.length+' sesi</span></div><div class="schedule-list">'+(sessions.length?sessions.map(function(row){return '<div class="schedule-item"><span class="schedule-time">'+esc(String(row.jam_mulai).slice(0,5))+'–'+esc(String(row.jam_selesai).slice(0,5))+'</span><i class="schedule-bar"></i><div class="schedule-info"><strong>'+esc(row.nama_mata_pelajaran)+'</strong><small>'+esc(row.nama_guru||row.keterangan||'Guru belum ditentukan')+'</small></div></div>'}).join(''):'<p class="stat-note">Belum ada jadwal mapel untuk hari '+day+'.</p>')+'</div></section>';
      }).join('');
    }).catch(function(error){
      if(request===version&&window.page==='studentSchedule'&&(window.currentUser||{}).id===userId){var box=document.getElementById('studentScheduleError');if(box)box.textContent=error.message||'Jadwal gagal dimuat.';var daysBox=document.getElementById('studentWeeklyDays');if(daysBox)daysBox.innerHTML='';}
    });
  }
  window.AIWalasPages=window.AIWalasPages||{};window.AIWalasPages.studentSchedule=renderStudentSchedule;
})();
