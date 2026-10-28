(function () {
  var version=0, date='', selectedTeacher='', busy=false;
  function esc(value){return escapeHtml(value==null?'':String(value))}
  function today(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())}
  function key(){return String((window.currentUser||{}).id)+':'+window.activeClassId+':'+window.currentRole}
  function uuid(){return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,function(c){var n=Math.floor(Math.random()*16);return (c==='x'?n:(n&3|8)).toString(16)})}
  function student(){return window.currentRole==='student'&&!(window.currentUser||{}).isParent&&window.page==='attendance'}
  async function compress(file){
    if(!file||!['image/jpeg','image/png','image/webp'].includes(file.type))throw new Error('Pilih foto JPG, PNG, atau WebP.');
    if(file.size>20*1024*1024)throw new Error('Ukuran foto maksimal 20 MB sebelum diperkecil.');
    var source=await createImageBitmap(file);
    try{
      if(source.width*source.height>50000000)throw new Error('Resolusi foto terlalu besar. Pilih ukuran foto yang lebih kecil.');
      var scale=Math.min(1,2000/Math.max(source.width,source.height)),canvas=document.createElement('canvas');
      canvas.width=Math.max(1,Math.round(source.width*scale));canvas.height=Math.max(1,Math.round(source.height*scale));
      var ctx=canvas.getContext('2d');ctx.fillStyle='#ffffff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(source,0,0,canvas.width,canvas.height);
      for(var quality of [0.86,0.72,0.55]){
        var blob=await new Promise(function(resolve){canvas.toBlob(resolve,'image/jpeg',quality)});
        if(blob&&blob.size<1800000)return blob;
      }
      throw new Error('Foto masih terlalu besar. Pilih foto dengan ukuran lebih kecil.');
    }finally{source.close()}
  }
  function photoCard(row,own){
    return '<article class="photo-entry"><a href="'+esc(row.url)+'" target="_blank" rel="noopener"><img src="'+esc(row.url)+'" loading="lazy" alt="Foto '+esc(own?'milik Anda':row.nama_lengkap)+'"></a><div class="photo-entry-body">'+(own?'':'<strong>'+esc(row.nama_lengkap)+'</strong><small>NIS '+esc(row.nis)+'</small>')+'<p class="photo-description">'+esc(row.keterangan)+'</p></div></article>';
  }
  function mount(){
    if(!student())return;
    var root=document.querySelector('#pageContent .student-attendance-page'),recap=root&&root.querySelector('.student-attendance-recap');
    if(!root||!recap||root.querySelector('#studentPhotoUploadCard'))return;
    var card=document.createElement('section');card.id='studentPhotoUploadCard';card.className='panel student-photo-upload';
    card.innerHTML='<div class="panel-head"><div><h2>Upload Foto</h2><p>Kirim foto kegiatan Anda beserta keterangannya kepada wali kelas.</p></div></div><p class="photo-guide">Maksimal 35 foto per kelas pada tanggal yang sama. Foto akan disusun menjadi kolase satu halaman oleh wali kelas.</p><form id="studentPhotoUploadForm"><label for="studentPhotoFile">Pilih foto<input id="studentPhotoFile" type="file" accept="image/jpeg,image/png,image/webp" required><small>JPG, PNG, atau WebP, maksimal 20 MB. Foto akan diperkecil otomatis sebelum diunggah.</small></label><img id="studentPhotoPreview" class="photo-upload-preview" alt="Pratinjau foto yang dipilih" hidden><label for="studentPhotoNote">Keterangan foto<textarea id="studentPhotoNote" maxlength="2000" rows="3" required placeholder="Contoh: Kegiatan praktik hari ini, lokasi kegiatan, atau informasi yang perlu diketahui wali kelas."></textarea><small>Wajib diisi, maksimal 2.000 karakter.</small></label><p id="studentPhotoMessage" role="status" aria-live="polite"></p><button type="submit" class="primary-btn">Upload Foto</button></form><div class="panel-head photo-history-head"><h3>Foto Terakhir Anda</h3><button type="button" class="outline-btn" id="refreshStudentPhotos">Muat ulang</button></div><div class="photo-grid" id="studentPhotoHistory"></div>';
    root.appendChild(card);
    var context=key(),form=card.querySelector('form'),file=card.querySelector('#studentPhotoFile'),note=card.querySelector('#studentPhotoNote'),message=card.querySelector('#studentPhotoMessage'),preview=card.querySelector('#studentPhotoPreview'),requestKey=uuid(),previewUrl='';
    function active(){return card.isConnected&&student()&&key()===context}
    function history(){return authRequest('api/upload-foto',{cache:'no-store'}).then(function(data){if(active())card.querySelector('#studentPhotoHistory').innerHTML=(data.data||[]).map(function(row){return photoCard(row,true)}).join('')||'<p class="stat-note">Belum ada foto yang diunggah.</p>'}).catch(function(error){if(active())card.querySelector('#studentPhotoHistory').textContent=error.message})}
    file.onchange=function(){requestKey=uuid();if(previewUrl)URL.revokeObjectURL(previewUrl);preview.hidden=!file.files.length;if(file.files[0]){previewUrl=URL.createObjectURL(file.files[0]);preview.src=previewUrl}};
    card.querySelector('#refreshStudentPhotos').onclick=history;history();
    form.onsubmit=async function(event){
      event.preventDefault();if(!form.reportValidity())return;
      var button=form.querySelector('[type=submit]');button.disabled=true;button.textContent='Menyiapkan foto...';message.textContent='';
      try{
        var blob=await compress(file.files[0]);if(!active())return;
        var body=new FormData();body.append('foto',blob,'foto.jpg');body.append('keterangan',note.value.trim());body.append('request_key',requestKey);button.textContent='Mengunggah...';
        var result=await authRequest('api/upload-foto',{method:'POST',body:body});if(!active())return;
        message.className='photo-success';message.textContent=result.message;form.reset();file.onchange();await history();
      }catch(error){if(active()){message.className='login-error';message.textContent=error.message;history()}}
      finally{button.disabled=false;button.textContent='Upload Foto'}
    };
  }
  function renderUploads(){
    if(window.currentRole!=='walas')return;
    date=date||today();var root=document.getElementById('pageContent'),context=key(),request=++version;busy=false;
    root.innerHTML='<div class="page photo-uploads-page"><div class="page-heading"><div><div class="eyebrow">Dokumentasi siswa</div><h1>Upload Foto</h1><p>Dokumentasi foto siswa sesuai kelas dan tanggal yang dipilih.</p></div></div><section class="panel photo-tools"><div class="photo-tool-row"><label for="photoUploadDate">Tanggal foto<input id="photoUploadDate" type="date" value="'+esc(date)+'" class="filter-btn"></label><button class="outline-btn" id="refreshClassPhotos">Muat ulang</button><button class="primary-btn" id="buildPhotoCollage" disabled>Buat Kolase</button></div><p id="photoCount" class="stat-note">Memuat foto...</p><div class="photo-tool-row"><label for="collageTeacher">Guru penerima<select id="collageTeacher" class="filter-btn"><option value="">Pilih guru tujuan</option></select></label><button class="primary-btn" id="sendPhotoCollage" disabled>Kirim Kolase</button></div><p class="stat-note">Nomor tujuan mengikuti Data Guru. Periksa pratinjau kolase sebelum mengirim.</p><p id="photoPageMessage" role="status" aria-live="polite"></p></section><section class="panel" id="collagePreviewPanel" hidden><div class="panel-head"><div><h2>Pratinjau Kolase</h2><p id="collageSummary"></p></div></div><div id="collagePreview" class="collage-grid"></div></section><section class="panel"><div class="panel-head"><h2>Foto yang Terkumpul</h2></div><div class="photo-grid" id="classPhotoGallery"></div><button class="outline-btn" id="moreClassPhotos" hidden>Tampilkan foto berikutnya</button></section></div>';
    var dateInput=document.getElementById('photoUploadDate'),build=document.getElementById('buildPhotoCollage'),send=document.getElementById('sendPhotoCollage'),teacher=document.getElementById('collageTeacher'),message=document.getElementById('photoPageMessage'),more=document.getElementById('moreClassPhotos'),gallery=document.getElementById('classPhotoGallery'),collage=null,total=0,page=0;
    var crumb=document.getElementById('breadcrumbCurrent');if(crumb)crumb.textContent='Upload Foto';
    function active(){return request===version&&window.page==='photoUploads'&&window.currentRole==='walas'&&key()===context}
    function buttons(){build.disabled=busy||total===0;send.disabled=busy||!collage||!collage.terbaru||!teacher.value;dateInput.disabled=busy;teacher.disabled=busy;more.disabled=busy;document.getElementById('refreshClassPhotos').disabled=busy}
    function showCollage(value){
      collage=value;document.getElementById('collagePreviewPanel').hidden=!collage;
      if(collage){document.getElementById('collageSummary').textContent=collage.jumlah_foto+' foto · 1 halaman. '+(collage.terbaru?'Seluruh foto yang terkumpul sudah masuk.':'Ada foto baru; tekan Buat Kolase kembali.');document.getElementById('collagePreview').innerHTML=collage.halaman.map(function(item){return '<article><a href="'+esc(item.url)+'" target="_blank" rel="noopener"><img src="'+esc(item.url)+'" alt="Kolase seluruh foto dalam satu halaman" loading="lazy"></a><p>Kolase satu halaman</p><a class="panel-action" href="'+esc(item.url)+'?download=1">Unduh kolase</a></article>'}).join('')}
      buttons();
    }
    async function load(next){
      if(!active())return;
      more.disabled=true;
      try{
        var data=await authRequest('api/upload-foto?kelas_id='+window.activeClassId+'&tanggal='+encodeURIComponent(date)+'&page='+next,{cache:'no-store'});if(!active())return;
        total=data.total;page=next;document.getElementById('photoCount').textContent=total+' dari maksimal 35 foto pada '+date.split('-').reverse().join('-')+'. Kolase memuat seluruh foto pada tanggal ini, termasuk foto di halaman berikutnya.';
        if(next===1){gallery.innerHTML='';teacher.innerHTML='<option value="">Pilih guru tujuan</option>'+data.guru.map(function(row){return '<option value="'+row.id+'"'+(!row.nomor_valid?' disabled':'')+'>'+esc(row.nama_lengkap)+' — '+esc(row.nomor_hp||'Nomor belum tersedia')+'</option>'}).join('');teacher.value=selectedTeacher;showCollage(data.kolase)}
        gallery.insertAdjacentHTML('beforeend',data.data.map(function(row){return photoCard(row,false)}).join('')||(next===1?'<p class="stat-note">Belum ada foto siswa pada tanggal ini.</p>':''));more.hidden=!data.has_more;buttons();
      }catch(error){if(active()){message.className='login-error';message.textContent=error.message;more.disabled=false}}
    }
    dateInput.onchange=function(){date=dateInput.value;renderUploads()};document.getElementById('refreshClassPhotos').onclick=function(){load(1)};more.onclick=function(){load(page+1)};teacher.onchange=function(){selectedTeacher=teacher.value;buttons()};
    build.onclick=async function(){
      busy=true;buttons();message.className='';message.textContent='Membuat kolase dari seluruh foto...';
      try{var result=await authRequest('api/upload-foto/kolase',{method:'POST',body:JSON.stringify({kelas_id:window.activeClassId,tanggal:date})});if(active()){showCollage(result.data);message.className='photo-success';message.textContent='Kolase berhasil dibuat. Periksa kolase satu halaman sebelum mengirim.'}}
      catch(error){if(active()){message.className='login-error';message.textContent=error.message}}
      finally{busy=false;if(active())buttons()}
    };
    send.onclick=function(){
      if(!collage||!teacher.value)return;
      var destination=teacher.selectedOptions[0].textContent,item=collage.halaman[0],teacherId=Number(teacher.value);
      modal('Kirim Kolase','Periksa guru tujuan dan kolase sebelum mengirim melalui gateway.','<p><strong>Penerima:</strong> '+esc(destination)+'</p><p>'+collage.jumlah_foto+' foto · 1 halaman · '+esc(date.split('-').reverse().join('-'))+'</p><p>Hasil pengiriman dicatat pada menu Komunikasi.</p><p id="collageSendMessage" role="status"></p><div class="modal-actions"><button type="button" class="filter-btn" id="cancelModal">Batal</button><button type="button" class="primary-btn" id="confirmSendCollage">Kirim Kolase</button></div>');
      var confirm=document.getElementById('confirmSendCollage'),feedback=document.getElementById('collageSendMessage');
      confirm.onclick=async function(){
        if(busy)return;busy=true;buttons();confirm.disabled=true;document.getElementById('cancelModal').disabled=true;
        try{
          if(!active())throw new Error('Pengiriman dihentikan karena halaman atau akun berubah.');
          feedback.textContent='Mengirim kolase...';
          await authRequest('api/upload-foto/kolase/halaman/'+item.id+'/kirim',{method:'POST',body:JSON.stringify({guru_id:teacherId})});
          if(active()){closeModal();message.className='photo-success';message.textContent='Kolase satu halaman diterima gateway. Lihat hasilnya di Komunikasi.'}
        }catch(error){if(feedback.isConnected){feedback.className='login-error';feedback.textContent=error.message+' Periksa Komunikasi sebelum mencoba lagi.'}if(active()){message.className='login-error';message.textContent='Pengiriman kolase belum terkonfirmasi. '+error.message}}
        finally{busy=false;if(active())buttons();if(confirm.isConnected){confirm.textContent='Proses selesai';document.getElementById('cancelModal').disabled=false;document.getElementById('cancelModal').textContent='Tutup'}}
      };
    };
    load(1);
  }
  window.roleAccess.walas=(window.roleAccess.walas||[]).concat(['photoUploads']);window.names.photoUploads='Upload Foto';
  var access=window.applyRoleAccess;window.applyRoleAccess=function(){access.apply(this,arguments);var menu=document.querySelector('[data-page="photoUploads"]');if(menu)menu.style.display=window.currentRole==='walas'?'flex':'none'};
  window.AIWalasPages.photoUploads=renderUploads;
  var attendance=window.AIWalasPages.studentAttendance;window.AIWalasPages.studentAttendance=function(){var result=attendance.apply(this,arguments);mount();return result};
  new MutationObserver(mount).observe(document.getElementById('pageContent'),{childList:true,subtree:true});mount();
})();
