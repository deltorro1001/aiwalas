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
prefix='shome_'+secrets.token_hex(5);password=secrets.token_hex(16);hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
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
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_wali','{hashed}','Test Wali','wali_kelas');SELECT LAST_INSERT_ID();"))
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{prefix}','Ganjil',1);SELECT LAST_INSERT_ID();")
    for i in range(2):classes.append(sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}_{i}',11,'Test',{year},{users[0]});SELECT LAST_INSERT_ID();"))
    class_id=classes[0]
    names=['Test Hadir','Test Terlambat','Test <img src=x onerror=alert(1)>','Test Izin','Test Alpha','Test Belum Scan','Test Menunggu','Inactive Peer','Foreign Peer']
    today=datetime.datetime.now(ZoneInfo('Asia/Jakarta')).date()
    statuses=['Hadir','Terlambat','Sakit','Izin','Alpha',None,'Menunggu keterangan','Sakit','Sakit']
    for i,name in enumerate(names):
        klass=classes[1] if i==8 else class_id
        pupils.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{prefix}_{i}','{name}','L',{klass},{0 if i==7 else 1});SELECT LAST_INSERT_ID();"))
        if statuses[i]:sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,semester,dicatat_oleh,keterangan) VALUES({pupils[-1]},{klass},'{today}','{statuses[i]}','Ganjil',{users[0]},'Private note');")
    sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,semester,dicatat_oleh) VALUES({pupils[0]},{class_id},'{today}','Sakit','Genap',{users[0]});")
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{prefix}_0','{hashed}','Test Hadir','siswa','{prefix}_0');SELECT LAST_INSERT_ID();"))
    teacher=sql("INSERT INTO guru(nama_lengkap,nama_panggilan,jenis_kelamin,aktif) VALUES('Test Guru','Test Guru','L',1);SELECT LAST_INSERT_ID();")
    subject=sql(f"INSERT INTO mata_pelajaran(nama_mata_pelajaran,aktif) VALUES('{prefix}_mapel',1);SELECT LAST_INSERT_ID();")
    weekday=['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'][today.weekday()]
    sql(f"INSERT INTO jadwal_kelas(kelas_id,mata_pelajaran_id,guru_id,hari,jam_mulai,jam_selesai) VALUES({class_id},{subject},{teacher},'{weekday}','08:00:00','09:00:00');")
    school_days=['Senin','Selasa','Rabu','Kamis','Jumat']
    for school_day in school_days:
        if school_day!=weekday:sql(f"INSERT INTO jadwal_kelas(kelas_id,mata_pelajaran_id,guru_id,hari,jam_mulai,jam_selesai) VALUES({class_id},{subject},{teacher},'{school_day}','08:00:00','09:00:00');")
    sql(f"INSERT INTO jadwal_kelas(kelas_id,mata_pelajaran_id,guru_id,hari,jam_mulai,jam_selesai) VALUES({classes[1]},{subject},{teacher},'Senin','18:00:00','19:00:00');")
    endpoint='api/siswa/dashboard?semester=Ganjil&kelas_id='+classes[1]
    anonymous=Client();anonymous.call(endpoint,status=401)
    a=Client();a.call('api/auth/login',{'role':'student','username':prefix+'_0','password':password})
    data=a.call(endpoint)['data'];summary=data['ringkasan_kelas']
    assert sum(summary['status'].values())==7 and summary['status']['Menunggu keterangan']==2,summary
    assert summary['status']['Sakit']==1 and summary['status']['Terlambat']==1
    assert len(summary['siswa_tidak_hadir'])==3 and len(summary['menunggu_keterangan'])==2
    assert summary['nama_kelas']==prefix+'_0' and len(data['jadwal_hari_ini'])==1
    assert not any(x in json.dumps(summary) for x in ['Private note','nomor_hp','Inactive Peer','Foreign Peer','nis'])
    assert len(summary['tren_absensi'])==5
    if today.weekday()<5:
        row=summary['tren_absensi'][today.weekday()];assert row['Sakit']==1 and row['Izin']==1 and row['Terlambat']==1
    genap=a.call('api/siswa/dashboard?semester=Genap')['data']['ringkasan_kelas']
    assert genap['status']['Sakit']==1 and genap['status']['Menunggu keterangan']==6
    a.call('api/siswa/dashboard?semester=invalid',status=422)
    assert data['kedisiplinan']['catatan']==[] and 'notifikasi_wali' not in data
    assert data['notifikasi_whatsapp']==[]
    anonymous.call('api/siswa/rekap-absensi',status=401)
    for date,status,stamp,semester in [('2026-01-01','Alpha',None,'Genap'),('2026-01-31','Hadir','06:40:59','Genap'),('2026-01-31','Sakit',None,'Ganjil'),('2026-02-01','Sakit',None,'Genap'),('2026-12-31','Izin',None,'Ganjil'),('2027-01-01','Sakit',None,'Genap'),('2025-12-31','Alpha',None,'Ganjil')]:
        scan_stamp="'"+stamp+"'" if stamp else 'NULL'
        sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,waktu_scan,semester,dicatat_oleh) VALUES({pupils[0]},{class_id},'{date}','{status}',{scan_stamp},'{semester}',{users[0]});")
    sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,semester,dicatat_oleh) VALUES({pupils[1]},{class_id},'2026-01-15','Sakit','Genap',{users[0]});")
    january=a.call('api/siswa/rekap-absensi?bulan=01&tahun=2026')
    assert january['bulan']=='2026-01' and january['label_bulan']=='Januari 2026'
    assert january['data']==[{'tanggal':'2026-01-01','waktu_scan':None,'status':'Alpha'},{'tanggal':'2026-01-31','waktu_scan':'06:40:59','status':'Hadir'}]
    assert a.call('api/siswa/rekap-absensi?bulan=01&tahun=2026&siswa_id='+pupils[1]+'&kelas_id='+classes[1])['data']==january['data']
    assert a.call('api/siswa/rekap-absensi?bulan=03&tahun=2026')['data']==[]
    december=a.call('api/siswa/rekap-absensi?bulan=12&tahun=2026')['data']
    assert len(december)==1 and december[0]['tanggal']=='2026-12-31'
    current_month=a.call('api/siswa/rekap-absensi')
    assert current_month['bulan']==today.strftime('%Y-%m') and all(row['tanggal'].startswith(today.strftime('%Y-%m')) for row in current_month['data'])
    assert a.call('api/siswa/rekap-absensi?bulan=')['data']==current_month['data']
    for invalid in ['00','13','-1','Jan','2026-01']:
        a.call('api/siswa/rekap-absensi?bulan='+invalid,status=422)
    a.call('api/siswa/rekap-absensi?bulan[]=01',status=422)
    for invalid_year in ['2025','abc','10000','2026-01']:
        a.call('api/siswa/rekap-absensi?bulan=01&tahun='+invalid_year,status=422)
    a.call('api/siswa/rekap-absensi?tahun[]=2026',status=422)
    next_year=a.call('api/siswa/rekap-absensi?bulan=01&tahun=2027')
    assert next_year['tahun']==2027 and next_year['nomor_bulan']==1 and next_year['label_bulan']=='Januari 2027'
    assert next_year['data']==[{'tanggal':'2027-01-01','waktu_scan':None,'status':'Sakit'}]
    assert january['jumlah_hari']==31
    assert a.call('api/siswa/rekap-absensi?bulan=02&tahun=2026')['jumlah_hari']==28
    assert a.call('api/siswa/rekap-absensi?bulan=02&tahun=2028')['jumlah_hari']==29
    assert a.call('api/siswa/rekap-absensi?bulan=04&tahun=2026')['jumlah_hari']==30


    anonymous.call('api/siswa/kedisiplinan',status=401)
    empty_discipline=a.call('api/siswa/kedisiplinan')['data']
    assert empty_discipline['kategori_bercatatan']==[] and empty_discipline['keterlambatan']['jumlah']==0

    sql(f"UPDATE siswa SET nomor_hp='081111000111',nomor_hp_orang_tua='081222000111' WHERE id={pupils[0]};UPDATE siswa SET nomor_hp_orang_tua='081222000111' WHERE id={pupils[1]};")
    def message(body,student_id,stamp,status='Terkirim',phone='6281111000111',klass=None,category='WhatsApp'):
        sql(f"INSERT INTO pesan_komunikasi(siswa_id,kelas_id,pengirim,penerima,nomor_tujuan,isi_pesan,kategori,status_pengiriman,dikirim_pada,dibuat_oleh) VALUES({student_id or 'NULL'},{klass or class_id},'Test Wali','Test Recipient','{phone}','{body}','{category}','{status}','{stamp}',{users[0]});")
    for i in range(6):message('Pesan <img src=x onerror=alert(1)>' if i==5 else f'Own message {i}',pupils[0],f'{today} 10:0{i}:00')
    message('Legacy own phone',None,f'{today} 10:07:00')
    message('Pending message',pupils[0],f'{today} 11:00:00',status='Disiapkan')
    message('Failed message',pupils[0],f'{today} 11:01:00',status='Gagal')
    message('Peer message',pupils[1],f'{today} 11:02:00')
    message('Shared family phone',None,f'{today} 11:03:00',phone='6281222000111')
    message('Teacher reminder',None,f'{today} 11:04:00',category='Pengingat Guru Otomatis')
    message('Foreign class message',None,f'{today} 11:05:00',klass=classes[1])
    notifications=a.call(endpoint)['data']['notifikasi_whatsapp']
    expected_messages=['Legacy own phone','Pesan <img src=x onerror=alert(1)>','Own message 4','Own message 3']
    assert [x['isi_pesan'] for x in notifications]==expected_messages,notifications
    assert not any('nomor_tujuan' in item for item in notifications)

    anonymous.call('api/kedisiplinan?kelas_id='+class_id,status=401)
    a.call('api/kedisiplinan?kelas_id='+class_id,status=403)
    a.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':'etika','pelanggaran':'Unauthorized'},status=403)
    wali=Client();wali.call('api/auth/login',{'role':'walas','username':prefix+'_wali','password':password})
    wali.call('api/siswa/rekap-absensi',status=403)
    anonymous.call('api/jadwal',status=401)
    own_week=a.call('api/jadwal')['data'];assert all(str(row['kelas_id'])==class_id for row in own_week)
    assert a.call('api/jadwal?kelas_id='+classes[1])['data']==own_week
    assert wali.call('api/jadwal?kelas_id='+class_id)['data']==own_week
    a.call('api/jadwal',{'kelas_id':int(class_id)},status=403)
    a.call('api/jadwal/'+str(own_week[0]['id']),{'kelas_id':int(class_id)},status=403,method='PUT')
    changed_day='Jumat' if weekday!='Jumat' else 'Selasa'
    change_row=next(row for row in own_week if row['hari']==changed_day)
    change_payload={'kelas_id':int(class_id),'mata_pelajaran_id':int(subject),'guru_id':int(teacher),'hari':changed_day,'jam_mulai':'09:15','jam_selesai':'10:00'}
    wali.call('api/jadwal/'+str(change_row['id']),change_payload,method='PUT')
    assert next(row for row in a.call('api/jadwal')['data'] if row['hari']==changed_day)['jam_mulai']=='09:15:00'
    late_peer=next(x for x in wali.call('api/kedisiplinan?kelas_id='+class_id)['data'] if str(x['id'])==pupils[1])
    assert late_peer['keterlambatan']['jumlah']==1 and late_peer['catatan'][0]['kategori']=='terlambat'
    for category,values in [('etika',{'pelanggaran':'Catatan Etika Awal','tanggal':str(today),'saksi':'Test Wali','alasan':'Alasan etika','tindakan':'Pembinaan'}),('atribut',{'pelanggaran':'Atribut kurang lengkap','perbaikan':'Lengkapi atribut'}),('terlambat',{'jumlah_terlambat':2,'tanggal':str(today),'alasan':'Alasan terlambat'})]:
        wali.call('api/kedisiplinan',dict(siswa_id=int(pupils[0]),kategori=category,**values))
    wali.call('api/kedisiplinan',{'siswa_id':int(pupils[2]),'kategori':'etika','pelanggaran':'CATATAN SISWA LAIN'})
    own_notes=a.call('api/siswa/dashboard?kelas_id='+classes[1])['data']['kedisiplinan']['catatan']
    assert len(own_notes)==3 and 'CATATAN SISWA LAIN' not in json.dumps(own_notes)
    assert own_notes[1]['status']=='Dalam Peringatan (Terlambat 2x)'
    own_discipline=a.call('api/siswa/kedisiplinan')['data']
    assert own_discipline['records']['etika']=={'pelanggaran':'Catatan Etika Awal','tanggal':str(today),'saksi':'Test Wali','tindakan':'Pembinaan'}
    assert own_discipline['records']['atribut']['perbaikan']=='Lengkapi atribut'
    assert own_discipline['keterlambatan']['jumlah']==2 and own_discipline['records']['terlambat']['alasan']=='Alasan terlambat'
    assert a.call('api/siswa/kedisiplinan?siswa_id='+pupils[2]+'&kelas_id='+classes[1])['data']==own_discipline
    assert 'dicatat_oleh' not in json.dumps(own_discipline) and 'CATATAN SISWA LAIN' not in json.dumps(own_discipline)
    wali.call('api/siswa/kedisiplinan',status=403)

    wali.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':'etika','tanggal':'2026-02-31'},status=422)
    wali.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':'terlambat','jumlah_terlambat':-1},status=422)
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_other','{hashed}','Other Wali','wali_kelas');SELECT LAST_INSERT_ID();"))
    outsider=Client();outsider.call('api/auth/login',{'role':'walas','username':prefix+'_other','password':password})
    outsider.call('api/kedisiplinan?kelas_id='+class_id,status=404)
    outsider.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':'etika','pelanggaran':'Other Wali'},status=404)

    sql(f"UPDATE absensi SET waktu_scan='06:12:34' WHERE siswa_id={pupils[0]} AND tanggal='{today}' AND semester='Ganjil';")
    attendance_endpoint='api/absensi?kelas_id='+class_id+'&semester=Ganjil'
    own=a.call(attendance_endpoint)['data']
    assert len(own)==1 and str(own[0]['siswa_id'])==pupils[0] and own[0]['waktu_scan']=='06:12:34' and own[0]['status']=='Hadir'
    assert not a.call('api/absensi?kelas_id='+classes[1])['data']
    assert a.call('api/absensi?kelas_id='+class_id+'&semester=Genap')['data'][0]['waktu_scan'] is None
    # Manual PJJ correction survives storage and is visible in the child's own views.
    pjj_payload={'siswa_id':int(pupils[0]),'kelas_id':int(class_id),'semester':'Ganjil','status':'PJJ','waktu_scan':None,'keterangan':'Pembelajaran jarak jauh'}
    pjj=wali.call('api/absensi',pjj_payload)['data']
    assert pjj['status']=='PJJ' and pjj['waktu_scan'] is None
    assert a.call(attendance_endpoint)['data'][0]['status']=='PJJ'
    assert a.call(endpoint)['data']['scan']['status']=='PJJ'
    assert any(row['tanggal']==str(today) and row['status']=='PJJ' for row in a.call('api/siswa/rekap-absensi')['data'])
    wali.call('api/absensi',dict(pjj_payload,status='PJJ typo'),status=422)
    wali.call('api/absensi',dict(pjj_payload,status='Hadir',waktu_scan='06:12:34',keterangan='Private note'))
    yesterday=today-datetime.timedelta(days=1)
    sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,waktu_scan,semester,dicatat_oleh) VALUES({pupils[0]},{class_id},'{yesterday}','Hadir','05:55:55','Ganjil',{users[0]});")
    # Historical corrections must update that day's row without touching today's record.
    historical=wali.call('api/absensi',dict(pjj_payload,tanggal=str(yesterday)))['data']
    assert sql('SELECT tanggal FROM absensi WHERE id='+str(historical['id']))==str(yesterday) and historical['status']=='PJJ'
    assert a.call('api/absensi?kelas_id='+class_id+'&semester=Ganjil&tanggal='+str(yesterday))['data'][0]['status']=='PJJ'
    assert a.call(attendance_endpoint)['data'][0]['status']=='Hadir'
    assert sql(f"SELECT COUNT(*) FROM absensi WHERE siswa_id={pupils[0]} AND tanggal='{yesterday}' AND semester='Ganjil'")=='1'
    wali.call('api/absensi',dict(pjj_payload,tanggal='invalid'),status=422)
    wali.call('api/absensi',dict(pjj_payload,tanggal=str(yesterday),status='Hadir',waktu_scan='05:55:55'))
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies')
        c.call('Page.addScriptToEvaluateOnNewDocument',{'source':"const cloneElement=Element.prototype.cloneNode;Element.prototype.cloneNode=function(deep){const copy=cloneElement.call(this,deep);if(this.matches('.summary-panel')&&this.isConnected&&!this.classList.contains('student-today-schedule')){window.__studentOriginalCardHeight=this.getBoundingClientRect().height;window.__studentOriginalCardTemplate=copy}return copy};"})
        c.call('Page.navigate',{'url':BASE});c.wait("typeof authRequest==='function' && !!window.AIWalasPages && window.render.toString().includes('var studentRoutes')")
        wali_credentials=json.dumps({'role':'walas','username':prefix+'_wali','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+wali_credentials+")}).then(function(d){return finishLogin(d.user)}).then(function(){return authRequest('api/bootstrap?kelas_id="+class_id+"')}).then(function(d){applyBootstrapData(d);window.page='discipline';window.render()})")
        # Keep the legacy attendance renderer from executing the XSS fixture used by other views.
        c.js("window.students.forEach(function(s){if(s[0].includes('<img'))s[0]='Test Sakit'});window.page='attendance';window.render()")
        correction_selector=json.dumps('[data-student-id="'+pupils[4]+'"].attendance-correct')
        c.wait("!!document.querySelector("+correction_selector+")")
        c.js("document.querySelector("+correction_selector+").click()")
        assert 'PJJ' in c.js("Array.from(document.querySelectorAll('#correctStatus option')).map(o=>o.value)")
        c.js("document.getElementById('correctStatus').value='PJJ';document.getElementById('saveModal').click()")
        c.wait("document.querySelector("+correction_selector+")?.dataset.status==='PJJ'")
        c.js("window.render()")
        c.wait("document.querySelector("+correction_selector+")?.dataset.status==='PJJ'")
        c.js("document.querySelector("+correction_selector+").click()")
        assert c.js("document.getElementById('correctStatus').value")=='PJJ'
        c.js("document.getElementById('cancelModal').click();document.getElementById('attendanceSortFilter').value='PJJ';document.getElementById('attendanceSortFilter').dispatchEvent(new Event('change'))")
        assert c.js("document.querySelectorAll('[data-attendance-status]').length")==1
        assert c.js("document.querySelector('[data-attendance-status]').dataset.attendanceStatus")=='PJJ'
        wali.call('api/absensi',{'siswa_id':int(pupils[4]),'kelas_id':int(class_id),'semester':'Ganjil','status':'Alpha','waktu_scan':None})
        c.js("document.getElementById('attendanceSortFilter').value='Nama';document.getElementById('attendanceSortFilter').dispatchEvent(new Event('change'));document.getElementById('attendanceViewDate').value="+json.dumps(str(yesterday))+";document.getElementById('attendanceViewDate').dispatchEvent(new Event('change'))")
        history_selector=json.dumps('[data-student-id="'+pupils[0]+'"].attendance-correct')
        c.wait("document.querySelector("+history_selector+")?.dataset.time==='05:55:55'")
        assert c.js("document.getElementById('scanQr').disabled")
        c.js("document.querySelector("+history_selector+").click()")
        c.js("document.getElementById('correctStatus').value='PJJ';document.getElementById('correctTime').value='';document.getElementById('correctNote').value='Koreksi riwayat';document.getElementById('saveModal').click()")
        c.wait("document.querySelector("+history_selector+")?.dataset.status==='PJJ'")
        c.js("window.render()")
        c.wait("document.querySelector("+history_selector+")?.dataset.status==='PJJ'")
        assert c.js("window.students.find(s=>String(s[7])==="+json.dumps(pupils[0])+")[2]")=='Hadir'
        assert a.call(attendance_endpoint)['data'][0]['status']=='Hadir'
        assert sql(f"SELECT status FROM absensi WHERE siswa_id={pupils[0]} AND tanggal='{yesterday}' AND semester='Ganjil'")=='PJJ'
        wali.call('api/absensi',dict(pjj_payload,tanggal=str(yesterday),status='Hadir',waktu_scan='05:55:55'))
        c.js("document.getElementById('attendanceViewDate').value="+json.dumps(str(today))+";document.getElementById('attendanceViewDate').dispatchEvent(new Event('change'))")
        c.wait("document.querySelector("+history_selector+")?.dataset.time==='06:12:34'")
        print('PASS: Historical wali correction persists on selected date, keeps today unchanged, retains button on reload and leaves historical scan disabled.')
        c.js("window.page='discipline';window.render()")
        print('PASS: Wali PJJ correction saves, survives re-render, reopens selected and filters correctly.')
        c.wait("!!document.querySelector('[data-stored-discipline=\""+pupils[0]+"\"]')")
        c.js("document.querySelector('[data-stored-discipline=\""+pupils[0]+"\"]').click()")
        c.js("document.getElementById('storedDiscipline_pelanggaran').value='Etika UI <img src=x onerror=alert(1)>';document.getElementById('saveStoredDiscipline').click()")
        c.wait("!document.getElementById('modalBackdrop').classList.contains('show')")
        c.wait("document.getElementById('storedDisciplineRows')?.textContent.includes('Etika UI')")
        c.js("window.render()")
        c.wait("document.getElementById('storedDisciplineRows')?.textContent.includes('Etika UI')")
        assert not c.js("!!document.querySelector('#storedDisciplineRows img')")
        assert c.js("getComputedStyle(document.querySelector('.sidebar [data-page=studentSchedule]')).display")=='none'
        credentials=json.dumps({'role':'student','username':prefix+'_0','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(function(d){return finishLogin(d.user)})")
        c.wait("!!document.querySelector('.student-home .student-today-schedule') && !!document.querySelector('.student-whatsapp-card')")
        assert c.js("document.querySelectorAll('.student-home .summary-panel').length")==1
        discipline_text=c.js("document.querySelector('.student-home').textContent")
        assert 'Etika UI <img src=x onerror=alert(1)>' in discipline_text and 'CATATAN SISWA LAIN' not in discipline_text
        assert 'Dalam Peringatan (Terlambat 2x)' in discipline_text and 'Lengkapi atribut' in discipline_text
        assert 'Notifikasi' in discipline_text and not c.js("!!document.querySelector('.student-discipline-note img')")
        assert c.js("Array.from(document.querySelector('.student-home .dashboard-right-stack').children).map(el=>el.querySelector('h2').textContent)")==['Status Kedisiplinan','Notifikasi']
        assert c.js("Array.from(document.querySelectorAll('.student-whatsapp-message p')).map(el=>el.textContent)")==expected_messages
        assert not c.js("!!document.querySelector('.student-whatsapp-list img')")
        assert 'Siswa Tidak Hadir Hari Ini' not in discipline_text
        assert all(x not in discipline_text for x in ['Pending message','Failed message','Peer message','Shared family phone','Teacher reminder','Foreign class message','Own message 0','Own message 1','Own message 2'])
        assert c.js("getComputedStyle(document.querySelector('.sidebar [data-page=studentDiscipline]')).display")!='none'
        c.js("document.querySelector('.sidebar [data-page=studentDiscipline]').click()")
        c.wait("document.getElementById('studentDiscipline_etika')?.textContent.includes('Etika UI')")
        assert c.js("Array.from(document.querySelectorAll('.student-discipline-page h2')).map(el=>el.textContent)")==['Pelanggaran Etika','Keterlambatan','Pakaian & Atribut']
        assert c.js("Array.from(document.querySelector('.student-discipline-page table').querySelectorAll('th')).map(el=>el.textContent)")==['Nomor','Pelanggaran Yang Dilakukan','Tanggal Dilakukan','Disaksikan Oleh','Tindakan']
        ethics=c.js("document.getElementById('studentDiscipline_etika').textContent")
        assert 'Etika UI <img src=x onerror=alert(1)>' in ethics and 'Test Wali' in ethics and 'Pembinaan' in ethics and 'Alasan etika' not in ethics
        assert 'Alasan terlambat' in c.js("document.getElementById('studentDiscipline_terlambat').textContent")
        assert 'Dalam Peringatan (Terlambat 2x)' in c.js("document.getElementById('studentDiscipline_terlambat').textContent")
        assert 'Lengkapi atribut' in c.js("document.getElementById('studentDiscipline_atribut').textContent")
        assert not c.js("!!document.querySelector('.student-discipline-page input,.student-discipline-page textarea,.student-discipline-page img')")
        assert c.js("document.querySelectorAll('.student-discipline-page button').length")==1
        assert 'CATATAN SISWA LAIN' not in c.js("document.querySelector('.student-discipline-page').textContent")
        wali.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':'etika','pelanggaran':'Perubahan Wali','tanggal':str(today),'saksi':'Test Wali','tindakan':'Pembinaan lanjutan'})
        c.js("document.getElementById('refreshStudentDiscipline').click()")
        c.wait("document.getElementById('studentDiscipline_etika')?.textContent.includes('Perubahan Wali')")
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js("document.documentElement.scrollWidth<=window.innerWidth"),'Student discipline mobile overflow'
        c.call('Emulation.clearDeviceMetricsOverride')
        for category in ['etika','atribut','terlambat']:
            wali.call('api/kedisiplinan',{'siswa_id':int(pupils[0]),'kategori':category,'jumlah_terlambat':0})
        assert not a.call('api/siswa/dashboard')['data']['kedisiplinan']['catatan']
        c.js("document.getElementById('refreshStudentDiscipline').click()")
        c.wait("document.getElementById('studentDiscipline_etika')?.textContent==='Tidak Ada Catatan Pelanggaran'")
        assert c.js("document.getElementById('studentDiscipline_atribut').textContent")=='Tidak Ada Catatan Pelanggaran'
        assert 'Baik' in c.js("document.getElementById('studentDiscipline_terlambat').textContent")
        c.js("window.page='dashboard';window.render()")
        print('PASS: Student discipline sidebar and three read-only tables, exact ethics columns, shared wali data, reload changes, empty state, escaping and mobile layout.')

        c.js("window.render()")
        c.wait("!!document.querySelector('.student-discipline-summary') && document.querySelector('.student-discipline-summary').textContent==='Tidak Ada Catatan Pelanggaran'")
        c.wait("!!document.querySelector('.student-whatsapp-card')")
        assert c.js("document.querySelector('.student-home .activity-list').textContent")=='Tidak Ada Catatan Pelanggaran'

        text=c.js("document.querySelector('.student-home').textContent")
        for label in ['Waktu Kehadiran','Jumlah Keterlambatan','Rekap Absensi','Jadwal Ulangan Harian Minggu Ini','Daftar Tugas Yang Harus Diselesaikan','Status Kedisiplinan','Notifikasi']:
            assert label in text,label
        assert c.js("document.querySelectorAll('.student-whatsapp-message').length")==4
        sql('DELETE FROM pesan_komunikasi WHERE kelas_id='+class_id)
        c.js("window.render()")
        c.wait("document.querySelector('.student-whatsapp-list')?.textContent==='Belum ada pesan WhatsApp terkirim.'")
        assert 'Foreign Peer' not in text and 'Inactive Peer' not in text
        schedule_text=c.js("document.querySelector('.student-today-schedule').textContent")
        assert prefix+'_mapel' in schedule_text and '08:00-09:00' in schedule_text and 'Test Guru' in schedule_text
        assert not c.js("!!document.querySelector('.student-today-schedule [data-summary-tab]')")
        assert not c.js("!!document.querySelector('.student-today-schedule .analytics-svg')")
        assert c.js("Math.abs(document.querySelector('.student-today-schedule').getBoundingClientRect().height-window.__studentOriginalCardHeight)<1"),'Card height changed'
        c.js("window.page='attendance';window.render();window.page='dashboard';window.render()")
        c.wait("!!document.querySelector('.student-home .student-today-schedule') && !!document.querySelector('.student-whatsapp-card')")
        assert c.js("document.querySelectorAll('.student-whatsapp-card').length")==1
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js("document.documentElement.scrollWidth<=window.innerWidth"),'Mobile overflow'
        c.js("new Promise(function(resolve){requestAnimationFrame(function(){requestAnimationFrame(resolve)})})")
        assert c.js("(()=>{const card=document.querySelector('.student-today-schedule'),probe=window.__studentOriginalCardTemplate.cloneNode(true);probe.style.cssText='position:fixed;visibility:hidden;width:'+card.getBoundingClientRect().width+'px';document.body.appendChild(probe);const same=Math.abs(card.getBoundingClientRect().height-probe.getBoundingClientRect().height)<1;probe.remove();return same})()"),'Mobile card height changed'
        c.call('Emulation.clearDeviceMetricsOverride')
        errors=[e for e in c.events if e.get('method')=='Runtime.exceptionThrown'];assert not errors,errors
        assert c.js("getComputedStyle(document.querySelector('.sidebar [data-page=studentSchedule]')).display")!='none'
        c.js("document.querySelector('.sidebar [data-page=studentSchedule]').click()")
        c.wait("document.querySelectorAll('[data-student-schedule-day] .schedule-item').length===5")
        assert c.js("Array.from(document.querySelectorAll('[data-student-schedule-day] h2')).map(el=>el.textContent)")==school_days
        assert c.js("document.querySelector('.sidebar [data-page=studentSchedule]').classList.contains('active')")
        assert c.js("document.querySelector('.student-weekly-schedule').textContent").count('Test Guru')==5
        assert c.js("document.querySelector('.student-weekly-schedule').textContent").count(prefix+'_mapel')==5
        assert c.js("document.querySelector('.student-weekly-schedule').textContent").count('09:15–10:00')==1
        assert c.js("document.querySelectorAll('.student-weekly-schedule button').length")==1
        change_payload.update(jam_mulai='09:30',jam_selesai='10:15')
        wali.call('api/jadwal/'+str(change_row['id']),change_payload,method='PUT')
        c.js("document.getElementById('refreshStudentSchedule').click()")
        c.wait("document.querySelector('.student-weekly-schedule')?.textContent.includes('09:30–10:15')")
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js("document.documentElement.scrollWidth<=window.innerWidth"),'Weekly schedule mobile overflow'
        c.call('Emulation.clearDeviceMetricsOverride')
        sql(f"DELETE FROM jadwal_kelas WHERE kelas_id={class_id} AND hari='{changed_day}'")
        c.js("document.getElementById('refreshStudentSchedule').click()")
        c.wait("document.querySelector('.student-weekly-schedule')?.textContent.includes('Belum ada jadwal mapel untuk hari "+changed_day+".')")
        c.js("window.page='dashboard';window.render()")
        c.wait("!!document.querySelector('.student-today-schedule')")
        print('PASS: Student sidebar weekly schedule Mon–Fri, own-class restriction, read-only access, wali changes reflected on refresh, empty day and mobile layout.')
        for hour in range(9,20):
            sql(f"INSERT INTO jadwal_kelas(kelas_id,mata_pelajaran_id,guru_id,hari,jam_mulai,jam_selesai) VALUES({class_id},{subject},{teacher},'{weekday}','{hour:02d}:00:00','{hour:02d}:30:00');")
        c.js("window.render()")
        c.wait("document.querySelectorAll('.student-today-schedule .summary-schedule-row').length===12")
        assert c.js("document.querySelector('.student-today-schedule .summary-content').scrollHeight>document.querySelector('.student-today-schedule .summary-content').clientHeight"),'Long schedule does not scroll'
        assert c.js("Math.abs(document.querySelector('.student-today-schedule').getBoundingClientRect().height-window.__studentOriginalCardHeight)<1"),'Long schedule resized card'
        sql('DELETE FROM jadwal_kelas WHERE kelas_id='+class_id)
        c.js("window.render()")
        c.wait("document.querySelector('.student-today-schedule')?.textContent.includes('Tidak ada jadwal mapel hari ini.')")
        c.js("window.page='attendance';window.render()")
        c.wait("document.getElementById('studentAttendanceStatus')?.textContent==='Hadir'")
        assert c.js("document.getElementById('studentAttendanceScan').textContent")=='06:12:34 WIB'
        assert c.js("document.getElementById('studentQrButton').textContent")=='Buka Scanner QR'
        c.wait("document.getElementById('studentAttendanceRecapRows')?.textContent.includes('Hadir')")
        assert c.js("Array.from(document.querySelector('#studentAttendanceRecap thead').querySelectorAll('th')).map(el=>el.textContent)")==['Hari','Tanggal','Waktu Scan','Status']
        assert c.js("document.getElementById('studentAttendanceMonth').value")==today.strftime('%m')
        assert c.js("document.getElementById('studentAttendanceYear').value")==str(today.year)
        assert c.js("Array.from(document.getElementById('studentAttendanceMonth').options).map(option=>option.textContent)")==['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
        assert c.js("Array.from(document.getElementById('studentAttendanceYear').options).slice(0,3).map(option=>option.textContent)")==['2026','2027','2028']
        assert c.js("document.getElementById('studentAttendanceYear').getBoundingClientRect().top>=document.getElementById('studentAttendanceMonth').getBoundingClientRect().bottom"),'Year selector not below month'
        c.js("var monthChoice=document.getElementById('studentAttendanceMonth');monthChoice.value='01';monthChoice.dispatchEvent(new Event('change'))")
        c.wait("document.getElementById('studentAttendanceRecapMonthLabel').textContent==='Januari 2026'")
        january_rows=c.js("Array.from(document.querySelectorAll('#studentAttendanceRecapRows tr')).map(row=>Array.from(row.children).map(cell=>cell.textContent))")
        assert len(january_rows)==31 and january_rows[0]==['Kamis','01/01/2026','—','Alpha'] and january_rows[-1]==['Sabtu','31/01/2026','06:40:59 WIB','Hadir']
        assert january_rows[1]==['Jumat','02/01/2026','—','Belum ada catatan']
        c.js("var yearChoice=document.getElementById('studentAttendanceYear');yearChoice.value='2027';yearChoice.dispatchEvent(new Event('change'))")
        c.wait("document.getElementById('studentAttendanceRecapMonthLabel').textContent==='Januari 2027'")
        assert c.js("document.getElementById('studentAttendanceRecapRows').textContent").startswith('Jumat01/01/2027')
        c.js("yearChoice.value='2026';yearChoice.dispatchEvent(new Event('change'))")
        c.wait("document.getElementById('studentAttendanceRecapMonthLabel').textContent==='Januari 2026'")
        c.js("monthChoice.value='03';monthChoice.dispatchEvent(new Event('change'))")
        c.wait("document.getElementById('studentAttendanceRecapMonthLabel').textContent==='Maret 2026'")
        assert c.js("document.querySelectorAll('#studentAttendanceRecapRows tr').length")==31
        assert c.js("document.querySelector('#studentAttendanceRecapRows tr').textContent")=='Minggu01/03/2026—Belum ada catatan'
        c.js("monthChoice.value='12';monthChoice.dispatchEvent(new Event('change'))")
        c.wait("document.getElementById('studentAttendanceRecapMonthLabel').textContent==='Desember 2026'")
        assert '31/12/2026' in c.js("document.getElementById('studentAttendanceRecapRows').textContent")
        c.js("monthChoice.value="+json.dumps(today.strftime('%m'))+";yearChoice.value="+json.dumps(str(today.year))+";monthChoice.dispatchEvent(new Event('change'))")
        c.wait("document.getElementById('studentAttendanceRecapRows').textContent.includes('06:12:34 WIB')")
        assert c.js("document.querySelector('#studentAttendanceRecapRows tr td:nth-child(2)').textContent")==today.strftime('01/%m/%Y')

        for status,scan_time in [('Izin',None),('Sakit',None),('Alpha',None),('Terlambat','06:45:00'),('Menunggu keterangan',None)]:
            stamp="'"+scan_time+"'" if scan_time else 'NULL'
            sql(f"UPDATE absensi SET status='{status}',waktu_scan={stamp} WHERE siswa_id={pupils[0]} AND tanggal='{today}' AND semester='Ganjil';")
            c.js("document.getElementById('refreshStudentAttendance').click()")
            c.wait("document.getElementById('studentAttendanceStatus').textContent==="+json.dumps(status))
            assert c.js("document.getElementById('studentAttendanceScan').textContent")==((scan_time+' WIB') if scan_time else 'Belum ada scan')
        sql(f"DELETE FROM absensi WHERE siswa_id={pupils[0]} AND tanggal='{today}' AND semester='Ganjil';")
        c.js("document.getElementById('refreshStudentAttendance').click()")
        c.wait("document.getElementById('studentAttendanceStatus').textContent==='Belum tercatat'")
        assert c.js("document.getElementById('studentAttendanceScan').textContent")=='Belum ada scan'
        # A valid QR scan is submitted locally; the server supplies status and time.
        wali=Client();wali.call('api/auth/login',{'role':'walas','username':prefix+'_wali','password':password})
        qr=wali.call('api/absensi/qr',{'kelas_id':int(class_id)})['data']['qr_code']
        payload=json.dumps({'kelas_id':int(class_id),'semester':'Ganjil','qr_code':qr,'waktu_scan':'00:00:00','tanggal':'2000-01-01','status':'Hadir'})
        saved=c.js("authRequest('api/absensi',{method:'POST',body:JSON.stringify("+payload+")}).then(function(d){window.render();return d.data})")
        c.wait("document.getElementById('studentAttendanceScan').textContent==="+json.dumps(saved['waktu_scan']+' WIB'))
        assert c.js("document.getElementById('studentAttendanceStatus').textContent")==saved['status']
        c.wait("document.getElementById('studentAttendanceRecapRows')?.textContent.includes("+json.dumps(saved['waktu_scan']+' WIB')+")")
        assert sql('SELECT tanggal FROM absensi WHERE id='+str(saved['id']))==str(today) and saved['waktu_scan']!='00:00:00'
        assert saved['status']==('Terlambat' if saved['waktu_scan']>='06:41:00' else 'Hadir')
        scan_result=wali.call('api/absensi',{'siswa_id':int(pupils[0]),'kelas_id':int(class_id),'semester':'Ganjil','metode':'scan_qr','waktu_scan':'00:00:00','tanggal':'2000-01-01','status':'Hadir'})['data']
        assert sql('SELECT tanggal FROM absensi WHERE id='+str(scan_result['id']))==str(today) and scan_result['waktu_scan']!='00:00:00'
        assert scan_result['status']==('Terlambat' if scan_result['waktu_scan']>='06:41:00' else 'Hadir')
        manual_result=wali.call('api/absensi',{'siswa_id':int(pupils[0]),'kelas_id':int(class_id),'semester':'Ganjil','waktu_scan':None,'status':'Sakit','keterangan':'Manual correction'})['data']
        assert manual_result['status']=='Sakit' and manual_result['waktu_scan'] is None
        assert not c.js("!!document.querySelector('.student-attendance-rule')")

        sql(f"UPDATE absensi SET status='Alpha',waktu_scan=NULL WHERE siswa_id={pupils[0]} AND tanggal='{today}' AND semester='Ganjil';")
        time.sleep(16)
        assert c.js("document.getElementById('studentAttendanceStatus').textContent")=='Alpha','Status did not refresh automatically'
        assert c.js("document.getElementById('studentAttendanceScan').textContent")=='Belum ada scan'
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js("document.documentElement.scrollWidth<=window.innerWidth"),'Attendance mobile overflow'
        c.call('Emulation.clearDeviceMetricsOverride')
        assert not [e for e in c.events if e.get('method')=='Runtime.exceptionThrown']
        c.js("logout()")
        print('PASS: Monthly attendance recap defaults to current month, separate month/year selectors from 2026, cross-year selection, January/December boundaries, semester inference, own records, empty month, scan refresh and mobile layout.')
        print('PASS: Attendance cards show current student/day/semester, all recorded statuses, missing scan/data, QR scan results, automatic refresh and mobile layout.')
        print('PASS: Student daily schedule with times/teachers, preserved desktop/mobile dimensions, scrolling/empty schedule, discipline above four latest sent WhatsApp notifications, existing personal/task cards retained, repeat navigation, escaped names and mobile layout.')
    print('PASS: Discipline API ownership/role isolation, shared saved notes, separate categories, validation and no-notes state.')
    print('PASS: Own-class student overview, semester/active-student filtering, real weekly counts and minimal classmate fields.')
finally:
    if classes:
        ids=','.join(classes)
        sql('DELETE FROM pesan_komunikasi WHERE kelas_id IN ('+ids+');DELETE FROM qr_absensi_aktif WHERE kelas_id IN ('+ids+');DELETE FROM absensi WHERE kelas_id IN ('+ids+');DELETE FROM jadwal_kelas WHERE kelas_id IN ('+ids+');DELETE FROM siswa WHERE kelas_id IN ('+ids+');DELETE FROM kelas WHERE id IN ('+ids+');')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
    if teacher:sql('DELETE FROM guru WHERE id='+teacher)
    if subject:sql('DELETE FROM mata_pelajaran WHERE id='+subject)
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
