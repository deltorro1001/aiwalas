"""Local journal date/access regression. Disposable fixtures; no gateway calls."""
import datetime,http.cookiejar,json,os,re,secrets,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP='/opt/homebrew/opt/php@8.3/bin/php';MYSQL='/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
tag='journal_'+secrets.token_hex(5);password=secrets.token_hex(16)
hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
class Client:
    def __init__(self):
        self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token=re.search(r"name=['\"]csrf-token['\"] content=['\"]([^'\"]+)",self.op.open(BASE).read().decode()).group(1)
    def call(self,path,data=None,status=200,method=None):
        req=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),method=method,headers={'Content-Type':'application/json','X-CSRF-TOKEN':self.token})
        try:r=self.op.open(req,timeout=15)
        except urllib.error.HTTPError as error:r=error
        result=json.load(r);self.token=result.get('csrfHash',self.token);assert r.code==status,(path,r.code,result);return result
    def login(self,role,username,secret=password):self.call('api/auth/login',{'role':role,'username':username,'password':secret});return self
users=[];classes=[];teachers=[];pupils=[];year=None;c=None
try:
    for i in range(2):
        users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{tag}_wali{i}','{hashed}','Wali Jurnal {i}','wali_kelas');SELECT LAST_INSERT_ID();"))
        teachers.append(sql(f"INSERT INTO guru(nama_lengkap,nama_panggilan,aktif) VALUES('{tag} Guru {i}','Uji',1);SELECT LAST_INSERT_ID();"))
    for i in range(2):users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,guru_id) VALUES('{tag}_guru{i}','{hashed}','Guru Jurnal {i}','guru',{teachers[i]});SELECT LAST_INSERT_ID();"))
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{tag}','Ganjil',1);SELECT LAST_INSERT_ID();")
    for i in range(2):classes.append(sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{tag}_{i}',11,'Test',{year},{users[i]});SELECT LAST_INSERT_ID();"))
    subjects=sql('SELECT id FROM mata_pelajaran WHERE aktif=1 ORDER BY id LIMIT 2').splitlines()
    for i in range(2):sql(f"INSERT INTO pengampu_mapel(guru_id,kelas_id,mata_pelajaran_id) VALUES({teachers[i]},{classes[0]},{subjects[i]})")
    for i in range(2):pupils.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{tag}_{i}','Siswa Jurnal {i}','L',{classes[0]},1);SELECT LAST_INSERT_ID();"))
    for role,nis in [('siswa',tag+'_0'),('sekretaris',tag+'_1'),('guest',None)]:
        users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{tag}_{role}','{hashed}','Fixture {role}','{role}',{'NULL' if nis is None else chr(39)+nis+chr(39)});SELECT LAST_INSERT_ID();"))
    wali=Client().login('walas',tag+'_wali0');outsider=Client().login('walas',tag+'_wali1')
    guru=Client().login('teacher',tag+'_guru0');other=Client().login('teacher',tag+'_guru1');secretary=Client().login('assistant',tag+'_sekretaris')
    student=Client().login('student',tag+'_siswa');parent=Client().login('parent',tag+'_0',tag+'_0');guest=Client().login('guest',tag+'_guest')
    today=datetime.datetime.now(ZoneInfo('Asia/Jakarta')).date().isoformat()
    payload={'kelas_id':int(classes[0]),'mata_pelajaran_id':int(subjects[0]),'guru_id':int(teachers[0]),'tanggal':'2026-01-01','materi':'Materi Januari <img src=x onerror=alert(1)>','catatan':'Tugas Januari','status':'Terbit'}
    first=guru.call('api/jurnal',payload)['id']
    second=other.call('api/jurnal',dict(payload,mata_pelajaran_id=int(subjects[1]),guru_id=int(teachers[1]),materi='Jurnal guru lain'))['id']
    wali_note=wali.call('api/jurnal',dict(payload,tanggal='2026-01-02',materi='Materi tanggal kedua'))['id']
    today_note=secretary.call('api/jurnal',dict(payload,tanggal=today,materi='Materi hari ini'))['id']
    endpoint='api/jurnal?kelas_id='+classes[0]
    Client().call(endpoint,status=401)
    guest.call(endpoint,status=403)
    for client in [wali,secretary,student,parent]:
        january=client.call(endpoint+'&tanggal=2026-01-01');assert len(january['data'])==2 and january['minimum_date']=='2026-01-01'
        assert {row['tanggal'] for row in january['data']}=={'2026-01-01'}
        assert len(client.call(endpoint+'&tanggal=2026-01-02')['data'])==1
        assert client.call(endpoint+'&tanggal=2026-01-31')['data']==[]
    mine=guru.call(endpoint+'&tanggal=2026-01-01');assert len(mine['data'])==1 and mine['data'][0]['can_edit'] is True
    assert guru.call(endpoint+'&tanggal=2026-01-02')['data'][0]['can_edit'] is False
    guru.call('api/jurnal/'+str(wali_note),dict(payload,tanggal='2026-01-02'),method='PUT',status=403)
    for client in [student,parent]:
        result=client.call(endpoint+'&tanggal=2026-01-01');assert result['can_create'] is False and result['assignments']==[] and all(not r['can_edit'] for r in result['data'])
        for method,path in [('POST','api/jurnal'),('PUT','api/jurnal/'+str(first)),('DELETE','api/jurnal/'+str(first))]:client.call(path,payload,method=method,status=403)
    for client in [student,parent,secretary,guru]:client.call('api/jurnal?kelas_id='+classes[1],status=403)
    outsider.call(endpoint,status=403);outsider.call('api/jurnal/'+str(first),payload,method='PUT',status=403);outsider.call('api/jurnal/'+str(first),method='DELETE',status=403)
    for invalid in ['2025-12-31','2026-02-30','invalid','']:
        wali.call(endpoint+'&tanggal='+invalid,status=422)
    wali.call(endpoint+'&tanggal[]=2026-01-01',status=422)
    wali.call('api/jurnal',dict(payload,tanggal='2025-12-31'),status=422)
    wali.call('api/jurnal/'+str(first),dict(payload,materi='Koreksi wali Januari'),method='PUT')
    secretary.call('api/jurnal/'+str(first),dict(payload,materi='Koreksi wali Januari',catatan='Tugas dikoreksi sekretaris'),method='PUT')
    assert student.call(endpoint+'&tanggal=2026-01-01')['data'][1]['catatan']=='Tugas dikoreksi sekretaris'
    assert sql(f'SELECT dibuat_oleh FROM jurnal_kelas WHERE id={first}')==users[2]
    assert wali.call(endpoint+'&tanggal='+today)['data'][0]['materi']=='Materi hari ini'
    sql(f'DELETE FROM pengampu_mapel WHERE guru_id={teachers[1]} AND kelas_id={classes[0]}')
    archived=dict(payload,mata_pelajaran_id=int(subjects[1]),guru_id=int(teachers[1]),materi='Koreksi penugasan lama')
    wali.call('api/jurnal/'+str(second),archived,method='PUT')
    print('PASS: Exact date filtering from Jan 1 2026, historic editing with original author/date isolation, archived assignments, writer permissions, read-only student/parent and denied guest, class isolation and date validation.')
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
        c.wait("!!window.AIWalasPages?.journal && typeof authRequest==='function'")
        def login(role,username,secret=password):
            credentials=json.dumps({'role':role,'username':username,'password':secret})
            c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(()=>authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")})).then(d=>finishLogin(d.user)).then(()=>authRequest('api/bootstrap?kelas_id="+classes[0]+"')).then(d=>{applyBootstrapData(d);applyRoleAccess();window.page='journal';window.render()})")
            c.wait("document.getElementById('journalDateCaption')?.textContent.includes('jurnal')")
        def select(date):
            c.js("document.getElementById('journalViewDate').value="+json.dumps(date)+";document.getElementById('journalViewDate').dispatchEvent(new Event('change'))")
            c.wait("document.getElementById('journalDateCaption')?.textContent.includes('jurnal')")
        for role,username in [('walas',tag+'_wali0'),('teacher',tag+'_guru0'),('assistant',tag+'_sekretaris')]:
            login(role,username);assert c.js("document.getElementById('journalViewDate').min")=='2026-01-01'
            assert c.js("document.getElementById('journalViewDate').value")==today
            select('2026-01-01');c.js("document.querySelector('[data-journal-edit=\""+str(first)+"\"]').click()")
            assert c.js("document.getElementById('jDate').value")=='2026-01-01'
            c.js("document.getElementById('jMateri').value="+json.dumps('Koreksi UI '+role)+";document.getElementById('journalForm').requestSubmit()")
            c.wait("!document.getElementById('modalBackdrop').classList.contains('show')")
            c.wait("document.getElementById('journalRows')?.textContent.includes("+json.dumps('Koreksi UI '+role)+")")
            c.js('window.render()');c.wait("document.getElementById('journalRows')?.textContent.includes("+json.dumps('Koreksi UI '+role)+")")
            assert c.js("document.getElementById('journalViewDate').value")=='2026-01-01'
        login('walas',tag+'_wali0');select('2026-01-01');c.js("document.querySelector('[data-journal-edit=\""+str(second)+"\"]').click()")
        assert c.js("document.getElementById('jAssignment').selectedOptions[0].textContent.includes('Guru 1')")
        c.js("document.getElementById('jTugas').value='Tugas penugasan lama';document.getElementById('journalForm').requestSubmit()")
        c.wait("!document.getElementById('modalBackdrop').classList.contains('show')")
        select('2026-01-31');assert c.js("document.getElementById('journalRows').textContent.includes('Belum ada jurnal')")
        c.js("document.getElementById('addJournal').click()");assert c.js("document.getElementById('jDate').value")=='2026-01-31'
        c.js("closeModal();document.getElementById('journalToday').click()");c.wait("document.getElementById('journalViewDate')?.value==="+json.dumps(today))
        for role,username,secret in [('student',tag+'_siswa',password),('parent',tag+'_0',tag+'_0')]:
            login(role,username,secret);select('2026-01-01')
            assert c.js("getComputedStyle(document.querySelector('.sidebar [data-page=journal]')).display")!='none'
            assert c.js("document.querySelectorAll('[data-journal-edit],#addJournal').length")==0
            assert c.js("document.querySelectorAll('#journalRows tr').length")==2
            assert not c.js("!!document.querySelector('#journalRows img')")
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js('document.documentElement.scrollWidth<=innerWidth')
        c.call('Emulation.clearDeviceMetricsOverride')
        print('PASS: Jan date picker, edit/reload for all three writer roles, retained filter, archived selector, selected-date create defaults, empty date, today button, reader sidebar/buttons and mobile layout.')
finally:
    if c:
        try:c.js("closeModal();authRequest('api/auth/logout',{method:'POST',body:'{}'})")
        except Exception:pass
    if classes:
        ids=','.join(classes);sql(f"DELETE FROM pengguna WHERE siswa_id IN (SELECT id FROM siswa WHERE kelas_id IN ({ids}));DELETE FROM jurnal_kelas WHERE kelas_id IN ({ids});DELETE FROM pengampu_mapel WHERE kelas_id IN ({ids});DELETE FROM siswa WHERE kelas_id IN ({ids});DELETE FROM kelas WHERE id IN ({ids});")
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teachers:sql('DELETE FROM guru WHERE id IN ('+','.join(teachers)+')')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
