(function(){
  // Cover plain table labels and status text rendered by all role-specific pages.
  function refresh(){
    document.querySelectorAll('#pageContent td,#pageContent th,#pageContent span,#pageContent strong,#pageContent p,#pageContent option,#modalContent td,#modalContent th,#modalContent span,#modalContent option,#pageContent .summary-empty').forEach(function(node){
      var text=node.textContent.trim();
      node.classList.toggle('attendance-holiday-text',/^Libur(?:\b|$)/.test(text));
      node.classList.toggle('attendance-sick-text',/^Sakit(?:\s+\d+)?$/.test(text));
      node.classList.toggle('attendance-permission-text',/^Izin(?:\s+\d+)?$/.test(text));
    });
    document.querySelectorAll('[data-calendar-date],#pageContent input[type=date],#modalContent input[type=date]').forEach(function(node){
      var date=node.dataset.calendarDate||node.value||'';
      node.classList.toggle('attendance-calendar-holiday',!!(window.AIWalasCalendar&&window.AIWalasCalendar.isHoliday(date)));
    });
  }
  var queued=false;
  function schedule(){if(queued)return;queued=true;requestAnimationFrame(function(){queued=false;refresh()})}
  ['pageContent','modalContent'].forEach(function(id){var root=document.getElementById(id);if(root)new MutationObserver(schedule).observe(root,{childList:true,subtree:true,characterData:true,attributes:true,attributeFilter:['data-calendar-date','data-attendance-status','data-status']})});
  document.addEventListener('change',schedule);
  document.addEventListener('input',function(event){if(event.target.matches('input[type=date]'))schedule()});
  window.AIWalasAttendanceColors={refresh:refresh};refresh();
})();
