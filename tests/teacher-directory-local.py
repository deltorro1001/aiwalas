"""Local browser regression for live subject averages. Disposable fixtures; no gateway calls."""
import http.cookiejar,json,re,secrets,shutil,subprocess,sys,urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql';PHP='/opt/homebrew/opt/php@8.3/bin/php';BASE='http://localhost:8080/'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
prefix='directory_'+secrets.token_hex(5);password=secrets.token_hex(16)
hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
owner=year=teacher=None;classes=[];students=[];components=[];browser=None
try:
    owner=sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}','{hashed}','Wali Fixture','wali_kelas');SELECT LAST_INSERT_ID();")
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{prefix}','Ganjil',1);SELECT LAST_INSERT_ID();")
    teacher=sql("INSERT INTO guru(nama_lengkap,nama_panggilan,jenis_kelamin,aktif) VALUES('Guru Fixture Direktori','Uji','L',1);SELECT LAST_INSERT_ID();")
    animation=sql("SELECT id FROM mata_pelajaran WHERE nama_mata_pelajaran='Animasi 3D' AND aktif=1")
    sport=sql("SELECT id FROM mata_pelajaran WHERE nama_mata_pelajaran='Olahraga' AND aktif=1")
    assert animation and sport
    for index in range(2):
        cls=sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}_{index}',11,'Test',{year},{owner});SELECT LAST_INSERT_ID();");classes.append(cls)
        sql(f"INSERT INTO pengampu_mapel(guru_id,kelas_id,mata_pelajaran_id) VALUES({teacher},{cls},{animation}),({teacher},{cls},{sport})")
        for pupil in range(2):students.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{prefix}_{index}_{pupil}','Siswa Fixture {pupil}','L',{cls},1);SELECT LAST_INSERT_ID();"))
    for subject,component in [(animation,'UH1'),(animation,'UH2'),(sport,'UH1')]:
        components.append(sql(f"INSERT INTO komponen_nilai(kelas_id,mata_pelajaran_id,nama_komponen,jenis_komponen,urutan) VALUES({classes[0]},{subject},'{component}','UH',{2 if component=='UH2' else 1});SELECT LAST_INSERT_ID();"))
    def grade(student,component,semester,value):sql(f"INSERT INTO nilai_siswa(siswa_id,komponen_nilai_id,semester,nilai,dinilai_oleh) VALUES({student},{component},'{semester}',{value},{owner}) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)")
    grade(students[0],components[0],'Ganjil',80);grade(students[1],components[0],'Ganjil',100);grade(students[0],components[1],'Ganjil',0)
    grade(students[0],components[2],'Ganjil',85);grade(students[1],components[2],'Ganjil',95)
    grade(students[0],components[0],'Genap',90);grade(students[1],components[0],'Genap',94);grade(students[0],components[2],'Genap',0)
    from browser_client import CDP
    browser=CDP();browser.call('Runtime.enable');browser.call('Page.enable');browser.call('Network.enable');browser.call('Network.clearBrowserCookies');browser.call('Page.navigate',{'url':BASE})
    browser.wait("typeof authRequest==='function' && !!window.AIWalasPages?.teachers")
    credentials=json.dumps({'role':'walas','username':prefix,'password':password})
    browser.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(d=>finishLogin(d.user))")
    def open_directory(cls,semester):
        browser.js("document.getElementById('semesterSelect').value="+json.dumps(semester)+";window.activeSemester="+json.dumps(semester)+";localStorage.setItem('aiwalas.semester',"+json.dumps(semester)+");authRequest('api/bootstrap?kelas_id="+cls+"&semester="+semester+"').then(d=>{applyBootstrapData(d);window.page='teachers';window.render()})")
        browser.wait("!!document.querySelector('.teacher-directory tbody tr')")
    def summaries():return browser.js("Object.fromEntries(Array.from(document.querySelectorAll('.teacher-directory tbody tr')).map(row=>[row.children[1].textContent.trim(),row.children[3].textContent.trim()]).filter(([subject])=>['Animasi 3D','PJOK'].includes(subject)))")
    open_directory(classes[0],'Ganjil');assert summaries()=={'Animasi 3D':'Rata-rata 60','PJOK':'Rata-rata 90'},summaries()
    assert browser.js("JSON.stringify(ledgerTeacherAssessment('Animasi 3D'))===JSON.stringify(ledgerTeacherAssessment('Mapel Pilihan (Animasi 3D)'))")
    assert browser.js("JSON.stringify(ledgerTeacherAssessment('Olahraga'))===JSON.stringify(ledgerTeacherAssessment('PJOK'))")
    assert browser.js("document.querySelector('.teacher-directory .panel-head').textContent.includes('Jumlah Mapel: '+document.querySelectorAll('.teacher-directory tbody tr').length)")
    # Local drafts must never override database grades in either summary or detail.
    browser.js("localStorage.setItem('aiwalas.assessment.v6.gurufixturedirektori',JSON.stringify({"+json.dumps(prefix+'_0_0')+":{UH1:99}}));document.querySelector('[data-teacher-score=\\\"Animasi 3D\\\"]').click()".replace('\\\"','\"'))
    browser.wait("document.getElementById('modalBackdrop').classList.contains('show')")
    first=browser.js("Array.from(document.querySelector('.score-summary-table tbody tr').children).map(td=>td.textContent.trim())")
    assert first[1:]==['80','0','40'],first
    browser.js('closeModal()')
    open_directory(classes[0],'Genap');assert summaries()=={'Animasi 3D':'Rata-rata 92','PJOK':'Rata-rata 0'},summaries()
    browser.js("document.querySelector('[data-teacher-score=\\\"Animasi 3D\\\"]').click()".replace('\\\"','\"'))
    first=browser.js("Array.from(document.querySelector('.score-summary-table tbody tr').children).map(td=>td.textContent.trim())")
    assert first[1:]==['90','90'],first
    browser.js('closeModal()')
    open_directory(classes[1],'Ganjil');assert summaries()=={'Animasi 3D':'Belum ada nilai','PJOK':'Belum ada nilai'},summaries()
    grade(students[0],components[0],'Ganjil',95)
    open_directory(classes[0],'Ganjil');assert summaries()=={'Animasi 3D':'Rata-rata 65','PJOK':'Rata-rata 90'},summaries()
    assert sql(f"SELECT COUNT(*) FROM pesan_komunikasi WHERE kelas_id IN ({','.join(classes)})")=='0'
    print('PASS: Live Animasi 3D/PJOK averages, aliases, real zero versus missing values, empty class, class/semester changes, updated grades, counts and database-backed detail ignoring browser drafts. No WhatsApp sends.')
finally:
    if browser:
        try:browser.js("closeModal();localStorage.removeItem('aiwalas.assessment.v6.gurufixturedirektori');localStorage.setItem('aiwalas.semester','Ganjil');authRequest('api/auth/logout',{method:'POST',body:'{}'})")
        except Exception:pass
    if classes:
        ids=','.join(classes);sql(f"DELETE FROM pesan_komunikasi WHERE kelas_id IN ({ids});DELETE FROM nilai_siswa WHERE komponen_nilai_id IN (SELECT id FROM komponen_nilai WHERE kelas_id IN ({ids}));DELETE FROM komponen_nilai WHERE kelas_id IN ({ids});DELETE FROM pengampu_mapel WHERE kelas_id IN ({ids});DELETE FROM siswa WHERE kelas_id IN ({ids});DELETE FROM kelas WHERE id IN ({ids});")
    if teacher:sql('DELETE FROM guru WHERE id='+teacher)
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
    if owner:sql('DELETE FROM pengguna WHERE id='+owner)
