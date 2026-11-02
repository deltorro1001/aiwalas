"""Actual assessment/attendance buttons with mocked API: no WhatsApp or application DB."""
from pathlib import Path
from browser_client import CDP

root = Path(__file__).resolve().parents[1]
c = CDP()
c.call('Page.navigate', {'url': 'about:blank'})
c.wait("document.readyState==='complete'")
c.js("""
document.body.innerHTML='<div id="breadcrumbCurrent"></div><div id="pageContent"></div><div id="modalContent"></div>';
window.currentRole='walas';window.page='walasAssessment';window.activeClassId=12;window.activeSemester='Ganjil';
window.currentUser={id:7};window.roleAccess={walas:[]};window.names={};window.render=function(){};
window.applyRoleAccess=function(){};window.escapeHtml=function(v){return String(v).replace(/[&<>"']/g,function(x){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[x]})};
window.academicSubjectKey=function(v){return String(v).toLowerCase().replace(/[^a-z0-9]/g,'')};
window.academicSubjectLabel=function(v){return v};window.academicComponentLabel=function(v){return v};
window.academicComponentKeys=function(){return ['tugas1']};window.academicComponentKey=academicSubjectKey;
window.academicGradeClass=function(){return 'grade-low'};
window.students=[['Fixture Siswa','00000','','','','081234567891','081234567890',21]];
window.teacherContacts={'Bahasa Inggris':{name:'Fixture Guru'}};window.schoolTeachers=[];
window.modalClosed=false;window.lastToast='';window.toast=function(v){lastToast=v};
window.modal=function(a,b,html){modalClosed=false;document.getElementById('modalContent').innerHTML=html};
window.closeModal=function(){modalClosed=true;document.getElementById('modalContent').innerHTML=''};
window.calls=[];window.outcome='sent';
window.fetch=function(){throw new Error('Real HTTP is forbidden in this test')};
window.authRequest=function(path,options){calls.push({path:path,options:options});
 if(path==='api/mata-pelajaran')return Promise.resolve({data:[{id:4,nama_mata_pelajaran:'Bahasa Inggris',aktif:1}]});
 if(path.startsWith('api/komponen-nilai'))return Promise.resolve({data:[{id:5,mata_pelajaran_id:4,nama_komponen:'Tugas 1',aktif:1}]});
 if(path.startsWith('api/nilai'))return Promise.resolve({data:[{siswa_id:21,komponen_nilai_id:5,nilai:0}]});
 if(path==='api/whatsapp/send')return outcome==='sent'?Promise.resolve({ok:true,data:{status:'Terkirim'}}):Promise.reject(new Error(outcome==='unknown'?'Gateway belum mengonfirmasi pesan.':'Gateway menolak permintaan.'));
 throw new Error('Unexpected API route: '+path);
};
""")
for filename in ['whatsapp-gateway.js', 'walas-assessment.js']:
    c.js(root.joinpath('public/assets', filename).read_text())
c.js('renderWalasAssessmentPage()')
c.wait("!!document.querySelector('.gateway-grade-action')")
assert c.js("calls.filter(x=>x.path==='api/whatsapp/send').length===0"), 'Rendering sent a notification'
c.js("document.querySelector('.gateway-grade-action').click()")
assert c.js("calls.filter(x=>x.path==='api/whatsapp/send').length===0"), 'Preview sent without confirmation'
c.js("document.getElementById('confirmGatewayGrade').click();document.getElementById('confirmGatewayGrade').click()")
assert c.js("modalClosed && calls.filter(x=>x.path==='api/whatsapp/send').length===1"), 'Confirmation failed or sent twice'
assert c.js("(()=>{var call=calls.find(x=>x.path==='api/whatsapp/send'),p=JSON.parse(call.options.body);return call.options.method==='POST'&&p.kelas_id===12&&p.nomor_tujuan==='6281234567890'&&p.isi_pesan.includes('Tugas 1, sebesar 0')})()"), 'Wrong route, recipient, class or message'
for outcome in ['failed', 'unknown']:
    c.js(f"outcome='{outcome}';document.querySelector('.gateway-grade-action').click();document.getElementById('confirmGatewayGrade').click()")
    assert c.js("!modalClosed&&!document.getElementById('confirmGatewayGrade').disabled&&document.getElementById('gatewayGradeError').textContent.length>0"), 'Gateway error hidden or reported as sent'
assert c.js("calls.filter(x=>x.path==='api/whatsapp/send').length===3"), 'Unexpected retries'

