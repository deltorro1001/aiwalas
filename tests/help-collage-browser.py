"""Actual Help collage command, sequential build/send APIs mocked; no network or DB."""
from pathlib import Path
import json
from browser_client import CDP
ROOT=Path(__file__).resolve().parents[1]
c=CDP();c.call('Page.navigate',{'url':'about:blank'});c.wait("document.readyState==='complete'")
c.js("""
document.body.innerHTML='<button id="helpQuestion">Tanya sekarang</button><div id="modalContent"></div>';
window.currentRole='walas';window.currentUser={id:7};window.activeClassId=12;window.page='communication';window.render=function(){};window.toast=function(){};
window.escapeHtml=function(value){var e=document.createElement('div');e.textContent=String(value);return e.innerHTML};
window.modal=function(title,description,html){document.getElementById('modalContent').innerHTML='<h2>'+title+'</h2><p>'+description+'</p>'+html;document.getElementById('cancelModal').onclick=closeModal};
window.closeModal=function(){document.getElementById('modalContent').innerHTML=''};
window.fallbacks=0;window.helpAnswer=function(q){fallbacks++;return 'Jawaban biasa'};
window.confirm=function(){throw new Error('Help commands must not request confirmation')};window.calls=[];
window.fetch=function(){throw new Error('Real network forbidden')};
window.authRequest=function(path,options){calls.push({path:path,options:options});return Promise.resolve(path.startsWith('api/absensi/tanya')?{periode:{mulai:'2026-07-13',sampai:'2026-11-03',diperiksa:'2026-11-03 08:00:00'},kelas:{nama_kelas:'Fixture'},siswa:[{id:21,nis:'00001',nama:'Khaira Aurelia',riwayat:[]}]}:{ok:true,data:{status:'Terkirim'}})};
window.schoolTeachers=[{name:'Nani Aminah, SE.',call:'Bu Nani',phone:'081111111111',gender:'P'},{name:'Erna Cahyani',phone:'082222222222',gender:'P'},{name:'Erna Lestari',phone:'083333333333',gender:'P'},{name:'Guru Tanpa Nomor',phone:'',gender:'L'}];
window.students=[['Khaira Aurelia','00001','','','','084444444444','085555555555',21]];
""")
app=ROOT.joinpath('public/assets/app.js').read_text();start=app.index('function helpQuestion()');end=app.index('function bind()',start)
c.js(app[start:end])
for file in ['whatsapp-gateway.js','introduction-message.js','attendance-questions.js','help-introduction.js']:c.js(ROOT.joinpath('public/assets',file).read_text())
c.js(ROOT.joinpath('public/assets/help-collage.js').read_text())
c.js("""
window.AIWalasCalendar={today:function(){return '2026-11-03'}};
window.confirm=function(){throw new Error('No collage confirmation allowed')};
window.buildCase='ok';window.sendCase='ok';window.already=false;
window.goodCollage=function(){return {ok:true,data:{halaman:[{id:41,nomor:1}],jumlah_foto:35}}};
window.authRequest=function(path,options){calls.push({path:path,options:options});
 if(path==='api/upload-foto/kolase'){
  if(buildCase==='empty')return Promise.reject(new Error('Belum ada foto pada tanggal ini.'));
  if(buildCase==='slow')return new Promise(resolve=>window.finishBuild=resolve);
  var data=goodCollage();if(buildCase==='tooMany')data.data.jumlah_foto=36;if(buildCase==='multi')data.data.halaman.push({id:42,nomor:2});return Promise.resolve(data);
 }
 if(path.endsWith('/kirim'))return sendCase==='failed'?Promise.reject(new Error('Gateway menolak kolase.')):Promise.resolve({ok:sendCase==='ok',status:sendCase==='ok'?'sent':'unknown',already:already,message:'Belum ada konfirmasi'});
 if(path==='api/whatsapp/send')return Promise.resolve({ok:true});
 if(path.startsWith('api/absensi/tanya'))return Promise.resolve({periode:{mulai:'2026-07-13',sampai:'2026-11-03',diperiksa:'2026-11-03 08:00:00'},kelas:{nama_kelas:'Fixture'},siswa:[{id:21,nis:'00001',nama:'Khaira Aurelia',riwayat:[]}]});
 throw new Error('Unexpected route: '+path);
};
""")
def ask(text,enter=False):
 c.js("document.getElementById('helpQuestion').click();helpQuestionText.value="+json.dumps(text))
 c.js("helpQuestionText.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))" if enter else "sendHelpQuestion.click()")
def reset():c.js("calls=[];buildCase='ok';sendCase='ok';already=false;currentRole='walas';currentUser={id:7};activeClassId=12")
def body(index):return c.js('JSON.parse(calls['+str(index)+'].options.body)')
for wording,expected in [('03-11-2026','2026-11-03'),('2026-11-03','2026-11-03'),('3/11/2026','2026-11-03'),('3 November 2026','2026-11-03'),('tanggal 03-11-2026','2026-11-03'),('hari ini','2026-11-03'),('kemarin','2026-11-02'),('29 Februari 2028','2028-02-29')]:
 reset();ask('Kirim kolase '+wording+' ke Ibu Nani');c.wait('!sendHelpQuestion.disabled')
 assert c.js("calls.length===2&&calls[0].path==='api/upload-foto/kolase'&&calls[1].path==='api/upload-foto/kolase/halaman/41/kirim'"),wording
 assert body(0)=={'kelas_id':12,'tanggal':expected},wording
 assert body(1)['nomor_tujuan']=='6281111111111' and body(1)['nama_penerima']=='Ibu Nani Aminah, SE.'
 assert c.js("!document.querySelector('img')&&!document.getElementById('aiwalasIntroForm')&&document.getElementById('helpAnswer').textContent.includes('terkirim melalui gateway')&&helpQuestionText.value===''")
