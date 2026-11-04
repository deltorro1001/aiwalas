"""Private letter previews: disposable local fixtures, no gateway calls."""
import base64, http.cookiejar, json, re, secrets, subprocess, urllib.request, urllib.error
from pathlib import Path
from browser_client import CDP

ROOT=Path(__file__).resolve().parents[1]
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
BASE='http://localhost:8080/'
PHP='/opt/homebrew/opt/php@8.3/bin/php'
def sql(query):
    return subprocess.check_output(['/opt/homebrew/bin/mysql','-h127.0.0.1','-uroot','aiwalas','-N','-B','-e',query],text=True).strip()
tag='preview_'+secrets.token_hex(5);password=secrets.token_hex(12)
hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
class Client:
    def __init__(self):
        self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token=re.search(r"name=['\"]csrf-token['\"] content=['\"]([^'\"]+)",self.op.open(BASE).read().decode()).group(1)
    def request(self,path,data=None,status=200):
        req=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),headers={'Content-Type':'application/json','X-CSRF-TOKEN':self.token})
        try: response=self.op.open(req)
        except urllib.error.HTTPError as error: response=error
        body=response.read();assert response.code==status,(path,response.code,body[:300])
        if 'application/json' in response.headers.get('Content-Type',''):
            result=json.loads(body);self.token=result.get('csrfHash',self.token)
        return response,body
    def login(self,role,user,secret=password):
        self.request('api/auth/login',{'role':role,'username':user,'password':secret});return self
