(function(){
  'use strict';
  var stop=new Set('sakit izin hadir alpha alpa libur menunggu keterangan siswa siswi kelas anak ananda nama yang dan atau hari bulan tahun'.split(' '));
  function normalize(value){return String(value||'').normalize('NFKD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^\p{L}\p{N}]+/gu,' ').trim().replace(/\s+/g,' ')}
  function name(student){return Array.isArray(student)?student[0]:student.nama||student.nama_lengkap||''}
  function nis(student){return String(Array.isArray(student)?student[1]:student.nis||'')}
  function aliases(student){return Array.from(new Set((student.aliases||(window.studentAliases||{})[nis(student)]||[]).map(normalize).filter(Boolean)))}
  function terms(student){
    var full=normalize(name(student)),tokens=full.split(' '),result=[{text:normalize(nis(student)),rank:4},{text:full,rank:3}];
    aliases(student).forEach(function(text){result.push({text:text,rank:2})});
    for(var length=tokens.length-1;length>=1;length--)for(var start=0;start+length<=tokens.length;start++){
      var text=tokens.slice(start,start+length).join(' ');
      if(text.length>=3&&!stop.has(text))result.push({text:text,rank:1});
    }
    return result.filter(function(item){return item.text});
  }
  function resolve(target,students){
    var key=normalize(target),matches=[];if(!key)return [];
    students.forEach(function(student){terms(student).forEach(function(term){if(term.text===key)matches.push({student:student,rank:term.rank})})});
    var rank=Math.max.apply(null,matches.map(function(item){return item.rank}));
    return Array.from(new Set(matches.filter(function(item){return item.rank===rank}).map(function(item){return item.student})));
  }
  function inQuestion(question,students){
    var needle=' '+normalize(question)+' ',matches=[];
    students.forEach(function(student){
      terms(student).forEach(function(term){
        var from=0,index;
        while((index=needle.indexOf(' '+term.text+' ',from))>=0){
          matches.push({student:student,phrase:term.text,rank:term.rank,start:index+1,end:index+1+term.text.length});from=index+1;
        }
      });
    });
    matches=matches.filter(function(item){return !matches.some(function(other){
      var overlaps=item.start<other.end&&other.start<item.end;
      return overlaps&&((other.end-other.start>item.end-item.start)||(other.end-other.start===item.end-item.start&&other.rank>item.rank));
    })});
    matches=matches.filter(function(item,index){return !matches.slice(0,index).some(function(other){return other.student===item.student&&other.start===item.start&&other.end===item.end})});
    return {rows:Array.from(new Set(matches.map(function(item){return item.student}))),matches:matches,ambiguous:matches.some(function(item){return matches.some(function(other){return other.student!==item.student&&other.start===item.start&&other.end===item.end})})};
  }
  function expand(question,selection){
    var phrases=Array.from(new Set(selection.matches.map(function(item){return item.phrase}))).sort(function(a,b){return b.length-a.length});
    if(!phrases.length)return String(question||'');
    var patterns=phrases.map(function(phrase){return phrase.split(' ').map(function(token){return token.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')}).join(String.raw`[\s\W_]+`)});
    var regex=new RegExp(String.raw`(^|[^\p{L}\p{N}])(`+patterns.join('|')+String.raw`)(?=$|[^\p{L}\p{N}])`,'giu');
    return String(question||'').replace(regex,function(all,before,phrase){
      var match=selection.matches.find(function(item){return item.phrase===normalize(phrase)});
      return match?before+name(match.student):all;
    });
  }

  window.AIWalasStudentNames={normalize:normalize,aliases:aliases,resolve:resolve,inQuestion:inQuestion,expand:expand};
})();
