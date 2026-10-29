"""Disposable local fixtures: template, task entry/import, shared views, legacy IDs and isolation.
No gateway requests or real credentials changed.
"""
import http.cookiejar,json,re,secrets,subprocess,urllib.request,urllib.error
from pathlib import Path
from browser_client import CDP
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP='/opt/homebrew/opt/php@8.3/bin/php';MYSQL='/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
tag='tasks_'+secrets.token_hex(5);password=secrets.token_hex(16)
hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
class Client:
    def __init__(self):
        self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token=re.search(r"name=['\"]csrf-token['\"] content=['\"]([^'\"]+)",self.op.open(BASE).read().decode()).group(1)
    def call(self,path,data=None,status=200):
        req=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),headers={'Content-Type':'application/json','X-CSRF-TOKEN':self.token})
        try:r=self.op.open(req,timeout=15)
        except urllib.error.HTTPError as error:r=error
        result=json.load(r);self.token=result.get('csrfHash',self.token);assert r.code==status,(path,r.code,result);return result
users=[];classes=[];pupils=[];year=teacher=None;c=None
try:
    teacher=sql(f"INSERT INTO guru(nama_lengkap,nama_panggilan,jenis_kelamin,aktif) VALUES('{tag} Guru','Uji','L',1);SELECT LAST_INSERT_ID();")
    for role in ['wali_kelas','guru']:
        users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,guru_id) VALUES('{tag}_{role}','{hashed}','Fixture {role}','{role}',{teacher if role=='guru' else 'NULL'});SELECT LAST_INSERT_ID();"))
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{tag}','Ganjil',1);SELECT LAST_INSERT_ID();")
    subject=sql("SELECT id FROM mata_pelajaran WHERE nama_mata_pelajaran='PAI' AND aktif=1 LIMIT 1")
    for i in range(2):
        classes.append(sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{tag}_{i}',11,'Test',{year},{users[0]});SELECT LAST_INSERT_ID();"))
    sql(f"INSERT INTO pengampu_mapel(guru_id,kelas_id,mata_pelajaran_id) VALUES({teacher},{classes[0]},{subject})")
    for i in range(2):
        pupils.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{tag}_{i}','Siswa Tugas {i}','L',{classes[0]},1);SELECT LAST_INSERT_ID();"))
    for role,nis in [('siswa',tag+'_0'),('sekretaris',tag+'_1')]:
        users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{tag}_{role}','{hashed}','Fixture {role}','{role}','{nis}');SELECT LAST_INSERT_ID();"))
    legacy=sql(f"INSERT INTO komponen_nilai(kelas_id,mata_pelajaran_id,nama_komponen,jenis_komponen,urutan) VALUES({classes[0]},{subject},'Tugas 1','Tugas',5);SELECT LAST_INSERT_ID();")
    sql(f"INSERT INTO nilai_siswa(siswa_id,komponen_nilai_id,semester,nilai,dinilai_oleh) VALUES({pupils[0]},{legacy},'Genap',55,{users[0]})")
    teacher_api=Client();teacher_api.call('api/auth/login',{'role':'teacher','username':tag+'_guru','password':password})
    payload={'kelas_id':int(classes[0]),'mata_pelajaran_id':int(subject),'semester':'Ganjil','values':{tag+'_0':91}}
    for component,value in [('UH1',90),('ATS',82),('ASS',88),('Tugas1',91),('Tugas 2',0),('Tugas3',79),('Tugas4',80)]:
        teacher_api.call('api/akademik',dict(payload,komponen=component,values={tag+'_0':value}))
    assert sql(f"SELECT COUNT(*) FROM komponen_nilai WHERE kelas_id={classes[0]} AND REPLACE(nama_komponen,' ','')='Tugas1'")=='1'
    assert sql(f"SELECT nilai FROM nilai_siswa WHERE siswa_id={pupils[0]} AND komponen_nilai_id={legacy} AND semester='Genap'")=='55.00'
    for invalid in ['Tugas0','Tugas7','Tugas06','Tugas','Task1']:
        teacher_api.call('api/akademik',dict(payload,komponen=invalid),status=422)
    teacher_api.call('api/akademik',dict(payload,kelas_id=int(classes[1]),komponen='Tugas1'),status=403)
    c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
    c.wait("typeof academicGradeSummary==='function' && !!window.AIWalasPages?.assessment")
    def login(role,username,secret=password):
        credentials=json.dumps({'role':role,'username':username,'password':secret})
        c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(()=>authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")})).then(d=>finishLogin(d.user)).then(()=>authRequest('api/bootstrap?kelas_id="+classes[0]+"&semester=Ganjil')).then(d=>{window.activeSemester='Ganjil';applyBootstrapData(d);applyRoleAccess()})")
        c.wait("window.currentRole==="+json.dumps({'teacher':'teacher','walas':'walas','assistant':'assistant','student':'student','parent':'student'}[role])+" && window.activeClassId==="+classes[0])
    def open_page(page,ready):c.js("window.page="+json.dumps(page)+";window.render()");c.wait(ready)
    login('teacher',tag+'_guru');open_page('assessment',"!!document.getElementById('openAssessmentInput')")
    c.js("window.__taskTemplateBlob=null;window.__taskOriginalBlobURL=URL.createObjectURL;URL.createObjectURL=function(blob){window.__taskTemplateBlob=blob;return window.__taskOriginalBlobURL(blob)};document.getElementById('exportAssessmentTemplate').click()")
    headers=c.js("window.__taskTemplateBlob.arrayBuffer().then(b=>{const w=XLSX.read(b,{type:'array'});return XLSX.utils.sheet_to_json(w.Sheets[w.SheetNames[0]],{header:1})[0]})")
    assert headers==['NIS','Nama Siswa']+['UH'+str(i) for i in range(1,7)]+['Tugas '+str(i) for i in range(1,7)]+['ATS','ASS'],headers
    c.js("URL.createObjectURL=window.__taskOriginalBlobURL;document.getElementById('openAssessmentInput').click()")
    assert c.js("Array.from(document.querySelectorAll('#assessmentComponent option')).filter(o=>o.textContent.startsWith('Tugas ')).map(o=>o.textContent)")==['Tugas '+str(i) for i in range(1,7)]
    c.js("document.getElementById('assessmentComponent').value='Tugas5';document.getElementById('assessmentComponent').onchange();document.querySelector('.assessment-score').value='95';document.getElementById('saveAssessmentValues').click()")
    c.wait("!document.getElementById('modalBackdrop').classList.contains('show')")
    c.js("document.getElementById('openAssessmentInput').click();document.getElementById('assessmentComponent').value='Tugas6';document.getElementById('assessmentComponent').onchange()")
    import_rows=[['NIS','Nama Siswa','Tugas 6'],[tag+'_1','Siswa Tugas 1',77],[tag+'_0','Siswa Tugas 0',88],['99999999','Unknown',100]]
    c.js("(()=>{const w=XLSX.utils.book_new();XLSX.utils.book_append_sheet(w,XLSX.utils.aoa_to_sheet("+json.dumps(import_rows)+"),'Nilai');const file=new File([XLSX.write(w,{type:'array',bookType:'xlsx'})],'tugas6.xlsx'),dt=new DataTransfer();dt.items.add(file);const input=document.getElementById('assessmentFile');input.files=dt.files;input.dispatchEvent(new Event('change'))})()")
    c.wait("Array.from(document.querySelectorAll('.assessment-score')).map(i=>i.value).join(',')==='88,77'")
    c.js("document.getElementById('saveAssessmentValues').click()");c.wait("!document.getElementById('modalBackdrop').classList.contains('show')")
    assert c.js("Array.from(document.querySelectorAll('.assessment-table th')).map(th=>th.textContent).filter(s=>/^Tugas [1-6]$/.test(s)).length")==6
    assert c.js("getComputedStyle(Array.from(document.querySelectorAll('.assessment-table tbody tr:first-child td')).find(td=>td.textContent==='0')).color")=='rgb(220, 38, 38)'
    assert c.js("academicGradeSummary({UH1:80,UH2:90,UH3:100,Tugas1:60,Tugas2:70}).average")==80
    assert c.js("academicGradeSummary({UH1:0,Tugas1:80,Tugas2:null,Tugas3:''}).average")==40
    assert c.js("(()=>{const table=document.querySelector('.assessment-table'),i=Array.from(table.querySelectorAll('thead th')).findIndex(th=>th.textContent==='Rerata UH');return table.querySelector('tbody tr').children[i].textContent})()")=='75'
    expected=[91,0,79,80,95,88]
    assert c.js("Array.from(document.querySelectorAll('.assessment-table tbody tr:first-child td')).map(td=>td.textContent).includes('0')")
    def family_tasks():
        open_page('studentGrades',"!!document.querySelector('[data-grade-subject=PAI]')")
        return c.js("Array.from(document.querySelectorAll('#studentGradeTableWrap tbody tr')).filter(r=>/^Tugas [1-6]$/.test(r.children[0].textContent)).map(r=>r.children[1].textContent.trim())")
    for role,username,secret in [('student',tag+'_siswa',password),('parent',tag+'_0',tag+'_0')]:
        login(role,username,secret);assert family_tasks()==list(map(str,expected))
        assert c.js("getComputedStyle(Array.from(document.querySelectorAll('#studentGradeTableWrap tbody tr')).find(r=>r.children[0].textContent==='Tugas 2').children[1]).color")=='rgb(220, 38, 38)'
        assert c.js("authRequest('api/siswa/dashboard?semester=Genap').then(r=>r.data.capaian_nilai.filter(v=>academicComponentKey(v.nama_komponen)==='Tugas1').map(v=>Number(v.nilai)))")==[55]
    login('parent',tag+'_1',tag+'_1');assert family_tasks()==['—']*5+['77']
    login('walas',tag+'_wali_kelas');open_page('teachers',"!!document.querySelector('.teacher-directory tbody tr')")
    assert c.js("document.querySelector('.teacher-directory tbody tr').children[3].textContent.trim()")=='Rata-rata 77'
    c.js("document.querySelector('[data-teacher-score]').click()")
    assert c.js("Array.from(document.querySelectorAll('.score-summary-table th')).filter(th=>/^Tugas [1-6]$/.test(th.textContent)).length")==6
    c.js('closeModal()');open_page('walasAssessment',"!!document.querySelector('#walasGradeTableWrap tbody tr')")
    assert c.js("Array.from(document.querySelectorAll('#walasGradeTableWrap tbody tr')).filter(r=>/^Tugas [1-6]$/.test(r.children[0].textContent)).map(r=>r.children[1].textContent.trim())")==list(map(str,expected))
    open_page('grades',"!!document.querySelector('#pageContent table tbody tr')")
    assert c.js("ledgerReportValue(ledgerTeacherAssessment('PAI')["+json.dumps(tag+'_0')+"])")==81
    open_page('reports',"!!document.querySelector('#reportDaya table tbody tr')")
    assert c.js("Array.from(document.querySelectorAll('#reportDaya table tbody tr')).find(r=>r.children[1].textContent.includes('Pendidikan Agama')).children[2].textContent.trim()")=='81'
    login('assistant',tag+'_sekretaris')
    assert c.js("authRequest('api/akademik?kelas_id="+classes[0]+"').then(()=>false).catch(()=>true)")
    assert sql(f"SELECT COUNT(*) FROM pesan_komunikasi WHERE kelas_id={classes[0]}")=='0'
    print('PASS: 14-component Excel template, Tugas 1–6 manual entry/import by NIS, zero grades, shared teacher/wali/student/parent views and existing secretary permissions, legacy component reuse, semester/child/class isolation, combined UH+Tugas average and consistent Ledger report; no WhatsApp messages sent.')
finally:
    if c:
        try:c.js("closeModal();authRequest('api/auth/logout',{method:'POST',body:'{}'})")
        except Exception:pass
    if classes:
        ids=','.join(classes)
        sql(f"DELETE FROM pengguna WHERE siswa_id IN (SELECT id FROM siswa WHERE kelas_id IN ({ids}));DELETE FROM nilai_siswa WHERE komponen_nilai_id IN (SELECT id FROM komponen_nilai WHERE kelas_id IN ({ids}));DELETE FROM komponen_nilai WHERE kelas_id IN ({ids});DELETE FROM pengampu_mapel WHERE kelas_id IN ({ids});DELETE FROM siswa WHERE kelas_id IN ({ids});DELETE FROM kelas WHERE id IN ({ids});")
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teacher:sql('DELETE FROM guru WHERE id='+teacher)
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
