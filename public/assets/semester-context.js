(function(){
  var key='aiwalas.semester', allowed=['Ganjil','Genap'];
  function current(){var value=localStorage.getItem(key)||'Ganjil';return allowed.indexOf(value)>=0?value:'Ganjil'}
  function load(value){
    if(!window.activeClassId||typeof authRequest!=='function')return;
    var url='api/bootstrap?semester='+encodeURIComponent(value)+'&kelas_id='+window.activeClassId;
    authRequest(url).then(function(data){window.activeSemester=value;applyBootstrapData(data);if(window.page==='assessment'&&window.renderAssessmentPage)window.renderAssessmentPage();else render()}).catch(function(error){toast(error.message||'Semester gagal dimuat.')});
  }
  function install(){
    var select=document.getElementById('semesterSelect');if(!select)return;
    if(window.currentRole==='assistant'||window.currentRole==='student'){var row=select.closest('.academic-year-row');if(row)row.style.display='none';window.activeSemester='Ganjil';return}
    if(select.dataset.bound==='1')return;
    select.dataset.bound='1';select.value=current();window.activeSemester=select.value;
    select.onchange=function(){var value=allowed.indexOf(this.value)>=0?this.value:'Ganjil';localStorage.setItem(key,value);window.activeSemester=value;load(value)};
  }
  window.activeSemester=current();setInterval(install,300);install();
})();