"""Read-only color regression using a mocked clock/API. No database or gateway writes."""
from browser_client import CDP
c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies')
c.call('Page.addScriptToEvaluateOnNewDocument',{'source':"window.__dateTestNow='2026-10-31T08:00:00+07:00';const RealDate=Date;window.Date=class extends RealDate{constructor(...args){super(...(args.length?args:[window.__dateTestNow]))}static now(){return new RealDate(window.__dateTestNow).getTime()}};"})
c.call('Page.navigate',{'url':'http://localhost:8080/'})
c.wait("!!window.AIWalasAttendanceColors && !!window.AIWalasPages?.studentAttendance")
# Exclude cached Runtime events from the tab used by a previous test run.
c.events.clear()
c.js("""
document.getElementById('loginScreen').classList.add('hidden');
window.activeClassId=999999;window.activeSemester='Ganjil';window.students=[['Siswa Uji','999999','Hadir','SU','','','',999999]];
window.authRequest=function(path){
 if(path.includes('rekap-absensi'))return Promise.resolve({data:[{tanggal:'2026-10-19',status:'Sakit',waktu_scan:null},{tanggal:'2026-10-20',status:'Izin',waktu_scan:null},{tanggal:'2026-10-21',status:'PJJ',waktu_scan:null},{tanggal:'2026-10-22',status:'Hadir',waktu_scan:'06:30:00'}],bulan:'2026-10',nomor_bulan:10,tahun:2026,jumlah_hari:31,label_bulan:'Oktober 2026'});
 if(path.startsWith('api/absensi?')){var date=new URL(path,location.href).searchParams.get('tanggal')||AIWalasCalendar.today();var status=date==='2026-10-19'?'Sakit':date==='2026-10-20'?'Izin':AIWalasCalendar.isHoliday(date)?'Libur':AIWalasCalendar.isPjj(date)?'PJJ':'Hadir';return Promise.resolve({data:[{siswa_id:999999,tanggal:date,status:status,waktu_scan:status==='Hadir'?'06:30:00':null}],kalender:{tanggal:date,hari_libur:AIWalasCalendar.isHoliday(date),status:AIWalasCalendar.attendanceOverride(date)}})}
 if(path.startsWith('api/jurnal'))return Promise.resolve({data:[{id:1,tanggal:'2026-10-31',nama_mata_pelajaran:'Mapel',materi:'Materi',catatan:'',nama_pembuat:'Guru',can_edit:false}],can_create:false,minimum_date:'2026-01-01',assignments:[]});
 return Promise.resolve({data:[]});
};
""")
red='rgb(220, 38, 38)';green='rgb(21, 128, 61)';brown='rgb(107, 63, 29)'
def color(selector):return c.js("getComputedStyle(document.querySelector("+repr(selector)+")).color")
for role,parent in [('walas',False),('assistant',False),('teacher',False),('student',False),('student',True),('guest',False)]:
    c.js("window.currentRole="+repr(role)+";window.currentUser={id:999999,name:'Uji Kalender',isParent:"+str(parent).lower()+"};window.page='dashboard';AIWalasCalendar.refresh()")
    assert color('#schoolHolidayBanner')==red,(role,parent)
    assert 'Sabtu' in c.js("document.getElementById('schoolHolidayBanner').textContent")
