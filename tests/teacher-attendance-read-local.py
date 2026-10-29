"""Local teacher attendance/discipline read-only regression. Disposable fixtures; no gateway calls."""
import datetime,http.cookiejar,json,os,re,secrets,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP='/opt/homebrew/opt/php@8.3/bin/php';MYSQL='/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
tag='tr_'+secrets.token_hex(5);password=secrets.token_hex(16)
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
    date='2026-10-29'
    sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,semester,waktu_scan,dicatat_oleh) VALUES({pupils[0]},{classes[0]},'{date}','Terlambat','Ganjil','06:40:00',{users[0]})")
    wali.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':'etika','pelanggaran':'Catatan uji','tanggal_kejadian':[date],'status_kedisiplinan':'Peringatan 1'})
    endpoints=['api/absensi?kelas_id='+classes[0]+'&tanggal='+date+'&semester=Ganjil', 'api/absensi/rekap-kelas?kelas_id='+classes[0]+'&tahun=2026&semester=Ganjil', 'api/kedisiplinan?kelas_id='+classes[0]]
    for endpoint in endpoints:
        expected=wali.call(endpoint);actual=guru.call(endpoint)
        expected.pop('csrfHash');actual.pop('csrfHash');assert actual==expected,(endpoint,actual,expected)
        guru.call(endpoint.replace('kelas_id='+classes[0],'kelas_id='+classes[1]),status=404)
        Client().call(endpoint,status=401)
    for path,payload in [('api/absensi',{'siswa_id':int(pupils[0]),'kelas_id':int(classes[0]),'tanggal':date,'status':'Hadir'}),('api/absensi/qr',{'kelas_id':int(classes[0])}),('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':'etika','pelanggaran':'Tidak boleh'})]:
        guru.call(path,payload,status=403)
    assert sql(f"SELECT status FROM absensi WHERE siswa_id={pupils[0]}")=='Terlambat'
    print('PASS: Teacher reads match Wali across all three APIs; unassigned classes rejected; writes and QR creation forbidden.')
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
        c.wait("!!window.AIWalasPages?.discipline && typeof authRequest==='function'")
        credentials=json.dumps({'role':'teacher','username':tag+'_guru0','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(d=>finishLogin(d.user)).then(()=>authRequest('api/bootstrap?kelas_id="+classes[0]+"')).then(d=>{applyBootstrapData(d);applyRoleAccess();window.page='attendance';window.render()})")
        c.wait("document.querySelectorAll('[data-attendance-student]').length===2")
        for page in ['attendance','attendanceRecapPage','discipline']:
            assert c.js("getComputedStyle(document.querySelector('.sidebar [data-page="+page+"]')).display")!='none'
        assert c.js("[...document.querySelectorAll('[data-attendance-student] td:last-child button')].every(b=>b.disabled)")
        assert c.js("document.getElementById('scanQr').disabled")
        c.js("document.getElementById('attendanceViewDate').value='2026-10-29';document.getElementById('attendanceViewDate').dispatchEvent(new Event('change'))")
        c.wait("document.querySelector('[data-attendance-status=Terlambat]')!==null")
        c.js("window.page='attendanceRecapPage';render()")
        c.wait("!document.getElementById('exportClassRecap').disabled")
        assert c.js("document.querySelectorAll('#classRecapMonthlyRows tr').length")==13
        c.js("document.querySelector('[data-recap-mode=individu]').click()")
        assert c.js("!document.getElementById('classRecapStudentLabel').hidden")
        c.js("window.page='discipline';render()")
        c.wait("document.querySelectorAll('#storedDisciplineRows [data-discipline-student]').length>0 || document.querySelector('#storedDisciplineRows button')!==null")
        for category in ['etika','terlambat','atribut']:
            c.js("document.getElementById('storedDisciplineCategory').value='"+category+"';document.getElementById('storedDisciplineCategory').dispatchEvent(new Event('change'))")
            assert c.js("document.querySelectorAll('#storedDisciplineRows button').length")>0
            assert c.js("[...document.querySelectorAll('#storedDisciplineRows button')].every(b=>b.disabled)")
            c.js("document.querySelector('#storedDisciplineRows button').click()")
            assert not c.js("document.getElementById('modalBackdrop').classList.contains('show')")
        # Compile the changed JS using the browser parser, including placeholder scripts.
        for name in ['app','teacher-role','attendance-ui','attendance-live','discipline-page','class-attendance-recap']:
            assert c.js("fetch('assets/"+name+".js').then(r=>r.text()).then(s=>{new Function(s);return true})")
        print('PASS: Teacher sidebar, daily/history rows, disabled correction/scan, class and individual recap, all three discipline categories with disabled Edit, JS syntax.')
finally:
    if c:
        try:c.js("closeModal();authRequest('api/auth/logout',{method:'POST',body:'{}'})")
        except Exception:pass
    if classes:
        ids=','.join(classes);sql(f"DELETE FROM pengguna WHERE siswa_id IN (SELECT id FROM siswa WHERE kelas_id IN ({ids}));DELETE FROM absensi WHERE kelas_id IN ({ids});DELETE FROM catatan_kedisiplinan WHERE kelas_id IN ({ids});DELETE FROM pengampu_mapel WHERE kelas_id IN ({ids});DELETE FROM siswa WHERE kelas_id IN ({ids});DELETE FROM kelas WHERE id IN ({ids});")
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teachers:sql('DELETE FROM guru WHERE id IN ('+','.join(teachers)+')')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
