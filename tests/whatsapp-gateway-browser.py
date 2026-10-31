"""Gateway UI regression with mocked HTTP in a blank local Chrome tab (port 9222)."""
import json
from pathlib import Path
from browser_client import CDP

c = CDP()
c.call('Page.navigate', {'url': 'about:blank'})
c.wait("document.readyState === 'complete'")
c.js("""document.body.innerHTML='<div id="breadcrumbCurrent"></div><div id="pageContent"></div>';
window.render=function(){document.getElementById('pageContent').textContent='other page'};
window.head=function(){return '<h1>WhatsApp Gateway</h1>'};window.toast=function(){};
window.currentRole='walas';window.currentUser={id:1};window.page='whatsappGateway';
window.roleAccess={walas:[],asisten:[]};window.requests=[];window.polls=[];
window.setTimeout=function(fn){polls.push(fn);return polls.length};window.clearTimeout=function(){};
window.config={session_id:'school-session',status:'CONNECTED',phone:'6281234567890',has_api_key:true,api_base_url:'https://gateway.example.test/api/v1'};
window.remote={status:'CONNECTED'};
window.authRequest=function(path){requests.push(path);return Promise.resolve({ok:true,data:{config:config,remote:remote}})};
""")
c.js(Path(__file__).resolve().parents[1].joinpath('public/assets/whatsapp-gateway.js').read_text())
c.js('render()')
assert c.js("document.body.textContent.includes('wa.sims.id') && document.body.textContent.includes('WhatsApp terhubung')")
assert c.js("Array.from(document.querySelectorAll('input[readonly]')).some(n=>n.value==='school-session')")
assert c.js("requests.every(p=>p==='api/whatsapp/status') && !document.getElementById('waPair') && !document.getElementById('waTest').disabled")
assert c.js("!roleAccess.asisten.includes('whatsappGateway') && roleAccess.walas.includes('whatsappGateway')")

# Pair only through the mock: the QR is a PNG, not a string to encode a second time.
c.js("config.status='DISCONNECTED';remote={status:'DISCONNECTED'};render()")
c.js("remote={status:'NEED_QR',qr:'data:image/png;base64,aGVsbG8='};document.getElementById('waPair').click()")
assert c.js("document.querySelector('#waQr img').src.startsWith('data:image/png;base64,')")
c.js("remote={status:'NEED_QR'};polls.pop()()")
assert c.js("!!document.querySelector('#waQr img')"), 'Polling discarded QR'
c.js("config.status='CONNECTED';remote={status:'CONNECTED'};render()")
assert c.js("!document.getElementById('waQr')"), 'Connected device still shows QR'

# Delayed status responses must not replace another page or another user's page.
c.js("window.authRequest=function(){return new Promise(r=>window.finish=r)};render();page='dashboard';render();finish({data:{config:config,remote:remote}})")
assert c.js("document.getElementById('pageContent').textContent==='other page'")
c.js("page='whatsappGateway';render();currentUser={id:2};document.getElementById('pageContent').textContent='new user';finish({data:{config:config,remote:remote}})")
assert c.js("document.getElementById('pageContent').textContent==='new user'")
print('PASS: Shared session settings, role access, PNG pairing QR, polling retention, connection transition and stale response guards. All HTTP mocked.')
