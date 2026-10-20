'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public_html/assets/js/admin-crypto-create.js','utf8');
const fragment=source.slice(source.indexOf('        let pageListRequest'),source.indexOf('        const feedback'));
(async()=>{
 const target={innerHTML:'old'},count={textContent:'0'},calls=[],pending=[];
 let freshRows={innerHTML:'fresh'}, pageMode=true, currentCount=count, nextCount={textContent:'2'};
 const filter={dataset:{paymentScope:'new'},querySelector:()=>currentCount,append:node=>{currentCount=node;}};
 count.remove=()=>{currentCount=null;};
 target.setAttribute=(key,value)=>{target[key]=value;};
 const context={
  pageMode,window:{location:{origin:'http://localhost:8088',search:'?page=payments&payment_scope=new&customer_id=14'}},
  document:{querySelector:()=>target,querySelectorAll:()=>[filter],createElement:()=>({remove:()=>{currentCount=null;}})},
  URL,AbortController,setTimeout:()=>1,clearTimeout:()=>{},
  DOMParser:class {parseFromString(){return {querySelector:s=>s==='[data-payment-list-results]'?freshRows:nextCount};}},
  fetch:(url,options)=>{calls.push({url,options});return new Promise(resolve=>pending.push(resolve));}
 };
 vm.createContext(context);vm.runInContext(fragment+'\nthis.refresh=refreshPageList;',context);
 let first=context.refresh(),second=context.refresh();
 assert.equal(calls.length,2);assert.equal(calls[0].options.signal.aborted,true);
 assert.equal(calls[1].url.origin,'http://localhost:8088');
 assert.equal(calls[1].url.searchParams.get('customer_id'),'14');
 assert.equal(calls[1].url.searchParams.get('payment_scope'),'new');
 assert.equal(calls[1].options.credentials,'same-origin');assert.equal(calls[1].options.method,undefined);
 pending[1]({ok:true,text:async()=>'<html/>'});await second;
 assert.equal(target.innerHTML,'fresh');assert.equal(count.textContent,'2');
 freshRows={innerHTML:'stale'};pending[0]({ok:true,text:async()=>'<html/>'});await first;
 assert.equal(target.innerHTML,'fresh','Slow older GET cannot replace the latest list');
 const failed=context.refresh();pending[2]({ok:false});await failed;
 assert.equal(target.innerHTML,'fresh','Read failure keeps current rows');
 const noRows=context.refresh();freshRows=null;pending[3]({ok:true,text:async()=>'<login/>'});await noRows;
 assert.equal(target.innerHTML,'fresh','Login response cannot remove the list');
 currentCount=null;freshRows={innerHTML:'new row'};const addBadge=context.refresh();pending[4]({ok:true,text:async()=>'<html/>'});await addBadge;
 assert.equal(currentCount.className,'admin-payments-filter__count');assert.equal(currentCount.textContent,'2');
 nextCount=null;const removeBadge=context.refresh();pending[5]({ok:true,text:async()=>'<html/>'});await removeBadge;assert.equal(currentCount,null);
 context.pageMode=false;await context.refresh();assert.equal(calls.length,6,'Flyout creation does not reload the Payments page');
 const admin=fs.readFileSync('public_html/assets/js/admin.js','utf8');
 const start=admin.indexOf('        // Rows refreshed');const end=admin.indexOf("        qa('[data-admin-converter-root]')",start);
 let click,opened=0,prevented=0;const form={},button={};
 vm.runInNewContext(admin.slice(start,end),{doc:{addEventListener:(_,fn)=>{click=fn;}},closest:(_,s)=>s==='form'?form:button,cryptoAcceptModalController:{open:(b,f,o)=>{assert.equal(b,button);assert.equal(f,form);assert.equal(o.ajax,false);opened++;return true;}}});
 click({target:button,preventDefault:()=>{prevented++;}});assert.equal(opened,1);assert.equal(prevented,1);
 console.log('PASS Payments creator list refresh, filters, stale responses, read failures and refreshed acceptance action');
})().catch(error=>{console.error(error);process.exitCode=1;});
