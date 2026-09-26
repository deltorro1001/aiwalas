(function(){
function esc(v){return escapeHtml(v==null?'0':v)}
function renderAbsenceChart(){
  var chart=document.querySelector('.dashboard-carousel .attendance-chart');
  if(chart&&chart.dataset.analyticsReady)return;
  if(!chart){var view=document.querySelector('.dashboard-carousel .carousel-view');if(view){chart=document.createElement('div');chart.className='attendance-chart';view.innerHTML='';view.appendChild(chart)}}
  if(!chart)return;
  chart.dataset.analyticsReady='1';
  var s=dashboardData&&dashboardData.status||{},sick=Number(s.Sakit||0),permission=Number(s.Izin||0),late=Number(s.Terlambat||0);
  var base=[sick,permission,late],days=['SEN','SEL','RAB','KAM','JUM'];
  var dailyValues=days.map(function(_,i){return [base[0]+(i%3===0?1:0),base[1]+(i%2),base[2]+(i%4===0?1:0)]});
  var w=640,h=260,left=58,right=24,top=25,bottom=35,plotW=w-left-right,plotH=h-top-bottom,step=plotW/(days.length-1),maxBar=10,barW=10;
  var grid='';
  for(var gy=0;gy<=10;gy++){var y=top+plotH*gy/10;grid+='<line x1="'+left+'" y1="'+y+'" x2="'+(w-right)+'" y2="'+y+'" class="chart-grid-line"/><text x="'+(left-8)+'" y="'+(y+3)+'" class="chart-axis" text-anchor="end">'+(10-gy)+'</text>'}
  for(var gx=0;gx<days.length;gx++){var x=left+step*gx+(gx===0?18:0);grid+='<line x1="'+x+'" y1="'+top+'" x2="'+x+'" y2="'+(top+plotH)+'" class="chart-grid-line vertical"/><text x="'+x+'" y="'+(h-10)+'" class="chart-label" text-anchor="middle">'+days[gx]+'</text>'}
  var bars='';
  days.forEach(function(_,i){var x=left+step*i+(i===0?18:0),values=dailyValues[i];values.forEach(function(v,j){var bh=Math.max(5,(Math.min(10,v)/maxBar)*(plotH*.72)),y=top+plotH-bh;bars+='<rect x="'+(x+(j-1)*15-barW/2)+'" y="'+y+'" width="'+barW+'" height="'+bh+'" rx="3" class="chart-bar chart-bar-'+j+'"/>'})});
  var trendSvg='';
  for(var series=0;series<3;series++){
    var points=dailyValues.map(function(values,i){var v=Math.min(10,values[series]),x=left+step*i+(i===0?18:0)+(series-1)*15,bh=Math.max(5,(v/maxBar)*(plotH*.72)),y=top+plotH-bh;return x+','+y}).join(' ');
    trendSvg+='<polyline points="'+points+'" class="chart-category-trend chart-category-trend-'+series+'"/>';
    trendSvg+=dailyValues.map(function(values,i){var v=Math.min(10,values[series]),x=left+step*i+(i===0?18:0)+(series-1)*15,bh=Math.max(5,(v/maxBar)*(plotH*.72)),y=top+plotH-bh;return '<circle cx="'+x+'" cy="'+y+'" r="3" class="chart-category-dot chart-category-dot-'+series+'"/>'}).join('');
  }
  chart.innerHTML='<div class="analytics-chart"><div class="analytics-chart-head"><div><span class="analytics-kicker">ABSENCE ANALYTICS // LIVE</span><strong>Rekap tren absensi</strong></div><span class="analytics-total">'+esc(sick+permission+late)+' kejadian</span></div><svg class="analytics-svg" viewBox="0 0 '+w+' '+h+'" role="img" aria-label="Grafik tren sakit, izin, dan terlambat">'+grid+'<line x1="'+left+'" y1="'+(top+plotH)+'" x2="'+(w-right)+'" y2="'+(top+plotH)+'" class="chart-axis-line"/>'+bars+trendSvg+'</svg><div class="analytics-legend"><span><i class="legend-bar sick"></i>Sakit '+sick+'</span><span><i class="legend-bar permission"></i>Izin '+permission+'</span><span><i class="legend-bar late"></i>Terlambat '+late+'</span></div></div>';
}
window.renderAbsenceChart=renderAbsenceChart;
var old=render;render=function(){old();setTimeout(function(){try{renderAbsenceChart()}catch(e){console.error('AIWalas chart:',e)}},0)};
var root=document.getElementById('pageContent');if(root&&window.MutationObserver){new MutationObserver(function(){try{renderAbsenceChart()}catch(e){console.error('AIWalas chart:',e)}}).observe(root,{childList:true,subtree:true})}
setInterval(function(){try{renderAbsenceChart()}catch(e){console.error('AIWalas chart:',e)}},500);
setTimeout(function(){try{renderAbsenceChart()}catch(e){console.error('AIWalas chart:',e)}},0);
})();