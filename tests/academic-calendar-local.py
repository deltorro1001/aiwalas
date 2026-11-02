"""Local DB + actual HTTP/role/CSRF + optional Chrome UI. Fixtures cleaned; no gateway calls."""
import os,re,secrets,subprocess,json,urllib.request,urllib.error,http.cookiejar,datetime,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
MYSQL='/opt/homebrew/bin/mysql';PHP='/opt/homebrew/opt/php@8.3/bin/php';BASE='http://localhost:8080/'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
def quote(s):return "'"+str(s).replace('\\','\\\\').replace("'","\\'")+"'"
def insert(table,data):return int(sql('INSERT INTO '+table+' ('+','.join(data)+') VALUES ('+','.join(quote(v) for v in data.values())+');SELECT LAST_INSERT_ID();'))
class Client:
 def __init__(self):self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=''
 def call(self,path,method='GET',data=None):
  if method!='GET' and path!='api/auth/csrf':self.call('api/auth/csrf')
  req=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),method=method,headers={'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':self.csrf})
  try:r=self.op.open(req)
  except urllib.error.HTTPError as e:r=e
  result=json.load(r);self.csrf=result.get('csrfHash',self.csrf);return r.code,result
 def login(self,role,name,password):
  code,data=self.call('api/auth/login','POST',{'role':role,'username':name,'password':password});assert code==200,(role,code,data.get('message'))
