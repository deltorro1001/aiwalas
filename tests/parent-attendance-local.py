"""Parent request attendance automation and shared status color regression. Disposable fixtures; no gateway calls."""
import datetime,http.cookiejar,json,os,re,secrets,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP='/opt/homebrew/opt/php@8.3/bin/php';MYSQL='/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
tag='auto_'+secrets.token_hex(5);password=secrets.token_hex(16)
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
    def request(status,start,end,days):
        fields={'tanggal_mulai':start,'tanggal_selesai':end,'jumlah_hari':days,'jenis_izin':'Keperluan keluarga','keterangan':'Pengajuan otomatis '+status}
        return multipart(parent,fields,endpoint='api/pengajuan-'+('sakit' if status=='Sakit' else 'izin'))
    def attendance(day,status,semester='Ganjil',time='06:30:00'):
        sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,semester,waktu_scan,dicatat_oleh) VALUES({pupils[0]},{classes[0]},'{day}','{status}','{semester}','{time}',{users[0]})")
    # A historic request is deliberately never applied while reading or submitting new ones.
    parent_id=parent.call('api/auth/session')['user']['id']
    sql(f"INSERT INTO pengajuan_sakit(siswa_id,kelas_id,pengguna_id,tanggal_mulai,tanggal_selesai,jumlah_hari,keterangan) VALUES({pupils[0]},{classes[0]},{parent_id},'2026-10-12','2026-10-12',1,'Pengajuan lama')")
    attendance('2026-10-12','Hadir')
    for day,status in [('2026-10-19','Hadir'),('2026-10-20','Terlambat'),('2026-10-21','Alpha'),('2026-10-22','PJJ'),('2026-10-23','Menunggu keterangan')]:attendance(day,status)
    result=request('Sakit','2026-10-19','2026-10-25',7)
    assert result['absensi']=={'diisi':4,'dilewati':3},result
    for day in ['2026-10-19','2026-10-20','2026-10-21','2026-10-23']:
        rows=wali.call(f'api/absensi?kelas_id={classes[0]}&tanggal={day}&semester=Ganjil')['data']
        assert len(rows)==1 and rows[0]['status']=='Sakit' and rows[0]['waktu_scan'] is None,rows
        for client in [guru,secretary,student,parent]:
            own=client.call(f'api/absensi?kelas_id={classes[0]}&tanggal={day}&semester=Ganjil')['data'];assert own[0]['status']=='Sakit'
    assert sql(f"SELECT status FROM absensi WHERE siswa_id={pupils[0]} AND tanggal='2026-10-22'")=='PJJ'
    assert sql(f"SELECT COUNT(*) FROM absensi WHERE siswa_id={pupils[0]} AND tanggal IN ('2026-10-24','2026-10-25')")=='0'
    conflict={'tanggal_mulai':'2026-10-20','tanggal_selesai':'2026-10-20','jumlah_hari':1,'jenis_izin':'Lainnya','keterangan':'Bertumpuk'}
    multipart(parent,conflict,status=409)
    assert sql(f"SELECT COUNT(*) FROM pengajuan_izin WHERE siswa_id={pupils[0]}")=='0'
    # A later Wali correction is authoritative: history reads never replay submissions.
    wali.call('api/absensi',{'siswa_id':int(pupils[0]),'kelas_id':int(classes[0]),'tanggal':'2026-10-20','status':'Izin','semester':'Ganjil'})
    parent.call('api/pengajuan-sakit');wali.call('api/pengajuan-sakit?kelas_id='+classes[0])
    assert sql(f"SELECT status FROM absensi WHERE siswa_id={pupils[0]} AND tanggal='2026-10-20'")=='Izin'
    attendance('2026-11-03','Sakit')
    result=request('Izin','2026-11-02','2026-11-04',3);assert result['absensi']['diisi']==3
    assert sql(f"SELECT COUNT(*) FROM absensi WHERE siswa_id={pupils[0]} AND tanggal BETWEEN '2026-11-02' AND '2026-11-04' AND status='Izin' AND waktu_scan IS NULL")=='3'
    assert request('Izin','2026-06-30','2026-07-01',2)['absensi']['diisi']==2
    assert sql(f"SELECT semester FROM absensi WHERE siswa_id={pupils[0]} AND tanggal='2026-06-30'")=='Genap'
    assert sql(f"SELECT semester FROM absensi WHERE siswa_id={pupils[0]} AND tanggal='2026-07-01'")=='Ganjil'
    assert request('Sakit','2026-08-17','2026-08-28',12)['absensi']=={'diisi':5,'dilewati':7}
    assert sql(f"SELECT status FROM absensi WHERE siswa_id={pupils[0]} AND tanggal='2026-10-12'")=='Hadir'
    assert sql(f"SELECT COUNT(*) FROM absensi WHERE siswa_id={pupils[1]}")=='0'
    for client in [wali,guru,secretary]:
        recap=client.call(f'api/absensi/rekap-kelas?kelas_id={classes[0]}&tahun=2026&semester=Ganjil')['bulanan']
        october=next(m for m in recap if m['bulan']==10);assert october['sakit']==3 and october['izin']==1 and october['terlambat']==0,october
    for client in [student,parent]:
        october=client.call('api/siswa/rekap-absensi?bulan=10&tahun=2026')['data'];assert len([r for r in october if r['status']=='Sakit'])==3
    print('PASS: New-only Sakit/Izin automation, inclusive ranges, school calendar/PJJ preservation, semester boundary, overwritten scan cleared, all-role reads/recaps, overlap rejection, old requests untouched and later Wali corrections retained.')
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
        c.wait("!!window.AIWalasAttendanceColors && typeof authRequest==='function'")
        def login(role,username,secret=password):
            credentials=json.dumps({'role':role,'username':username,'password':secret})
            c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(()=>authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")})).then(d=>finishLogin(d.user)).then(()=>authRequest('api/bootstrap?kelas_id="+classes[0]+"')).then(d=>{applyBootstrapData(d);applyRoleAccess();window.page='attendance';window.render()})")
        colors={'Sakit':'rgb(21, 128, 61)','Izin':'rgb(107, 63, 29)'}
        for role,username,secret in [('walas',tag+'_wali0',password),('teacher',tag+'_guru0',password),('assistant',tag+'_sekretaris',password)]:
            login(role,username,secret);c.wait("!!document.getElementById('attendanceViewDate')")
            for day,status in [('2026-10-19','Sakit'),('2026-11-02','Izin')]:
                c.js("document.getElementById('attendanceViewDate').value='"+day+"';document.getElementById('attendanceViewDate').dispatchEvent(new Event('change'))")
                c.wait("!!document.querySelector('tr[data-attendance-status=\""+status+"\"]')")
                assert c.js("getComputedStyle(document.querySelector('tr[data-attendance-status=\""+status+"\"] .status')).color")==colors[status]
            c.js("window.page='attendanceRecapPage';render()")
            c.wait("!document.getElementById('exportClassRecap').disabled")
            c.js('AIWalasAttendanceColors.refresh()')
            for status in colors:assert c.js("getComputedStyle([...document.querySelectorAll('#pageContent th')].find(n=>n.textContent==='"+status+"')).color")==colors[status]
        for role,username,secret in [('student',tag+'_siswa',password),('parent',tag+'_0',tag+'_0')]:
            login(role,username,secret);c.wait("!!document.getElementById('studentAttendanceMonth')")
            c.js("document.getElementById('studentAttendanceYear').value='2026';document.getElementById('studentAttendanceMonth').value='10';document.getElementById('studentAttendanceMonth').dispatchEvent(new Event('change'))")
            c.wait("document.getElementById('studentAttendanceRecapRows')?.textContent.includes('Sakit')")
            c.js('AIWalasAttendanceColors.refresh()')
            for status in colors:assert c.js("getComputedStyle([...document.querySelectorAll('#studentAttendanceRecapRows td')].find(n=>n.textContent==='"+status+"')).color")==colors[status]
            for status in colors:
                c.js("document.getElementById('studentAttendanceStatus').dataset.status='"+status+"';document.getElementById('studentAttendanceStatus').textContent='"+status+"';AIWalasAttendanceColors.refresh()")
                assert c.js("getComputedStyle(document.getElementById('studentAttendanceStatus')).color")==colors[status]
        # Verify the shared painter also handles text changing in place and does not recolor PJJ.
        c.js("const p=document.createElement('p');p.id='colorProbe';p.textContent='Sakit';document.getElementById('pageContent').appendChild(p);AIWalasAttendanceColors.refresh()")
        assert c.js("getComputedStyle(document.getElementById('colorProbe')).color")==colors['Sakit']
        c.js("document.getElementById('colorProbe').textContent='Izin';AIWalasAttendanceColors.refresh()")
        assert c.js("getComputedStyle(document.getElementById('colorProbe')).color")==colors['Izin']
        c.js("document.getElementById('colorProbe').textContent='PJJ';AIWalasAttendanceColors.refresh()")
        assert not c.js("document.getElementById('colorProbe').classList.contains('attendance-permission-text')")
        assert c.js("fetch('assets/app.js').then(r=>r.text()).then(s=>{new Function(s);return true})")
        assert not [e for e in c.events if e.get('method')=='Runtime.exceptionThrown'],c.events
        print('PASS: Actual daily rows, recap headers, student/parent histories and status cards show green Sakit/dark-brown Izin across roles; dynamic color updates and PJJ separation.')
finally:
    if c:
        try:c.js("closeModal();authRequest('api/auth/logout',{method:'POST',body:'{}'})")
        except Exception:pass
    if classes:
        ids=','.join(classes)
        for filename in sql(f'SELECT berkas_lampiran FROM pengajuan_izin WHERE kelas_id IN ({ids}) AND berkas_lampiran IS NOT NULL').splitlines():
            path=ROOT/'writable'/'pengajuan-izin'/filename
            if path.is_file():path.unlink()
        sql(f"DELETE FROM absensi WHERE kelas_id IN ({ids});DELETE FROM pengguna WHERE siswa_id IN (SELECT id FROM siswa WHERE kelas_id IN ({ids}));DELETE FROM catatan_kedisiplinan WHERE kelas_id IN ({ids});DELETE FROM pengampu_mapel WHERE kelas_id IN ({ids});DELETE FROM siswa WHERE kelas_id IN ({ids});DELETE FROM kelas WHERE id IN ({ids});")
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teachers:sql('DELETE FROM guru WHERE id IN ('+','.join(teachers)+')')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
