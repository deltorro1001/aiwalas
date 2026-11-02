"""Actual Help dialog and introduction template, with mocked data/gateway; no network or DB."""
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
def ask(text,enter=False):
 c.js("document.getElementById('helpQuestion').click();helpQuestionText.value="+json.dumps(text))
 c.js("helpQuestionText.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))" if enter else "sendHelpQuestion.click()")
def sent():return c.js("calls.filter(x=>x.path==='api/whatsapp/send').length")
def payload():return c.js("JSON.parse(calls.filter(x=>x.path==='api/whatsapp/send').slice(-1)[0].options.body)")
for text in ['Kirim perkenalan aiwalas ke Nani','Kirimkan perkenalan aiwalas ke Ibu Nani','Kirim perkenalan ke Nani','Tolong kirimkan perkenalan AI WALAS kepada Nani Aminah, SE.']:
 before=sent();ask(text);c.wait("!sendHelpQuestion.disabled")
 assert sent()==before+1,text
 item=payload();assert item['nama_penerima']=='Ibu Nani Aminah, SE.' and item['nomor_tujuan']=='6281111111111' and 'Perkenalkan, saya AIWalas' in item['isi_pesan']
 assert c.js("!document.getElementById('aiwalasIntroForm')&&document.getElementById('helpAnswer').textContent.includes('terkirim')&&helpQuestionText.value===''")
before=sent();ask('Kirim perkenalan ke 081111111111',enter=True);c.wait("!sendHelpQuestion.disabled")
assert sent()==before+1 and payload()['nama_penerima']=='Ibu Nani Aminah, SE.'
# A new phone is sufficient for direct delivery; use a neutral salutation when no name is known.
before=sent();ask('Kirimkan perkenalan aiwalas ke nomor telepon +62 899-9999-9999');c.wait("!sendHelpQuestion.disabled")
item=payload();assert sent()==before+1 and item['nomor_tujuan']=='6289999999999' and item['nama_penerima']=='6289999999999'
assert ', Bapak/Ibu.\n\nPerkenalkan,' in item['isi_pesan'] and not c.js("!!document.getElementById('aiwalasIntroForm')")
for text in ['Kirim perkenalan ke Bapak Andi Baru','Kirim perkenalan ke Guru Tanpa Nomor']:
 before=sent();ask(text);assert sent()==before and c.js("helpError.textContent.includes('nomor telepon')&&!document.getElementById('aiwalasIntroForm')"),text
# Ambiguous names need a recipient selection only, followed immediately by sending.
before=sent();ask('Kirim perkenalan ke Erna')
assert sent()==before and c.js("document.querySelectorAll('.help-introduction-choice').length===2&&!document.getElementById('aiwalasIntroForm')")
c.js("document.querySelectorAll('.help-introduction-choice')[1].click()");c.wait("!sendHelpQuestion.disabled")
assert sent()==before+1 and payload()['nama_penerima']=='Ibu Erna Lestari'
ask('Kirim perkenalan ke Khaira');assert c.js("document.querySelectorAll('.help-introduction-choice').length===2")
before=sent();ask('Kirim perkenalan ke Orangtua Khaira Aurelia');c.wait("!sendHelpQuestion.disabled")
assert sent()==before+1 and payload()['nomor_tujuan']=='6285555555555'
ask('Kirim perkenalan ke Erna');before=sent();c.js("activeClassId=13;document.querySelector('.help-introduction-choice').click()")
assert sent()==before,'Stale contact selection sent a message'
c.js('activeClassId=12')
for text in ['Kirim perkenalan','Kirim perkenalan ke','Kirim perkenalan ke 123']:
 before=sent();ask(text);assert sent()==before and c.js("helpError.textContent.length>0&&!document.getElementById('aiwalasIntroForm')"),text
# A slow gateway receives one request despite repeated clicks, Enter, or reopening Help.
c.js("window.ordinaryRequest=authRequest;window.authRequest=function(path,options){calls.push({path:path,options:options});return new Promise(resolve=>window.finishSend=resolve)}")
before=sent();ask('Kirim perkenalan ke Nani');c.js("sendHelpQuestion.click();helpQuestionText.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}))")
assert sent()==before+1 and c.js('sendHelpQuestion.disabled&&helpQuestionText.disabled')
ask('Kirim perkenalan ke Nani')
assert sent()==before+1 and c.js("helpError.textContent.includes('sedang dikirim')")
c.js("finishSend({ok:true});authRequest=ordinaryRequest")
# Failed/ambiguous delivery is reported without an automatic retry or success claim.
c.js("window.authRequest=function(path,options){calls.push({path:path,options:options});return Promise.resolve({ok:false,message:'Gateway belum mengonfirmasi pesan.'})}")
before=sent();ask('Kirim perkenalan ke Nani');c.wait("!sendHelpQuestion.disabled")
assert sent()==before+1 and c.js("helpError.textContent.includes('histori Komunikasi')&&!document.getElementById('helpAnswer').textContent.includes('terkirim melalui')&&helpQuestionText.value.length>0")
c.js('authRequest=ordinaryRequest;activeClassId=0');before=sent();ask('Kirim perkenalan ke Nani');assert sent()==before and c.js("helpError.textContent.includes('kelas aktif')")
c.js('activeClassId=12')
ask('Jangan kirim perkenalan ke Nani');assert c.js("fallbacks===1&&!document.getElementById('aiwalasIntroForm')")
before=sent();ask('Berapa kali Khaira sakit?');c.wait("!!document.querySelector('[data-total-status=Sakit]')")
assert sent()==before and c.js("calls[calls.length-1].path.startsWith('api/absensi/tanya?kelas_id=12')"),'Attendance help was broken'
for role in ['student','assistant','teacher','guest']:
 c.js('currentRole='+json.dumps(role));before=sent();ask('Kirim perkenalan ke Nani')
 assert sent()==before and c.js("helpError.textContent.includes('wali kelas')&&!document.getElementById('aiwalasIntroForm')"),role
print('PASS: Direct Help/Enter sending without preview/confirmation; command variants, contact/phone lookup, generic new-number greeting, missing targets, recipient ambiguity, class/role checks, duplicate in-flight guards, failure without retry and ordinary/attendance help preserved. All gateway calls mocked.')
