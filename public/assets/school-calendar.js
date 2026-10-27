(function(){
  function today(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())}
  function weekend(value){if(!/^\d{4}-\d{2}-\d{2}$/.test(value))return false;var date=new Date(value+'T00:00:00Z');return !isNaN(date)&&date.toISOString().slice(0,10)===value&&(date.getUTCDay()===0||date.getUTCDay()===6)}
  function refresh(){
    var root=document.getElementById('pageContent');if(!root)return;
    var date=window.page==='attendance'&&window.currentRole!=='student'&&window.AIWalasAttendance?window.AIWalasAttendance.selectedDate():today();
    var banner=document.getElementById('schoolHolidayBanner');
    if(!banner){banner=document.createElement('div');banner.id='schoolHolidayBanner';banner.className='school-holiday-banner';banner.setAttribute('role','status');root.parentNode.insertBefore(banner,root)}
    var show=!!window.currentRole&&weekend(date);banner.hidden=!show;
    if(show){var text='Libur — '+new Intl.DateTimeFormat('id-ID',{timeZone:'Asia/Jakarta',weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(new Date(date+'T12:00:00+07:00'))+'. Sabtu dan Minggu merupakan hari libur.';if(banner.textContent!==text)banner.textContent=text}
  }
  window.AIWalasCalendar={today:today,isWeekend:weekend,refresh:refresh};
  var root=document.getElementById('pageContent');if(root)new MutationObserver(refresh).observe(root,{childList:true,subtree:true});
  var login=document.getElementById('loginScreen');if(login)new MutationObserver(refresh).observe(login,{attributes:true,attributeFilter:['class']});
  document.addEventListener('visibilitychange',refresh);setInterval(refresh,30000);setTimeout(refresh,0);
})();
