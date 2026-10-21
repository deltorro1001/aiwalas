(function(){
  var selectedDate=null,currentPage=1,version=0,identity=null;
  function today(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())}
  function esc(value){return escapeHtml(value==null?'':value)}
  function renderNotifications(){
    var user=String((window.currentUser||{}).id);
    if(identity!==user){identity=user;selectedDate=today();currentPage=1;}
    var request=++version,date=selectedDate,page=currentPage;
    document.getElementById('pageContent').innerHTML='<div class="page"><div class="page-heading"><div><div class="eyebrow">Pusat informasi</div><h1>Notifikasi</h1><p>Lihat notifikasi berdasarkan tanggal yang dipilih.</p></div><div class="notification-actions"><button type="button" class="outline-btn" id="incomingMessage">Pesan Masuk</button><div class="notification-date-controls"><button type="button" class="outline-btn" id="markRead">Tandai Semua Dibaca</button><label for="notificationDate">Tanggal notifikasi</label><input id="notificationDate" type="date" value="'+esc(date)+'"><button type="button" class="panel-action" id="allNotificationDates">Semua tanggal</button></div></div></div><p class="login-error" id="notificationError" role="alert"></p><section class="panel"><p class="stat-note" id="notificationCount">Memuat notifikasi...</p><div class="activity-list" id="notificationList" aria-live="polite"></div><div class="notification-pagination" id="notificationPagination"></div></section></div>';
    document.getElementById('incomingMessage').onclick=function(){if(typeof window.incomingMessage==='function')window.incomingMessage()};
    document.getElementById('notificationDate').onchange=function(){if(!this.validity.valid)return;selectedDate=this.value;currentPage=1;renderNotifications()};
    document.getElementById('allNotificationDates').onclick=function(){selectedDate='';currentPage=1;renderNotifications()};
    document.getElementById('markRead').onclick=function(){
      var button=this;button.disabled=true;
      authRequest('api/notifikasi/baca-semua',{method:'PATCH',body:'{}'}).then(function(){
        (window.notificationData||[]).forEach(function(n){n.sudah_dibaca=1});
        if(window.page==='notifications')renderNotifications();toast('Semua notifikasi ditandai dibaca.');
      }).catch(function(error){var box=document.getElementById('notificationError');if(box)box.textContent=error.message;button.disabled=false;});
    };
    authRequest('api/notifikasi?page='+page+(date?'&tanggal='+encodeURIComponent(date):'')).then(function(data){
      if(request!==version||window.page!=='notifications'||String((window.currentUser||{}).id)!==user)return;
      document.getElementById('notificationCount').textContent=data.total+' notifikasi'+(date?' pada '+date:' dari semua tanggal');
      document.getElementById('notificationList').innerHTML=(data.data||[]).map(function(n){return '<article class="activity"><div class="activity-text"><strong>'+esc(n.judul)+'</strong><div class="notification-message-body">'+esc(n.isi)+'</div><span class="activity-time">'+esc(n.dibuat_pada)+'</span><small>'+((Number(n.sudah_dibaca))?'Sudah dibaca':'Belum dibaca')+'</small></div></article>'}).join('')||'<p class="stat-note">'+(date?'Tidak ada notifikasi pada tanggal ini.':'Belum ada notifikasi.')+'</p>';
      document.getElementById('notificationPagination').innerHTML='<button type="button" class="outline-btn" id="previousNotifications"'+(page<=1?' disabled':'')+'>Sebelumnya</button><span>Halaman '+page+'</span><button type="button" class="outline-btn" id="nextNotifications"'+(!data.has_more?' disabled':'')+'>Berikutnya</button>';
      document.getElementById('previousNotifications').onclick=function(){currentPage=Math.max(1,currentPage-1);renderNotifications()};
      document.getElementById('nextNotifications').onclick=function(){currentPage++;renderNotifications()};
    }).catch(function(error){if(request===version&&window.page==='notifications'){document.getElementById('notificationError').textContent=error.message;document.getElementById('notificationCount').textContent='Notifikasi belum dapat dimuat.';}});
  }
  window.AIWalasPages=window.AIWalasPages||{};window.AIWalasPages.notifications=renderNotifications;
})();
