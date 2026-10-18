'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const ticket={dataset:{customerCryptoTicket:'201',ticketStatus:'pending',ticketDetected:'0'}};
let poll,reloads=0,calls=0,response={ok:true,status:200,json:async()=>({ok:true,status:'pending',detected:false})};
const document={hidden:false,querySelector:()=>ticket},navigator={onLine:true};
const ctx={document,navigator,AbortController,setTimeout:()=>1,clearTimeout(){},setInterval:(fn,ms)=>{assert.equal(ms,30000);poll=fn;return 1;},clearInterval(){},window:{addEventListener(){},location:{reload(){reloads++;}}},fetch:async(url,opts)=>{calls++;assert.equal(url,'/check_payment.php?ticket_status=201');assert.equal(opts.credentials,'same-origin');return response;}};
vm.runInNewContext(fs.readFileSync('public_html/assets/js/customer-crypto-ticket-status.js','utf8'),ctx);
(async()=>{
assert.equal(calls,0);document.hidden=true;await poll();assert.equal(calls,0);
document.hidden=false;navigator.onLine=false;await poll();assert.equal(calls,0);
navigator.onLine=true;await poll();assert.equal(calls,1);assert.equal(reloads,0);
response={ok:true,status:200,json:async()=>({ok:true,status:'awaiting_review',detected:true})};await poll();assert.equal(reloads,1);await poll();assert.equal(calls,2);
console.log('PASS customer crypto polling: bounded interval, offline/hidden skips, owned request and reload-on-detection');
})().catch(e=>{console.error(e);process.exitCode=1;});
