"""Local API/browser checks for monthly class/individual attendance recap. Disposable fixtures only.
Run: AIWALAS_BROWSER_TEST=1 python3 tests/class-attendance-recap-local.py
"""
import time,datetime,http.cookiejar,json,os,re,secrets,shutil,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP=shutil.which('php') or '/opt/homebrew/opt/php@8.3/bin/php';MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
prefix='recap_'+secrets.token_hex(5);password=secrets.token_hex(16);hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
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
    for i in range(2):users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_{i}','{hashed}','Wali Rekap {i}','wali_kelas');SELECT LAST_INSERT_ID();"))
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{prefix}','Ganjil',1);SELECT LAST_INSERT_ID();")
    for i in range(3):classes.append(sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}_{i}',11,'Test',{year},{users[1] if i==1 else users[0]});SELECT LAST_INSERT_ID();"))
    for i,name in enumerate(['Siswa <b>Uji</b>','=FORMULA','Siswa Nonaktif','Siswa Kelas Lain']):
        pupils.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{prefix}_{i}','{name}','L',{classes[1] if i==3 else classes[0]},{0 if i==2 else 1});SELECT LAST_INSERT_ID();"))
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{prefix}_student','{hashed}','Siswa Uji','siswa','{prefix}_0');SELECT LAST_INSERT_ID();"))
    users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{prefix}_secretary','{hashed}','Sekretaris Rekap','sekretaris','{prefix}_1');SELECT LAST_INSERT_ID();"))
    records=[(0,'2026-01-01','Sakit','Genap'),(0,'2026-01-31','Izin','Genap'),(0,'2026-02-01','Alpha','Genap'),(0,'2026-07-01','Terlambat','Ganjil'),(0,'2026-12-31','Sakit','Ganjil'),(0,'2027-01-01','Alpha','Genap'),(0,'2026-01-01','Sakit','Ganjil'),(1,'2026-01-01','Izin','Genap'),(1,'2026-10-02','Alpha','Ganjil'),(1,'2026-10-03','PJJ','Ganjil'),(1,'2026-10-04','Menunggu keterangan','Ganjil'),(1,'2026-10-05','Hadir','Ganjil'),(2,'2026-10-01','Sakit','Ganjil'),(3,'2026-10-01','Terlambat','Ganjil')]
    records += [(0,'2026-08-17','Alpha','Ganjil'),(0,'2026-08-18','Terlambat','Ganjil'),(1,'2026-08-17','Sakit','Ganjil'),(1,'2026-08-18','Izin','Ganjil'),(0,'2026-08-25','Terlambat','Ganjil'),(1,'2026-08-25','Alpha','Ganjil')]
    records += [(0,'2026-08-27','Alpha','Ganjil'),(0,'2026-08-28','Terlambat','Ganjil'),(1,'2026-08-27','Sakit','Ganjil'),(1,'2026-08-28','Izin','Ganjil')]
    records += [(0,'2026-09-07','Alpha','Ganjil'),(0,'2026-09-08','Terlambat','Ganjil'),(1,'2026-09-07','Sakit','Ganjil'),(1,'2026-09-08','Izin','Ganjil')]
    for i,date,status,semester in records:sql(f"INSERT INTO absensi(siswa_id,kelas_id,tanggal,status,semester,dicatat_oleh) VALUES({pupils[i]},{classes[1] if i==3 else classes[0]},'{date}','{status}','{semester}',{users[0]});")
    wali=Client();wali.call('api/auth/login',{'role':'walas','username':prefix+'_0','password':password})
    other=Client();other.call('api/auth/login',{'role':'walas','username':prefix+'_1','password':password})
    student=Client();student.call('api/auth/login',{'role':'student','username':prefix+'_student','password':password})
    parent=Client();parent.call('api/auth/login',{'role':'parent','username':prefix+'_0','password':prefix+'_0'})
    endpoint='api/absensi/rekap-kelas?kelas_id='+classes[0]+'&tahun=2026&semester='
    secretary=Client();secretary.call('api/auth/login',{'role':'assistant','username':prefix+'_secretary','password':password})
    bootstrap=secretary.call('api/bootstrap?kelas_id='+classes[1])
    assert str(bootstrap['selectedClassId'])==classes[0] and len(bootstrap['classes'])==1
    anonymous=Client();anonymous.call(endpoint+'Ganjil',status=401)
    student.call(endpoint+'Ganjil',status=403);parent.call(endpoint+'Ganjil',status=403);other.call(endpoint+'Ganjil',status=404)
    wali.call('api/absensi/rekap-kelas?kelas_id='+classes[1],status=404)
    expected={'sakit':2,'izin':0,'alpha':1,'terlambat':1}
    ganjil=wali.call(endpoint+'Ganjil');assert ganjil['total']==expected and len(ganjil['bulanan'])==12 and len(ganjil['siswa'])==2,ganjil
    assert ganjil['bulanan'][0]['sakit']==1 and ganjil['bulanan'][11]['sakit']==1 and ganjil['bulanan'][6]['terlambat']==1
    assert ganjil['bulanan'][9]['alpha']==1 and ganjil['bulanan'][9]['sakit']==0
    secretary_recap=secretary.call(endpoint+'Ganjil')
    assert secretary_recap['total']==ganjil['total'] and secretary_recap['bulanan']==ganjil['bulanan'] and secretary_recap['siswa']==ganjil['siswa']
    secretary.call('api/absensi/rekap-kelas?kelas_id='+classes[1],status=404)
    secretary.call('api/absensi/rekap-kelas?kelas_id='+classes[2],status=404)
    genap=wali.call(endpoint+'Genap');assert genap['total']=={'sakit':1,'izin':2,'alpha':1,'terlambat':0}
    assert genap['bulanan'][0]['izin']==2 and genap['bulanan'][1]['alpha']==1
    assert wali.call('api/absensi/rekap-kelas?kelas_id='+classes[0]+'&tahun=2027&semester=Genap')['total']['alpha']==1
    zero={'sakit':0,'izin':0,'alpha':0,'terlambat':0}
    assert wali.call('api/absensi/rekap-kelas?kelas_id='+classes[0]+'&tahun=2028')['total']==zero
    empty=wali.call('api/absensi/rekap-kelas?kelas_id='+classes[2]);assert empty['siswa']==[] and empty['total']==zero
    for invalid in ['2025','9999','abc','2026-01','2026.0']:wali.call('api/absensi/rekap-kelas?kelas_id='+classes[0]+'&tahun='+invalid,status=422)
    for query in ['kelas_id[]=1','kelas_id=0','kelas_id='+classes[0]+'&tahun[]=2026','kelas_id='+classes[0]+'&semester[]=Ganjil','kelas_id='+classes[0]+'&semester=invalid']:wali.call('api/absensi/rekap-kelas?'+query,status=422)
    for date in ['2026-08-17','2026-08-18','2026-08-25']:
        for client in [wali,secretary,student,parent]:
            result=client.call('api/absensi?kelas_id='+classes[0]+'&tanggal='+date+'&semester=Ganjil')
            assert result['kalender']['hari_libur'] is True,result
            assert result['data'] and all(r['status']=='Libur' and r['waktu_scan'] is None for r in result['data']),result
        wali.call('api/absensi',{'siswa_id':int(pupils[0]),'kelas_id':int(classes[0]),'tanggal':date,'semester':'Ganjil','status':'Hadir'},status=422)
    for date in ['2026-08-27','2026-08-28','2026-09-07','2026-09-08']:
        for client in [wali,secretary,student,parent]:
            result=client.call('api/absensi?kelas_id='+classes[0]+'&tanggal='+date+'&semester=Ganjil')
            assert result['kalender']['hari_libur'] is False and result['kalender']['status']=='PJJ',result
            assert result['data'] and all(r['status']=='PJJ' and r['waktu_scan'] is None for r in result['data']),result
        corrected=wali.call('api/absensi',{'siswa_id':int(pupils[0]),'kelas_id':int(classes[0]),'tanggal':date,'semester':'Ganjil','status':'Alpha','waktu_scan':'06:50:00'})
        assert corrected['data']['status']=='PJJ' and corrected['data']['waktu_scan'] is None,corrected
    for client in [student,parent]:
        august=client.call('api/siswa/rekap-absensi?bulan=8&tahun=2026')
        assert len(august['data'])==5 and all(r['status']==('PJJ' if r['tanggal'] in ['2026-08-27','2026-08-28'] else 'Libur') and r['waktu_scan'] is None for r in august['data']),august
        september=client.call('api/siswa/rekap-absensi?bulan=9&tahun=2026')
        assert len(september['data'])==2 and all(r['status']=='PJJ' and r['waktu_scan'] is None for r in september['data']),september
        overview=client.call('api/siswa/dashboard')['data']
        assert all(r['tanggal'] not in ['2026-08-17','2026-08-18','2026-08-25','2026-08-27','2026-08-28','2026-09-07','2026-09-08'] for r in overview['keterlambatan']['riwayat']),overview
    report=wali.call('api/laporan?kelas_id='+classes[0]+'&semester=Ganjil')['data']['siswa']
    own=next(r for r in report if str(r['id'])==pupils[0])
    assert int(own['sakit'])==2 and int(own['alpha'])==0 and int(own['izin'])==0,own
    discipline=wali.call('api/kedisiplinan?kelas_id='+classes[0])['data']
    own=next(r for r in discipline if str(r['id'])==pupils[0])
    assert own['keterlambatan']['jumlah']==1 and '2026-08-18' not in own['tanggal_pelanggaran']['terlambat'],own
    # Original records remain intact; calendar overrides reads and aggregate counts only.
    assert sql('SELECT status FROM absensi WHERE siswa_id='+pupils[0]+" AND tanggal='2026-08-17'")=='Alpha'
    today=datetime.datetime.now(ZoneInfo('Asia/Jakarta')).date()
    correction={'siswa_id':int(pupils[0]),'kelas_id':int(classes[0]),'tanggal':str(today),'semester':'Ganjil','status':'Sakit','waktu_scan':None}
    # Existing seeded dates differ from today; changing attendance must update the recap.
    wali.call('api/absensi',correction,status=201)
    assert wali.call(endpoint+'Ganjil')['total']['sakit']==3
    print('PASS: Real monthly class/student counts, Jan/Dec/year boundaries, semester isolation, inactive/foreign students, zero months, own-class/role restrictions and daily attendance corrections.')
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
        c.wait("typeof authRequest==='function' && !!window.AIWalasPages?.attendanceRecapPage")
        credentials=json.dumps({'role':'walas','username':prefix+'_0','password':password})
        c.js("localStorage.setItem('aiwalas.semester','Ganjil');authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(d=>finishLogin(d.user)).then(()=>authRequest('api/bootstrap?kelas_id="+classes[0]+"')).then(d=>{applyBootstrapData(d);window.page='attendanceRecapPage';window.render()})")
        c.wait("document.getElementById('classRecapIdentity')?.textContent.includes('Tahun 2026')")
        assert c.js("getComputedStyle(document.querySelector('.sidebar [data-page=attendanceRecapPage]')).display")!='none'
        assert c.js("document.querySelectorAll('#classRecapMonthlyRows tr').length")==13
        assert not c.js("!!document.querySelector('#classRecapStudentRows b')")
        c.js("document.getElementById('classRecapMonth').value='1';document.getElementById('classRecapMonth').dispatchEvent(new Event('change'))")
        selector=json.dumps('[data-recap-student="'+pupils[0]+'"]')
        values=c.js("Array.from(document.querySelector("+selector+").closest('tr').children).map(el=>el.textContent)")
        assert values[3:8]==['1','0','0','0','1'],values
        c.js("document.querySelector("+selector+").click()")
        assert not c.js("document.getElementById('classRecapStudentLabel').hidden")
        assert c.js("document.getElementById('classRecapRoster').hidden")
        assert c.js("document.getElementById('classRecapCaption').textContent").startswith('Siswa <b>Uji</b>')
        c.wait("typeof XLSX!=='undefined' && typeof XLSX.writeFile==='function'")
        assert c.js("document.getElementById('exportClassRecap').textContent")=='Export Excel'
        c.js("XLSX.writeFile=function(book,name){window.__recapBook=book;window.__recapFileName=name;window.__recapExcel=XLSX.write(book,{type:'base64',bookType:'xlsx',compression:true})};document.getElementById('exportClassRecap').click()")
        assert c.js('window.__recapBook.SheetNames')==['Rekap Individu']
        assert c.js('window.__recapFileName').endswith('.xlsx')
        import base64,io,zipfile,xml.etree.ElementTree as ET
        with zipfile.ZipFile(io.BytesIO(base64.b64decode(c.js('window.__recapExcel')))) as workbook:
            assert 'xl/workbook.xml' in workbook.namelist()
            xml=ET.fromstring(workbook.read('xl/worksheets/sheet1.xml'))
            ns={'s':'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
            assert xml.find('s:autoFilter',ns) is not None and xml.find('s:cols',ns) is not None
            formulas=[node.text for node in xml.findall('.//s:f',ns)]
            assert len(formulas)==4 and all(value.startswith('SUM(') for value in formulas),formulas
        assert c.js("window.__recapBook.Sheets['Rekap Individu'].B6.t")=='s'
        c.js("document.querySelector('[data-recap-mode=kelas]').click();document.getElementById('exportClassRecap').click()")
        assert c.js('window.__recapBook.SheetNames')==['Rekap Kelas','Rincian Bulan','Data Bulanan Siswa']
        cells=c.js("Object.values(window.__recapBook.Sheets['Rincian Bulan']).filter(cell=>cell && cell.v==='=FORMULA').map(cell=>({type:cell.t,formula:cell.f||null}))")
        assert cells==[{'type':'s','formula':None}],cells
        assert c.js("window.__recapBook.Sheets['Rincian Bulan'].A8.t")=='s'
        flat=c.js("XLSX.utils.sheet_to_json(window.__recapBook.Sheets['Data Bulanan Siswa'],{header:1,range:5})")
        assert len(flat)==26 and flat[0][0]=='NIS' and flat[0][4]=='Sakit',flat
        assert sum(row[4] for row in flat[1:-1])==3
        c.js("document.getElementById('classRecapYear').value='2028';document.getElementById('classRecapYear').dispatchEvent(new Event('change'))")
        c.wait("document.getElementById('classRecapIdentity')?.textContent.includes('2028')")
        assert c.js("Array.from(document.querySelectorAll('#classRecapStats .stat-number')).map(el=>el.textContent)")==['0','0','0','0']
        c.js("document.getElementById('classRecapYear').value='2026';document.getElementById('classRecapYear').dispatchEvent(new Event('change'))")
        c.wait("document.getElementById('classRecapIdentity')?.textContent.includes('Tahun 2026')")
        wali.call('api/absensi',dict(correction,status='Izin'))
        c.js("document.getElementById('refreshClassRecap').click()")
        c.wait("document.querySelectorAll('#classRecapStats .stat-number')[1]?.textContent==='1'")
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js("document.documentElement.scrollWidth<=window.innerWidth"),'Monthly recap mobile overflow'
        c.call('Emulation.clearDeviceMetricsOverride')
        c.js("window.page='attendance';window.render()")
        c.wait("!!document.getElementById('attendanceDetailRecap')")
        c.js("document.getElementById('attendanceDetailRecap').click()")
        c.wait("!!document.getElementById('classRecapYear')")
        assert c.js('window.page')=='attendanceRecapPage'
        c.js("window.page='attendance';window.render();document.getElementById('attendanceRecap').click()")
        c.wait("document.getElementById('classRecapStudentLabel')?.hidden===false")
        c.js("window.page='settings';window.render()")
        wali_settings=c.js("Array.from(document.querySelectorAll('.settings-card .panel-head h2')).map(el=>el.textContent)")
        secretary_creds=json.dumps({'role':'assistant','username':prefix+'_secretary','password':password})
        c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(()=>authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+secretary_creds+")})).then(d=>finishLogin(d.user))")
        c.wait("window.currentRole==='assistant' && !!document.querySelector('.sidebar [data-page=attendanceRecapPage]')")
        assert c.js("getComputedStyle(document.querySelector('.sidebar [data-page=attendanceRecapPage]')).display")!='none'
        assert c.js("getComputedStyle(document.querySelector('.sidebar-bottom [data-page=settings]')).display")!='none'
        assert c.js("getComputedStyle(document.getElementById('semesterSelect').closest('.academic-year-row')).display")!='none'
        c.js("document.querySelector('.sidebar [data-page=attendanceRecapPage]').click()")
        c.wait("!!document.querySelector('#classRecapMonthlyRows .class-recap-total')")
        assert c.js("document.querySelectorAll('#classRecapMonthlyRows tr').length")==13
        c.js("document.querySelector('[data-recap-mode=kelas]').click();document.getElementById('exportClassRecap').click()")
        assert c.js('window.__recapBook.SheetNames')==['Rekap Kelas','Rincian Bulan','Data Bulanan Siswa']
        c.js("document.getElementById('semesterSelect').value='Genap';document.getElementById('semesterSelect').dispatchEvent(new Event('change'))")
        c.wait("document.getElementById('classRecapIdentity')?.textContent.includes('Semester Genap')")
        assert c.js("Array.from(document.querySelectorAll('#classRecapStats .stat-number')).map(el=>el.textContent)")==['1','2','1','0']
        c.js("document.querySelector('.sidebar-bottom [data-page=settings]').click()")
        assert c.js("Array.from(document.querySelectorAll('.settings-card .panel-head h2')).map(el=>el.textContent)")==wali_settings
        assert c.js("document.querySelectorAll('.settings-card').length")==4
        assert 'Sekretaris Kelas' in c.js("document.querySelector('.settings-profile').textContent")
        c.js("document.getElementById('newPassword').value='Secretary-Test-New';document.getElementById('confirmPassword').value='Secretary-Test-New';document.getElementById('savePassword').click()")
        c.wait("window.currentRole===null")
        changed=Client();changed.call('api/auth/login',{'role':'assistant','username':prefix+'_secretary','password':'Secretary-Test-New'})
        same_student=Client();same_student.call('api/auth/login',{'role':'student','username':prefix+'_1','password':password})
        print('PASS: Secretary own-class recap matches wali, Excel export and semester switch work, Settings has identical cards and can change the secretary password independently.')
        c.js('logout()')
        print('PASS: Class/individual tabs, monthly roster, 12-month student recap, real XLSX export, separate sheets, numeric counts, text NIS, filters, SUM formulas, year filter, refresh after corrections, daily-page links and mobile layout.')
finally:
    if classes:
        ids=','.join(classes)
        sql('DELETE FROM absensi WHERE kelas_id IN ('+ids+');')
        if pupils:sql('DELETE FROM pengguna WHERE siswa_id IN ('+','.join(pupils)+') OR nis IN (SELECT nis FROM siswa WHERE id IN ('+','.join(pupils)+'));')
        sql('DELETE FROM siswa WHERE kelas_id IN ('+ids+');DELETE FROM kelas WHERE id IN ('+ids+');')
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
