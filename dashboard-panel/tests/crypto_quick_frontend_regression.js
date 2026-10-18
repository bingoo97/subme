'use strict';
const fs = require('fs'), vm = require('vm'), assert = require('assert');
let count = 0, now = 1000, observe, mediaChange;
function ck(ok, message) { assert.ok(ok, message); count++; }
function element(dataset = {}) { return { dataset, hidden: false, disabled: false, innerHTML: '', attrs: {}, listeners: {}, getClientRects: () => [1], setAttribute(k,v) { this.attrs[k]=v; }, addEventListener(k,fn) { this.listeners[k]=fn; }, replaceChildren(n) { this.innerHTML=n.textContent; }, focus() {}, click() { this.listeners.click?.({}); } }; }
const panel = element({ url:'/admin/crypto_quick.php',loading:'loading',error:'error' });panel.hidden = true;
const list = element(), refresh = element(), close = element(), toggle = element(), hint = element();
const filters = ['new','expired','cancelled','payout'].map(f=>element({cryptoQuickFilter:f}));
const hintTitle=element(),hintText=element(),hintIcon=element(),settingsLink=element();hint.dataset.hints=JSON.stringify(Object.fromEntries(['new','expired','cancelled','payout'].map(f=>[f,{title:f+' title',text:f+' text',icon:'bi-'+f}])));hint.querySelector=s=>s==='[data-crypto-quick-settings-link]'?settingsLink:s==='i'?hintIcon:s==='[data-crypto-quick-hint-title]'?hintTitle:hintText;
const media = { matches:false,addEventListener(k,fn){mediaChange=fn;} };
const classes = new Set();
const document = { readyState:'complete', querySelector:()=>panel, addEventListener(){}, createElement:()=>element(), body:{classList:{toggle(k,v){v?classes.add(k):classes.delete(k);}}} };
const badge=element();
const root = { querySelector:s=>s==='[data-crypto-quick-badge]'?badge:toggle };
panel.closest=()=>root;panel.querySelector=s=>({'[data-crypto-quick-list]':list,'[data-crypto-quick-refresh]':refresh,'[data-admin-topbar-flyout-close]':close,'[data-crypto-quick-hint]':hint}[s]);panel.querySelectorAll=s=>s==='[data-crypto-quick-filter]'?filters:[];
const requests=[];const navigator={onLine:true};
vm.runInNewContext(fs.readFileSync('public_html/assets/js/admin-crypto-quick.js','utf8'),{document,navigator,window:{setInterval(){return 1;},clearInterval(){},innerWidth:390,innerHeight:740,addEventListener(){},matchMedia:()=>media},MutationObserver:class{constructor(fn){observe=fn;}observe(){}},AbortController,Map,Date:{now:()=>now},setTimeout:()=>1,clearTimeout(){},fetch:(url,options)=>new Promise((resolve,reject)=>requests.push({url,options,resolve,reject}))});
const tick = async()=>{for(let i=0;i<8;i++)await Promise.resolve();};
const reply = (i,f,html)=>requests[i].resolve({ok:true,json:async()=>({ok:true,filter:f,html})});
(async()=>{
 ck(requests.length===0,'Closed flyout sends no requests');panel.hidden=false;observe();ck(requests.length===1&&list.innerHTML==='loading','Opening lazily loads requests');
 ck(!hint.hidden&&hint.dataset.tone==='new'&&hintTitle.textContent==='new title','New filter has a matching explanatory card');
 ck(!settingsLink.hidden,'Auto-accept settings link is shown only in New');
 reply(0,'new','new rows');await tick();ck(list.innerHTML==='new rows'&&!refresh.disabled,'Successful result renders and unlocks refresh');
 filters[1].click();ck(hint.dataset.tone==='expired'&&hintText.textContent==='expired text','Expired filter updates explanation immediately');ck(settingsLink.hidden,'Other categories hide the auto-accept settings link');ck(requests.length===2,'Uncached category loads once');filters[0].click();ck(requests[1].options.signal.aborted,'Changing back aborts superseded request');ck(list.innerHTML==='new rows'&&requests.length===2,'Fresh cached category does not refetch');
 reply(1,'expired','stale rows');await tick();ck(list.innerHTML==='new rows','Late response cannot replace current filter');
 media.matches=true;mediaChange();ck(classes.has('has-crypto-quick-open')&&panel.attrs['aria-modal']==='true','Mobile opens full-screen accessible mode');ck(requests.length===2,'Resizing never refetches data');
 refresh.click();ck(requests.length===3,'Refresh explicitly bypasses cache');panel.hidden=true;observe();ck(requests[2].options.signal.aborted&&!classes.has('has-crypto-quick-open'),'Closing aborts work and unlocks body');reply(2,'new','closed result');await tick();ck(list.innerHTML!=='closed result','Closed response is ignored');
 panel.hidden=false;observe();ck(requests.length===3&&list.innerHTML==='new rows','Reopen within TTL uses cache');
 now+=31000;filters[0].click();ck(requests.length===4,'Expired cache is refreshed');navigator.onLine=false;filters[2].click();ck(requests[3].options.signal.aborted&&!refresh.disabled&&list.attrs['aria-busy']==='false','Offline category change aborts and leaves controls usable');ck(hint.dataset.tone==='cancelled'&&hintIcon.className==='bi bi-cancelled','Cancelled filter has matching icon and tone');ck(requests.length===4&&list.innerHTML==='error','Offline changes send no requests');requests[3].reject(new Error('aborted'));await tick();
 navigator.onLine=true;refresh.click();ck(requests.length===5,'Recovery can retry without reloading page');requests[4].reject(new Error('network'));await tick();ck(list.innerHTML==='error'&&!refresh.disabled,'Network failure leaves a readable recoverable state');
 filters[3].click();reply(5,'payout','approved rows');await tick();ck(!hint.hidden&&list.innerHTML==='approved rows','Approved filter explains wallet balance limitation');
 panel.hidden=true;observe();ck(!refresh.disabled&&panel.attrs['aria-modal']==='false','Closing clears mobile dialog state');
 console.log(`PASS ${count} crypto overview cache, races, cancellation, offline and responsive-state assertions`);
})().catch(error=>{console.error(error);process.exitCode=1;});
