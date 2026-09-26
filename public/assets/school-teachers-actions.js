(function(){
function decorate(){
 document.querySelectorAll('#schoolTeacherRows tr').forEach(function(row){
  var c=row.lastElementChild;
  if(c&&!c.querySelector('.school-teacher-delete')){
   var d=document.createElement('button');d.className='panel-action school-teacher-detail-action';d.textContent='Detail';c.insertBefore(d,c.firstChild);
   var b=document.createElement('button');
   b.className='delete-student-btn school-teacher-delete';
   b.textContent=String.fromCodePoint(128465);
   b.title='Hapus guru';
   c.appendChild(b);
  }
 });
}
setInterval(decorate,700);
document.addEventListener('click',function(e){var detail=e.target.closest&&e.target.closest('.school-teacher-detail-action');if(detail){var row=detail.closest('tr'),rows=Array.prototype.slice.call(document.querySelectorAll('#schoolTeacherRows tr')),teacher=schoolTeachers[rows.indexOf(row)];if(teacher&&window.showSchoolTeacherDetail)showSchoolTeacherDetail(teacher);return}if(!e.target.classList.contains('school-teacher-delete'))return;var row=e.target.closest('tr'),name=row&&row.cells[0]?row.cells[0].textContent.trim():'';if(!confirm('Hapus guru '+name+' dari daftar?'))return;authRequest('api/guru').then(function(x){var g=(x.data||[]).find(function(v){return v.nama_lengkap===name});if(!g)throw Error('Guru tidak ditemukan');return authRequest('api/guru/'+g.id,{method:'DELETE',body:'{}'})}).then(function(x){toast(x.message||'Guru berhasil dihapus');render()}).catch(function(x){toast(x.message||'Guru gagal dihapus')})});
})();
