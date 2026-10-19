'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert');let n=0;
const source=fs.readFileSync('public_html/assets/js/admin-crypto-quick.js','utf8');
const check=(v,m)=>{assert.ok(v,m);n++;};
function setup(counts){
 let observe,reply,url,requests=0;
 const el=(dataset={})=>({dataset,hidden:false,style:{},attrs:{},listeners:{},setAttribute(k,v){this.attrs[k]=v;},removeAttribute(k){delete this.attrs[k];},addEventListener(k,f){this.listeners[k]=f;},replaceChildren(){},getClientRects(){return [1];}});
 const panel=el({counts:JSON.stringify(counts),labels:JSON.stringify({title:'Crypto',new:'New',expired:'Expired',payout:'To withdraw',cancelled:'Cancelled'}),url:'/quick'});panel.hidden=true;panel.contains=()=>true;
 const toggle=el(),badge=el(),categoryCount=el(),hint=el({hints:'{}'}),list=el(),refresh=el(),close=el();
 hint.querySelector=()=>null;
 const filters=['new','expired','cancelled','payout'].map(k=>el({cryptoQuickFilter:k}));
 panel.closest=()=>({querySelector:s=>s==='[data-crypto-quick-badge]'?badge:toggle});
 const more=el(),menu=el();menu.hidden=true;panel.getBoundingClientRect=()=>({left:0,right:390,width:390,top:100,bottom:740});list.getBoundingClientRect=()=>({top:200,bottom:680});more.getBoundingClientRect=()=>({right:360,top:630,bottom:660});menu.getBoundingClientRect=()=>({height:96});more.focus=()=>{};more.closest=()=>({querySelector:()=>menu});
 panel.querySelector=s=>s==='[data-crypto-payment-more][aria-expanded="true"]'?(more.attrs['aria-expanded']==='true'?more:null):({'[data-crypto-quick-category-count]':categoryCount,'[data-crypto-quick-list]':list,'[data-crypto-quick-refresh]':refresh,'[data-admin-topbar-flyout-close]':close,'[data-crypto-quick-hint]':hint}[s]);panel.querySelectorAll=s=>s==='[data-crypto-quick-filter]'?filters:s==='[data-crypto-payment-more]'?[more]:s==='[data-crypto-payment-actions]'?[menu]:[];
 vm.runInNewContext(source,{document:{readyState:'complete',querySelector:()=>panel,createElement:()=>el(),addEventListener(){},body:{classList:{toggle(){}}}},window:{setInterval(){return 1;},clearInterval(){},innerWidth:390,innerHeight:740,addEventListener(){},matchMedia:()=>({matches:false,addEventListener(){}})},navigator:{onLine:true},MutationObserver:class{constructor(f){observe=f;}observe(){}},AbortController,Map,Date,JSON,setTimeout:()=>1,clearTimeout(){},FormData:class{constructor(form){this.fields=new Map(form.fields);}delete(k){this.fields.delete(k);}set(k,v){this.fields.set(k,v);}get(k){return this.fields.get(k);}},fetch:(u)=>{requests++;url=u;return new Promise(resolve=>{reply=resolve;});}});
 return {panel,badge,categoryCount,toggle,filters,more,menu,list,get requests(){return requests;},deleteLast(){const button=el();const form={action:'/payments',getAttribute:()=>'/payments',fields:[['quick_action','delete'],['_csrf','fixture']],querySelector:()=>button};return panel.listeners.submit({target:{closest:()=>form},preventDefault(){}});},open(){panel.hidden=false;observe();},close(){panel.hidden=true;observe();},reply(data){reply({ok:true,json:async()=>data});},get url(){return url;}};
}
(async()=>{
 for(const [counts,key,count] of [[{new:14,expired:3,payout:2,cancelled:1},'new',14],[{expired:3,payout:2,cancelled:1},'expired',3],[{payout:2,cancelled:1},'payout',2],[{cancelled:1},'cancelled',1],[{},'new',0]]){
  const t=setup(counts);check(t.badge.hidden===(count===0)&&t.badge.textContent===String(count)&&t.badge.dataset.tone===key,'Correct priority, count and empty visibility');
  check(t.categoryCount.textContent===String(count)&&t.categoryCount.dataset.tone===key,'Header shows the initially selected category count and colour');
  check(!t.url,'Badge uses server counts without a background request');t.open();check(t.url==='/quick?filter='+key&&t.filters.find(f=>f.dataset.cryptoQuickFilter===key).attrs['aria-pressed']==='true','Opening selects priority category');
 }
 const t=setup({new:1});t.open();t.reply({ok:true,filter:'new',html:'rows',counts:{new:0,expired:0,payout:7,cancelled:2}});for(let i=0;i<8;i++)await Promise.resolve();
 check(t.badge.dataset.tone==='payout'&&t.badge.textContent==='7','Fresh response updates badge when higher categories empty');
 check(t.categoryCount.dataset.tone==='new'&&t.categoryCount.textContent==='0','Header keeps the selected category when navbar badge priority changes');
 t.filters[3].listeners.click();check(t.categoryCount.textContent==='7'&&t.categoryCount.dataset.tone==='payout','Switching tab updates header immediately from existing counts');
 t.reply({ok:true,filter:'payout',html:'new payout rows',counts:{new:0,expired:0,payout:6,cancelled:2}});for(let i=0;i<8;i++)await Promise.resolve();
 check(t.categoryCount.textContent==='6'&&t.categoryCount.attrs['aria-label']==='To withdraw: 6','Live refresh updates selected header count and accessible description');
 check(t.toggle.attrs['aria-label']==='Crypto: To withdraw (6)','Badge count/category have accessible description');
 t.close();t.open();check(t.url==='/quick?filter=payout','Reopen follows refreshed priority');
 const m=setup({payout:1});m.open();
 m.panel.listeners.click({target:{closest:()=>m.more}});check(!m.menu.hidden&&m.more.attrs['aria-expanded']==='true','More button opens payment actions');
 check(m.menu.style.top==='528px'&&m.menu.style.left==='115px','Last-row menu opens upward within list bounds');
 m.panel.listeners.click({target:{closest:()=>m.more}});check(m.menu.hidden&&m.more.attrs['aria-expanded']==='false','Second click collapses actions');
 m.panel.listeners.click({target:{closest:()=>m.more}});m.close();check(m.menu.hidden&&m.more.attrs['aria-expanded']==='false','Closing flyout collapses actions');
 m.open();m.more.getBoundingClientRect=()=>({right:360,top:220,bottom:250});m.panel.listeners.click({target:{closest:()=>m.more}});
 check(m.menu.style.top==='256px','Menu opens below when there is room');m.list.listeners.scroll();check(m.menu.hidden,'Scrolling list closes floating menu');
 m.panel.getBoundingClientRect=()=>({left:0,right:390,width:390,top:100,bottom:400});m.more.getBoundingClientRect=()=>({right:360,top:340,bottom:370});m.menu.getBoundingClientRect=()=>({height:260});m.panel.listeners.click({target:{closest:()=>m.more}});check(m.menu.style.top==='112px'&&m.menu.style.maxHeight==='276px','Tall menu uses full flyout height instead of short list height');
 check(!/requestSubmit|\.submit\(/.test(source),'Opening menu never submits a payment');
 const emptyHtml='<div class="admin-crypto-quick__empty"><span><i class="bi bi-wallet2"></i></span><strong>Brak płatności w tej kategorii</strong><p>Gdy pojawią się płatności klientów, zobaczysz je tutaj.</p></div>';
 for(const [category,before,after,next,count] of [
  ['expired',{expired:1,payout:2,cancelled:3},{payout:2,cancelled:3},'payout',2],
  ['expired',{expired:1,cancelled:3},{cancelled:3},'cancelled',3],
  ['cancelled',{cancelled:1},{new:4,expired:2,payout:3},'new',4],
  ['cancelled',{cancelled:1},{},'new',0]
 ]){
  const last=setup(before);last.open();last.reply({ok:true,filter:category,html:'last row',counts:before});for(let i=0;i<8;i++)await Promise.resolve();
  const requestsBefore=last.requests;const pending=last.deleteLast();
  last.reply({ok:true,message:'Deleted',crypto_quick:{filter:category,html:emptyHtml,counts:after}});await pending;
  check(last.list.innerHTML===emptyHtml,'Deleting last '+category+' row immediately renders the complete empty view');
  check(last.badge.textContent===String(count)&&last.badge.dataset.tone===next&&last.badge.hidden===(count===0),'Deleting last row immediately updates navbar count, priority colour and visibility');
  check(last.categoryCount.textContent==='0'&&last.categoryCount.dataset.tone===category,'Empty selected category keeps a zero header count with its own colour');
  check(last.filters.find(f=>f.dataset.cryptoQuickFilter===category).attrs['aria-pressed']==='true'&&!last.panel.hidden,'Empty view stays in the selected tab without closing the flyout');
  check(last.requests===requestsBefore+1,'Deletion result updates view and badge without another GET request');
 }
 console.log(`PASS ${n} crypto badge priority and default filter assertions`);
})().catch(e=>{console.error(e);process.exitCode=1;});
