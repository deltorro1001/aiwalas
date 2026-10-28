"""Local API/browser checks for teaching assignments without timetable slots. Disposable fixtures only.
Run: AIWALAS_BROWSER_TEST=1 python3 tests/teacher-assignments-local.py
"""
import time,datetime,http.cookiejar,json,os,re,secrets,shutil,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP=shutil.which('php') or '/opt/homebrew/opt/php@8.3/bin/php';MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
prefix='animasi_'+secrets.token_hex(5);password=secrets.token_hex(16);hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
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
other_teacher=None
try:
    subject=sql("SELECT id FROM mata_pelajaran WHERE nama_mata_pelajaran='Animasi 3D' AND aktif=1")
    assert subject
    actual=sql("SELECT g.nama_lengkap FROM pengampu_mapel p JOIN guru g ON g.id=p.guru_id WHERE p.mata_pelajaran_id="+subject)
    assert 'Sumantoro Kasdhani, S.Kom., M.I.Kom.' in actual
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_wali','{hashed}','Wali Uji','wali_kelas');SELECT LAST_INSERT_ID();"))
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{prefix}','Ganjil',1);SELECT LAST_INSERT_ID();")
    for i in range(2):classes.append(sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}_{i}',11,'Test',{year},{users[0]});SELECT LAST_INSERT_ID();"))
    teacher=sql("INSERT INTO guru(nama_lengkap,nama_panggilan,jenis_kelamin,aktif) VALUES('Uji Guru Animasi','Animasi','L',1);SELECT LAST_INSERT_ID();")
    other_teacher=sql("INSERT INTO guru(nama_lengkap,nama_panggilan,jenis_kelamin,aktif) VALUES('Uji Guru Lain','Lain','L',1);SELECT LAST_INSERT_ID();")
    for i,t in enumerate([teacher,other_teacher]):users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,guru_id) VALUES('{prefix}_guru{i}','{hashed}','Guru Uji {i}','guru',{t});SELECT LAST_INSERT_ID();"))
    pupil=sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{prefix}','Siswa Animasi','L',{classes[0]},1);SELECT LAST_INSERT_ID();");pupils.append(pupil)
    other_subject=sql("SELECT id FROM mata_pelajaran WHERE aktif=1 AND id<>"+subject+" LIMIT 1")
    sql(f"INSERT INTO pengampu_mapel(guru_id,kelas_id,mata_pelajaran_id) VALUES({teacher},{classes[0]},{subject});INSERT INTO jadwal_kelas(kelas_id,guru_id,mata_pelajaran_id,hari,jam_mulai,jam_selesai) VALUES({classes[0]},{other_teacher},{other_subject},'Senin','08:00','09:00'),({classes[1]},{other_teacher},{other_subject},'Senin','08:00','09:00');")
    a=Client();a.call('api/auth/login',{'role':'teacher','username':prefix+'_guru0','password':password})
    b=Client();b.call('api/auth/login',{'role':'teacher','username':prefix+'_guru1','password':password})
    wali=Client();wali.call('api/auth/login',{'role':'walas','username':prefix+'_wali','password':password})
    boot=a.call('api/bootstrap');assert len(boot['classes'])==1 and str(boot['selectedClassId'])==classes[0]
    assert len(boot['teacherSubjects'])==1 and boot['teacherSubjects'][0]['nama_mata_pelajaran']=='Animasi 3D'
    assert all(row['nama_mata_pelajaran']!='Animasi 3D' for row in b.call('api/bootstrap')['teacherSubjects'])
    assert wali.call('api/bootstrap?kelas_id='+classes[0])['teacherContacts']['Animasi 3D']['name']=='Uji Guru Animasi'
    assert len(a.call('api/guru/siswa?kelas_id='+classes[0])['data'])==1
    assert a.call('api/guru/siswa?kelas_id='+classes[1])['data']==[]
    a.call('api/akademik?kelas_id='+classes[1],status=403)
    payload={'kelas_id':int(classes[0]),'mata_pelajaran_id':int(subject),'semester':'Ganjil','komponen':'UH1','values':{prefix:88}}
    a.call('api/akademik',payload)
    assert float(a.call('api/akademik?kelas_id='+classes[0])['data'][0]['nilai'])==88
    # Grade alerts are queued transactionally by both input endpoints, never sent in HTTP requests.
    def dispatch_count():return int(sql(f"SELECT COUNT(*) FROM student_grade_dispatches WHERE siswa_id={pupil}"))
    assert dispatch_count()==0
    payload['values'][prefix]=79.99
    a.call('api/akademik',payload);a.call('api/akademik',payload)
    assert dispatch_count()==1
    component=sql(f"SELECT id FROM komponen_nilai WHERE kelas_id={classes[0]} AND mata_pelajaran_id={subject} AND nama_komponen='UH1'")
    legacy={'siswa_id':int(pupil),'komponen_nilai_id':int(component),'semester':'Ganjil','nilai':79.99}
    wali.call('api/nilai',legacy);assert dispatch_count()==1
    legacy['nilai']=0;wali.call('api/nilai',legacy);assert dispatch_count()==2
    legacy['nilai']=79.999;wali.call('api/nilai',legacy);assert dispatch_count()==2
    assert sql(f"SELECT COUNT(*) FROM student_grade_dispatches WHERE siswa_id={pupil} AND status='pending'")=='0'
    payload['values'][prefix]=79.999;a.call('api/akademik',payload);assert dispatch_count()==2
    for index,comp in enumerate(['UH2','UH3','UH4','UH5','UH6','ATS','ASS']):
        other=dict(payload,komponen=comp,values={prefix:70})
        a.call('api/akademik',other);assert dispatch_count()==3+index
    other=dict(payload,semester='Genap',values={prefix:60});a.call('api/akademik',other)
    assert dispatch_count()==10
    assert sql(f"SELECT COUNT(*) FROM pesan_komunikasi WHERE kelas_id={classes[0]}")=='0'
    payload['values'][prefix]=88;a.call('api/akademik',payload)
    assert wali.call('api/pesan?kelas_id='+classes[0])['data']==[]
    message=sql(f"INSERT INTO pesan_komunikasi(siswa_id,kelas_id,pengirim,penerima,nomor_tujuan,isi_pesan,kategori,status_pengiriman,dibuat_oleh) VALUES({pupil},{classes[0]},'Fixture','Fixture','6281234567890','Test only','Nilai Siswa Otomatis','Disiapkan',{users[0]});SELECT LAST_INSERT_ID();")
    sql(f"UPDATE student_grade_dispatches SET message_id={message},result_note='Nomor WhatsApp siswa belum tersedia atau tidak valid.' WHERE siswa_id={pupil} AND status='pending' ORDER BY id LIMIT 1")
    history=wali.call('api/pesan?kelas_id='+classes[0]+'&status=pending')['data']
    assert len(history)==1 and history[0]['delivery_status']=='Pending' and 'Nomor WhatsApp siswa' in history[0]['delivery_note']
    sql(f"UPDATE pesan_komunikasi SET status_pengiriman='Terkirim' WHERE id={message}")
    assert wali.call('api/pesan?kelas_id='+classes[0]+'&status=sent')['data'][0]['delivery_status']=='Terkirim'
    sql(f"DELETE FROM pesan_komunikasi WHERE id={message}")
    print('PASS: Teacher/Wali grade-input hooks, both endpoints, all eight components, semester isolation, threshold rounding, zero, unchanged saves and superseded jobs; no HTTP-triggered sends.')
    b.call('api/akademik',payload,status=422)
    assignments=a.call('api/jurnal?kelas_id='+classes[0])['assignments'];assert len(assignments)==1 and 'nomor_hp' not in assignments[0]
    journal={'kelas_id':int(classes[0]),'mata_pelajaran_id':int(subject),'guru_id':int(teacher),'tanggal':'2026-10-28','materi':'Uji Animasi 3D','status':'Terbit'}
    a.call('api/jurnal',journal)
    b.call('api/jurnal',journal,status=403)
    assert not any(str(row['mata_pelajaran_id'])==subject for row in wali.call('api/jadwal?kelas_id='+classes[0])['data'])
    sql(f"INSERT INTO jadwal_kelas(kelas_id,guru_id,mata_pelajaran_id,hari,jam_mulai,jam_selesai) VALUES({classes[0]},{teacher},{subject},'Selasa','08:00','09:00');")
    assert len(a.call('api/bootstrap')['teacherSubjects'])==1
    sql(f"DELETE FROM jadwal_kelas WHERE guru_id={teacher} AND kelas_id={classes[0]};")
    print('PASS: Animasi 3D teacher assignment, explicit/scheduled deduplication, scoped subjects/classes, grades, journals, teacher contacts and no invented timetable slots.')
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
        c.wait("typeof authRequest==='function' && !!window.AIWalasPages?.assessment")
        credentials=json.dumps({'role':'teacher','username':prefix+'_guru0','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(d=>finishLogin(d.user))")
        c.wait("!!document.querySelector('#teacherSubjectSelect option[value=\\\"Animasi 3D\\\"]')")
        assert c.js("document.getElementById('teacherSubjectSelect').value")=='Animasi 3D'
        c.js("window.page='assessment';window.render()")
        c.wait("!!document.querySelector('.assessment-table') && document.querySelector('.assessment-table').textContent.includes('88')")
        c.call('Page.reload')
        c.wait("window.currentRole==='teacher' && document.getElementById('teacherSubjectSelect')?.value==='Animasi 3D'")
        c.js('logout()')
        print('PASS: Animasi 3D appears/selects in the teacher dropdown, loads assessment data and persists after reload.')
        sql(f"UPDATE siswa SET nomor_hp='081234567890',nomor_hp_orang_tua='089876543210' WHERE id={pupil}")
        c.wait('window.currentRole===null')
        c.js('window.activeClassId='+classes[0])
        credentials=json.dumps({'role':'walas','username':prefix+'_wali','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(d=>finishLogin(d.user))")
        c.js("authRequest('api/bootstrap?kelas_id="+classes[0]+"').then(applyBootstrapData)")
        c.wait("window.currentRole==='walas' && window.students?.[0]?.[1]==="+json.dumps(prefix))
        c.js("window.page='walasAssessment';window.render()")
        c.wait("!!document.querySelector('[data-walas-grade-subject=\""+subject+"\"]')")
        c.js("document.querySelector('[data-walas-grade-subject=\""+subject+"\"]').click()")
        c.wait("!!document.querySelector('.gateway-grade-action[data-gateway-label=Orangtua]')")
        assert c.js("document.querySelectorAll('.gateway-grade-action[data-gateway-label=Siswa]').length")==0
        assert 'Notifikasi siswa otomatis' in c.js("document.getElementById('walasGradeTableWrap').textContent")
        c.js("document.querySelector('.gateway-grade-action[data-gateway-label=Orangtua]').click()")
        c.wait("!!document.getElementById('confirmGatewayGrade')")
        assert 'Ulangan Harian 2' in c.js("document.getElementById('gatewayGradeMessage').value")
        c.js('closeModal()');c.js('logout()')
        assert sql(f"SELECT COUNT(*) FROM pesan_komunikasi WHERE kelas_id={classes[0]}")=='0'
        print('PASS: Wali sees automatic student notice, only parent has a manual gateway button and confirmation modal; opening the modal sends nothing.')

finally:
    if classes:
        ids=','.join(classes)
        sql('DELETE FROM pesan_komunikasi WHERE kelas_id IN ('+ids+');DELETE FROM jurnal_kelas WHERE kelas_id IN ('+ids+');DELETE FROM nilai_siswa WHERE komponen_nilai_id IN (SELECT id FROM komponen_nilai WHERE kelas_id IN ('+ids+'));DELETE FROM komponen_nilai WHERE kelas_id IN ('+ids+');DELETE FROM jadwal_kelas WHERE kelas_id IN ('+ids+');DELETE FROM pengampu_mapel WHERE kelas_id IN ('+ids+');DELETE FROM siswa WHERE kelas_id IN ('+ids+');DELETE FROM kelas WHERE id IN ('+ids+');')
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teacher:sql('DELETE FROM guru WHERE id='+teacher)
    if other_teacher:sql('DELETE FROM guru WHERE id='+other_teacher)
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
