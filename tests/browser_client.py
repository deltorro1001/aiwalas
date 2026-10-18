"""Minimal localhost Chrome DevTools client for optional development UI checks."""
import socket,struct,json,urllib.request,urllib.parse,base64,os,time
class CDP:
 def __init__(self):
  tabs=json.load(urllib.request.urlopen('http://localhost:9222/json'))
  u=urllib.parse.urlparse(next(t for t in tabs if t['type']=='page')['webSocketDebuggerUrl'])
  self.s=socket.create_connection((u.hostname,u.port));self.s.settimeout(20);self.i=0;self.events=[]
  key=base64.b64encode(os.urandom(16)).decode();self.s.sendall(f'GET {u.path} HTTP/1.1\r\nHost: localhost:9222\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {key}\r\nSec-WebSocket-Version: 13\r\nOrigin: http://localhost:9222\r\n\r\n'.encode())
  h=b''
  while not h.endswith(b'\r\n\r\n'):h+=self.s.recv(1)
  assert b'101 ' in h,h
 def read(self,n):
  result=b''
  while len(result)<n:
   chunk=self.s.recv(n-len(result))
   if not chunk:raise ConnectionError('Chrome connection closed')
   result+=chunk
  return result
 def call(self,method,params={}):
  self.i+=1;data=json.dumps({'id':self.i,'method':method,'params':params}).encode();mask=os.urandom(4);n=len(data)
  header=bytes([129,128+n]) if n<126 else (bytes([129,254])+struct.pack('!H',n) if n<65536 else bytes([129,255])+struct.pack('!Q',n))
  self.s.sendall(header+mask+bytes(b^mask[i%4] for i,b in enumerate(data)))
  while True:
   b=self.read(2);n=b[1]&127
   if n==126:n=struct.unpack('!H',self.read(2))[0]
   elif n==127:n=struct.unpack('!Q',self.read(8))[0]
   m=self.read(4) if b[1]&128 else None
   payload=self.read(n)
   if m:payload=bytes(v^m[i%4] for i,v in enumerate(payload))
   if b[0]&15!=1:continue
   message=json.loads(payload)
   if message.get('id')==self.i:
    assert 'error' not in message,message
    return message.get('result',{})
   self.events.append(message)
 def js(self,expression):
  r=self.call('Runtime.evaluate',{'expression':expression,'returnByValue':True,'awaitPromise':True})
  assert 'exceptionDetails' not in r,r
  return r.get('result',{}).get('value')
 def wait(self,condition):
  for i in range(80):
   if self.js(condition):return
   time.sleep(.1)
  raise AssertionError('Timed out: '+condition)