# Use the real attendance installer and click its parent-notification button.
c.js("""outcome='sent';page='attendance';window.render=function(){};window.setInterval=function(){};
window.setTimeout=function(fn){fn()};window.AIWalasAttendance={isToday:function(){return true}};
document.getElementById('pageContent').innerHTML='<section class="table-panel"><table><tbody><tr data-attendance-student="21" data-attendance-status="Sakit"><td></td><td></td><td></td><td></td></tr></tbody></table></section>';
""")
c.js(root.joinpath('public/assets/attendance-whatsapp.js').read_text())
c.js("document.querySelector('[data-wa-parent]').click()")
assert c.js("document.querySelector('[data-wa-parent]').textContent==='Terkirim'"), 'Attendance button failed'
assert c.js("(()=>{var p=JSON.parse(calls[calls.length-1].options.body);return p.kelas_id===12&&p.nomor_tujuan==='6281234567890'&&p.isi_pesan.includes('Sakit')})()"), 'Wrong attendance payload'
c.js("outcome='failed';document.querySelector('[data-wa-parent]').textContent='Kirim via Gateway';document.querySelector('[data-wa-parent]').click()")
assert c.js("lastToast==='Gateway menolak permintaan.'&&document.querySelector('[data-wa-parent]').textContent==='Kirim via Gateway'"), 'Attendance failure reported as sent'
# All parent attendance narratives, removed fallback, and WIB greetings at each boundary.
c.js("""
window.__NarrativeDate=Date;window.__narrativeNow='2026-11-02T03:59:00Z';
window.Date=class extends __NarrativeDate{constructor(...args){super(...(args.length?args:[__narrativeNow]))}static now(){return new __NarrativeDate(__narrativeNow).getTime()}};
window.mountParentAttendance=function(status,name){students[0][0]=name;page='attendance';document.getElementById('pageContent').innerHTML='<section class="table-panel"><table><tbody><tr data-attendance-student="21"><td></td><td></td><td></td><td></td></tr></tbody></table></section>';document.querySelector('tr').dataset.attendanceStatus=status;render()};
""")
checks={'Menunggu keterangan':'Mohon konfirmasi kehadirannya.','Terlambat':'Mohon menjadi perhatian.','Sakit':'Semoga ananda lekas pulih dan dapat kembali bersekolah dalam keadaan sehat.','Izin':'Terima kasih atas informasi yang Bapak/Ibu sampaikan.'}
import json
for status,expected in checks.items():
    c.js("outcome='sent';mountParentAttendance("+json.dumps(status)+",'Fixture Siswa');document.querySelector('[data-wa-parent]').click()")
    text=c.js("JSON.parse(calls[calls.length-1].options.body).isi_pesan")
    assert text.startswith('Assalamualaikum, Bapak/Ibu Orangtua Fixture Siswa.') and expected in text,(status,text)
    if status in ['Sakit','Izin']:assert 'konfirmasi' not in text.lower(),text
for status in ['Alpha','PJJ','Pengambilan Raport','Tanpa absensi','Hadir','Libur']:
    count=c.js('calls.length');c.js('mountParentAttendance('+json.dumps(status)+",'Fixture Siswa')")
    assert c.js("!document.querySelector('[data-wa-parent]') && calls.length==="+str(count)),status
for instant,greeting in [('2026-11-02T03:59:00Z','Selamat Pagi'),('2026-11-02T04:00:00Z','Selamat Siang'),('2026-11-02T08:00:00Z','Selamat Sore'),('2026-11-02T11:00:00Z','Selamat Malam'),('2026-11-02T17:00:00Z','Selamat Pagi')]:
    c.js('__narrativeNow='+json.dumps(instant))
    for status in checks:
        c.js('mountParentAttendance('+json.dumps(status)+",'Timothy Frans');document.querySelector('[data-wa-parent]').click()")
        text=c.js("JSON.parse(calls[calls.length-1].options.body).isi_pesan")
        assert text.startswith(greeting+', Bapak/Ibu Orangtua Timothy Frans.'),(instant,status,text)
    c.js("page='walasAssessment';renderWalasAssessmentPage()")
    c.wait("!!document.querySelector('.gateway-grade-action')")
    c.js("document.querySelector('.gateway-grade-action').click()")
    assert c.js("document.getElementById('gatewayGradeMessage').value").startswith(greeting+', Bapak/Ibu Orangtua/Wali Timothy Frans.'),instant
c.js('window.Date=__NarrativeDate')
assert c.js("(async()=>{window.authRequest=undefined;try{await sendWhatsAppGateway({});return false}catch(e){return e.message.includes('Sesi AIWalas belum siap')}})()"), 'Missing session not handled'
print('PASS: Actual assessment confirmation and attendance buttons call shared send API; zero scores, correct payload, duplicate-click guard, rejection/unknown errors, missing session, revised Sakit/Izin, removed fallback, all parent WIB greetings and no automatic sends. All HTTP mocked.')
