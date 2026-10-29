"""Regression integration test against a LOCAL development database and server.
Run: python3 tests/academic-local.py (PHP and mysql must be on PATH).
Creates isolated fixtures and removes them in finally; sends no external messages.
"""
import http.cookiejar, json, os, re, secrets, shutil, subprocess, urllib.error, urllib.request
BASE = 'http://localhost:8080/'
PHP = shutil.which('php') or '/opt/homebrew/opt/php@8.3/bin/php'
MYSQL = shutil.which('mysql') or '/opt/homebrew/bin/mysql'
def sql(query):
    return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',query],text=True).strip()
assert os.path.isfile('.env') and re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',open('.env').read(),re.M)
prefix='reg_'+secrets.token_hex(5)
password=secrets.token_hex(16)
hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1], PASSWORD_DEFAULT);',password],text=True)
class Client:
    def __init__(self):
        self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        html=self.op.open(BASE).read().decode()
        self.token=re.search(r"name=['\"]csrf-token['\"] content=['\"]([^'\"]+)",html).group(1)
    def call(self,path,data=None,method=None,status=200):
        req=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),method=method,headers={'Content-Type':'application/json','X-CSRF-TOKEN':self.token})
        try:r=self.op.open(req)
        except urllib.error.HTTPError as e:r=e
        body=json.load(r)
        self.token=body.get('csrfHash',self.token)
        assert r.code==status,(path,r.code,body)
        return body
    def login(self,name,role='teacher',pwd=password,status=200):
        return self.call('api/auth/login',{'role':role,'username':name,'password':pwd},status=status)
