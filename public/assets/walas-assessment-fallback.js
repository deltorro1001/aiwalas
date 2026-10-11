(function(){
  if(typeof window.renderWalasAssessmentPage==='function')return;
  window.renderWalasAssessmentPage=function(){
    var root=document.getElementById('pageContent');
    if(root)root.innerHTML='<div class="page"><div class="page-heading"><div><div class="eyebrow">Aktivitas akademik</div><h1>Penilaian</h1><p>Halaman penilaian Wali Kelas.</p></div></div><section class="panel"><p class="summary-empty">Data penilaian belum dapat dimuat. Silakan refresh halaman.</p></section></div>';
  };
  var old=window.render;
  window.render=function(){if(window.page==='walasAssessment'){window.renderWalasAssessmentPage();return}return old.apply(this,arguments)};
})();
