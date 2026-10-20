'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
process.env.TZ='Europe/Vienna';
const source=fs.readFileSync('public_html/assets/js/admin.js','utf8');
const start=source.indexOf('    function initOrderExtendPreviews()');
const end=source.indexOf('    function initSearch()',start);
assert(start>0&&end>start);
let clock=new Date('2026-10-20T12:00:00').getTime();
class ClockDate extends Date {constructor(...args){super(...(args.length?args:[clock]));}static now(){return clock;}}
function form(expiry,hours){
 let change;
 const selected={value:'package',textContent:'Three months',getAttribute:()=>String(hours)};
 const product={options:[selected],selectedIndex:0,addEventListener:(event,fn)=>{assert.equal(event,'change');change=fn;}};
 const preview={value:''},automatic={value:''},summary={},note={hidden:true},noteInput={};
 const elements={'[data-admin-order-extend-product]':product,'[data-admin-order-extend-preview]':preview,'[data-admin-order-extend-automatic]':automatic,'[data-admin-order-extend-selection]':summary,'[data-admin-order-extend-note]':note};
 return {getAttribute:()=>expiry,elements,preview,automatic,selected,note,noteInput,change:()=>change()};
}
const active=form('2026-11-01 09:15:00',2160),expired=form('2026-10-10 12:00:00',2160),empty=form('',0);
const forms=[active,expired,empty];
vm.runInNewContext(source.slice(start,end)+'\ninitOrderExtendPreviews();',{
 Date:ClockDate,qa:()=>forms,q:(selector,parent)=>selector==='textarea'?forms.find(f=>f.note===parent).noteInput:parent.elements[selector],setHidden:(node,hidden)=>{node.hidden=hidden;}
});
assert.equal(active.preview.value,'2027-02-01T09:15','Active preview starts at existing expiry');
assert.equal(expired.preview.value,'2027-01-20T12:00','Expired preview starts today');
assert.equal(active.automatic.value,active.preview.value);assert.equal(expired.automatic.value,expired.preview.value);
assert.equal(empty.preview.value,'');assert.equal(empty.automatic.value,'');
expired.preview.value='2027-03-01T12:00';
assert.notEqual(expired.preview.value,expired.automatic.value,'Manual edits remain distinguishable from the automatic preview');
clock+=2*86400000;expired.change();
assert.equal(expired.preview.value,'2027-01-22T12:00');assert.equal(expired.automatic.value,expired.preview.value,'Changing package refreshes the automatic value');
expired.selected.value='';expired.selected.getAttribute=()=> '0';expired.change();
assert.equal(expired.preview.value,'');assert.equal(expired.automatic.value,'');assert.equal(expired.noteInput.disabled,true);
const php=fs.readFileSync('dashboard-panel/admin/index.php','utf8');
assert(php.includes('admin_order_extension_requested_expiry($_POST)'));assert(php.includes('data-admin-order-extend-automatic'));
console.log('PASS active/expired renewal previews, elapsed time, custom dates and server automatic-date wiring');
