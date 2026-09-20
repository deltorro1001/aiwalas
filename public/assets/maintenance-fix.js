(function(){
function ensure(){
  if(document.querySelector('[data-page=maintenance]'))return;
  var host=document.querySelector('.sidebar-bottom');
  if(!host)return;
  var button=document.createElement('button');
  button.className='nav-item';
  button.dataset.page='maintenance';
  button.innerHTML='<span>M</span> Maintenance';
  button.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();window.page='maintenance';if(typeof window.render==='function')window.render()});
  host.insertBefore(button,host.firstChild);
}
ensure();
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ensure);
setTimeout(ensure,500);
setTimeout(ensure,1500);
setInterval(ensure,3000);
})();
