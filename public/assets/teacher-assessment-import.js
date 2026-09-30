(function(){
  var previous=window.render;
  function setup(){
    if(window.page!=='assessment') return;
    var row=document.querySelector('.assessment-save-row');
    if(!row||document.getElementById('importAssessment')) return;
    var box=document.createElement('span'); box.className='assessment-import-controls';
    box.innerHTML='<select id="importAssessmentColumn" class="filter-btn" aria-label="Pilih kolom nilai"><option>UH1</option><option>UH2</option><option>UH3</option><option>UH4</option><option>Tugas1</option><option>Tugas2</option><option>Tugas3</option><option>Tugas4</option><option>PTS</option><option>PAS</option></select><button type="button" class="outline-btn" id="importAssessment">Import Nilai</button><input id="assessmentFileInput" type="file" accept=".xls,.xlsx" hidden>';
    row.insertBefore(box,row.querySelector('#saveAssessment'));
    var button=document.getElementById('importAssessment'), input=document.getElementById('assessmentFileInput');
    button.onclick=function(){input.click()};
    input.onchange=function(){
      var file=input.files&&input.files[0], column=(document.getElementById('importAssessmentColumn')||{}).value;
      if(!file)return;
      if(!window.XLSX){toast('Pembaca Excel belum tersedia. Periksa koneksi internet lalu coba lagi.');return}
      file.arrayBuffer().then(function(buffer){
        var workbook=XLSX.read(buffer,{type:'array'}), sheet=workbook.Sheets[workbook.SheetNames[0]], rows=XLSX.utils.sheet_to_json(sheet,{header:1,defval:''});
        if(!rows.length)throw new Error('File Excel kosong.');
        var norm=function(v){return String(v==null?'':v).toLowerCase().replace(/[\s_.-]/g,'')}, headerIndex=rows.findIndex(function(row){return row.map(norm).some(function(h){return h==='nis'||h==='nomorinduk'||h==='nomorinduksiswa'||h!=='nisn'&&h.indexOf('nis')>=0})}), header=headerIndex>=0?rows.splice(0,headerIndex+1).pop().map(norm):[], find=function(names){return header.findIndex(function(v){return names.indexOf(v)>=0||v!=='nisn'&&names.some(function(n){return v.indexOf(n)>=0})})};
        var ni=find(['nis','nomorinduk','nomorinduksiswa']);if(ni<0){var known={};(window.students||[]).forEach(function(st){known[String(st[1]).trim()]=1});for(var niCol=0;niCol<10&&ni<0;niCol++){var hits=rows.reduce(function(n,r){return n+(known[String(r[niCol]==null?'':r[niCol]).trim()]?1:0)},0);if(hits>0)ni=niCol}}var ci=find([norm(column),norm(column.replace('Tugas','Tugas ')),norm(column.replace('UH','UH '))]); if(ci<0)ci=find(['nilai','score','value']); if(ci<0)ci=header.findIndex(function(v,i){return i!==ni});
        if(ni<0||ci<0)throw new Error('Format Excel harus memiliki kolom NIS dan '+column+'.');
        var matched=0;
        rows.forEach(function(data){
          var nis=String(data[ni]==null?'':data[ni]).trim(); if(!nis)return;
          var target=Array.prototype.slice.call(document.querySelectorAll('.assessment-score[data-field="'+column+'"]')).find(function(el){var small=el.closest('tr').querySelector('.score-name small');return small&&small.textContent.replace(/^NIS:\\s*/i,'').trim()===nis});
          if(!target)return;
          var raw=data[ci]; if(raw===''||raw==null||isNaN(Number(raw)))return;
          target.value=Math.max(0,Math.min(100,Math.round(Number(raw)))); target.dispatchEvent(new Event('input',{bubbles:true})); matched++;
        });
        sessionStorage.setItem('aiwalas.assessment.started','1');toast(matched+' nilai '+column+' berhasil diimpor.');
      }).catch(function(error){toast(error.message||'File Excel tidak dapat dibaca.')}).finally(function(){input.value=''});
    };
  }
  window.render=function(){var result=previous.apply(this,arguments);setTimeout(setup,0);return result};
  setTimeout(setup,0);
})();