for parent in [False,True]:
    c.js("window.currentRole='student';window.currentUser={id:999999,name:'Siswa Uji',nis:'999999',isParent:"+str(parent).lower()+"};window.page='attendance';window.render()")
    c.wait("document.querySelectorAll('#studentAttendanceRecapRows tr').length===31")
    c.js('AIWalasAttendanceColors.refresh()')
    for status,expected in [('Libur',red),('Sakit',green),('Izin',brown),('Hadir','rgb(0, 0, 0)')]:
        row='#studentAttendanceRecapRows [data-attendance-status='+status+']'
        for index in [1,2,4]:assert color(row+' td:nth-child('+str(index)+')')==expected,(status,index)
    assert color('#studentAttendanceRecapRows [data-attendance-status=Hadir] td:nth-child(3)')=='rgb(0, 0, 0)'
    # Colors are limited to date/status labels, never the scan placeholder.
    assert color('#studentAttendanceRecapRows [data-attendance-status=Libur] td:nth-child(3)')!=red
    assert color('#studentAttendanceStatus')==red and color('#studentAttendanceDate')==red
    for day,status,expected in [('19','Sakit',green),('20','Izin',brown),('22','Hadir','rgb(0, 0, 0)')]:
        c.js("window.__dateTestNow='2026-10-"+day+"T08:00:00+07:00';window.render()")
        c.wait("document.getElementById('studentAttendanceStatus')?.textContent==='"+status+"'")
        c.js('AIWalasAttendanceColors.refresh()')
        assert color('#studentAttendanceDate')==expected
        if status=='Hadir':
            assert color('#studentAttendanceScan')==expected
            assert c.js("document.getElementById('studentAttendanceScan').textContent")=='06:30:00 WIB'
    c.js("window.__dateTestNow='2026-10-31T08:00:00+07:00'")
for user_id,role in enumerate(['walas','assistant','teacher'],1000000):
    c.js("window.currentRole='"+role+"';window.currentUser={id:"+str(user_id)+",name:'Uji',isParent:false};window.page='attendance';window.render()")
    c.wait("!!document.querySelector('[data-attendance-status=Libur]')")
    c.js('AIWalasAttendanceColors.refresh()')
    for selector in ['.attendance-page .page-heading p','.attendance-page .table-panel .panel-head h2','#attendanceViewDate','[data-attendance-status=Libur] .status']:
        assert color(selector)==red,(role,selector)
    assert color('[data-attendance-status=Libur] .student-cell strong')!=red
    c.js("document.getElementById('attendanceViewDate').value='2026-08-27';document.getElementById('attendanceViewDate').dispatchEvent(new Event('change'))")
    c.wait("!!document.querySelector('[data-attendance-status=PJJ]')")
    c.js('AIWalasAttendanceColors.refresh();AIWalasCalendar.refresh()')
    assert color('#schoolHolidayBanner')!=red and color('#attendanceViewDate')!=red
    c.js("document.getElementById('attendanceViewDate').value='2026-10-22';document.getElementById('attendanceViewDate').dispatchEvent(new Event('change'))")
    c.wait("!!document.querySelector('tr[data-attendance-status=Hadir]')")
    c.js('AIWalasAttendanceColors.refresh()')
    assert color('tr[data-attendance-status=Hadir] .status')=='rgb(0, 0, 0)'
    assert color('tr[data-attendance-status=Hadir] [data-attendance-time]')=='rgb(0, 0, 0)'

# Journal dates carry holiday colors for roles that can read journals. Guest uses its public calendar.
for role in ['walas','assistant','teacher','student']:
    c.js("window.currentRole='"+role+"';window.currentUser={id:999999,name:'Uji',isParent:false};window.page='journal';render()")
    c.wait("document.getElementById('journalDateCaption')?.textContent.includes('jurnal')")
    c.js('AIWalasAttendanceColors.refresh()')
    assert color('#journalDateCaption')==red and color('#journalRows td:nth-child(2)')==red
