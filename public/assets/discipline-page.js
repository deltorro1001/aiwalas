(function(){
  var rows=[],kind='etika',version=0;
  var titles={etika:'Pelanggaran Etika',terlambat:'Keterlambatan',atribut:'Pakaian & Atribut'};
  var fields={etika:['pelanggaran','tanggal','saksi','alasan','tindakan'],terlambat:['jumlah_terlambat','tanggal','alasan'],atribut:['pelanggaran','perbaikan']};
  var labels={pelanggaran:'Pelanggaran Yang Dilakukan',tanggal:'Tanggal Dilakukan',saksi:'Disaksikan Oleh',alasan:'Alasan',tindakan:'Tindakan',perbaikan:'Perbaikan',jumlah_terlambat:'Jumlah Keterlambatan'};
  function esc(value){return escapeHtml(value==null?'':value)}
  function cells(student){var record=student.records[kind]||{},late=student.keterlambatan;return [student.nama_lengkap,student.nis].concat(fields[kind].map(function(field){return field==='jumlah_terlambat'?late.jumlah:field==='tanggal'&&kind==='terlambat'?(record.tanggal||late.tanggal||' - '):(record[field]||' - ')})).concat(kind==='etika'?[]:[late.status])}
  function headers(){return ['Nama Siswa','NIS'].concat(fields[kind].map(function(field){return labels[field]})).concat(kind==='etika'?[]:['Status Kedisiplinan'])}
  function paint(){
    var body=document.getElementById('storedDisciplineRows');if(!body)return;
    document.getElementById('storedDisciplineHead').innerHTML='<tr>'+headers().concat(['Aksi']).map(function(label){return '<th>'+esc(label)+'</th>'}).join('')+'</tr>';
    body.innerHTML=rows.map(function(student){return '<tr>'+cells(student).map(function(value){return '<td>'+esc(value)+'</td>'}).join('')+'<td><button type="button" class="panel-action" data-stored-discipline="'+student.id+'">Edit</button></td></tr>'}).join('')||'<tr><td colspan="9">Belum ada siswa.</td></tr>';
    body.querySelectorAll('[data-stored-discipline]').forEach(function(button){button.onclick=function(){edit(rows.find(function(student){return String(student.id)===button.dataset.storedDiscipline}))}});
  }
  function edit(student){
    var record=student.records[kind]||{},category=kind,classId=window.activeClassId,userId=(window.currentUser||{}).id;
    modal('Edit '+titles[category],'Catatan disimpan dan ditampilkan pada Beranda siswa yang bersangkutan.','<form>'+fields[category].map(function(field){var value=field==='jumlah_terlambat'?student.keterlambatan.jumlah:(record[field]||'');return '<label>'+labels[field]+(['tanggal','jumlah_terlambat','saksi'].indexOf(field)>=0?'<input id="storedDiscipline_'+field+'" type="'+(field==='tanggal'?'date':field==='jumlah_terlambat'?'number':'text')+'" '+(field==='jumlah_terlambat'?'min="0" max="10000" step="1" ':'')+'value="'+esc(value)+'">':'<textarea id="storedDiscipline_'+field+'" maxlength="2000">'+esc(value)+'</textarea>')+'</label>'}).join('')+'<p id="storedDisciplineError" class="login-error" role="alert"></p><div class="modal-actions"><button type="button" class="filter-btn" id="cancelModal">Batal</button><button type="button" class="primary-btn" id="saveStoredDiscipline">Simpan</button></div></form>');
    document.getElementById('saveStoredDiscipline').onclick=function(){
      var button=this,payload={siswa_id:student.id,kategori:category};
      if(!button.closest('form').reportValidity())return;
      fields[category].forEach(function(field){payload[field]=document.getElementById('storedDiscipline_'+field).value});button.disabled=true;
      authRequest('api/kedisiplinan',{method:'POST',body:JSON.stringify(payload)}).then(function(){
        if((window.currentUser||{}).id!==userId||window.activeClassId!==classId)return;
        closeModal();toast('Catatan kedisiplinan disimpan.');if(window.page==='discipline')renderDiscipline();
      }).catch(function(error){var box=document.getElementById('storedDisciplineError');if(box)box.textContent=error.message;button.disabled=false});
    };
  }
  function renderDiscipline(){
    if(window.currentRole!=='walas')return;
    var request=++version,classId=window.activeClassId,userId=(window.currentUser||{}).id;
    document.getElementById('pageContent').innerHTML='<div class="page discipline-page"><div class="page-heading"><div><div class="eyebrow">REPORT</div><h1>Status Kedisiplinan</h1><p>Pantauan Kedisiplinan Siswa</p></div><div class="discipline-actions"><select id="storedDisciplineCategory" class="filter-btn">'+Object.keys(titles).map(function(key){return '<option value="'+key+'"'+(key===kind?' selected':'')+'>'+esc(titles[key])+'</option>'}).join('')+'</select><button type="button" id="exportStoredDiscipline" class="outline-btn">Export</button></div></div><p id="storedDisciplineLoadError" class="login-error" role="alert"></p><section class="panel table-panel"><div class="table-wrap"><table><thead id="storedDisciplineHead"></thead><tbody id="storedDisciplineRows"><tr><td>Memuat catatan...</td></tr></tbody></table></div></section></div>';
    document.getElementById('storedDisciplineCategory').onchange=function(){kind=this.value;paint()};
    document.getElementById('exportStoredDiscipline').onclick=function(){
      var html='<table><tr>'+headers().map(function(label){return '<th>'+esc(label)+'</th>'}).join('')+'</tr>'+rows.map(function(student){return '<tr>'+cells(student).map(function(value){return '<td>'+esc(value)+'</td>'}).join('')+'</tr>'}).join('')+'</table>',url=URL.createObjectURL(new Blob(['\ufeff<html><meta charset="utf-8"><body>'+html+'</body></html>'],{type:'application/vnd.ms-excel'})),link=document.createElement('a');link.href=url;link.download='Status_Kedisiplinan_'+kind+'.xls';link.click();setTimeout(function(){URL.revokeObjectURL(url)},1000);
    };
    rows=[];
    authRequest('api/kedisiplinan?kelas_id='+encodeURIComponent(classId),{cache:'no-store'}).then(function(data){
      if(request!==version||window.page!=='discipline'||window.activeClassId!==classId||(window.currentUser||{}).id!==userId)return;
      rows=data.data||[];paint();
    }).catch(function(error){if(request===version&&window.page==='discipline'){var box=document.getElementById('storedDisciplineLoadError');if(box)box.textContent=error.message}});
  }
  window.AIWalasPages=window.AIWalasPages||{};window.AIWalasPages.discipline=renderDiscipline;
})();
