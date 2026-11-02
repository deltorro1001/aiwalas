(function(){
  var inFlight=new Set(),months=['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'];
  function date(value){
    value=String(value||'').trim().toLowerCase().replace(/^(?:tanggal|tgl\.?)[\s:]+/,'');
    if(value==='hari ini'||value==='kemarin'){
      var today=window.AIWalasCalendar?AIWalasCalendar.today():new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
      var local=new Date(today+'T12:00:00Z');if(value==='kemarin')local.setUTCDate(local.getUTCDate()-1);return local.toISOString().slice(0,10);
    }
    var year,month,day,match;
    if((match=/^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(value))){year=match[1];month=match[2];day=match[3]}
    else if((match=/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/.exec(value))){year=match[3];month=match[2];day=match[1]}
    else if((match=/^(\d{1,2})\s+([a-z]+)\s+(\d{4})$/.exec(value))){year=match[3];month=months.indexOf(match[2])+1;day=match[1]}
    else return '';
    if(Number(year)<1000||Number(month)<1||Number(month)>12||Number(day)<1||Number(day)>31)return '';
    var iso=year+'-'+String(month).padStart(2,'0')+'-'+String(day).padStart(2,'0'),result=new Date(iso+'T12:00:00Z');
    return !isNaN(result)&&result.toISOString().slice(0,10)===iso?iso:'';
  }
  function parse(question){
    var head=/^\s*(?:(?:tolong|mohon)\s+)?kirim(?:kan)?\s+(?:hasil\s+)?kolase\b(.*)$/i.exec(String(question||''));if(!head)return null;
    var parts=/^(.*?)\s+(?:ke|kepada)\b\s*(.*)$/i.exec(head[1]),day=parts&&date(parts[1]);
    if(!day)return {error:'Tuliskan tanggal yang valid. Contoh: Kirim kolase 03-11-2026 ke Ibu Nani. Bisa juga memakai 2026-11-03, 3 November 2026, hari ini, atau kemarin.'};
    var target=parts[2].trim().replace(/[.!?]+$/,'').trim().replace(/^(?:nomor(?:\s+(?:telepon|whatsapp|wa|hp))?|no\.?\s*(?:telepon|whatsapp|wa|hp)?)\s*:?\s*(?=[+\d])/i,'');
    if(!target)return {error:'Isi nama atau nomor tujuan setelah kata ke. Contoh: Kirim kolase '+day+' ke 081234567890.'};
    if(/^[+\d][\d\s().-]*$/.test(target)){
      var number=window.AIWalasIntroduction&&AIWalasIntroduction.normalizePhone(target);
      return number?{date:day,target:number,number:true}:{error:'Nomor WhatsApp tujuan tidak valid. Gunakan format 08… atau 628….'};
    }
    if(target.length>120)return {error:'Nama penerima maksimal 120 karakter.'};
    return {date:day,target:target,number:false};
  }
  var previous=window.helpQuestion;
  window.helpQuestion=function(){
    previous.apply(this,arguments);
    var input=document.getElementById('helpQuestionText'),button=document.getElementById('sendHelpQuestion');if(!input||!button)return;
    var fallback=button.onclick,pending=false;
    if(window.currentRole==='walas'){
      var hint=document.createElement('p');hint.id='helpCollageHint';hint.className='stat-note';hint.textContent='Kolase: “Kirim kolase 03-11-2026 ke Ibu Nani” atau “Kirim kolase hari ini ke 081234567890”. Kolase terbaru akan disiapkan dan dikirim langsung tanpa pratinjau atau konfirmasi.';input.closest('form').insertBefore(hint,input.closest('form').querySelector('.modal-actions'));
    }
    async function send(contact,command){
      var error=document.getElementById('helpError'),classId=Number(window.activeClassId),owner=(window.currentUser||{}).id,phone=AIWalasIntroduction.normalizePhone(contact.phone);
      if(!phone){error.textContent='Nomor penerima belum tersedia. Gunakan nama lengkap lain atau nomor telepon langsung.';return}
      if(!Number.isInteger(classId)||classId<1){error.textContent='Pilih kelas aktif sebelum mengirim kolase.';return}
      var key=[owner,classId,command.date,phone].join(':');if(pending||inFlight.has(key)){error.textContent='Kolase tanggal tersebut sedang diproses untuk nomor ini. Tunggu hasilnya.';return}
      var form=input.closest('form');
      function active(){var backdrop=document.getElementById('modalBackdrop');return form.isConnected&&(!backdrop||backdrop.classList.contains('show'))&&window.currentRole==='walas'&&(window.currentUser||{}).id===owner&&Number(window.activeClassId)===classId}
      var box=document.getElementById('helpAnswer');if(!box){box=document.createElement('div');box.id='helpAnswer';box.className='alert-box';form.insertBefore(box,form.querySelector('.modal-actions'))}
      var label=command.date.split('-').reverse().join('-'),recipient=contact.name?((contact.salutation?contact.salutation+' ':'')+contact.name):phone;
      pending=true;inFlight.add(key);button.disabled=true;input.disabled=true;button.textContent='Memproses kolase…';error.textContent='';box.textContent='Menyiapkan kolase seluruh foto tanggal '+label+'…';
      try{
        var built=await authRequest('api/upload-foto/kolase',{method:'POST',body:JSON.stringify({kelas_id:classId,tanggal:command.date})});
        if(!active())throw new Error('Pengiriman dihentikan karena dialog, kelas, atau akun berubah.');
        var data=built&&built.data,pages=data&&data.halaman;
        if(!built||built.ok!==true||!Array.isArray(pages)||pages.length!==1||!pages[0]||!Number.isInteger(Number(pages[0].id))||Number(pages[0].id)<1||!Number.isInteger(Number(data.jumlah_foto))||Number(data.jumlah_foto)<1||Number(data.jumlah_foto)>35)throw new Error('Kolase satu halaman belum tersedia.');
        box.textContent='Mengirim kolase tanggal '+label+' ke '+(contact.name||phone)+'…';button.textContent='Mengirim…';
        var result=await authRequest('api/upload-foto/kolase/halaman/'+Number(pages[0].id)+'/kirim',{method:'POST',body:JSON.stringify({nomor_tujuan:phone,nama_penerima:recipient})});
        if(!result||result.ok!==true||result.status!=='sent')throw new Error(result&&result.message||'Gateway belum mengonfirmasi pengiriman.');
        if(active()){box.textContent='Kolase tanggal '+label+' '+(result.already?'sudah pernah diterima gateway untuk ':'terkirim melalui gateway ke ')+(contact.name||'nomor tujuan')+' ('+phone+'). '+(result.already?'Tidak dikirim ulang. ':'')+'Hasil tercatat di menu Komunikasi.';input.value='';toast(result.already?'Kolase sudah pernah terkirim.':'Kolase terkirim.')}
      }catch(reason){if(active()){box.textContent='Kolase belum berhasil dikonfirmasi terkirim.';error.textContent=(reason.message||'Proses kolase gagal.')+' Periksa Komunikasi sebelum mengirim ulang.';}}
      finally{pending=false;inFlight.delete(key);if(form.isConnected){button.disabled=false;input.disabled=false;button.textContent='Kirim'}}
    }
    button.onclick=function(){
      if(pending)return;
      var question=input.value.trim(),command=parse(question);if(!command)return fallback.apply(this,arguments);
      var error=document.getElementById('helpError'),old=document.getElementById('helpAnswer');error.textContent='';if(old)old.textContent='';
      if(window.currentRole!=='walas'){error.textContent='Perintah kirim kolase hanya tersedia untuk wali kelas.';return}
      if(command.error){error.textContent=command.error;return}
      if(!window.AIWalasIntroduction){error.textContent='Data kontak belum siap. Muat ulang halaman.';return}
      var matches=AIWalasIntroduction.resolve(command.target);
      if(command.number){send(matches.length===1?matches[0]:{name:'',phone:command.target},command);return}
      if(matches.length===1){send(matches[0],command);return}
      if(!matches.length){error.textContent='Nama penerima belum ditemukan. Gunakan nama lengkap yang tersimpan atau nomor telepon tujuan.';return}
      var box=old||document.createElement('div');box.id='helpAnswer';box.className='alert-box';if(!old)input.closest('form').insertBefore(box,input.closest('form').querySelector('.modal-actions'));
      var title=document.createElement('p');title.textContent='Beberapa kontak cocok. Pilih penerima yang dimaksud; kolase akan langsung dikirim.';box.appendChild(title);
      var choices=document.createElement('div');choices.style.cssText='max-height:230px;overflow:auto';box.appendChild(choices);var owner=(window.currentUser||{}).id,classId=window.activeClassId;
      matches.forEach(function(contact){var choice=document.createElement('button');choice.type='button';choice.className='outline-btn help-collage-choice';choice.style.cssText='display:block;width:100%;margin-top:8px;text-align:left;white-space:normal;overflow-wrap:anywhere';choice.textContent=contact.label+(contact.phone?' · '+contact.phone:' · Nomor belum tersedia');choice.onclick=function(){if(input.isConnected&&window.currentRole==='walas'&&(window.currentUser||{}).id===owner&&window.activeClassId===classId&&input.value.trim()===question)send(contact,command)};choices.appendChild(choice)});
    };
  };
  var help=document.getElementById('helpQuestion');if(help)help.onclick=window.helpQuestion;
  window.AIWalasCollageCommands={parse:parse};
})();