reset();ask('Tolong kirimkan hasil kolase 3 November 2026 kepada nomor telepon +62 899-9999-9999',True);c.wait('!sendHelpQuestion.disabled')
assert body(1)['nomor_tujuan']=='6289999999999' and body(1)['nama_penerima']=='6289999999999'
for q in ['Kirim kolase ke Nani','Kirim kolase 31-02-2026 ke Nani','Kirim kolase 29 Februari 2026 ke Nani','Kirim kolase 03-11-2026 ke','Kirim kolase 03-11-2026 ke 123','Kirim kolase 03-11-2026 ke Tidak Dikenal','Kirim kolase 03-11-2026 ke Guru Tanpa Nomor']:
 reset();ask(q);assert c.js('calls.length===0&&helpError.textContent.length>0'),q
reset();ask('Kirim kolase 03-11-2026 ke Erna')
assert c.js("calls.length===0&&document.querySelectorAll('.help-collage-choice').length===2")
c.js("document.querySelectorAll('.help-collage-choice')[1].click()");c.wait('!sendHelpQuestion.disabled');assert body(1)['nomor_tujuan']=='6283333333333'
reset();ask('Kirim kolase 03-11-2026 ke Erna');c.js("activeClassId=13;document.querySelector('.help-collage-choice').click()");assert c.js('calls.length===0')
for mode in ['empty','tooMany','multi']:
 reset();c.js('buildCase='+json.dumps(mode));ask('Kirim kolase 03-11-2026 ke Nani');c.wait('!sendHelpQuestion.disabled');assert c.js("calls.length===1&&helpError.textContent.length>0"),mode
for mode in ['failed','unknown']:
 reset();c.js('sendCase='+json.dumps(mode));ask('Kirim kolase 03-11-2026 ke Nani');c.wait('!sendHelpQuestion.disabled');assert c.js("calls.length===2&&helpError.textContent.includes('Komunikasi')&&helpQuestionText.value.length>0&&!document.getElementById('helpAnswer').textContent.includes('terkirim melalui gateway')"),mode
reset();c.js('already=true');ask('Kirim kolase 03-11-2026 ke Nani');c.wait('!sendHelpQuestion.disabled');assert c.js("document.getElementById('helpAnswer').textContent.includes('Tidak dikirim ulang')")
# Duplicate UI events, then a changed class while the build is running: never send.
reset();c.js("buildCase='slow'");ask('Kirim kolase 03-11-2026 ke Nani');c.js("sendHelpQuestion.click();helpQuestionText.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))")
assert c.js('calls.length===1&&sendHelpQuestion.disabled&&helpQuestionText.disabled')
c.js('activeClassId=13;finishBuild(goodCollage())');c.wait('!sendHelpQuestion.disabled');assert c.js('calls.length===1')
# A second dialog cannot create a concurrent build/send for the same date and destination.
reset();c.js("buildCase='slow'");ask('Kirim kolase 03-11-2026 ke Nani');ask('Kirim kolase 03-11-2026 ke Nani');assert c.js("calls.length===1&&helpError.textContent.includes('sedang diproses')")
c.js('finishBuild(goodCollage())');assert c.js('calls.length===1')
reset();c.js("buildCase='slow'");ask('Kirim kolase 03-11-2026 ke Nani');c.js('currentUser={id:8};finishBuild(goodCollage())');c.wait('!sendHelpQuestion.disabled');assert c.js('calls.length===1')
for role in ['student','assistant','teacher','guest']:
 reset();c.js('currentRole='+json.dumps(role));ask('Kirim kolase 03-11-2026 ke Nani');assert c.js("calls.length===0&&helpError.textContent.includes('wali kelas')"),role
reset();c.js('activeClassId=0');ask('Kirim kolase 03-11-2026 ke Nani');assert c.js("calls.length===0&&helpError.textContent.includes('kelas aktif')")
reset();ask('Kirim perkenalan ke Nani');c.wait('!sendHelpQuestion.disabled');assert c.js("calls.length===1&&calls[0].path==='api/whatsapp/send'"),'Introduction command regressed'
reset();ask('Berapa kali Khaira sakit?');c.wait("!!document.querySelector('[data-total-status=Sakit]')");assert c.js("calls.length===1&&calls[0].path.startsWith('api/absensi/tanya')"),'Attendance question regressed'
reset();ask('Jangan kirim kolase 03-11-2026 ke Nani');assert c.js('calls.length===0&&fallbacks===1')
print('PASS: Direct Help collage command, ISO/Indonesian/relative/leap dates, recipient/phone lookup, ambiguity, sequential build/send, 35-photo single-page guard, no photos, duplicate events, stale class/account guards, role checks, failure/no retry/already-sent states and existing Help commands. All gateway APIs mocked.')
