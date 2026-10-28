"""Input through a temporary teacher fixture based on Wahyu must reach the camera ledger column.
Uses disposable classes/accounts; does not reset real credentials or call the WhatsApp gateway.
"""
import json,re,secrets,shutil,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql';PHP='/opt/homebrew/opt/php@8.3/bin/php';BASE='http://localhost:8080/'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
prefix='camera_'+secrets.token_hex(5);password=secrets.token_hex(16)
hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
users=[];classes=[];year=None;subject=None;created_subject=False;browser=None;pupil=None;teacher=None
try:
    original=sql("SELECT id FROM guru WHERE nama_lengkap LIKE 'Wahyu Lukman Hakim%' AND aktif=1 LIMIT 1");assert original
    assert int(sql(f"SELECT COUNT(*) FROM jadwal_kelas j JOIN mata_pelajaran m ON m.id=j.mata_pelajaran_id WHERE j.guru_id={original} AND m.nama_mata_pelajaran='Tata Kamera & Cahaya'"))>0
    teacher=sql(f"INSERT INTO guru(nama_lengkap,nama_panggilan,jenis_kelamin,aktif) SELECT nama_lengkap,nama_panggilan,jenis_kelamin,1 FROM guru WHERE id={original};SELECT LAST_INSERT_ID();")
    for role in ['wali_kelas','guru']:
        users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,guru_id) VALUES('{prefix}_{role}','{hashed}','Fixture {role}','{role}',{teacher if role=='guru' else 'NULL'});SELECT LAST_INSERT_ID();"))
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{prefix}','Ganjil',1);SELECT LAST_INSERT_ID();")
    subject=sql("SELECT id FROM mata_pelajaran WHERE nama_mata_pelajaran='Tata Kamera & Cahaya' AND aktif=1")
    if not subject:
        subject=sql("INSERT INTO mata_pelajaran(nama_mata_pelajaran,aktif) VALUES('Tata Kamera dan Cahaya',1);SELECT LAST_INSERT_ID();");created_subject=True
    other_subject=sql("SELECT id FROM mata_pelajaran WHERE nama_mata_pelajaran='PAI' AND aktif=1")
    for i in range(2):
        cls=sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}_{i}',11,'Test',{year},{users[0]});SELECT LAST_INSERT_ID();");classes.append(cls)
        sql(f"INSERT INTO pengampu_mapel(guru_id,kelas_id,mata_pelajaran_id) VALUES({teacher},{cls},{subject}),({teacher},{cls},{other_subject})")
    pupil=sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{prefix}','Siswa Fixture Kamera','L',{classes[0]},1);SELECT LAST_INSERT_ID();")
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{prefix}_siswa','{hashed}','Siswa Fixture Kamera','siswa','{prefix}');SELECT LAST_INSERT_ID();"))
    other_pupil=sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{prefix}_other','Siswa Lain','L',{classes[1]},1);SELECT LAST_INSERT_ID();")
    sql(f"UPDATE siswa SET nomor_hp_orang_tua='081234567890' WHERE id={pupil}")
    from browser_client import CDP
    browser=CDP();browser.call('Runtime.enable');browser.call('Page.enable');browser.call('Network.enable');browser.call('Network.clearBrowserCookies');browser.call('Page.navigate',{'url':BASE})
    browser.wait("typeof academicSubjectKey==='function' && !!window.AIWalasPages?.assessment")
    credentials=json.dumps({'role':'teacher','username':prefix+'_guru','password':password})
    browser.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(d=>finishLogin(d.user)).then(()=>authRequest('api/bootstrap?kelas_id="+classes[0]+"')).then(d=>{applyBootstrapData(d);applyRoleAccess()})")
    browser.wait("document.getElementById('teacherSubjectSelect')?.querySelector('option[value=\"Tata Kamera dan Cahaya\"]') && window.activeClassId==="+classes[0])
    assert browser.js("['Tata Kamera & Cahaya','Tata Kamera dan Cahaya','Tata Kamera dan Tata Cahaya','Tata Kamera & Tata Cahaya'].every(s=>academicSubjectKey(s)===academicSubjectKey('Tata Kamera & Cahaya'))")
    browser.js("localStorage.setItem('aiwalas.teacher.subject','Tata Kamera & Cahaya');applyRoleAccess()")
    assert browser.js("document.getElementById('teacherSubjectSelect').value")=='Tata Kamera dan Cahaya'
    browser.js("localStorage.setItem('aiwalas.teacher.subject','Tata Kamera dan Cahaya');var select=document.getElementById('teacherSubjectSelect');select.value='Tata Kamera dan Cahaya';select.selectedOptions[0].value='Tata Kamera & Cahaya';select.value='Tata Kamera & Cahaya';window.page='assessment';window.render()")
    browser.wait("document.querySelector('#pageContent h1')?.textContent==='Nilai Tata Kamera dan Cahaya'")
    def input_score(component,value):
        browser.js("document.getElementById('openAssessmentInput').click();document.getElementById('assessmentComponent').value="+json.dumps(component)+";document.getElementById('assessmentComponent').onchange();document.querySelector('.assessment-score').value="+json.dumps(str(value))+";document.getElementById('saveAssessmentValues').click()")
        browser.wait("!document.getElementById('modalBackdrop').classList.contains('show')")
    input_score('UH1',90);input_score('ATS',82);input_score('ASS',88);input_score('UH2',60)
    assert sql(f"SELECT COUNT(*) FROM nilai_siswa n JOIN komponen_nilai k ON k.id=n.komponen_nilai_id WHERE n.siswa_id={pupil} AND k.mata_pelajaran_id={subject} AND n.dinilai_oleh={users[1]}")=='4'
    assert sql(f"SELECT COUNT(*) FROM nilai_siswa n JOIN komponen_nilai k ON k.id=n.komponen_nilai_id WHERE n.siswa_id={pupil} AND k.mata_pelajaran_id={other_subject}")=='0'
    credentials=json.dumps({'role':'walas','username':prefix+'_wali_kelas','password':password})
    browser.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(()=>authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")})).then(d=>finishLogin(d.user))")
    def open_ledger(cls,semester):
        browser.js("document.getElementById('semesterSelect').value="+json.dumps(semester)+";window.activeSemester="+json.dumps(semester)+";authRequest('api/bootstrap?kelas_id="+cls+"&semester="+semester+"').then(d=>{applyBootstrapData(d);window.page='grades';window.render()})")
        browser.wait("!!document.querySelector('#pageContent table tbody tr')" if cls==classes[0] else "!!document.querySelector('#pageContent table thead')")
    def camera_value():return browser.js("(()=>{const table=document.querySelector('#pageContent table'),index=Array.from(table.querySelectorAll('thead th')).findIndex(th=>th.textContent.trim()==='Tata Kamera & Cahaya');return table.querySelector('tbody tr')?.children[index]?.textContent.trim()})()")
    open_ledger(classes[0],'Ganjil');assert camera_value()=='81',camera_value()
    assert browser.js("ledgerTeacherAssessment('Tata Kamera dan Tata Cahaya')["+json.dumps(prefix)+"].UH1")==90
    open_ledger(classes[0],'Genap');assert camera_value()=='-',camera_value()
    open_ledger(classes[1],'Ganjil');assert browser.js("Object.keys(ledgerTeacherAssessment('Tata Kamera & Cahaya')).length")==0
    # Wali assessment and its parent-message preview must use the same subject and teacher.
    open_ledger(classes[0],'Ganjil')
    browser.js("delete window.teacherContacts['Tata Kamera dan Cahaya'];window.teacherContacts.PJOK={name:'Guru PJOK Fixture'};window.page='walasAssessment';window.render()")
    browser.wait("!!document.querySelector('[data-walas-grade-subject=\""+subject+"\"]')")
    browser.js("document.querySelector('[data-walas-grade-subject=\""+subject+"\"]').click()")
    assert browser.js("Array.from(document.querySelectorAll('#walasGradeTableWrap tbody tr')).map(row=>row.children[1].textContent.trim())")==['90','60','82','88']
    browser.js("document.querySelector('.gateway-grade-action[data-gateway-label=Orangtua]').click()")
    browser.wait("!!document.getElementById('gatewayGradeMessage')")
    preview=browser.js("document.getElementById('gatewayGradeMessage').value")
    assert 'Wahyu Lukman Hakim' in preview and 'Guru PJOK Fixture' not in preview and 'Ulangan Harian 2' in preview
    browser.js('closeModal();window.page=\'reports\';window.render()')
    browser.wait("!!document.querySelector('#reportDaya table tbody tr')")
    report=browser.js("Array.from(document.querySelectorAll('#reportDaya table tbody tr')).map(row=>Array.from(row.children).map(td=>td.textContent.trim())).find(cells=>academicSubjectKey(cells[1])===academicSubjectKey('Tata Kamera & Cahaya'))")
    assert report[4]=='81',report
    # Students and parents read the same saved components, scoped to their own child and semester.
    def login(role,username,secret):
        credentials=json.dumps({'role':role,'username':username,'password':secret})
        browser.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(()=>authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")})).then(d=>finishLogin(d.user))")
    def family_scores(semester):
        browser.js("window.activeSemester="+json.dumps(semester)+";window.page='studentGrades';window.render()")
        browser.wait("!!document.querySelector('[data-grade-subject=\"Tata Kamera & Cahaya\"]')")
        browser.js("document.querySelector('[data-grade-subject=\"Tata Kamera & Cahaya\"]').click()")
        return browser.js("Array.from(document.querySelectorAll('#studentGradeTableWrap tbody tr')).map(row=>row.children[1].textContent.trim())")
    for role,username,secret in [('student',prefix+'_siswa',password),('parent',prefix,prefix)]:
        login(role,username,secret)
        assert family_scores('Ganjil')==['90','60','—','—','—','—','82','88']
        browser.js("window.page='dashboard';window.render()")
        browser.wait("!!document.querySelector('.student-home')")
        assert browser.js("authRequest('api/siswa/dashboard?semester=Genap').then(r=>r.data.capaian_nilai.length)")==0
    login('parent',prefix+'_other',prefix+'_other')
    assert family_scores('Ganjil')==['—']*8
    print('PASS: Wali assessment and report, accurate Wahyu parent-message preview without sending, student/parent saved grades and semester/child isolation.')
    assert sql(f"SELECT COUNT(*) FROM pesan_komunikasi WHERE kelas_id IN ({','.join(classes)})")=='0'
    print('PASS: Wahyu-linked teacher input UH1/ATS/ASS stored under camera subject, label aliases never select PAI, wali Ledger camera report=81, class/semester isolation, no real credentials changed or WhatsApp sent.')
finally:
    if browser:
        try:browser.js("closeModal();localStorage.removeItem('aiwalas.teacher.subject');localStorage.setItem('aiwalas.semester','Ganjil');authRequest('api/auth/logout',{method:'POST',body:'{}'})")
        except Exception:pass
    if classes:
        ids=','.join(classes);sql(f"DELETE FROM pengguna WHERE siswa_id IN (SELECT id FROM siswa WHERE kelas_id IN ({ids}));DELETE FROM pesan_komunikasi WHERE kelas_id IN ({ids});DELETE FROM nilai_siswa WHERE komponen_nilai_id IN (SELECT id FROM komponen_nilai WHERE kelas_id IN ({ids}));DELETE FROM komponen_nilai WHERE kelas_id IN ({ids});DELETE FROM pengampu_mapel WHERE kelas_id IN ({ids});DELETE FROM siswa WHERE kelas_id IN ({ids});DELETE FROM kelas WHERE id IN ({ids});")
    if created_subject:sql('DELETE FROM mata_pelajaran WHERE id='+subject)
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teacher:sql('DELETE FROM guru WHERE id='+teacher)
