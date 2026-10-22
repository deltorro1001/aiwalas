"""Local API/browser checks for preserved student home cards. Disposable fixtures only.
Run: AIWALAS_BROWSER_TEST=1 python3 tests/student-home-local.py
"""
import time,datetime,http.cookiejar,json,os,re,secrets,shutil,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP=shutil.which('php') or '/opt/homebrew/opt/php@8.3/bin/php';MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
prefix='shome_'+secrets.token_hex(5);password=secrets.token_hex(16);hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
users=[];teacher=None;subject=None;year=None;class_id=None;classes=[];pupils=[]
class Client:
    def __init__(self):
        self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        html=self.op.open(BASE).read().decode();self.token=re.search(r"name=['\"]csrf-token['\"] content=['\"]([^'\"]+)",html).group(1)
    def call(self,path,data=None,status=200):
        req=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),headers={'Content-Type':'application/json','X-CSRF-TOKEN':self.token})
        try:r=self.op.open(req,timeout=10)
        except urllib.error.HTTPError as e:r=e
        result=json.load(r);assert r.code==status,(path,r.code,result);self.token=result.get('csrfHash',self.token);return result
try:
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_wali','{hashed}','Test Wali','wali_kelas');SELECT LAST_INSERT_ID();"))
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{prefix}','Ganjil',1);SELECT LAST_INSERT_ID();")
    for i in range(2):classes.append(sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}_{i}',11,'Test',{year},{users[0]});SELECT LAST_INSERT_ID();"))
    class_id=classes[0]
    names=['Test Hadir','Test Terlambat','Test <img src=x onerror=alert(1)>','Test Izin','Test Alpha','Test Belum Scan','Test Menunggu','Inactive Peer','Foreign Peer']
    today=datetime.datetime.now(ZoneInfo('Asia/Jakarta')).date()
    statuses=['Hadir','Terlambat','Sakit','Izin','Alpha',None,'Menunggu keterangan','Sakit','Sakit']
    for i,name in enumerate(names):
        klass=classes[1] if i==8 else class_id
        pupils.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{prefix}_{i}','{name}','L',{klass},{0 if i==7 else 1});SELECT LAST_INSERT_ID();"))
        if statuses[i]:sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,semester,dicatat_oleh,keterangan) VALUES({pupils[-1]},{klass},'{today}','{statuses[i]}','Ganjil',{users[0]},'Private note');")
    sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,semester,dicatat_oleh) VALUES({pupils[0]},{class_id},'{today}','Sakit','Genap',{users[0]});")
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{prefix}_0','{hashed}','Test Hadir','siswa','{prefix}_0');SELECT LAST_INSERT_ID();"))
    teacher=sql("INSERT INTO guru(nama_lengkap,nama_panggilan,jenis_kelamin,aktif) VALUES('Test Guru','Test Guru','L',1);SELECT LAST_INSERT_ID();")
    subject=sql(f"INSERT INTO mata_pelajaran(nama_mata_pelajaran,aktif) VALUES('{prefix}_mapel',1);SELECT LAST_INSERT_ID();")
    weekday=['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'][today.weekday()]
    sql(f"INSERT INTO jadwal_kelas(kelas_id,mata_pelajaran_id,guru_id,hari,jam_mulai,jam_selesai) VALUES({class_id},{subject},{teacher},'{weekday}','08:00:00','09:00:00');")
    endpoint='api/siswa/dashboard?semester=Ganjil&kelas_id='+classes[1]
    anonymous=Client();anonymous.call(endpoint,status=401)
    a=Client();a.call('api/auth/login',{'role':'student','username':prefix+'_0','password':password})
    data=a.call(endpoint)['data'];summary=data['ringkasan_kelas']
    assert sum(summary['status'].values())==7 and summary['status']['Menunggu keterangan']==2,summary
    assert summary['status']['Sakit']==1 and summary['status']['Terlambat']==1
    assert len(summary['siswa_tidak_hadir'])==3 and len(summary['menunggu_keterangan'])==2
    assert summary['nama_kelas']==prefix+'_0' and len(data['jadwal_hari_ini'])==1
    assert not any(x in json.dumps(summary) for x in ['Private note','nomor_hp','Inactive Peer','Foreign Peer','nis'])
    assert len(summary['tren_absensi'])==5
    if today.weekday()<5:
        row=summary['tren_absensi'][today.weekday()];assert row['Sakit']==1 and row['Izin']==1 and row['Terlambat']==1
    genap=a.call('api/siswa/dashboard?semester=Genap')['data']['ringkasan_kelas']
    assert genap['status']['Sakit']==1 and genap['status']['Menunggu keterangan']==6
    a.call('api/siswa/dashboard?semester=invalid',status=422)
    assert data['kedisiplinan']['catatan']==[] and 'notifikasi_wali' not in data
    anonymous.call('api/kedisiplinan?kelas_id='+class_id,status=401)
    a.call('api/kedisiplinan?kelas_id='+class_id,status=403)
    a.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':'etika','pelanggaran':'Unauthorized'},status=403)
    wali=Client();wali.call('api/auth/login',{'role':'walas','username':prefix+'_wali','password':password})
    late_peer=next(x for x in wali.call('api/kedisiplinan?kelas_id='+class_id)['data'] if str(x['id'])==pupils[1])
    assert late_peer['keterlambatan']['jumlah']==1 and late_peer['catatan'][0]['kategori']=='terlambat'
    for category,values in [('etika',{'pelanggaran':'Catatan Etika Awal','tanggal':str(today),'saksi':'Test Wali','alasan':'Alasan etika','tindakan':'Pembinaan'}),('atribut',{'pelanggaran':'Atribut kurang lengkap','perbaikan':'Lengkapi atribut'}),('terlambat',{'jumlah_terlambat':2,'tanggal':str(today),'alasan':'Alasan terlambat'})]:
        wali.call('api/kedisiplinan',dict(siswa_id=int(pupils[0]),kategori=category,**values))
    wali.call('api/kedisiplinan',{'siswa_id':int(pupils[2]),'kategori':'etika','pelanggaran':'CATATAN SISWA LAIN'})
    own_notes=a.call('api/siswa/dashboard?kelas_id='+classes[1])['data']['kedisiplinan']['catatan']
    assert len(own_notes)==3 and 'CATATAN SISWA LAIN' not in json.dumps(own_notes)
    assert own_notes[1]['status']=='Dalam Peringatan (Terlambat 2x)'
    wali.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':'etika','tanggal':'2026-02-31'},status=422)
    wali.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':'terlambat','jumlah_terlambat':-1},status=422)
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_other','{hashed}','Other Wali','wali_kelas');SELECT LAST_INSERT_ID();"))
    outsider=Client();outsider.call('api/auth/login',{'role':'walas','username':prefix+'_other','password':password})
    outsider.call('api/kedisiplinan?kelas_id='+class_id,status=404)
    outsider.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':'etika','pelanggaran':'Other Wali'},status=404)

    sql(f"UPDATE absensi SET waktu_scan='06:12:34' WHERE siswa_id={pupils[0]} AND tanggal='{today}' AND semester='Ganjil';")
    attendance_endpoint='api/absensi?kelas_id='+class_id+'&semester=Ganjil'
    own=a.call(attendance_endpoint)['data']
    assert len(own)==1 and str(own[0]['siswa_id'])==pupils[0] and own[0]['waktu_scan']=='06:12:34' and own[0]['status']=='Hadir'
    assert not a.call('api/absensi?kelas_id='+classes[1])['data']
    assert a.call('api/absensi?kelas_id='+class_id+'&semester=Genap')['data'][0]['waktu_scan'] is None
    yesterday=today-datetime.timedelta(days=1)
    sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,waktu_scan,semester,dicatat_oleh) VALUES({pupils[0]},{class_id},'{yesterday}','Hadir','05:55:55','Ganjil',{users[0]});")
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies')
        c.call('Page.navigate',{'url':BASE});c.wait("typeof authRequest==='function' && !!window.AIWalasPages && window.render.toString().includes('var studentRoutes')")
        wali_credentials=json.dumps({'role':'walas','username':prefix+'_wali','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+wali_credentials+")}).then(function(d){return finishLogin(d.user)}).then(function(){return authRequest('api/bootstrap?kelas_id="+class_id+"')}).then(function(d){applyBootstrapData(d);window.page='discipline';window.render()})")
        c.wait("!!document.querySelector('[data-stored-discipline=\""+pupils[0]+"\"]')")
        c.js("document.querySelector('[data-stored-discipline=\""+pupils[0]+"\"]').click()")
        c.js("document.getElementById('storedDiscipline_pelanggaran').value='Etika UI <img src=x onerror=alert(1)>';document.getElementById('saveStoredDiscipline').click()")
        c.wait("!document.getElementById('modalBackdrop').classList.contains('show')")
        c.wait("document.getElementById('storedDisciplineRows')?.textContent.includes('Etika UI')")
        c.js("window.render()")
        c.wait("document.getElementById('storedDisciplineRows')?.textContent.includes('Etika UI')")
        assert not c.js("!!document.querySelector('#storedDisciplineRows img')")
        credentials=json.dumps({'role':'student','username':prefix+'_0','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(function(d){return finishLogin(d.user)})")
        c.wait("!!document.querySelector('.student-home .analytics-svg') && !!document.querySelector('.dashboard-absence-card')")
        assert c.js("document.querySelectorAll('.student-home .summary-panel').length")==1
        discipline_text=c.js("document.querySelector('.student-home').textContent")
        assert 'Etika UI <img src=x onerror=alert(1)>' in discipline_text and 'CATATAN SISWA LAIN' not in discipline_text
        assert 'Dalam Peringatan (Terlambat 2x)' in discipline_text and 'Lengkapi atribut' in discipline_text
        assert 'Notifikasi' not in discipline_text and not c.js("!!document.querySelector('.student-discipline-note img')")
        for category in ['etika','atribut','terlambat']:
            wali.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':category,'jumlah_terlambat':0})
        assert not a.call('api/siswa/dashboard')['data']['kedisiplinan']['catatan']
        c.js("window.render()")
        c.wait("!!document.querySelector('.student-discipline-summary') && document.querySelector('.student-discipline-summary').textContent==='Tidak Ada Catatan Pelanggaran'")
        c.wait("!!document.querySelector('.dashboard-absence-card')")
        assert c.js("document.querySelector('.student-home .activity-list').textContent")=='Tidak Ada Catatan Pelanggaran'

        text=c.js("document.querySelector('.student-home').textContent")
        for label in ['Waktu Kehadiran','Jumlah Keterlambatan','Rekap Absensi','Jadwal Ulangan Harian Minggu Ini','Daftar Tugas Yang Harus Diselesaikan','Status Kedisiplinan','Siswa Tidak Hadir Hari Ini']:
            assert label in text,label
        assert 'Test <img src=x onerror=alert(1)>' in c.js("document.querySelector('.dashboard-absence-list').textContent") and not c.js("!!document.querySelector('.dashboard-absence-list img')")
        assert 'Foreign Peer' not in text and 'Inactive Peer' not in text
        assert c.js("document.querySelector('.attendance-chart').studentSummaryData.status.Sakit")==1
        c.js("document.querySelector('[data-summary-tab=\"schedule\"]').click()")
        assert prefix+'_mapel' in c.js("document.querySelector('.summary-view.active').textContent")
        c.js("document.querySelector('[data-summary-tab=\"waiting\"]').click()")
        assert 'Test Belum Scan' in c.js("document.querySelector('.summary-view.active').textContent")
        c.js("window.page='attendance';window.render();window.page='dashboard';window.render()")
        c.wait("!!document.querySelector('.student-home .analytics-svg') && !!document.querySelector('.dashboard-absence-card')")
        assert c.js("document.querySelectorAll('.dashboard-absence-card').length")==1
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js("document.documentElement.scrollWidth<=window.innerWidth"),'Mobile overflow'
        c.call('Emulation.clearDeviceMetricsOverride')
        errors=[e for e in c.events if e.get('method')=='Runtime.exceptionThrown'];assert not errors,errors
        c.js("window.page='attendance';window.render()")
        c.wait("document.getElementById('studentAttendanceStatus')?.textContent==='Hadir'")
        assert c.js("document.getElementById('studentAttendanceScan').textContent")=='06:12:34 WIB'
        assert c.js("document.getElementById('studentQrButton').textContent")=='Buka scanner QR'
        for status,scan_time in [('Izin',None),('Sakit',None),('Alpha',None),('Terlambat','06:45:00'),('Menunggu keterangan',None)]:
            stamp="'"+scan_time+"'" if scan_time else 'NULL'
            sql(f"UPDATE absensi SET status='{status}',waktu_scan={stamp} WHERE siswa_id={pupils[0]} AND tanggal='{today}' AND semester='Ganjil';")
            c.js("document.getElementById('refreshStudentAttendance').click()")
            c.wait("document.getElementById('studentAttendanceStatus').textContent==="+json.dumps(status))
            assert c.js("document.getElementById('studentAttendanceScan').textContent")==((scan_time+' WIB') if scan_time else 'Belum ada scan')
        sql(f"DELETE FROM absensi WHERE siswa_id={pupils[0]} AND tanggal='{today}' AND semester='Ganjil';")
        c.js("document.getElementById('refreshStudentAttendance').click()")
        c.wait("document.getElementById('studentAttendanceStatus').textContent==='Belum tercatat'")
        assert c.js("document.getElementById('studentAttendanceScan').textContent")=='Belum ada scan'
        # A valid QR scan is submitted locally; the server supplies status and time.
        wali=Client();wali.call('api/auth/login',{'role':'walas','username':prefix+'_wali','password':password})
        qr=wali.call('api/absensi/qr',{'kelas_id':int(class_id)})['data']['qr_code']
        payload=json.dumps({'kelas_id':int(class_id),'semester':'Ganjil','qr_code':qr})
        saved=c.js("authRequest('api/absensi',{method:'POST',body:JSON.stringify("+payload+")}).then(function(d){window.render();return d.data})")
        c.wait("document.getElementById('studentAttendanceScan').textContent==="+json.dumps(saved['waktu_scan']+' WIB'))
        assert c.js("document.getElementById('studentAttendanceStatus').textContent")==saved['status']
        sql(f"UPDATE absensi SET status='Alpha',waktu_scan=NULL WHERE siswa_id={pupils[0]} AND tanggal='{today}' AND semester='Ganjil';")
        time.sleep(16)
        assert c.js("document.getElementById('studentAttendanceStatus').textContent")=='Alpha','Status did not refresh automatically'
        assert c.js("document.getElementById('studentAttendanceScan').textContent")=='Belum ada scan'
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js("document.documentElement.scrollWidth<=window.innerWidth"),'Attendance mobile overflow'
        c.call('Emulation.clearDeviceMetricsOverride')
        assert not [e for e in c.events if e.get('method')=='Runtime.exceptionThrown']
        c.js("logout()")
        print('PASS: Attendance cards show current student/day/semester, all recorded statuses, missing scan/data, QR scan results, automatic refresh and mobile layout.')
        print('PASS: Student home chart, schedule/waiting tabs, absence card, existing personal/task cards retained, repeat navigation, escaped names and mobile layout.')
    print('PASS: Discipline API ownership/role isolation, shared saved notes, separate categories, validation and no-notes state.')
    print('PASS: Own-class student overview, semester/active-student filtering, real weekly counts and minimal classmate fields.')
finally:
    if classes:
        ids=','.join(classes)
        sql('DELETE FROM qr_absensi_aktif WHERE kelas_id IN ('+ids+');DELETE FROM absensi WHERE kelas_id IN ('+ids+');DELETE FROM jadwal_kelas WHERE kelas_id IN ('+ids+');DELETE FROM siswa WHERE kelas_id IN ('+ids+');DELETE FROM kelas WHERE id IN ('+ids+');')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
    if teacher:sql('DELETE FROM guru WHERE id='+teacher)
    if subject:sql('DELETE FROM mata_pelajaran WHERE id='+subject)
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
