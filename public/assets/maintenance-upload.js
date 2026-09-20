(function(){
function page(){
 var root=document.getElementById('pageContent');
 root.innerHTML='<div class=page><h1>Maintenance</h1><p>Upload patch aplikasi ke VPS.</p><section class=panel><input id=patchFile type=file accept=.zip><button id=uploadPatch class=primary-btn>Upload Patch</button><p id=patchStatus></p><button id=applyPatch class=outline-btn style=display:none>Terapkan Patch ke VPS</button></section></div>';
 var name='',status=document.getElementById('patchStatus');
 document.getElementById('uploadPatch').onclick=function(){var f=document.getElementById('patchFile').files[0],d=new FormData();if(!f){status.textContent='Pilih file ZIP.';return}d.append('patch',f);status.textContent='Mengunggah…';fetch('api/maintenance/upload',{method:'POST',body:d,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(x){if(!x.ok)throw Error(x.message);name=x.name;status.textContent='Patch tersimpan: '+name;document.getElementById('applyPatch').style.display='inline-block'}).catch(function(e){status.textContent=e.message})};
 document.getElementById('applyPatch').onclick=function(){status.textContent='Menerapkan…';authRequest('api/maintenance/apply',{method:'POST',body:JSON.stringify({name:name})}).then(function(x){status.textContent=x.message}).catch(function(e){status.textContent=e.message})};
}
document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('[data-page=maintenance]');if(b){e.preventDefault();window.page='maintenance';page()}});
})();
