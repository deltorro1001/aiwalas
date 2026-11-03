"""Synthetic family records; exercises real Help routing without network or private data."""
from pathlib import Path
import json
from browser_client import CDP

root = Path(__file__).resolve().parents[1]
c = CDP()
c.call('Page.navigate', {'url': 'about:blank'})
c.wait("document.readyState==='complete'")
c.js("""
window.currentRole='walas';
window.isWalasRole=function(){return currentRole==='walas'||currentRole==='wali_kelas'};
window.students=[['Siswa A','001'],['Siswa B','002'],['Siswa C','003'],['Siswa D','004'],['Siswa E','005']];
window.studentDetails={
 '001':{'Nama Ayah':'Nama (Alm.)','Nama Ibu':'Salmah'},
 '002':{'nama_ayah':'Alma','nama_ibu':'Nama','pekerjaan_ibu':'Meninggal dunia'},
 '003':{'Nama Ayah':'Almarhum Nama','Nama Ibu':'Nama (Almh.)'},
 '004':{'Nama Ayah':'Belum meninggal','Nama Ibu':'Tidak wafat'},
 '005':{'Nama Ayah':'','Nama Ibu':''}
};
window.fetch=function(){throw Error('Network forbidden')};
""")
c.js(root.joinpath('public/assets/family-questions.js').read_text())
app = root.joinpath('public/assets/app.js').read_text()
start = app.index('function helpAnswer(')
end = app.index('function helpQuestion(', start)
c.js(app[start:end])
def ask(question):
    return c.js('helpAnswer('+json.dumps(question)+')')

for question in ['Siapa saja yang orangtuanya sudah wafat', 'Siapa saja yang oraangtuanya sudah meningal dunia', 'Siapa saja yang orang tuanya telah meninggal dunia', 'Daftar siswa yatim atau piatu']:
    result = ask(question)
    assert '3 siswa.' in result and all(name in result for name in ['Siswa A','Siswa B','Siswa C']), (question,result)
    assert 'Siswa D' not in result and 'Siswa E' not in result
for parent, included, excluded in [('ayahnya','Siswa A','Siswa B'), ('ibunya','Siswa B','Siswa A')]:
    result = ask('Siapa saja yang '+parent+' sudah wafat?')
    assert '2 siswa.' in result and included in result and 'Siswa C' in result and excluded not in result
for term, explanation, included, excluded in [('yatim','ayah wafat','Siswa A','Siswa B'), ('piatu','ibu wafat','Siswa B','Siswa A')]:
    for question in ['siapa saja yang '+term+'?', 'Siapa saja siswa '+term+'?', 'Siapa saja yang '+term+'? ('+('ayahnya' if term=='yatim' else 'ibunya')+' sudah wafat)']:
        result=ask(question)
        assert '2 siswa.' in result and term+' ('+explanation+')' in result, result
        assert included in result and 'Siswa C' in result and excluded not in result
for question in ['Siapa saja siswa yatim piatu?', 'Siapa saja yang kedua orangtuanya wafat?', 'Siapa saja yang ayah dan ibunya meninggal?']:
    result=ask(question)
    assert '1 siswa.' in result and 'Siswa C' in result and 'Siswa A' not in result
assert c.js("AIWalasFamilyQuestions.intent('Siapa saja yang naik motor?')===null")
assert c.js("AIWalasFamilyQuestions.parentStatus({'Nama Ayah':'Alma Salmah'},'ayah')===false")
assert 'belum dapat memastikan' in ask('Siapa yang ayahnya belum wafat?')
# No data leak when roles change, even if the old profiles remain in memory.
for role in ['guru','siswa','orangtua','sekretaris','tamu']:
    c.js('currentRole='+json.dumps(role))
    result=ask('Siapa saja yang orangtuanya sudah wafat?')
    assert 'hanya dapat diakses oleh wali kelas' in result and 'Siswa A' not in result
c.js("currentRole='wali_kelas';students=[['Siswa Baru','099']];studentDetails={}")
assert '0 siswa.' in ask('Siapa saja yang ibunya wafat?')
# The actual modal preserves line breaks and escapes names rather than inserting HTML.
c.js("""
document.body.innerHTML='<div id="modalContent"></div>';
window.modal=function(title,description,html){document.getElementById('modalContent').innerHTML=html};
window.escapeHtml=function(value){var el=document.createElement('div');el.textContent=String(value);return el.innerHTML};
window.toast=function(){};
students=[['<img src=x onerror=alert(1)>','099']];studentDetails={'099':{'Nama Ayah':'wafat'}};
""")
c.js(app[app.index('function helpQuestion()'):app.index('function bind()')])
c.js("helpQuestion();helpQuestionText.value='Siapa saja yang ayahnya wafat?';sendHelpQuestion.click()")
assert c.js("document.getElementById('helpAnswer').style.whiteSpace==='pre-line'&&!document.querySelector('#helpAnswer img')&&document.getElementById('helpAnswer').textContent.includes('<img')")
print('PASS: family classification, query variants, role restrictions, empty records and Help rendering')
