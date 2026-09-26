(function(){
function importScheduleFile(input){
 var file=input.files&&input.files[0];if(!file)return;
 if(!window.XLSX){toast('Pembaca Excel belum tersedia.');return}
 file.arrayBuffer().then(function(buffer){
  var workbook=XLSX.read(buffer,{type:'array'}),sheet=workbook.Sheets[workbook.SheetNames[0]],data=XLSX.utils.sheet_to_json(sheet,{header:1,defval:''}),headers=(data.shift()||[]).map(function(value){return String(value).toLowerCase().trim()}),column=function(names){return headers.findIndex(function(value){return names.indexOf(value)>=0})},day=column(['hari']),start=column(['jam mulai','mulai']),end=column(['jam selesai','selesai']),subject=column(['mata pelajaran','mapel']),teacher=column(['nama guru','guru']);
  if([day,start,end,subject].some(function(index){return index<0})){toast('Template wajib memiliki Hari, Jam Mulai, Jam Selesai, dan Mata Pelajaran.');return}
  var rows=data.filter(function(row){return row[day]&&row[start]&&row[end]&&row[subject]}).map(function(row){return{hari:String(row[day]).trim(),jam_mulai:String(row[start]).slice(0,5),jam_selesai:String(row[end]).slice(0,5),mata_pelajaran:String(row[subject]).trim(),guru:teacher>=0?String(row[teacher]||'').trim():''}});
  authRequest('api/jadwal/import',{method:'POST',body:JSON.stringify({kelas_id:activeClassId,rows:rows})}).then(function(response){toast(response.message||'Jadwal berhasil diimpor.');render()}).catch(function(error){toast(error.message||'Import jadwal gagal.')});
 }).catch(function(){toast('File jadwal tidak dapat dibaca.')});
}
function enhanceScheduleActions(){
 if(typeof page==='undefined'||page!=='schedule'||typeof currentRole==='undefined'||currentRole!=='walas')return;
 var heading=document.querySelector('#pageContent .page-heading');if(!heading)return;
 var buttons=heading.querySelectorAll('button'),manage=null;
 buttons.forEach(function(button){if(!manage&&(/(?:kelola|tambah)\s+jadwal/i.test(button.textContent)||button.id==='addScheduleDb'))manage=button});
 if(!manage)return;if(manage.textContent.trim()!=='▦ Kelola Jadwal')manage.textContent='▦ Kelola Jadwal';
 var actions=manage.closest('.schedule-heading-actions');
 if(!actions){actions=document.createElement('div');actions.className='schedule-heading-actions';manage.parentNode.insertBefore(actions,manage);actions.appendChild(manage)}
 if(!manage.id){manage.id='manageScheduleBtn';manage.onclick=function(){if(window.AIWalasUI&&typeof window.AIWalasUI.schedule==='function')window.AIWalasUI.schedule()}}
 var importButton=heading.querySelector('#importScheduleBtn');if(importButton&&!actions.contains(importButton))actions.appendChild(importButton);
 if(!importButton){
  importButton=document.createElement('button');importButton.type='button';importButton.className='primary-btn';importButton.id='importScheduleBtn';importButton.textContent='Import Jadwal';actions.appendChild(importButton);
  var input=document.createElement('input');input.id='baseScheduleFileInput';input.type='file';input.accept='.xls,.xlsx';input.hidden=true;actions.appendChild(input);
  importButton.onclick=function(){input.value='';input.click()};input.onchange=function(){importScheduleFile(input)};
 }
 var dynamicInput=heading.querySelector('#scheduleFileInput');if(dynamicInput&&!actions.contains(dynamicInput))actions.appendChild(dynamicInput);
}
var root=document.getElementById('pageContent');
if(root&&window.MutationObserver)new MutationObserver(enhanceScheduleActions).observe(root,{childList:true,subtree:true});
document.addEventListener('click',function(){setTimeout(enhanceScheduleActions,0)});setTimeout(enhanceScheduleActions,0);
})();
