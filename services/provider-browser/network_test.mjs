import assert from 'node:assert/strict';
import http from 'node:http';
import net from 'node:net';
import {validURL,restrictedProxy} from './engine.mjs';
let checks=0;
const check=(value,label)=>{assert.ok(value,label);checks++;};
for(const value of ['http://provider.example.invalid:2052/login','http://provider.example.invalid:8880','https://provider.example.invalid:8443','https://provider.example.invalid:65535'])check(validURL(value).href.startsWith(value),'Custom HTTP/HTTPS port accepted');
for(const value of ['ftp://provider.example.invalid','http://provider.example.invalid:0','http://provider.example.invalid:65536','http://user:pass@provider.example.invalid:2052','http://127.0.0.1:2052','http://[::1]:2052','http://localhost:2052','http://provider.example.invalid\\@localhost','http://provider.example.invalid/#']){
 assert.throws(()=>validURL(value));checks++;
}
for(const value of ['http://other.example.invalid:2052/login','http://provider.example.invalid:2053/login','http://provider.example.invalid/login','https://provider.example.invalid:2052/login']){
 assert.throws(()=>validURL(value,'http://provider.example.invalid:2052'));checks++;
}
check(validURL('http://provider.example.invalid:80').origin==='http://provider.example.invalid','Default port canonicalized');
let fixtureRequests=0,lastOptions,lastBody,lastHost;
const fixture=http.createServer(async(req,res)=>{
 fixtureRequests++;lastBody='';for await(const chunk of req)lastBody+=chunk;lastHost=req.headers.host;
 res.writeHead(200,{'Content-Type':'text/plain','Set-Cookie':'fixture=ok; Path=/'});res.end('fixture response');
});
await new Promise(resolve=>fixture.listen(0,'127.0.0.1',resolve));
const send=(proxy,path,{method='GET',body='',host='provider.example.invalid:2052'}={})=>new Promise((resolve,reject)=>{
 const u=new URL(proxy.url),req=http.request({hostname:u.hostname,port:u.port,path,method,headers:{Host:host,'Content-Length':Buffer.byteLength(body),'Content-Type':'application/x-www-form-urlencoded'}},res=>{
  let text='';res.on('data',chunk=>text+=chunk);res.on('end',()=>resolve({status:res.statusCode,text,headers:res.headers}));
 });req.on('error',reject);req.end(body);
});
let dnsCalls=0,privateDNS=false;
const proxy=await restrictedProxy('http://provider.example.invalid:2052',{
 lookup:async()=>{dnsCalls++;return [{address:privateDNS?'127.0.0.1':'1.1.1.1',family:4}];},
 request:(options,callback)=>{lastOptions=options;return http.request({...options,hostname:'127.0.0.1',port:fixture.address().port},callback);}
});
try{
 let response=await send(proxy,'http://provider.example.invalid:2052/login?next=lines');
 check(response.status===200&&response.text==='fixture response','HTTP proxy forwards response');
 check(lastOptions.hostname==='1.1.1.1'&&lastOptions.port===2052&&lastOptions.path==='/login?next=lines','HTTP transport pins validated IP and uses configured port');
 check(lastHost==='provider.example.invalid:2052'&&response.headers['set-cookie'][0].startsWith('fixture='),'Provider Host and session cookies preserved');
 const body='username=fixture&password=test-only';
 response=await send(proxy,'http://provider.example.invalid:2052/login',{method:'POST',body});
 check(response.status===200&&lastBody===body,'Login POST reaches configured HTTP endpoint exactly once');
 const before=fixtureRequests;
 for(const path of ['http://provider.example.invalid:2053/login','https://provider.example.invalid:2052/login','http://other.example.invalid:2052/login','http://127.0.0.1:2052/login'])check((await send(proxy,path)).status===403,'Proxy rejects another host/protocol/port or private URL');
 check((await send(proxy,'http://provider.example.invalid:2052/login',{host:'other.example.invalid:2052'})).status===403,'Mismatched Host rejected');
 privateDNS=true;
 check((await send(proxy,'http://provider.example.invalid:2052/login')).status===403,'DNS rebinding to private address blocked');
 check(fixtureRequests===before&&dnsCalls===3,'Denied requests never reach upstream; DNS checked on each accepted destination');
}finally{await proxy.close();await new Promise(resolve=>fixture.close(resolve));}
const echo=net.createServer(socket=>socket.pipe(socket));
await new Promise(resolve=>echo.listen(0,'127.0.0.1',resolve));
let tunnelOptions;
const secureProxy=await restrictedProxy('https://provider.example.invalid:8443',{
 lookup:async()=>[{address:'1.1.1.1',family:4}],
 connect:options=>{tunnelOptions=options;return net.connect({host:'127.0.0.1',port:echo.address().port});}
});
const tunnel=path=>new Promise((resolve,reject)=>{
 const u=new URL(secureProxy.url),req=http.request({hostname:u.hostname,port:u.port,method:'CONNECT',path});
 req.on('connect',(res,socket)=>{socket.destroy();resolve(res.statusCode);});req.on('error',reject);req.end();
});
try{
 check(await tunnel('provider.example.invalid:8443')===200&&tunnelOptions.host==='1.1.1.1'&&tunnelOptions.port===8443,'HTTPS tunnel pins public IP on custom port');
 check(await tunnel('provider.example.invalid:443')===403,'Tunnel to another port rejected');
 check(await tunnel('127.0.0.1:8443')===403,'Private tunnel rejected');
 check((await send(secureProxy,'http://provider.example.invalid:8443/login',{host:'provider.example.invalid:8443'})).status===403,'HTTPS proxy rejects plaintext forwarding');
}finally{await secureProxy.close();await new Promise(resolve=>echo.close(resolve));}
const mixed=await restrictedProxy('http://provider.example.invalid:2052',{lookup:async()=>[{address:'1.1.1.1',family:4},{address:'10.0.0.1',family:4}]});
try{check((await send(mixed,'http://provider.example.invalid:2052/login')).status===403,'Mixed public/private DNS records rejected');}finally{await mixed.close();}
console.log(`PASS: ${checks} HTTP/HTTPS/custom-port/proxy/DNS assertions`);
