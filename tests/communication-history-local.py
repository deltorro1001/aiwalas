"""Local API/browser history regression; no real gateway requests.
Run: AIWALAS_BROWSER_TEST=1 python3 tests/communication-history-local.py
"""
import datetime,http.cookiejar,json,os,re,secrets,shutil,subprocess,time,urllib.request,urllib.error
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/';PHP=shutil.which('php') or '/opt/homebrew/opt/php@8.3/bin/php';MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
prefix='history_'+secrets.token_hex(5);password=secrets.token_hex(16);hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
users=[];class_id=None
class Client:
    def __init__(self):
        self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        html=self.op.open(BASE).read().decode();self.token=re.search(r"name=['\"]csrf-token['\"] content=['\"]([^'\"]+)",html).group(1)
    def call(self,path,data=None,method=None,status=200):
        req=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),method=method,headers={'Content-Type':'application/json','X-CSRF-TOKEN':self.token})
        try:r=self.op.open(req,timeout=10)
        except urllib.error.HTTPError as e:r=e
        result=json.load(r);assert r.code==status,(path,r.code,result);self.token=result.get('csrfHash',self.token);return result
try:
    for i,role in enumerate(['wali_kelas','wali_kelas','guest']):users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_{i}','{hashed}','History test {i}','{role}');SELECT LAST_INSERT_ID();"))
    year=sql('SELECT id FROM tahun_ajaran LIMIT 1')
    class_id=sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}',11,'Test',{year},{users[0]});SELECT LAST_INSERT_ID();")
    def message(state,body,owner=users[0]):
        sent="'2026-10-22 07:00:00'" if state=='Terkirim' else 'NULL'
        return sql(f"INSERT INTO pesan_komunikasi(kelas_id,pengirim,penerima,nomor_tujuan,isi_pesan,kategori,status_pengiriman,dikirim_pada,dibuat_oleh) VALUES({class_id},'AIWalas Otomatis','Guru Test','6281234567890','{body}','Pengingat Guru Otomatis','{state}',{sent},{owner});SELECT LAST_INSERT_ID();")
    sent=message('Terkirim','<img src=x onerror=alert(1)> Sent reminder')
    pending=message('Disiapkan','<img src=x onerror=alert(1)> Pending reminder')
    failed=message('Gagal','Failed reminder')
    sql(f"INSERT INTO teacher_schedule_dispatches(session_key,claim_token,schedule_id,kelas_id,guru_id,lesson_date,event_type,due_at,attempted_at,status,message_id,result_note) VALUES('{secrets.token_hex(32)}','{secrets.token_hex(16)}',0,{class_id},0,'2026-10-22','reminder','2026-10-22 07:50:00','2026-10-22 07:50:00','unknown',{pending},'Hasil gateway belum terkonfirmasi.');")
    for i in range(25):message('Terkirim','Older sent message '+str(i))
    message('Terkirim','Foreign owner message',users[1])
    endpoint='api/pesan?kelas_id='+class_id
    anonymous=Client();anonymous.call(endpoint,status=401)
    a=Client();a.call('api/auth/login',{'role':'walas','username':prefix+'_0','password':password})
    listing=a.call(endpoint);assert listing['total']==28 and len(listing['data'])==25 and listing['has_more']
    assert len(a.call(endpoint+'&page=2')['data'])==3
    sent_rows=a.call(endpoint+'&status=sent');assert sent_rows['total']==26 and all(r['delivery_status']=='Terkirim' for r in sent_rows['data'])
    pending_rows=a.call(endpoint+'&status=pending');assert pending_rows['total']==2 and all(r['delivery_status']=='Pending' for r in pending_rows['data'])
    assert any(r['automation_status']=='unknown' for r in pending_rows['data'])
    assert any('gagal' in r['delivery_note'].lower() for r in pending_rows['data'])
    a.call(endpoint+'&status=invalid',status=422)
    a.call('api/pesan/'+sent+'/dibuka',{},method='PATCH');assert sql('SELECT status_pengiriman FROM pesan_komunikasi WHERE id='+sent)=='Terkirim'
    a.call('api/pesan/'+failed+'/dibuka',{},method='PATCH');assert sql('SELECT status_pengiriman FROM pesan_komunikasi WHERE id='+failed)=='Gagal'
    b=Client();b.call('api/auth/login',{'role':'walas','username':prefix+'_1','password':password});b.call(endpoint,status=404);b.call('api/pesan/'+sent+'/dibuka',{},method='PATCH',status=404)
    guest=Client();guest.call('api/auth/login',{'role':'guest','username':prefix+'_2','password':password});guest.call(endpoint,status=403)
    if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
        from browser_client import CDP
        c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Page.navigate',{'url':BASE})
        c.wait("typeof authRequest==='function' && !!window.AIWalasPages && !!window.AIWalasPages.communication")
        credentials=json.dumps({'role':'walas','username':prefix+'_0','password':password})
        c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")}).then(function(d){return finishLogin(d.user)}).then(function(){return authRequest('api/bootstrap?kelas_id="+class_id+"')}).then(function(d){applyBootstrapData(d);window.page='communication';window.render()})")
        c.wait("!!document.getElementById('nextCommunication')")
        assert c.js("document.querySelectorAll('[data-communication-id]').length")==25
        c.js("document.getElementById('nextCommunication').click()")
        c.wait("document.getElementById('communicationPagination').textContent.includes('Halaman 2')")
        assert c.js("document.querySelectorAll('[data-communication-id]').length")==3
        c.js("document.getElementById('communicationStatus').value='pending';document.getElementById('communicationStatus').onchange()")
        c.wait("document.querySelectorAll('[data-delivery-status=\"Pending\"]').length===2")
        assert c.js("document.querySelectorAll('#communicationHistory img').length")==0
        assert c.js("document.getElementById('communicationHistory').textContent.includes('<img src=x onerror=alert(1)>')")
        sql('UPDATE pesan_komunikasi SET status_pengiriman="Terkirim",dikirim_pada="2026-10-22 07:00:00" WHERE id='+pending)
        sql('UPDATE teacher_schedule_dispatches SET status="sent",result_note="Diterima gateway." WHERE message_id='+pending)
        time.sleep(10.5)
        c.wait("document.querySelectorAll('[data-delivery-status=\"Pending\"]').length===1")
        c.js("document.getElementById('communicationStatus').value='sent';document.getElementById('communicationStatus').onchange()")
        c.wait("document.getElementById('communicationCount').textContent==='27 pesan'")
        assert c.js("document.querySelectorAll('[data-delivery-status=\"Pending\"]').length")==0
        c.js("document.getElementById('showManualMessages').click()")
        assert c.js("document.getElementById('manualCommunication').hidden") is False
        sends=[e for e in c.events if e.get('method')=='Network.requestWillBeSent' and '/api/whatsapp/send' in e['params']['request']['url']]
        errors=[e for e in c.events if e.get('method')=='Runtime.exceptionThrown'];assert not sends and not errors,(sends,errors)
        c.js("window.page='dashboard';window.render()")
        print('PASS: Browser communication history, Terkirim/Pending filters, paging, polling status updates, escaped bodies, manual drafts retained; no sends.')
    print('PASS: History totals/status details, pending outcomes, owner/role isolation, sent status preserved when opened.')
finally:
    if class_id:sql('DELETE FROM teacher_schedule_dispatches WHERE kelas_id='+class_id+';DELETE FROM pesan_komunikasi WHERE kelas_id='+class_id+';DELETE FROM kelas WHERE id='+class_id+';')
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
