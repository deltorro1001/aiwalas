(function(){
  var records=[],assignments=[],version=0;
  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function edit(record){
    var item=record||{},selected=assignments.findIndex(function(a){return Number(a.mata_pelajaran_id)===Number(item.mata_pelajaran_id)&&Number(a.guru_id)===Number(item.guru_id)});
    var today=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
    modal(record?'Edit Jurnal':'Tambah Jurnal','Jurnal disimpan ke database kelas.','<form id="journalForm"><label>Mata pelajaran dan guru<select id="jAssignment">'+assignments.map(function(a,i){return '<option value="'+i+'"'+(i===selected?' selected':'')+'>'+esc(a.nama_mata_pelajaran+' · '+a.nama_guru)+'</option>'}).join('')+'</select></label><label>Tanggal<input id="jDate" type="date" value="'+esc(item.tanggal||today)+'"></label><label>Materi<textarea id="jMateri">'+esc(item.materi||'')+'</textarea></label><label>Tugas<textarea id="jTugas">'+esc(item.catatan||'')+'</textarea></label><p id="journalError" class="login-error"></p><div class="modal-actions"><button type="button" id="cancelModal" class="filter-btn">Batal</button><button type="submit" class="primary-btn">Simpan</button></div></form>');
    document.getElementById('journalForm').onsubmit=function(event){
      event.preventDefault();var form=this,button=form.querySelector('[type=submit]'),assignment=assignments[Number(document.getElementById('jAssignment').value)];if(!assignment)return;
      button.disabled=true;
      var data={kelas_id:window.activeClassId,mata_pelajaran_id:assignment.mata_pelajaran_id,guru_id:assignment.guru_id,tanggal:document.getElementById('jDate').value,materi:document.getElementById('jMateri').value,catatan:document.getElementById('jTugas').value,status:item.status||'Terbit'};
      authRequest('api/jurnal'+(record?'/'+record.id:''),{method:record?'PUT':'POST',body:JSON.stringify(data)}).then(function(){closeModal();renderJournal();toast('Jurnal tersimpan di database.');}).catch(function(error){document.getElementById('journalError').textContent=error.message;button.disabled=false;});
    };
  }
  function renderJournal(){
    var current=++version,classId=window.activeClassId;
    document.getElementById('pageContent').innerHTML='<p class="stat-note">Memuat jurnal...</p>';
    authRequest('api/jurnal?kelas_id='+classId).then(function(data){
      if(current!==version||window.page!=='journal')return;
      records=data.data||[];assignments=data.assignments||[];
      document.getElementById('pageContent').innerHTML='<div class="page"><div class="page-heading"><h1>Jurnal Kelas</h1><button type="button" class="primary-btn" id="addJournal"'+(!assignments.length?' disabled':'')+'>Tambah Jurnal</button></div><section class="panel table-panel"><div class="table-wrap"><table class="teacher-journal-table"><thead><tr><th>Mata Pelajaran</th><th>Tanggal</th><th>Materi</th><th>Tugas</th><th>Diisi Oleh</th><th>Aksi</th></tr></thead><tbody>'+records.map(function(row){return '<tr><td>'+esc(row.nama_mata_pelajaran)+'</td><td>'+esc(row.tanggal)+'</td><td>'+esc(row.materi)+'</td><td>'+esc(row.catatan)+'</td><td>'+esc(row.nama_pembuat)+'</td><td>'+((window.currentRole!=='teacher'||Number(row.dibuat_oleh)===Number(window.currentUser.id))?'<button type="button" class="panel-action" data-journal-edit="'+esc(row.id)+'">Edit</button>':'')+'</td></tr>'}).join('')+(!records.length?'<tr><td colspan="6">Belum ada jurnal.</td></tr>':'')+'</tbody></table></div></section></div>';
      document.getElementById('addJournal').onclick=function(){edit()};
      document.querySelectorAll('[data-journal-edit]').forEach(function(button){button.onclick=function(){edit(records.find(function(r){return String(r.id)===button.dataset.journalEdit}))}});
    }).catch(function(error){if(current===version&&window.page==='journal')document.getElementById('pageContent').textContent=error.message;});
  }
  window.AIWalasPages=window.AIWalasPages||{};window.AIWalasPages.journal=renderJournal;
})();
