(function(){
  var rows=[],kind='etika',version=0,statusOptions=[];
  var titles={etika:'Pelanggaran Etika',terlambat:'Keterlambatan',atribut:'Pakaian & Atribut'};
  var fields={etika:['pelanggaran','tanggal','saksi','alasan','tindakan'],terlambat:['jumlah_terlambat','tanggal','alasan'],atribut:['pelanggaran','tanggal','perbaikan']};
  var columnWidths={etika:[16,7,16,10,10,10,12,13,6],terlambat:[20,8,9,13,23,19,8],atribut:[19,8,22,12,16,16,7]};
  var labels={pelanggaran:'Pelanggaran Yang Dilakukan',tanggal:'Tanggal Dilakukan',saksi:'Disaksikan Oleh',alasan:'Alasan',tindakan:'Tindakan',perbaikan:'Perbaikan',jumlah_terlambat:'Jumlah Keterlambatan'};
  function esc(value){return escapeHtml(value==null?'':value)}
  function formatDate(value){return /^\d{4}-\d{2}-\d{2}$/.test(value)?value.split('-').reverse().join('-'):value}
  function datesFor(student){var dates=(student.tanggal_pelanggaran||{})[kind];if(!dates){var record=student.records[kind]||{};dates=[record.tanggal||(kind==='terlambat'?student.keterlambatan.tanggal:null)].filter(Boolean)}return dates.map(formatDate).join('\n')||' - '}
  function reasonsFor(student){var dates=(student.tanggal_pelanggaran||{}).terlambat||[],reasons=student.alasan_keterlambatan||{};return dates.map(function(date){return reasons[date]||'Belum diisi'}).join('\n')||' - '}
  function cells(student){var record=student.records[kind]||{},late=student.keterlambatan;return [student.nama_lengkap,student.nis].concat(fields[kind].map(function(field){return field==='jumlah_terlambat'?late.jumlah:field==='tanggal'?datesFor(student):field==='alasan'&&kind==='terlambat'?reasonsFor(student):(record[field]||' - ')})).concat([(student.status_kedisiplinan||{})[kind]||(kind==='terlambat'?late.status:'Baik')])}
  function cellHtml(value,index){return (index===2+fields[kind].indexOf('tanggal')||(kind==='terlambat'&&index===2+fields[kind].indexOf('alasan')))?'<td class="discipline-event-dates">'+String(value).split('\n').map(esc).join('<br>')+'</td>':'<td>'+esc(value)+'</td>'}
  function headers(){return ['Nama Siswa','NIS'].concat(fields[kind].map(function(field){return labels[field]})).concat(['Status Kedisiplinan'])}
  function studentRowsHtml(student,withAction){
    if(kind!=='terlambat')return '<tr>'+cells(student).map(cellHtml).join('')+(withAction?'<td>'+editButton(student)+'</td>':'')+'</tr>';
    var dates=(student.tanggal_pelanggaran||{}).terlambat||[],reasons=student.alasan_keterlambatan||{},late=student.keterlambatan;
    if(!dates.length)dates=[''];
    return dates.map(function(date,index){
      var span=' rowspan="'+dates.length+'"';
      return '<tr data-discipline-student="'+esc(student.id)+'">'+(index===0?'<td'+span+'>'+esc(student.nama_lengkap)+'</td><td'+span+'>'+esc(student.nis)+'</td><td'+span+'>'+esc(late.jumlah)+'</td>':'')+'<td class="discipline-event-dates">'+esc(date?formatDate(date):' - ')+'</td><td class="discipline-event-reason-cell">'+esc(date?(reasons[date]||'Belum diisi'):' - ')+'</td>'+(index===0?'<td'+span+'>'+esc(late.status)+'</td>'+(withAction?'<td'+span+'>'+editButton(student)+'</td>':''):'')+'</tr>';
    }).join('');
  }
  function editButton(student){return '<button type="button" class="panel-action" data-stored-discipline="'+esc(student.id)+'">Edit</button>'}
  function paint(){
    var body=document.getElementById('storedDisciplineRows');if(!body)return;
    document.getElementById('storedDisciplineCols').innerHTML=columnWidths[kind].map(function(width){return '<col style="width:'+width+'%">'}).join('');
    document.getElementById('storedDisciplineHead').innerHTML='<tr>'+headers().concat(['Aksi']).map(function(label){return '<th>'+esc(label)+'</th>'}).join('')+'</tr>';
    body.innerHTML=rows.map(function(student){return studentRowsHtml(student,true)}).join('')||'<tr><td colspan="9">Belum ada siswa.</td></tr>';
    body.querySelectorAll('[data-stored-discipline]').forEach(function(button){button.onclick=function(){edit(rows.find(function(student){return String(student.id)===button.dataset.storedDiscipline}))}});
  }
  function edit(student){
    var record=student.records[kind]||{},category=kind,classId=window.activeClassId,userId=(window.currentUser||{}).id;
    var manual=[];try{manual=JSON.parse(record.tanggal_kejadian||'[]')}catch(error){}if(record.tanggal)manual.push(record.tanggal);manual=Array.from(new Set(manual)).sort();if(category==='terlambat')manual=((student.tanggal_pelanggaran||{}).terlambat||manual).slice();var countTouched=false;
    modal('Edit '+titles[category],'Catatan disimpan dan ditampilkan pada Beranda siswa yang bersangkutan.','<form>'+fields[category].map(function(field){
      if(field==='alasan'&&category==='terlambat')return '';
      if(field==='tanggal')return '<div class="discipline-date-editor"><strong>Tanggal Dilakukan</strong><p class="stat-note">Tambahkan satu tanggal untuk setiap kejadian. Tanggal ditampilkan dalam format dd-mm-yyyy.'+(category==='terlambat'?' Isi alasan pada masing-masing tanggal keterlambatan. Semua tanggal dari absensi ikut ditampilkan dan bisa dikoreksi atau dihapus dari catatan kedisiplinan. Jumlah mengikuti tanggal yang diisi dan tetap bisa dikoreksi manual.':'')+'</p><div id="storedDisciplineDates"></div><button type="button" class="outline-btn" id="addDisciplineDate">Tambah Tanggal</button></div>';
      var value=field==='jumlah_terlambat'?student.keterlambatan.jumlah:(record[field]||'');return '<label>'+labels[field]+(['jumlah_terlambat','saksi'].indexOf(field)>=0?'<input id="storedDiscipline_'+field+'" type="'+(field==='jumlah_terlambat'?'number':'text')+'" '+(field==='jumlah_terlambat'?'min="0" max="10000" step="1" required ':'')+'value="'+esc(value)+'">':'<textarea id="storedDiscipline_'+field+'" maxlength="2000">'+esc(value)+'</textarea>')+'</label>';
    }).join('')+(category!=='terlambat'?'<label>Status Kedisiplinan<select id="storedDisciplineStatus">'+statusOptions.map(function(status){return '<option value="'+esc(status)+'"'+(status===((student.status_kedisiplinan||{})[category]||'Baik')?' selected':'')+'>'+esc(status)+'</option>'}).join('')+'</select></label><p class="stat-note">Status berlaku hanya untuk '+esc(titles[category])+'. Baik berlaku jika tidak ada catatan pelanggaran.</p>':'')+(category==='terlambat'?'<p class="stat-note">Perubahan ini berlaku untuk catatan Status Kedisiplinan. Untuk mengubah status kehadiran, gunakan Absensi Harian.</p>':'')+'<div class="discipline-delete-confirm" id="disciplineDeleteConfirm" hidden><p>Hapus seluruh catatan '+esc(titles[category])+' untuk '+esc(student.nama_lengkap)+'? Isian catatan akan dikosongkan.</p><button type="button" class="discipline-delete-button" id="confirmDeleteDiscipline">Ya, Hapus Catatan</button><button type="button" class="filter-btn" id="cancelDeleteDiscipline">Batal Hapus</button></div><p id="storedDisciplineError" class="login-error" role="alert"></p><div class="modal-actions"><button type="button" class="discipline-delete-button" id="deleteStoredDiscipline">Hapus Catatan</button><button type="button" class="filter-btn" id="cancelModal">Batal</button><button type="button" class="primary-btn" id="saveStoredDiscipline">Simpan</button></div></form>');
    var statusSelect=document.getElementById('storedDisciplineStatus');
    if(statusSelect)statusSelect.closest('form').addEventListener('input',function(event){if(event.target===statusSelect)return;var hasContent=Array.from(this.querySelectorAll('textarea,input')).some(function(input){return input.value.trim()!==''});if(hasContent&&statusSelect.value==='Baik')statusSelect.value='Peringatan 1'});
    var number=document.getElementById('storedDiscipline_jumlah_terlambat');if(number)number.oninput=function(){countTouched=true};
    function syncCount(){if(number&&!countTouched)number.value=Array.from(new Set(Array.from(document.querySelectorAll('#storedDisciplineDates input')).map(function(input){return input.value}).filter(Boolean))).length}
    function addDate(value){
      var wrap=document.getElementById('storedDisciplineDates');if(wrap.children.length>=500)return;
      var row=document.createElement('div');row.className='discipline-date-entry';
      var reason=(student.alasan_keterlambatan||{})[value]||'';
      row.innerHTML='<div class="discipline-date-input-row"><input class="discipline-event-date-input" type="date" aria-label="Tanggal kejadian" value="'+esc(value||'')+'"><span class="stat-note"></span><button type="button" class="panel-action">Hapus</button></div>'+(category==='terlambat'?'<label>Alasan keterlambatan<textarea class="discipline-event-reason" maxlength="2000" rows="2" placeholder="Tuliskan alasan untuk tanggal ini (opsional)">'+esc(reason)+'</textarea></label>':'');
      var input=row.querySelector('input'),preview=row.querySelector('span');input.onchange=function(){preview.textContent=formatDate(input.value);syncCount()};preview.textContent=formatDate(input.value);row.querySelector('button').onclick=function(){row.remove();syncCount()};wrap.appendChild(row);
    }
    (manual.length?manual:['']).forEach(addDate);document.getElementById('addDisciplineDate').onclick=function(){addDate('')};
    function persist(payload,button,deleted){
      button.disabled=true;
      authRequest('api/kedisiplinan',{method:'POST',body:JSON.stringify(payload)}).then(function(){
        if((window.currentUser||{}).id!==userId||window.activeClassId!==classId)return;
        closeModal();toast(deleted?'Catatan kedisiplinan dihapus.':'Catatan kedisiplinan disimpan.');if(window.page==='discipline')renderDiscipline();
      }).catch(function(error){var box=document.getElementById('storedDisciplineError');if(box)box.textContent=error.message;button.disabled=false});
    }
    document.getElementById('saveStoredDiscipline').onclick=function(){
      var payload={siswa_id:student.id,kategori:category};
      var seen={};document.querySelectorAll('#storedDisciplineDates .discipline-date-entry').forEach(function(row){var input=row.querySelector('input'),reason=row.querySelector('textarea');input.setCustomValidity(input.value&&seen[input.value]?'Tanggal ini sudah diisi. Gunakan satu alasan per tanggal.':!input.value&&reason&&reason.value.trim()?'Isi tanggal untuk alasan keterlambatan ini.':'');if(input.value)seen[input.value]=true});
      if(!this.closest('form').reportValidity())return;
      fields[category].forEach(function(field){if(field!=='tanggal'&&!(category==='terlambat'&&field==='alasan'))payload[field]=document.getElementById('storedDiscipline_'+field).value});payload.tanggal_kejadian=Array.from(document.querySelectorAll('#storedDisciplineDates input')).map(function(input){return input.value}).filter(Boolean);
      if(category==='terlambat'){
        payload.koreksi_keterlambatan=true;payload.alasan_per_tanggal={};
        document.querySelectorAll('#storedDisciplineDates .discipline-date-entry').forEach(function(row){var date=row.querySelector('input').value;if(date)payload.alasan_per_tanggal[date]=row.querySelector('textarea').value});
      }
      if(category!=='terlambat')payload.status_kedisiplinan=document.getElementById('storedDisciplineStatus').value;
      persist(payload,this,false);
    };
    document.getElementById('deleteStoredDiscipline').onclick=function(){document.getElementById('disciplineDeleteConfirm').hidden=false};
    document.getElementById('cancelDeleteDiscipline').onclick=function(){document.getElementById('disciplineDeleteConfirm').hidden=true};
    document.getElementById('confirmDeleteDiscipline').onclick=function(){
      var payload={siswa_id:student.id,kategori:category,tanggal_kejadian:[]};
      fields[category].forEach(function(field){if(field!=='tanggal')payload[field]=field==='jumlah_terlambat'?0:''});
      if(category==='terlambat'){payload.koreksi_keterlambatan=true;payload.alasan_per_tanggal={}}
      if(category!=='terlambat')payload.status_kedisiplinan='Baik';
      persist(payload,this,true);
    };
  }
  function renderDiscipline(){
    if(window.currentRole!=='walas')return;
    var request=++version,classId=window.activeClassId,userId=(window.currentUser||{}).id;
    document.getElementById('pageContent').innerHTML='<div class="page discipline-page"><div class="page-heading"><div><div class="eyebrow">REPORT</div><h1>Status Kedisiplinan</h1><p>Pantauan Kedisiplinan Siswa</p></div><div class="discipline-actions"><select id="storedDisciplineCategory" class="filter-btn">'+Object.keys(titles).map(function(key){return '<option value="'+key+'"'+(key===kind?' selected':'')+'>'+esc(titles[key])+'</option>'}).join('')+'</select><button type="button" id="exportStoredDiscipline" class="outline-btn">Export</button></div></div><p id="storedDisciplineLoadError" class="login-error" role="alert"></p><section class="panel table-panel"><div class="table-wrap"><table class="discipline-records-table"><colgroup id="storedDisciplineCols"></colgroup><thead id="storedDisciplineHead"></thead><tbody id="storedDisciplineRows"><tr><td>Memuat catatan...</td></tr></tbody></table></div></section></div>';
    document.getElementById('storedDisciplineCategory').onchange=function(){kind=this.value;paint()};
    document.getElementById('exportStoredDiscipline').onclick=function(){
      var html='<table><tr>'+headers().map(function(label){return '<th>'+esc(label)+'</th>'}).join('')+'</tr>'+rows.map(function(student){return studentRowsHtml(student,false)}).join('')+'</table>',url=URL.createObjectURL(new Blob(['\ufeff<html><meta charset="utf-8"><body>'+html+'</body></html>'],{type:'application/vnd.ms-excel'})),link=document.createElement('a');link.href=url;link.download='Status_Kedisiplinan_'+kind+'.xls';link.click();setTimeout(function(){URL.revokeObjectURL(url)},1000);
    };
    rows=[];
    authRequest('api/kedisiplinan?kelas_id='+encodeURIComponent(classId),{cache:'no-store'}).then(function(data){
      if(request!==version||window.page!=='discipline'||window.activeClassId!==classId||(window.currentUser||{}).id!==userId)return;
      rows=data.data||[];statusOptions=data.status_options||[];paint();
    }).catch(function(error){if(request===version&&window.page==='discipline'){var box=document.getElementById('storedDisciplineLoadError');if(box)box.textContent=error.message}});
  }
  window.AIWalasPages=window.AIWalasPages||{};window.AIWalasPages.discipline=renderDiscipline;
})();
