(function(){
  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function note(status,value){return status==='Sakit'&&value==='(surat sakit terlampir)'?value:status==='Izin'&&value==='(surat izin terlampir)'?value:''}
  function html(status,value){var text=note(status,value);return '<span class="status '+(typeof statusClass==='function'?statusClass(status):'')+'">'+esc(status)+'</span>'+(text?' <small class="attendance-attachment-note" data-attendance-status="'+esc(status)+'">'+esc(text)+'</small>':'')}
  window.AIWalasAttendanceDisplay={note:note,html:html};
})();
