(function(){
  var timer=null;
  function refresh(){if(window.page==='attendance'&&window.currentRole!=='student'&&window.AIWalasAttendance)window.AIWalasAttendance.refresh()}
  function install(){clearInterval(timer);if(window.page==='attendance'&&window.currentRole!=='student'){refresh();timer=setInterval(refresh,5000)}}
  var old=window.render;window.render=function(){var result=old.apply(this,arguments);setTimeout(install,50);return result};setTimeout(install,300);
})();
