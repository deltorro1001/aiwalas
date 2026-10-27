(function(){
  var mode='kelas',year='',month='',studentId='',context='',version=0;
  var months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  var labels=['Sakit','Izin','Alpha','Keterlambatan'],keys=['sakit','izin','alpha','terlambat'];
  function esc(v){return escapeHtml(v==null?'':String(v))}
  function ctx(){return String((window.currentUser||{}).id)+':'+window.activeClassId+':'+(window.activeSemester||'Ganjil')}
  function now(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())}
  function totals(row){return keys.map(function(key){return Number(row[key]||0)})}
  function head(){return '<tr><th>Bulan</th>'+labels.map(function(label){return '<th>'+label+'</th>'}).join('')+'</tr>'}
  function monthly(rows,total){return rows.map(function(row){return '<tr><td>'+months[row.bulan-1]+'</td>'+totals(row).map(function(n){return '<td>'+n+'</td>'}).join('')+'</tr>'}).join('')+'<tr class="class-recap-total"><th>Total</th>'+totals(total).map(function(n){return '<th>'+n+'</th>'}).join('')+'</tr>'}
  function renderRecap(){
    if(window.currentRole!=='walas')return;
    if(context!==ctx()){context=ctx();year=now().slice(0,4);month=now().slice(5,7);studentId=''}
    var request=++version,scope=context,data=null;
    var currentYear=Number(now().slice(0,4)),years=[];for(var y=2026;y<=Math.max(currentYear+10,Number(year));y++)years.push(y);
    document.getElementById('pageContent').innerHTML='<div class="page class-recap-page"><div class="page-heading"><div><div class="eyebrow">Kehadiran Siswa</div><h1>Rekap Absen</h1><p id="classRecapIdentity">Rekap bulanan dari Absensi Harian</p></div><div class="class-recap-actions"><button type="button" class="outline-btn" id="refreshClassRecap">Muat ulang</button><button type="button" class="primary-btn" id="exportClassRecap" disabled>Export Excel</button></div></div><section class="panel class-recap-controls"><div class="class-recap-tabs"><button type="button" class="outline-btn" data-recap-mode="kelas">Rekap Kelas</button><button type="button" class="outline-btn" data-recap-mode="individu">Rekap Individu</button></div><label><span>Tahun</span><select id="classRecapYear" class="filter-btn">'+years.map(function(y){return '<option'+(String(y)===year?' selected':'')+'>'+y+'</option>'}).join('')+'</select></label><label id="classRecapStudentLabel" hidden><span>Nama Siswa</span><select id="classRecapStudent" class="filter-btn" disabled><option>Memuat siswa...</option></select></label><p class="stat-note">Rekap mengikuti kelas dan semester pada panel samping. Hari tanpa catatan absensi tidak dihitung sebagai Alpha. Keterlambatan mengikuti status pada Absensi Harian.</p></section><p id="classRecapError" class="login-error" role="alert"></p><div class="grid-stats" id="classRecapStats"></div><section class="panel table-panel"><div class="panel-head"><div><h2 id="classRecapTitle">Rekap Absen Kelas per Bulan</h2><p id="classRecapCaption"></p></div></div><div class="table-wrap"><table class="class-recap-monthly-table"><thead>'+head()+'</thead><tbody id="classRecapMonthlyRows"><tr><td colspan="5">Memuat rekap...</td></tr></tbody></table></div></section><section class="panel table-panel class-recap-roster" id="classRecapRoster"><div class="panel-head"><div><h2>Rekap Setiap Siswa</h2><p>Jumlah catatan pada bulan yang dipilih</p></div><label>Bulan<select id="classRecapMonth" class="filter-btn">'+months.map(function(name,i){return '<option value="'+(i+1)+'"'+(i+1===Number(month)?' selected':'')+'>'+name+'</option>'}).join('')+'</select></label></div><div class="table-wrap"><table><thead><tr><th>No.</th><th>NIS</th><th>Nama Siswa</th>'+labels.map(function(label){return '<th>'+label+'</th>'}).join('')+'<th>Total Tidak Hadir (S/I/A)</th><th>Aksi</th></tr></thead><tbody id="classRecapStudentRows"><tr><td colspan="9">Memuat siswa...</td></tr></tbody></table></div></section></div>';
    function active(){return request===version&&window.currentRole==='walas'&&window.page==='attendanceRecapPage'&&scope===ctx()}
    function chosen(){return data.siswa.find(function(s){return String(s.id)===studentId})}
    function paint(){
      if(!data||!active())return;
      var individual=mode==='individu',student=chosen(),total=individual?(student?student.total:{}):data.total;
      document.querySelectorAll('[data-recap-mode]').forEach(function(button){button.classList.toggle('class-recap-active',button.dataset.recapMode===mode);button.setAttribute('aria-pressed',String(button.dataset.recapMode===mode))});
      document.getElementById('classRecapStudentLabel').hidden=!individual;document.getElementById('classRecapRoster').hidden=individual;
      document.getElementById('classRecapTitle').textContent=individual?'Rekap Absen Individu per Bulan':'Rekap Absen Kelas per Bulan';
      document.getElementById('classRecapCaption').textContent=individual?(student?student.nama_lengkap+' · NIS '+student.nis:'Belum ada siswa aktif.'):data.siswa.length+' siswa aktif · Akumulasi catatan seluruh kelas';
      document.getElementById('classRecapStats').innerHTML=labels.map(function(label,i){return '<div class="stat-card"><div class="stat-label">'+label+'</div><div class="stat-number">'+Number(total[keys[i]]||0)+'</div><div class="stat-note">Total tahun '+data.tahun+' · Semester '+esc(data.semester)+'</div></div>'}).join('');
      document.getElementById('classRecapMonthlyRows').innerHTML=individual&&!student?'<tr><td colspan="5">Belum ada siswa aktif pada kelas ini.</td></tr>':monthly(individual?student.bulanan:data.bulanan,total);
      document.getElementById('classRecapStudentRows').innerHTML=data.siswa.map(function(s,index){var row=s.bulanan[Number(month)-1];return '<tr><td>'+(index+1)+'</td><td>'+esc(s.nis)+'</td><td>'+esc(s.nama_lengkap)+'</td>'+totals(row).map(function(n){return '<td>'+n+'</td>'}).join('')+'<td>'+(row.sakit+row.izin+row.alpha)+'</td><td><button type="button" class="panel-action" data-recap-student="'+esc(s.id)+'">Lihat Individu</button></td></tr>'}).join('')||'<tr><td colspan="9">Belum ada siswa aktif pada kelas ini.</td></tr>';
      document.querySelectorAll('[data-recap-student]').forEach(function(button){button.onclick=function(){studentId=button.dataset.recapStudent;document.getElementById('classRecapStudent').value=studentId;mode='individu';paint()}});
    }
    document.getElementById('classRecapYear').onchange=function(){year=this.value;renderRecap()};document.getElementById('classRecapMonth').onchange=function(){month=String(this.value).padStart(2,'0');paint()};document.getElementById('classRecapStudent').onchange=function(){studentId=this.value;paint()};
    document.querySelectorAll('[data-recap-mode]').forEach(function(button){button.onclick=function(){mode=button.dataset.recapMode;paint()}});document.getElementById('refreshClassRecap').onclick=renderRecap;
    document.getElementById('exportClassRecap').onclick=function(){
      if(!data)return;var student=chosen(),individual=mode==='individu';if(individual&&!student)return;
      if(!window.XLSX){document.getElementById('classRecapError').textContent='Export Excel belum siap. Periksa koneksi lalu muat ulang halaman.';return}
      try{
        var book=XLSX.utils.book_new();book.Props={Title:'Rekap Absen '+data.kelas.nama_kelas,Subject:'Tahun '+data.tahun+' Semester '+data.semester,Author:'Aiwalas'};
        function sheet(name,title,headers,body,widths,sumColumns,extra){
          var meta=[[title],['Kelas',data.kelas.nama_kelas],['Tahun',data.tahun],['Semester',data.semester]].concat(extra||[]),headerRow=meta.length+1;
          var values=meta.concat([[],headers],body),lastData=headerRow+body.length,totalRow=lastData+1,total=['TOTAL'];
          headers.forEach(function(_,i){if(i)total[i]=''});
          sumColumns.forEach(function(column){total[column]=body.reduce(function(sum,row){return sum+Number(row[column]||0)},0)});values.push(total);
          var ws=XLSX.utils.aoa_to_sheet(values);ws['!cols']=widths.map(function(w){return {wch:w}});ws['!rows']=[];ws['!rows'][0]={hpt:28};ws['!rows'][headerRow]={hpt:24};
          ws['!merges']=[{s:{r:0,c:0},e:{r:0,c:headers.length-1}}];
          for(var r=1;r<meta.length;r++)ws['!merges'].push({s:{r:r,c:1},e:{r:r,c:headers.length-1}});
          ws['!autofilter']={ref:XLSX.utils.encode_range({s:{r:headerRow,c:0},e:{r:lastData,c:headers.length-1}})};
          sumColumns.forEach(function(column){var cell=ws[XLSX.utils.encode_cell({r:totalRow,c:column})];cell.z='0';if(body.length)cell.f='SUM('+XLSX.utils.encode_cell({r:headerRow+1,c:column})+':'+XLSX.utils.encode_cell({r:lastData,c:column})+')'});
          Object.keys(ws).forEach(function(address){if(address.charAt(0)!=='!'&&ws[address].t==='n')ws[address].z='0'});
          XLSX.utils.book_append_sheet(book,ws,name);
        }
        var monthlyRows=(individual?student.bulanan:data.bulanan).map(function(row){return [months[row.bulan-1]].concat(totals(row))});
        sheet(individual?'Rekap Individu':'Rekap Kelas',individual?'REKAP ABSEN INDIVIDU':'REKAP ABSEN KELAS',['Bulan'].concat(labels),monthlyRows,[24,16,16,16,20],[1,2,3,4],individual?[['Nama',student.nama_lengkap],['NIS',String(student.nis)]]:[]);
        if(!individual){
          var roster=data.siswa.map(function(s){var row=s.bulanan[Number(month)-1];return [String(s.nis),s.nama_lengkap].concat(totals(row)).concat([row.sakit+row.izin+row.alpha])});
          sheet('Rincian Bulan','REKAP PER SISWA',['NIS','Nama Siswa'].concat(labels).concat(['Total Tidak Hadir (S/I/A)']),roster,[20,38,14,14,14,20,28],[2,3,4,5,6],[['Bulan',months[Number(month)-1]]]);
          var detail=[];data.siswa.forEach(function(s){s.bulanan.forEach(function(row){detail.push([String(s.nis),s.nama_lengkap,row.bulan,months[row.bulan-1]].concat(totals(row)).concat([row.sakit+row.izin+row.alpha]))})});
          sheet('Data Bulanan Siswa','DATA BULANAN SELURUH SISWA',['NIS','Nama Siswa','No. Bulan','Bulan'].concat(labels).concat(['Total Tidak Hadir (S/I/A)']),detail,[20,38,14,24,14,14,14,20,28],[4,5,6,7,8]);
        }
        XLSX.writeFile(book,'Rekap_Absen_'+(individual?'Individu_'+String(student.nis).replace(/[^a-zA-Z0-9_-]/g,''):'Kelas')+'_'+data.tahun+'_'+data.semester+'.xlsx',{bookType:'xlsx',compression:true});
        document.getElementById('classRecapError').textContent='';
      }catch(error){document.getElementById('classRecapError').textContent='File Excel belum dapat dibuat. Silakan muat ulang dan coba kembali.'}

    };
    authRequest('api/absensi/rekap-kelas?kelas_id='+encodeURIComponent(window.activeClassId)+'&tahun='+encodeURIComponent(year)+'&semester='+encodeURIComponent(window.activeSemester||'Ganjil'),{cache:'no-store'}).then(function(result){
      if(!active())return;data=result;document.getElementById('classRecapIdentity').textContent='Kelas '+result.kelas.nama_kelas+' · Tahun '+result.tahun+' · Semester '+result.semester;
      if(!result.siswa.some(function(s){return String(s.id)===studentId}))studentId=result.siswa.length?String(result.siswa[0].id):'';
      var select=document.getElementById('classRecapStudent');select.innerHTML=result.siswa.map(function(s){return '<option value="'+esc(s.id)+'"'+(String(s.id)===studentId?' selected':'')+'>'+esc(s.nama_lengkap)+' · '+esc(s.nis)+'</option>'}).join('')||'<option>Belum ada siswa</option>';select.disabled=!result.siswa.length;
      document.getElementById('exportClassRecap').disabled=false;paint();
    }).catch(function(error){if(active()){document.getElementById('classRecapError').textContent=error.message;document.getElementById('classRecapMonthlyRows').innerHTML='<tr><td colspan="5">Rekap belum dapat dimuat. Tekan Muat ulang untuk mencoba kembali.</td></tr>';document.getElementById('classRecapStudentRows').innerHTML='<tr><td colspan="9">Rekap belum dapat dimuat.</td></tr>'}});
  }
  function open(view){var next=ctx();if(context!==next)studentId='';context=next;var date=window.AIWalasAttendance?window.AIWalasAttendance.selectedDate():now();year=date.slice(0,4);month=date.slice(5,7);mode=view||'kelas';window.page='attendanceRecapPage';window.render()}
  function links(){if(window.currentRole!=='walas'||window.page!=='attendance')return;var button=document.getElementById('attendanceRecap');if(button){button.textContent='Rekap Individu';button.onclick=function(){open('individu')}}}
  window.names.attendanceRecapPage='Rekap Absen';window.roleAccess.walas=(window.roleAccess.walas||[]).concat(['attendanceRecapPage']);window.AIWalasPages=window.AIWalasPages||{};window.AIWalasPages.attendanceRecapPage=renderRecap;window.AIWalasClassRecap={open:open};
  var previous=window.render;window.render=function(){var result=previous.apply(this,arguments);links();return result};setTimeout(links,0);
})();
