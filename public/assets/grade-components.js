(function(){
  var labels=window.AIWalasGradeComponentLabels||{},keys=Object.keys(labels);
  function key(value){var text=String(value==null?'':value).trim(),match=/^(UH|Tugas)\s*([1-6])$/i.exec(text);return match?(match[1].toUpperCase()==='UH'?'UH':'Tugas')+match[2]:text.toUpperCase()}
  function label(value){return labels[key(value)]||String(value)}
  function score(value){if(value==null||String(value).trim()==='')return null;var n=Number(String(value).trim().replace(',','.'));return isFinite(n)&&n>=0&&n<=100?n:null}
  function mean(values){return values.length?Math.round(values.reduce(function(a,b){return a+b},0)/values.length):null}
  function summary(row){
    var uh=keys.filter(function(c){return /^UH/.test(c)}).map(function(c){return score(row[c])}).filter(function(n){return n!==null});
    var tasks=keys.filter(function(c){return /^Tugas/.test(c)}).map(function(c){return score(row[c])}).filter(function(n){return n!==null});
    // Rerata UH includes every filled UH and Tugas score, with equal weight.
    var daily=uh.concat(tasks),average=mean(daily),ats=score(row.ATS),ass=score(row.ASS),available=daily.concat(ats===null?[]:[ats],ass===null?[]:[ass]);
    var report=available.length===1?available[0]:average!==null&&ats!==null?(ass===null?Math.round((3*average+ats)/4):Math.round((3*average+ats+2*ass)/6)):null;
    return {average:average,report:report};
  }
  function columns(values){
    var active=keys.filter(function(c){return Object.keys(values).some(function(nis){return score((values[nis]||{})[c])!==null})});
    var uh=active.filter(function(c){return /^UH/.test(c)}),tasks=active.filter(function(c){return /^Tugas/.test(c)}),out=uh.concat(tasks);
    if(uh.length+tasks.length>=2)out.push('Rerata UH');
    ['ATS','ASS'].forEach(function(c){if(active.indexOf(c)>=0)out.push(c)});
    if(Object.keys(values).some(function(nis){return summary(values[nis]||{}).report!==null}))out.push('Nilai Raport');
    return out;
  }
  window.academicComponentKeys=function(){return keys.slice()};window.academicComponentKey=key;window.academicComponentLabel=label;
  window.academicGradeScore=score;window.academicGradeSummary=summary;window.academicGradeColumns=columns;
  window.academicGradeClass=function(value){var n=score(value);return n!==null&&n<80?'grade-below-threshold':''};
})();
