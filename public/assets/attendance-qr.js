(function(){
var recs=[],loaded='',stream=null,raf=null;
function date(){var d=new Date();return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}
function time(){var d=new Date();return [d.getHours(),d.getMinutes(),d.getSeconds()].map(function(x){return String(x).padStart(2,'0')}).join(':')}
function esc(v){return escapeHtml(v==null?'':String(v))}
function get(id){return recs.find(function(x){return Number(x.siswa_id)===Number(id)})}
function load(){if(page!=='attendance'||currentRole==='student'||!activeClassId)return;var k=activeClassId+'|'+date();if(loaded===k)return;loaded=k;authRequest('api/absensi?kelas_id='+activeClassId+'&tanggal='+date()).then(function(x){recs=x.data||[];draw()}).catch(function(){})}
function draw(){if(page!=='attendance'||currentRole==='student')return;var root=document.getElementById('pageContent'),panel=root&&root.querySelector('.table-panel');if(!panel)return;var acts=root.querySelector('.attendance-action-buttons');if(acts&&!document.getElementById('makeAttendanceQr')){var q=document.createElement('button');q.id='makeAttendanceQr';q.className='outline-btn';q.textContent='Buat QR Siswa';acts.insertBefore(q,acts.firstChild);q.onclick=makeQr}var sc=root.querySelector('#scanQr');if(sc){sc.textContent='Scanner QR Kamera';sc.onclick=scan}var body=panel.querySelector('tbody');if(body)body.querySelectorAll('tr').forEach(function(row,i){var s=students[i],r=s&&get(s[7]),cell=row.children[1];if(cell)cell.textContent=r&&r.waktu_scan?String(r.waktu_scan).slice(0,5):'-'});load()}
