"""Local API/browser checks for Wali account management. Disposable fixtures only.
Run: AIWALAS_BROWSER_TEST=1 python3 tests/account-management-local.py
"""
import time,datetime,http.cookiejar,json,os,re,secrets,shutil,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP=shutil.which('php') or '/opt/homebrew/opt/php@8.3/bin/php';MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
prefix='accounts_'+secrets.token_hex(5);password=secrets.token_hex(16);hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
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
try:
    for role,name in [('wali_kelas','Wali Akun'),('wali_kelas','Wali Lain'),('guest','Tamu Uji')]:
        users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_{len(users)}','{hashed}','{name}','{role}');SELECT LAST_INSERT_ID();"))
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{prefix}','Ganjil',1);SELECT LAST_INSERT_ID();")
    for i in range(2):classes.append(sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}_{i}',11,'Test',{year},{users[i]});SELECT LAST_INSERT_ID();"))
    nises=['9'+''.join(str(secrets.randbelow(10)) for _ in range(12)) for _ in range(4)]
    for i,name in enumerate(['Sekretaris Uji','Siswa <img src=x onerror=alert(1)>','Siswa Belum Login','Siswa Kelas Lain']):
        pupils.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{nises[i]}','{name}','L',{classes[1] if i==3 else classes[0]},1);SELECT LAST_INSERT_ID();"))
    for i,role in [(0,'sekretaris'),(1,'siswa'),(3,'siswa')]:
        username=prefix+'_sekretaris' if i==0 else nises[i]
        users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{username}','{hashed}','Akun Uji {i}','{role}','{nises[i]}');SELECT LAST_INSERT_ID();"))
    secretary_id,student_id,foreign_id=users[3:6]
    teacher=sql("INSERT INTO guru(nama_lengkap,nama_panggilan,jenis_kelamin,aktif) VALUES('Guru Akun Uji','uji','L',1);SELECT LAST_INSERT_ID();")
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,guru_id) VALUES('{prefix}_guru','{hashed}','Guru Akun Uji','guru',{teacher});SELECT LAST_INSERT_ID();"));teacher_id=users[-1]
    def login(role,username,secret):
        client=Client();client.call('api/auth/login',{'role':role,'username':username,'password':secret});return client
    wali=login('walas',prefix+'_0',password)
    other=login('walas',prefix+'_1',password)
    student=login('student',nises[1],password)
    secretary=login('assistant',prefix+'_sekretaris',password)
    guru=login('teacher',prefix+'_guru',password)
    guest=login('guest',prefix+'_2',password)
    parent=login('parent',nises[1],nises[1]);parent_id=sql("SELECT id FROM pengguna WHERE siswa_id="+pupils[1])
    rows=wali.call('api/kelola-akun')['data']
    assert not any(row['key']=='akun:'+foreign_id for row in rows)
    assert all(row['role'] in ['sekretaris','siswa','orang_tua','guru'] for row in rows)
    assert any(row['key']=='siswa:'+pupils[2] and row['username']==nises[2] for row in rows)
    assert any(row['key']=='orang_tua:'+pupils[2] and row['username']==nises[2] for row in rows)
    assert sql(f"SELECT COUNT(*) FROM pengguna WHERE nis='{nises[2]}' OR siswa_id={pupils[2]}")=='0'
    assert all(set(row)<=set(['key','name','role','username','class','status','can_reset','shared_student']) for row in rows)
    assert not any(row['key']=='akun:'+student_id for row in other.call('api/kelola-akun')['data'])
    anon=Client();anon.call('api/kelola-akun',status=401)
    for client in [student,secretary,guru,parent,guest]:
        client.call('api/kelola-akun',status=403)
        client.call('api/kelola-akun/reset',{'akun':'akun:'+student_id,'konfirmasi':True},status=403)
    for key in ['akun:'+users[0],'akun:'+users[2],'akun:'+foreign_id]:
        wali.call('api/kelola-akun/reset',{'akun':key,'konfirmasi':True},status=404)
    wali.call('api/kelola-akun/reset',{'akun':'akun:'+student_id,'konfirmasi':False},status=422)
    wali.call('api/kelola-akun/reset',{'akun':'invalid','konfirmasi':True},status=422)
    for ident,role,username,old_secret in [(student_id,'student',nises[1],password),(parent_id,'parent',nises[1],nises[1]),(secretary_id,'assistant',prefix+'_sekretaris',password),(teacher_id,'teacher',prefix+'_guru',password)]:
        reset=wali.call('api/kelola-akun/reset',{'akun':'akun:'+ident,'konfirmasi':True})
        assert reset['username']==username and len(reset['password'])==20
        login(role,username,reset['password'])
        Client().call('api/auth/login',{'role':role,'username':username,'password':old_secret},status=401)
        stored=sql('SELECT kata_sandi FROM pengguna WHERE id='+ident)
        assert reset['password'] not in stored and stored.startswith('$2')
        if role=='assistant':
            login('student',nises[0],reset['password'])
            Client().call('api/auth/login',{'role':'student','username':nises[0],'password':nises[0]},status=401)
            Client().call('api/auth/login',{'role':'student','username':nises[0],'password':password},status=401)
    for role,key in [('student','siswa:'),('parent','orang_tua:')]:
        reset=wali.call('api/kelola-akun/reset',{'akun':key+pupils[2],'konfirmasi':True})
        assert reset['username']==nises[2]
        login(role,nises[2],reset['password'])
        Client().call('api/auth/login',{'role':role,'username':nises[2],'password':nises[2]},status=401)
    sql('UPDATE pengguna SET aktif=0 WHERE id='+student_id)
    wali.call('api/kelola-akun/reset',{'akun':'akun:'+student_id,'konfirmasi':True},status=404)
    sql('UPDATE pengguna SET aktif=1 WHERE id='+student_id)
    print('PASS: Four account roles, class ownership, hash-only storage, explicit confirmation, inactive/privileged protection, lazy provisioning and old-password rejection including secretary student login.')
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
        c.wait("typeof authRequest==='function' && !!window.AIWalasPages?.accountManagement")
        creds=json.dumps({'role':'walas','username':prefix+'_0','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+creds+")}).then(d=>finishLogin(d.user))")
        c.wait("!!document.querySelector('.sidebar [data-page=accountManagement]')")
        assert c.js("getComputedStyle(document.querySelector('.sidebar [data-page=accountManagement]')).display")!='none'
        c.js("document.querySelector('.sidebar [data-page=accountManagement]').click()")
        c.wait("!!document.querySelector('[data-managed-account=\"akun:"+secretary_id+"\"]')")
        assert not c.js("!!document.querySelector('#managedAccountRows img')")
        c.js("document.getElementById('managedAccountRole').value='sekretaris';document.getElementById('managedAccountRole').dispatchEvent(new Event('change'))")
        assert c.js("document.querySelectorAll('#managedAccountRows tr').length")==1
        c.js("document.querySelector('[data-managed-account]').click();document.getElementById('cancelModal').click()")
        c.js("document.querySelector('[data-managed-account]').click();document.getElementById('managedAccountResetForm').requestSubmit()")
        c.wait("!!document.getElementById('managedAccountResultPassword')")
        new_password=c.js("document.getElementById('managedAccountResultPassword').value")
        login('assistant',prefix+'_sekretaris',new_password)
        assert new_password not in c.js("document.getElementById('pageContent').textContent")
        c.js("document.getElementById('cancelModal').click()")
        assert not c.js("document.getElementById('managedAccountResultPassword')?.value")
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js("document.documentElement.scrollWidth<=window.innerWidth")
        c.call('Emulation.clearDeviceMetricsOverride');c.js('logout()')
        print('PASS: Kelola Akun sidebar, role filter, escaped names, confirmation, one-time password dialog and mobile layout.')
finally:
    if pupils:
        ids=','.join(pupils)
        sql('DELETE FROM pengguna WHERE siswa_id IN ('+ids+') OR nis IN (SELECT nis FROM siswa WHERE id IN ('+ids+'));DELETE FROM siswa WHERE id IN ('+ids+');')
    if classes:sql('DELETE FROM kelas WHERE id IN ('+','.join(classes)+')')
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teacher:sql('DELETE FROM guru WHERE id='+teacher)
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
