(function(){
  var pageName='walasAssessment', threshold=80, cache=null;
  var oldAccess=window.applyRoleAccess, oldRender=window.render;
  if(window.names)window.names[pageName]='Penilaian';
  if(window.roleAccess)window.roleAccess.walas=(window.roleAccess.walas||[]).concat([pageName]);
  function esc(value){return String(value==null?'':value).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function phone(value){var number=String(value||'').replace(/\D/g,'');if(number.indexOf('0')===0)number='62'+number.slice(1);return /^62\d{8,15}$/.test(number)?number:''}
  function semester(){return window.activeSemester||localStorage.getItem('aiwalas.semester')||'Ganjil'}
  function teacherFor(subject){var contact=(window.teacherContacts||{})[subject]||{},name=contact.name||'guru mata pelajaran',record=(window.schoolTeachers||[]).find(function(item){return String(item.name||'').trim()===String(name).trim()})||{},gender=String(record.gender||record.jenis_kelamin||'').toLowerCase(),prefix=(gender==='l'||gender.indexOf('pria')>=0||gender.indexOf('laki')>=0)?'Bpk':'Ibu';return prefix+' '+name}
  function message(student,subject,component,score){var teacher=teacherFor(subject);return 'Assalamualaikum, Bapak/Ibu Orangtua/Wali '+student[0]+'. Kami menginformasikan bahwa capaian nilai ananda pada mata pelajaran '+subject+', komponen '+component+', adalah '+score+'. Nilai tersebut masih di bawah batas ketuntasan 80. Mohon untuk meminta ananda menghubungi '+teacher+' untuk mendapatkan arahan perbaikan nilai.'}
  function studentMessage(student,subject,component,score){var teacher=teacherFor(subject);return 'Assalamualaikum, '+student[0]+'. Nilai kamu pada mata pelajaran '+subject+', komponen '+component+', adalah '+score+', masih di bawah batas ketuntasan 80. Silakan menghubungi '+teacher+' untuk mendapatkan arahan perbaikan nilai.'}
  function notification(student,subject,component,score){
    if(score===null||score>=threshold)return '<span class="walas-grade-notification-empty">—</span>';
    var parentNumber=phone(student[6]),studentNumber=phone(student[5]);
    var parentAction=parentNumber
      ? '<a class="wa-parent-action" href="https://web.whatsapp.com/send?phone='+parentNumber+'&text='+encodeURIComponent(message(student,subject,component,score))+'" target="_blank" rel="noopener">Siapkan WhatsApp Orangtua</a>'
      : '<span class="wa-parent-action disabled" aria-disabled="true">Nomor orangtua belum tersedia</span>';
    var studentAction=studentNumber
      ? '<a class="wa-parent-action wa-student-action" href="https://web.whatsapp.com/send?phone='+studentNumber+'&text='+encodeURIComponent(studentMessage(student,subject,component,score))+'" target="_blank" rel="noopener">Siapkan WhatsApp Siswa</a>'
      : '<span class="wa-parent-action disabled" aria-disabled="true">Nomor siswa belum tersedia</span>';
    return '<div class="walas-notification-actions">'+parentAction+studentAction+'</div>';
  }
  function normalizeData(result){
    var subjects=(result[0].data||[]).filter(function(row){return Number(row.aktif)!==0}),components=(result[1].data||[]).filter(function(row){return Number(row.aktif)!==0}),scores=result[2].data||[],scoreMap={};
    scores.forEach(function(row){scoreMap[String(row.siswa_id)+'-'+String(row.komponen_nilai_id)]=Number(row.nilai)});
    return {subjects:subjects,components:components,scoreMap:scoreMap};
  }
  function renderSubject(student,subject){
    if(!student||!subject||!cache)return;
    var components=cache.components.filter(function(row){return Number(row.mata_pelajaran_id)===Number(subject.id)}).sort(function(a,b){return Number(a.urutan)-Number(b.urutan)});
    var rows=components.map(function(component){var key=String(student[7])+'-'+String(component.id),exists=Object.prototype.hasOwnProperty.call(cache.scoreMap,key),score=exists?Math.round(cache.scoreMap[key]):null,status=score===null?'—':score>=threshold?'Lulus':'Tidak Lulus',kind=score===null?'':score>=threshold?'teal':'coral';return '<tr><td>'+esc(component.nama_komponen)+'</td><td><strong>'+(score===null?'—':score)+'</strong></td><td>'+(score===null?'—':'<span class="pill '+kind+'">'+status+'</span>')+'</td><td>'+notification(student,subject.nama_mata_pelajaran,component.nama_komponen,score)+'</td></tr>'}).join('');
    var target=document.getElementById('walasGradeTableWrap'),summary=document.getElementById('walasGradeSummary'),info=document.getElementById('walasGradeStudent');
    if(target)target.innerHTML='<div class="student-grade-selected"><strong>Mapel: '+esc(subject.nama_mata_pelajaran)+'</strong></div><div class="score-sheet-wrap"><table class="score-sheet student-component-grades walas-component-grades"><thead><tr><th>Komponen Nilai</th><th>Nilai</th><th>Status</th><th>Notifikasi Orangtua &amp; Siswa</th></tr></thead><tbody>'+(rows||'<tr><td colspan="4" class="empty-state">Belum ada komponen nilai.</td></tr>')+'</tbody></table></div>';
    if(summary)summary.textContent='Komponen penilaian '+subject.nama_mata_pelajaran+' · tombol WhatsApp aktif untuk nilai di bawah 80';
    if(info)info.textContent=student[0]+' · NIS '+student[1];
    document.querySelectorAll('[data-walas-grade-subject]').forEach(function(button){button.classList.toggle('active',String(button.dataset.walasGradeSubject)===String(subject.id))});
  }
  function renderPage(){
    var root=document.getElementById('pageContent');if(!root)return;
    root.innerHTML='<div class="page student-grades-page"><div class="page-heading"><div><div class="eyebrow">Aktivitas akademik</div><h1>Penilaian</h1><p id="walasGradeStudent">Memuat data nilai siswa...</p></div></div><section class="panel table-panel"><div class="panel-head"><div><h2>Rekap Nilai</h2><p id="walasGradeSummary">Pilih siswa dan mata pelajaran untuk melihat nilai.</p></div><label class="walas-student-filter">Siswa<select id="walasGradeStudentSelect" class="filter-btn"></select></label></div><div id="walasGradeSubjectButtons" class="student-grade-subject-buttons"></div><div id="walasGradeTableWrap"><p class="summary-empty">Memuat data penilaian...</p></div></section></div>';
    var breadcrumb=document.getElementById('breadcrumbCurrent');if(breadcrumb)breadcrumb.textContent='Penilaian';
    document.querySelectorAll('.nav-item[data-page]').forEach(function(item){item.classList.toggle('active',item.dataset.page===pageName)});
    Promise.all([authRequest('api/mata-pelajaran'),authRequest('api/komponen-nilai?kelas_id='+activeClassId),authRequest('api/nilai?kelas_id='+activeClassId)]).then(function(result){if(window.page!==pageName)return;cache=normalizeData(result);var select=document.getElementById('walasGradeStudentSelect'),buttons=document.getElementById('walasGradeSubjectButtons');if(!select||!buttons)return;select.innerHTML=(window.students||[]).map(function(student){return '<option value="'+esc(student[7])+'">'+esc(student[0])+' · '+esc(student[1])+'</option>'}).join('');buttons.innerHTML=cache.subjects.map(function(subject){return '<button type="button" class="student-grade-subject" data-walas-grade-subject="'+esc(subject.id)+'">'+esc(subject.nama_mata_pelajaran)+'</button>'}).join('');function selectedStudent(){return (window.students||[]).find(function(student){return String(student[7])===String(select.value)})}function selectedSubject(id){return cache.subjects.find(function(subject){return String(subject.id)===String(id)})||cache.subjects[0]}var activeSubject=cache.subjects[0];buttons.querySelectorAll('[data-walas-grade-subject]').forEach(function(button){button.onclick=function(){activeSubject=selectedSubject(button.dataset.walasGradeSubject);renderSubject(selectedStudent(),activeSubject)}});select.onchange=function(){renderSubject(selectedStudent(),activeSubject)};if(cache.subjects.length&&window.students.length)renderSubject(selectedStudent(),activeSubject);else document.getElementById('walasGradeTableWrap').innerHTML='<p class="summary-empty">Belum ada data nilai siswa.</p>'}).catch(function(error){var target=document.getElementById('walasGradeTableWrap');if(target)target.innerHTML='<p class="summary-empty">'+esc(error.message||'Data nilai tidak dapat dimuat.')+'</p>'});
  }
  window.applyRoleAccess=function(){oldAccess.apply(this,arguments);var button=document.querySelector('[data-page="'+pageName+'"]');if(button)button.style.display=window.currentRole==='walas'?'flex':'none'};
  window.render=function(){if(window.currentRole==='walas'&&window.page===pageName){renderPage();return}return oldRender.apply(this,arguments)};
})();