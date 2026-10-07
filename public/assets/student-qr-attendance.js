(function(){
 var old=window.render,stream=null,raf=0,stopped=false;
 function stop(){stopped=true;if(stream){stream.getTracks().forEach(function(t){t.stop()});stream=null}if(raf)cancelAnimationFrame(raf)}
 function valid(raw){var p=String(raw||'').trim().split('|');return p.length===4&&p[0]==='AIWALAS'&&p[1]==='ATTENDANCE'?p:null}
 function submit(raw,p,hint){stop();hint.textContent='Menyimpan absensi...';authRequest('api/absensi',{method:'POST',body:JSON.stringify({kelas_id:Number(p[2]),qr_code:raw,waktu_scan:new Date().toTimeString().slice(0,8),status:'Hadir'})}).then(function(){closeModal();toast('Absensi berhasil dicatat.');old()}).catch(function(e){hint.textContent=e.message||'Absensi gagal disimpan.';toast(hint.textContent)})}
 function openCamera(){return navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false}).catch(function(error){if(error&&(['OverconstrainedError','NotFoundError'].indexOf(error.name)>=0))return navigator.mediaDevices.getUserMedia({video:true,audio:false});throw error})}
 function scan(){
  if(!window.isSecureContext&&!/^localhost$|^127\\./.test(location.hostname)){toast('Kamera iPhone hanya dapat digunakan melalui HTTPS.');return}
  if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){toast('Browser tidak mendukung akses kamera. Gunakan Safari/Chrome terbaru.');return}
  modal('Scan QR Absensi','Arahkan kamera belakang ke QR yang ditampilkan wali kelas.','<div class="student-camera-frame"><video id="studentQrVideo" autoplay playsinline muted></video><div class="student-camera-guide"></div></div><canvas id="studentQrCanvas" hidden></canvas><p id="studentQrHint" class="student-camera-hint">Meminta izin kamera...</p>');
  var v=document.getElementById('studentQrVideo'),canvas=document.getElementById('studentQrCanvas'),hint=document.getElementById('studentQrHint'),ctx=canvas.getContext('2d');stopped=false;
  navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false}).then(function(media){
   stream=media;v.srcObject=media;return v.play();
  }).then(function(){
   var detector=window.BarcodeDetector?new BarcodeDetector({formats:['qr_code']}):null;
   function loop(){if(stopped||!stream)return;
    if(detector){detector.detect(v).then(function(c){if(c.length){var raw=c[0].rawValue,p=valid(raw);if(p)return submit(raw,p,hint)}raf=requestAnimationFrame(loop)}).catch(function(){raf=requestAnimationFrame(loop)})}
    else if(window.jsQR&&v.readyState>=2&&v.videoWidth){canvas.width=v.videoWidth;canvas.height=v.videoHeight;ctx.drawImage(v,0,0,canvas.width,canvas.height);var code=jsQR(ctx.getImageData(0,0,canvas.width,canvas.height).data,canvas.width,canvas.height);if(code){var raw=code.data,p=valid(raw);if(p)return submit(raw,p,hint)}raf=requestAnimationFrame(loop)}
    else {hint.textContent=window.jsQR?'Menyiapkan pemindai QR...':'Library pemindai belum termuat. Periksa koneksi internet.';raf=requestAnimationFrame(loop)}
   } loop();
  }).catch(function(error){hint.textContent=error&&error.name==='NotAllowedError'?'Izin kamera ditolak. Buka pengaturan browser untuk situs AIWalas, aktifkan Kamera, lalu muat ulang halaman.':'Kamera tidak dapat diakses. Izinkan kamera dan coba lagi.';});
 }
 function studentAttendance(){if(window.currentRole!=='student'||window.page!=='attendance')return false;var root=document.getElementById('pageContent');if(!root)return true;root.innerHTML='<div class="page student-attendance-page"><div class="page-heading"><div><div class="eyebrow">Absensi siswa</div><h1>Halo, '+escapeHtml((currentUser&&currentUser.name)||'Siswa')+'</h1><p>NIS '+escapeHtml((currentUser&&currentUser.nis)||'')+' &middot; Scan QR kelas untuk mencatat kehadiran.</p></div></div><section class="panel student-attendance-card"><div class="student-attendance-icon">QR</div><h2>Scan QR untuk mengisi absensi</h2><p class="student-attendance-help">Minta wali kelas menampilkan QR Absensi, lalu tekan tombol di bawah. Izinkan kamera ketika diminta.</p><button class="primary-btn student-qr-button" id="studentQrButton">Buka scanner QR</button><div class="alert-box student-attendance-rule"><strong>Aturan absensi hari ini</strong><span>05.30-06.30 Hadir &middot; 06.30-07.00 Terlambat &middot; Setelah 07.00 menunggu verifikasi wali kelas.</span></div></section></div>';document.getElementById('studentQrButton').onclick=scan;return true}
 window.render=function(){if(studentAttendance())return;return old.apply(this,arguments)}
})();
/* Camera permission fallback: retry without facingMode constraints for older iPhones. */
(function(){
  var original=window.render;
  function cameraFallback(){
    var button=document.getElementById('studentQrButton');
    if(button)button.setAttribute('title','Pastikan izin Kamera untuk situs AIWalas diaktifkan.');
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',cameraFallback);else cameraFallback();
})();