# Programmatic dates and in-place changes clear stale holiday styles.
c.js("document.getElementById('journalViewDate').value='2026-11-02';document.getElementById('journalViewDate').dispatchEvent(new Event('change'))")
c.wait("document.getElementById('journalDateCaption')?.textContent.includes('Senin')")
c.js('AIWalasAttendanceColors.refresh()');assert color('#journalDateCaption')!=red
# Home arrival time and live updates follow the same present-color rule.
c.js("""
window.__dateTestNow='2026-10-22T08:00:00+07:00';window.__presentStatus='Hadir';
const previousColorRequest=window.authRequest;
window.authRequest=function(path){
 if(path.startsWith('api/siswa/dashboard'))return Promise.resolve({data:{student:{nama:'Siswa Uji',nis:'999999'},scan:{status:'Hadir',waktu:'06:30:00',tanggal:'2026-10-22'},absensi:{},ringkasan_kelas:{nama_kelas:'11PF1',status:{Hadir:1},siswa_tidak_hadir:[],menunggu_keterangan:[],tren_absensi:Array.from({length:5},()=>({Sakit:0,Izin:0,Terlambat:0}))},kalender:{hari_libur:false},keterlambatan:{riwayat:[]},kedisiplinan:{catatan:[]},notifikasi_whatsapp:[],jadwal_hari_ini:[]}});
 if(path.startsWith('api/absensi?'))return Promise.resolve({data:[{siswa_id:999999,status:window.__presentStatus,waktu_scan:window.__presentStatus==='Hadir'?'06:30:00':null}],kalender:{tanggal:'2026-10-22',hari_libur:false}});
 if(path.startsWith('api/tamu'))return Promise.resolve({sekolah:'Sekolah Uji',kelas:{id:1,nama_kelas:'11PF1',nama_tahun_ajaran:'2026/2027'},daftar_kelas:[{id:1,nama_kelas:'11PF1',nama_tahun_ajaran:'2026/2027'}],tanggal:'2026-10-22',jumlah_siswa:7,kehadiran:{Hadir:7},jadwal:[],pengumuman:[],kalender:[],bulan:10,tahun:2026});
 return previousColorRequest(path);
};
""")
for parent in [False,True]:
    c.js("window.currentRole='student';window.currentUser={id:999999,name:'Siswa Uji',nis:'999999',isParent:"+str(parent).lower()+"};window.page='dashboard';render()")
    c.wait("document.querySelector('.student-current-attendance')?.textContent==='Hadir'")
    c.js('AIWalasAttendanceColors.refresh()')
    assert color('.student-home [data-attendance-time]')=='rgb(0, 0, 0)'
    c.js("window.__presentStatus='Izin';AIWalasDashboardAttendance.refresh()")
    c.wait("document.querySelector('.student-current-attendance')?.textContent==='Izin'")
    c.js('AIWalasAttendanceColors.refresh()')
    assert color('.student-home [data-attendance-time]')!='rgb(0, 0, 0)'
    c.js("window.__presentStatus='Hadir';AIWalasDashboardAttendance.refresh()")
    c.wait("document.querySelector('.student-current-attendance')?.textContent==='Hadir'")
    assert color('.student-home [data-attendance-time]')=='rgb(0, 0, 0)'
c.js("window.currentRole='guest';window.currentUser={id:999997,name:'Tamu'};window.page='dashboard';applyRoleAccess();render()")
c.wait("!!document.querySelector('.guest-status-list .status.present')")
c.js('AIWalasAttendanceColors.refresh()')
assert color('.guest-status-list .status.present')=='rgb(0, 0, 0)'
assert color('.guest-status-list strong')=='rgb(0, 0, 0)'
for name in ['attendance-status-colors','student-attendance-recap','student-qr-attendance','school-calendar','attendance-ui','dashboard-summary','teacher-journal-ui','parent-sick-request','parent-leave-request','student-home-force','dashboard-attendance-live']:
    assert c.js("fetch('assets/"+name+".js').then(r=>r.text()).then(s=>{new Function(s);return true})")
c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
assert c.js('document.documentElement.scrollWidth<=innerWidth')
c.call('Emulation.clearDeviceMetricsOverride')
errors=[e['params']['exceptionDetails'] for e in c.events if e.get('method')=='Runtime.exceptionThrown']
assert not errors,[(e.get('exception',{}).get('description',e.get('text')),e.get('stackTrace')) for e in errors]
print('PASS: Hadir black on all-role status/day/date/scan, student/parent home and live updates, guest aggregate labels/counts; Libur/Sakit/Izin/PJJ colors retained, journal dates, JS syntax and mobile width.')
