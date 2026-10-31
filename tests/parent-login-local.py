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
prefix='parent_'+secrets.token_hex(5);password=secrets.token_hex(16);hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
users=[];teacher=None;subject=None;year=None;class_id=None;classes=[];pupils=[]
class Client:
    def __init__(self):
        self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        html=self.op.open(BASE).read().decode();self.token=re.search(r"name=['\"]csrf-token['\"] content=['\"]([^'\"]+)",html).group(1)
    def call(self,path,data=None,status=200,method=None):
        req=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),headers={'Content-Type':'application/json','X-CSRF-TOKEN':self.token},method=method)
        try:r=self.op.open(req,timeout=10)
        except urllib.error.HTTPError as e:r=e
        result=json.load(r);assert r.code==status,(path,r.code,result);self.token=result.get('csrfHash',self.token);return result
def multipart(client,fields,file=None,status=201):
    boundary='aiwalas'+secrets.token_hex(12);chunks=[]
    for key,value in fields.items():chunks.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+key+'"\r\n\r\n'+str(value)+'\r\n').encode())
    if file:
        name,mime,data=file
        chunks.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="lampiran"; filename="'+name+'"\r\nContent-Type: '+mime+'\r\n\r\n').encode()+data+b'\r\n')
    chunks.append(('--'+boundary+'--\r\n').encode())
    req=urllib.request.Request(BASE+'api/pengajuan-sakit',data=b''.join(chunks),headers={'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-TOKEN':client.token})
    try:r=client.op.open(req)
    except urllib.error.HTTPError as error:r=error
    result=json.load(r);client.token=result.get('csrfHash',client.token);assert r.code==status,(r.code,result);return result
