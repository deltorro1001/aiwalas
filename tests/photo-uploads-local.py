"""Local photo API/browser regression. Disposable fixtures, no real gateway sends."""
import base64,datetime,http.cookiejar,json,os,re,secrets,shutil,subprocess,tempfile,urllib.request,urllib.error,uuid
from pathlib import Path
from zoneinfo import ZoneInfo
ROOT=Path(__file__).resolve().parent.parent
assert re.search(r'^CI_ENVIRONMENT\s*=\s*development\s*$',(ROOT/'.env').read_text(),re.M)
MYSQL=shutil.which('mysql') or '/opt/homebrew/bin/mysql';PHP='/opt/homebrew/opt/php@8.3/bin/php';BASE='http://localhost:8080/'
def sql(q):return subprocess.check_output([MYSQL,'-h','127.0.0.1','-u','root','aiwalas','-N','-B','-e',q],text=True).strip()
class Client:
 def __init__(self):
  self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
  html=self.op.open(BASE).read().decode();self.token=re.search(r"name=['\"]csrf-token['\"] content=['\"]([^'\"]+)",html).group(1)
 def request(self,path,data=None,status=200,content=None,binary=False):
  headers={'X-CSRF-TOKEN':self.token,'Content-Type':content or 'application/json'}
  if data is not None and content is None:data=json.dumps(data).encode()
  req=urllib.request.Request(BASE+path,data=data,headers=headers)
  try:res=self.op.open(req,timeout=60)
  except urllib.error.HTTPError as e:res=e
  raw=res.read();assert res.code==status,(path,res.code,raw[:300])
  if binary:return raw,res.headers
  result=json.loads(raw);self.token=result.get('csrfHash',self.token);return result
 def upload(self,photo,note='Kegiatan praktik hari ini',key=None,status=201,extra=None,mime='image/jpeg'):
  boundary='AIWalas'+secrets.token_hex(12);parts=[]
  for name,value in dict(keterangan=note,request_key=key or str(uuid.uuid4()),**(extra or {})).items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
  parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="foto"; filename="fixture.jpg"\r\nContent-Type: {mime}\r\n\r\n'.encode()+photo+b'\r\n');parts.append(f'--{boundary}--\r\n'.encode())
  return self.request('api/upload-foto',b''.join(parts),status,'multipart/form-data; boundary='+boundary)
