(function(){
 var old=window.render,stream=null,raf=0,stopped=false;
 function stop(){stopped=true;if(stream){stream.getTracks().forEach(function(t){t.stop()});stream=null}if(raf)cancelAnimationFrame(raf)}
 function valid(raw){var p=String(raw||'').split('|');return p.length===4&&p[0]==='AIWALAS'&&p[1]==='ATTENDANCE'?p:null}
 function submit(raw,p,hint){stop();hint.textContent='Menyimpan absensi...';authRequest('api/absensi',{method:'POST',body:JSON.stringify({kelas_id:Number(p[2]),qr_code:raw,waktu_scan:new Date().toTimeString().slice(0,8),status:'Hadir'})}).then(function(){closeModal();toast('Absensi berhasil dicatat.');old()}).catch(function(e){hint.textContent=e.message;toast(e.message)})}
 function scan(){
  if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){toast('Browser ini tidak mengizinkan akses kamera. Gunakan HTTPS dan izinkan kamera.');return}
  modal('Scan QR Absensi','Arahkan kamera ke QR absensi dari wali kelas.','<video id="studentQrVideo" autoplay playsinline muted style="width:100%;max-height:340px;border-radius:8px;background:#111"></video><canvas id="studentQrCanvas" style="display:none"></canvas><p id="studentQrHint" style="font-size:11px;color:var(--muted);margin-top:10px">Meminta izin kamera...</p>');
  var v=document.getElementById('studentQrVideo'),canvas=document.getElementById('studentQrCanvas'),hint=document.getElementById('studentQrHint'),ctx=canvas.getContext('2d');
  stopped=false;
  navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false}).then(function(media){
   stream=media;v.srcObject=media;v.play().catch(function(){});
   var detector=window.BarcodeDetector?new BarcodeDetector({formats:['qr_code']}):null;
   function loop(){
    if(stopped||!stream)return;
    if(detector){detector.detect(v).then(function(c){if(c.length){var raw=c[0].rawValue,p=valid(raw);if(p)return submit(raw,p,hint)}raf=requestAnimationFrame(loop)}).catch(function(){raf=requestAnimationFrame(loop)})}
    else if(window.jsQR&&v.readyState>=2&&v.videoWidth){canvas.width=v.videoWidth;canvas.height=v.videoHeight;ctx.drawImage(v,0,0,canvas.width,canvas.height);var image=ctx.getImageData(0,0,canvas.width,canvas.height),code=jsQR(image.data,image.width,image.height);if(code){var raw=code.data,p=valid(raw);if(p)return submit(raw,p,hint)}raf=requestAnimationFrame(loop)}
    else {hint.textContent='Memuat pemindai QR...';raf=requestAnimationFrame(loop)}
   } loop();
  }).catch(function(){hint.textContent='Kamera tidak dapat diakses. Izinkan kamera pada browser dan gunakan HTTPS.'});
 }
 function studentAttendance(){if(window.currentRole!=='student'||window.page!=='attendance')return false;var root=document.getElementById('pageContent');if(!root)return true;root.innerHTML='<div class="page"><div class="page-heading"><div><div class="eyebrow">Absensi siswa</div><h1>Halo, '+escapeHtml((currentUser&&currentUser.name)||'Siswa')+'</h1><p>NIS '+escapeHtml((currentUser&&currentUser.nis)||'')+' · Scan QR kelas untuk mencatat kehadiran.</p></div></div><section class="panel" style="max-width:620px;margin:auto;text-align:center"><div class="stat-icon teal-bg" style="margin:0 auto 16px;width:52px;height:52px;font-size:25px"?</div><h2 style="font-size:18px;margin-bottom:8px">Scan QR untuk mengisi absensi</h2><p style="font-size:12px;color:var(--muted);line-height:1.6">Fitur ini mendukung iPhone, Android, dan browser modern. Izinkan akses kamera saat diminta.</p><button class="primary-btn" id="studentQrButton" style="margin-top:20px;width:100%;max-width:320px">Buka scanner QR</button><div class="alert-box" style="margin-top:18px;text-align:left"><strong>Aturan absensi hari ini</strong>05.3006.30 Hadir · 06.3007.00 Terlambat · Setelah 07.00 menunggu verifikasi wali kelas.</div></section></div>';document.getElementById('studentQrButton').onclick=scan;return true}
 window.render=function(){if(studentAttendance())return;return old.apply(this,arguments)}
})();