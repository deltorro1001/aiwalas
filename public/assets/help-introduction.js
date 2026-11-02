(function(){
  function parse(question){
    var match=/^\s*(?:(?:tolong|mohon)\s+)?kirim(?:kan)?\s+perkenalan(?:\s+ai\s*walas)?(?:\s+(?:ke|kepada)\b\s*(.*))?\s*$/i.exec(String(question||''));
    if(!match)return null;
    var target=String(match[1]||'').trim().replace(/[.!?]+$/,'').trim();
    target=target.replace(/^(?:nomor(?:\s+(?:telepon|whatsapp|wa|hp))?|no\.?\s*(?:telepon|whatsapp|wa|hp)?)\s*:?\s*(?=[+\d])/i,'');
    if(!target)return {error:'Tuliskan nama penerima atau nomor tujuan. Contoh: Kirim perkenalan ke Ibu Nani atau Kirim perkenalan ke 081234567890.'};
    if(/^[+\d][\d\s().-]*$/.test(target)){
      var number=window.AIWalasIntroduction&&AIWalasIntroduction.normalizePhone(target);
      return number?{target:number,number:true}:{error:'Nomor tujuan tidak valid. Gunakan format 08… atau 628….'};
    }
    if(target.length>120)return {error:'Nama penerima maksimal 120 karakter.'};
    return {target:target,number:false};
  }
  var inFlight=new Set();
  var previous=window.helpQuestion;
  window.helpQuestion=function(){
    previous.apply(this,arguments);
    var input=document.getElementById('helpQuestionText'),button=document.getElementById('sendHelpQuestion');if(!input||!button)return;
    var fallback=button.onclick;
    if(window.currentRole==='walas'){
      var hint=document.createElement('p');hint.className='stat-note';hint.id='helpIntroductionHint';hint.textContent='Ketik “Kirim perkenalan ke Ibu Nani” atau “Kirimkan perkenalan aiwalas ke 081234567890” untuk langsung mengirim melalui gateway tanpa pratinjau atau konfirmasi. Pertanyaan rekap absensi tetap tersedia.';
      input.closest('form').insertBefore(hint,input.closest('form').querySelector('.modal-actions'));
    }
    var pending=false;
    function send(contact){
      var error=document.getElementById('helpError'),classId=Number(window.activeClassId),owner=(window.currentUser||{}).id,destination=AIWalasIntroduction.normalizePhone(contact.phone);
      if(!destination){error.textContent='Nomor WhatsApp penerima belum tersedia. Gunakan perintah Kirim perkenalan ke [nomor telepon].';return}
      if(!Number.isInteger(classId)||classId<1){error.textContent='Pilih kelas aktif sebelum mengirim perkenalan.';return}
      if(typeof window.sendWhatsAppGateway!=='function'){error.textContent='Sesi pengiriman belum siap. Muat ulang halaman.';return}
      var key=[owner,classId,destination].join(':');
      if(pending||inFlight.has(key)){error.textContent='Perkenalan ke nomor tersebut sedang dikirim. Tunggu hasilnya.';return}
      var form=input.closest('form');
      function active(){return form.isConnected&&window.currentRole==='walas'&&(window.currentUser||{}).id===owner&&Number(window.activeClassId)===classId}
      var box=document.getElementById('helpAnswer');if(!box){box=document.createElement('div');box.id='helpAnswer';box.className='alert-box';form.insertBefore(box,form.querySelector('.modal-actions'))}
      box.textContent='Mengirim perkenalan AIWalas ke '+(contact.name||destination)+'…';error.textContent='';pending=true;inFlight.add(key);button.disabled=true;input.disabled=true;button.textContent='Mengirim…';
      var recipient=contact.name?((contact.salutation?contact.salutation+' ':'')+contact.name):destination;
      var payload={kelas_id:classId,nomor_tujuan:destination,nama_penerima:recipient,isi_pesan:AIWalasIntroduction.buildText(contact.name||'',contact.salutation===undefined?'Bapak/Ibu':contact.salutation)};
      Promise.resolve().then(function(){return window.sendWhatsAppGateway(payload)}).then(function(result){
        if(!result||result.ok!==true)throw new Error(result&&result.message||'Gateway belum mengonfirmasi pengiriman.');
        if(active()){box.textContent='Perkenalan AIWalas terkirim melalui gateway ke '+(contact.name||'nomor tujuan')+' ('+destination+'). Riwayat tersimpan di menu Komunikasi.';input.value='';toast('Perkenalan AIWalas terkirim.')}
      }).catch(function(reason){if(active()){box.textContent='Perkenalan belum berhasil dikonfirmasi terkirim.';error.textContent=(reason.message||'Pengiriman gagal.')+' Periksa histori Komunikasi sebelum mengirim ulang.';}}).finally(function(){inFlight.delete(key);pending=false;if(form.isConnected){button.disabled=false;input.disabled=false;button.textContent='Kirim'}});
    }
    button.onclick=function(){
      if(pending)return;
      var question=input.value.trim(),command=parse(question);if(!command)return fallback.apply(this,arguments);
      var error=document.getElementById('helpError'),old=document.getElementById('helpAnswer');error.textContent='';if(old)old.textContent='';
      if(window.currentRole!=='walas'){error.textContent='Perintah perkenalan hanya tersedia untuk wali kelas.';return}
      if(command.error){error.textContent=command.error;return}
      if(!window.AIWalasIntroduction){error.textContent='Template perkenalan belum siap. Muat ulang halaman.';return}
      var matches=AIWalasIntroduction.resolve(command.target);
      if(command.number){send(matches.length===1?matches[0]:{name:'',phone:command.target,salutation:'Bapak/Ibu'});return}
      if(matches.length===1){send(matches[0]);return}
      if(matches.length>1){
        var box=old||document.createElement('div');box.id='helpAnswer';box.className='alert-box';if(!old)input.closest('form').insertBefore(box,input.closest('form').querySelector('.modal-actions'));
        var label=document.createElement('p');label.textContent='Beberapa kontak cocok. Pilih penerima yang dimaksud; perkenalan akan langsung dikirim.';box.appendChild(label);
        var user=(window.currentUser||{}).id,classId=window.activeClassId;
        matches.forEach(function(contact){var choice=document.createElement('button');choice.type='button';choice.className='outline-btn help-introduction-choice';choice.style.cssText='display:block;width:100%;margin-top:8px;text-align:left;white-space:normal;overflow-wrap:anywhere';choice.textContent=contact.label+(contact.phone?' · '+contact.phone:' · Nomor belum tersedia');choice.onclick=function(){if(input.isConnected&&window.currentRole==='walas'&&(window.currentUser||{}).id===user&&window.activeClassId===classId&&input.value.trim()===question)send(contact)};box.appendChild(choice)});
        return;
      }
      error.textContent='Nama penerima belum ditemukan. Gunakan nama lengkap yang tersimpan atau ketik Kirim perkenalan ke [nomor telepon].';
    };
  };
  var help=document.getElementById('helpQuestion');if(help)help.onclick=window.helpQuestion;
  window.AIWalasIntroductionCommands={parse:parse};
})();
