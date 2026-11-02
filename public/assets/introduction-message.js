(function(){
  function greeting(){
    var hour=Number(new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Jakarta',hour:'2-digit',hourCycle:'h23'}).format(new Date()));
    return hour<11?'Selamat Pagi':hour<15?'Selamat Siang':hour<18?'Selamat Sore':'Selamat Malam';
  }
  function text(name,salutation){
    return greeting()+', '+([salutation,name].filter(Boolean).join(' ')||'Bapak/Ibu')+'.\n\n'+
      'Perkenalkan, saya AIWalas, asisten digital yang membantu pengelolaan kegiatan akademik dan komunikasi antara wali kelas, guru, siswa, serta orangtua/wali murid.\n\n'+
      'AIWalas membantu pencatatan kehadiran dan kedisiplinan siswa, penyajian jadwal pelajaran dan nilai, pengelolaan jurnal kelas, serta penyampaian informasi akademik melalui WhatsApp.\n\n'+
      'Aiwalas hadir untuk dapat memudahkan akses informasi akademik dan mendukung pendampingan belajar siswa. Terima kasih.';
  }
  function phone(value){var n=String(value||'').replace(/\D/g,'');if(n.indexOf('0')===0)n='62'+n.slice(1);return /^62\d{8,15}$/.test(n)?n:''}
  function contacts(includeMissing){
    var rows=[],seen={};
    function add(label,name,number,salutation,aliases){var normalized=phone(number);if(!normalized&&!includeMissing)return;var key=label+'|'+normalized;if(seen[key])return;seen[key]=true;rows.push({label:label,name:name,phone:normalized,salutation:salutation,aliases:[name].concat(aliases||[])})}
    (window.schoolTeachers||[]).forEach(function(t){add('Guru — '+t.name,t.name,t.phone,t.gender==='L'?'Bapak':t.gender==='P'?'Ibu':'Bapak/Ibu',[t.call||''])});
    (window.students||[]).forEach(function(t){add('Siswa — '+t[0],t[0],t[5],'');add('Orangtua — '+t[0],'Orangtua '+t[0],t[6],'Bapak/Ibu')});
    return rows.sort(function(a,b){return a.label.localeCompare(b.label,'id')});
  }
  function normalizeName(value){return String(value||'').normalize('NFKD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,' ').trim().replace(/^(?:bapak|ibu|pak|bu|bpk|sdr|sdri)\s+/,'').replace(/\s+/g,' ')}
  function resolve(target){
    if(window.currentRole!=='walas')return [];
    var rows=contacts(true);
    if(/^[+\d][\d\s().-]*$/.test(target)){var number=phone(target);return number?rows.filter(function(item){return item.phone===number}):[]}
    var key=normalizeName(target);if(!key)return [];
    var exact=rows.filter(function(item){return item.aliases.some(function(alias){return normalizeName(alias)===key})});
    if(exact.length)return exact;
    return rows.filter(function(item){return item.aliases.some(function(alias){return (' '+normalizeName(alias)+' ').indexOf(' '+key+' ')>=0})});
  }
  function open(prefill){
    prefill=prefill&&typeof prefill==='object'?prefill:{};
    if(window.currentRole!=='walas')return;
    var owner=(window.currentUser||{}).id,saved=contacts();
    modal('Perkenalan AIWalas','Isi nama penerima terlebih dahulu, lalu buat dan periksa narasinya. Pesan dapat disalin untuk digunakan kapan pun.',
      '<form id="aiwalasIntroForm" class="aiwalas-intro-form"><label>Nama penerima<input id="introName" name="nama" maxlength="120" placeholder="Nama orang yang akan diperkenalkan dengan AIWalas" autocomplete="off" required></label><label>Sapaan<select id="introSalutation"><option>Bapak/Ibu</option><option>Bapak</option><option>Ibu</option><option value="">Tanpa sapaan</option></select></label><button type="submit" class="outline-btn" id="introGenerate">Buat / Perbarui Narasi</button><label>Pratinjau pesan<textarea id="introMessage" rows="10" maxlength="5000" placeholder="Narasi akan muncul setelah nama penerima diisi dan tombol Buat Narasi ditekan."></textarea></label><p class="stat-note">Salam pada narasi dibuat sesuai waktu WIB. Anda dapat mengedit pesan sebelum menyalin atau mengirimnya.</p><label>Sumber nomor tujuan<select id="introPhoneSource"><option value="manual">Isi nomor tujuan secara manual</option><option value="saved">Pilih nomor tersimpan</option></select></label><label id="introSavedContactLabel" hidden>Kontak tersimpan<select id="introSavedContact"><option value="">Pilih guru, siswa, atau orangtua</option></select></label><p id="introPhoneHelp" class="stat-note">Nomor tujuan boleh diisi langsung meskipun belum ada dalam data nomor tersimpan.</p><label>Nomor WhatsApp tujuan (opsional)<input id="introPhone" type="tel" maxlength="30" placeholder="Contoh: 0812… atau 62812…" autocomplete="off"></label><p class="stat-note">Nomor tidak diperlukan jika hanya ingin menyalin pesan. Pengiriman gateway dilakukan setelah Anda menekan Kirim dan mengonfirmasi penerimanya.</p><p id="introError" class="login-error" role="alert"></p><p id="introFeedback" class="stat-note" role="status"></p><div class="modal-actions"><button type="button" class="filter-btn" id="cancelModal">Tutup</button><button type="button" class="outline-btn" id="introCopy" disabled>Salin Pesan</button><button type="button" class="primary-btn" id="introSend" disabled>Kirim via Gateway</button></div></form>');
    var form=document.getElementById('aiwalasIntroForm'),name=document.getElementById('introName'),salutation=document.getElementById('introSalutation'),message=document.getElementById('introMessage'),number=document.getElementById('introPhone'),copy=document.getElementById('introCopy'),send=document.getElementById('introSend'),generate=document.getElementById('introGenerate'),error=document.getElementById('introError'),feedback=document.getElementById('introFeedback'),generated=false,busy=false,source=document.getElementById('introPhoneSource'),contact=document.getElementById('introSavedContact'),contactLabel=document.getElementById('introSavedContactLabel'),phoneHelp=document.getElementById('introPhoneHelp');
    saved.forEach(function(item,index){var option=document.createElement('option');option.value=String(index);option.textContent=item.label+' · '+item.phone;contact.appendChild(option)});
    function active(){return document.getElementById('aiwalasIntroForm')===form&&window.currentRole==='walas'&&(window.currentUser||{}).id===owner}
    function state(){var ready=generated&&!!message.value.trim();copy.disabled=busy||!ready;send.disabled=busy||!ready||!number.value.trim();generate.disabled=busy;name.disabled=busy;salutation.disabled=busy;number.disabled=busy;message.disabled=busy;source.disabled=busy;contact.disabled=busy;number.readOnly=source.value==='saved';}
    function reset(){generated=false;message.value='';error.textContent='';feedback.textContent='Nama atau sapaan berubah. Tekan Buat / Perbarui Narasi untuk menyiapkan pesan baru.';state()}
    source.onchange=function(){contactLabel.hidden=source.value!=='saved';number.value='';contact.value='';phoneHelp.textContent=source.value==='saved'?(saved.length?'Pilih kontak untuk mengisi nama dan nomor tujuan.':'Belum ada nomor tersimpan yang valid. Pilih isian manual untuk memasukkan nomor baru.'):'Nomor tujuan boleh diisi langsung meskipun belum ada dalam data nomor tersimpan.';error.textContent='';state()};
    contact.onchange=function(){var item=contact.value===''?null:saved[Number(contact.value)];number.value=item?item.phone:'';if(item){name.value=item.name;salutation.value=item.salutation;reset()}state()};
    name.oninput=reset;salutation.onchange=reset;number.oninput=state;message.oninput=state;
    form.onsubmit=function(event){event.preventDefault();if(busy)return;if(!name.value.trim()){error.textContent='Isi nama penerima terlebih dahulu.';name.focus();return}message.value=text(name.value.trim(),salutation.value);generated=true;error.textContent='';feedback.textContent='Narasi siap diperiksa. Belum ada pesan yang dikirim.';state()};
    copy.onclick=async function(){
      if(!active()||copy.disabled)return;error.textContent='';
      try{
        if(navigator.clipboard&&navigator.clipboard.writeText)await navigator.clipboard.writeText(message.value);
        else{message.focus();message.select();if(!document.execCommand('copy'))throw new Error('copy');}
        if(active())feedback.textContent='Pesan berhasil disalin. Anda dapat menempelkannya di aplikasi yang diinginkan.';
      }catch(ignored){if(active()){message.focus();message.select();error.textContent='Pesan sudah dipilih. Gunakan Ctrl+C atau Cmd+C untuk menyalinnya.'}}
    };
    send.onclick=async function(){
      if(!active()||send.disabled||busy)return;error.textContent='';var destination=phone(number.value),classId=Number(window.activeClassId),recipient=(salutation.value?salutation.value+' ':'')+name.value.trim(),body=message.value.trim();
      if(!destination){error.textContent='Isi nomor WhatsApp yang valid, misalnya 0812… atau 62812…';return}
      if(!Number.isInteger(classId)||classId<1){error.textContent='Pilih kelas aktif sebelum mengirim melalui gateway. Pesan tetap dapat disalin.';return}
      if(typeof window.sendWhatsAppGateway!=='function'){error.textContent='Sesi pengiriman belum siap. Muat ulang halaman.';return}
      if(!confirm('Kirim pesan perkenalan AIWalas kepada '+recipient+' ('+destination+')?'))return;
      busy=true;state();send.textContent='Mengirim…';feedback.textContent='';
      try{
        await window.sendWhatsAppGateway({kelas_id:classId,nomor_tujuan:destination,nama_penerima:recipient,isi_pesan:body});
        if(active()){feedback.textContent='Pesan diterima gateway dan dicatat di menu Komunikasi.';toast('Perkenalan AIWalas terkirim.');closeModal();if(window.page==='communication')window.render()}
      }catch(failure){if(active())error.textContent=failure.message||'Pengiriman belum terkonfirmasi. Periksa histori Komunikasi sebelum mengirim ulang.';}
      finally{if(active()){busy=false;state();send.textContent='Kirim via Gateway'}}
    };
    if(prefill.name)name.value=String(prefill.name).slice(0,120);
    if(['Bapak/Ibu','Bapak','Ibu',''].indexOf(prefill.salutation)>=0)salutation.value=prefill.salutation;
    if(prefill.phone)number.value=phone(prefill.phone);
    if(prefill.generate&&name.value.trim())form.onsubmit({preventDefault:function(){}});
    if(prefill.notice)feedback.textContent=prefill.notice;
    state();name.focus();
  }
  window.AIWalasIntroduction={open:open,resolve:resolve,normalizePhone:phone,buildText:text};
})();
