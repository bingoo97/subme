'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('public_html/assets/js/admin.js','utf8');
const start=source.indexOf('        // The quick payment list is rendered lazily');
const end=source.indexOf("        qa('button[data-admin-crypto-accept-trigger]')",start);
let listener,opened=[],closed=[],prevented=0,allowed=true;
const button={},form={},flyout={},unrelated={};
vm.runInNewContext(source.slice(start,end),{
 doc:{addEventListener:(event,fn)=>{assert.equal(event,'click');listener=fn;}},
 closest:(node,selector)=>selector==='[data-crypto-quick-accept]'?(node===button?button:null):selector==='form'?form:flyout,
 cryptoAcceptModalController:{open:(...args)=>{opened.push(args);return allowed;}},
 closeFlyout:node=>closed.push(node)
});
listener({target:unrelated});assert.equal(opened.length,0);
listener({target:button,preventDefault(){prevented++;}});
assert.equal(prevented,1);assert.equal(opened.length,1);assert.equal(opened[0][0],button);assert.equal(opened[0][1],form);assert.equal(opened[0][2].ajax,true);assert.equal(opened[0][2].flyout,null);assert.equal(closed[0],flyout);
allowed=false;listener({target:button,preventDefault(){}});assert.equal(closed.length,1);
assert.ok(!/fetch\(|requestSubmit|\.submit\(/.test(source.slice(start,end)));
console.log('PASS 10 quick acceptance modal assertions');
