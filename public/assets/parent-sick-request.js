(function(){
  function esc(value){return escapeHtml(value==null?'':String(value))}
  function today(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())}
  function dateValue(value){return /^\d{4}-\d{2}-\d{2}$/.test(value)?new Date(value+'T00:00:00Z'):null}
  function label(value){var date=dateValue(value);return date&&!isNaN(date)?new Intl.DateTimeFormat('id-ID',{weekday:'long',day:'numeric',month:'long',year:'numeric',timeZone:'UTC'}).format(date):value}
  function history(card,rows,parent){
    var box=card.querySelector('.sick-request-history');
    box.innerHTML=rows.length?rows.map(function(row){return '<article class="sick-request-entry"><div class="sick-request-entry-head"><strong>'+esc(parent?row.jumlah_hari+' hari istirahat':row.nama_lengkap+' · '+row.nis)+'</strong><span class="pill teal">Terkirim</span></div><p data-attendance-date data-attendance-status="Sakit">'+esc(label(row.tanggal_mulai))+' sampai '+esc(label(row.tanggal_selesai))+(parent?'':' · '+esc(row.jumlah_hari)+' hari')+'</p>'+(row.keterangan?'<p class="sick-request-note">'+esc(row.keterangan)+'</p>':'')+(row.lampiran_url?'<a class="panel-action" href="'+esc(row.lampiran_url)+'">Unduh surat sakit: '+esc(row.nama_lampiran)+'</a>':'<small class="stat-note">Tanpa lampiran surat sakit</small>')+'<small class="stat-note sick-request-stamp">Dikirim '+esc(row.dibuat_pada)+' WIB</small></article>'}).join(''):'<p class="summary-empty">'+(parent?'Belum ada pengajuan istirahat sakit.':'Belum ada pengajuan istirahat sakit dari orangtua di kelas ini.')+'</p>';
  }
  function load(card,parent){
    var user=(window.currentUser||{}).id,classId=window.activeClassId;
    function active(){return card.isConnected&&(window.currentUser||{}).id===user&&window.activeClassId===classId&&window.page==='attendance'}
    return authRequest('api/pengajuan-sakit'+(parent?'':'?kelas_id='+encodeURIComponent(classId)),{cache:'no-store'}).then(function(result){if(active())history(card,result.data||[],parent)}).catch(function(error){if(active())card.querySelector('.sick-request-history').textContent=error.message});
  }
  function mount(){
    if(window.page!=='attendance')return;
    var parent=!!(window.currentUser||{}).isParent;
    if(!parent&&window.currentRole!=='walas')return;
    var root=document.querySelector('#pageContent .page');if(!root||root.querySelector('#sickRequestCard'))return;
    var card=document.createElement('section');card.id='sickRequestCard';card.className='panel sick-request-card';
    card.innerHTML='<div class="panel-head"><div><h2>Pengajuan Istirahat Sakit</h2><p>'+(parent?'Sampaikan kebutuhan istirahat anak kepada wali kelas.':'Pengajuan sakit yang dikirim melalui akun orangtua/wali murid.')+'</p></div></div>'+(parent?'<div class="sick-request-guide"><strong>Cara mengajukan</strong><p>Isi lama istirahat dan tanggal mulai. Tanggal selesai dihitung otomatis dan dapat disesuaikan. Lampirkan surat sakit jika tersedia, lalu tekan <b>Kirim Pengajuan</b>.</p></div><form id="sickRequestForm"><div class="sick-request-fields"><label for="sickRequestDays">Perlu istirahat<div class="sick-request-days"><input id="sickRequestDays" name="jumlah_hari" type="number" min="1" max="60" step="1" value="1" required aria-describedby="sickDaysHelp"><span>hari</span></div><small id="sickDaysHelp">1–60 hari kalender, termasuk tanggal mulai dan selesai.</small></label><label for="sickRequestStart">Dari tanggal<input id="sickRequestStart" name="tanggal_mulai" type="date" value="'+today()+'" required></label><label for="sickRequestEnd">Sampai tanggal<input id="sickRequestEnd" name="tanggal_selesai" type="date" value="'+today()+'" required></label></div><p id="sickRequestSummary" class="sick-request-summary" aria-live="polite"></p><label class="sick-request-file" for="sickRequestFile">Lampiran Surat Sakit <span class="stat-note">(opsional)</span><input id="sickRequestFile" name="lampiran" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-describedby="sickFileHelp"><small id="sickFileHelp">Tekan Choose File / Pilih File untuk memilih surat dari perangkat Anda. PDF, JPG, atau PNG; maksimal 2 MB. Pastikan tulisan terbaca jelas.</small><span id="sickFileName" class="stat-note" aria-live="polite">Belum ada berkas dipilih.</span></label><label class="sick-request-note-field" for="sickRequestNote">Keterangan tambahan <span class="stat-note">(opsional)</span><textarea id="sickRequestNote" name="keterangan" maxlength="1000" rows="3" placeholder="Contoh: Anak disarankan dokter beristirahat di rumah selama dua hari."></textarea></label><p class="stat-note">Pengajuan ini dikirim ke wali kelas melalui Aiwalas. Pengajuan baru otomatis mengisi status Sakit pada tanggal yang diajukan. Hari Libur dan PJJ tetap berlaku. Wali kelas dapat melakukan koreksi bila diperlukan.</p><p id="sickRequestMessage" role="status" aria-live="polite"></p><div class="sick-request-actions"><button class="primary-btn" type="submit">Kirim Pengajuan</button></div></form>':'<p class="stat-note">Periksa tanggal, keterangan, dan surat sakit. Pengajuan baru otomatis mengisi status Sakit. Gunakan tombol Koreksi bila perlu penyesuaian; pengajuan lama tetap mengikuti status sebelumnya.</p>')+'<div class="sick-request-history-head"><h3>'+(parent?'Riwayat Pengajuan':'Pengajuan dari Orangtua')+'</h3><button type="button" class="outline-btn sick-request-refresh">Muat ulang</button></div><div class="sick-request-history" aria-live="polite"><p class="stat-note">Memuat pengajuan...</p></div>';
    var recap=root.querySelector('.student-attendance-recap');if(parent&&recap)root.insertBefore(card,recap);else root.appendChild(card);
    card.querySelector('.sick-request-refresh').onclick=function(){load(card,parent)};
    load(card,parent);if(!parent)return;
    var form=card.querySelector('form'),days=card.querySelector('#sickRequestDays'),start=card.querySelector('#sickRequestStart'),end=card.querySelector('#sickRequestEnd'),file=card.querySelector('#sickRequestFile'),message=card.querySelector('#sickRequestMessage'),summary=card.querySelector('#sickRequestSummary');
    function summarize(){end.min=start.value;var a=dateValue(start.value),b=dateValue(end.value),n=a&&b?Math.round((b-a)/86400000)+1:0;end.setCustomValidity(n<1?'Tanggal selesai tidak boleh sebelum tanggal mulai.':n>60?'Maksimal istirahat 60 hari.':'');summary.textContent=n>0&&n<=60?'Istirahat '+n+' hari: '+label(start.value)+' sampai '+label(end.value)+'.':'Lengkapi rentang tanggal istirahat dengan benar.'}
    function calculateEnd(){var d=dateValue(start.value),n=Number(days.value);if(d&&Number.isInteger(n)&&n>=1&&n<=60){d.setUTCDate(d.getUTCDate()+n-1);end.value=d.toISOString().slice(0,10)}summarize()}
    days.oninput=calculateEnd;start.onchange=calculateEnd;end.onchange=function(){var a=dateValue(start.value),b=dateValue(end.value);if(a&&b)days.value=Math.round((b-a)/86400000)+1;summarize()};summarize();
    file.onchange=function(){file.setCustomValidity('');var f=file.files[0];if(f&&f.size>2*1024*1024)file.setCustomValidity('Ukuran lampiran maksimal 2 MB.');card.querySelector('#sickFileName').textContent=f?f.name+' ('+(f.size/1024/1024).toFixed(2)+' MB)':'Belum ada berkas dipilih.'};
    form.onsubmit=function(event){
      event.preventDefault();summarize();if(!form.reportValidity())return;
      var body=new FormData(form),button=form.querySelector('[type=submit]'),user=(window.currentUser||{}).id;
      button.disabled=true;button.textContent='Mengirim...';message.textContent='';
      authRequest('api/pengajuan-sakit',{method:'POST',body:body}).then(function(result){
        if(!card.isConnected||(window.currentUser||{}).id!==user)return;
        message.className='sick-request-success';message.textContent=result.message+' Silakan lihat riwayat di bawah.';file.value='';file.onchange();card.querySelector('#sickRequestNote').value='';load(card,true);if(window.AIWalasStudentAttendanceRecap)window.AIWalasStudentAttendanceRecap.refresh();var refresh=document.getElementById('refreshStudentAttendance');if(refresh)refresh.click();
      }).catch(function(error){if(card.isConnected){message.className='login-error';message.textContent=error.message||'Koneksi terputus. Periksa riwayat sebelum mengirim ulang.'}}).finally(function(){button.disabled=false;button.textContent='Kirim Pengajuan'});
    };
  }
  var attendance=window.AIWalasPages.studentAttendance;
  window.AIWalasPages.studentAttendance=function(){var result=attendance.apply(this,arguments);mount();return result};
  var render=window.render;window.render=function(){var result=render.apply(this,arguments);mount();return result};
  setTimeout(mount,0);
})();
