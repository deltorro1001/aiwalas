"""Workbook aliases on synthetic profiles and fake gateway; no real messages or addresses."""
from pathlib import Path
import json
from browser_client import CDP
root=Path(__file__).resolve().parents[1]
registry=json.loads(root.joinpath('resources/data/student-nicknames.json').read_text())
assert len(registry)==35 and len({row['nis'] for row in registry})==35
c=CDP();c.call('Page.navigate',{'url':'about:blank'});c.wait("document.readyState==='complete'")
c.js("window.registry="+json.dumps(registry))
c.js("""
window.currentRole='walas';window.currentUser={id:7};window.activeClassId=1;window.page='dashboard';
window.isWalasRole=function(){return currentRole==='walas'||currentRole==='wali_kelas'};
window.studentAliases=Object.fromEntries(registry.map(row=>[row.nis,row.panggilan]));
window.students=registry.map((row,i)=>[row.nama_dalam_sumber,row.nis,'Hadir','','L','08123456'+String(i).padStart(4,'0'),'08923456'+String(i).padStart(4,'0'),i+1]);
window.studentDetails=Object.fromEntries(registry.map(row=>[row.nis,{'Alamat Lengkap':'Alamat sintetis '+row.nis,'Agama':'Islam'}]));
window.schoolTeachers=[];window.roleAccess={};window.render=function(){};window.toast=function(){};
window.escapeHtml=function(value){var e=document.createElement('div');e.textContent=String(value);return e.innerHTML};
window.modal=function(title,desc,html){document.getElementById('modalContent').innerHTML=html};window.closeModal=function(){document.getElementById('modalContent').innerHTML=''};
document.body.innerHTML='<button id="helpQuestion">Bantuan</button><div id="modalContent"></div>';
window.fetch=function(){throw Error('Real network forbidden')};window.sent=[];
window.sendWhatsAppGateway=async function(payload){sent.push(payload);return {status:'Terkirim'}};
window.confirm=function(){throw Error('No confirmation expected')};
window.attendance={periode:{mulai:'2026-07-13',sampai:'2026-11-05'},kelas:{nama_kelas:'Fixture'},siswa:registry.map((row,i)=>({id:i+1,nis:row.nis,nama:row.nama_dalam_sumber,aliases:row.panggilan,riwayat:[{tanggal:'2026-10-01',status:'Sakit',keterangan_lampiran:'(surat sakit terlampir)'}]}))};
window.authRequest=async function(path){if(path.startsWith('api/absensi/tanya'))return attendance;throw Error('Unexpected API request: '+path)};
""")
for file in ['student-name-matching.js','address-questions.js']:
    c.js(root.joinpath('public/assets',file).read_text())
app=root.joinpath('public/assets/app.js').read_text()
# Load the real profile helpers, general answer and Help modal, without executing startup/network code.
c.js(app[app.index('function detailValue('):app.index('function bind()')])
for file in ['introduction-message.js','attendance-questions.js','help-introduction.js','help-collage.js']:
    c.js(root.joinpath('public/assets',file).read_text())
def ask(question):
    c.js("document.getElementById('helpQuestion').click();helpQuestionText.value="+json.dumps(question)+";sendHelpQuestion.click()")
    return c.js("document.getElementById('helpAnswer')?.textContent||''")
for row in registry:
    for alias in row['panggilan']:
        resolved=c.js('AIWalasStudentNames.resolve('+json.dumps(alias)+',students).map(s=>s[1])')
        assert resolved==[row['nis']],(alias,resolved)
        assert 'Alamat sintetis '+row['nis'] in ask('Tampilkan alamat '+alias),alias
        result=c.js('AIWalasAttendanceQuestions.analyze('+json.dumps('Berapa kali '+alias+' sakit?')+',attendance)')
        assert len(result['rows'])==1 and result['rows'][0]['nis']==row['nis'],(alias,result)
        assert c.js('AIWalasIntroduction.resolve('+json.dumps(alias)+').map(c=>c.name)')==[row['nama_dalam_sumber']],alias
        assert c.js('AIWalasIntroduction.resolve('+json.dumps('orangtua '+alias)+').map(c=>c.name)')==['Orangtua '+row['nama_dalam_sumber']],alias
assert 'WENNY ALISTANTIA WIJAYA' in ask('Weni tinggal dimana?')
assert 'TIMOTHY FRANS WURI' in ask('Dimana alamat rumah Timoti?')
assert 'Islam' in ask('Apa agama Malika?')
for query,expected in [('Berapa kali riski sakit?',['13085']),('Berapa kali riski fatur sakit?',['13151']),('Bandingkan sakit riski dan riski fatur',['13085','13151']),('Rekap sakit kirana dan khairatunisa',['13074','13070'])]:
    selection=c.js('AIWalasStudentNames.inQuestion('+json.dumps(query)+',students)')
    assert sorted(row[1] for row in selection['rows'])==sorted(expected),(query,selection)
assert 'belum spesifik' in ask('Apa agama Rizki?')
assert c.js("AIWalasStudentNames.resolve('wen',students).length===0")
assert c.js("AIWalasStudentNames.expand('Tampilkan alamat weni',AIWalasStudentNames.inQuestion('Tampilkan alamat weni',students)).includes('WENNY ALISTANTIA WIJAYA')")
for alias in ['weni','timoti','riski fatur']:
    before=c.js('sent.length');ask('Kirim perkenalan ke '+alias);c.wait('sent.length==='+str(before+1))
    expected=c.js('AIWalasIntroduction.resolve('+json.dumps(alias)+')[0].phone')
    assert c.js('sent[sent.length-1].nomor_tujuan')==expected
for role in ['student','teacher','assistant','guest']:
    c.js('currentRole='+json.dumps(role))
    assert 'hanya dapat diakses oleh wali kelas' in ask('Tampilkan alamat weni')
c.js("currentRole='walas';students=[['Siswa Kelas Lain','99999']];studentAliases={};studentDetails={}")
assert 'tidak ditemukan' in ask('Tampilkan alamat weni')
# Bootstrap replaces alias scope rather than merging data across users/classes.
c.js(app[app.index('function applyBootstrapData('):app.index('function finishLogin(')])
c.js("applyBootstrapData({legacyStudents:[['Wenny Test','13190']],studentAliases:{'13190':['weni']}})")
assert c.js("AIWalasStudentNames.resolve('weni',students)[0][1]")=='13190'
c.js("applyBootstrapData({legacyStudents:[]})")
assert c.js("Object.keys(studentAliases).length===0&&AIWalasStudentNames.resolve('weni',students).length===0")
print('PASS: All 35 identities and workbook aliases across addresses, attendance and recipients; phonetic spellings, collisions, multi-student queries, fake direct send, generic profiles, roles and class scope.')
