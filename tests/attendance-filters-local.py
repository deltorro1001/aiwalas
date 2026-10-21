"""Local date/status attendance regression; temporary fixtures, no gateway requests.
Run: AIWALAS_BROWSER_TEST=1 python3 tests/attendance-filters-local.py
"""
import datetime,http.cookiejar,json,os,re,secrets,shutil,subprocess,time,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/'
PHP=shutil.which('php') or '/opt/homebrew/opt/php@8.3/bin/php';MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
prefix='attendance_'+secrets.token_hex(5);password=secrets.token_hex(16)
hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
today=str(datetime.datetime.now(ZoneInfo('Asia/Jakarta')).date());past=str(datetime.date.fromisoformat(today)-datetime.timedelta(days=1))
class Client:
    def __init__(self):
        self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        html=self.op.open(BASE).read().decode();self.token=re.search(r"name=['\"]csrf-token['\"] content=['\"]([^'\"]+)",html).group(1)
    def call(self,path,data=None,method=None,status=200):
        req=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),method=method,headers={'Content-Type':'application/json','X-CSRF-TOKEN':self.token})
        try:r=self.op.open(req,timeout=10)
        except urllib.error.HTTPError as e:r=e
        result=json.load(r);assert r.code==status,(path,r.code,result);self.token=result.get('csrfHash',self.token);return result
