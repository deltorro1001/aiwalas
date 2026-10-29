"""Read-only browser checks with a simulated clock and mocked API; no account changes."""
import sys,time
from pathlib import Path
from browser_client import CDP
c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies')
script="""window.__calendarTestNow='2026-10-31T08:00:00+07:00';const CalendarRealDate=Date;window.Date=class extends CalendarRealDate{constructor(...args){super(...(args.length?args:[window.__calendarTestNow]))}static now(){return new CalendarRealDate(window.__calendarTestNow).getTime()}};"""
c.call('Page.addScriptToEvaluateOnNewDocument',{'source':script})
c.call('Page.navigate',{'url':'http://localhost:8080/'})
c.wait("!!window.AIWalasCalendar && !!window.AIWalasPages?.studentAttendance && typeof render==='function'")
c.js("document.getElementById('loginScreen').classList.add('hidden');window.page='dashboard';document.getElementById('pageContent').innerHTML='<div class=page>Kalender</div>'")
for role,parent in [('walas',False),('assistant',False),('student',False),('student',True),('teacher',False),('guest',False)]:
    c.js("window.currentRole="+repr(role)+";window.currentUser={id:999999,name:'Uji Kalender',isParent:"+str(parent).lower()+"};window.AIWalasCalendar.refresh()")
    assert c.js("!document.getElementById('schoolHolidayBanner').hidden && document.getElementById('schoolHolidayBanner').textContent.includes('Libur')"),(role,parent)
for instant in ['2026-08-17T08:00:00+07:00','2026-08-18T08:00:00+07:00']:
    c.js("window.__calendarTestNow="+repr(instant))
    for role,parent in [('walas',False),('assistant',False),('student',False),('student',True),('teacher',False),('guest',False)]:
        c.js("window.currentRole="+repr(role)+";window.currentUser={id:999999,isParent:"+str(parent).lower()+"};window.AIWalasCalendar.refresh()")
        assert c.js("!document.getElementById('schoolHolidayBanner').hidden && !document.getElementById('schoolHolidayBanner').textContent.includes('Sabtu')"),(instant,role,parent)
for instant,holiday in [('2026-08-17T08:00:00+07:00',True),('2026-08-18T08:00:00+07:00',True),('2026-08-19T08:00:00+07:00',False),('2026-10-30T23:59:59+07:00',False),('2026-10-31T00:00:00+07:00',True),('2026-11-01T23:59:59+07:00',True),('2026-11-02T00:00:00+07:00',False)]:
    c.js("window.__calendarTestNow="+repr(instant)+";window.AIWalasCalendar.refresh()")
    assert c.js("!document.getElementById('schoolHolidayBanner').hidden")==holiday
c.js("window.__calendarTestNow='2026-10-31T08:00:00+07:00';window.activeClassId=999999;window.activeSemester='Ganjil';window.currentRole='student';window.currentUser={id:999999,name:'Uji Kalender',nis:'999999',isParent:true};window.authRequest=function(path){if(path.includes('rekap-absensi'))return Promise.resolve({data:[{tanggal:'2026-01-03',status:'Hadir',waktu_scan:'06:20:00'}],bulan:'2026-01',nomor_bulan:1,tahun:2026,jumlah_hari:31,label_bulan:'Januari 2026'});return Promise.resolve({data:[],kalender:{tanggal:'2026-10-31',hari_libur:true}})};window.page='attendance';window.render()")
c.wait("document.getElementById('studentAttendanceStatus')?.textContent==='Libur'")
c.wait("document.querySelectorAll('#studentAttendanceRecapRows tr').length===31")
rows=c.js("Array.from(document.querySelectorAll('#studentAttendanceRecapRows tr')).map(row=>Array.from(row.children).map(cell=>cell.textContent))")
assert rows[2][3]=='Hadir' and rows[3][3]=='Libur' and rows[4][3]=='Belum ada catatan',rows[:5]
for parent in [True,False]:
    c.js("window.currentUser.isParent="+str(parent).lower()+";window.authRequest=function(path){if(path.includes('rekap-absensi'))return Promise.resolve({data:[{tanggal:'2026-08-17',status:'Alpha',waktu_scan:'06:50:00'},{tanggal:'2026-08-18',status:'Terlambat',waktu_scan:'06:50:00'}],bulan:'2026-08',nomor_bulan:8,tahun:2026,jumlah_hari:31,label_bulan:'Agustus 2026'});return Promise.resolve({data:[],kalender:{hari_libur:true}})};window.render()")
    c.wait("document.getElementById('studentAttendanceRecapMonthLabel')?.textContent==='Agustus 2026'")
    rows=c.js("Array.from(document.querySelectorAll('#studentAttendanceRecapRows tr')).map(row=>Array.from(row.children).map(cell=>cell.textContent))")
    assert rows[16][2:] == ['—','Libur'] and rows[17][2:] == ['—','Libur'] and rows[18][3]=='Belum ada catatan',rows[16:19]
c.js("window.currentUser.isParent=false;window.render()")
c.wait("document.getElementById('studentAttendanceStatus')?.textContent==='Libur'")
c.js("window.currentRole='walas';window.students=[['Siswa Uji','999999','Menunggu keterangan','SU','','','',999999]];window.page='attendance';window.render()")
c.wait("!!document.querySelector('[data-attendance-status=Libur]')")
assert c.js("document.querySelector('.table-panel .panel-head h2').textContent.includes('Libur')")
c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
assert c.js('document.documentElement.scrollWidth<=window.innerWidth')
c.call('Emulation.clearDeviceMetricsOverride')
c.js("window.currentRole=null;window.AIWalasCalendar.refresh()")
assert c.js("document.getElementById('schoolHolidayBanner').hidden")
print('PASS: Holiday indication for every role, Jakarta day rollover, parent/student missing attendance, weekend calendar rows with preserved actual records, wali status and mobile layout.')
