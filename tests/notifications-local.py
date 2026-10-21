"""Local regression checks for date-filtered notifications; fixtures removed afterwards.
Run: python3 tests/notifications-local.py
Optional UI: AIWALAS_BROWSER_TEST=1 python3 tests/notifications-local.py
"""
import datetime,http.cookiejar,json,os,re,secrets,shutil,subprocess,urllib.request,urllib.error
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/'
PHP=shutil.which('php') or '/opt/homebrew/opt/php@8.3/bin/php'
MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
class Client:
    def __init__(self):self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.token=None
    def request(self,path,data=None,method=None,status=200):
        headers={'Content-Type':'application/json'}
        if self.token:headers['X-CSRF-TOKEN']=self.token
        req=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),method=method,headers=headers)
        try:r=self.op.open(req,timeout=10)
        except urllib.error.HTTPError as e:r=e
        body=r.read();assert r.code==status,(path,r.code,body[:1000])
        parsed=json.loads(body);self.token=parsed.get('csrfHash',self.token);return parsed
    def login(self,name):
        html=self.op.open(BASE).read().decode();self.token=re.search(r"name=['\"]csrf-token['\"] content=['\"]([^'\"]+)",html).group(1)
        self.request('api/auth/login',{'role':'walas','username':name,'password':password})
prefix='notify_'+secrets.token_hex(5);password=secrets.token_hex(16)
hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
users=[]
today=datetime.datetime.now(ZoneInfo('Asia/Jakarta')).date();yesterday=today-datetime.timedelta(days=1);tomorrow=today+datetime.timedelta(days=1)
def notification(user,title,stamp,body='Notification date regression'):
    return sql(f"INSERT INTO notifikasi(pengguna_id,judul,isi,jenis,sudah_dibaca,dibuat_pada) VALUES({user},'{title}','{body}','Test',0,'{stamp}');SELECT LAST_INSERT_ID();")
try:
    for i in range(2):users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_{i}','{hashed}','Notification test {i}','wali_kelas');SELECT LAST_INSERT_ID();"))
    notification(users[0],'TODAY-FIRST',str(today)+' 00:00:00')
    notification(users[0],'TODAY-LAST',str(today)+' 23:59:59','<img src=x onerror=alert(1)>')
    for i in range(31):notification(users[0],'TODAY-'+str(i),str(today)+' 12:00:00')
    notification(users[0],'YESTERDAY',str(yesterday)+' 23:59:59')
    notification(users[0],'TOMORROW',str(tomorrow)+' 00:00:00')
    foreign_id=notification(users[1],'OTHER-USER',str(today)+' 13:00:00')
    a=Client();a.login(prefix+'_0')
    boot=a.request('api/bootstrap');assert len(boot['notifications'])==10
    assert all(row['dibuat_pada'].startswith(str(today)) for row in boot['notifications'])
    first=a.request('api/notifikasi?tanggal='+str(today));assert first['total']==33 and len(first['data'])==30 and first['has_more']
    second=a.request('api/notifikasi?tanggal='+str(today)+'&page=2');assert len(second['data'])==3 and not second['has_more']
    ids={str(n['id']) for n in first['data']+second['data']};assert len(ids)==33 and foreign_id not in ids
    previous=a.request('api/notifikasi?tanggal='+str(yesterday));assert previous['total']==1 and previous['data'][0]['judul']=='YESTERDAY'
    assert a.request('api/notifikasi')['total']==35
    assert a.request('api/notifikasi?tanggal=')['total']==35
    for invalid in ['2026-02-30','2026-13-01','not-a-date','2026-1-01']:
        a.request('api/notifikasi?tanggal='+invalid,status=422)
    a.request('api/notifikasi?tanggal[]=2026-10-21',status=422)
    assert a.request('api/notifikasi?tanggal=2001-01-01')['total']==0
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Page.navigate',{'url':BASE})
        c.wait("typeof authRequest==='function' && !!window.AIWalasPages && !!window.AIWalasPages.notifications")
        credentials=json.dumps({'role':'walas','username':prefix+'_0','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(function(d){return finishLogin(d.user)})")
        c.wait("!!document.querySelector('.dashboard-grid')")
        dashboard=c.js("document.getElementById('pageContent').textContent")
        assert 'YESTERDAY' not in dashboard and 'TOMORROW' not in dashboard and 'TODAY-LAST' in dashboard
        c.js("window.page='notifications';window.render()")
        c.wait("!!document.getElementById('nextNotifications')")
        assert c.js("document.getElementById('notificationDate').value")==str(today)
        assert c.js("document.getElementById('notificationDate').getBoundingClientRect().top>=document.getElementById('markRead').getBoundingClientRect().bottom")
        assert c.js("document.querySelectorAll('#notificationList img').length")==0
        assert c.js("document.getElementById('notificationList').textContent.includes('<img src=x onerror=alert(1)>')")
        c.js("document.getElementById('nextNotifications').click()")
        c.wait("document.getElementById('notificationPagination').textContent.includes('Halaman 2')")
        assert c.js("document.querySelectorAll('#notificationList article').length")==3
        c.js("document.getElementById('notificationDate').value="+json.dumps(str(yesterday))+";document.getElementById('notificationDate').onchange()")
        c.wait("document.getElementById('notificationList').textContent.includes('YESTERDAY')")
        assert 'TODAY-' not in c.js("document.getElementById('notificationList').textContent")
        c.js("document.getElementById('markRead').click()")
        c.wait("document.getElementById('notificationList').textContent.includes('Sudah dibaca')")
        assert c.js("document.getElementById('notificationDate').value")==str(yesterday)
        c.js("document.getElementById('allNotificationDates').click()")
        c.wait("document.getElementById('notificationCount').textContent.includes('35 notifikasi')")
        assert c.js("document.getElementById('notificationDate').value")==''
        c.js("document.getElementById('notificationDate').value='2001-01-01';document.getElementById('notificationDate').onchange()")
        c.wait("document.getElementById('notificationList').textContent.includes('Tidak ada notifikasi pada tanggal ini.')")
        c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
        assert c.js("document.getElementById('notificationDate').getBoundingClientRect().top>=document.getElementById('markRead').getBoundingClientRect().bottom")
        c.call('Emulation.clearDeviceMetricsOverride')
        c.js("window.page='dashboard';window.render()")
        assert 'YESTERDAY' not in c.js("document.getElementById('pageContent').textContent")
        errors=[e for e in c.events if e.get('method')=='Runtime.exceptionThrown'];assert not errors,errors
        print('PASS: Browser today-only dashboard, date control below Mark All Read (desktop/mobile), date changes, pagination, empty state, all dates, preserved selection and escaped notification content.')
    else:a.request('api/notifikasi/baca-semua',{},method='PATCH')
    assert sql('SELECT COUNT(*) FROM notifikasi WHERE pengguna_id='+users[0]+' AND sudah_dibaca=0')=='0'
    assert sql('SELECT sudah_dibaca FROM notifikasi WHERE id='+foreign_id)=='0'
    print('PASS: Jakarta calendar boundaries, all pages accessible, invalid/empty dates, owner isolation and mark-all-read scope.')
finally:
    if users:
        ids=','.join(users);sql('DELETE FROM notifikasi WHERE pengguna_id IN ('+ids+');DELETE FROM pengguna WHERE id IN ('+ids+');')