owner=None;class_id=None;students=[]
try:
    owner=sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}','{hashed}','Attendance test','wali_kelas');SELECT LAST_INSERT_ID();")
    year=sql('SELECT id FROM tahun_ajaran LIMIT 1')
    class_id=sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}',11,'Test',{year},{owner});SELECT LAST_INSERT_ID();")
    names=['Zulu Student','Alpha Student','Beta Student','Gamma Student','Delta Student','Missing Student']
    now_status=['Hadir','Terlambat','Sakit','Izin','Alpha']
    old_status=['Sakit','Hadir','Izin','Alpha','Terlambat']
    for i,name in enumerate(names):
        sid=sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,nomor_hp_orang_tua,aktif) VALUES('{prefix}_{i}','{name}','L',{class_id},'0812345678{i:02}',1);SELECT LAST_INSERT_ID();");students.append(sid)
        if i<5:
            for day,status in [(today,now_status[i]),(past,old_status[i])]:
                sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,semester,waktu_scan,status,dicatat_oleh) VALUES({sid},{class_id},'{day}','Ganjil','07:01:02','{status}',{owner});")
            sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,semester,waktu_scan,status,dicatat_oleh) VALUES({sid},{class_id},'{past}','Genap','08:02:03','Alpha',{owner});")
    sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,semester,waktu_scan,status,dicatat_oleh) VALUES({students[2]},{class_id},'{today}','Genap','08:03:04','Alpha',{owner});")
    api=Client();api.call('api/auth/login',{'role':'walas','username':prefix,'password':password})
    def rows(day,semester='Ganjil'):return api.call('api/absensi?kelas_id='+class_id+'&tanggal='+day+'&semester='+semester)['data']
    assert len(rows(today))==5 and len(rows(past))==5
    assert next(r for r in rows(today) if str(r['siswa_id'])==students[2])['status']=='Sakit'
    assert next(r for r in rows(past) if str(r['siswa_id'])==students[2])['status']=='Izin'
    assert all(r['status']=='Alpha' for r in rows(past,'Genap'))
    for date in ['2026-02-30','not-a-date','2026-1-01']:
        api.call('api/absensi?kelas_id='+class_id+'&tanggal='+date,status=422)
    api.call('api/absensi?kelas_id='+class_id+'&tanggal[]=2026-10-21',status=422)
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Page.navigate',{'url':BASE})
        c.wait("typeof authRequest==='function' && !!window.AIWalasAttendance")
        credentials=json.dumps({'role':'walas','username':prefix,'password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(function(d){return finishLogin(d.user)}).then(function(){return authRequest('api/bootstrap?kelas_id="+class_id+"')}).then(function(d){applyBootstrapData(d);window.page='attendance';window.render()})")
        c.wait("document.querySelectorAll('tr[data-attendance-student]').length===6")
        assert c.js("document.getElementById('attendanceViewDate').value")==today
        assert c.js("Array.from(document.getElementById('attendanceSortFilter').options).map(function(o){return o.value})")==['Nama','Terlambat','Sakit','Izin','Alpha']
        assert c.js("Array.from(document.querySelectorAll('tr[data-attendance-student] strong')).map(function(s){return s.textContent})")==sorted(names)
        assert c.js("document.querySelector('[data-attendance-student=\""+students[0]+"\"]').children[1].textContent")=='07:01:02'
        def choose(status):
            c.js("document.getElementById('attendanceSortFilter').value="+json.dumps(status)+";document.getElementById('attendanceSortFilter').onchange()")
        def view(day):
            c.js("document.getElementById('attendanceViewDate').value="+json.dumps(day)+";document.getElementById('attendanceViewDate').onchange()")
        for status in ['Terlambat','Sakit','Izin','Alpha']:
            choose(status);assert c.js("document.querySelectorAll('tr[data-attendance-student]').length")==1
            assert c.js("document.querySelector('tr[data-attendance-student]').dataset.attendanceStatus")==status
        choose('Sakit')
        c.wait("!!document.querySelector('tr[data-attendance-student] .wa-parent-action')")
        c.js("window.sendWhatsAppGateway=function(data){window.__attendanceSent=data;return Promise.resolve({ok:true})};document.querySelector('tr[data-attendance-student] .wa-parent-action').click()")
        assert c.js("window.__attendanceSent.nomor_tujuan")=='6281234567802'
        choose('Nama');view(past)
        c.wait("!!document.querySelector('[data-attendance-student=\""+students[0]+"\"][data-attendance-status=\"Sakit\"]')")
        assert c.js("document.querySelectorAll('.attendance-correct').length")==0
        assert c.js("document.getElementById('makeLiveQr').disabled") is True
        time.sleep(5.5)
        assert c.js("document.getElementById('attendanceViewDate').value")==past
        assert c.js("document.querySelector('[data-attendance-student=\""+students[0]+"\"]').dataset.attendanceStatus")=='Sakit'
        assert c.js("document.querySelectorAll('.table-panel .wa-parent-action').length")==0
        choose('Terlambat')
        assert c.js("document.querySelector('tr[data-attendance-student]').dataset.attendanceStudent")==students[4]
        c.js("window.activeSemester='Genap';window.render()")
        c.wait("document.getElementById('attendanceViewDate').value==="+json.dumps(today)+" && document.querySelectorAll('tr[data-attendance-student]').length===6")
        view(past);choose('Alpha')
        c.wait("document.querySelectorAll('tr[data-attendance-student]').length===5")
        c.js("window.activeSemester='Ganjil';window.render()")
        c.wait("document.getElementById('attendanceViewDate').value==="+json.dumps(today)+" && document.querySelectorAll('tr[data-attendance-student]').length===6")
        choose('Sakit')
        c.js("document.querySelector('.attendance-correct').click();document.getElementById('correctStatus').value='Izin';document.getElementById('saveModal').click()")
        c.wait("!document.getElementById('modalBackdrop').classList.contains('show') && document.querySelectorAll('tr[data-attendance-student]').length===0")
        assert next(r for r in rows(today) if str(r['siswa_id'])==students[2])['status']=='Izin'
        assert next(r for r in rows(today,'Genap') if str(r['siswa_id'])==students[2])['status']=='Alpha'
        assert next(r for r in rows(past) if str(r['siswa_id'])==students[2])['status']=='Izin'
        assert c.js("document.getElementById('attendanceSortFilter').value")=='Sakit'
        choose('Nama');view('2001-01-01')
        c.wait("document.querySelectorAll('[data-attendance-status=\"Menunggu keterangan\"]').length===6")
        choose('Alpha');assert c.js("document.querySelector('.table-panel tbody').textContent.includes('Tidak ada siswa')")
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js("document.getElementById('attendanceViewDate').getBoundingClientRect().right<=document.documentElement.clientWidth+1")
        c.call('Emulation.clearDeviceMetricsOverride')
        c.js("window.page='dashboard';window.render()")
        errors=[e for e in c.events if e.get('method')=='Runtime.exceptionThrown'];assert not errors,errors
        print('PASS: Browser date selection, alphabetical/status filters, actual scan times, realtime preserves history, semester changes, read-only history, filtered correction, correct parent phone, mobile layout and empty dates. No gateway messages sent.')
    print('PASS: Attendance date/semester isolation and invalid date validation.')
finally:
    if class_id:
        sql('DELETE FROM absensi WHERE kelas_id='+class_id+';DELETE FROM siswa WHERE kelas_id='+class_id+';DELETE FROM kelas WHERE id='+class_id+';')
    if owner:sql('DELETE FROM pengguna WHERE id='+owner)
