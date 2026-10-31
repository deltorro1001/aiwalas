(function(){
  var records=[],assignments=[],version=0,selectedDate='',selectionContext='',canCreate=false;
  var minimumDate='2026-01-01';
  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function today(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())}
  function context(){return String((window.currentUser||{}).id)+':'+window.currentRole+':'+window.activeClassId}
  function writer(){return ['walas','teacher','assistant'].indexOf(window.currentRole)>=0}
  function dateLabel(date){return new Intl.DateTimeFormat('id-ID',{timeZone:'Asia/Jakarta',weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(new Date(date+'T12:00:00+07:00'))}
  function edit(record){
    if(!writer()||(!record&&!canCreate)||(record&&!record.can_edit))return;
    var item=record||{},editContext=context(),classId=window.activeClassId,options=assignments.slice();
    var selected=options.findIndex(function(a){return Number(a.mata_pelajaran_id)===Number(item.mata_pelajaran_id)&&Number(a.guru_id)===Number(item.guru_id)});
    // Retain historical assignments when the class timetable has since changed.
    if(record&&selected<0){selected=options.length;options.push(item)}
    modal(record?'Edit Jurnal':'Tambah Jurnal','Jurnal disimpan untuk tanggal dan kelas yang dipilih.','<form id="journalForm"><label>Mata pelajaran dan guru<select id="jAssignment" required>'+options.map(function(a,i){return '<option value="'+i+'"'+(i===selected?' selected':'')+'>'+esc((window.academicSubjectLabel?window.academicSubjectLabel(a.nama_mata_pelajaran):a.nama_mata_pelajaran)+' · '+a.nama_guru)+'</option>'}).join('')+'</select></label><label>Tanggal<input id="jDate" type="date" min="'+minimumDate+'" required value="'+esc(item.tanggal||selectedDate||today())+'"></label><label>Materi<textarea id="jMateri" maxlength="10000" required>'+esc(item.materi||'')+'</textarea></label><label>Tugas<textarea id="jTugas" maxlength="10000">'+esc(item.catatan||'')+'</textarea></label><p id="journalError" class="login-error" role="alert"></p><div class="modal-actions"><button type="button" id="cancelModal" class="filter-btn">Batal</button><button type="submit" class="primary-btn">Simpan</button></div></form>');
    document.getElementById('journalForm').onsubmit=function(event){
      event.preventDefault();if(context()!==editContext)return;
      var button=this.querySelector('[type=submit]'),assignment=options[Number(document.getElementById('jAssignment').value)];if(!assignment)return;
      button.disabled=true;
      var data={kelas_id:classId,mata_pelajaran_id:assignment.mata_pelajaran_id,guru_id:assignment.guru_id,tanggal:document.getElementById('jDate').value,materi:document.getElementById('jMateri').value,catatan:document.getElementById('jTugas').value,status:item.status||'Terbit'};
      authRequest('api/jurnal'+(record?'/'+record.id:''),{method:record?'PUT':'POST',body:JSON.stringify(data)}).then(function(){
        if(context()!==editContext)return;selectedDate=data.tanggal;closeModal();if(window.page==='journal')renderJournal();toast('Jurnal tersimpan untuk '+dateLabel(data.tanggal)+'.');
      }).catch(function(error){var box=document.getElementById('journalError');if(box)box.textContent=error.message;button.disabled=false;});
    };
  }
  function remove(record){
    if(!record||!record.can_delete||['walas','teacher'].indexOf(window.currentRole)<0)return;
    var deleteContext=context(),busy=false;
    modal('Hapus Jurnal','Periksa isian berikut sebelum menghapus. Jurnal yang dihapus tidak dapat dikembalikan.','<form id="journalDeleteForm"><div class="journal-delete-preview"><strong>'+esc(window.academicSubjectLabel(record.nama_mata_pelajaran))+'</strong><p>'+esc(dateLabel(record.tanggal))+'</p><p>Diisi oleh '+esc(record.nama_pembuat)+'</p><p><b>Materi:</b> '+esc(record.materi)+'</p><p><b>Tugas:</b> '+esc(record.catatan||'—')+'</p></div><p id="journalDeleteError" class="login-error" role="alert"></p><div class="modal-actions"><button type="button" id="cancelModal" class="filter-btn">Batal</button><button type="submit" id="confirmDeleteJournal" class="journal-delete-confirm">Hapus Jurnal</button></div></form>');
    var form=document.getElementById('journalDeleteForm');
    form.onsubmit=function(event){
      event.preventDefault();if(busy||context()!==deleteContext||window.page!=='journal')return;
      busy=true;var button=form.querySelector('[type=submit]');button.disabled=true;button.textContent='Menghapus...';
      authRequest('api/jurnal/'+record.id,{method:'DELETE'}).then(function(){
        if(context()!==deleteContext)return;
        if(document.getElementById('journalDeleteForm')===form)closeModal();
        if(window.page==='journal')renderJournal();toast('Jurnal berhasil dihapus.');
      }).catch(function(error){if(form.isConnected){form.querySelector('#journalDeleteError').textContent=error.message;button.disabled=false;button.textContent='Hapus Jurnal';busy=false}});
    };
  }
  function renderJournal(){
    var current=++version,classId=window.activeClassId,requestedContext=context();
    if(selectionContext!==requestedContext){selectionContext=requestedContext;selectedDate=today()}
    var requestedDate=selectedDate;records=[];assignments=[];canCreate=false;
    document.getElementById('pageContent').innerHTML='<div class="page journal-page"><div class="page-heading"><div><h1>Jurnal Kelas</h1><p id="journalDateCaption" data-calendar-date="'+esc(requestedDate)+'">'+esc(dateLabel(requestedDate))+'</p></div>'+(writer()?'<button type="button" class="primary-btn" id="addJournal" disabled>Tambah Jurnal</button>':'')+'</div><section class="panel journal-date-controls"><label for="journalViewDate">Tanggal jurnal<input id="journalViewDate" type="date" min="'+minimumDate+'" required value="'+esc(requestedDate)+'"></label><button type="button" class="outline-btn" id="journalToday">Hari ini</button><button type="button" class="outline-btn" id="refreshJournal">Muat ulang</button><p class="stat-note">Pilih tanggal mulai 1 Januari 2026 untuk melihat jurnal.'+(writer()?' Tekan Edit untuk memperbarui isian pada tanggal tersebut.':' Akun ini hanya dapat membaca jurnal.')+'</p></section><p id="journalLoadError" class="login-error" role="alert"></p><section class="panel table-panel"><div class="table-wrap"><table class="teacher-journal-table"><thead><tr><th>Mata Pelajaran</th><th>Tanggal</th><th>Materi</th><th>Tugas</th><th>Diisi Oleh</th>'+(writer()?'<th class="journal-action-col">Aksi</th>':'')+'</tr></thead><tbody id="journalRows"><tr><td colspan="'+(writer()?6:5)+'">Memuat jurnal...</td></tr></tbody></table></div></section></div>';
    document.getElementById('journalViewDate').onchange=function(){if(!this.value||!this.validity.valid){this.value=selectedDate;return}selectedDate=this.value;renderJournal()};
    document.getElementById('journalToday').onclick=function(){selectedDate=today();renderJournal()};
    document.getElementById('refreshJournal').onclick=renderJournal;
    function active(){return current===version&&window.page==='journal'&&context()===requestedContext&&selectedDate===requestedDate}
    authRequest('api/jurnal?kelas_id='+encodeURIComponent(classId)+'&tanggal='+encodeURIComponent(requestedDate),{cache:'no-store'}).then(function(data){
      if(!active())return;records=data.data||[];assignments=data.assignments||[];canCreate=writer()&&data.can_create===true;
      document.getElementById('journalDateCaption').textContent=dateLabel(requestedDate)+' · '+records.length+' jurnal';
      document.getElementById('journalRows').innerHTML=records.map(function(row){return '<tr><td>'+esc(window.academicSubjectLabel?window.academicSubjectLabel(row.nama_mata_pelajaran):row.nama_mata_pelajaran)+'</td><td data-calendar-date="'+esc(row.tanggal)+'">'+esc(row.tanggal.split('-').reverse().join('-'))+'</td><td>'+esc(row.materi)+'</td><td>'+esc(row.catatan)+'</td><td>'+esc(row.nama_pembuat)+'</td>'+(writer()?'<td class="journal-actions-cell"><div class="journal-row-actions">'+(row.can_edit?'<button type="button" class="panel-action" data-journal-edit="'+esc(row.id)+'">Edit</button>':'<span class="stat-note">Hanya baca</span>')+(row.can_delete&&['walas','teacher'].indexOf(window.currentRole)>=0?'<button type="button" class="journal-delete-button" data-journal-delete="'+esc(row.id)+'" title="Hapus jurnal" aria-label="Hapus jurnal '+esc(row.nama_mata_pelajaran)+' tanggal '+esc(row.tanggal.split('-').reverse().join('-'))+'"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6"/></svg></button>':'')+'</div></td>':'')+'</tr>'}).join('')||'<tr><td colspan="'+(writer()?6:5)+'">Belum ada jurnal pada tanggal ini.</td></tr>';
      var add=document.getElementById('addJournal');if(add){add.disabled=!canCreate||!assignments.length;add.onclick=function(){edit()}}
      document.querySelectorAll('[data-journal-edit]').forEach(function(button){button.onclick=function(){edit(records.find(function(r){return String(r.id)===button.dataset.journalEdit}))}});
      document.querySelectorAll('[data-journal-delete]').forEach(function(button){button.onclick=function(){remove(records.find(function(r){return String(r.id)===button.dataset.journalDelete}))}});
    }).catch(function(error){if(active()){document.getElementById('journalLoadError').textContent=error.message;document.getElementById('journalRows').innerHTML='<tr><td colspan="'+(writer()?6:5)+'">Jurnal belum dapat dimuat. Tekan Muat ulang untuk mencoba lagi.</td></tr>'}});
  }
  ['student'].forEach(function(role){if(window.roleAccess&&window.roleAccess[role]&&window.roleAccess[role].indexOf('journal')<0)window.roleAccess[role].push('journal')});
  var apply=window.applyRoleAccess;window.applyRoleAccess=function(){apply.apply(this,arguments);if(['student'].indexOf(window.currentRole)>=0){var button=document.querySelector('.sidebar [data-page="journal"]');if(button)button.style.display='flex'}};
  window.AIWalasPages=window.AIWalasPages||{};window.AIWalasPages.journal=renderJournal;
})();