prefix='calendar_'+secrets.token_hex(5);password=secrets.token_hex(16)
hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
users=[];classes=[];students=[];year=None;teacher=None;subject=None
monday=next(datetime.date(2099,2,d) for d in range(1,8) if datetime.date(2099,2,d).weekday()==0)
dates=[str(monday+datetime.timedelta(days=n)) for n in [0,1,2,5]];official='2027-12-25';touched=dates+[official]
assert sql('SELECT COUNT(*) FROM kalender_akademik WHERE tanggal IN ('+','.join(map(quote,touched))+')')=='0','Test dates already configured'
try:
 def user(role,suffix,**extra):
  name=prefix+'_'+suffix;uid=insert('pengguna',dict(nama_pengguna=name,kata_sandi=hashed,nama_lengkap='Fixture '+suffix,peran=role,aktif=1,**extra));users.append(uid);return uid,name
 owner,name=user('wali_kelas','wali');other,othername=user('wali_kelas','other')
 year=insert('tahun_ajaran',dict(nama_tahun_ajaran=prefix,semester='Genap',aktif=1))
 for n,who in enumerate([owner,other]):
  cid=insert('kelas',dict(nama_kelas=prefix+str(n),tingkat=11,jurusan='Test',tahun_ajaran_id=year,wali_kelas_id=who));classes.append(cid)
  sid=insert('siswa',dict(nis=prefix+str(n),nama_lengkap='Fixture Siswa '+str(n),jenis_kelamin='L',kelas_id=cid,aktif=1));students.append(sid)
 teacher=insert('guru',dict(nama_lengkap=prefix,jenis_kelamin='L',aktif=1))
 subject=insert('mata_pelajaran',dict(nama_mata_pelajaran=prefix,aktif=1))
 for cid in classes:insert('pengampu_mapel',dict(guru_id=teacher,kelas_id=cid,mata_pelajaran_id=subject))
 accounts={}
 for role,dbrole,extra in [('student','siswa',{'nis':prefix+'0'}),('assistant','sekretaris',{'nis':prefix+'1'}),('parent','orang_tua',{'siswa_id':students[0]}),('teacher','guru',{'guru_id':teacher}),('guest','guest',{})]:
  uid,username=user(dbrole,role,**extra);accounts[role]=prefix+'0' if role=='parent' else username
 for cid,sid in zip(classes,students):
  for date,status in zip(dates,['Alpha','Terlambat','Sakit','Izin']):insert('absensi',dict(kelas_id=cid,siswa_id=sid,tanggal=date,semester='Genap',status=status,waktu_scan='06:50:00',dicatat_oleh=owner))
  insert('absensi',dict(kelas_id=cid,siswa_id=sid,tanggal=official,semester='Ganjil',status='Alpha',dicatat_oleh=owner))
 wali=Client();wali.login('walas',name,password)
 assert Client().call('api/kalender-akademik')[0]==401
 clients={}
 for role,username in accounts.items():
  client=Client();client.login(role,username,password);clients[role]=client
  assert client.call('api/kalender-akademik')[0]==200,role
  assert client.call('api/kalender-akademik','POST',{})[0]==403,role
  assert client.call('api/kalender-akademik/'+dates[0],'DELETE',{})[0]==403,role
 payload=dict(tanggal_mulai=dates[0],tanggal_selesai=dates[1],jenis='PJJ',judul='PJJ Fixture',dampak_absensi='Normal',keterangan='Perubahan pembelajaran fixture')
 code,data=wali.call('api/kalender-akademik','POST',payload);assert code==200,(code,data)
 assert data['data']['entries'][dates[0]]['dampak_absensi']=='PJJ','Type must enforce effect'
 for role,client in clients.items():
  assert client.call('api/kalender-akademik')[1]['data']['entries'][dates[1]]['dampak_absensi']=='PJJ',role
  if role!='guest':
   for cid in ([classes[1]] if role=='assistant' else classes):
    # Student/parent only receive their own rows; check the appropriate class.
    if role in ['student','parent'] and cid!=classes[0]:continue
    code,result=client.call('api/absensi?kelas_id='+str(cid)+'&tanggal='+dates[0]+'&semester=Genap')
    assert code==200 and result['kalender']['status']=='PJJ',(role,code,result)
    assert all(r['status']=='PJJ' and r['waktu_scan'] is None for r in result['data']),role
 def recap(cid=classes[0],yr=2099,semester='Genap'):
  code,result=wali.call('api/absensi/rekap-kelas?kelas_id='+str(cid)+'&tahun='+str(yr)+'&semester='+semester);assert code==200,(code,result);return result
 assert recap()['total']=={'sakit':1,'izin':0,'alpha':0,'terlambat':0},recap()['total']
 code,report=wali.call('api/laporan?kelas_id='+str(classes[0])+'&semester=Genap');assert code==200 and int(report['data']['siswa'][0]['jumlah_absen'])==1,report
 assert recap(yr=2027,semester='Ganjil')['total']['alpha']==0,'National holiday counted absent'
 for role in ['student','parent']:
  result=clients[role].call('api/siswa/rekap-absensi?bulan=2&tahun=2099')[1]
  assert [r for r in result['data'] if r['tanggal']==dates[0]][0]['status']=='PJJ',role
 # Removing one date reveals original attendance, without modifying its stored record.
 assert wali.call('api/kalender-akademik/'+dates[0],'DELETE',{})[0]==200
 assert recap()['total']['alpha']==1
 assert sql('SELECT status FROM absensi WHERE siswa_id='+str(students[0])+' AND tanggal='+quote(dates[0]))=='Alpha'
 # School override of a national holiday, then restoration to government baseline.
 p=dict(payload,tanggal_mulai=official,tanggal_selesai=official)
 assert wali.call('api/kalender-akademik','POST',p)[0]==200
 assert wali.call('api/kalender-akademik/'+official,'DELETE',{})[1]['data']['entries'][official]['jenis']=='Libur Nasional'
 # A school day on Saturday deliberately enables ordinary attendance and counts.
 p=dict(payload,tanggal_mulai=dates[3],tanggal_selesai=dates[3],jenis='Agenda Lainnya',dampak_absensi='Normal',judul='Hari sekolah pengganti')
 assert wali.call('api/kalender-akademik','POST',p)[0]==200
 assert recap()['total']['izin']==1
 for invalid in [dict(payload,tanggal_mulai='2099-02-31'),dict(payload,tanggal_selesai='2099-01-01'),dict(payload,tanggal_selesai='2101-01-01'),dict(payload,judul=''),dict(payload,dampak_absensi='Hadir')]:
  assert wali.call('api/kalender-akademik','POST',invalid)[0]==422
 # UI integration uses the same temporary account. No mocking of the calendar API.
 if '--browser' in sys.argv:
  from browser_client import CDP
  c=CDP();c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE});c.wait("typeof authRequest==='function'&&!!window.AIWalasCalendar&&!!window.AIWalasPages.academicCalendar")
  credentials=json.dumps(dict(role='walas',username=name,password=password))
  c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(d=>finishLogin(d.user))")
  c.wait("currentRole==='walas'&&!!currentUser")
  c.js("page='academicCalendar';render()")
  c.wait("!!document.getElementById('academicForm')")
  c.js("document.getElementById('academicYear').value='2099';document.getElementById('academicYear').dispatchEvent(new Event('change'));document.getElementById('academicMonth').value='2';document.getElementById('academicMonth').dispatchEvent(new Event('change'))")
  c.js("document.querySelector('[data-academic-date=\""+dates[2]+"\"]').click();var f=document.getElementById('academicForm');f.elements.jenis.value='Hari Pengambilan Raport';f.elements.jenis.dispatchEvent(new Event('change'))")
  assert c.js("document.getElementById('academicForm').elements.dampak_absensi.value==='TanpaAbsen'"),'Report day default'
  c.js("document.getElementById('academicForm').dispatchEvent(new Event('submit',{cancelable:true,bubbles:true}))")
  c.wait("document.querySelector('.academic-current')?.textContent.includes('Hari Pengambilan Raport')")
  assert recap()['total']['sakit']==0,'UI save did not affect recap'
  item=wali.call('api/kalender-akademik')[1]['data']['entries'][dates[2]]
  assert item['judul']=='Pengambilan Raport' and item['dampak_absensi']=='TanpaAbsen',item
  for role,client in clients.items():
   if role=='guest':continue
   code,result=client.call('api/absensi?kelas_id='+str(classes[0])+'&tanggal='+dates[2]+'&semester=Genap')
   if role=='assistant':continue
   assert result['kalender']['status']=='Pengambilan Raport' and result['kalender']['hari_libur'] is False,(role,result)
   assert all(r['status']=='Pengambilan Raport' and r['waktu_scan'] is None for r in result['data']),role
  assert wali.call('api/absensi','POST',dict(siswa_id=students[0],kelas_id=classes[0],tanggal=dates[2],semester='Genap',status='Hadir'))[0]==422
  c.js("var f=document.getElementById('academicForm');f.elements.judul.value='';f.elements.judul.dispatchEvent(new Event('change'));f.dispatchEvent(new Event('submit',{cancelable:true,bubbles:true}))")
  c.wait("AIWalasCalendar.entry('"+dates[2]+"').judul==='' && !document.getElementById('academicForm').dataset.saving")
  item=wali.call('api/kalender-akademik')[1]['data']['entries'][dates[2]]
  assert item['judul']=='' and item['dampak_absensi']=='TanpaAbsen','Untitled agenda lost its effect'
  c.js("var f=document.getElementById('academicForm');f.elements.judul.value='__custom__';f.elements.judul.dispatchEvent(new Event('change'));f.elements.judul_kustom.value='Raport semester khusus';f.dispatchEvent(new Event('submit',{cancelable:true,bubbles:true}))")
  c.wait("AIWalasCalendar.entry('"+dates[2]+"').judul==='Raport semester khusus' && !document.getElementById('academicForm').dataset.saving")
  # The rendered daily table, student card, parent card, and monthly recap use the same blue label.
  c.js("activeClassId="+str(classes[0])+";students=[['Fixture Siswa','"+prefix+"0','','FS','','','',"+str(students[0])+"]];currentRole='walas';page='attendance';render()")
  c.wait("!!document.getElementById('attendanceViewDate')")
  c.js("document.getElementById('attendanceViewDate').value='"+dates[2]+"';document.getElementById('attendanceViewDate').dispatchEvent(new Event('change'))")
  c.wait("!!document.querySelector('.table-panel .status.report')")
  assert c.js("document.querySelector('.table-panel .status.report').textContent==='Pengambilan Raport'&&getComputedStyle(document.querySelector('.table-panel .status.report')).color==='rgb(37, 99, 235)'")
  # Force the selected day for role-specific attendance cards without touching today's real calendar.
  c.js("window.__calendarRealRequest=authRequest;window.authRequest=function(path,options){if(path.startsWith('api/absensi?'))return Promise.resolve({data:[{tanggal:'"+dates[2]+"',status:'Pengambilan Raport',waktu_scan:null}],kalender:{status:'Pengambilan Raport',hari_libur:false,tanpa_absensi:true}});if(path.includes('rekap-absensi'))return Promise.resolve({data:[{tanggal:'"+dates[2]+"',status:'Pengambilan Raport',waktu_scan:null}],bulan:'2099-02',nomor_bulan:2,tahun:2099,jumlah_hari:28,label_bulan:'Februari 2099'});return __calendarRealRequest(path,options)}")
  for parent in [False,True]:
   c.js("currentRole='student';currentUser.isParent="+str(parent).lower()+";page='attendance';render()")
   c.wait("document.getElementById('studentAttendanceStatus')?.textContent==='Pengambilan Raport'")
   assert c.js("getComputedStyle(document.getElementById('studentAttendanceStatus')).color==='rgb(37, 99, 235)'")
   c.wait("!!document.querySelector('#studentAttendanceRecapRows .status.report')")
   assert c.js("getComputedStyle(document.querySelector('#studentAttendanceRecapRows .status.report')).color==='rgb(37, 99, 235)'")
   if not parent:assert c.js("document.getElementById('studentQrButton').disabled")
  c.js("authRequest=__calendarRealRequest;currentRole='walas';currentUser.isParent=false;page='academicCalendar';render()")
  c.wait("!!document.getElementById('academicForm')")
  for role in ['student','assistant','teacher','guest']:
   c.js("currentRole="+json.dumps(role)+";applyRoleAccess()")
   assert c.js("getComputedStyle(document.querySelector('[data-page=academicCalendar]')).display==='none'"),role
  c.js("currentRole='walas';applyRoleAccess()")
  c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
  assert c.js('document.documentElement.scrollWidth<=window.innerWidth'),'Mobile horizontal overflow'
  c.call('Emulation.clearDeviceMetricsOverride')
  # Shared browser rules update immediately, including ordinary-school overrides.
  assert c.js("AIWalasCalendar.isHoliday('2027-12-25')&&!AIWalasCalendar.isHoliday('"+dates[3]+"')&&AIWalasCalendar.isPjj('"+dates[1]+"')")
 print('PASS: Official holidays, global date ranges, all role read access, Wali-only writes, PJJ normalization, daily/student/class recap, retained original attendance, delete restoration, school-day weekend, validation'+(', real UI/report-day save/mobile/menu visibility' if '--browser' in sys.argv else '')+'. No WhatsApp calls.')
finally:
 if 'c' in globals():
  try:c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'})")
  except Exception:pass
 sql('DELETE FROM kalender_akademik WHERE tanggal IN ('+','.join(map(quote,touched))+')')
 if classes:
  ids=','.join(map(str,classes))
  for table in ['absensi','pengampu_mapel']:sql('DELETE FROM '+table+' WHERE kelas_id IN ('+ids+')')
 if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(map(str,users))+')')
 if students:sql('DELETE FROM siswa WHERE id IN ('+','.join(map(str,students))+')')
 if classes:sql('DELETE FROM kelas WHERE id IN ('+','.join(map(str,classes))+')')
 if subject:sql('DELETE FROM mata_pelajaran WHERE id='+str(subject))
 if teacher:sql('DELETE FROM guru WHERE id='+str(teacher))
 if year:sql('DELETE FROM tahun_ajaran WHERE id='+str(year))
