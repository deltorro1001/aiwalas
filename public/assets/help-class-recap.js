(function(){
  var inFlight=new Set(),months=['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'];
  function parse(question){
    var head=/^\s*(?:(?:tolong|mohon)\s+)?kirim(?:kan)?\s+rekap\s+absen(?:si)?(?:\s+kelas)?\b(.*)$/i.exec(String(question||''));if(!head)return null;
    var parts=/^(.*?)\s+(?:ke|kepada)\b\s*(.*)$/i.exec(head[1]);
    if(!parts)return {error:'Gunakan: Kirim Rekap Absen Oktober 2026 ke Ibu Nani.'};
    var period=parts[1].trim().toLowerCase(),match=period.match(/^(?:bulan\s+)?([a-z]+|\d{1,2})\s+(?:tahun\s+)?(\d{4})$/),month,year;
    if(!period){
      var selected=window.AIWalasClassRecap&&window.AIWalasClassRecap.period();
      var today=window.AIWalasCalendar?AIWalasCalendar.today():new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
      month=selected?selected.bulan:Number(today.slice(5,7));year=selected?selected.tahun:Number(today.slice(0,4));
    }else if(match){month=/^\d+$/.test(match[1])?Number(match[1]):months.indexOf(match[1])+1;year=Number(match[2]);}
    if(!month||month<1||month>12||!year||year<2026||year>9998)return {error:'Pilih bulan dan tahun yang valid mulai 2026. Contoh: Kirim Rekap Absen Kelas Oktober 2026 ke Ibu Nani.'};
    var target=parts[2].trim().replace(/[.!?]+$/,'').trim().replace(/^(?:nomor(?:\s+(?:telepon|whatsapp|wa|hp))?|no\.?\s*(?:telepon|whatsapp|wa|hp)?)\s*:?\s*(?=[+\d])/i,'');
    if(!target)return {error:'Isi nama atau nomor tujuan setelah kata ke.'};
    if(/^[+\d][\d\s().-]*$/.test(target)){
      var number=window.AIWalasIntroduction&&AIWalasIntroduction.normalizePhone(target);
      return number?{month:month,year:year,target:number,number:true}:{error:'Nomor WhatsApp tujuan tidak valid. Gunakan format 08… atau 628….'};
    }
    if(target.length>120)return {error:'Nama penerima maksimal 120 karakter.'};
    return {month:month,year:year,target:target,number:false};
  }
  function contacts(target){
    var prefix=/^(guru|siswa|orang\s*tua|wali(?:\s+murid)?)\s+(.+)$/i.exec(target);
    if(!prefix)return AIWalasIntroduction.resolve(target);
    if(/orang|wali/i.test(prefix[1]))return AIWalasIntroduction.resolve('Orangtua '+prefix[2]);
    var label=prefix[1].toLowerCase()==='guru'?'Guru — ':'Siswa — ';
    return AIWalasIntroduction.resolve(prefix[2]).filter(function(contact){return contact.label.indexOf(label)===0});
  }
  var previous=window.helpQuestion;
  window.helpQuestion=function(){
    previous.apply(this,arguments);
    var input=document.getElementById('helpQuestionText'),button=document.getElementById('sendHelpQuestion');if(!input||!button)return;
    var fallback=button.onclick,pending=false;
    if(window.currentRole==='walas'){
      var hint=document.createElement('p');hint.id='helpClassRecapHint';hint.className='stat-note';hint.textContent='PDF rekap: “Kirim Rekap Absen Oktober 2026 ke Ibu Nani” atau “Kirimkan Rekap Absen Kelas November 2026 ke orangtua Timoti”. Nomor langsung juga dapat dipakai. Jika periode tidak disebutkan, mengikuti pilihan Rekap Absen atau bulan berjalan. PDF langsung dikirim melalui gateway.';input.closest('form').insertBefore(hint,input.closest('form').querySelector('.modal-actions'));
    }
    async function send(contact,command){
      var error=document.getElementById('helpError'),classId=Number(window.activeClassId),owner=(window.currentUser||{}).id,phone=AIWalasIntroduction.normalizePhone(contact.phone);
      if(!phone){error.textContent='Nomor penerima belum tersedia. Gunakan nama lengkap lain atau nomor telepon langsung.';return}
      if(!Number.isInteger(classId)||classId<1){error.textContent='Pilih kelas aktif sebelum mengirim rekap.';return}
      var key=[owner,classId,[command.year,command.month].join('-'),phone].join(':');if(pending||inFlight.has(key)){error.textContent='Rekap periode tersebut sedang diproses untuk nomor ini. Tunggu hasilnya.';return}
      var form=input.closest('form');
      function active(){var backdrop=document.getElementById('modalBackdrop');return form.isConnected&&(!backdrop||backdrop.classList.contains('show'))&&window.currentRole==='walas'&&(window.currentUser||{}).id===owner&&Number(window.activeClassId)===classId}
      var box=document.getElementById('helpAnswer');if(!box){box=document.createElement('div');box.id='helpAnswer';box.className='alert-box';form.insertBefore(box,form.querySelector('.modal-actions'))}
      var label=months[command.month-1]+' '+command.year,recipient=contact.name?((contact.salutation?contact.salutation+' ':'')+contact.name):phone;
      pending=true;inFlight.add(key);button.disabled=true;input.disabled=true;button.textContent='Mengirim rekap…';error.textContent='';box.textContent='Menyiapkan PDF rekap '+label+'…';
      try{
        if(!active())throw new Error('Pengiriman dihentikan karena dialog, kelas, atau akun berubah.');
        var result=await authRequest('api/absensi/rekap-kelas/kirim-pdf',{method:'POST',body:JSON.stringify({kelas_id:classId,tahun:command.year,bulan:command.month,nomor_tujuan:phone,nama_penerima:recipient})});
        if(!result||result.ok!==true||result.status!=='sent')throw new Error(result&&result.message||'Gateway belum mengonfirmasi pengiriman.');
        if(active()){box.textContent='PDF rekap '+label+' '+(result.already?'sudah pernah diterima gateway untuk ':'terkirim melalui gateway ke ')+(contact.name||'nomor tujuan')+' ('+phone+'). '+(result.already?'Tidak dikirim ulang. ':'')+'Hasil tercatat di menu Komunikasi.';input.value='';toast(result.already?'Rekap sudah pernah terkirim.':'Rekap terkirim.')}
      }catch(reason){if(active()){box.textContent='Rekap belum berhasil dikonfirmasi terkirim.';error.textContent=(reason.message||'Pengiriman PDF rekap gagal.')+' Periksa Komunikasi sebelum mengirim ulang.';}}
      finally{pending=false;inFlight.delete(key);if(form.isConnected){button.disabled=false;input.disabled=false;button.textContent='Kirim'}}
    }
    button.onclick=function(){
      if(pending)return;
      var question=input.value.trim(),command=parse(question);if(!command)return fallback.apply(this,arguments);
      var error=document.getElementById('helpError'),old=document.getElementById('helpAnswer');error.textContent='';if(old)old.textContent='';
      if(window.currentRole!=='walas'){error.textContent='Perintah kirim rekap hanya tersedia untuk wali kelas.';return}
      if(command.error){error.textContent=command.error;return}
      if(!window.AIWalasIntroduction){error.textContent='Data kontak belum siap. Muat ulang halaman.';return}
      var matches=contacts(command.target);
      if(command.number){send(matches.length===1?matches[0]:{name:'',phone:command.target},command);return}
      if(matches.length===1){send(matches[0],command);return}
      if(!matches.length){error.textContent='Nama penerima belum ditemukan. Gunakan nama lengkap yang tersimpan atau nomor telepon tujuan.';return}
      var box=old||document.createElement('div');box.id='helpAnswer';box.className='alert-box';if(!old)input.closest('form').insertBefore(box,input.closest('form').querySelector('.modal-actions'));
      var title=document.createElement('p');title.textContent='Beberapa kontak cocok. Pilih penerima yang dimaksud; PDF rekap akan langsung dikirim.';box.appendChild(title);
      var choices=document.createElement('div');choices.style.cssText='max-height:230px;overflow:auto';box.appendChild(choices);var owner=(window.currentUser||{}).id,classId=window.activeClassId;
      matches.forEach(function(contact){var choice=document.createElement('button');choice.type='button';choice.className='outline-btn help-class-recap-choice';choice.style.cssText='display:block;width:100%;margin-top:8px;text-align:left;white-space:normal;overflow-wrap:anywhere';choice.textContent=contact.label+(contact.phone?' · '+contact.phone:' · Nomor belum tersedia');choice.onclick=function(){if(input.isConnected&&window.currentRole==='walas'&&(window.currentUser||{}).id===owner&&window.activeClassId===classId&&input.value.trim()===question)send(contact,command)};choices.appendChild(choice)});
    };
  };
  var help=document.getElementById('helpQuestion');if(help)help.onclick=window.helpQuestion;
  window.AIWalasClassRecapCommands={parse:parse};
})();
