'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public_html/assets/js/admin.js','utf8');
const start=source.indexOf('            function syncAmountVisibility('),end=source.indexOf('            function clearState()',start);
const state={payload:{},previewMeta:null,submitting:false},amountFields={},amountHint={},submitButton={};
const ctx={state,amountFields,amountHint,submitButton,parseNumeric:v=>Number(v)||0,setHidden:(node,v)=>{node.hidden=v;}};
vm.createContext(ctx);vm.runInContext(source.slice(start,end),ctx);
let n=0;
function check(unavailable,visible){ctx.syncAmountVisibility(unavailable);assert.equal(amountFields.hidden,!visible);assert.equal(amountHint.hidden,!visible);assert.equal(submitButton.disabled,!visible||state.submitting);n+=3;}
check(false,true); // Manual correction remains available while loading.
state.previewMeta={candidate_crypto_amount:''};check(false,true); // Successful empty preview.
state.previewMeta={candidate_crypto_amount:'0'};check(false,true);
state.previewMeta={candidate_crypto_amount:'0.00058'};check(false,true); // Detected transfer.
state.previewMeta=null;state.payload.received_crypto_amount='0.0004';check(false,true); // Saved receipt.
state.payload={};check(true,true); // Provider failure allows manual verification.
check(false,true); // Another empty request keeps manual correction available.
state.previewMeta={candidate_crypto_amount:'0.1'};state.submitting=true;check(false,true);
console.log(`PASS ${n} acceptance amount visibility assertions`);
