'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('public_html/assets/js/admin-customer-import.js','utf8');
let checks=0;const ck=(value)=>{assert.ok(value);checks++;};
const tick=()=>new Promise(resolve=>setImmediate(resolve));
function setup(){
 const nodes={},events={},calls=[],storage=new Map([['subme-customer-import:7','job-fixture']]);
 const node=name=>nodes[name]??=( {hidden:false,disabled:false,textContent:'',value:'',classList:{toggle(){}},addEventListener(type,fn){events[name+':'+type]=fn;}} );
 const form={dataset:{adminId:'7'},elements:{_csrf:{value:'csrf-fixture'}},querySelector(selector){return node(selector.match(/data-import-([\w-]+)/)[1]);},querySelectorAll(){return [];},addEventListener(type,fn){events['form:'+type]=fn;}};
 class Body{constructor(){this.values=new Map();}set(k,v){this.values.set(k,v);}}
 vm.runInNewContext(source,{document:{querySelector:()=>form},sessionStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v),removeItem:k=>storage.delete(k)},FormData:Body,AbortController,setTimeout,clearTimeout,window:{addEventListener(){}},fetch:(url,options)=>new Promise((resolve,reject)=>calls.push({action:options.body.values.get('action'),csrf:options.body.values.get('_csrf'),resolve(data){resolve({ok:true,json:async()=>data});},reject}))});
 return {node,events,calls,storage};
}
const summary=(done=false)=>({ok:true,total:50,processed:done?50:0,created:done?50:0,skipped:0,failed:0,queued:0,errors:[],can_start:true,done});
(async()=>{
 const app=setup();ck(!app.node('resume').hidden); // Job survives page navigation, without source/password storage.
 const work=app.events['resume:click']();ck(app.calls[0].action==='status');app.calls[0].resolve(summary());await tick();
 ck(app.calls.length===2&&app.calls[1].action==='batch');ck(app.calls[1].csrf==='csrf-fixture');
 app.events['pause:click']();ck(app.node('resume').disabled);
 await app.events['resume:click']();ck(app.calls.length===2); // No second loop while paused batch is in flight.
 app.calls[1].resolve(summary(true));await work;ck(app.storage.size===0);ck(app.node('progress-title').textContent.includes('50 / 50'));ck(!app.node('refresh').hidden);
 const uncertain=setup();const pending=uncertain.events['resume:click']();uncertain.calls[0].resolve(summary());await tick();uncertain.calls[1].reject(new Error('Network response lost'));await pending;
 ck(uncertain.calls.length===2);ck(uncertain.node('notice').textContent.includes('Nie wysyłamy partii ponownie automatycznie'));ck(!uncertain.node('resume').disabled&&uncertain.storage.size===1);
 const recovered=uncertain.events['resume:click']();ck(uncertain.calls[2].action==='status');uncertain.calls[2].resolve(summary(true));await recovered;ck(uncertain.storage.size===0);
 console.log('PASS '+checks+' import frontend checks: resume, pause race, uncertain response, no automatic replay.');
})().catch(error=>{console.error(error);process.exitCode=1;});
