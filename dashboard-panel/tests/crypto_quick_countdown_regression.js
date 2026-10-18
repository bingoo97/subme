'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public_html/assets/js/admin-crypto-quick.js','utf8');
const start=source.indexOf('        let countdownTimer'),end=source.indexOf('        const message =',start);
let now=1000000,live=new Map(),next=1;
const spinner={hidden:false},node={dataset:{cryptoQuickExpires:'1120',remainingLabel:'Pozostało: {minutes} min',expiredLabel:'Termin minął'},closest:()=>({querySelector:()=>spinner})};
const panel={hidden:false,querySelectorAll:()=>[node]};
const ctx={panel,filter:'new',Date:{now:()=>now},window:{setInterval:fn=>{const id=next++;live.set(id,fn);return id;},clearInterval:id=>live.delete(id)}};
vm.createContext(ctx);vm.runInContext(source.slice(start,end)+'\nglobalThis.testStart=startCountdown;globalThis.testStop=stopCountdown;globalThis.testUpdate=updateCountdowns;',ctx);
ctx.testStart();assert.equal(node.textContent,'Pozostało: 2 min');assert.equal(live.size,1);
now+=61000;ctx.testUpdate();assert.equal(node.textContent,'Pozostało: 1 min');
ctx.testStart();assert.equal(live.size,1); // Reopen/cached render never duplicates interval.
now+=59000;ctx.testUpdate();assert.equal(node.textContent,'Termin minął');assert.equal(spinner.hidden,true);
ctx.testStop();assert.equal(live.size,0);
now-=120000;ctx.testStart();assert.equal(node.textContent,'Pozostało: 2 min');assert.equal(spinner.hidden,false);
panel.hidden=true;ctx.testStart();assert.equal(live.size,0);
assert.ok(!/fetch\(/.test(source.slice(start,end)));
console.log('PASS 11 quick payment countdown assertions');
