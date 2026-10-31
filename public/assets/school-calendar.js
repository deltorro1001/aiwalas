(function(){
  function today(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())}
  function weekend(value){if(!/^\d{4}-\d{2}-\d{2}$/.test(value))return false;var date=new Date(value+'T00:00:00Z');return !isNaN(date)&&date.toISOString().slice(0,10)===value&&(date.getUTCDay()===0||date.getUTCDay()===6)}
  var special=window.AIWalasSchoolHolidays||{};
  var pjjDates=window.AIWalasSchoolPjjDates||[];
  function pjj(value){return pjjDates.indexOf(value)!==-1}
  function specialHoliday(value){return Object.prototype.hasOwnProperty.call(special,value)}
  function holiday(value){return specialHoliday(value)||weekend(value)}
  function attendanceOverride(value){return specialHoliday(value)?'Libur':pjj(value)?'PJJ':null}
  function refresh(){
    var root=document.getElementById('pageContent');if(!root)return;
    var date=window.page==='attendance'&&window.currentRole!=='student'&&window.AIWalasAttendance?window.AIWalasAttendance.selectedDate():today();
    var banner=document.getElementById('schoolHolidayBanner');
    if(!banner){banner=document.createElement('div');banner.id='schoolHolidayBanner';banner.className='school-holiday-banner';banner.setAttribute('role','status');root.parentNode.insertBefore(banner,root)}
    var show=!!window.currentRole&&(holiday(date)||pjj(date));banner.hidden=!show;
    banner.dataset.status=show?(pjj(date)?'PJJ':'Libur'):'';
    if(show){var text=(pjj(date)?'PJJ':'Libur')+' — '+new Intl.DateTimeFormat('id-ID',{timeZone:'Asia/Jakarta',weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(new Date(date+'T12:00:00+07:00'))+'. '+(specialHoliday(date)?special[date]:pjj(date)?'Pembelajaran Jarak Jauh (PJJ).':'Sabtu dan Minggu merupakan hari libur.');if(banner.textContent!==text)banner.textContent=text}
  }
  window.AIWalasCalendar={today:today,isWeekend:weekend,isHoliday:holiday,isSpecialHoliday:specialHoliday,isPjj:pjj,attendanceOverride:attendanceOverride,refresh:refresh};
  var root=document.getElementById('pageContent');if(root)new MutationObserver(refresh).observe(root,{childList:true,subtree:true});
  var login=document.getElementById('loginScreen');if(login)new MutationObserver(refresh).observe(login,{attributes:true,attributeFilter:['class']});
  document.addEventListener('visibilitychange',refresh);setInterval(refresh,30000);setTimeout(refresh,0);
})();
