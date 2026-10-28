"""Local CSRF/gateway request regression. Invalid empty gateway payloads only; no messages sent."""
import os,re,secrets,shutil,subprocess,json,urllib.request,http.cookiejar,urllib.error
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql';PHP=shutil.which('php') or '/opt/homebrew/opt/php@8.3/bin/php'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
name='csrf_'+secrets.token_hex(6);password=secrets.token_hex(16);hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
user=None
try:
    user=sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{name}','{hashed}','CSRF Test Wali','wali_kelas');SELECT LAST_INSERT_ID();")
    sql(f"INSERT INTO notifikasi(pengguna_id,judul,isi,jenis,sudah_dibaca) VALUES({user},'Fixture','Fixture','Test',0);")
    op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    op.open('http://localhost:8080/').read()
    request=urllib.request.Request('http://localhost:8080/api/whatsapp/send',data=b'{}',headers={'Content-Type':'application/json','X-CSRF-TOKEN':'invalid-stale-token'})
    try:r=op.open(request)
    except urllib.error.HTTPError as error:r=error
    result=json.load(r);assert r.code==403 and result['code']=='csrf_expired' and result['csrfHash']
    from browser_client import CDP
    c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':'http://localhost:8080/'})
    c.wait("typeof authRequest==='function' && typeof authTokenVersion!=='undefined'")
    credentials=json.dumps({'role':'walas','username':name,'password':password})
    c.js("authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")})")
    # A stale tab/meta token is refreshed before any write, without weakening CSRF.
    c.js("document.querySelector('meta[name=csrf-token]').content='expired-meta-token';authRequest('api/notifikasi/baca-semua',{method:'PATCH',body:'{}'})")
    assert sql('SELECT sudah_dibaca FROM notifikasi WHERE pengguna_id='+user)=='1'
    c.js("Promise.all(Array.from({length:4},()=>authRequest('api/notifikasi/baca-semua',{method:'PATCH',body:'{}'})))")
    # Force one genuine CSRF rejection, then allow validation through. Empty payload cannot send.
    result=c.js("(async()=>{const original=window.fetch;let posts=0;window.fetch=function(path,options){if(path==='api/whatsapp/send'){posts++;if(posts===1)options=Object.assign({},options,{headers:Object.assign({},options.headers,{'X-CSRF-TOKEN':'stale-cross-tab-token'})})}return original(path,options)};try{await authRequest('api/whatsapp/send',{method:'POST',body:'{}'});return {unexpected:true}}catch(e){return {posts:posts,status:e.status,message:e.message}}finally{window.fetch=original}})()")
    assert result['posts']==2 and result['status']==422 and 'Nomor' in result['message'],result
    assert sql('SELECT COUNT(*) FROM pesan_komunikasi WHERE dibuat_oleh='+user)=='0'
    # Late polling data must not overwrite the token supplied after a successful write.
    c.js("window.__nativeCsrfFetch=window.fetch;window.fetch=function(path,options){if(path==='api/bootstrap')return window.__nativeCsrfFetch(path,options).then(async r=>{const data=await r.json();data.csrfHash='stale-polling-token';return new Promise(resolve=>{window.__releaseCsrfPoll=()=>resolve(new Response(JSON.stringify(data),{status:200,headers:{'Content-Type':'application/json'}}))})});return window.__nativeCsrfFetch(path,options)};window.__pendingCsrfPoll=authRequest('api/bootstrap');void 0")
    c.wait("typeof window.__releaseCsrfPoll==='function'")
    c.js("authRequest('api/notifikasi/baca-semua',{method:'PATCH',body:'{}'})")
    token=c.js("document.querySelector('meta[name=csrf-token]').content")
    c.js("window.__releaseCsrfPoll();window.__pendingCsrfPoll.then(()=>{window.fetch=window.__nativeCsrfFetch})")
    assert c.js("document.querySelector('meta[name=csrf-token]').content")==token
    for status in [403,502,504]:
        result=c.js("(async()=>{const original=window.fetch;let posts=0;window.fetch=function(path,options){if(path==='api/whatsapp/send'){posts++;return Promise.resolve(new Response(JSON.stringify({message:'Gateway/test failure'}),{status:"+str(status)+",headers:{'Content-Type':'application/json'}}))}return original(path,options)};try{await authRequest('api/whatsapp/send',{method:'POST',body:'{}'})}catch(e){return {posts:posts,status:e.status}}finally{window.fetch=original}})()")
        assert result=={'posts':1,'status':status},result
    result=c.js("(async()=>{const original=window.fetch;let posts=0;window.fetch=function(path,options){if(path==='api/whatsapp/send'){posts++;return Promise.reject(new TypeError('Network test failure'))}return original(path,options)};try{await authRequest('api/whatsapp/send',{method:'POST',body:'{}'})}catch(e){return posts}finally{window.fetch=original}})()")
    assert result==1
    c.js("authRequest('api/notifikasi/baca-semua',{method:'PATCH',body:'{}'})")
    c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'})")
    assert sql('SELECT COUNT(*) FROM pesan_komunikasi WHERE dibuat_oleh='+user)=='0'
    print('PASS: CSRF protection intact; stale token recovery, serialized writes, cross-tab rejection retried once before controller, stale polling ignored, no retry for permission/gateway/network errors, no WhatsApp sends.')
finally:
    if user:
        sql('DELETE FROM notifikasi WHERE pengguna_id='+user+';DELETE FROM pesan_komunikasi WHERE dibuat_oleh='+user+';DELETE FROM pengguna WHERE id='+user)
