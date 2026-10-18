'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public_html/assets/js/admin.js','utf8');
const start=source.indexOf('    function initPaymentStatusGuidance()');
const end=source.indexOf('    function initPaymentReceivedFields()',start);
let n=0;function ck(v,m){assert.ok(v,m);n++;}
const classes=new Set(),listeners={};
const select={value:'pending',classList:{toggle(k,on){on?classes.add(k):classes.delete(k);}},addEventListener(k,f){listeners[k]=f;}};
const nodes=Object.fromEntries(['[data-status-hint-title]','[data-status-hint-text]','i'].map(s=>[s,{}]));
const items=Object.fromEntries(['pending','approved','archived','cancelled','expired','awaiting_review'].map((s,i)=>[s,{title:s+' title',text:s+' explanation',icon:'bi-'+s,tone:['warning','success','neutral','danger','danger','info'][i]}]));
const hint={dataset:{guidance:JSON.stringify(items)},querySelector:s=>nodes[s]};
vm.runInNewContext(source.slice(start,end)+'\ninitPaymentStatusGuidance();',{q:s=>s==='#payment_status'?select:hint,JSON});
for(const [status,item] of Object.entries(items)){
 select.value=status;listeners.change();
 ck(hint.dataset.tone===item.tone&&nodes['[data-status-hint-text]'].textContent===item.text,'Change updates '+status+' explanation and tone');
 ck(classes.size===1&&classes.has('admin-payment-status-select--'+item.tone),'Select color matches '+status);
}
select.value='pending';listeners.change();ck(nodes['[data-status-hint-text]'].textContent===items.pending.text,'Returning to original restores its explanation');
ck(!/fetch\(|\.submit\(|requestSubmit/.test(source.slice(start,end)),'Guidance does not save or send network requests');
ck(source.includes("statusSelect.dispatchEvent(new Event('change', { bubbles: true }))"),'Blockchain result also triggers guidance refresh');
console.log(`PASS ${n} payment status preview assertions`);
