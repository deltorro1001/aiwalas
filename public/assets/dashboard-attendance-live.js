(function(){
  var timer=null,inflight=false;
  function context(){return [(window.currentUser||{}).id,window.currentRole,window.activeClassId,window.activeSemester||'Ganjil'].join(':')}
  function active(){return window.page==='dashboard'&&['walas','assistant','teacher','student'].indexOf(window.currentRole)>=0&&window.activeClassId&&!document.hidden}
  function refresh(){
    if(!active()||inflight)return Promise.resolve();
    var scope=context(),date=window.AIWalasCalendar.today();inflight=true;
    return authRequest('api/absensi?kelas_id='+encodeURIComponent(window.activeClassId)+'&tanggal='+date+'&semester='+encodeURIComponent(window.activeSemester||'Ganjil'),{cache:'no-store'}).then(function(result){
      if(!active()||scope!==context())return;
      var rows=result.data||[],map={};rows.forEach(function(row){map[String(row.siswa_id)]=row});
      if(window.currentRole==='student'){
        var own=rows[0],status=own?own.status:(result.kalender||{}).status||'Belum tercatat',box=document.querySelector('.student-current-attendance');
        if(box){box.dataset.attendanceStatus=status;box.innerHTML=window.AIWalasAttendanceDisplay.html(status,own&&own.keterangan_lampiran)}
        var time=box&&box.parentElement.querySelector('[data-attendance-time]');if(time){time.dataset.attendanceStatus=status;time.textContent=own&&own.waktu_scan?own.waktu_scan:'--:--:--'}
        return;
      }
      var counts={},notes={};(window.students||[]).forEach(function(student){var record=map[String(student[7])];student[2]=record?record.status:(result.kalender||{}).status||'Menunggu keterangan';counts[student[2]]=(counts[student[2]]||0)+1;notes[String(student[7])]=record?record.keterangan_lampiran||'':''});
      window.dashboardData=Object.assign({},window.dashboardData,{status:counts,tanggal:date,attachment_notes:notes});
      var absent=(window.students||[]).filter(function(s){return ['Sakit','Izin','Alpha'].indexOf(s[2])>=0}),list=document.querySelector('.dashboard-absence-list');
      if(list)list.innerHTML=absent.length?absent.map(function(s){return '<div class="dashboard-absence-row"><strong>'+escapeHtml(s[0])+'</strong> <span>'+window.AIWalasAttendanceDisplay.html(s[2],notes[String(s[7])])+'</span></div>'}).join(''):'<div class="summary-empty">Tidak ada siswa yang tidak hadir.</div>';
      var numbers=document.querySelectorAll('.grid-stats>.stat-card>.stat-number'),stats=[(window.students||[]).length,counts.Hadir||0,counts.Terlambat||0,absent.length+(counts['Menunggu keterangan']||0)];numbers.forEach(function(number,index){if(index<stats.length)number.textContent=stats[index]});
      var footer=document.querySelector('.summary-footer'),values=[counts.Hadir||0,counts.Terlambat||0,absent.length,counts['Menunggu keterangan']||0];
      if(footer)footer.querySelectorAll('b').forEach(function(b,index){if(index<values.length)b.textContent=values[index]});
      if(typeof window.renderAbsenceChart==='function')window.renderAbsenceChart();
    }).catch(function(){/* Keep the last successful display; a later refresh can retry. */}).finally(function(){inflight=false});
  }
  function install(){clearInterval(timer);if(active()){refresh();timer=setInterval(refresh,15000)}}
  var previous=window.render;window.render=function(){var result=previous.apply(this,arguments);setTimeout(install,0);return result};
  document.addEventListener('visibilitychange',function(){if(!document.hidden)install()});
  window.AIWalasDashboardAttendance={refresh:refresh};setTimeout(install,0);
})();
