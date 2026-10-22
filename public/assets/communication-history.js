(function(){
  var version=0,pageNumber=1,status='all',context='',timer=null;
  function esc(value){return escapeHtml(value==null?'':value)}
  function renderCommunication(){
    if(window.currentRole!=='walas')return;
    clearTimeout(timer);
    var next=String((window.currentUser||{}).id)+':'+window.activeClassId;
    if(context!==next){context=next;pageNumber=1;status='all'}
    document.getElementById('pageContent').innerHTML='<div class="page"><div class="page-heading"><div><div class="eyebrow">Komunikasi</div><h1>Histori Pesan WhatsApp</h1><p>Pesan otomatis dan pengiriman lain tercatat di sini.</p></div><div class="notification-actions"><button type="button" class="outline-btn" id="refreshCommunicationHistory">Muat ulang</button><button type="button" class="outline-btn" id="showManualMessages">Pesan manual</button></div></div><div class="toolbar"><label>Status pengiriman <select id="communicationStatus" class="filter-btn"><option value="all">Semua</option><option value="sent">Terkirim</option><option value="pending">Pending</option></select></label><span id="communicationCount" class="stat-note">Memuat histori...</span></div><p class="stat-note">Pending berarti belum terkonfirmasi atau memerlukan pemeriksaan; pesan tidak otomatis dikirim ulang.</p><p id="communicationError" class="login-error" role="alert"></p><section class="panel" id="communicationHistory" aria-live="polite"></section><div class="notification-pagination" id="communicationPagination"></div><div id="manualCommunication" hidden></div></div>';
    document.getElementById('communicationStatus').value=status;
    document.getElementById('communicationStatus').onchange=function(){status=this.value;pageNumber=1;load()};
    document.getElementById('refreshCommunicationHistory').onclick=load;
    document.getElementById('showManualMessages').onclick=function(){var target=document.getElementById('manualCommunication');target.hidden=!target.hidden;if(!target.hidden)target.innerHTML=typeof window.communication==='function'?window.communication():''};
    load();
  }
  function load(){
    clearTimeout(timer);if(window.page!=='communication'||window.currentRole!=='walas')return;
    var request=++version,classId=window.activeClassId,user=(window.currentUser||{}).id;
    authRequest('api/pesan?kelas_id='+classId+'&status='+status+'&page='+pageNumber).then(function(data){
      if(request!==version||window.page!=='communication'||window.activeClassId!==classId||(window.currentUser||{}).id!==user)return;
      document.getElementById('communicationError').textContent='';document.getElementById('communicationCount').textContent=data.total+' pesan';
      document.getElementById('communicationHistory').innerHTML=(data.data||[]).map(function(message){var sent=message.delivery_status==='Terkirim',time=message.dikirim_pada||message.attempted_at;return '<article class="chat-message" data-communication-id="'+message.id+'" data-delivery-status="'+esc(message.delivery_status)+'"><div style="width:100%"><div class="panel-head"><strong>'+esc(message.penerima)+'</strong><span class="pill '+(sent?'teal':'amber')+'">'+esc(message.delivery_status)+'</span></div><small>'+esc(message.nomor_tujuan)+' · '+esc(message.kategori||'WhatsApp')+(time?' · '+esc(time)+' WIB':'')+'</small><p style="white-space:pre-wrap;overflow-wrap:anywhere">'+esc(message.isi_pesan)+'</p><small>'+esc(message.delivery_note)+'</small></div></article>'}).join('')||'<p class="stat-note">Belum ada pesan pada pilihan ini.</p>';
      document.getElementById('communicationPagination').innerHTML='<button type="button" class="outline-btn" id="previousCommunication"'+(pageNumber<=1?' disabled':'')+'>Sebelumnya</button><span>Halaman '+pageNumber+'</span><button type="button" class="outline-btn" id="nextCommunication"'+(!data.has_more?' disabled':'')+'>Berikutnya</button>';
      document.getElementById('previousCommunication').onclick=function(){pageNumber=Math.max(1,pageNumber-1);load()};
      document.getElementById('nextCommunication').onclick=function(){pageNumber++;load()};
      timer=setTimeout(function(){if(!document.hidden)load()},10000);
    }).catch(function(error){if(request===version&&window.page==='communication'){var box=document.getElementById('communicationError');if(box)box.textContent=error.message;}});
  }
  document.addEventListener('visibilitychange',function(){if(!document.hidden&&window.page==='communication')load()});
  window.AIWalasPages=window.AIWalasPages||{};window.AIWalasPages.communication=renderCommunication;
})();
