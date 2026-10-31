"""Historical attendance question API and deterministic question answers. Disposable fixtures; no gateway calls."""
import datetime,http.cookiejar,json,os,re,secrets,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP='/opt/homebrew/opt/php@8.3/bin/php';MYSQL='/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
tag='aq_'+secrets.token_hex(5);password=secrets.token_hex(16)
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
def multipart(client,fields,file=None,status=201,endpoint='api/pengajuan-sakit'):
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
    today=datetime.datetime.now(ZoneInfo('Asia/Jakarta')).date();end=str(today)
    sql(f"UPDATE siswa SET nama_lengkap='Kanaya Safa Aulia' WHERE id={pupils[0]};UPDATE siswa SET nama_lengkap='Khaira Aurelia' WHERE id={pupils[1]};")
    for i,name in enumerate(['Khaira Aulia','Timothy Santoso','Nonaktif Uji','Siswa Asing'],2):
        pupils.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{tag}_{i}','{name}','L',{classes[1] if i==5 else classes[0]},{0 if i==4 else 1});SELECT LAST_INSERT_ID();"))
    records=[(0,'2026-07-12','Sakit','Ganjil'),(0,'2026-07-13','Sakit','Ganjil'),(0,'2026-07-14','Sakit','Ganjil'),(0,'2026-07-15','Izin','Ganjil'),(0,'2026-08-03','Izin','Ganjil'),(0,'2026-08-04','Terlambat','Ganjil'),(0,'2026-09-01','Terlambat','Ganjil'),(0,'2026-10-01','Terlambat','Ganjil'),(0,'2026-08-17','Sakit','Ganjil'),(0,'2026-08-27','Terlambat','Ganjil'),(0,'2026-08-05','Terlambat','Genap'),(0,'2026-07-13','Sakit','Genap'),(1,'2026-07-13','Izin','Ganjil'),(1,'2026-08-03','Izin','Ganjil'),(1,'2026-09-01','Izin','Ganjil'),(1,'2026-10-01','Terlambat','Ganjil'),(2,'2026-07-16','Sakit','Ganjil'),(2,'2026-08-10','Izin','Ganjil'),(4,'2026-08-04','Terlambat','Ganjil'),(5,'2026-08-04','Terlambat','Ganjil'),(0,end,'Hadir','Ganjil'),(0,str(today+datetime.timedelta(days=1)),'Sakit','Ganjil')]
    for i,day,status,semester in records:
        sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,semester,waktu_scan,keterangan,dicatat_oleh) VALUES({pupils[i]},{classes[1] if i==5 else classes[0]},'{day}','{status}','{semester}',{'NULL' if status!='Terlambat' else chr(39)+'06:45:00'+chr(39)},'Catatan <img src=x onerror=alert(1)>',{users[0]})")
    pdf=b'%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF'
    for day,endpoint_name in [('2026-07-13','api/pengajuan-sakit'),('2026-08-03','api/pengajuan-izin')]:
        multipart(parent,{'tanggal_mulai':day,'tanggal_selesai':day,'jumlah_hari':1,'jenis_izin':'Keperluan keluarga','keterangan':'Surat uji'},('surat.pdf','application/pdf',pdf),endpoint=endpoint_name)
    endpoint='api/absensi/tanya?kelas_id='+classes[0]
    result=wali.call(endpoint)
    assert result['periode']['mulai']=='2026-07-13' and result['periode']['sampai']==end and result['periode']['zona']=='Asia/Jakarta'
    assert len(result['siswa'])==4 and {s['nama'] for s in result['siswa']}=={'Kanaya Safa Aulia','Khaira Aurelia','Khaira Aulia','Timothy Santoso'}
    assert [result['total'][key] for key in ['Sakit','Izin','Terlambat']]==[3,6,4],result['total']
    assert result['kelompok']['Sakit']['jumlah_siswa']==2 and result['kelompok']['Izin']['jumlah_siswa']==3 and result['kelompok']['Terlambat']['jumlah_siswa']==2
    kanaya=next(s for s in result['siswa'] if s['nama']=='Kanaya Safa Aulia')
    assert [kanaya['jumlah'][key] for key in ['Sakit','Izin','Terlambat']]==[2,2,3]
    assert kanaya['bulanan']['2026-08']['Terlambat']==1
    history={r['tanggal']:r for r in kanaya['riwayat']}
    assert history['2026-07-13']['keterangan_lampiran']=='(surat sakit terlampir)'
    assert history['2026-07-14']['keterangan_lampiran']=='' and history['2026-07-14']['surat_terlampir'] is False
    assert history['2026-08-03']['keterangan_lampiran']=='(surat izin terlampir)'
    assert history['2026-07-15']['keterangan_lampiran']==''

    assert all('2026-07-13'<=row['tanggal']<=end for s in result['siswa'] for row in s['riwayat'])
    assert kanaya['jumlah']['Libur']==1 and kanaya['jumlah']['PJJ']==1
    assert not any(s['nama'] in ['Nonaktif Uji','Siswa Asing'] for s in result['siswa'])
    august=wali.call(f'api/absensi/rekap-kelas?kelas_id={classes[0]}&tahun=2026&semester=Ganjil')['bulanan'][7]
    assert august['sakit']==sum(s['bulanan'].get('2026-08',{}).get('Sakit',0) for s in result['siswa'])
    assert august['izin']==sum(s['bulanan'].get('2026-08',{}).get('Izin',0) for s in result['siswa'])
    assert august['terlambat']==sum(s['bulanan'].get('2026-08',{}).get('Terlambat',0) for s in result['siswa'])
    for client in [secretary,guru,student,parent,guest]:client.call(endpoint,status=403)
    outsider.call(endpoint,status=403);Client().call(endpoint,status=401)
    wali.call('api/absensi/tanya?kelas_id[]=1',status=422)
    print('PASS: Fixed inclusive Jul 13–today WIB range, per-student/status/month/date grouping, monthly recap agreement, wrong-semester duplicate/future/inactive/foreign exclusion and Wali-only class ownership.')
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
        c.wait("!!window.AIWalasAttendanceQuestions && typeof authRequest==='function'");c.events.clear()
        c.js('window.__attendanceFacts='+json.dumps(result))
        def analyze(q):return c.js('AIWalasAttendanceQuestions.analyze('+json.dumps(q)+',window.__attendanceFacts)')
        answer=analyze('Berapa kali Kanaya terlambat?');assert len(answer['rows'])==1 and answer['rows'][0]['jumlah']['Terlambat']==3
        assert analyze('Siapa saja yang izin lebih dari 2 kali?')['rows'][0]['nama']=='Khaira Aurelia'
        assert len(analyze('Siapa saja yang izin minimal 2 kali?')['rows'])==2
        assert len(analyze('Siapa saja yang izin >= 2 kali?')['rows'])==2
        assert analyze('Rekap izin Kanaya per bulan')['bulanan'] is True
        assert c.js("AIWalasAttendanceQuestions.isQuestion('Berapa izinnya Kanaya?')") is True
        assert analyze('Siapa paling sering terlambat?')['rows'][0]['nama']=='Kanaya Safa Aulia'
        assert analyze('Berapa kali Khaira izin?')['message'].startswith('Nama belum spesifik')
        assert analyze('Berapa kali Khaira Aurelia izin?')['rows'][0]['jumlah']['Izin']==3
        assert analyze('Berapa sakit NIS '+tag+'_0')['rows'][0]['jumlah']['Sakit']==2
        assert len(analyze('Rekap sakit izin keterlambatan seluruh siswa')['rows'])==4
        assert len(analyze('Siapa belum pernah terlambat?')['rows'])==2
        assert analyze('Kapan Kanaya izin?')['detail'] is True
        assert analyze('Jumlah sakit Kanaya bulan Agustus 2026')['rows'][0]['jumlah']['Sakit']==0
        assert analyze('Jumlah sakit Kanaya bulan Juli 2026')['rows'][0]['jumlah']['Sakit']==2
        assert analyze('Jumlah sakit Kanaya bulan Juli 2026')['periode']['mulai']=='2026-07-13'
        assert 'belum dikenali' in analyze('Berapa sakit Budi?')['message']
        assert analyze('Urutkan berdasarkan jumlah keterlambatan')['rows'][0]['nama']=='Kanaya Safa Aulia'
        assert len(analyze('Berapa izin Kanaya dan Khaira Aurelia?')['rows'])==2
        assert analyze('Rekap absensi kelas '+tag+'_0')['kelas']==tag+'_0'
        print('PASS: Name/NIS matching, ambiguous/unknown names, all-student summaries, zero counts, thresholds, ranking, multiple names, date detail and month/start-boundary parsing.')
        credentials=json.dumps({'role':'walas','username':tag+'_wali0','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(d=>finishLogin(d.user)).then(()=>authRequest('api/bootstrap?kelas_id="+classes[0]+"')).then(d=>{applyBootstrapData(d);applyRoleAccess();render()})")
        c.wait("window.activeClassId==="+classes[0])
        c.js('helpQuestion()')
        def ask(q):
            c.js("document.getElementById('helpQuestionText').value="+json.dumps(q)+";document.getElementById('sendHelpQuestion').click()")
            c.wait("!document.getElementById('sendHelpQuestion').disabled")
            return c.js("document.getElementById('helpAnswer').textContent")
        text=ask('Berapa kali Kanaya terlambat?');assert 'Kanaya Safa Aulia' in text and '13-07-2026' in text
        assert c.js("document.querySelector('[data-total-status=Terlambat] .attendance-question-total-number').textContent")=='3'
        assert c.js("document.querySelectorAll('#helpAnswer img').length")==0
        assert '06:45:00 WIB' in text and 'Catatan <img' in text
        assert c.js("document.querySelector('.attendance-question-totals').compareDocumentPosition(document.querySelector('.attendance-question-details')) & Node.DOCUMENT_POSITION_FOLLOWING")
        assert c.js("document.querySelectorAll('.attendance-question-dates li').length")==3
        text=ask('Rekap absen sakit Kanaya')
        assert 'Total Sakit Kanaya Safa Aulia: 2 kali.' in text
        assert c.js("[...document.querySelectorAll('.attendance-question-dates [data-attendance-date]')].map(n=>n.textContent)")==['13-07-2026','14-07-2026']
        sick_lines=c.js("[...document.querySelectorAll('.attendance-question-date-line')].map(n=>n.textContent)")
        assert '(surat sakit terlampir)' in sick_lines[0] and '(surat' not in sick_lines[1]
        assert c.js("(()=>{let r=[...document.querySelectorAll('.attendance-question-dates li')].map(n=>n.getBoundingClientRect());return r[1].top>r[0].top&&r[1].top>=r[0].bottom-0.5})()")

        text=ask('Rekap absen izin Kanaya')
        assert 'Total Izin Kanaya Safa Aulia: 2 kali.' in text
        assert c.js("[...document.querySelectorAll('.attendance-question-dates [data-attendance-date]')].map(n=>n.textContent)")==['15-07-2026','03-08-2026']
        izin_lines=c.js("[...document.querySelectorAll('.attendance-question-date-line')].map(n=>n.textContent)")
        assert '(surat' not in izin_lines[0] and '(surat izin terlampir)' in izin_lines[1]

        text=ask('Rekap sakit Timothy')
        assert 'Total Sakit Timothy Santoso: 0 kali.' in text and 'Tidak ada catatan Sakit' in text
        assert c.js("document.querySelectorAll('.attendance-question-dates [data-attendance-date]').length")==0
        text=ask('Rekap sakit dan izin Kanaya')
        assert c.js("document.querySelectorAll('.attendance-question-total').length")==2
        assert c.js("document.querySelector('.attendance-question-totals').compareDocumentPosition(document.querySelector('.attendance-question-details')) & Node.DOCUMENT_POSITION_FOLLOWING")
        # The next question must fetch fresh corrected data, not reuse a page-load snapshot.
        wali.call('api/absensi',{'siswa_id':int(pupils[0]),'kelas_id':int(classes[0]),'tanggal':'2026-10-01','semester':'Ganjil','status':'Izin'})
        ask('Berapa kali Kanaya terlambat?')
        assert c.js("document.querySelector('[data-total-status=Terlambat] .attendance-question-total-number').textContent")=='2'
        text=ask('Rekap izin Kanaya per bulan');assert 'Rekap Bulanan Kanaya Safa Aulia' in text and 'juli 2026' in text
        text=ask('Rekap sakit izin keterlambatan seluruh siswa');assert c.js("document.querySelectorAll('.attendance-question-table tbody tr').length")==4
        assert c.js("document.querySelectorAll('.attendance-question-details details[open]').length")==4
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js('document.documentElement.scrollWidth<=innerWidth');c.call('Emulation.clearDeviceMetricsOverride')
        assert c.js("fetch('assets/attendance-questions.js').then(r=>r.text()).then(s=>{new Function(s);return true})")
        errors=[e['params']['exceptionDetails'].get('text') for e in c.events if e.get('method')=='Runtime.exceptionThrown'];assert not errors,errors
        print('PASS: Actual Tanya AIWalas form, history/date/time display, escaping, fresh data after Wali correction, all-student table, and mobile layout.')
finally:
    if c:
        try:c.js("closeModal();authRequest('api/auth/logout',{method:'POST',body:'{}'})")
        except Exception:pass
    if classes:
        ids=','.join(classes)
        for table,folder in [('pengajuan_sakit','pengajuan-sakit'),('pengajuan_izin','pengajuan-izin')]:
            for filename in sql(f'SELECT berkas_lampiran FROM {table} WHERE kelas_id IN ({ids}) AND berkas_lampiran IS NOT NULL').splitlines():
                path=ROOT/'writable'/folder/filename
                if path.is_file():path.unlink()
        sql(f"DELETE FROM absensi WHERE kelas_id IN ({ids});DELETE FROM pengguna WHERE siswa_id IN (SELECT id FROM siswa WHERE kelas_id IN ({ids}));DELETE FROM catatan_kedisiplinan WHERE kelas_id IN ({ids});DELETE FROM pengampu_mapel WHERE kelas_id IN ({ids});DELETE FROM siswa WHERE kelas_id IN ({ids});DELETE FROM kelas WHERE id IN ({ids});")
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teachers:sql('DELETE FROM guru WHERE id IN ('+','.join(teachers)+')')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
