(function(){
  var version=0;
  function esc(value){return escapeHtml(value==null?'':value)}
  function renderSchedulePanel(){
    if(window.currentRole!=='walas'||window.page!=='schedule'||!window.activeClassId)return;
    var root=document.querySelector('#pageContent .page');if(!root)return;
    var old=document.getElementById('teacherReminderSchedulePanel');if(old)old.remove();
    var panel=document.createElement('section');panel.id='teacherReminderSchedulePanel';panel.className='panel teacher-reminder-schedule-panel';panel.innerHTML='<h2>WhatsApp Otomatis Guru</h2><p>Memuat jadwal pengiriman...</p>';root.appendChild(panel);
    var request=++version,classId=window.activeClassId;
    authRequest('api/whatsapp/teacher-schedule?kelas_id='+classId).then(function(data){
      if(request!==version||window.page!=='schedule'||window.activeClassId!==classId||!panel.isConnected)return;
      var status=!data.enabled?'Pengiriman otomatis belum diaktifkan.':!data.gateway_configured?'Gateway WhatsApp belum dikonfigurasi.':!data.worker_recent?'Scheduler server belum terdeteksi aktif.':'Scheduler server aktif. Pesan dikirim tanpa membuka browser.';
      var scheduleList=document.querySelector('#pageContent .schedule-list');
      if(scheduleList){
        scheduleList.innerHTML=['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'].map(function(day){
          var sessions=(data.schedule||[]).filter(function(item){return item.hari===day});if(!sessions.length)return '';
          return '<div class="schedule-day"><h3>'+day+'</h3>'+sessions.map(function(item){return '<div class="schedule-item"><span class="schedule-time">'+esc(item.jam_mulai.slice(0,5))+'–'+esc(item.jam_selesai.slice(0,5))+'</span><div class="schedule-info"><strong>'+esc(item.nama_mata_pelajaran)+'</strong><small>'+esc(item.nama_guru||'Guru belum ditentukan')+'</small></div></div>'}).join('')+'</div>';
        }).join('')||'<p class="stat-note">Belum ada jadwal tersimpan untuk kelas ini.</p>';
      }
      var labels={pending:'Menunggu jadwal',processing:'Sedang diproses',sent:'Diterima gateway',failed:'Gagal',unknown:'Perlu diperiksa',skipped:'Dilewati',missed:'Waktu pengiriman terlewat'};
      panel.innerHTML='<div class="panel-head"><div><h2>WhatsApp Otomatis Guru</h2><p>'+esc(status)+'</p>'+(data.last_run?'<small>Terakhir dijalankan: '+esc(data.last_run)+' WIB</small>':'')+'<p>Mengikuti jadwal kelas yang tersimpan: pengingat 10 menit sebelum mulai dan terima kasih saat jadwal selesai. Waktu Jakarta.</p><p>Pengecualian pengiriman otomatis: '+esc((data.excluded_teachers||[]).join(', '))+'.</p></div><button type="button" class="outline-btn" id="refreshTeacherAutomation">Muat ulang status</button></div><div class="teacher-reminder-schedule-list">'+((data.data||[]).map(function(item){return '<div class="teacher-reminder-schedule-row"><div><strong>'+esc(item.teacher)+'</strong><small>'+esc(item.subject)+' · '+esc(item.class)+' · '+esc(item.lesson_date)+' · '+esc(item.start.slice(0,5))+'–'+esc(item.end.slice(0,5))+'</small></div><div><strong>'+esc(item.type==='reminder'?'Pengingat':'Terima kasih')+' '+esc(item.due_at.slice(11,16))+'</strong><small>'+esc(item.phone_available?(labels[item.status]||item.status):'Nomor guru belum tersedia')+'</small></div></div>'}).join('')||'<p class="stat-note">Tidak ada jadwal pengiriman hari ini.</p>')+'</div>';
      document.getElementById('refreshTeacherAutomation').onclick=renderSchedulePanel;
    }).catch(function(error){if(request===version&&panel.isConnected)panel.textContent=error.message;});
  }
  var previousRender=window.render;window.render=function(){var result=previousRender.apply(this,arguments);setTimeout(renderSchedulePanel,0);return result};
})();
