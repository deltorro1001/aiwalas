(function(){
  'use strict';
  function normalize(value){return String(value||'').normalize('NFKD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^\p{L}\p{N}]+/gu,' ').trim().replace(/\s+/g,' ')}
  function target(question){
    var q=normalize(question).replace(/\bdi mana\b/g,'dimana').replace(/^(?:tolong|mohon) /,'');
    var match=q.match(/^(?:tampilkan|lihat|tunjukkan) alamat(?: rumah)?(?: (.*))?$/)||q.match(/^dimana alamat(?: rumah)?(?: (.*))?$/)||q.match(/^(.*?) tinggal dimana$/);
    if(!match)return null;
    return (match[1]||'').replace(/^(?:siswa|ananda) /,'').trim();
  }
  function answer(question){
    var name=target(question);if(name===null)return null;
    if(typeof isWalasRole!=='function'||!isWalasRole())return 'Alamat siswa hanya dapat diakses oleh wali kelas.';
    if(!name)return 'Tuliskan nama panggilan atau nama lengkap siswa. Contoh: Tampilkan alamat Khaira.';
    var rows=(window.students||[]).map(function(student){
      var detail=(window.studentDetails||{})[student[1]]||{};
      return {student:student,detail:detail,names:[normalize(student[0]),normalize(detail['Nama Panggilan']||detail.nama_panggilan)]};
    });
    var exact=rows.filter(function(row){return row.names.indexOf(name)>=0});
    var found=exact.length?exact:rows.filter(function(row){return row.names.some(function(value){return (' '+value+' ').indexOf(' '+name+' ')>=0})});
    if(window.AIWalasStudentNames){var matched=window.AIWalasStudentNames.resolve(name,window.students||[]);if(matched.length)found=rows.filter(function(row){return matched.indexOf(row.student)>=0})}
    if(!found.length)return 'Nama siswa "'+name+'" tidak ditemukan pada kelas terpilih. Periksa nama atau gunakan nama lengkap siswa.';
    if(found.length>1)return 'Nama "'+name+'" cocok dengan lebih dari satu siswa:\n'+found.map(function(row,index){return (index+1)+'. '+row.student[0]+' (NIS '+row.student[1]+')'}).join('\n')+'\nTuliskan nama yang lebih lengkap agar alamat yang ditampilkan tepat.';
    var row=found[0],address='';
    ['Alamat Lengkap','alamat_lengkap'].some(function(key){
      var value=String(row.detail[key]||'').trim();
      if(!value||/^(?:[-–—]|belum diisi|tidak ada|null)$/i.test(value))return false;
      address=value;return true;
    });
    var identity=String(row.student[0])+' (NIS '+String(row.student[1])+')';
    return address?'Alamat '+identity+':\n'+address:'Alamat '+identity+' belum diisi pada data siswa.';
  }
  window.AIWalasAddressQuestions={answer:answer,target:target};
})();
