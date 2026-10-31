(function(){
  // Cover plain table labels and status text rendered by all role-specific pages.
  function refresh(){
    document.querySelectorAll('#pageContent td,#pageContent th,#pageContent span,#pageContent strong,#pageContent p,#pageContent option,#modalContent td,#modalContent th,#modalContent span,#modalContent option').forEach(function(node){
      var text=node.textContent.trim();
      node.classList.toggle('attendance-sick-text',/^Sakit(?:\s+\d+)?$/.test(text));
      node.classList.toggle('attendance-permission-text',/^Izin(?:\s+\d+)?$/.test(text));
    });
  }
  var queued=false;
  function schedule(){if(queued)return;queued=true;requestAnimationFrame(function(){queued=false;refresh()})}
  ['pageContent','modalContent'].forEach(function(id){var root=document.getElementById(id);if(root)new MutationObserver(schedule).observe(root,{childList:true,subtree:true,characterData:true})});
  window.AIWalasAttendanceColors={refresh:refresh};refresh();
})();
