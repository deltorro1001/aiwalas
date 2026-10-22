"""Local API/browser checks for preserved student home cards. Disposable fixtures only.
Run: AIWALAS_BROWSER_TEST=1 python3 tests/student-home-local.py
"""
import datetime,http.cookiejar,json,os,re,secrets,shutil,subprocess,urllib.request,urllib.error
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
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies')
        c.call('Page.navigate',{'url':BASE});c.wait("typeof authRequest==='function' && !!window.AIWalasPages")
        credentials=json.dumps({'role':'student','username':prefix+'_0','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(function(d){return finishLogin(d.user)})")
        c.wait("!!document.querySelector('.student-home .analytics-svg') && !!document.querySelector('.dashboard-absence-card')")
        assert c.js("document.querySelectorAll('.student-home .summary-panel').length")==1
        text=c.js("document.querySelector('.student-home').textContent")
        for label in ['Waktu Kehadiran','Jumlah Keterlambatan','Rekap Absensi','Jadwal Ulangan Harian Minggu Ini','Daftar Tugas Yang Harus Diselesaikan','Notifikasi Terakhir','Siswa Tidak Hadir Hari Ini']:
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
        c.js("logout()")
        print('PASS: Student home chart, schedule/waiting tabs, absence card, existing personal/task cards retained, repeat navigation, escaped names and mobile layout.')
    print('PASS: Own-class student overview, semester/active-student filtering, real weekly counts and minimal classmate fields.')
finally:
    if classes:
        ids=','.join(classes)
        sql('DELETE FROM absensi WHERE kelas_id IN ('+ids+');DELETE FROM jadwal_kelas WHERE kelas_id IN ('+ids+');DELETE FROM siswa WHERE kelas_id IN ('+ids+');DELETE FROM kelas WHERE id IN ('+ids+');')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
    if teacher:sql('DELETE FROM guru WHERE id='+teacher)
    if subject:sql('DELETE FROM mata_pelajaran WHERE id='+subject)
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
