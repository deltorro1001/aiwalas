(function(){
  var defaultStatuses=['Sakit','Izin','Terlambat'];
  var monthNames=['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'];
  function normalize(value){return String(value||'').normalize('NFKD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,' ').trim().replace(/\s+/g,' ').replace(/\b(izin|sakit|terlambat|keterlambatan|absensi|kehadiran)nya\b/g,'$1')}
  function esc(value){return escapeHtml(value==null?'':String(value))}
  function dateLabel(value){return value.split('-').reverse().join('-')}
  function attendanceQuestion(value){return /\b(sakit|izin|terlambat|keterlambatan|telat|absen|absensi|kehadiran|alpha|alpa|hadir)\b/i.test(normalize(value))&&!/(penyakit|riwayat kesehatan|alamat|nomor (?:hp|telepon)|surat izin)/i.test(value)}
  function selectedStudents(q,students){
    var needle=' '+q+' ',stop=/^(sakit|izin|hadir|alpha|alpa|libur|menunggu|keterangan|siswa|kelas|anak|nama|yang|dan|hari|bulan|tahun)$/;
    var matches=students.map(function(student){
      if(needle.indexOf(' '+normalize(student.nis)+' ')>=0)return {student:student,phrase:normalize(student.nis)};
      var tokens=normalize(student.nama).split(' '),phrase='';
      for(var length=tokens.length;length>=1&&!phrase;length--)for(var start=0;start+length<=tokens.length;start++){
        var text=tokens.slice(start,start+length).join(' ');
        if(text.length>=3&&!stop.test(text)&&needle.indexOf(' '+text+' ')>=0){phrase=text;break}
      }
      return phrase?{student:student,phrase:phrase}:null;
    }).filter(Boolean);
    matches=matches.filter(function(item){return !matches.some(function(other){return other.phrase!==item.phrase&&(' '+other.phrase+' ').indexOf(' '+item.phrase+' ')>=0})});
    var ambiguous=matches.some(function(item){return matches.some(function(other){return other.student.id!==item.student.id&&other.phrase===item.phrase})});
    return {rows:matches.map(function(item){return item.student}),ambiguous:ambiguous};
  }
  function range(q,period){
    var start=period.mulai,end=period.sampai,parts=end.split('-');
    if(/\bhari ini\b/.test(q))start=end;
    else if(/\bkemarin\b/.test(q)){var d=new Date(end+'T12:00:00Z');d.setUTCDate(d.getUTCDate()-1);start=end=d.toISOString().slice(0,10)}
    else{
      var match=q.match(new RegExp('(?:bulan|pada) ('+monthNames.join('|')+')(?: (20[0-9]{2}))?')),month='',year='';
      if(/\bbulan ini\b/.test(q)){month=parts[1];year=parts[0]}
      else if(match){month=String(monthNames.indexOf(match[1])+1).padStart(2,'0');year=match[2]||parts[0]}
      else{var y=q.match(/\btahun (20[0-9]{2})\b/);if(y)year=y[1]}
      if(year){start=year+'-'+(month||'01')+'-01';var last=month?new Date(Date.UTC(Number(year),Number(month),0)).toISOString().slice(0,10):year+'-12-31';end=last<end?last:end;}
    }
    if(start<period.mulai)start=period.mulai;
    return {mulai:start,sampai:end};
  }
  function analyze(question,data){
    var q=normalize(question),statuses=[];
    if(/\bsakit\b/.test(q))statuses.push('Sakit');if(/\bizin\b/.test(q))statuses.push('Izin');if(/\b(terlambat|keterlambatan|telat)\b/.test(q))statuses.push('Terlambat');if(/\b(alpha|alpa)\b/.test(q))statuses.push('Alpha');
    if(/\bhadir\b/.test(q)&&!/\btidak hadir\b/.test(q))statuses.push('Hadir');
    if(/\btidak hadir\b/.test(q)&&!statuses.length)statuses=['Sakit','Izin','Alpha'];
    if(!statuses.length)statuses=defaultStatuses.slice();
    var selected=selectedStudents(q,data.siswa),period=range(q,data.periode);
    if(selected.ambiguous)return {message:'Nama belum spesifik. Gunakan nama lengkap atau NIS: '+selected.rows.map(function(s){return s.nama+' (NIS '+s.nis+')'}).join('; ')+'.'};
    if(period.mulai>period.sampai)return {message:'Periode tersebut belum tersedia. Data yang dapat diperiksa adalah '+dateLabel(data.periode.mulai)+' sampai '+dateLabel(data.periode.sampai)+'.'};
    // Do not silently treat an unknown student's name as a request for the entire class.
    var ignored=new Set(('berapa berapakah jumlah kali hari ke kali ini itu semua seluruh masing masingnya per setiap siswa siswi anak ananda nama nis kelas rekap rekapitulasi absen absensi kehadiran sakit izin terlambat keterlambatan telat alpha alpa hadir tidak belum tanpa pernah yang dengan dari lebih kurang sama di atas bawah minimal maksimal setidaknya paling sedikit banyak sering tertinggi terbanyak terendah rendah urutkan urutan daftar tampilkan siapa saja tolong cek periksa lihat rincian detail tanggal kapan riwayat catatan sejak mulai hingga sampai saat pertanyaan diajukan hari bulan tahun pada kemarin untuk tentang bagaimana berapa orang bandingkan antara dan atau total data lakukan melakukan mengalami sudah mendapatkan mendapat mempunyai memiliki apakahnya apakah sekarang sd s d ranking peringkat berdasarkan sebutkan tunjukkan berapa banyaknya seringnya akumulasi akumulasinya alasan keterangan kenapa mengapa bulanan bulannya selama').split(' ').concat(monthNames,normalize(data.kelas.nama_kelas).split(' ')));
    var unknown=q.split(' ').filter(function(word){return word.length>=3&&!/^\d+$/.test(word)&&!ignored.has(word)});
    if(!selected.rows.length&&unknown.length)return {message:'Nama/NIS atau maksud pertanyaan belum dikenali. Contoh: “Berapa kali Kanaya terlambat?” atau “Siapa saja yang izin lebih dari 2 kali?”.'};
    var rows=(selected.rows.length?selected.rows:data.siswa).map(function(student){
      var history=student.riwayat.filter(function(row){return row.tanggal>=period.mulai&&row.tanggal<=period.sampai}),counts={};
      statuses.forEach(function(status){counts[status]=history.filter(function(row){return row.status===status}).length});
      return {id:student.id,nama:student.nama,nis:student.nis,jumlah:counts,riwayat:history,score:statuses.reduce(function(n,status){return n+counts[status]},0)};
    });
    var threshold=q.match(/(?:minimal|setidaknya|paling sedikit|lebih dari sama dengan) (\d+)/),symbol=question.match(/(>=|≥|<=|≤|>|<|=)\s*(\d+)/),test=null;
    if(symbol){threshold=symbol;var limit=Number(symbol[2]),op=symbol[1];test=function(n){return op==='>='||op==='≥'?n>=limit:op==='<='||op==='≤'?n<=limit:op==='>'?n>limit:op==='<'?n<limit:n===limit}}
    else if(threshold)test=function(n){return n>=Number(threshold[1])};
    else if((threshold=q.match(/(?:maksimal|tidak lebih dari|paling banyak) (\d+)/)))test=function(n){return n<=Number(threshold[1])};
    else if((threshold=q.match(/(?:lebih dari|di atas) (\d+)/)))test=function(n){return n>Number(threshold[1])};
    else if((threshold=q.match(/(?:kurang dari|di bawah) (\d+)/)))test=function(n){return n<Number(threshold[1])};
    else if((threshold=q.match(/(?:sebanyak|tepat) (\d+)|(\d+) kali/)))test=function(n){return n===Number(threshold[1]||threshold[2])};
    var zero=/\b(?:tidak|belum) pernah\b|\btanpa\b/.test(q),rank=/\bpaling (?:sering|banyak)|\btertinggi|\bterbanyak/.test(q)&&!threshold;
    if(test)rows=rows.filter(function(row){return test(row.score)});
    else if(zero)rows=rows.filter(function(row){return row.score===0});
    else if(!selected.rows.length&&(/\bsiapa\b|\bpernah\b/.test(q)||rank))rows=rows.filter(function(row){return row.score>0});
    if(rank){var max=Math.max.apply(null,rows.map(function(row){return row.score}));rows=rows.filter(function(row){return row.score===max})}
    if(/\burutkan|\branking|\bperingkat/.test(q)||rank)rows.sort(function(a,b){return b.score-a.score||a.nama.localeCompare(b.nama,'id')});
    var totals={};statuses.forEach(function(status){totals[status]=rows.reduce(function(n,row){return n+row.jumlah[status]},0)});
    return {bulanan:/\bbulanan|\b(?:per|setiap|masing masing) bulan\b/.test(q),periode:period,statuses:statuses,rows:rows,total:totals,detail:selected.rows.length>0||/\btanggal|\bkapan|\briwayat|\brincian|\bdetail|\balasan|\bketerangan|\bkenapa|\bmengapa/.test(q),kelas:data.kelas.nama_kelas,diperiksa:data.periode.diperiksa_pada};
  }
  function html(result){
    if(result.message)return '<p>'+esc(result.message)+'</p>';
    var out='<h3>Rekap Absensi Kelas '+esc(result.kelas)+'</h3><p class="attendance-question-period">'+dateLabel(result.periode.mulai)+' sampai '+dateLabel(result.periode.sampai)+' (WIB)</p><p>'+result.rows.length+' siswa sesuai pertanyaan.</p>';
    if(!result.rows.length)return out+'<p>Tidak ada siswa yang memenuhi kriteria tersebut pada catatan absensi.</p>';
    out+='<div class="table-wrap"><table class="attendance-question-table"><thead><tr><th>Nama Siswa</th>'+result.statuses.map(function(status){return '<th>'+esc(status)+'<small>'+(status==='Terlambat'?'kejadian':'hari')+'</small></th>'}).join('')+'</tr></thead><tbody>'+result.rows.map(function(row){return '<tr><td><strong>'+esc(row.nama)+'</strong><small>NIS '+esc(row.nis)+'</small></td>'+result.statuses.map(function(status){return '<td>'+row.jumlah[status]+'</td>'}).join('')+'</tr>'}).join('')+'</tbody><tfoot><tr><th>Total</th>'+result.statuses.map(function(status){return '<th>'+result.total[status]+'</th>'}).join('')+'</tr></tfoot></table></div>';
    if(result.bulanan){
      var monthKeys=[],cursor=new Date(result.periode.mulai.slice(0,7)+'-01T12:00:00Z');
      while(cursor.toISOString().slice(0,10)<=result.periode.sampai){monthKeys.push(cursor.toISOString().slice(0,7));cursor.setUTCMonth(cursor.getUTCMonth()+1)}
      out+='<div class="attendance-question-months">'+result.rows.map(function(student){return '<details'+(result.rows.length===1?' open':'')+'><summary>Rekap Bulanan '+esc(student.nama)+'</summary><div class="table-wrap"><table class="attendance-question-table"><thead><tr><th>Bulan</th>'+result.statuses.map(function(status){return '<th>'+esc(status)+'</th>'}).join('')+'</tr></thead><tbody>'+monthKeys.map(function(month){return '<tr><td>'+esc(monthNames[Number(month.slice(5))-1])+' '+month.slice(0,4)+'</td>'+result.statuses.map(function(status){return '<td>'+student.riwayat.filter(function(row){return row.tanggal.slice(0,7)===month&&row.status===status}).length+'</td>'}).join('')+'</tr>'}).join('')+'</tbody></table></div></details>'}).join('')+'</div>';
    }
    if(result.detail)out+='<div class="attendance-question-details">'+result.rows.map(function(student){return '<details'+(result.rows.length===1?' open':'')+'><summary>Rincian '+esc(student.nama)+'</summary>'+result.statuses.map(function(status){var dates=student.riwayat.filter(function(row){return row.status===status});return '<p data-attendance-status="'+esc(status)+'"><b>'+esc(status)+':</b> '+(dates.length?dates.map(function(row){return '<span data-attendance-date>'+dateLabel(row.tanggal)+(status==='Terlambat'&&row.waktu_scan?' ('+esc(row.waktu_scan)+' WIB)':'')+'</span>'+((row.keterangan||'').trim()?' — '+esc(row.keterangan):'')}).join(', '):'Tidak ada catatan')+'</p>'}).join('')+'</details>'}).join('')+'</div>';
    return out+'<p class="stat-note">Sakit dan Izin dihitung per hari tercatat; Terlambat per kejadian harian. Hari tanpa catatan tidak dianggap Alpha. Data mengikuti koreksi pada Absensi Harian, melintasi semester.</p><small class="stat-note">Diperiksa '+esc(result.diperiksa)+' WIB</small>';
  }
  var previous=window.helpQuestion;
  window.helpQuestion=function(){
    previous.apply(this,arguments);if(window.currentRole!=='walas')return;
    var input=document.getElementById('helpQuestionText'),button=document.getElementById('sendHelpQuestion'),fallback=button.onclick;
    input.placeholder='Contoh: Berapa kali Kanaya terlambat?';
    button.onclick=function(){
      var question=input.value.trim();if(!question||!attendanceQuestion(question))return fallback.apply(this,arguments);
      var user=(window.currentUser||{}).id,classId=window.activeClassId,form=input.closest('form');
      function active(){return form.isConnected&&window.currentRole==='walas'&&(window.currentUser||{}).id===user&&window.activeClassId===classId&&input.value.trim()===question}
      var error=document.getElementById('helpError');error.textContent='';button.disabled=true;button.textContent='Memeriksa rekap...';
      var box=document.getElementById('helpAnswer');if(!box){box=document.createElement('div');box.id='helpAnswer';form.insertBefore(box,form.querySelector('.modal-actions'))}box.className='attendance-question-answer';box.textContent='Memeriksa data absensi...';document.getElementById('modalContent').classList.add('attendance-question-modal');
      authRequest('api/absensi/tanya?kelas_id='+encodeURIComponent(classId),{cache:'no-store'}).then(function(data){if(active()){box.innerHTML='<strong>Jawaban AIWalas</strong>'+html(analyze(question,data));if(window.AIWalasAttendanceColors)window.AIWalasAttendanceColors.refresh()}}).catch(function(reason){if(active()){box.textContent='Rekap belum dapat diperiksa.';error.textContent=reason.message}}).finally(function(){if(button.isConnected){button.disabled=false;button.textContent='Kirim'}});
    };
  };
  var helpButton=document.getElementById('helpQuestion');if(helpButton)helpButton.onclick=window.helpQuestion;
  window.AIWalasAttendanceQuestions={analyze:analyze,isQuestion:attendanceQuestion};
})();
