(function(){
  'use strict';
  var stop=null;
  function cleanup(){if(stop){stop();stop=null}}
  function open(row,kind){
    if(window.currentRole!=='walas'||!row.preview_url)return;
    var url=new URL(row.preview_url,location.href),download=new URL(row.lampiran_url,location.href);
    if(url.origin!==location.origin||download.origin!==location.origin)return;
    cleanup();
    var user=(window.currentUser||{}).id,classId=window.activeClassId,page=window.page;
    var image=/^image\/(jpeg|png)$/.test(row.mime_lampiran),name=String(row.nama_lampiran||'Surat '+kind);
    modal('Preview Surat '+(kind==='sakit'?'Sakit':'Izin'),escapeHtml(row.nama_lengkap||'')+' · '+escapeHtml(name),
      '<div class="request-attachment-preview"><div class="request-attachment-viewer">'+(image?'<img src="'+escapeHtml(url.href)+'" alt="'+escapeHtml('Surat '+kind+' '+(row.nama_lengkap||''))+'">':'<iframe src="'+escapeHtml(url.href)+'" title="'+escapeHtml(name)+'"></iframe>')+'</div><p class="stat-note">Jika preview tidak tampil, buka surat di tab baru atau unduh berkasnya.</p><div class="modal-actions"><a class="outline-btn" href="'+escapeHtml(url.href)+'" target="_blank" rel="noopener">Buka di tab baru</a><a class="primary-btn" href="'+escapeHtml(download.href)+'">Unduh Surat</a><button class="filter-btn" type="button" id="cancelModal">Tutup</button></div></div>');
    var preview=document.querySelector('.request-attachment-preview');
    var observer=new MutationObserver(function(){
      if(!preview.isConnected){cleanup();return}
      if(window.currentRole!=='walas'||(window.currentUser||{}).id!==user||window.activeClassId!==classId||window.page!==page)window.closeModal();
    });
    stop=function(){observer.disconnect();preview.querySelectorAll('iframe,img').forEach(function(node){node.removeAttribute('src')});preview.remove()};
    observer.observe(document.body,{childList:true,subtree:true});
  }
  function bind(card,rows,kind){
    card.querySelectorAll('[data-request-preview]').forEach(function(button){
      button.onclick=function(){var row=rows.find(function(item){return String(item.id)===button.dataset.requestPreview});if(row&&card.isConnected)open(row,kind)};
    });
  }
  var close=window.closeModal;
  window.closeModal=function(){cleanup();return close.apply(this,arguments)};
  window.AIWalasRequestAttachmentPreview={bind:bind};
})();
