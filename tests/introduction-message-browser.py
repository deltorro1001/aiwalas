"""Introduction template with mocked clipboard and gateway. No network or application DB."""
from pathlib import Path
import json
from browser_client import CDP
ROOT=Path(__file__).resolve().parents[1]
c=CDP();c.call('Page.navigate',{'url':'about:blank'});c.wait("document.readyState==='complete'")
c.js("""
document.body.innerHTML='<div id="modalContent"></div>';
window.currentRole='walas';window.currentUser={id:7};window.activeClassId=12;window.page='communication';window.render=function(){window.renders++};window.renders=0;
window.toast=function(){};window.closedModal=false;
window.modal=function(title,description,html){closedModal=false;document.getElementById('modalContent').innerHTML='<h2>'+title+'</h2><p>'+description+'</p>'+html;document.getElementById('cancelModal').onclick=closeModal};
window.closeModal=function(){closedModal=true;document.getElementById('modalContent').innerHTML=''};
window.calls=[];window.confirmed=false;window.confirm=function(){return confirmed};
window.fetch=function(){throw new Error('Real network forbidden')};
window.authRequest=function(path,options){calls.push({path:path,options:options});return Promise.resolve({ok:true,data:{status:'Terkirim'}})};
window.schoolTeachers=[{name:'Guru Tersimpan',phone:'081111111111',gender:'P'},{name:'Guru Tersimpan',phone:'081111111111',gender:'P'},{name:'Guru Tanpa Nomor',phone:'',gender:'L'}];window.students=[['Siswa Tersimpan','00001','','','','082222222222','083333333333',21]];
window.copied='';Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:async function(value){copied=value}}});
window.NativeDate=Date;window.testNow='2026-11-03T00:00:00Z';window.Date=class extends NativeDate{constructor(...args){super(...(args.length?args:[testNow]))}static now(){return new NativeDate(testNow).getTime()}};
""")
for file in ['whatsapp-gateway.js','introduction-message.js']:c.js(ROOT.joinpath('public/assets',file).read_text())
for role in ['student','assistant','teacher','guest']:
 c.js('currentRole='+json.dumps(role)+';AIWalasIntroduction.open()')
 assert not c.js("!!document.getElementById('aiwalasIntroForm')"),role
c.js("currentRole='walas';AIWalasIntroduction.open()")
assert c.js("introCopy.disabled&&introSend.disabled&&introMessage.value===''&&calls.length===0")
c.js("document.getElementById('aiwalasIntroForm').dispatchEvent(new Event('submit',{cancelable:true}))")
assert c.js("introError.textContent.includes('nama penerima')&&calls.length===0")
for instant,salutation in [('2026-11-03T03:59:00Z','Selamat Pagi'),('2026-11-03T04:00:00Z','Selamat Siang'),('2026-11-03T08:00:00Z','Selamat Sore'),('2026-11-03T11:00:00Z','Selamat Malam'),('2026-11-03T17:00:00Z','Selamat Pagi')]:
 c.js('testNow='+json.dumps(instant)+";introName.value='Ika';introSalutation.value='Ibu';introName.dispatchEvent(new Event('input'));document.getElementById('aiwalasIntroForm').dispatchEvent(new Event('submit',{cancelable:true}))")
 body=c.js('introMessage.value')
 assert body.startswith(salutation+', Ibu Ika.') and 'Perkenalkan, saya AIWalas' in body,(instant,body)
 assert 'Aiwalas hadir untuk dapat memudahkan akses informasi akademik' in body
 assert 'Semoga AIWalas dapat' not in body
 assert 'uji coba' not in body and 'Alisah Putri' not in body
 assert c.js('!introCopy.disabled&&introSend.disabled&&calls.length===0')
 c.js('introCopy.click()');assert c.js('copied===introMessage.value&&calls.length===0')