prefix='photo_'+secrets.token_hex(5);password=secrets.token_hex(16);hashed=subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password],text=True)
users=[];classes=[];pupils=[];teachers=[];year=None;browser=None;files=[]
today=datetime.datetime.now(ZoneInfo('Asia/Jakarta')).strftime('%Y-%m-%d')
with tempfile.TemporaryDirectory(prefix='aiwalas-photo-test-') as tmp:
 try:
  fixture=Path(tmp)/'source.jpg'
  subprocess.check_call([PHP,'-r',"$im=imagecreatetruecolor(320,240);imagefill($im,0,0,imagecolorallocate($im,23,139,131));imagefilledrectangle($im,30,30,160,190,imagecolorallocate($im,235,181,44));imagejpeg($im,$argv[1],90);imagedestroy($im);",str(fixture)])
  photo=fixture.read_bytes()
  for i in range(2):users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran) VALUES('{prefix}_wali{i}','{hashed}','Wali Foto {i}','wali_kelas');SELECT LAST_INSERT_ID();"))
  year=sql(f"INSERT INTO tahun_ajaran(nama_tahun_ajaran,semester,aktif) VALUES('{prefix}','Ganjil',1);SELECT LAST_INSERT_ID();")
  for i in range(2):
   classes.append(sql(f"INSERT INTO kelas(nama_kelas,tingkat,jurusan,tahun_ajaran_id,wali_kelas_id) VALUES('{prefix}_{i}',11,'Test',{year},{users[i]});SELECT LAST_INSERT_ID();"))
   pupils.append(sql(f"INSERT INTO siswa(nis,nama_lengkap,jenis_kelamin,kelas_id,aktif) VALUES('{prefix}_{i}','Siswa Foto {i}','L',{classes[i]},1);SELECT LAST_INSERT_ID();"))
   users.append(sql(f"INSERT INTO pengguna(nama_pengguna,kata_sandi,nama_lengkap,peran,nis) VALUES('{prefix}_student{i}','{hashed}','Siswa Foto {i}','siswa','{prefix}_{i}');SELECT LAST_INSERT_ID();"))
  for i,phone in enumerate(['081234567890','']):teachers.append(sql(f"INSERT INTO guru(nama_lengkap,jenis_kelamin,nomor_hp,aktif) VALUES('{prefix} Guru {i}','L','{phone}',1);SELECT LAST_INSERT_ID();"))
  wali=Client();wali.request('api/auth/login',{'role':'walas','username':prefix+'_wali0','password':password})
  other_wali=Client();other_wali.request('api/auth/login',{'role':'walas','username':prefix+'_wali1','password':password})
  student=Client();student.request('api/auth/login',{'role':'student','username':prefix+'_student0','password':password})
  other=Client();other.request('api/auth/login',{'role':'student','username':prefix+'_student1','password':password})
  parent=Client();parent.request('api/auth/login',{'role':'parent','username':prefix+'_0','password':prefix+'_0'})
  parent.request('api/upload-foto',status=403);parent.upload(photo,status=403);wali.upload(photo,status=403)
  student.upload(photo,note='',status=422);student.upload(b'<svg xmlns="http://www.w3.org/2000/svg"/>',status=422,mime='image/svg+xml');student.upload(b'not an image',status=422)
  huge=Path(tmp)/'huge.png';subprocess.check_call([PHP,'-r',"$im=imagecreatetruecolor(5000,3000);imagepng($im,$argv[1]);imagedestroy($im);",str(huge)])
  student.upload(huge.read_bytes(),status=422,mime='image/png')
  request_key=str(uuid.uuid4());first=student.upload(photo,key=request_key,extra={'siswa_id':pupils[1],'kelas_id':classes[1],'tanggal':'2000-01-01'})['id']
  assert str(student.upload(photo,key=request_key,status=200)['id'])==str(first)
  row=sql(f"SELECT siswa_id,kelas_id,tanggal,diunggah_pada,lebar,tinggi FROM foto_siswa WHERE id={first}").split('\t')
  assert row[0]==pupils[0] and row[1]==classes[0] and row[2]==today and int(row[4])==640 and int(row[5])==480,row
  for client in [student,wali]:
   image,headers=client.request(f'api/upload-foto/{first}/gambar',binary=True);assert image.startswith(b'\xff\xd8') and headers['Content-Type'].startswith('image/jpeg') and image!=photo
  for client in [other,other_wali,parent]:client.request(f'api/upload-foto/{first}/gambar',status=404)
  other_wali.request('api/upload-foto?kelas_id='+classes[0],status=403)
  wali.request('api/upload-foto?kelas_id='+classes[0]+'&tanggal=2026-02-30',status=422)
  wali.request('api/upload-foto/kolase',{'kelas_id':int(classes[0]),'tanggal':'2026-01-01'},status=422)
  other_wali.request('api/upload-foto/kolase',{'kelas_id':int(classes[0]),'tanggal':today},status=403)
  for index in range(24):student.upload(photo,note='Kegiatan foto '+str(index))
  result=wali.request('api/upload-foto?kelas_id='+classes[0]+'&tanggal='+today)
  assert result['total']==25 and len(result['data'])==24 and result['has_more']
  assert len(wali.request('api/upload-foto?kelas_id='+classes[0]+'&tanggal='+today+'&page=2')['data'])==1
  collage=wali.request('api/upload-foto/kolase',{'kelas_id':int(classes[0]),'tanggal':today},status=201)['data']
  assert int(collage['jumlah_foto'])==25 and len(collage['halaman'])==1
  assert wali.request('api/upload-foto/kolase',{'kelas_id':int(classes[0]),'tanggal':today})['data']['id']==collage['id']
  ids=[]
  for line in sql('SELECT foto_ids FROM foto_kolase_halaman WHERE kolase_id='+str(collage['id'])+' ORDER BY nomor').splitlines():ids+=json.loads(line)
  assert len(ids)==len(set(ids))==25
  first_page=collage['halaman'][0]['id']
  image,_=wali.request('api/upload-foto/kolase/halaman/'+str(first_page),binary=True);assert image.startswith(b'\xff\xd8')
  for client in [student,parent,other_wali]:client.request('api/upload-foto/kolase/halaman/'+str(first_page),status=403 if client!=other_wali else 404)
  wali.request('api/upload-foto/kolase/halaman/'+str(first_page)+'/kirim',{'guru_id':int(teachers[0])},status=422) # localhost cannot be fetched by the actual gateway
  student.upload(photo,note='Foto baru setelah kolase dibuat')
  assert not wali.request('api/upload-foto?kelas_id='+classes[0]+'&tanggal='+today)['kolase']['terbaru']
  print('PASS: Photo validation, own/student versus parent permissions, class privacy, no timestamp burned into photo, deduplication, spoofed fields ignored, daily pagination, all 25 photos in one collage page, snapshot reuse/staleness and localhost send guard.')
  if os.environ.get('AIWALAS_BROWSER_TEST')=='1':
   from browser_client import CDP
   browser=CDP();browser.call('Runtime.enable');browser.call('Page.enable');browser.call('Network.enable');browser.call('Network.clearBrowserCookies');browser.call('Page.navigate',{'url':BASE})
   browser.call('Emulation.setDeviceMetricsOverride',{'width':1440,'height':1100,'deviceScaleFactor':1,'mobile':False})
   browser.wait("typeof authRequest==='function' && !!window.AIWalasPages?.photoUploads")
   def screenshot(name):
    if os.environ.get('AIWALAS_SCREENSHOT_DIR'):
     target=Path(os.environ['AIWALAS_SCREENSHOT_DIR']);target.mkdir(parents=True,exist_ok=True)
     (target/name).write_bytes(base64.b64decode(browser.call('Page.captureScreenshot',{'format':'png'})['data']))
   def login(role,username,secret):
    credentials=json.dumps({'role':role,'username':username,'password':secret})
    browser.js("authRequest('api/auth/logout',{method:'POST',body:'{}'}).then(()=>authRequest('api/auth/login',{method:'POST',body:JSON.stringify("+credentials+")})).then(d=>finishLogin(d.user))")
   login('student',prefix+'_student0',password)
   browser.js("window.page='attendance';window.render()")
   browser.wait("!!document.getElementById('studentPhotoUploadForm')")
   assert browser.js("document.getElementById('studentAttendanceRecap').compareDocumentPosition(document.getElementById('studentPhotoUploadCard'))&Node.DOCUMENT_POSITION_FOLLOWING")
   assert browser.js("document.getElementById('studentPhotoUploadCard').parentElement.lastElementChild.id")=='studentPhotoUploadCard'
   browser.wait("document.querySelectorAll('#studentAttendanceRecapRows tr').length>2 && !document.getElementById('studentAttendanceRecapRows').textContent.includes('Memuat')")
   browser.js("document.getElementById('studentPhotoUploadCard').scrollIntoView({block:'start'});new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))");screenshot('student-photo-upload.png')
   document=browser.call('DOM.getDocument');node=browser.call('DOM.querySelector',{'nodeId':document['root']['nodeId'],'selector':'#studentPhotoFile'})
   browser.call('DOM.setFileInputFiles',{'nodeId':node['nodeId'],'files':[str(fixture)]})
   browser.js("document.getElementById('studentPhotoNote').value='Uji unggah dari browser';document.getElementById('studentPhotoUploadForm').requestSubmit()")
   browser.wait("document.getElementById('studentPhotoMessage').textContent.includes('berhasil')")
   login('parent',prefix+'_0',prefix+'_0');browser.js("window.page='attendance';window.render()")
   browser.wait("!!document.getElementById('studentAttendanceRecap')")
   assert not browser.js("!!document.getElementById('studentPhotoUploadCard')")
   login('walas',prefix+'_wali0',password)
   browser.js("authRequest('api/bootstrap?kelas_id="+classes[0]+"').then(d=>{applyBootstrapData(d);window.page='photoUploads';window.render()})")
   browser.wait("document.getElementById('photoCount')?.textContent.includes('27 dari maksimal 35 foto')")
   assert browser.js("document.querySelector('[data-page=photoUploads]').nextElementSibling.dataset.page")=='settings'
   assert browser.js("document.querySelector('[data-page=photoUploads]').style.display")=='flex'
   assert browser.js("document.getElementById('collageTeacher').querySelector('option[value=\\\""+teachers[1]+"\\\"]').disabled".replace('\\\"','\"'))
   browser.js("document.getElementById('moreClassPhotos').click()")
   browser.wait("document.querySelectorAll('#classPhotoGallery .photo-entry').length===27")
   browser.js("document.getElementById('buildPhotoCollage').click()")
   browser.wait("document.getElementById('collageSummary').textContent.includes('27 foto')")
   assert browser.js("document.querySelectorAll('#collagePreview img').length")==1
   browser.js("document.getElementById('collagePreviewPanel').scrollIntoView({block:'start'})");screenshot('wali-photo-collage.png')
   browser.js("document.getElementById('collageTeacher').value="+json.dumps(teachers[0])+";document.getElementById('collageTeacher').onchange();document.getElementById('sendPhotoCollage').click()")
   browser.wait("!!document.getElementById('confirmSendCollage')")
   assert prefix+' Guru 0' in browser.js("document.getElementById('modalContent').textContent")
   browser.js("window.__photoSendCalls=[];window.__photoOriginalFetch=window.fetch;window.fetch=function(path,options){if(/^api\\/upload-foto\\/kolase\\/halaman\\/\\d+\\/kirim$/.test(path)){window.__photoSendCalls.push(JSON.parse(options.body));return Promise.resolve(new Response(JSON.stringify({ok:true,status:'sent'}),{status:200,headers:{'Content-Type':'application/json'}}))}return window.__photoOriginalFetch(path,options)};document.getElementById('confirmSendCollage').click()")
   browser.wait("!document.getElementById('modalBackdrop').classList.contains('show')")
   assert browser.js("window.__photoSendCalls")==[{'guru_id':int(teachers[0])}]
   assert 'Kolase satu halaman diterima gateway' in browser.js("document.getElementById('photoPageMessage').textContent")
   browser.js("window.fetch=window.__photoOriginalFetch;authRequest('api/auth/logout',{method:'POST',body:'{}'})")
   print('PASS: Student card after recap, browser image compression/upload, parent exclusion, Wali sidebar position, paginated gallery, single-page preview, teacher selection and confirmation before sending.')
  current=int(sql('SELECT COUNT(*) FROM foto_siswa WHERE kelas_id='+classes[0]+" AND tanggal='"+today+"'"))
  for index in range(35-current):student.upload(photo,note='Foto tambahan '+str(index))
  student.upload(photo,note='Foto ke-36 tidak boleh masuk',status=409)
  assert sql('SELECT COUNT(*) FROM foto_siswa WHERE kelas_id='+classes[0]+" AND tanggal='"+today+"'")=='35'
  final=wali.request('api/upload-foto/kolase',{'kelas_id':int(classes[0]),'tanggal':today},status=201)['data']
  assert int(final['jumlah_foto'])==35 and len(final['halaman'])==1
  assert len(json.loads(sql('SELECT foto_ids FROM foto_kolase_halaman WHERE kolase_id='+str(final['id']))))==35
  other.upload(photo,note='Kelas lain memiliki kuota tersendiri')
  print('PASS: All 35 photos in exactly one page; 36th upload rejected while other classes retain their own quota.')
  assert sql('SELECT COUNT(*) FROM pesan_komunikasi WHERE kelas_id IN ('+','.join(classes)+')')=='0'
 finally:
  if browser:
   try:browser.js("closeModal();authRequest('api/auth/logout',{method:'POST',body:'{}'})")
   except Exception:pass
  if classes:
   ids=','.join(classes)
   photo_files=sql(f'SELECT berkas FROM foto_siswa WHERE kelas_id IN ({ids})').splitlines()
   collage_files=sql(f'SELECT h.berkas FROM foto_kolase_halaman h JOIN foto_kolase f ON f.id=h.kolase_id WHERE f.kelas_id IN ({ids})').splitlines()
   for name in photo_files+collage_files:
    file=ROOT/'writable/uploads/student-photos'/name
    if file.is_file():file.unlink()
   sql(f'DELETE FROM pesan_komunikasi WHERE kelas_id IN ({ids});DELETE FROM pengguna WHERE siswa_id IN (SELECT id FROM siswa WHERE kelas_id IN ({ids}));DELETE FROM siswa WHERE kelas_id IN ({ids});DELETE FROM kelas WHERE id IN ({ids});')
  if teachers:sql('DELETE FROM guru WHERE id IN ('+','.join(teachers)+')')
  if users:sql('DELETE FROM pengguna WHERE id IN ('+','.join(users)+')')
  if year:sql('DELETE FROM tahun_ajaran WHERE id='+year)
