"""Actual Help dialog command routing; synthetic contacts and fake delivery only."""
from pathlib import Path
import json
from browser_client import CDP
root=Path(__file__).resolve().parents[1]
c=CDP();c.call('Page.navigate',{'url':'about:blank'});c.wait("document.readyState==='complete'")
c.js("""
document.body.innerHTML='<button id="helpQuestion">Bantuan</button><div id="modalContent"></div>';
window.currentRole='walas';window.currentUser={id:7};window.activeClassId=12;window.page='dashboard';
window.render=function(){};window.toast=function(){};
window.escapeHtml=function(v){var e=document.createElement('div');e.textContent=String(v);return e.innerHTML};
window.modal=function(t,d,html){document.getElementById('modalContent').innerHTML=html};window.closeModal=function(){document.getElementById('modalContent').innerHTML=''};
window.helpAnswer=function(){return 'Fallback biasa'};window.confirm=function(){throw Error('No confirmation expected')};
window.fetch=function(){throw Error('Real network forbidden')};window.calls=[];
window.authRequest=function(path,options){calls.push({path:path,body:JSON.parse(options.body)});return Promise.resolve({ok:true,status:'sent'})};
window.schoolTeachers=[{name:'Nani Aminah, SE.',call:'Ibu Nani',phone:'081111111111',gender:'P'},{name:'Erna Cahyani',phone:'082222222222'},{name:'Erna Lestari',phone:'083333333333'},{name:'Guru Tanpa Nomor',phone:''}];
window.students=[['Timothy Frans Wuri','13186','','','','084444444444','085555555555',1],['Wenny Alistantia Wijaya','13190','','','','086666666666','087777777777',2]];
window.studentAliases={'13186':['timothy','timoti'],'13190':['wenny','weni']};
window.AIWalasCalendar={today:()=> '2026-11-05'};window.AIWalasClassRecap={period:()=>({bulan:9,tahun:2026})};
""")
app=root.joinpath('public/assets/app.js').read_text()
c.js(app[app.index('function helpQuestion()'):app.index('function bind()')])
for file in ['student-name-matching.js','introduction-message.js','attendance-questions.js','help-introduction.js','help-collage.js','help-class-recap.js']:
    c.js(root.joinpath('public/assets',file).read_text())
def ask(text):
    c.js("document.getElementById('helpQuestion').click();helpQuestionText.value="+json.dumps(text)+";sendHelpQuestion.click()")
    c.wait('!sendHelpQuestion.disabled')
def last():return c.js('calls[calls.length-1]')
for query,month,year in [('Kirim Rekap Absen Oktober 2026 ke Ibu Nani',10,2026),('Kirimkan Rekap Absen Kelas November 2026 ke Ibu Nani',11,2026),('kirim rekap absen kelas januari 2027 kepada guru Nani',1,2027),('Kirim rekap absensi bulan September tahun 2026 ke Ibu Nani',9,2026),('Kirim Rekap Absen Kelas ke Ibu Nani',9,2026)]:
    before=c.js('calls.length');ask(query)
    assert c.js('calls.length')==before+1,query
    item=last();assert item['path']=='api/absensi/rekap-kelas/kirim-pdf' and item['body']['bulan']==month and item['body']['tahun']==year,item
    assert item['body']['nomor_tujuan']=='6281111111111' and item['body']['kelas_id']==12,item
    assert 'terkirim' in c.js("document.getElementById('helpAnswer').textContent"),query
for target,phone in [('timoti','6284444444444'),('siswa weni','6286666666666'),('orangtua timoti','6285555555555'),('orang tua weni','6287777777777'),('089999999999','6289999999999'),('nomor +62 899-9999-9999','6289999999999')]:
    ask('Kirim Rekap Absen Oktober 2026 ke '+target);assert last()['body']['nomor_tujuan']==phone,(target,last())
before=c.js('calls.length');ask('Kirim Rekap Absen Oktober 2026 ke Erna')
assert c.js('calls.length')==before and c.js("document.querySelectorAll('.help-class-recap-choice').length")==2
c.js("document.querySelectorAll('.help-class-recap-choice')[1].click()");c.wait('!sendHelpQuestion.disabled')
assert last()['body']['nomor_tujuan']=='6283333333333'
for query in ['Kirim Rekap Absen Oktober 2026 ke Nama Tidak Dikenal','Kirim Rekap Absen Oktober 2026 ke Guru Tanpa Nomor','Kirim Rekap Absen Salahbulan 2026 ke Nani','Kirim Rekap Absen Desember 2025 ke Nani','Kirim Rekap Absen Oktober 2026 ke 123','Kirim Rekap Absen Oktober 2026 ke']:
    before=c.js('calls.length');ask(query);assert c.js('calls.length')==before and c.js('helpError.textContent.length>0'),query
for role in ['teacher','assistant','student','guest']:
    c.js('currentRole='+json.dumps(role));before=c.js('calls.length');ask('Kirim Rekap Absen Oktober 2026 ke Ibu Nani');assert c.js('calls.length')==before
c.js("currentRole='walas';window.authRequest=function(path,options){calls.push({path:path,body:JSON.parse(options.body)});return new Promise(resolve=>window.releaseSend=resolve)}")
c.js("document.getElementById('helpQuestion').click();helpQuestionText.value='Kirim Rekap Absen Oktober 2026 ke Ibu Nani';sendHelpQuestion.click();sendHelpQuestion.click()")
before=c.js('calls.length');c.js("document.getElementById('helpQuestion').click();helpQuestionText.value='Kirim Rekap Absen Oktober 2026 ke Ibu Nani';sendHelpQuestion.click()")
assert c.js('calls.length')==before and 'sedang diproses' in c.js('helpError.textContent')
c.js("releaseSend({ok:true,status:'sent'});currentRole='student'")
c.js("currentRole='walas';helpQuestion();helpQuestionText.value='Pertanyaan biasa';sendHelpQuestion.click()")
assert 'Fallback biasa' in c.js("document.getElementById('helpAnswer').textContent")
assert c.js("AIWalasClassRecapCommands.parse('Kirim perkenalan ke Nani')===null&&AIWalasClassRecapCommands.parse('Kirim kolase hari ini ke Nani')===null")
print('PASS: With/without Kelas, Kirim/Kirimkan, explicit month/year/default period, teacher/student/parent/nickname/direct number, ambiguity, invalid input, role restrictions, double-click and existing Help fallback. No real sends.')
