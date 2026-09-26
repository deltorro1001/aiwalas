(function(){
document.addEventListener('click',function(e){
 var button=e.target.closest&&e.target.closest('#uploadPatch');
 if(!button)return;
 e.preventDefault();e.stopImmediatePropagation();
 var file=document.getElementById('patchFile').files[0],status=document.getElementById('patchStatus'),apply=document.getElementById('applyPatch');
 if(!file){status.textContent='Pilih file ZIP.';return}
 status.textContent='Menyiapkan token keamanan...';
 fetch('api/auth/csrf',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(token){var meta=document.querySelector('meta[name=csrf-token]');if(meta&&token.csrfHash)meta.content=token.csrfHash;var body=new FormData(),headers={Accept:'application/json'};body.append('patch',file);body.append('csrf_test_name',token.csrfHash);headers['X-CSRF-TOKEN']=token.csrfHash;status.textContent='Mengunggah...';return fetch('api/maintenance/upload',{method:'POST',credentials:'same-origin',headers:headers,body:body})}).then(function(r){return r.json().then(function(x){if(!r.ok)throw Error(x.message||'Upload gagal.');return x})}).then(function(x){window.aiwalasUploadedPatch=x.name;status.textContent='Patch tersimpan: '+x.name;apply.style.display='inline-block'}).catch(function(err){status.textContent=err.message});
 },true);
document.addEventListener('click',function(e){var button=e.target.closest&&e.target.closest('#applyPatch');if(!button||!window.aiwalasUploadedPatch)return;e.preventDefault();e.stopImmediatePropagation();var status=document.getElementById('patchStatus');status.textContent='Menerapkan...';authRequest('api/maintenance/apply',{method:'POST',body:JSON.stringify({name:window.aiwalasUploadedPatch})}).then(function(x){status.textContent=x.message;toast('Patch berhasil diterapkan')}).catch(function(err){status.textContent=err.message})},true);
})();
