"""Actual Help routing with synthetic addresses; no real student data or network."""
from pathlib import Path
import json
from browser_client import CDP
root=Path(__file__).resolve().parents[1]
c=CDP();c.call('Page.navigate',{'url':'about:blank'});c.wait("document.readyState==='complete'")
c.js(r"""
window.currentRole='walas';window.currentUser={id:7};window.activeClassId=1;window.page='dashboard';
window.isWalasRole=function(){return currentRole==='walas'||currentRole==='wali_kelas'};
window.students=[['Khaira Aurelia','001'],['Rizky Fahrezi','002'],['Muhammad Rizky Putra','003'],['Annisa Safa','004'],['Siswa Tanpa Alamat','005'],['Siswa Alias','006']];
window.studentDetails={
 '001':{'Alamat Lengkap':'Jalan Contoh 1\nRT 01/RW 02'},
 '002':{'alamat_lengkap':'Jalan Contoh 2'},'003':{'Alamat Lengkap':'Jalan Contoh 3'},
 '004':{'Alamat Lengkap':'Jalan Contoh 4'},'005':{'Alamat Lengkap':'-'},
 '006':{'Nama Panggilan':'Bimo','Alamat Lengkap':'Jalan Contoh 6'}
};
window.fetch=function(){throw Error('Network forbidden')};window.authRequest=window.fetch;
document.body.innerHTML='<button id="helpQuestion">Tanya</button><div id="modalContent"></div>';
window.escapeHtml=function(value){var e=document.createElement('div');e.textContent=String(value);return e.innerHTML};
window.modal=function(title,description,html){document.getElementById('modalContent').innerHTML=html};
window.closeModal=function(){document.getElementById('modalContent').innerHTML=''};window.toast=function(){};
""")
c.js(root.joinpath('public/assets/address-questions.js').read_text())
app=root.joinpath('public/assets/app.js').read_text()
c.js(app[app.index('function helpAnswer('):app.index('function helpQuestion(')])
c.js(app[app.index('function helpQuestion()'):app.index('function bind()')])
for module in ['attendance-questions.js','help-introduction.js','help-collage.js']:
    c.js(root.joinpath('public/assets',module).read_text())
def ask(question):
    c.js("document.getElementById('helpQuestion').click();helpQuestionText.value="+json.dumps(question)+";sendHelpQuestion.click()")
    return c.js("document.getElementById('helpAnswer').textContent")
for question in ['Tampilkan alamat Khaira','Khaira tinggal dimana?','Dimana alamat rumah Khaira?','Dimana alamat Khaira?','  TAMPILKAN   ALAMAT KHAIRA! ','Khaira tinggal di mana?','Di mana alamat rumah Khaira Aurelia?','Tolong tampilkan alamat siswa Khaira']:
    result=ask(question)
    assert 'Khaira Aurelia (NIS 001)' in result and 'Jalan Contoh 1\nRT 01/RW 02' in result,(question,result)
result=ask('Dimana alamat Rizky?')
assert 'lebih dari satu siswa' in result and 'Rizky Fahrezi' in result and 'Muhammad Rizky Putra' in result
assert 'Jalan Contoh' not in result
assert 'Jalan Contoh 2' in ask('Dimana alamat Rizky Fahrezi?')
assert 'Jalan Contoh 3' not in ask('Dimana alamat Rizky Fahrezi?')
assert 'Jalan Contoh 6' in ask('Bimo tinggal dimana?')
assert 'tidak ditemukan' in ask('Dimana alamat Ann?') # A name fragment is not a nickname match.
assert 'tidak ditemukan' in ask('Tampilkan alamat Tidakdikenal')
assert 'belum diisi' in ask('Tanpa Alamat tinggal dimana?')
assert 'Tuliskan nama panggilan' in ask('Tampilkan alamat')
assert c.js("AIWalasAddressQuestions.answer('Siapa saja yang tinggal bersama wali?')===null")
assert c.js("AIWalasAddressQuestions.answer('Kirim perkenalan ke Khaira')===null&&AIWalasAddressQuestions.answer('Siapa saja yang ayahnya wafat?')===null")
for role in ['teacher','assistant','student','guest']:
    c.js('currentRole='+json.dumps(role))
    result=ask('Tampilkan alamat Khaira')
    assert 'hanya dapat diakses oleh wali kelas' in result and 'Jalan Contoh' not in result
c.js("currentRole='student';currentUser.isParent=true")
assert 'hanya dapat diakses oleh wali kelas' in ask('Khaira tinggal dimana?')
# The current class data is used on each question, without retained results from the prior class.
c.js("currentRole='walas';currentUser.isParent=false;activeClassId=2;students=[['Nadia Putri','007']];studentDetails={'007':{'Alamat Lengkap':'Alamat kelas kedua'}}")
assert 'tidak ditemukan' in ask('Tampilkan alamat Khaira')
assert 'Alamat kelas kedua' in ask('Nadia tinggal dimana?')
c.js("studentDetails['007']['Alamat Lengkap']='<img src=x onerror=alert(1)> Jalan Uji'")
assert '<img' in ask('Dimana alamat Nadia?')
assert not c.js("!!document.querySelector('#helpAnswer img')")
print('PASS: Four address commands, nickname/full-name matching, ambiguity, missing data, role privacy, class switching, escaped multiline addresses and actual Help wrappers.')
