(function(){
  var selectedDate=null,selectedFilter='Nama',context='',records={},loadedKey='',inflight='',version=0;
  function today(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())}
  function esc(v){return escapeHtml(v==null?'':v)}
  function isToday(){return (selectedDate||today())===today()}
  function key(){return String((window.currentUser||{}).id)+':'+window.activeClassId+':'+(window.activeSemester||'Ganjil')+':'+selectedDate}
  function actionState(){
    ['scanQr','makeLiveQr','manualAttendance','attendanceManual','createStudentQr'].forEach(function(id){var b=document.getElementById(id);if(b){b.disabled=!isToday();b.title=isToday()?'':'Pilih tanggal hari ini untuk mencatat absensi.'}});
  }
  function rows(){
    return (window.students||[]).slice().sort(function(a,b){return String(a[0]).localeCompare(String(b[0]),'id')}).map(function(student){var record=records[String(student[7])];return {student:student,record:record,status:record?record.status:'Menunggu keterangan'}});
  }
  function paint(){
    if(window.page!=='attendance'||window.currentRole==='student')return;
    var root=document.getElementById('pageContent'),body=root.querySelector('.table-panel tbody');if(!body)return;
    var list=rows(),visible=list.filter(function(row){return selectedFilter==='Nama'||row.status===selectedFilter});
    var counts={};list.forEach(function(row){counts[row.status]=(counts[row.status]||0)+1});
    var cards=root.querySelectorAll('.grid-stats .stat-card'),total=list.length,present=counts.Hadir||0;
    [[present,total?Math.round(present/total*100)+'% siswa':'0% siswa'],[counts.Terlambat||0,'Berdasarkan tanggal pilihan'],[(counts.Sakit||0)+(counts.Izin||0)+(counts.Alpha||0),'Sakit, izin, atau alpha'],[counts['Menunggu keterangan']||0,'Belum ada catatan kehadiran']].forEach(function(value,i){var card=cards[i];if(!card)return;var number=card.querySelector('.stat-number'),note=card.querySelector('.stat-note');if(number)number.textContent=value[0];if(note)note.textContent=value[1]});
    body.innerHTML=visible.map(function(row){var s=row.student,r=row.record,status=row.status,time=r&&r.waktu_scan?String(r.waktu_scan).slice(0,8):'—';return '<tr data-attendance-student="'+esc(s[7])+'" data-attendance-status="'+esc(status)+'"><td><div class="student-cell"><span class="avatar avatar-teal">'+esc(s[3])+'</span><strong>'+esc(s[0])+'</strong></div></td><td>'+esc(time)+'</td><td><span class="status '+statusClass(status)+'">'+esc(status)+'</span></td><td><span class="stat-note">'+(isToday()?'—':'Riwayat')+'</span></td><td>'+(currentRole==='walas'?'<button type="button" class="panel-action attendance-correct" data-student-id="'+esc(s[7])+'" data-student-name="'+esc(s[0])+'" data-status="'+esc(status)+'" data-time="'+esc(time)+'" data-note="'+esc(r&&r.keterangan||'')+'">Koreksi</button>':'<span class="stat-note">'+(isToday()?'Wali kelas':'Riwayat')+'</span>')+'</td></tr>'}).join('')||'<tr><td colspan="5" class="summary-empty">'+(list.length?'Tidak ada siswa dengan status '+esc(selectedFilter)+' pada tanggal ini.':'Belum ada siswa pada kelas ini.')+'</td></tr>';
    var note=document.getElementById('attendanceViewSummary');if(note)note.textContent=visible.length+' dari '+list.length+' siswa · '+selectedDate;
    body.querySelectorAll('.attendance-correct').forEach(function(btn){btn.onclick=function(){openCorrection(btn)}});
    actionState();
  }
  function refresh(){
    if(window.page!=='attendance'||window.currentRole==='student'||!window.activeClassId)return;
    var requestKey=key();if(inflight===requestKey)return;
    var request=++version;inflight=requestKey;
    if(loadedKey!==requestKey){records={};var body=document.querySelector('#pageContent .table-panel tbody');if(body)body.innerHTML='<tr><td colspan="5">Memuat absensi...</td></tr>'}
    var date=selectedDate,classId=activeClassId,semester=window.activeSemester||'Ganjil';
    return authRequest('api/absensi?kelas_id='+classId+'&tanggal='+encodeURIComponent(date)+'&semester='+encodeURIComponent(semester)).then(function(data){
      if(request!==version||window.page!=='attendance'||key()!==requestKey)return;
      records={};(data.data||[]).forEach(function(row){records[String(row.siswa_id)]=row});loadedKey=requestKey;
      var error=document.getElementById('attendanceViewError');if(error)error.textContent='';paint();
    }).catch(function(error){if(request===version&&window.page==='attendance'&&key()===requestKey){var box=document.getElementById('attendanceViewError');if(box)box.textContent=error.message;var body=document.querySelector('#pageContent .table-panel tbody');if(body&&loadedKey!==requestKey)body.innerHTML='<tr><td colspan="5">Absensi belum dapat dimuat.</td></tr>'}}).finally(function(){if(request===version)inflight=''});
  }
  function install(){
    if(window.page!=='attendance'||window.currentRole==='student')return;
    var root=document.getElementById('pageContent'),table=root&&root.querySelector('.table-panel');if(!table)return;
    var nextContext=String((window.currentUser||{}).id)+':'+activeClassId+':'+(window.activeSemester||'Ganjil');
    if(context!==nextContext){context=nextContext;selectedDate=today();selectedFilter='Nama';records={};loadedKey='';}
    if(!selectedDate)selectedDate=today();root.querySelector('.page').classList.add('attendance-page');
    if(!document.getElementById('attendanceViewDate')){
      var heading=table.querySelector('.panel-head'),oldFilter=heading&&heading.querySelector('.filter-btn');if(oldFilter)oldFilter.remove();
      var controls=document.createElement('div');controls.className='attendance-view-controls';controls.innerHTML='<label>Tanggal absensi<input type="date" id="attendanceViewDate" value="'+esc(selectedDate)+'"></label><label>Pilih berdasarkan<select id="attendanceSortFilter" class="filter-btn">'+['Nama','Terlambat','Sakit','Izin','Alpha','PJJ'].map(function(item){return '<option'+(selectedFilter===item?' selected':'')+'>'+item+'</option>'}).join('')+'</select></label>';
      if(heading)heading.appendChild(controls);
      var note=heading&&heading.querySelector('p');if(note)note.id='attendanceViewSummary';
      var error=document.createElement('p');error.id='attendanceViewError';error.className='login-error';error.setAttribute('role','alert');table.insertBefore(error,table.querySelector('.table-wrap'));
      document.getElementById('attendanceViewDate').onchange=function(){if(!this.value||!this.validity.valid){this.value=selectedDate;return}selectedDate=this.value;loadedKey='';actionState();updateHeading();refresh()};
      document.getElementById('attendanceSortFilter').onchange=function(){selectedFilter=this.value;if(loadedKey===key())paint();else refresh()};
    }
    updateHeading();actionState();if(loadedKey===key())paint();refresh();
  }
  function updateHeading(){
    var root=document.getElementById('pageContent'),heading=root.querySelector('.page-heading p'),title=root.querySelector('.table-panel .panel-head h2');
    if(heading)heading.textContent='Tanggal '+selectedDate+' · '+(isToday()?'Absensi hari ini':'Riwayat absensi');
    if(title)title.textContent=isToday()?'Status absensi hari ini':'Status absensi '+selectedDate;
  }
  function openCorrection(btn){
    if(window.currentRole!=='walas')return;
    var classId=activeClassId,semester=window.activeSemester||'Ganjil',studentId=btn.dataset.studentId,date=selectedDate||today();
    modal('Koreksi Absensi','Koreksi absensi tanggal '+date+'.','<form><label>Nama siswa<input value="'+esc(btn.dataset.studentName)+'" disabled></label><label>Waktu scan<input type="time" id="correctTime" value="'+(btn.dataset.time==='—'?'':esc(btn.dataset.time.slice(0,5)))+'"></label><label>Status<select id="correctStatus">'+['Hadir','Terlambat','Sakit','Izin','Alpha','PJJ','Menunggu keterangan'].map(function(status){return '<option>'+status+'</option>'}).join('')+'</select></label><label>Keterangan<textarea id="correctNote">'+esc(btn.dataset.note)+'</textarea></label><p class="login-error" id="correctError"></p><div class="modal-actions"><button type="button" class="filter-btn" id="cancelModal">Batal</button><button type="button" class="primary-btn" id="saveModal">Simpan koreksi</button></div></form>');
    document.getElementById('correctStatus').value=btn.dataset.status;
    document.getElementById('saveModal').onclick=function(){var button=this;button.disabled=true;authRequest('api/absensi',{method:'POST',body:JSON.stringify({siswa_id:+studentId,kelas_id:classId,tanggal:date,waktu_scan:document.getElementById('correctTime').value?document.getElementById('correctTime').value+':00':null,status:document.getElementById('correctStatus').value,keterangan:document.getElementById('correctNote').value.trim(),semester:semester})}).then(function(result){if(date===today()&&activeClassId===classId&&(window.activeSemester||'Ganjil')===semester){(window.students||[]).forEach(function(s){if(String(s[7])===studentId)s[2]=result.data.status})}closeModal();toast('Absensi berhasil dikoreksi');loadedKey='';refresh()}).catch(function(error){var box=document.getElementById('correctError');if(box)box.textContent=error.message;button.disabled=false})};
  }
  window.AIWalasAttendance={refresh:refresh,install:install,isToday:isToday,selectedDate:function(){return selectedDate||today()},setActionState:actionState};
  var old=window.render;window.render=function(){var result=old.apply(this,arguments);setTimeout(install,0);return result};setTimeout(install,0);
})();
