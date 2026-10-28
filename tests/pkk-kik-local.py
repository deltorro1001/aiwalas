"""Local API/browser checks for PKK to KIK grade merging. Disposable fixtures only.
Run: AIWALAS_BROWSER_TEST=1 python3 tests/pkk-kik-local.py
"""
import time,datetime,http.cookiejar,json,os,re,secrets,shutil,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP=shutil.which('php') or '/opt/homebrew/opt/php@8.3/bin/php';MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
prefix='kik_'+secrets.token_hex(5);password=secrets.token_hex(16);hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
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
legacy=None
try:
    canonical=sql("SELECT id FROM mata_pelajaran WHERE nama_mata_pelajaran='KIK'")
    assert canonical and not sql("SELECT id FROM mata_pelajaran WHERE nama_mata_pelajaran='PKK'")
    legacy=sql("INSERT INTO mata_pelajaran(nama_mata_pelajaran,aktif) VALUES('PKK',1);SELECT LAST_INSERT_ID();")
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_wali','{hashed}','Wali KIK','wali_kelas');SELECT LAST_INSERT_ID();"))
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{prefix}','Ganjil',1);SELECT LAST_INSERT_ID();")
    classes.append(sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}',11,'Test',{year},{users[0]});SELECT LAST_INSERT_ID();"))
    teacher=sql("INSERT INTO guru(nama_lengkap,nama_panggilan,mata_pelajaran,aktif) VALUES('Uji Guru PKK','Uji','PKK',1);SELECT LAST_INSERT_ID();")
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,guru_id) VALUES('{prefix}_guru','{hashed}','Uji Guru PKK','guru',{teacher});SELECT LAST_INSERT_ID();"))
    pupils.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{prefix}','Siswa KIK','L',{classes[0]},1);SELECT LAST_INSERT_ID();"))
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{prefix}_student','{hashed}','Siswa KIK','siswa','{prefix}');SELECT LAST_INSERT_ID();"))
    sql(f"INSERT INTO jadwal_kelas(kelas_id,mata_pelajaran_id,guru_id,hari,jam_mulai,jam_selesai) VALUES({classes[0]},{legacy},{teacher},'Senin','08:00','09:00');INSERT INTO pengampu_mapel(guru_id,kelas_id,mata_pelajaran_id) VALUES({teacher},{classes[0]},{canonical}),({teacher},{classes[0]},{legacy});")
    a=Client();a.call('api/auth/login',{'role':'teacher','username':prefix+'_guru','password':password})
    wali=Client();wali.call('api/auth/login',{'role':'walas','username':prefix+'_wali','password':password})
    student=Client();student.call('api/auth/login',{'role':'student','username':prefix+'_student','password':password})
    parent=Client();parent.call('api/auth/login',{'role':'parent','username':prefix,'password':prefix})
    payload={'kelas_id':int(classes[0]),'mata_pelajaran_id':int(legacy),'semester':'Ganjil','komponen':'UH1','values':{prefix:93}}
    a.call('api/akademik',payload);a.call('api/akademik',dict(payload,semester='Genap',values={prefix:0}));a.call('api/akademik',dict(payload,komponen='ATS',values={prefix:84}))
    component=sql(f"INSERT INTO komponen_nilai(kelas_id,mata_pelajaran_id,nama_komponen,jenis_komponen,urutan) VALUES({classes[0]},{canonical},'UH 1','UH',1);SELECT LAST_INSERT_ID();")
    sql(f"INSERT INTO nilai_siswa(siswa_id,komponen_nilai_id,semester,nilai,dinilai_oleh,dibuat_pada,diperbarui_pada) VALUES({pupils[0]},{component},'Ganjil',80,{users[0]},'2026-10-01','2026-10-20'),({pupils[0]},{component},'Genap',75,{users[0]},'2026-10-01','2026-10-30');UPDATE nilai_siswa n JOIN komponen_nilai k ON k.id=n.komponen_nilai_id SET n.dibuat_pada='2026-10-01',n.diperbarui_pada='2026-10-28' WHERE k.mata_pelajaran_id={legacy} AND k.nama_komponen='UH1';UPDATE nilai_siswa n JOIN komponen_nilai k ON k.id=n.komponen_nilai_id SET n.diperbarui_pada=NULL WHERE k.mata_pelajaran_id={legacy} AND k.nama_komponen='ATS';")
    a.call('api/jurnal',{'kelas_id':int(classes[0]),'mata_pelajaran_id':int(legacy),'guru_id':int(teacher),'tanggal':'2026-10-28','materi':'Jurnal PKK lama','status':'Terbit'})
    sql(f"INSERT INTO remedial_tugas(kelas_id,mata_pelajaran_id,judul,dibuat_oleh) VALUES({classes[0]},{legacy},'Tugas PKK lama',{users[0]});")
    boot="define('ENVIRONMENT','development');define('FCPATH',getcwd().'/public/');require 'app/Config/Paths.php';$p=new Config\\Paths();require $p->systemDirectory.'/Boot.php';CodeIgniter\\Boot::bootConsole($p);App\\Libraries\\MergePkkIntoKik::apply();"
    subprocess.check_call([PHP,'-r',boot],cwd=ROOT,stdout=subprocess.DEVNULL)
    assert not sql("SELECT id FROM mata_pelajaran WHERE nama_mata_pelajaran='PKK'")
    assert sql(f"SELECT COUNT(*) FROM komponen_nilai WHERE kelas_id={classes[0]} AND nama_komponen='UH1' AND mata_pelajaran_id={canonical}")=='1'
    assert sql(f"SELECT nilai FROM nilai_siswa WHERE siswa_id={pupils[0]} AND komponen_nilai_id={component} AND semester='Ganjil'")=='93.00'
    assert sql(f"SELECT nilai FROM nilai_siswa WHERE siswa_id={pupils[0]} AND komponen_nilai_id={component} AND semester='Genap'")=='75.00'
    assert sql(f"SELECT COUNT(*) FROM arsip_penggabungan_pkk WHERE JSON_UNQUOTE(JSON_EXTRACT(data_asli,'$.nilai.siswa_id'))={pupils[0]}")=='4'
    assert sql(f"SELECT n.diperbarui_pada IS NULL FROM nilai_siswa n JOIN komponen_nilai k ON k.id=n.komponen_nilai_id WHERE k.kelas_id={classes[0]} AND k.nama_komponen='ATS'")=='1'
    assert sql(f"SELECT COUNT(*) FROM pengampu_mapel WHERE kelas_id={classes[0]}")=='1'
    assert sql(f"SELECT mata_pelajaran_id FROM jadwal_kelas WHERE kelas_id={classes[0]}")==canonical
    assert sql(f"SELECT mata_pelajaran_id FROM remedial_tugas WHERE kelas_id={classes[0]}")==canonical
    assert sql(f"SELECT mata_pelajaran FROM guru WHERE id={teacher}")=='KIK'
    assert a.call('api/bootstrap')['teacherSubjects'][0]['nama_mata_pelajaran']=='KIK'
    assert a.call('api/jurnal?kelas_id='+classes[0])['assignments'][0]['nama_mata_pelajaran']=='KIK'
    for client in [student,parent]:assert all(row['nama_mata_pelajaran']=='KIK' for row in client.call('api/siswa/dashboard')['data']['capaian_nilai'])
    wali.call('api/mata-pelajaran',{'nama_mata_pelajaran':'PKK'},status=409)
    wali.call('api/jadwal/import',{'kelas_id':int(classes[0]),'rows':[{'hari':'Selasa','jam_mulai':'09:00','jam_selesai':'10:00','mata_pelajaran':' PKK ','guru':'Uji Guru PKK'}]})
    assert not sql("SELECT id FROM mata_pelajaran WHERE nama_mata_pelajaran='PKK'")
    assert sql(f"SELECT mata_pelajaran_id FROM jadwal_kelas WHERE kelas_id={classes[0]}")==canonical
    a.call('api/akademik',dict(payload,mata_pelajaran_id=int(canonical),komponen='UH2',values={prefix:97}))
    subprocess.check_call([PHP,'-r',boot],cwd=ROOT,stdout=subprocess.DEVNULL)
    assert len(a.call('api/akademik?kelas_id='+classes[0])['data'])==3
    print('PASS: PKK/KIK grade/component merge, newest score with conflict archive, semesters and zero preserved, timestamps preserved, assignment deduplication, schedule/journal/remedial references, names and repeat safety.')
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
        c.wait("typeof authRequest==='function' && !!window.AIWalasPages?.assessment")
        credentials=json.dumps({'role':'teacher','username':prefix+'_guru','password':password})
        c.js("localStorage.setItem('aiwalas.teacher.subject','PKK');authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(d=>finishLogin(d.user))")
        c.wait("document.getElementById('teacherSubjectSelect')?.value==='KIK'")
        assert 'PKK' not in c.js("Array.from(document.getElementById('teacherSubjectSelect').options).map(o=>o.textContent)")
        c.js("window.page='assessment';window.render()")
        c.wait("document.querySelector('#pageContent h1')?.textContent==='Nilai KIK'")
        c.js("document.getElementById('openAssessmentInput').click();document.getElementById('assessmentComponent').value='UH2';document.getElementById('assessmentComponent').onchange();document.querySelector('.assessment-score').value='97';document.getElementById('saveAssessmentValues').click()")
        c.wait("!document.getElementById('modalBackdrop').classList.contains('show')")
        credentials=json.dumps({'role':'walas','username':prefix+'_wali','password':password})
        c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(()=>authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")})).then(d=>finishLogin(d.user)).then(()=>authRequest('api/bootstrap?kelas_id="+classes[0]+"')).then(d=>{applyBootstrapData(d);window.page='grades';window.render()})")
        c.wait("!!document.querySelector('#pageContent table tbody tr')")
        ledger=c.js("(()=>{const table=document.querySelector('#pageContent table'),head=Array.from(table.querySelectorAll('thead th')).map(th=>th.textContent.trim()),index=head.indexOf('KIK');return {head:head,value:table.querySelector('tbody tr').children[index]?.textContent.trim()}})()")
        assert ledger['value']=='92' and 'PKK' not in ledger['head'],ledger
        c.js('logout()')
        print('PASS: Teacher dropdown/assessment KIK, stale PKK selection migrated, teacher input visible as 92 under KIK in wali Ledger Nilai.')
