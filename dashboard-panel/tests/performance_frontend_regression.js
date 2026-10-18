'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../../public_html/assets/js/admin.js'),'utf8');
const a=source.indexOf('        function pollChatInboxState(force) {'),b=source.indexOf('        function saveAdminChatPanelState(',a);
let checks=0;const ck=(v,m)=>{assert.ok(v,m);checks++;};
async function main(){
 let requests=[],timers=new Map(),n=0;
 const context={chatStateUrl:'/fixture',chatInboxFetchInFlight:false,doc:{hidden:false},window:{AbortController,setTimeout(f){const id=++n;timers.set(id,f);return id;},clearTimeout(id){timers.delete(id);}},updateChatToggleBadge(){},updateChatInboxItems(){},jsonFetch(url,options){return new Promise((resolve,reject)=>{options.signal.addEventListener('abort',()=>reject(new Error('timeout')));requests.push({resolve,reject});});}};
 vm.createContext(context);vm.runInContext(source.slice(a,b),context);
 context.pollChatInboxState(false);context.pollChatInboxState(true);ck(requests.length===1,'Focus and timer cannot start overlapping inbox requests');
 requests[0].resolve({ok:true,items:[]});await new Promise(setImmediate);ck(!context.chatInboxFetchInFlight&&timers.size===0,'Completion releases the guard and its timeout');
 context.doc.hidden=true;context.pollChatInboxState(false);ck(requests.length===1,'Hidden tab does not poll');
 context.doc.hidden=false;context.pollChatInboxState(false);[...timers.values()][0]();await new Promise(setImmediate);ck(!context.chatInboxFetchInFlight,'Timeout unlocks the inbox without accumulating requests');
 context.pollChatInboxState(false);ck(requests.length===3,'A later poll can recover after a timeout');requests[2].resolve({ok:false});await new Promise(setImmediate);ck(!context.chatInboxFetchInFlight,'Rejected API result also unlocks polling');
 console.log(`PASS ${checks} frontend performance assertions`);
}
main().catch(e=>{console.error(e);process.exitCode=1;});
