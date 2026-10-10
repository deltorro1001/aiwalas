(function(){
  window.sendWhatsAppGateway=function(payload){
    if(typeof window.authRequest!=='function')return Promise.reject(new Error('Sesi AIWalas belum siap.'));return window.authRequest('api/whatsapp/send',{method:'POST',body:JSON.stringify(payload)});
  };
})();