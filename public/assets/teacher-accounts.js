(function(){
  var version=0;
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function accountForm(teacher){
    var reset=!!teacher.nama_pengguna, suggested=teacher.nama_pengguna||String(teacher.nama_panggilan||teacher.nama_lengkap).toLowerCase().replace(/^(pak|bu|bapak|ibu)\s+/,'').replace(/[^a-z0-9._-]/g,'').slice(0,100);
    modal(reset?'Reset Password Guru':'Buat Akun Guru',teacher.nama_lengkap,
      '<form id="teacherAccountForm"><label>Username<input id="teacherAccountUsername" required minlength="3" maxlength="100" pattern="[a-zA-Z0-9._-]+" autocomplete="off" value="'+esc(suggested)+'"'+(reset?' readonly':'')+'></label><p>'+(reset?'Password sebelumnya tidak berlaku setelah reset.':'Password baru akan dibuat otomatis.')+'</p><p id="teacherAccountError" class="login-error"></p><div class="modal-actions"><button type="button" id="cancelModal" class="filter-btn">Batal</button><button type="submit" class="primary-btn">'+(reset?'Reset Password':'Buat Akun')+'</button></div></form>');
    document.getElementById('teacherAccountForm').onsubmit=function(event){
      event.preventDefault();var button=this.querySelector('[type=submit]');button.disabled=true;
      authRequest('api/akun-guru/'+teacher.id,{method:'POST',body:JSON.stringify({username:document.getElementById('teacherAccountUsername').value.trim(),reset:reset})})
        .then(function(data){
          modal('Akun Guru Siap',teacher.nama_lengkap,
            '<p>Pilih peran <strong>Guru</strong> saat login. Simpan kredensial ini sebelum menutup dialog.</p><label>Username<input readonly autocomplete="off" value="'+esc(data.username)+'"></label><label>Password awal<input readonly autocomplete="off" value="'+esc(data.password)+'"></label><p>Password hanya ditampilkan sekarang. Guru dapat menggantinya melalui Pengaturan setelah masuk.</p><div class="modal-actions"><button type="button" id="cancelModal" class="primary-btn">Tutup</button></div>');
          renderAccounts();
        }).catch(function(error){var target=document.getElementById('teacherAccountError');if(target)target.textContent=error.message;button.disabled=false;});
    };
  }
  function renderAccounts(){
    if(window.currentRole!=='walas'){document.getElementById('pageContent').textContent='Halaman ini hanya tersedia untuk Wali Kelas.';return;}
    var current=++version;
    document.getElementById('pageContent').innerHTML='<p>Memuat akun guru...</p>';
    authRequest('api/akun-guru').then(function(data){
      if(current!==version||window.page!=='teacherAccounts')return;
      var teachers=data.data||[];
      document.getElementById('pageContent').innerHTML='<div class="page"><div class="page-heading"><div><h1>Akun Guru</h1><p>Buat akun atau reset password agar guru dapat masuk. Akses kelas dan mata pelajaran mengikuti jadwal mengajar.</p></div></div><section class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Guru</th><th>Username</th><th>Status</th><th>Aksi</th></tr></thead><tbody>'+teachers.map(function(teacher){return '<tr><td>'+esc(teacher.nama_lengkap)+'</td><td>'+esc(teacher.nama_pengguna||'—')+'</td><td>'+(teacher.nama_pengguna?(Number(teacher.akun_aktif)?'Aktif':'Nonaktif'):'Belum dibuat')+'</td><td><button type="button" class="outline-btn" data-teacher-account="'+teacher.id+'">'+(teacher.nama_pengguna?'Reset Password':'Buat Akun')+'</button></td></tr>'}).join('')+'</tbody></table></div></section></div>';
      document.querySelectorAll('[data-teacher-account]').forEach(function(button){button.onclick=function(){accountForm(teachers.find(function(t){return String(t.id)===button.dataset.teacherAccount}))}});
    }).catch(function(error){if(current===version&&window.page==='teacherAccounts')document.getElementById('pageContent').textContent=error.message;});
  }
  window.names.teacherAccounts='Akun Guru';
  window.roleAccess.walas=(window.roleAccess.walas||[]).concat(['teacherAccounts']);
  window.AIWalasPages=window.AIWalasPages||{};window.AIWalasPages.teacherAccounts=renderAccounts;
})();
