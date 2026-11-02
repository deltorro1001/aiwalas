(function(){
 function dateLabel(){return typeof todayLabel==='function'?todayLabel():new Date().toLocaleDateString('id-ID',{day:'numeric',month:'long',year:'numeric'})}
 function phone(v){var n=String(v||'').replace(/\D/g,'');if(n.indexOf('0')===0)n='62'+n.slice(1);return /^62\d{8,15}$/.test(n)?n:''}
 function greeting(student){
  if(!/^timothy\s+frans(?:\s|$)/i.test(String(student[0]||'').trim()))return 'Assalamualaikum';
  var parts=new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Jakarta',hour:'2-digit',hourCycle:'h23'}).formatToParts(new Date()),hour=Number((parts.find(function(part){return part.type==='hour'})||{}).value||0);
  return hour<11?'Selamat Pagi':hour<15?'Selamat Siang':hour<18?'Selamat Sore':'Selamat Malam';
 }
 function message(student,status){
  var name=student[0]||'Siswa',date=dateLabel(),text='',thanks='Terima kasih.';
  if(status==='Menunggu keterangan')text=name+' belum melakukan scan absensi hari ini ('+date+'). Mohon konfirmasi kehadirannya.';
  else if(status==='Terlambat')text=name+' tercatat terlambat pada absensi hari ini ('+date+'). Mohon menjadi perhatian.';
  else if(status==='Sakit')text='Absensi '+name+' hari ini tercatat Sakit ('+date+'). Semoga ananda lekas pulih dan dapat kembali bersekolah dalam keadaan sehat.';
  else if(status==='Izin'){text='Absensi '+name+' hari ini tercatat Izin ('+date+').';thanks='Terima kasih atas informasi yang Bapak/Ibu sampaikan.';}
  else return '';
  return greeting(student)+', Bapak/Ibu Orangtua '+name+'. '+text+' '+thanks;
 }
 function install(){if(window.currentRole!=='walas'||window.page!=='attendance'||(window.AIWalasAttendance&&!window.AIWalasAttendance.isToday()))return;var body=document.querySelector('.table-panel tbody');if(!body)return;body.querySelectorAll('tr').forEach(function(row,i){var s=(window.students||[]).find(function(student){return String(student[7])===row.dataset.attendanceStudent});if(!s||!row.children[3])return;var status=row.dataset.attendanceStatus||'Menunggu keterangan',n=phone(s[6]);if(['Menunggu keterangan','Terlambat','Sakit','Izin'].indexOf(status)<0){if(row.children[3].querySelector('.wa-parent-action'))row.children[3].textContent='—';return;}if(row.children[3].querySelector('.wa-parent-action'))return;row.children[3].innerHTML=n?'<button type="button" class="wa-parent-action" data-wa-parent="'+i+'">Kirim via Gateway</button>':'<span class="wa-parent-action disabled" aria-disabled="true">Nomor orangtua belum tersedia</span>';if(n){var action=row.children[3].querySelector('[data-wa-parent]');action.onclick=function(){if(typeof sendWhatsAppGateway!=='function'){toast('Gateway WhatsApp belum siap.');return}var text=message(s,row.dataset.attendanceStatus||'Menunggu keterangan');if(!text){install();return}sendWhatsAppGateway({kelas_id:window.activeClassId||1,nomor_tujuan:n,nama_penerima:'Orangtua '+s[0],isi_pesan:text}).then(function(){toast('Pesan WhatsApp terkirim.');action.textContent='Terkirim'}).catch(function(e){toast(e.message)})}}})}
 var oldRender=window.render;window.render=function(){var result=oldRender.apply(this,arguments);setTimeout(install,60);return result};setInterval(install,500);setTimeout(install,300);
})();