try:
    nis_a='9'+''.join(str(secrets.randbelow(10)) for _ in range(12));nis_b='8'+nis_a[1:]
    student_password='Student-'+secrets.token_hex(8);parent_password='Parent-'+secrets.token_hex(8)
    student_hash=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',student_password],text=True)
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_wali','{hashed}','Parent Test Wali','wali_kelas');SELECT LAST_INSERT_ID();"))
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{prefix}','Ganjil',1);SELECT LAST_INSERT_ID();")
    for i in range(2):classes.append(sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}_{i}',11,'Test',{year},{users[0]});SELECT LAST_INSERT_ID();"))
    class_id=classes[0]
    for nis,name in [(nis_a,'Anak Uji'),(nis_b,'Anak Lain')]:pupils.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{nis}','{name}','L',{class_id},1);SELECT LAST_INSERT_ID();"))
    student_id=sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{nis_a}','{student_hash}','Anak Uji','siswa','{nis_a}');SELECT LAST_INSERT_ID();");users.append(student_id)
    teacher=sql("INSERT INTO guru(nama_lengkap,nama_panggilan,jenis_kelamin,aktif) VALUES('Parent Test Guru','Test','L',1);SELECT LAST_INSERT_ID();")
    subject=sql(f"INSERT INTO mata_pelajaran(nama_mata_pelajaran,aktif) VALUES('{prefix}_mapel',1);SELECT LAST_INSERT_ID();")
    for day in ['Senin','Selasa','Rabu','Kamis','Jumat']:sql(f"INSERT INTO jadwal_kelas(kelas_id,mata_pelajaran_id,guru_id,hari,jam_mulai,jam_selesai) VALUES({class_id},{subject},{teacher},'{day}','08:00','09:00');")
    today=datetime.datetime.now(ZoneInfo('Asia/Jakarta')).date()
    sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,waktu_scan,semester,dicatat_oleh) VALUES({pupils[0]},{class_id},'{today}','Hadir','06:35:00','Ganjil',{users[0]}),({pupils[1]},{class_id},'{today}','Alpha',NULL,'Ganjil',{users[0]});")
    component=sql(f"INSERT INTO komponen_nilai(kelas_id,mata_pelajaran_id,nama_komponen,jenis_komponen) VALUES({class_id},{subject},'UH1','UH');SELECT LAST_INSERT_ID();")
    sql(f"INSERT INTO nilai_siswa(siswa_id,komponen_nilai_id,nilai,semester,dinilai_oleh) VALUES({pupils[0]},{component},86,'Ganjil',{users[0]}),({pupils[1]},{component},22,'Ganjil',{users[0]});")
    parent=Client();parent.call('api/auth/login',{'role':'parent','username':nis_a,'password':'wrong'},status=401)
    assert sql("SELECT COUNT(*) FROM pengguna WHERE peran='orang_tua' AND siswa_id="+pupils[0])=='0'
    login=parent.call('api/auth/login',{'role':'parent','username':nis_a,'password':nis_a})['user'];parent_id=str(login['id'])
    assert login['role']=='student' and login['isParent'] is True and login['accountRole']=='parent'
    assert login['name']=='Orangtua/Wali Murid Anak Uji' and login['username']==nis_a and login['nis']==nis_a and parent_id!=student_id
    assert parent.call('api/auth/session')['user']==login
    assert sql('SELECT nis IS NULL FROM pengguna WHERE id='+parent_id)=='1'
    student=Client();student.call('api/auth/login',{'role':'student','username':nis_a,'password':student_password})
    student.call('api/auth/login',{'role':'student','username':nis_a,'password':nis_a},status=401)
    parent.call('api/auth/login',{'role':'parent','username':nis_a,'password':student_password},status=401)
    bootstrap=parent.call('api/bootstrap?kelas_id='+classes[1])
    assert str(bootstrap['selectedClassId'])==class_id and len(bootstrap['legacyStudents'])==1 and bootstrap['legacyStudents'][0][1]==nis_a
    for path in ['api/siswa/dashboard','api/siswa/kedisiplinan','api/siswa/rekap-absensi','api/jadwal?kelas_id='+classes[1],'api/absensi?kelas_id='+class_id,'api/akademik?kelas_id='+class_id]:
        a=parent.call(path);b=student.call(path)
        a.pop('csrfHash',None);b.pop('csrfHash',None);assert a==b,(path,a,b)
    parent.call('api/siswa/'+pupils[1],status=403)
    parent.call('api/akademik?kelas_id='+classes[1],status=403)
    for path in ['api/siswa','api/jadwal','api/kedisiplinan','api/akun-guru/1','api/akademik','api/whatsapp/send']:
        parent.call(path,{},status=403)
    wali=Client();wali.call('api/auth/login',{'role':'walas','username':prefix+'_wali','password':password})
    wali.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':'etika','pelanggaran':'Catatan Anak Uji','tindakan':'Pembinaan'})
    assert parent.call('api/siswa/kedisiplinan')['data']==student.call('api/siswa/kedisiplinan')['data']
    qr=wali.call('api/absensi/qr',{'kelas_id':int(class_id)})['data']['qr_code']
    parent.call('api/absensi',{'kelas_id':int(class_id),'qr_code':qr,'siswa_id':int(pupils[1]),'waktu_scan':'00:00:00','status':'Hadir'},status=403)
    assert sql(f"SELECT status FROM absensi WHERE siswa_id={pupils[0]} AND tanggal='{today}'")=='Hadir'
    assert sql(f"SELECT status FROM absensi WHERE siswa_id={pupils[1]} AND tanggal='{today}'")=='Alpha'
    parent.call('api/auth/password',{'password':parent_password,'confirmation':parent_password})
    changed_hash=sql('SELECT kata_sandi FROM pengguna WHERE id='+parent_id)
    boot="define('ENVIRONMENT','development');define('FCPATH',getcwd().'/public/');require 'app/Config/Paths.php';$p=new Config\\Paths();require $p->systemDirectory.'/Boot.php';CodeIgniter\\Boot::bootConsole($p);$s=db_connect()->table('siswa')->where('id',(int)$argv[1])->get()->getRowArray();App\\Libraries\\ParentAccounts::ensure($s);"
    subprocess.check_call([PHP,'-r',boot,pupils[0]],cwd=ROOT,stdout=subprocess.DEVNULL)
    assert sql('SELECT kata_sandi FROM pengguna WHERE id='+parent_id)==changed_hash
    fresh=Client();fresh.call('api/auth/login',{'role':'parent','username':nis_a,'password':nis_a},status=401)
    fresh.call('api/auth/login',{'role':'parent','username':nis_a,'password':parent_password})
    student.call('api/auth/login',{'role':'student','username':nis_a,'password':student_password})
    b_parent=Client();b_login=b_parent.call('api/auth/login',{'role':'parent','username':nis_b,'password':nis_b})['user']
    fields={'jumlah_hari':2,'tanggal_mulai':str(today),'tanggal_selesai':str(today+datetime.timedelta(days=1)),'keterangan':'Istirahat <img src=x onerror=alert(1)>','siswa_id':pupils[1],'kelas_id':classes[1]}
    student.call('api/pengajuan-sakit',status=403)
    multipart(student,fields,status=403);multipart(wali,fields,status=403)
    multipart(parent,dict(fields,tanggal_selesai=str(today-datetime.timedelta(days=1))),status=422)
    multipart(parent,dict(fields,jumlah_hari=3),status=422)
    multipart(parent,dict(fields,tanggal_mulai='2026-02-30'),status=422)
    multipart(parent,fields,('fake.jpg','image/jpeg',b'<?php echo "invalid";'),status=422)
    multipart(parent,fields,('large.pdf','application/pdf',b'%PDF-'+b'0'*(2*1024*1024)),status=422)
    pdf=b'%PDF-1.4\n1 0 obj<</Type /Catalog>>endobj\n%%EOF'
    request_id=multipart(parent,fields,('surat-sakit.pdf','application/pdf',pdf))['id']
    multipart(parent,fields,status=409)
    requests=parent.call('api/pengajuan-sakit?siswa_id='+pupils[1])['data']
    assert len(requests)==1 and str(requests[0]['id'])==str(request_id) and requests[0]['nama_lengkap']=='Anak Uji' and requests[0]['status']=='Terkirim'
    assert b_parent.call('api/pengajuan-sakit')['data']==[]
    assert len(wali.call('api/pengajuan-sakit?kelas_id='+class_id)['data'])==1
    assert wali.call('api/pengajuan-sakit?kelas_id='+classes[1])['data']==[]
    url='api/pengajuan-sakit/'+str(request_id)+'/lampiran'
    assert parent.op.open(BASE+url).read()==pdf
    assert wali.op.open(BASE+url).read()==pdf
    b_parent.call(url,status=404);student.call(url,status=403)
    anonymous=Client();anonymous.call('api/pengajuan-sakit',status=401)
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_outsider','{hashed}','Other Wali','wali_kelas');SELECT LAST_INSERT_ID();"))
    outsider=Client();outsider.call('api/auth/login',{'role':'walas','username':prefix+'_outsider','password':password})
    assert outsider.call('api/pengajuan-sakit?kelas_id='+class_id)['data']==[]
    outsider.call(url,status=404)
    multipart(parent,dict(fields,jumlah_hari=61,tanggal_selesai=str(today+datetime.timedelta(days=60))),status=422)
    import base64
    png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l3sAAAAASUVORK5CYII=')
    png_id=multipart(parent,dict(fields,jumlah_hari=1,tanggal_mulai='2028-02-29',tanggal_selesai='2028-02-29'),('surat.png','image/png',png))['id']
    assert parent.op.open(BASE+'api/pengajuan-sakit/'+str(png_id)+'/lampiran').read()==png
    expected='Hadir' if today.weekday()>=5 or str(today) in ['2026-08-17','2026-08-18','2026-08-25','2026-08-27','2026-08-28','2026-09-07','2026-09-08'] else 'Sakit'
    assert sql(f"SELECT status FROM absensi WHERE siswa_id={pupils[0]} AND tanggal='{today}'")==expected
    print('PASS: Parent sick requests, multipart PDF upload, invalid MIME/size/dates, overlap protection, child identity, class ownership, private attachment access and automatic sick attendance.')
    b_student=Client();b_student.call('api/auth/login',{'role':'student','username':nis_b,'password':nis_b})
    assert b_student.call('api/auth/session')['user']['isParent'] is False
    sql('UPDATE pengguna SET aktif=0 WHERE id='+str(b_login['id']))
    assert b_parent.call('api/auth/session')['authenticated'] is False
    b_parent.call('api/auth/login',{'role':'parent','username':nis_b,'password':nis_b},status=401)
    assert sql('SELECT aktif FROM pengguna WHERE id='+str(b_login['id']))=='0'
    sql('UPDATE pengguna SET aktif=1 WHERE id='+str(b_login['id']))
    b_parent.call('api/auth/login',{'role':'parent','username':nis_b,'password':nis_b})
    sql('UPDATE siswa SET aktif=0 WHERE id='+pupils[1])
    b_parent.call('api/bootstrap',status=401)
    b_parent.call('api/auth/login',{'role':'parent','username':nis_b,'password':nis_b},status=401)
    nis_c='7'+nis_a[1:]
    child_payload={'nis':nis_c,'nama_lengkap':'Anak Baru','jenis_kelamin':'L','kelas_id':int(class_id),'aktif':1}
    created=wali.call('api/siswa',child_payload,status=201)['data'];new_child_id=str(created['id']);pupils.append(new_child_id)
    assert sql("SELECT COUNT(*) FROM pengguna WHERE peran='orang_tua' AND siswa_id="+new_child_id)=='1'
    c_parent=Client();c_parent.call('api/auth/login',{'role':'parent','username':nis_c,'password':nis_c})
    child_payload['nama_lengkap']='Anak Baru Diperbarui'
    wali.call('api/siswa/'+new_child_id,child_payload,method='PUT')
    renamed=c_parent.call('api/auth/login',{'role':'parent','username':nis_c,'password':nis_c})['user']
    assert renamed['name']=='Orangtua/Wali Murid Anak Baru Diperbarui'
    wali.call('api/siswa/'+new_child_id,method='DELETE')
    assert sql("SELECT aktif FROM pengguna WHERE peran='orang_tua' AND siswa_id="+new_child_id)=='0'
    c_parent.call('api/siswa/dashboard',status=401)
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies')
        c.call('Page.navigate',{'url':BASE});c.wait("typeof authRequest==='function' && !!window.AIWalasPages && window.render.toString().includes('var studentRoutes')")
        assert c.js("!!document.querySelector('#loginRole option[value=parent]')")
        c.js("document.getElementById('loginRole').value='parent';document.getElementById('loginRole').dispatchEvent(new Event('change'))")
        assert 'NIS anak' in c.js("document.getElementById('loginNote').textContent")
        c.js("document.getElementById('loginUsername').value="+json.dumps(nis_a)+";document.getElementById('loginPassword').value="+json.dumps(parent_password)+";document.getElementById('loginForm').dispatchEvent(new Event('submit',{cancelable:true}))")
        c.wait("document.querySelector('.student-home h1')?.textContent==='Selamat datang, Orangtua/Wali Murid Anak Uji'")
        parent_menu=c.js("Array.from(document.querySelectorAll('.sidebar .nav-item[data-page]')).filter(el=>el.getClientRects().length).map(el=>el.dataset.page)")
        for route in ['dashboard','attendance','studentSchedule','studentDiscipline','studentGrades','settings']:assert route in parent_menu,(route,parent_menu)
        assert 'Catatan Anak Uji' in c.js("document.querySelector('.student-home').textContent")
        c.js("window.page='attendance';window.render()")
        c.wait("document.querySelector('.student-attendance-page h1')?.textContent==='Halo, Orangtua/Wali Murid Anak Uji'")
        assert c.js("!document.getElementById('studentQrButton') && !document.querySelector('.student-attendance-card') && !!document.getElementById('studentAttendanceMonth') && !!document.getElementById('studentAttendanceStatus') && !!document.getElementById('studentAttendanceScan')")
        assert 'Izinkan kamera' not in c.js("document.querySelector('.student-attendance-page').textContent")
        c.wait("!!document.getElementById('sickRequestForm') && document.querySelector('.sick-request-history').textContent.includes('Istirahat <img')")
        assert not c.js("!!document.querySelector('.sick-request-history img')")
        c.js("document.getElementById('sickRequestStart').value='2027-01-31';document.getElementById('sickRequestDays').value='3';document.getElementById('sickRequestDays').dispatchEvent(new Event('input'))")
        assert c.js("document.getElementById('sickRequestEnd').value")=='2027-02-02'
        c.js("document.getElementById('sickRequestEnd').value='2027-02-03';document.getElementById('sickRequestEnd').dispatchEvent(new Event('change'))")
        assert c.js("document.getElementById('sickRequestDays').value")=='4'
        c.js("document.getElementById('sickRequestNote').value='Pengajuan melalui formulir';document.getElementById('sickRequestForm').requestSubmit()")
        c.wait("document.getElementById('sickRequestMessage')?.textContent.includes('berhasil dikirim')")
        c.wait("document.querySelector('.sick-request-history').textContent.includes('Pengajuan melalui formulir')")
        c.js("window.render()")
        c.wait("document.querySelector('.sick-request-history')?.textContent.includes('Pengajuan melalui formulir')")
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js("document.documentElement.scrollWidth<=window.innerWidth"),'Parent sick form mobile overflow'
        if os.environ.get('AIWALAS_SICK_SCREENSHOT'):
            import base64
            c.js("document.getElementById('sickRequestCard').scrollIntoView()")
            Path(os.environ['AIWALAS_SICK_SCREENSHOT']).write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
        c.call('Emulation.clearDeviceMetricsOverride')
        c.js("window.page='settings';window.render()")
        c.wait("!!document.querySelector('.settings-profile h2')")
        assert c.js("document.querySelector('.settings-profile h2').textContent")=='Orangtua/Wali Murid Anak Uji'
        assert c.js("document.getElementById('settingsName').value")=='Orangtua/Wali Murid Anak Uji'
        creds=json.dumps({'role':'student','username':nis_a,'password':student_password})
        c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(function(){return authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+creds+")})}).then(function(d){return finishLogin(d.user)})")
        c.wait("document.querySelector('.student-home h1')?.textContent==='Selamat datang, Anak Uji'")
        student_menu=c.js("Array.from(document.querySelectorAll('.sidebar .nav-item[data-page]')).filter(el=>el.getClientRects().length).map(el=>el.dataset.page)")
        assert student_menu==parent_menu,(student_menu,parent_menu)
        assert c.js("currentUser.isParent") is False
        c.js("window.page='attendance';window.render()")
        assert not c.js("!!document.getElementById('sickRequestCard')")
        wali_creds=json.dumps({'role':'walas','username':prefix+'_wali','password':password})
        c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(function(){return authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+wali_creds+")})}).then(function(d){return finishLogin(d.user)}).then(function(){return authRequest('api/bootstrap?kelas_id="+class_id+"')}).then(function(d){applyBootstrapData(d);window.page='attendance';window.render()})")
        c.wait("document.querySelector('#sickRequestCard .sick-request-history')?.textContent.includes('Pengajuan melalui formulir')")
        assert not c.js("!!document.getElementById('sickRequestForm')")
        assert not c.js("!!document.querySelector('.sick-request-history img')")
        print('PASS: Parent sick card date/day sync, multipart form submission, history persistence, mobile layout, student hidden card and wali read-only review.')
        assert not [e for e in c.events if e.get('method')=='Runtime.exceptionThrown'],c.events
        c.js("logout()")
        print('PASS: Parent login choice, identical student menus/content, parent prefix on home/attendance/settings, student greetings unchanged; no runtime errors.')
    print('PASS: Parent NIS credentials, separate password/identity, provisioning idempotency, same child data/read-only attendance, ownership/admin restrictions, lazy student login, disabled account/child sessions rejected.')
finally:
    if classes:
        ids=','.join(classes)
        for filename in sql('SELECT berkas_lampiran FROM pengajuan_sakit WHERE kelas_id IN ('+ids+') AND berkas_lampiran IS NOT NULL').splitlines():
            path=ROOT/'writable'/'pengajuan-sakit'/filename
            if path.is_file():path.unlink()
        sql('DELETE FROM pengajuan_sakit WHERE kelas_id IN ('+ids+')')
        sql('DELETE FROM nilai_siswa WHERE siswa_id IN (SELECT id FROM siswa WHERE kelas_id IN ('+ids+'));DELETE FROM catatan_kedisiplinan WHERE kelas_id IN ('+ids+');DELETE FROM pesan_komunikasi WHERE kelas_id IN ('+ids+');DELETE FROM qr_absensi_aktif WHERE kelas_id IN ('+ids+');DELETE FROM absensi WHERE kelas_id IN ('+ids+');DELETE FROM komponen_nilai WHERE kelas_id IN ('+ids+');DELETE FROM jadwal_kelas WHERE kelas_id IN ('+ids+');')
        if pupils:sql('DELETE FROM pengguna WHERE siswa_id IN ('+','.join(pupils)+') OR nis IN (SELECT nis FROM siswa WHERE id IN ('+','.join(pupils)+'));')
        sql('DELETE FROM siswa WHERE kelas_id IN ('+ids+');DELETE FROM kelas WHERE id IN ('+ids+');')
    if teacher:sql('DELETE FROM guru WHERE id='+teacher)
    if subject:sql('DELETE FROM mata_pelajaran WHERE id='+subject)
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
