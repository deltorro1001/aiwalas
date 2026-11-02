(function(){
  var data=window.AIWalasSchoolCalendar||{entries:{}},loading=false,signature='';
  // Compatibility with pages cached before the academic calendar was introduced.
  if(!window.AIWalasSchoolCalendar){Object.keys(window.AIWalasSchoolHolidays||{}).forEach(function(date){data.entries[date]={judul:window.AIWalasSchoolHolidays[date],jenis:'Hari Libur',dampak_absensi:'Libur'}});(window.AIWalasSchoolPjjDates||[]).forEach(function(date){data.entries[date]={judul:'Pembelajaran Jarak Jauh (PJJ)',jenis:'PJJ',dampak_absensi:'PJJ'}})}
  function today(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())}
  function weekend(value){if(!/^\d{4}-\d{2}-\d{2}$/.test(value))return false;var date=new Date(value+'T00:00:00Z');return !isNaN(date)&&date.toISOString().slice(0,10)===value&&(date.getUTCDay()===0||date.getUTCDay()===6)}
  function entry(date){return (data.entries||{})[date]||null}
  function attendanceOverride(date){var e=entry(date);if(reportDay(date))return 'Pengambilan Raport';return e?(e.dampak_absensi==='Normal'?null:(e.dampak_absensi==='TanpaAbsen'?'Tanpa absensi':e.dampak_absensi)):(weekend(date)?'Libur':null)}
  function holiday(date){return attendanceOverride(date)==='Libur'}
  function pjj(date){return attendanceOverride(date)==='PJJ'}
  function reportDay(date){return !!entry(date)&&entry(date).jenis==='Hari Pengambilan Raport'}
  function withoutAttendance(date){return reportDay(date)||(!!entry(date)&&entry(date).dampak_absensi==='TanpaAbsen')}
  function specialHoliday(date){var e=entry(date);return !!e&&e.dampak_absensi==='Libur'}
  function refresh(){
    var root=document.getElementById('pageContent');if(!root)return;
    var date=window.page==='attendance'&&window.currentRole!=='student'&&window.AIWalasAttendance?window.AIWalasAttendance.selectedDate():today();
    var banner=document.getElementById('schoolHolidayBanner');
    if(!banner){banner=document.createElement('div');banner.id='schoolHolidayBanner';banner.className='school-holiday-banner';banner.setAttribute('role','status');root.parentNode.insertBefore(banner,root)}
    var e=entry(date),status=attendanceOverride(date),show=!!window.currentRole&&!!(status||e);banner.hidden=!show;
    banner.dataset.status=status||'';
    if(show){var text=(status||e.jenis)+' — '+new Intl.DateTimeFormat('id-ID',{timeZone:'Asia/Jakarta',weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(new Date(date+'T12:00:00+07:00'))+'. '+(e?e.judul+(e.keterangan?' — '+e.keterangan:''):'Sabtu dan Minggu merupakan hari libur.');if(banner.textContent!==text)banner.textContent=text}
  }
  function setData(next){if(!next||!next.entries)return;var key=JSON.stringify(next.entries);data=next;window.AIWalasSchoolCalendar=data;refresh();if(key!==signature){signature=key;document.dispatchEvent(new CustomEvent('aiwalas:calendar-updated'))}}
  function sync(){if(loading||!window.currentRole||!window.currentUser||typeof window.authRequest!=='function'||document.hidden)return;loading=true;var user=window.currentUser.id;window.authRequest('api/kalender-akademik',{cache:'no-store'}).then(function(result){if(window.currentUser&&window.currentUser.id===user&&window.currentRole)setData(result.data)}).catch(function(){}).finally(function(){loading=false})}
  signature=JSON.stringify(data.entries||{});
  window.AIWalasCalendar={today:today,isWeekend:weekend,isHoliday:holiday,isSpecialHoliday:specialHoliday,isPjj:pjj,isReportDay:reportDay,isWithoutAttendance:withoutAttendance,attendanceOverride:attendanceOverride,entry:entry,getData:function(){return data},setData:setData,sync:sync,refresh:refresh};
  var root=document.getElementById('pageContent');if(root)new MutationObserver(refresh).observe(root,{childList:true,subtree:true});
  var login=document.getElementById('loginScreen');if(login)new MutationObserver(function(){refresh();sync()}).observe(login,{attributes:true,attributeFilter:['class']});
  document.addEventListener('visibilitychange',function(){refresh();sync()});setInterval(function(){refresh();sync()},30000);setTimeout(refresh,0);
})();
