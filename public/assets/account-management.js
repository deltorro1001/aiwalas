(function(){
  var version=0;
  var close=window.closeModal;
  window.closeModal=function(){var input=document.getElementById('managedAccountResultPassword');if(input){input.value='';input.removeAttribute('value')}return close.apply(this,arguments)};

  var roles={sekretaris:'Sekretaris Kelas',siswa:'Siswa',orang_tua:'Orangtua/Wali Murid',guru:'Guru'};
  function esc(value){return escapeHtml(value==null?'':String(value))}
  function reset(account){
    var owner=(window.currentUser||{}).id;
    modal('Reset Password',account.name,'<form id="managedAccountResetForm"><label>Peran<input readonly value="'+esc(roles[account.role])+'"></label><label>Username<input readonly value="'+esc(account.username)+'"></label><p>Setelah reset, pemilik akun harus menggunakan password baru. Username tetap sama.'+(account.shared_student?' Akun sekretaris ini juga digunakan saat masuk melalui pilihan Siswa.':'')+'</p><p id="managedAccountResetError" class="login-error" role="alert"></p><div class="modal-actions"><button type="button" class="filter-btn" id="cancelModal">Batal</button><button type="submit" class="primary-btn">Reset Password</button></div></form>');
    document.getElementById('managedAccountResetForm').onsubmit=function(event){
      event.preventDefault();var button=this.querySelector('[type=submit]');button.disabled=true;
      authRequest('api/kelola-akun/reset',{method:'POST',body:JSON.stringify({akun:account.key,konfirmasi:true})}).then(function(result){
        if((window.currentUser||{}).id!==owner||window.currentRole!=='walas')return;
        modal('Password Berhasil Direset',account.name,'<p>Pilih peran <strong>'+esc(roles[result.role])+'</strong> saat login. Salin password ini untuk disampaikan kepada pemilik akun.</p><label>Username<input id="managedAccountResultUsername" readonly autocomplete="off" value="'+esc(result.username)+'"></label><label>Password baru<input id="managedAccountResultPassword" readonly autocomplete="off" spellcheck="false" value="'+esc(result.password)+'"></label><p>Password ini hanya ditampilkan pada dialog ini. Pemilik akun dapat menggantinya melalui Pengaturan.</p><p id="managedAccountCopyStatus" class="stat-note" role="status"></p><div class="modal-actions"><button type="button" class="outline-btn" id="copyManagedAccountPassword">Salin Password</button><button type="button" class="primary-btn" id="cancelModal">Tutup</button></div>');
        document.getElementById('copyManagedAccountPassword').onclick=function(){var input=document.getElementById('managedAccountResultPassword'),status=document.getElementById('managedAccountCopyStatus');if(navigator.clipboard)navigator.clipboard.writeText(input.value).then(function(){status.textContent='Password berhasil disalin.'}).catch(function(){input.select();status.textContent='Salin teks password yang sudah dipilih.'});else{input.select();status.textContent='Salin teks password yang sudah dipilih.'}};
        if(window.page==='accountManagement')renderAccounts();
      }).catch(function(error){var box=document.getElementById('managedAccountResetError');if(box)box.textContent=error.message;button.disabled=false});
    };
  }
  function renderAccounts(){
    if(window.currentRole!=='walas')return;
    var current=++version,user=(window.currentUser||{}).id,all=[];
    document.getElementById('pageContent').innerHTML='<div class="page managed-accounts-page"><div class="page-heading"><div><div class="eyebrow">Pengaturan Akses</div><h1>Kelola Akun</h1><p>Lihat username dan reset password akun yang dikelola wali kelas.</p></div><button type="button" class="outline-btn" id="refreshManagedAccounts">Muat ulang</button></div><section class="panel"><p>Akun siswa, orangtua, dan sekretaris ditampilkan sesuai kelas perwalian Anda. Akun guru menampilkan guru yang terdaftar.</p><p class="stat-note">Password pengguna tersimpan sebagai hash dan tidak dapat dibaca. Password baru hasil reset hanya ditampilkan pada dialog hasil reset.</p></section><section class="panel table-panel managed-accounts-table"><div class="panel-head"><label>Cari akun<input type="search" id="managedAccountSearch" placeholder="Nama atau username"></label><label>Peran<select id="managedAccountRole" class="filter-btn"><option value="">Semua peran</option>'+Object.keys(roles).map(function(role){return '<option value="'+role+'">'+esc(roles[role])+'</option>'}).join('')+'</select></label></div><p id="managedAccountError" class="login-error" role="alert"></p><p id="managedAccountCount" class="stat-note"></p><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Peran</th><th>Username</th><th>Kelas</th><th>Status Akun</th><th>Aksi</th></tr></thead><tbody id="managedAccountRows"><tr><td colspan="6">Memuat akun...</td></tr></tbody></table></div></section></div>';
    function active(){return current===version&&window.currentRole==='walas'&&window.page==='accountManagement'&&(window.currentUser||{}).id===user}
    function paint(){
      var query=document.getElementById('managedAccountSearch').value.trim().toLowerCase(),role=document.getElementById('managedAccountRole').value;
      var rows=all.filter(function(account){return (!role||account.role===role)&&(!query||(account.name+' '+account.username).toLowerCase().indexOf(query)>=0)});
      document.getElementById('managedAccountCount').textContent=rows.length+' dari '+all.length+' akun';
      document.getElementById('managedAccountRows').innerHTML=rows.map(function(account){return '<tr><td>'+esc(account.name)+'</td><td>'+esc(roles[account.role])+'</td><td>'+esc(account.username)+'</td><td>'+esc(account.class||'—')+'</td><td>'+esc(account.status)+'</td><td><button type="button" class="outline-btn" data-managed-account="'+esc(account.key)+'"'+(account.can_reset?'':' disabled')+'>Reset Password</button></td></tr>'}).join('')||'<tr><td colspan="6">Tidak ada akun yang sesuai.</td></tr>';
      document.querySelectorAll('[data-managed-account]').forEach(function(button){button.onclick=function(){reset(all.find(function(account){return account.key===button.dataset.managedAccount}))}});
    }
    document.getElementById('managedAccountSearch').oninput=paint;document.getElementById('managedAccountRole').onchange=paint;document.getElementById('refreshManagedAccounts').onclick=renderAccounts;
    authRequest('api/kelola-akun',{cache:'no-store'}).then(function(result){if(active()){all=result.data||[];paint()}}).catch(function(error){if(active()){document.getElementById('managedAccountError').textContent=error.message;document.getElementById('managedAccountRows').innerHTML='<tr><td colspan="6">Daftar akun belum dapat dimuat.</td></tr>'}});
  }
  window.names.accountManagement='Kelola Akun';window.roleAccess.walas=(window.roleAccess.walas||[]).concat(['accountManagement']);
  window.AIWalasPages=window.AIWalasPages||{};window.AIWalasPages.accountManagement=renderAccounts;
})();
