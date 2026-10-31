(function(){
  var menus=['dashboard','guestSchedule','guestAnnouncements','guestCalendar'],version=0,klass='',month='',year='';
  var days=['Senin','Selasa','Rabu','Kamis','Jumat'],months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  function esc(v){return escapeHtml(v==null?'':String(v))}
  function label(v){return v?new Intl.DateTimeFormat('id-ID',{weekday:'long',day:'numeric',month:'long',year:'numeric',timeZone:'Asia/Jakarta'}).format(new Date(v+'T12:00:00+07:00')):''}
  function current(){var date=window.AIWalasCalendar.today().split('-');if(!month)month=date[1];if(!year)year=date[0]}
  function announcements(rows){return rows.length?rows.map(function(row){return '<article class="guest-announcement"><h3>'+esc(row.judul)+'</h3><p class="guest-announcement-body">'+esc(row.isi)+'</p>'+(row.tanggal_agenda?'<small data-calendar-date="'+esc(row.tanggal_agenda)+'">Agenda: '+esc(label(row.tanggal_agenda))+'</small>':'')+'</article>'}).join(''):'<p class="summary-empty">Belum ada pengumuman yang diterbitkan untuk tamu.</p>'}
  function showGuest(){
    if(window.currentRole!=='guest')return;
    if(menus.indexOf(window.page)<0)window.page='dashboard';
    current();var page=window.page,user=(window.currentUser||{}).id,request=++version;
    document.getElementById('pageContent').innerHTML='<div class="page guest-page"><p class="stat-note">Memuat informasi kelas...</p></div>';
    authRequest('api/tamu?bulan='+encodeURIComponent(month)+'&tahun='+encodeURIComponent(year)+(klass?'&kelas_id='+encodeURIComponent(klass):''),{cache:'no-store'}).then(function(data){
      if(request!==version||window.currentRole!=='guest'||window.page!==page||(window.currentUser||{}).id!==user)return;
      klass=data.kelas?String(data.kelas.id):'';
      var title={dashboard:'Beranda',guestSchedule:'Jadwal Mapel',guestAnnouncements:'Pengumuman',guestCalendar:'Kalender Akademik'}[page];
      var heading='<div class="page-heading"><div><div class="eyebrow">Informasi Kelas</div><h1>'+title+'</h1><p>'+esc(data.sekolah)+(data.kelas?' · Kelas '+esc(data.kelas.nama_kelas)+' · '+esc(data.kelas.nama_tahun_ajaran):'')+'</p></div><label class="guest-class-picker">Kelas<select id="guestClassSelect" class="filter-btn">'+(data.daftar_kelas||[]).map(function(row){return '<option value="'+esc(row.id)+'"'+(String(row.id)===klass?' selected':'')+'>'+esc(row.nama_kelas)+' · '+esc(row.nama_tahun_ajaran)+'</option>'}).join('')+'</select></label></div>';
      var body='';
      if(page==='dashboard'){
        body='<div class="grid-stats"><section class="stat-card"><span class="stat-label">Jumlah Siswa</span><div class="stat-number">'+data.jumlah_siswa+'</div><p class="stat-note">Siswa aktif pada kelas ini</p></section><section class="stat-card"><span class="stat-label">Hadir</span><div class="stat-number">'+(data.kehadiran.Hadir||0)+'</div><p class="stat-note">Kehadiran hari ini</p></section><section class="stat-card"><span class="stat-label">Tidak Hadir</span><div class="stat-number">'+((data.kehadiran.Sakit||0)+(data.kehadiran.Izin||0)+(data.kehadiran.Alpha||0))+'</div><p class="stat-note">Sakit, izin, dan alpha</p></section></div><section class="panel"><div class="panel-head"><div><h2>Ringkasan Kehadiran</h2><p data-calendar-date="'+esc(data.tanggal)+'">'+esc(label(data.tanggal))+'</p></div></div><div class="guest-status-list">'+Object.keys(data.kehadiran).map(function(status){return '<div><span class="status '+statusClass(status)+'">'+esc(status)+'</span><strong>'+data.kehadiran[status]+'</strong></div>'}).join('')+(Object.keys(data.kehadiran).length?'':'<p class="stat-note">Belum ada data kehadiran.</p>')+'</div></section><section class="panel guest-section"><div class="panel-head"><h2>Pengumuman Terbaru</h2></div>'+announcements(data.pengumuman.slice(0,3))+'</section>';
      }else if(page==='guestSchedule'){
        body=days.map(function(day){var items=data.jadwal.filter(function(row){return row.hari===day});return '<section class="panel guest-section"><div class="panel-head"><h2>'+day+'</h2><span class="pill teal">'+items.length+' sesi</span></div><div class="guest-schedule-list">'+(items.length?items.map(function(row){return '<article><strong>'+esc(row.jam_mulai.slice(0,5))+'–'+esc(row.jam_selesai.slice(0,5))+'</strong><div><h3>'+esc(window.academicSubjectLabel(row.nama_mata_pelajaran))+'</h3><p>'+esc(row.nama_guru||'Guru belum ditentukan')+'</p></div></article>'}).join(''):'<p class="stat-note">Belum ada jadwal pada hari ini.</p>')+'</div></section>'}).join('');
      }else if(page==='guestAnnouncements')body='<section class="panel">'+announcements(data.pengumuman)+'</section>';
      else{
        var years='';for(var y=2026;y<=Math.max(Number(year),new Date().getFullYear()+5);y++)years+='<option'+(y===Number(year)?' selected':'')+'>'+y+'</option>';
        body='<section class="panel guest-calendar-controls"><label>Bulan<select id="guestMonth" class="filter-btn">'+months.map(function(name,i){return '<option value="'+(i+1)+'"'+(i+1===Number(month)?' selected':'')+'>'+name+'</option>'}).join('')+'</select></label><label>Tahun<select id="guestYear" class="filter-btn">'+years+'</select></label></section><section class="panel table-panel"><div class="panel-head"><h2>'+months[Number(month)-1]+' '+esc(year)+'</h2></div><div class="table-wrap"><table class="guest-calendar-table"><thead><tr><th>Hari / Tanggal</th><th>Status</th><th>Agenda</th></tr></thead><tbody>'+data.kalender.map(function(day){var status=day.status||'Hari Sekolah';return '<tr data-attendance-status="'+esc(status)+'"><td data-attendance-date data-calendar-date="'+esc(day.tanggal)+'">'+esc(label(day.tanggal))+'</td><td>'+esc(status)+'</td><td>'+(day.agenda.length?day.agenda.map(esc).join('<br>'):'—')+'</td></tr>'}).join('')+'</tbody></table></div></section>';
      }
      document.getElementById('pageContent').innerHTML='<div class="page guest-page">'+heading+(!data.kelas?'<p class="alert-box">Belum ada kelas aktif.</p>':'')+body+'</div>';
      document.getElementById('breadcrumbCurrent').textContent=title;
      document.querySelectorAll('.sidebar [data-page]').forEach(function(button){button.classList.toggle('active',button.dataset.page===page)});
      document.getElementById('guestClassSelect').onchange=function(){klass=this.value;showGuest()};
      ['guestMonth','guestYear'].forEach(function(id){var select=document.getElementById(id);if(select)select.onchange=function(){month=document.getElementById('guestMonth').value;year=document.getElementById('guestYear').value;showGuest()}});
    }).catch(function(error){if(request===version&&window.currentRole==='guest')document.getElementById('pageContent').innerHTML='<div class="page guest-page"><p class="login-error">'+esc(error.message)+'</p><button class="outline-btn" id="guestRetry">Coba lagi</button></div>';var retry=document.getElementById('guestRetry');if(retry)retry.onclick=showGuest});
  }
  function manage(){
    if(window.currentRole!=='walas')return;
    var request=++version,classId=window.activeClassId,user=(window.currentUser||{}).id;
    function active(){return window.currentRole==='walas'&&window.page==='publicAnnouncements'&&request===version&&window.activeClassId===classId&&(window.currentUser||{}).id===user}
    document.getElementById('pageContent').innerHTML='<div class="page"><div class="page-heading"><div><h1>Pengumuman Tamu</h1><p>Hanya pengumuman yang diterbitkan yang dapat dibaca tamu.</p></div><button class="primary-btn" id="newGuestAnnouncement">Tambah Pengumuman</button></div><p class="login-error" id="announcementError"></p><section class="panel" id="publicAnnouncementList">Memuat pengumuman...</section></div>';
    function edit(item){
      item=item||{};
      modal(item.id?'Edit Pengumuman':'Tambah Pengumuman','Informasi yang diterbitkan dapat dilihat oleh semua pengguna akun tamu.','<form id="guestAnnouncementForm"><label>Judul<input id="gaTitle" maxlength="180" required value="'+esc(item.judul||'')+'"></label><label>Isi Pengumuman<textarea id="gaBody" maxlength="10000" required>'+esc(item.isi||'')+'</textarea></label><label>Tanggal Agenda (opsional)<input id="gaDate" type="date" min="2026-01-01" value="'+esc(item.tanggal_agenda||'')+'"></label><label class="guest-publish-check"><input id="gaPublished" type="checkbox" '+(Number(item.terbit)?'checked':'')+'> Terbitkan untuk tamu</label><p class="login-error" id="gaError"></p><div class="modal-actions"><button class="filter-btn" type="button" id="cancelModal">Batal</button><button class="primary-btn" type="submit">Simpan</button></div></form>');
      document.getElementById('guestAnnouncementForm').onsubmit=function(event){event.preventDefault();if(!active()){closeModal();return}var button=this.querySelector('[type=submit]');button.disabled=true;authRequest('api/pengumuman-tamu'+(item.id?'/'+item.id:''),{method:item.id?'PUT':'POST',body:JSON.stringify({kelas_id:classId,judul:document.getElementById('gaTitle').value,isi:document.getElementById('gaBody').value,tanggal_agenda:document.getElementById('gaDate').value,terbit:document.getElementById('gaPublished').checked})}).then(function(){closeModal();if(active())manage()}).catch(function(error){var box=document.getElementById('gaError');if(box)box.textContent=error.message;button.disabled=false})};
    }
    document.getElementById('newGuestAnnouncement').onclick=function(){edit()};
    authRequest('api/pengumuman-tamu?kelas_id='+classId,{cache:'no-store'}).then(function(result){if(!active())return;var rows=result.data||[];document.getElementById('publicAnnouncementList').innerHTML=rows.length?rows.map(function(row){return '<article class="guest-announcement"><h3>'+esc(row.judul)+'</h3><p class="guest-announcement-body">'+esc(row.isi)+'</p><span class="pill teal">'+(Number(row.terbit)?'Terbit':'Draf')+'</span><div class="modal-actions"><button class="outline-btn" data-ga-edit="'+row.id+'">Edit</button><button class="outline-btn" data-ga-delete="'+row.id+'">Hapus</button></div></article>'}).join(''):'<p class="summary-empty">Belum ada pengumuman untuk tamu.</p>';document.querySelectorAll('[data-ga-edit]').forEach(function(button){button.onclick=function(){edit(rows.find(function(row){return String(row.id)===button.dataset.gaEdit}))}});document.querySelectorAll('[data-ga-delete]').forEach(function(button){button.onclick=function(){if(!active()||!confirm('Hapus pengumuman ini?'))return;button.disabled=true;authRequest('api/pengumuman-tamu/'+button.dataset.gaDelete,{method:'DELETE'}).then(function(){if(active())manage()}).catch(function(error){document.getElementById('announcementError').textContent=error.message;button.disabled=false})}})}).catch(function(error){if(active())document.getElementById('announcementError').textContent=error.message});
  }
  window.roleAccess.guest=menus.slice();window.roleAccess.walas.push('publicAnnouncements');window.names.publicAnnouncements='Pengumuman Tamu';
  window.AIWalasPages.publicAnnouncements=manage;
  var apply=window.applyRoleAccess;window.applyRoleAccess=function(){
    document.querySelectorAll('[data-guest-hidden]').forEach(function(node){node.style.display=node.dataset.guestHidden;delete node.dataset.guestHidden});
    apply.apply(this,arguments);
    if(window.currentRole!=='guest')return;
    document.querySelectorAll('.sidebar [data-page]').forEach(function(node){node.style.display=menus.indexOf(node.dataset.page)>=0?'flex':'none'});
    document.querySelectorAll('.workspace-switch .academic-year-row,.sidebar .help-card,.nav-badge').forEach(function(node){node.dataset.guestHidden=node.style.display;node.style.display='none'});
    var role=document.querySelector('.workspace-switch small');if(role)role.textContent='Tamu · Informasi Kelas';
  };
  var render=window.render;window.render=function(){if(window.currentRole==='guest')return showGuest();return render.apply(this,arguments)};
  var role=document.getElementById('loginRole'),option=role&&role.querySelector('[value=guest]');if(option)option.textContent='Tamu';
  if(window.currentRole==='guest'){applyRoleAccess();showGuest()}
})();
