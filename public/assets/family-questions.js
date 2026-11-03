(function(){
  'use strict';
  function normalize(value){return String(value||'').toLowerCase().replace(/[^a-z0-9]+/g,' ').trim().replace(/\s+/g,' ').replace(/\bora+ng\s*tua(?:nya)?\b/g,'orangtua').replace(/\borang\s+tua(?:nya)?\b/g,'orangtua').replace(/\b(ayah|ibu|orangtua)nya\b/g,'$1').replace(/\bmeningal\b/g,'meninggal')}
  function deceased(value){
    var text=normalize(value);
    // Negated markers and names such as Salmah/Alma are not evidence of death.
    text=text.replace(/\b(?:tidak|belum|bukan)\s+(?:(?:pernah|sudah|telah)\s+)?(?:meninggal(?: dunia)?|wafat|almarhum(?:ah)?|almh?)\b/g,'');
    return /\b(?:almarhum(?:ah)?|almh?|meninggal|wafat)\b/.test(text);
  }
  function parentStatus(detail,parent){
    var father=parent==='ayah',keys=father?['Nama Ayah','nama_ayah','Pekerjaan ayah','pekerjaan_ayah']:['Nama Ibu','nama_ibu','Pekerjaan ibu','pekerjaan_ibu'];
    return keys.some(function(key){return deceased(detail[key])});
  }
  function intent(question){
    var q=normalize(question),death=/\b(?:meninggal|wafat|almarhum(?:ah)?|almh?|yatim|piatu)\b/.test(q);
    if(!death||!/\b(?:orangtua|ayah|ibu|yatim|piatu)\b/.test(q))return null;
    if(/\b(?:belum|tidak)\s+(?:meninggal|wafat)\b/.test(q))return 'unsupported';
    if(/\b(?:keduanya|kedua orangtua|yatim piatu)\b/.test(q)||/\bayah dan ibu\b/.test(q))return 'both';
    var father=/\bayah\b|\byatim\b/.test(q),mother=/\bibu\b|\bpiatu\b/.test(q);
    return father&&!mother?'father':mother&&!father?'mother':'any';
  }
  function answer(question){
    var type=intent(question);if(!type)return null;
    if(typeof isWalasRole!=='function'||!isWalasRole())return 'Informasi keluarga siswa hanya dapat diakses oleh wali kelas.';
    if(type==='unsupported')return 'Data tanpa penanda wafat belum dapat memastikan orangtua masih hidup. Gunakan pertanyaan tentang ayah, ibu, atau orangtua yang sudah wafat.';
    var groups={father:[],mother:[],both:[]};
    (typeof students!=='undefined'?students:[]).forEach(function(student){
      var detail=(typeof studentDetails!=='undefined'&&studentDetails[student[1]])||{},father=parentStatus(detail,'ayah'),mother=parentStatus(detail,'ibu');
      if((type==='father'&&!father)||(type==='mother'&&!mother)||(type==='both'&&!(father&&mother))||(!father&&!mother))return;
      groups[father&&mother?'both':father?'father':'mother'].push(String(student[0]||'')+' (NIS '+String(student[1]||'')+')');
    });
    var count=groups.father.length+groups.mother.length+groups.both.length;
    var title=type==='father'?'ayahnya':type==='mother'?'ibunya':type==='both'?'kedua orangtuanya':'ayah atau ibunya';
    var category=type==='father'?'yatim (ayah wafat)':type==='mother'?'piatu (ibu wafat)':type==='both'?'yatim piatu (ayah dan ibu wafat)':null;
    var lines=['Jumlah siswa yang tercatat '+(category||title+' wafat')+': '+count+' siswa.'];
    [['father','Ayah wafat; ibu belum tercatat wafat'],['mother','Ibu wafat; ayah belum tercatat wafat'],['both','Ayah dan ibu wafat (yatim piatu)']].forEach(function(group){
      var rows=groups[group[0]];if(!rows.length)return;
      lines.push('',group[1]+' — '+rows.length+' siswa:');
      rows.forEach(function(name,index){lines.push((index+1)+'. '+name)});
    });
    if(!count)lines.push('Tidak ditemukan penanda wafat pada data ayah/ibu yang tersedia untuk kelas terpilih.');
    lines.push('','Sumber: nama dan pekerjaan ayah/ibu pada data siswa di kelas terpilih. Data kosong atau tanpa penanda wafat tidak dianggap sebagai keterangan wafat maupun kepastian masih hidup.');
    return lines.join('\n');
  }
  window.AIWalasFamilyQuestions={answer:answer,intent:intent,parentStatus:parentStatus};
})();
