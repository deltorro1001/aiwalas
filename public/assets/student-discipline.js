(function(){
  var version=0;
  var tables=[
    {key:'etika',title:'Pelanggaran Etika',headers:['Nomor','Pelanggaran Yang Dilakukan','Tanggal Dilakukan','Disaksikan Oleh','Tindakan']},
    {key:'terlambat',title:'Keterlambatan',headers:['Nomor','Nama Siswa','NIS','Jumlah Keterlambatan','Tanggal Dilakukan','Alasan','Status Kedisiplinan']},
    {key:'atribut',title:'Pakaian & Atribut',headers:['Nomor','Nama Siswa','NIS','Pelanggaran Yang Dilakukan','Perbaikan','Status Kedisiplinan']}
  ];
  function esc(value){return escapeHtml(value==null?'':value)}
  function value(text){return text===null||text===undefined||text===''?'—':text}
  function renderStudentDiscipline(){
    if(window.currentRole!=='student')return;
    var request=++version,userId=(window.currentUser||{}).id;
    document.getElementById('pageContent').innerHTML='<div class="page student-discipline-page"><div class="page-heading"><div><div class="eyebrow">Catatan siswa</div><h1>Status Kedisiplinan</h1><p id="studentDisciplineIdentity"></p></div><button type="button" class="outline-btn" id="refreshStudentDiscipline">Muat ulang</button></div><p id="studentDisciplineError" class="login-error" role="alert"></p>'+tables.map(function(table){return '<section class="panel table-panel" style="margin-bottom:18px"><div class="panel-head"><h2>'+esc(table.title)+'</h2></div><div class="table-wrap"><table class="student-discipline-table" aria-label="'+esc(table.title)+'"><thead><tr>'+table.headers.map(function(header){return '<th scope="col">'+esc(header)+'</th>'}).join('')+'</tr></thead><tbody id="studentDiscipline_'+table.key+'"><tr><td colspan="'+table.headers.length+'">Memuat catatan...</td></tr></tbody></table></div></section>'}).join('')+'</div>';
    document.getElementById('refreshStudentDiscipline').onclick=renderStudentDiscipline;
    function active(){return request===version&&window.currentRole==='student'&&window.page==='studentDiscipline'&&(window.currentUser||{}).id===userId}
    authRequest('api/siswa/kedisiplinan',{cache:'no-store'}).then(function(result){
      if(!active())return;
      var data=result.data||{},student=data.student||{},records=data.records||{},late=data.keterlambatan||{};
      document.getElementById('studentDisciplineIdentity').textContent=(student.nama||'Siswa')+' · NIS '+(student.nis||'');
      tables.forEach(function(table){
        var record=records[table.key]||{},cells;
        if(table.key==='etika')cells=[1,record.pelanggaran,record.tanggal,record.saksi,record.tindakan];
        else if(table.key==='terlambat')cells=[1,student.nama,student.nis,late.jumlah,record.tanggal||late.tanggal,record.alasan,late.status];
        else cells=[1,student.nama,student.nis,record.pelanggaran,record.perbaikan,late.status];
        document.getElementById('studentDiscipline_'+table.key).innerHTML=table.key!=='terlambat'&&(data.kategori_bercatatan||[]).indexOf(table.key)<0?'<tr><td colspan="'+table.headers.length+'">Tidak Ada Catatan Pelanggaran</td></tr>':'<tr>'+cells.map(function(cell){return '<td>'+esc(value(cell))+'</td>'}).join('')+'</tr>';
      });
    }).catch(function(error){
      if(!active())return;
      document.getElementById('studentDisciplineError').textContent=error.message||'Catatan kedisiplinan gagal dimuat.';
      tables.forEach(function(table){document.getElementById('studentDiscipline_'+table.key).innerHTML='<tr><td colspan="'+table.headers.length+'">Catatan tidak dapat dimuat.</td></tr>'});
    });
  }
  window.AIWalasPages=window.AIWalasPages||{};window.AIWalasPages.studentDiscipline=renderStudentDiscipline;
})();
