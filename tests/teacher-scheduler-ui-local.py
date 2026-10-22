"""Local API/browser checks for scheduler status. No sends or worker activation.
Run: AIWALAS_BROWSER_TEST=1 python3 tests/teacher-scheduler-ui-local.py
"""
import datetime,http.cookiejar,json,os,re,secrets,shutil,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP=shutil.which('php') or '/opt/homebrew/opt/php@8.3/bin/php';MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
prefix='scheduler_'+secrets.token_hex(5);password=secrets.token_hex(16);hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
users=[];teacher=None;subject=None;year=None;class_id=None
class Client:
    def __init__(self):
        self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        html=self.op.open(BASE).read().decode();self.token=re.search(r"name=['\"]csrf-token['\"] content=['\"]([^'\"]+)",html).group(1)
    def call(self,path,data=None,status=200):
        req=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),headers={'Content-Type':'application/json','X-CSRF-TOKEN':self.token})
        try:r=self.op.open(req,timeout=10)
        except urllib.error.HTTPError as e:r=e
        result=json.load(r);assert r.code==status,(path,r.code,result);self.token=result.get('csrfHash',self.token);return result
try:
    for i,role in enumerate(['wali_kelas','wali_kelas','guest']):users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_{i}','{hashed}','Scheduler test {i}','{role}');SELECT LAST_INSERT_ID();"))
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{prefix}','Ganjil',1);SELECT LAST_INSERT_ID();")
    class_id=sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}',11,'Test',{year},{users[0]});SELECT LAST_INSERT_ID();")
    teacher=sql(f"INSERT INTO guru(nama_lengkap,nama_panggilan,jenis_kelamin,nomor_hp,aktif) VALUES('Scheduler Ruby','Pak Ruby','L','081234567890',1);SELECT LAST_INSERT_ID();")
    subject=sql(f"INSERT INTO mata_pelajaran(nama_mata_pelajaran,aktif) VALUES('{prefix}',1);SELECT LAST_INSERT_ID();")
    weekday=['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'][datetime.datetime.now(ZoneInfo('Asia/Jakarta')).weekday()]
    sql(f"INSERT INTO jadwal_kelas(kelas_id,mata_pelajaran_id,guru_id,hari,jam_mulai,jam_selesai) VALUES({class_id},{subject},{teacher},'{weekday}','08:00:00','09:00:00');")
    endpoint='api/whatsapp/teacher-schedule?kelas_id='+class_id
    anonymous=Client();anonymous.call(endpoint,status=401)
    a=Client();a.call('api/auth/login',{'role':'walas','username':prefix+'_0','password':password})
    data=a.call(endpoint);assert data['enabled'] is False and len(data['data'])==2 and len(data['schedule'])==1
    assert data['data'][0]['due_at'].endswith('07:50:00') and data['data'][1]['due_at'].endswith('09:00:00')
    assert 'api_key' not in json.dumps(data) and 'nomor_hp' not in json.dumps(data)
    assert data['excluded_teachers']==['Wahyu Lukman Hakim','Putu Arya Ranesda','Ruby Eka Prawira']
    for excluded_name in ['Wahyu Lukman Hakim, S.Kom.','Putu Arya Ranesda, S.Kom.','Ruby Eka Prawira, S.Pd.']:
        sql("UPDATE guru SET nama_lengkap='"+excluded_name+"' WHERE id="+teacher)
        excluded_status=a.call(endpoint)
        assert not excluded_status['data'] and len(excluded_status['schedule'])==1
    sql("UPDATE guru SET nama_lengkap='Scheduler Ruby' WHERE id="+teacher)
    other=Client();other.call('api/auth/login',{'role':'walas','username':prefix+'_1','password':password});other.call(endpoint,status=404)
    guest=Client();guest.call('api/auth/login',{'role':'guest','username':prefix+'_2','password':password});guest.call(endpoint,status=403)
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Page.addScriptToEvaluateOnNewDocument',{'source':'window.__openedWindows=0;window.open=function(){window.__openedWindows++;return null};'})
        c.call('Page.navigate',{'url':BASE});c.wait("typeof authRequest==='function' && !!window.AIWalasPages")
        credentials=json.dumps({'role':'walas','username':prefix+'_0','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(function(d){return finishLogin(d.user)}).then(function(){return authRequest('api/bootstrap?kelas_id="+class_id+"')}).then(function(d){applyBootstrapData(d);window.page='schedule';window.render()})")
        c.wait("!!document.getElementById('refreshTeacherAutomation')")
        text=c.js("document.getElementById('teacherReminderSchedulePanel').textContent")
        assert 'Pengiriman otomatis belum diaktifkan' in text and '07:50' in text and '09:00' in text and 'Scheduler Ruby' in text
        assert prefix in c.js("document.querySelector('#pageContent .schedule-list').textContent")
        assert c.js("window.__openedWindows")==0
        c.js("document.getElementById('refreshTeacherAutomation').click()")
        c.wait("!!document.getElementById('refreshTeacherAutomation')")
        assert c.js("document.querySelectorAll('#teacherReminderSchedulePanel').length")==1
        sends=[e for e in c.events if e.get('method')=='Network.requestWillBeSent' and '/api/whatsapp/send' in e['params']['request']['url']]
        errors=[e for e in c.events if e.get('method')=='Runtime.exceptionThrown']
        assert not sends and not errors,(sends,errors)
        c.js("window.page='dashboard';window.render()")
        print('PASS: Browser scheduler state/times, database schedule, refresh without duplicate panels, no WhatsApp popup or send request.')
    assert sql('SELECT COUNT(*) FROM teacher_schedule_dispatches WHERE kelas_id='+class_id)=='0'
    assert sql('SELECT COUNT(*) FROM pesan_komunikasi WHERE kelas_id='+class_id)=='0'
    print('PASS: Read-only scheduler API, disabled default, correct H-10/end times, owner and role isolation. No sends.')
finally:
    if class_id:sql('DELETE FROM jadwal_kelas WHERE kelas_id='+class_id+';DELETE FROM kelas WHERE id='+class_id+';')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
    if teacher:sql('DELETE FROM guru WHERE id='+teacher)
    if subject:sql('DELETE FROM mata_pelajaran WHERE id='+subject)
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
