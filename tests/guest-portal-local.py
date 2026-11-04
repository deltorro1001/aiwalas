"""Guest portal and publication boundary regression. Disposable fixtures; no gateway calls."""
import datetime,http.cookiejar,json,os,re,secrets,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP='/opt/homebrew/opt/php@8.3/bin/php';MYSQL='/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
tag='guest_'+secrets.token_hex(5);password=secrets.token_hex(16)
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
    default_guest=Client().login('guest','tamu','tamu123')
    Client().call('api/auth/login',{'role':'guest','username':'tamu','password':'salah'},status=401)
    Client().call('api/auth/login',{'role':'teacher','username':'tamu','password':'tamu123'},status=401)
    assert default_guest.call('api/auth/session')['user']['role']=='guest'
    default_guest.call('api/auth/password',{'password':'changed123','confirmation':'changed123'},status=403)
    for day in ['Senin','Selasa','Rabu','Kamis','Jumat']:
        sql(f"INSERT INTO jadwal_kelas(kelas_id,mata_pelajaran_id,guru_id,hari,jam_mulai,jam_selesai) VALUES({classes[0]},{subjects[0]},{teachers[0]},'{day}','07:30:00','09:00:00')")
    today=datetime.datetime.now(ZoneInfo('Asia/Jakarta')).strftime('%Y-%m-%d')
    semester='Genap' if int(today[5:7])<=6 else 'Ganjil'
    sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,semester,status,dicatat_oleh) VALUES({pupils[0]},{classes[0]},'{today}','{semester}','Sakit',{users[0]})")
    sql(f"INSERT INTO notifikasi(pengguna_id,judul,isi,jenis,dibuat_pada) VALUES({users[0]},'Info Beranda Fixture','Pesan hari ini <img src=x onerror=alert(1)>','Info','{today} 07:00:00'),({users[0]},'Info Lama Fixture','Notifikasi lama','Info','2026-01-01 07:00:00'),({users[1]},'Info Kelas Lain','Tidak boleh muncul','Info','{today} 07:00:00')")
    data={'kelas_id':int(classes[0]),'judul':'Agenda Terbit <img src=x onerror=alert(1)>','isi':'Informasi umum untuk tamu','tanggal_agenda':'2026-08-19','terbit':True}
    published=wali.call('api/pengumuman-tamu',data,status=201)['id']
    draft=wali.call('api/pengumuman-tamu',dict(data,judul='Draf Rahasia',terbit=False),status=201)['id']
    outsider.call('api/pengumuman-tamu?kelas_id='+classes[0],status=403)
    outsider.call('api/pengumuman-tamu/'+str(published),data,method='PUT',status=403)
    outsider.call('api/pengumuman-tamu/'+str(published),method='DELETE',status=403)
    assert len(wali.call('api/pengumuman-tamu?kelas_id='+classes[0])['data'])==2
    assert len(guest.call('api/tamu?kelas_id='+classes[0])['jadwal'])==5
    payload=guest.call('api/tamu?kelas_id='+classes[0]+'&bulan=8&tahun=2026')
    assert payload['jumlah_siswa']==2 and sum(payload['kehadiran'].values())==2
    assert len(payload['kalender'])==31
    assert payload['kalender'][16]['status']=='Libur' and payload['kalender'][17]['status']=='Libur' and payload['kalender'][24]['status']=='Libur'
    assert payload['kalender'][26]['status']=='PJJ' and payload['kalender'][27]['status']=='PJJ'
    assert payload['kalender'][18]['agenda']==[data['judul']]
    assert len(payload['pengumuman'])==1 and payload['pengumuman'][0]['judul']==data['judul']
    assert payload['beranda']['students'][0][0]=='Siswa Jurnal 0'
    assert payload['beranda']['students'][0][5:7]==['','']
    assert [n['judul'] for n in payload['beranda']['notifications']]==['Info Beranda Fixture']
    assert set(payload['beranda']['notifications'][0])=={'judul','isi','dibuat_pada'}
    public=json.dumps({key:value for key,value in payload.items() if key!='beranda'})
    for private in ['Siswa Jurnal','Draf Rahasia','nomor_hp','kata_sandi','siswa_id','"nis":','pengguna_id']:assert private not in public,(private,public)
    for private in ['nomor_hp','kata_sandi','pengguna_id','nama_ayah','nama_ibu','Info Kelas Lain','Info Lama Fixture']:assert private not in json.dumps(payload),private
    assert guest.call('api/tamu?kelas_id='+classes[1])['pengumuman']==[]
    for invalid in ['bulan=13','tahun=2025','bulan[]=8','kelas_id[]=1','kelas_id=0']:guest.call('api/tamu?'+invalid,status=422)
    guest.call('api/tamu?kelas_id=999999999',status=404);Client().call('api/tamu',status=401)
    protected=['api/jurnal?kelas_id='+classes[0],'api/kelas','api/tahun-ajaran','api/notifikasi','api/siswa','api/siswa/dashboard','api/absensi?kelas_id='+classes[0],'api/absensi/rekap-kelas?kelas_id='+classes[0]+'&tahun=2026','api/kedisiplinan?kelas_id='+classes[0],'api/akademik?kelas_id='+classes[0],'api/pesan','api/guru','api/pengajuan-sakit','api/pengajuan-izin','api/pengumuman-tamu?kelas_id='+classes[0],'api/kelola-akun']
    for endpoint in protected:guest.call(endpoint,status=403)
    for method,path in [('POST','api/pengumuman-tamu'),('PUT','api/pengumuman-tamu/'+str(published)),('DELETE','api/pengumuman-tamu/'+str(published)),('POST','api/absensi'),('POST','api/notifikasi'),('PATCH','api/notifikasi/baca-semua')]:guest.call(path,data,method=method,status=403)
    bootstrap=guest.call('api/bootstrap');assert bootstrap['legacyStudents']==[] and bootstrap['studentDetails']==[] and bootstrap['classes']==[]
    wali.call('api/pengumuman-tamu/'+str(published),dict(data,terbit=False),method='PUT',status=201)
    assert guest.call('api/tamu?kelas_id='+classes[0])['pengumuman']==[]
    wali.call('api/pengumuman-tamu/'+str(published),data,method='PUT',status=201)
    sql('UPDATE pengguna SET aktif=0 WHERE id='+users[-1]);guest.call('api/tamu',status=401)
    assert guest.call('api/auth/session')['authenticated'] is False
    sql('UPDATE pengguna SET aktif=1 WHERE id='+users[-1]);guest.login('guest',tag+'_guest')
    print('PASS: Guest login, scoped read-only home data, aggregate counts, schedule/calendar, announcements, forbidden private routes/writes/password change and disabled-session rejection.')
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
        c.wait("document.readyState==='complete'&&typeof authRequest==='function'&&!!window.AIWalasDashboardSummary&&document.querySelector('#loginRole option[value=guest]')?.textContent==='Tamu'")
        credentials=json.dumps({'role':'guest','username':'tamu','password':'tamu123'})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(d=>finishLogin(d.user))")
        c.wait("!!document.getElementById('guestClassSelect')")
        c.js("document.getElementById('guestClassSelect').value='"+classes[0]+"';document.getElementById('guestClassSelect').dispatchEvent(new Event('change'))")
        c.wait("document.querySelector('.guest-dashboard .activity-list')?.textContent.includes('Info Beranda Fixture')")
        assert c.js("document.querySelector('.guest-dashboard h1').textContent")=='Selamat Datang di aiwalas'
        assert c.js("document.querySelectorAll('.guest-dashboard .grid-stats>.stat-card').length")==4
        c.wait("!!document.querySelector('.guest-dashboard .analytics-svg')")
        guest_cards=c.js("[...document.querySelectorAll('.guest-dashboard h2')].map(n=>n.textContent)")
        assert guest_cards==['Ringkasan Kelas','Siswa Tidak Hadir Hari Ini','Notifikasi terbaru'],guest_cards
        assert c.js("[...document.querySelectorAll('.guest-dashboard [data-page],.guest-dashboard .date-control')].every(n=>n.disabled)")
        c.js("document.querySelector('[data-summary-tab=schedule]').click()")
        assert c.js("document.querySelector('[data-summary-view=schedule]').classList.contains('active')")
        assert c.js("document.querySelectorAll('.summary-schedule-row').length")==1
        c.js("document.querySelector('[data-summary-tab=waiting]').click()")
        assert 'Siswa Jurnal 1' in c.js("document.querySelector('[data-summary-view=waiting]').textContent")
        for width in [1440,390]:
            c.call('Emulation.setDeviceMetricsOverride',{'width':width,'height':844,'deviceScaleFactor':1,'mobile':width==390})
            assert c.js('document.documentElement.scrollWidth<=innerWidth'),width
        c.call('Emulation.clearDeviceMetricsOverride')
        menus=c.js("[...document.querySelectorAll('.sidebar [data-page]')].filter(n=>n.getClientRects().length).map(n=>n.dataset.page)")
        assert menus==['dashboard','guestSchedule','guestAnnouncements','guestCalendar'],menus
        assert not c.js("!!document.querySelector('.guest-page img')")
        for page in ['guestSchedule','guestAnnouncements','guestCalendar']:
            c.js("document.querySelector('.sidebar [data-page="+page+"]').click()")
            c.wait("document.getElementById('breadcrumbCurrent').textContent==="+json.dumps({'guestSchedule':'Jadwal Mapel','guestAnnouncements':'Pengumuman','guestCalendar':'Kalender Akademik'}[page]))
            assert c.js("document.querySelectorAll('.guest-page button').length")==0
            assert 'Draf Rahasia' not in c.js("document.querySelector('.guest-page').textContent")
            if page=='guestSchedule':
                for day in ['Senin','Selasa','Rabu','Kamis','Jumat']:assert day in c.js("document.querySelector('.guest-page').textContent")
                assert c.js("document.querySelectorAll('.guest-schedule-list article').length")==5
            if page=='guestAnnouncements':assert not c.js("!!document.querySelector('.guest-page img')")
        c.js("document.getElementById('guestMonth').value='8';document.getElementById('guestMonth').dispatchEvent(new Event('change'))")
        c.wait("document.querySelector('.guest-calendar-table')?.textContent.includes('19 Agustus 2026')")
        assert 'Agenda Terbit' in c.js("document.querySelector('.guest-calendar-table').textContent")
        for width in [1440,390]:
            c.call('Emulation.setDeviceMetricsOverride',{'width':width,'height':844,'deviceScaleFactor':1,'mobile':width==390});assert c.js('document.documentElement.scrollWidth<=innerWidth')
        c.call('Emulation.clearDeviceMetricsOverride')
        c.js("window.page='grades';render()");c.wait("document.getElementById('breadcrumbCurrent').textContent==='Beranda'")
        credentials=json.dumps({'role':'walas','username':tag+'_wali0','password':password})
        c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(()=>authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")})).then(d=>finishLogin(d.user)).then(()=>authRequest('api/bootstrap?kelas_id="+classes[0]+"')).then(d=>{applyBootstrapData(d);applyRoleAccess();window.page='dashboard';render()})")
        c.wait("!!document.querySelector('.summary-panel')&&!document.querySelector('.guest-dashboard')")
        assert c.js("[...document.querySelectorAll('#pageContent h2')].map(n=>n.textContent)")==guest_cards
        assert c.js("document.querySelector('#pageContent h1').textContent.includes('Wali Jurnal')")
        c.js("window.page='publicAnnouncements';render()")
        c.wait("!!document.querySelector('[data-ga-edit]')")
        c.js("document.getElementById('newGuestAnnouncement').click()")
        assert c.js("document.getElementById('gaPublished').checked") is False
        c.js("document.getElementById('gaTitle').value='UI Publikasi';document.getElementById('gaBody').value='Draf untuk diuji';document.getElementById('guestAnnouncementForm').requestSubmit()")
        c.wait("!document.getElementById('modalBackdrop').classList.contains('show')")
        c.wait("document.getElementById('publicAnnouncementList')?.textContent.includes('UI Publikasi')")
        assert not any(r['judul']=='UI Publikasi' for r in guest.call('api/tamu?kelas_id='+classes[0])['pengumuman'])
        assert c.js("fetch('assets/guest-portal.js').then(r=>r.text()).then(s=>{new Function(s);return true})")
        assert not [e for e in c.events if e.get('method')=='Runtime.exceptionThrown'],c.events
        print('PASS: Shared Wali/guest home cards, exact guest greeting, chart, read-only controls, view tabs, mobile layout, four menus, XSS escaping, account switching and Wali editing preserved.')
finally:
    if c:
        try:c.js("closeModal();authRequest('api/auth/logout',{method:'POST',body:'{}'})")
        except Exception:pass
    if classes:
        ids=','.join(classes);sql(f"DELETE FROM jadwal_kelas WHERE kelas_id IN ({ids});DELETE FROM pengumuman_tamu WHERE kelas_id IN ({ids});DELETE FROM pengguna WHERE siswa_id IN (SELECT id FROM siswa WHERE kelas_id IN ({ids}));DELETE FROM absensi WHERE kelas_id IN ({ids});DELETE FROM catatan_kedisiplinan WHERE kelas_id IN ({ids});DELETE FROM pengampu_mapel WHERE kelas_id IN ({ids});DELETE FROM siswa WHERE kelas_id IN ({ids});DELETE FROM kelas WHERE id IN ({ids});")
    if users:sql('DELETE FROM notifikasi WHERE pengguna_id IN ('+','.join(users)+');DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if teachers:sql('DELETE FROM guru WHERE id IN ('+','.join(teachers)+')')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