users=[];classes=[];students=[];files=[];year=None;c=None
try:
    for i in range(2):
        users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{tag}_wali{i}','{hashed}','Wali Preview {i}','wali_kelas');SELECT LAST_INSERT_ID();"))
    year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{tag}','Ganjil',1);SELECT LAST_INSERT_ID();")
    for i in range(2):
        classes.append(sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{tag}_{i}',11,'Test',{year},{users[i]});SELECT LAST_INSERT_ID();"))
        students.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{tag}_{i}','Siswa Preview {i}','L',{classes[i]},1);SELECT LAST_INSERT_ID();"))
    for role in ['siswa','sekretaris','guest']:
        users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{tag}_{role}','{hashed}','Preview {role}','{role}',{'NULL' if role=='guest' else chr(39)+tag+('_1' if role=='sekretaris' else '_0')+chr(39)});SELECT LAST_INSERT_ID();"))
    wali=Client().login('walas',tag+'_wali0');outsider=Client().login('walas',tag+'_wali1')
    parent=Client().login('parent',tag+'_0',tag+'_0');other_parent=Client().login('parent',tag+'_1',tag+'_1')
    forbidden=[Client().login(role,tag+'_'+db_role) for role,db_role in [('student','siswa'),('assistant','sekretaris'),('guest','guest')]]
    images={ext:subprocess.check_output([PHP,'-r',f'$i=imagecreatetruecolor(8,8);image{encoder}($i);']) for ext,encoder in [('jpg','jpeg'),('png','png')]}
    # Minimal PDF; inline response headers are tested independently from browser's PDF plugin.
    pdf=b'%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF'
    records={}
    for kind in ['sakit','izin']:
        records[kind]=[]
        for extension,mime,body in [('pdf','application/pdf',pdf),('jpg','image/jpeg',images['jpg']),('png','image/png',images['png']),(None,None,None)]:
            extra=",jenis_izin" if kind=='izin' else ''
            value=",'Keperluan keluarga'" if kind=='izin' else ''
            filename=secrets.token_hex(24)+'.'+extension if extension else None
            if filename:
                path=ROOT/'writable'/('pengajuan-'+kind)/filename;path.parent.mkdir(exist_ok=True);path.write_bytes(body);files.append(path)
            filename_sql="'"+filename+"'" if filename else 'NULL'
            name_sql="'surat-"+extension+"'" if filename else 'NULL'
            mime_sql="'"+mime+"'" if mime else 'NULL'
            rid=sql(f"INSERT INTO pengajuan_{kind}(siswa_id,kelas_id,pengguna_id,tanggal_mulai,tanggal_selesai,jumlah_hari,nama_lampiran,berkas_lampiran,mime_lampiran{extra}) VALUES({students[0]},{classes[0]},{users[0]},'2026-11-05','2026-11-05',1,{name_sql},{filename_sql},{mime_sql}{value});SELECT LAST_INSERT_ID();")
            records[kind].append(rid)
            endpoint=f'api/pengajuan-{kind}/{rid}/lampiran'
            if extension:
                for allowed in [wali,parent]:
                    response,content=allowed.request(endpoint+'?preview=1')
                    assert content==body and response.headers['Content-Disposition'].startswith('inline;')
                    assert response.headers['Content-Type'].startswith(mime)
                    assert all(token in response.headers['Cache-Control'] for token in ['private','no-store']) and response.headers['X-Content-Type-Options']=='nosniff'
                    assert response.headers['X-Frame-Options']=='SAMEORIGIN'
                    response,content=allowed.request(endpoint)
                    assert content==body and response.headers['Content-Disposition'].startswith('attachment;')
                for denied in forbidden:denied.request(endpoint+'?preview=1',status=403)
                outsider.request(endpoint+'?preview=1',status=404);other_parent.request(endpoint+'?preview=1',status=404);Client().request(endpoint+'?preview=1',status=401)
            else:wali.request(endpoint+'?preview=1',status=404)
        rows=json.loads(wali.request(f'api/pengajuan-{kind}?kelas_id={classes[0]}')[1])['data']
        assert sum(bool(row['preview_url']) for row in rows)==3
        assert next(row for row in rows if str(row['id'])==records[kind][-1])['lampiran_url'] is None
        assert all('berkas_lampiran' not in row for row in rows)
    print('PASS: PDF/JPG/PNG inline previews, downloads preserved, absent attachments hidden, private ownership/session checks.')
    c=CDP();c.call('Runtime.enable');c.call('Page.enable');c.call('Network.clearBrowserCookies');c.call('Page.navigate',{'url':BASE})
    c.wait("document.readyState==='complete'&&!!window.AIWalasRequestAttachmentPreview")
    def login(role,user,secret=password):
        credentials=json.dumps({'role':role,'username':user,'password':secret})
        c.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(()=>authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")})).then(d=>finishLogin(d.user)).then(()=>authRequest('api/bootstrap?kelas_id="+classes[0]+"')).then(d=>{applyBootstrapData(d);applyRoleAccess();page='attendance';render()})")
    login('walas',tag+'_wali0')
    for kind,card in [('sakit','sickRequestCard'),('izin','leaveRequestCard')]:
        c.wait("document.querySelectorAll('#"+card+" [data-request-preview]').length===3")
        assert c.js("[...document.querySelectorAll('#"+card+" .sick-request-entry')].filter(n=>n.textContent.includes('Tanpa lampiran')).every(n=>!n.querySelector('[data-request-preview]'))")
        for i,extension in enumerate(['pdf','jpg','png']):
            c.js("document.querySelector('#"+card+" [data-request-preview=\""+records[kind][i]+"\"]').click()")
            c.wait("document.getElementById('modalBackdrop').classList.contains('show')&&!!document.querySelector('.request-attachment-preview')")
            if extension=='pdf':assert c.js("document.querySelector('.request-attachment-viewer iframe').src.endsWith('?preview=1')")
            else:c.wait("document.querySelector('.request-attachment-viewer img')?.naturalWidth===8")
            assert c.js("document.querySelector('.request-attachment-preview .primary-btn').textContent==='Unduh Surat'")
            for width in [1440,390]:
                c.call('Emulation.setDeviceMetricsOverride',{'width':width,'height':844,'deviceScaleFactor':1,'mobile':width<500})
                assert c.js("document.documentElement.scrollWidth<=innerWidth&&document.getElementById('modalContent').scrollWidth<=document.getElementById('modalContent').clientWidth")
            c.call('Emulation.clearDeviceMetricsOverride')
            c.js('closeModal()');assert c.js("!document.querySelector('.request-attachment-preview')")
    c.js("document.querySelector('[data-request-preview]').click();page='dashboard';render()")
    c.wait("!document.getElementById('modalBackdrop').classList.contains('show')&&!document.querySelector('.request-attachment-preview')")
    login('parent',tag+'_0',tag+'_0')
    c.wait("!!document.getElementById('sickRequestForm')&&!!document.getElementById('leaveRequestForm')")
    assert not c.js("!!document.querySelector('[data-request-preview]')")
    assert not [event for event in c.events if event.get('method')=='Runtime.exceptionThrown'],c.events
    print('PASS: Both Wali cards preview PDF/images, optional button, download link, mobile fit, close/navigation cleanup and parent form preserved.')
finally:
    if c:
        try:c.js("closeModal();authRequest('api/auth/logout',{method:'POST',body:'{}'})")
        except Exception:pass
    for path in files:
        if path.is_file():path.unlink()
    if students:sql('DELETE FROM pengguna WHERE siswa_id IN ('+','.join(students)+');DELETE FROM siswa WHERE id IN ('+','.join(students)+')')
    if classes:sql('DELETE FROM kelas WHERE id IN ('+','.join(classes)+')')
    if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
    if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
