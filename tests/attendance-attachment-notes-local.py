"""Attendance attachment notes across home and related attendance pages. Disposable fixtures; no gateway calls."""
import datetime,http.cookiejar,json,os,re,secrets,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP='/opt/homebrew/opt/php@8.3/bin/php';MYSQL='/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
tag='note_'+secrets.token_hex(5);password=secrets.token_hex(16)
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
def multipart(client,fields,file=None,status=201,endpoint='api/pengajuan-izin'):
    boundary='aiwalas'+secrets.token_hex(12);chunks=[]
    for key,value in fields.items():chunks.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+key+'"\r\n\r\n'+str(value)+'\r\n').encode())
    if file:
        name,mime,data=file
        chunks.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="lampiran"; filename="'+name+'"\r\nContent-Type: '+mime+'\r\n\r\n').encode()+data+b'\r\n')
    chunks.append(('--'+boundary+'--\r\n').encode())
    req=urllib.request.Request(BASE+endpoint,data=b''.join(chunks),headers={'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-TOKEN':client.token})
    try:r=client.op.open(req)
    except urllib.error.HTTPError as error:r=error
    result=json.load(r);client.token=result.get('csrfHash',client.token);assert r.code==status,(r.code,result);return result
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
    today=datetime.datetime.now(ZoneInfo('Asia/Jakarta')).date();day=str(today);semester='Genap' if today.month<=6 else 'Ganjil'
    pdf=b'%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF'
    fields={'tanggal_mulai':day,'tanggal_selesai':day,'jumlah_hari':1,'jenis_izin':'Keperluan keluarga','keterangan':'Alasan pribadi jangan dipublikasikan'}
    sick_id=multipart(parent,fields,('dokumen-pribadi.pdf','application/pdf',pdf),endpoint='api/pengajuan-sakit')['id']
    parent2=Client().login('parent',tag+'_1',tag+'_1')
    multipart(parent2,dict(fields,tanggal_mulai='2026-10-05',tanggal_selesai='2026-10-05'),('izin-lama.pdf','application/pdf',pdf))
    multipart(parent2,fields)
    pupils.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{tag}_2','Siswa ZLampiran','L',{classes[0]},1);SELECT LAST_INSERT_ID();"))
    parent3=Client().login('parent',tag+'_2',tag+'_2')
    multipart(parent3,fields,('izin-hari-ini.pdf','application/pdf',pdf))
    multipart(parent,dict(fields,tanggal_mulai='2026-11-04',tanggal_selesai='2026-11-04'),('izin-baru.pdf','application/pdf',pdf))
    # Calendar holidays skip auto-writing; fixed attendance rows let the view be checked every day.
    for pupil,status in [(pupils[0],'Sakit'),(pupils[1],'Izin'),(pupils[2],'Izin')]:
        sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,semester,status,dicatat_oleh) VALUES({pupil},{classes[0]},'{day}','{semester}','{status}',{users[0]}) ON DUPLICATE KEY UPDATE status=VALUES(status)")
    endpoint=f'api/absensi?kelas_id={classes[0]}&tanggal={day}&semester={semester}'
    def checks(rows):
        a=next(r for r in rows if str(r['siswa_id'])==pupils[0]);assert a['surat_terlampir'] is True and a['keterangan_lampiran']=='(surat sakit terlampir)',a
        b=next((r for r in rows if str(r['siswa_id'])==pupils[1]),None)
        if b:assert b['surat_terlampir'] is False and b['keterangan_lampiran']=='',b
    for client in [wali,guru,secretary,student,parent]:checks(client.call(endpoint)['data'])
    for client in [wali,guru,secretary]:
        bootstrap=client.call(f'api/bootstrap?kelas_id={classes[0]}&semester={semester}')
        assert bootstrap['dashboard']['attachment_notes'][pupils[0]]=='(surat sakit terlampir)'
        assert bootstrap['dashboard']['attachment_notes'][pupils[1]]==''
        assert bootstrap['dashboard']['attachment_notes'][pupils[2]]=='(surat izin terlampir)'
    for client in [student,parent]:
        home=client.call('api/siswa/dashboard?semester='+semester)['data']
        assert home['scan']['keterangan_lampiran']=='(surat sakit terlampir)'
        absent=home['ringkasan_kelas']['siswa_tidak_hadir']
        assert next(r for r in absent if r['nama_lengkap']=='Siswa Jurnal 0')['keterangan_lampiran']=='(surat sakit terlampir)'
        assert next(r for r in absent if r['nama_lengkap']=='Siswa Jurnal 1')['keterangan_lampiran']==''
        overview=json.dumps(home['ringkasan_kelas']);assert 'dokumen-pribadi' not in overview and 'Alasan pribadi' not in overview and 'lampiran_url' not in overview
        month=client.call(f'api/siswa/rekap-absensi?bulan={today.month}&tahun={today.year}')['data']
        assert next(r for r in month if r['tanggal']==day)['keterangan_lampiran']=='(surat sakit terlampir)'
    november=parent.call('api/siswa/rekap-absensi?bulan=11&tahun=2026')['data'];assert next(r for r in november if r['tanggal']=='2026-11-04')['keterangan_lampiran']=='(surat izin terlampir)'
    # A different status must never inherit a sick request's annotation.
    sql(f"UPDATE absensi SET status='Izin' WHERE siswa_id={pupils[0]} AND tanggal='{day}'")
    assert next(r for r in wali.call(endpoint)['data'] if str(r['siswa_id'])==pupils[0])['keterangan_lampiran']==''
    sql(f"UPDATE absensi SET status='Sakit' WHERE siswa_id={pupils[0]} AND tanggal='{day}'")
    assert guest.call('api/bootstrap')['dashboard']==[]
    print('PASS: Matching child/class/date/status attachments on all authorized APIs, no-file request and old-file exclusion, own/class summaries, monthly histories, and no document names/reasons/URLs exposed to class summaries.')
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
        c.wait("!!window.AIWalasDashboardAttendance && !!window.AIWalasAttendanceDisplay")
        def login(role,username,secret=password):
            credentials=json.dumps({'role':role,'username':username,'password':secret})
            c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(()=>authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")})).then(d=>finishLogin(d.user)).then(()=>authRequest('api/bootstrap?kelas_id="+classes[0]+"&semester="+semester+"')).then(d=>{applyBootstrapData(d);window.activeSemester='"+semester+"';applyRoleAccess();window.page='dashboard';window.render()})")
        for role,username in [('walas',tag+'_wali0'),('teacher',tag+'_guru0'),('assistant',tag+'_sekretaris')]:
            login(role,username);c.wait("document.querySelector('.dashboard-absence-list')?.textContent.includes('Siswa Jurnal 0')")
            rows=c.js("[...document.querySelectorAll('.dashboard-absence-row')].map(r=>r.textContent)")
            assert '(surat sakit terlampir)' in rows[0] and '(surat izin terlampir)' not in rows[1] and '(surat izin terlampir)' in rows[2],rows
            c.js("window.page='attendance';render()")
            c.wait("document.querySelector('tr[data-attendance-student=\""+pupils[0]+"\"]')?.textContent.includes('(surat sakit terlampir)')")
        login('walas',tag+'_wali0');c.wait("document.querySelector('.dashboard-absence-list')?.textContent.includes('Siswa Jurnal 0')")
        sql(f"UPDATE absensi SET status='Hadir' WHERE siswa_id={pupils[0]} AND tanggal='{day}'")
        c.js('AIWalasDashboardAttendance.refresh()');c.wait("!document.querySelector('.dashboard-absence-list').textContent.includes('Siswa Jurnal 0')")
        sql(f"UPDATE absensi SET status='Sakit' WHERE siswa_id={pupils[0]} AND tanggal='{day}'")
        c.js('AIWalasDashboardAttendance.refresh()');c.wait("document.querySelector('.dashboard-absence-list').textContent.includes('(surat sakit terlampir)')")
        for role,username,secret in [('student',tag+'_siswa',password),('parent',tag+'_0',tag+'_0')]:
            login(role,username,secret);c.wait("document.querySelector('.student-current-attendance')?.textContent.includes('(surat sakit terlampir)')")
            assert c.js("!!document.querySelector('.student-whatsapp-card')"), 'Existing Notifikasi card must remain'
            c.js("window.page='attendance';render()")
            c.wait("document.getElementById('studentAttendanceAttachment')?.textContent==='(surat sakit terlampir)'")
            c.wait("document.getElementById('studentAttendanceRecapRows')?.textContent.includes('(surat sakit terlampir)')")
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js('document.documentElement.scrollWidth<=innerWidth');c.call('Emulation.clearDeviceMetricsOverride')
        for name in ['attendance-status-display','dashboard-attendance-live','dashboard-summary','student-home-force','attendance-ui','student-qr-attendance','student-attendance-recap']:
            assert c.js("fetch('assets/"+name+".js').then(r=>r.text()).then(s=>{new Function(s);return true})")
        assert not [e for e in c.events if e.get('method')=='Runtime.exceptionThrown'],c.events
        print('PASS: Wali/teacher/secretary home and daily rows, live updates after correction, student/parent home/status/history, original Notifikasi preserved, mobile width and JS syntax.')
    filename=sql(f'SELECT berkas_lampiran FROM pengajuan_sakit WHERE id={sick_id}')
    (ROOT/'writable'/'pengajuan-sakit'/filename).unlink()
    assert next(r for r in wali.call(endpoint)['data'] if str(r['siswa_id'])==pupils[0])['surat_terlampir'] is False
    print('PASS: Missing file suppresses the attachment annotation.')
finally:
    if c:
        try:c.js("closeModal();authRequest('api/auth/logout',{method:'POST',body:'{}'})")
        except Exception:pass
    if classes:
        ids=','.join(classes)
        for table,folder in [('pengajuan_sakit','pengajuan-sakit'),('pengajuan_izin','pengajuan-izin')]:
            for filename in sql(f'SELECT berkas_lampiran FROM {table} WHERE kelas_id IN ({ids}) AND berkas_lampiran IS NOT NULL').splitlines():
                path=ROOT/'writable'/folder/filename
                if path.is_file():path.unlink()
        sql(f"DELETE FROM absensi WHERE kelas_id IN ({ids});DELETE FROM pengguna WHERE siswa_id IN (SELECT id FROM siswa WHERE kelas_id IN ({ids}));DELETE FROM catatan_kedisiplinan WHERE kelas_id IN ({ids});DELETE FROM pengampu_mapel WHERE kelas_id IN ({ids});DELETE FROM siswa WHERE kelas_id IN ({ids});DELETE FROM kelas WHERE id IN ({ids});")
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teachers:sql('DELETE FROM guru WHERE id IN ('+','.join(teachers)+')')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
