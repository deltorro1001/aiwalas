"""Local parent leave request API and browser regression. Disposable fixtures; no gateway calls."""
import datetime,http.cookiejar,json,os,re,secrets,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP='/opt/homebrew/opt/php@8.3/bin/php';MYSQL='/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
tag='izin_'+secrets.token_hex(5);password=secrets.token_hex(16)
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
def multipart(client,fields,file=None,status=201):
    boundary='aiwalas'+secrets.token_hex(12);chunks=[]
    for key,value in fields.items():chunks.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+key+'"\r\n\r\n'+str(value)+'\r\n').encode())
    if file:
        name,mime,data=file
        chunks.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="lampiran"; filename="'+name+'"\r\nContent-Type: '+mime+'\r\n\r\n').encode()+data+b'\r\n')
    chunks.append(('--'+boundary+'--\r\n').encode())
    req=urllib.request.Request(BASE+'api/pengajuan-izin',data=b''.join(chunks),headers={'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-TOKEN':client.token})
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
    fields={'tanggal_mulai':'2027-01-02','tanggal_selesai':'2027-01-03','jumlah_hari':2,'jenis_izin':'Keperluan keluarga','keterangan':'Acara keluarga <img src=x onerror=alert(1)>'}
    baseline=sql(f'SELECT COUNT(*) FROM absensi WHERE kelas_id={classes[0]}')
    for client in [student,guru,secretary,guest]:
        client.call('api/pengajuan-izin',status=403);multipart(client,fields,status=403)
    multipart(wali,fields,status=403);Client().call('api/pengajuan-izin',status=401)
    for changes in [{'keterangan':''},{'keterangan':' '*5},{'keterangan':'x'*1001},{'jenis_izin':'Invalid'},{'tanggal_mulai':'2026-02-30'},{'tanggal_selesai':'2027-01-01'},{'jumlah_hari':3},{'tanggal_selesai':'2028-01-01','jumlah_hari':365}]:multipart(parent,dict(fields,**changes),status=422)
    multipart(parent,fields,('fake.jpg','image/jpeg',b'<?php invalid'),status=422)
    multipart(parent,fields,('big.pdf','application/pdf',b'%PDF-'+b'0'*(2*1024*1024)),status=422)
    pdf=b'%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF'
    rid=multipart(parent,dict(fields,siswa_id=pupils[1],kelas_id=classes[1]),('surat-izin.pdf','application/pdf',pdf))['id']
    multipart(parent,fields,status=409)
    rows=parent.call('api/pengajuan-izin?siswa_id='+pupils[1])['data'];assert len(rows)==1 and rows[0]['nis']==tag+'_0'
    assert len(wali.call('api/pengajuan-izin?kelas_id='+classes[0])['data'])==1
    assert outsider.call('api/pengajuan-izin?kelas_id='+classes[0])['data']==[]
    second_parent=Client().login('parent',tag+'_1',tag+'_1');assert second_parent.call('api/pengajuan-izin')['data']==[]
    url='api/pengajuan-izin/'+str(rid)+'/lampiran'
    for client in [parent,wali]:assert client.op.open(BASE+url).read()==pdf
    for client,status in [(student,403),(guru,403),(outsider,404),(second_parent,404)]:client.call(url,status=status)
    for i,(extension,mime,encoder) in enumerate([('jpg','image/jpeg','imagejpeg'),('jpeg','image/jpeg','imagejpeg'),('png','image/png','imagepng')]):
        image=subprocess.check_output([PHP,'-r',f'$i=imagecreatetruecolor(4,4);{encoder}($i);'])
        day=f'2027-02-0{i+1}'
        iid=multipart(parent,dict(fields,tanggal_mulai=day,tanggal_selesai=day,jumlah_hari=1),('surat.'+extension,mime,image))['id']
        assert parent.op.open(BASE+'api/pengajuan-izin/'+str(iid)+'/lampiran').read()==image
    nofile=multipart(parent,dict(fields,tanggal_mulai='2028-02-29',tanggal_selesai='2028-02-29',jumlah_hari=1))['id']
    assert next(r for r in parent.call('api/pengajuan-izin')['data'] if int(r['id'])==nofile)['lampiran_url'] is None
    assert sql(f'SELECT COUNT(*) FROM absensi WHERE kelas_id={classes[0]}')==baseline
    print('PASS: Parent-only submission, required purpose/reason, dates/days, PDF/JPG/JPEG/PNG uploads, MIME/size checks, overlap, child/class ownership, private attachments and unchanged attendance.')
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        import tempfile
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
        c.wait("typeof authRequest==='function' && !!window.AIWalasPages?.studentAttendance")
        def login(role,username,secret=password):
            credentials=json.dumps({'role':role,'username':username,'password':secret})
            c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(()=>authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")})).then(d=>finishLogin(d.user)).then(()=>authRequest('api/bootstrap?kelas_id="+classes[0]+"')).then(d=>{applyBootstrapData(d);applyRoleAccess();window.page='attendance';window.render()})")
        login('parent',tag+'_0',tag+'_0');c.wait("document.querySelector('#leaveRequestCard .sick-request-history')?.textContent.includes('Acara keluarga')")
        assert c.js("document.querySelectorAll('#leaveRequestCard').length")==1
        assert not c.js("!!document.querySelector('#leaveRequestCard .sick-request-history img')")
        assert c.js("!!document.getElementById('sickRequestForm') && document.getElementById('leaveRequestNote').required && document.getElementById('leaveRequestType').required")
        assert c.js("['.jpg','.jpeg','.png','.pdf'].every(x=>document.getElementById('leaveRequestFile').accept.includes(x))")
        c.js("document.getElementById('leaveRequestStart').value='2027-01-31';document.getElementById('leaveRequestDays').value='3';document.getElementById('leaveRequestDays').dispatchEvent(new Event('input'))")
        assert c.js("document.getElementById('leaveRequestEnd').value")=='2027-02-02'
        c.js("document.getElementById('leaveRequestEnd').value='2027-02-03';document.getElementById('leaveRequestEnd').dispatchEvent(new Event('change'))")
        assert c.js("document.getElementById('leaveRequestDays').value")=='4'
        c.js("document.getElementById('leaveRequestStart').value='2027-04-01';document.getElementById('leaveRequestDays').value='2';document.getElementById('leaveRequestDays').dispatchEvent(new Event('input'));document.getElementById('leaveRequestType').value='Keperluan administrasi';document.getElementById('leaveRequestNote').value='Pengajuan izin dari formulir browser'")
        with tempfile.NamedTemporaryFile(suffix='.pdf') as file:
            file.write(pdf);file.flush()
            doc=c.call('DOM.getDocument');node=c.call('DOM.querySelector',{'nodeId':doc['root']['nodeId'],'selector':'#leaveRequestFile'})
            c.call('DOM.setFileInputFiles',{'nodeId':node['nodeId'],'files':[file.name]})
            c.js("document.getElementById('leaveRequestForm').requestSubmit()")
            c.wait("document.getElementById('leaveRequestMessage')?.textContent.includes('berhasil dikirim')")
        c.wait("document.querySelector('#leaveRequestCard .sick-request-history').textContent.includes('Pengajuan izin dari formulir browser')")
        c.js('render()');c.wait("document.querySelector('#leaveRequestCard .sick-request-history')?.textContent.includes('Pengajuan izin dari formulir browser')")
        for width in [1440,390]:
            c.call('Emulation.setDeviceMetricsOverride',{'width':width,'height':900,'deviceScaleFactor':1,'mobile':width<500})
            assert c.js('document.documentElement.scrollWidth<=innerWidth')
        c.call('Emulation.clearDeviceMetricsOverride')
        login('student',tag+'_siswa');c.wait("!!document.querySelector('.student-attendance-page')")
        assert not c.js("!!document.getElementById('leaveRequestCard')")
        login('walas',tag+'_wali0');c.wait("document.querySelector('#leaveRequestCard .sick-request-history')?.textContent.includes('Pengajuan izin dari formulir browser')")
        assert not c.js("!!document.getElementById('leaveRequestForm')")
        login('teacher',tag+'_guru0');c.wait("!!document.getElementById('attendanceViewDate')")
        assert not c.js("!!document.getElementById('leaveRequestCard')")
        assert not [e for e in c.events if e.get('method')=='Runtime.exceptionThrown'],c.events
        print('PASS: Parent date/day sync, real multipart form/file submission, saved history, both request cards coexist, mobile fit, student/teacher hidden and wali review.')
finally:
    if c:
        try:c.js("closeModal();authRequest('api/auth/logout',{method:'POST',body:'{}'})")
        except Exception:pass
    if classes:
        ids=','.join(classes)
        for filename in sql(f'SELECT berkas_lampiran FROM pengajuan_izin WHERE kelas_id IN ({ids}) AND berkas_lampiran IS NOT NULL').splitlines():
            path=ROOT/'writable'/'pengajuan-izin'/filename
            if path.is_file():path.unlink()
        sql(f"DELETE FROM pengguna WHERE siswa_id IN (SELECT id FROM siswa WHERE kelas_id IN ({ids}));DELETE FROM absensi WHERE kelas_id IN ({ids});DELETE FROM catatan_kedisiplinan WHERE kelas_id IN ({ids});DELETE FROM pengampu_mapel WHERE kelas_id IN ({ids});DELETE FROM siswa WHERE kelas_id IN ({ids});DELETE FROM kelas WHERE id IN ({ids});")
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teachers:sql('DELETE FROM guru WHERE id IN ('+','.join(teachers)+')')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