# Saved contacts fill the recipient safely; manual mode accepts a new number outside that data.
c.js("introPhoneSource.value='saved';introPhoneSource.dispatchEvent(new Event('change'))")
assert c.js("!introSavedContactLabel.hidden&&introSavedContact.options.length===4&&introPhone.readOnly&&introPhone.value===''"),'Saved contacts or deduplication incorrect'
c.js("introSavedContact.value=Array.from(introSavedContact.options).find(o=>o.textContent.startsWith('Guru —')).value;introSavedContact.dispatchEvent(new Event('change'))")
assert c.js("introName.value==='Guru Tersimpan'&&introSalutation.value==='Ibu'&&introPhone.value==='6281111111111'&&introMessage.value===''&&introSend.disabled")
c.js("introSavedContact.value=Array.from(introSavedContact.options).find(o=>o.textContent.startsWith('Siswa —')).value;introSavedContact.dispatchEvent(new Event('change'))")
assert c.js("introName.value==='Siswa Tersimpan'&&introSalutation.value===''&&introPhone.value==='6282222222222'")
c.js("introSavedContact.value=Array.from(introSavedContact.options).find(o=>o.textContent.startsWith('Orangtua —')).value;introSavedContact.dispatchEvent(new Event('change'))")
assert c.js("introName.value==='Orangtua Siswa Tersimpan'&&introPhone.value==='6283333333333'")
c.js("introPhoneSource.value='manual';introPhoneSource.dispatchEvent(new Event('change'))")
assert c.js("introSavedContactLabel.hidden&&!introPhone.readOnly&&introPhone.value===''&&calls.length===0")
# Arbitrary names are rendered as text in the preview, with no HTML interpolation.
c.js("introName.value='<img src=x onerror=alert(1)>';introName.dispatchEvent(new Event('input'));introSalutation.value='';document.getElementById('aiwalasIntroForm').dispatchEvent(new Event('submit',{cancelable:true}))")
assert c.js("!document.querySelector('#modalContent img')&&introMessage.value.includes(', <img src=x onerror=alert(1)>.')")
c.js("introName.value='Nama Baru';introName.dispatchEvent(new Event('input'))")
assert c.js("introMessage.value===''&&introCopy.disabled&&introSend.disabled"),'Changed recipient retained old preview'
c.js("introSalutation.value='Bapak';document.getElementById('aiwalasIntroForm').dispatchEvent(new Event('submit',{cancelable:true}));introMessage.value+='\\nPesan tambahan pilihan wali kelas.';introPhone.value='123';introPhone.dispatchEvent(new Event('input'));introSend.click()")
assert c.js("introError.textContent.includes('nomor WhatsApp')&&calls.length===0")
c.js("introPhone.value='081234567890';introPhone.dispatchEvent(new Event('input'));activeClassId=0;introSend.click()")
assert c.js("introError.textContent.includes('kelas aktif')&&calls.length===0")
c.js("activeClassId=12;introSend.click()")
assert c.js('calls.length===0'),'Cancelled confirmation sent a message'
# A deliberate confirmed send is dispatched once and uses the visible edited text.
c.js("confirmed=true;window.authRequest=function(path,options){calls.push({path:path,options:options});return new Promise(resolve=>window.finishSend=resolve)};introSend.click();introSend.click()")
assert c.js('calls.length===1&&introSend.disabled&&introName.disabled&&introMessage.disabled')
request=c.js('({path:calls[0].path,method:calls[0].options.method,payload:JSON.parse(calls[0].options.body)})')
assert request['path']=='api/whatsapp/send' and request['method']=='POST'
assert request['payload']['kelas_id']==12 and request['payload']['nomor_tujuan']=='6281234567890' and request['payload']['nama_penerima']=='Bapak Nama Baru'
assert request['payload']['isi_pesan'].endswith('Pesan tambahan pilihan wali kelas.')
c.js("finishSend({ok:true,data:{status:'Terkirim'}})");assert c.js('closedModal&&renders===1')
# Failure retains the draft and does not retry without a further user action.
c.js("AIWalasIntroduction.open();introName.value='Ika';document.getElementById('aiwalasIntroForm').dispatchEvent(new Event('submit',{cancelable:true}));introPhone.value='081234567890';introPhone.dispatchEvent(new Event('input'));window.authRequest=function(path,options){calls.push({path:path,options:options});return Promise.reject(new Error('Gateway belum mengonfirmasi pesan; periksa histori.'))};introSend.click()")
assert c.js("calls.length===2&&!introSend.disabled&&introError.textContent.includes('periksa histori')&&introMessage.value.length>0&&!closedModal")
c.js("navigator.clipboard.writeText=async function(){throw new Error('Denied')};introCopy.click()")
assert c.js("introError.textContent.includes('Cmd+C')&&introMessage.selectionEnd===introMessage.value.length")
# A stale dialog must not send on behalf of another account.
c.js("currentUser={id:8};introSend.click()")
assert c.js('calls.length===2')
assert not ROOT.joinpath('public/assets/whatsapp-notifications.js').exists()
layout=ROOT.joinpath('app/Views/layouts/app.php').read_text()
assert 'whatsapp-notifications.js' not in layout
history=ROOT.joinpath('public/assets/communication-history.js').read_text()
assert 'showManualMessages' not in history and 'manualCommunication' not in history and 'openAiwalasIntroduction' in history
print('PASS: Wali-only template, required recipient, WIB greeting boundaries, safe preview, copy/edit/reset, saved teacher/student/parent contacts, manual number outside saved data, optional phone, class validation, confirmation/cancellation, one send, failure without retry, stale account guard and removal of old manual messages. All clipboard/gateway calls mocked.')
