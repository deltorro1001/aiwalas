(function(){
  var month='',year='',context='',version=0;
  var months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  var weekdays=new Intl.DateTimeFormat('id-ID',{timeZone:'Asia/Jakarta',weekday:'long'});
  function esc(value){return escapeHtml(value==null?'':value)}
  function active(){return window.currentRole==='student'&&window.page==='attendance'}
  function load(){
    if(!active())return;
    var section=document.getElementById('studentAttendanceRecap');if(!section)return;
    var request=++version,userId=(window.currentUser||{}).id,selection=month&&year?'?bulan='+encodeURIComponent(month)+'&tahun='+encodeURIComponent(year):'';
    document.getElementById('studentAttendanceRecapError').textContent='';
    document.getElementById('studentAttendanceRecapRows').innerHTML='<tr><td colspan="4">Memuat rekap absen...</td></tr>';
    authRequest('api/siswa/rekap-absensi'+selection,{cache:'no-store'}).then(function(result){
      if(request!==version||!active()||!section.isConnected||(window.currentUser||{}).id!==userId)return;
      document.getElementById('studentAttendanceRecapMonthLabel').textContent=result.label_bulan||'';
      if(!month&&!year){
        document.getElementById('studentAttendanceMonth').value=String(result.nomor_bulan).padStart(2,'0');
        var yearSelect=document.getElementById('studentAttendanceYear');
        if(!Array.from(yearSelect.options).some(function(option){return option.value===String(result.tahun)}))yearSelect.add(new Option(String(result.tahun),String(result.tahun)));
        yearSelect.value=String(result.tahun);
      }

      var byDate={},calendar=[];
      (result.data||[]).forEach(function(row){(byDate[row.tanggal]||(byDate[row.tanggal]=[])).push(row)});
      for(var day=1;day<=result.jumlah_hari;day++){
        var date=result.bulan+'-'+String(day).padStart(2,'0');
        var override=window.AIWalasCalendar&&window.AIWalasCalendar.attendanceOverride(date);
        if(override)calendar.push({tanggal:date,waktu_scan:null,status:override});
        else if(byDate[date])calendar=calendar.concat(byDate[date]);
        else calendar.push({tanggal:date,waktu_scan:null,status:window.AIWalasCalendar&&window.AIWalasCalendar.isHoliday(date)?'Libur':'Belum ada catatan'});
      }
      document.getElementById('studentAttendanceRecapRows').innerHTML=calendar.map(function(row){return '<tr data-attendance-status="'+esc(row.status)+'"><td data-attendance-date data-calendar-date="'+esc(row.tanggal)+'">'+esc(weekdays.format(new Date(row.tanggal+'T12:00:00+07:00')))+'</td><td data-attendance-date data-calendar-date="'+esc(row.tanggal)+'">'+esc(String(row.tanggal).split('-').reverse().join('/'))+'</td><td>'+esc(row.waktu_scan?row.waktu_scan+' WIB':'—')+'</td><td>'+esc(row.status)+'</td></tr>'}).join('')||'<tr><td colspan="4">Belum ada catatan absensi pada bulan ini.</td></tr>';
    }).catch(function(error){
      if(request!==version||!active()||!section.isConnected||(window.currentUser||{}).id!==userId)return;
      document.getElementById('studentAttendanceRecapMonthLabel').textContent='';
      document.getElementById('studentAttendanceRecapError').textContent=error.message||'Rekap absen gagal dimuat.';
      document.getElementById('studentAttendanceRecapRows').innerHTML='<tr><td colspan="4">Rekap absen tidak dapat dimuat.</td></tr>';
    });
  }
  function mount(){
    if(!active())return;
    var root=document.querySelector('.student-attendance-page');if(!root)return;
    var identity=String((window.currentUser||{}).id)+':'+window.activeClassId;
    if(context!==identity){context=identity;month='';year=''}
    var existing=document.getElementById('studentAttendanceRecap');if(existing)existing.remove();
    var section=document.createElement('section');section.id='studentAttendanceRecap';section.className='panel table-panel student-attendance-recap';
    var nowParts=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit'}).formatToParts(new Date());
    var currentYear=Number(nowParts.find(function(part){return part.type==='year'}).value),currentMonth=nowParts.find(function(part){return part.type==='month'}).value;
    var yearOptions='';for(var optionYear=2026;optionYear<=Math.max(currentYear+10,Number(year)||0,2027);optionYear++)yearOptions+='<option value="'+optionYear+'">'+optionYear+'</option>';

    section.innerHTML='<div class="panel-head"><div><h2>Rekap Absen</h2><p id="studentAttendanceRecapMonthLabel"></p></div><div class="student-attendance-filters"><label class="student-attendance-filter" for="studentAttendanceMonth">Bulan <select id="studentAttendanceMonth" class="filter-btn">'+months.map(function(label,index){return '<option value="'+String(index+1).padStart(2,'0')+'">'+label+'</option>'}).join('')+'</select></label><label class="student-attendance-filter" for="studentAttendanceYear">Tahun <select id="studentAttendanceYear" class="filter-btn">'+yearOptions+'</select></label></div></div><p id="studentAttendanceRecapError" class="login-error" role="alert"></p><div class="table-wrap"><table aria-label="Rekap Absen"><thead><tr><th scope="col">Hari</th><th scope="col">Tanggal</th><th scope="col">Waktu Scan</th><th scope="col">Status</th></tr></thead><tbody id="studentAttendanceRecapRows" aria-live="polite"></tbody></table></div>';
    root.appendChild(section);
    var monthSelect=document.getElementById('studentAttendanceMonth'),yearSelect=document.getElementById('studentAttendanceYear');
    monthSelect.value=month||currentMonth;yearSelect.value=year||String(currentYear);
    function change(){month=monthSelect.value;year=yearSelect.value;load()}
    monthSelect.onchange=change;yearSelect.onchange=change;
    load();
  }
  window.AIWalasStudentAttendanceRecap={mount:mount,refresh:load};
})();