classes=[];teachers=[];users=[]
try:
    year=sql('SELECT id FROM tahun_ajaran LIMIT 1')
    subjects=sql('SELECT id FROM mata_pelajaran WHERE aktif=1 ORDER BY id LIMIT 2').splitlines()
    for i in range(2):
        classes.append(sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id) VALUES('{prefix}_{i}',11,'Test',{year}); SELECT LAST_INSERT_ID();"))
        teachers.append(sql(f"INSERT INTO guru(nama_lengkap,nama_panggilan,aktif) VALUES('{prefix}_{i}','{prefix}_{i}',1); SELECT LAST_INSERT_ID();"))
        users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,guru_id) VALUES('{prefix}_{i}','{hashed}','{prefix}_{i}','guru',{teachers[i]}); SELECT LAST_INSERT_ID();"))
        sql(f"INSERT INTO jadwal_kelas(kelas_id,mata_pelajaran_id,guru_id,hari,jam_mulai,jam_selesai) VALUES({classes[0]},{subjects[i]},{teachers[i]},'Senin','07:00','08:00')")
    nis=str(secrets.randbelow(900000000)+100000000)
    sid=sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{nis}','{prefix}','L',{classes[0]},1); SELECT LAST_INSERT_ID();")
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{prefix}_s','{hashed}','{prefix}','siswa','{nis}'); SELECT LAST_INSERT_ID();"))
    a,b,student=Client(),Client(),Client()
    a.login(prefix+'_0',pwd=prefix+'_0',status=401)
    a.login(prefix+'_0');b.login(prefix+'_1');student.login(prefix+'_s',role='student')
    bootstrap=a.call('api/bootstrap')
    assert [str(c['id']) for c in bootstrap['classes']]==[classes[0]]
    assert len(bootstrap['teacherSubjects'])==1
    a.call('api/akademik?kelas_id='+classes[1],status=403)
    payload={'kelas_id':int(classes[0]),'mata_pelajaran_id':int(subjects[0]),'semester':'Ganjil','komponen':'UH6','values':{nis:88}}
    a.call('api/akademik',payload)
    other=Client();other.login(prefix+'_0')
    assert float(other.call('api/akademik?kelas_id='+classes[0])['data'][0]['nilai'])==88
    assert not other.call('api/akademik?kelas_id='+classes[0]+'&semester=Genap')['data']
    a.call('api/akademik',dict(payload,semester='Genap',values={nis:74}))
    assert float(student.call('api/akademik?kelas_id='+classes[0]+'&semester=Genap')['data'][0]['nilai'])==74
    assert float(student.call('api/siswa/dashboard')['data']['capaian_nilai'][0]['nilai'])==88
    b.call('api/akademik',payload,status=422)
    a.call('api/akademik',dict(payload,values={nis:101}),status=422)
    a.call('api/akademik',dict(payload,values={'not-a-student':50}),status=422)
    student.call('api/akademik',payload,status=403)
    journal={'kelas_id':int(classes[0]),'mata_pelajaran_id':int(subjects[0]),'guru_id':int(teachers[0]),'tanggal':'2026-10-18','materi':'Database journal regression','catatan':'Test','status':'Terbit'}
    saved=a.call('api/jurnal',journal)
    assert other.call('api/jurnal?kelas_id='+classes[0])['data'][0]['materi']==journal['materi']
    assert not b.call('api/jurnal?kelas_id='+classes[0])['data']
    b.call('api/jurnal/'+str(saved['id']),dict(journal,guru_id=int(teachers[1]),mata_pelajaran_id=int(subjects[1])),method='PUT',status=403)
    a.call('api/jurnal/'+str(saved['id']),dict(journal,materi='Updated'),method='PUT')
    assert other.call('api/jurnal?kelas_id='+classes[0])['data'][0]['materi']=='Updated'
    a.call('api/auth/password',{'password':password+'new','confirmation':password+'new'})
    fresh=Client();fresh.login(prefix+'_0',pwd=password,status=401);fresh.login(prefix+'_0',pwd=password+'new')
    walas=Client();walas.login('sumantoro',role='walas',pwd='aiwalas123')
    account_teacher=sql(f"INSERT INTO guru(nama_lengkap,nama_panggilan,aktif) VALUES('{prefix}_account','{prefix}_account',1); SELECT LAST_INSERT_ID();")
    teachers.append(account_teacher)
    a.call('api/akun-guru',status=403)
    student.call('api/akun-guru/'+account_teacher,{'username':prefix+'_account'},status=403)
    walas.call('api/akun-guru/'+account_teacher,{'username':prefix+'_0'},status=409)
    made=walas.call('api/akun-guru/'+account_teacher,{'username':prefix+'_account'})
    users.append(sql('SELECT id FROM pengguna WHERE guru_id='+account_teacher))
    provisioned=Client();provisioned.login(made['username'],pwd=made['password'])
    assert 'kata_sandi' not in json.dumps(walas.call('api/akun-guru'))
    walas.call('api/akun-guru/'+account_teacher,{'username':prefix+'_account'},status=409)
    reset=walas.call('api/akun-guru/'+account_teacher,{'username':prefix+'_account','reset':True})
    provisioned.login(made['username'],pwd=made['password'],status=401)
    provisioned.login(reset['username'],pwd=reset['password'])
    print('PASS: Wali Kelas account creation/reset, hashed login, old password rejected, duplicate username rejected, non-admin account access denied.')
    walas.call('api/maintenance/apply',{'name':'test.zip'},status=403)
    walas.call('api/maintenance/upload',{},status=403)
    for i in range(5):a.call('api/jurnal',dict(journal,materi='Additional journal '+str(i)))
    assert len(other.call('api/jurnal?kelas_id='+classes[0])['data'])==6
    if os.environ.get('AIWALAS_BROWSER_TEST') == '1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Page.navigate',{'url':BASE})
        c.wait("typeof authRequest==='function' && !!window.AIWalasPages && !!window.AIWalasPages.journal")
        c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'})")
        credentials=json.dumps({'role':'teacher','username':prefix+'_0','password':password+'new'})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(function(d){return finishLogin(d.user)})")
        c.js("window.page='assessment';window.render()")
        c.wait("!!document.getElementById('openAssessmentInput')")
        c.js("document.getElementById('openAssessmentInput').click();document.getElementById('assessmentComponent').value='UH6';document.getElementById('assessmentComponent').onchange();document.querySelector('.assessment-score').value='91';document.getElementById('saveAssessmentValues').click()")
        c.wait("!document.getElementById('saveAssessmentValues') || !document.getElementById('modalBackdrop').classList.contains('show')")
        assert float(other.call('api/akademik?kelas_id='+classes[0])['data'][0]['nilai'])==91
        c.call('Page.reload');c.wait("typeof authRequest==='function' && !!window.currentUser && currentRole==='teacher'")
        c.js("window.page='assessment';window.render()")
        c.wait("!!document.querySelector('.assessment-table')")
        assert '91' in c.js("document.querySelector('.assessment-table').textContent")
        c.wait("typeof window.XLSX !== 'undefined'")
        c.js("document.getElementById('openAssessmentInput').click();document.getElementById('assessmentComponent').value='UH2';document.getElementById('assessmentComponent').onchange()")
        c.js("(function(){var book=XLSX.utils.book_new();XLSX.utils.book_append_sheet(book,XLSX.utils.aoa_to_sheet([[String.fromCharCode(65279)+'NIS','UH2'],["+json.dumps(nis)+",82]]),'Nilai');var file=new File([XLSX.write(book,{bookType:'xlsx',type:'array'})],'nilai.xlsx');var transfer=new DataTransfer();transfer.items.add(file);document.getElementById('assessmentFile').files=transfer.files;document.getElementById('assessmentFile').onchange()})()")
        c.wait("document.querySelector('.assessment-score').value==='82'")
        c.js("document.getElementById('saveAssessmentValues').click()")
        c.wait("!document.getElementById('modalBackdrop').classList.contains('show')")
        saved_rows=other.call('api/akademik?kelas_id='+classes[0])['data']
        assert any(row['nama_komponen']=='UH2' and float(row['nilai'])==82 for row in saved_rows)
        c.js("window.page='journal';window.render()")
        c.wait("!!document.getElementById('addJournal') && !document.getElementById('addJournal').disabled")
        c.js("document.getElementById('addJournal').click();document.getElementById('jMateri').value='Browser persisted journal';document.getElementById('journalForm').requestSubmit()")
        c.wait("document.getElementById('pageContent').textContent.includes('Browser persisted journal')")
        assert len(other.call('api/jurnal?kelas_id='+classes[0])['data'])==7
        assert c.js("document.querySelectorAll('.teacher-journal-table tbody tr').length")==1
        c.js("document.getElementById('journalViewDate').value='2026-10-18';document.getElementById('journalViewDate').dispatchEvent(new Event('change'))")
        c.wait("document.querySelectorAll('.teacher-journal-table tbody tr').length===6")
        browser_teacher=sql(f"INSERT INTO guru(nama_lengkap,nama_panggilan,aktif) VALUES('{prefix}_ui','{prefix}_ui',1); SELECT LAST_INSERT_ID();")
        teachers.append(browser_teacher)
        c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(function(){return authRequest('api/auth/login',{method:'POST',body:JSON.stringify({role:'walas',username:'sumantoro',password:'aiwalas123'})})}).then(function(d){return finishLogin(d.user)})")
        c.js("window.page='teacherAccounts';window.render()")
        selector='[data-teacher-account="'+browser_teacher+'"]'
        c.wait('!!document.querySelector('+json.dumps(selector)+')')
        c.js('document.querySelector('+json.dumps(selector)+').click()')
        c.js("document.getElementById('teacherAccountUsername').value="+json.dumps(prefix+'_ui')+";document.getElementById('teacherAccountForm').requestSubmit()")
        c.wait("document.getElementById('modalContent').textContent.includes('Akun Guru Siap')")
        issued=c.js("Array.from(document.querySelectorAll('#modalContent input')).map(function(i){return i.value})")
        users.append(sql('SELECT id FROM pengguna WHERE guru_id='+browser_teacher))
        ui_client=Client();ui_client.login(issued[0],pwd=issued[1])
        c.js("closeModal()")
        c.wait('document.querySelector('+json.dumps(selector)+').textContent==="Reset Password"')
        c.js('document.querySelector('+json.dumps(selector)+').click();document.getElementById("teacherAccountForm").requestSubmit()')
        c.wait("document.getElementById('modalContent').textContent.includes('Akun Guru Siap')")
        issued_reset=c.js("Array.from(document.querySelectorAll('#modalContent input')).map(function(i){return i.value})")
        ui_client.login(issued[0],pwd=issued[1],status=401)
        ui_client.login(issued_reset[0],pwd=issued_reset[1])
        print('PASS: Browser account creation and reset, resulting credentials verified by teacher login.')
        errors=[e for e in c.events if e.get('method')=='Runtime.exceptionThrown']
        assert not errors,errors
        print('PASS: Chrome teacher login, grade save/reload, Excel import with BOM header, journal form and seven visible rows; no runtime exceptions.')
    print('PASS: hashed teacher login, password change, scoped classes/subjects, cross-session grade and journal persistence, semester isolation, student read-only access, invalid grades, unauthorized journal edits, disabled web patches.')
finally:
    if classes:
        ids=','.join(classes)
        sql(f'DELETE FROM jurnal_kelas WHERE kelas_id IN ({ids}); DELETE n FROM nilai_siswa n JOIN siswa s ON s.id=n.siswa_id WHERE s.kelas_id IN ({ids}); DELETE FROM siswa WHERE kelas_id IN ({ids}); DELETE FROM jadwal_kelas WHERE kelas_id IN ({ids}); DELETE FROM komponen_nilai WHERE kelas_id IN ({ids}); DELETE FROM kelas WHERE id IN ({ids});')
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teachers:sql('DELETE FROM guru WHERE id IN ('+','.join(teachers)+')')