finally:
    if classes:
        ids=','.join(classes)
        if pupils:sql('DELETE FROM arsip_penggabungan_pkk WHERE JSON_UNQUOTE(JSON_EXTRACT(data_asli,\'$.nilai.siswa_id\')) IN ('+','.join(pupils)+')')
        sql('DELETE FROM jurnal_kelas WHERE kelas_id IN ('+ids+');DELETE FROM remedial_tugas WHERE kelas_id IN ('+ids+');DELETE FROM nilai_siswa WHERE komponen_nilai_id IN (SELECT id FROM komponen_nilai WHERE kelas_id IN ('+ids+'));DELETE FROM komponen_nilai WHERE kelas_id IN ('+ids+');DELETE FROM jadwal_kelas WHERE kelas_id IN ('+ids+');DELETE FROM pengampu_mapel WHERE kelas_id IN ('+ids+');')
        if pupils:sql('DELETE FROM pengguna WHERE siswa_id IN ('+','.join(pupils)+') OR nis IN (SELECT nis FROM siswa WHERE id IN ('+','.join(pupils)+'));')
        sql('DELETE FROM siswa WHERE kelas_id IN ('+ids+');DELETE FROM kelas WHERE id IN ('+ids+');')
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teacher:sql('DELETE FROM guru WHERE id='+teacher)
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
    if legacy:sql('DELETE FROM mata_pelajaran WHERE id='+legacy)
