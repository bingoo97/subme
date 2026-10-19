'use strict';
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync('public_html/assets/js/admin.js','utf8');
const start = source.indexOf('function initOrdersGuidanceToggle()');
const code = source.slice(start, source.indexOf('function initOrdersActionMenus()',start));
let checks = 0;
for (const stored of [null,'0','1','invalid','blocked']) {
    let change, saved;
    const classes = new Set(['is-admin-orders-guidance-hidden']);
    const targets = [{hidden:false},{hidden:false},{hidden:false}];
    const toggle = {checked:false,setAttribute(name,value){this[name]=value;},addEventListener(name,handler){change=handler;}};
    const context = {q:()=>toggle,qa:()=>targets,setHidden:(node,value)=>{node.hidden=value;},doc:{body:{classList:{toggle(name,value){if(value)classes.add(name);else classes.delete(name);}}}},window:{localStorage:{getItem(){if(stored==='blocked')throw new Error('Storage unavailable');return stored;},setItem(key,value){saved=value;}}}};
    vm.createContext(context);vm.runInContext(code,context);context.initOrdersGuidanceToggle();
    const visible=stored==='1';
    assert.equal(classes.has('is-admin-orders-guidance-hidden'),!visible);checks++;
    assert.ok(targets.every(node=>node.hidden===!visible));checks++;
    assert.equal(toggle.checked,visible);assert.equal(toggle['aria-checked'],String(visible));checks++;
    toggle.checked=true;change();assert.ok(targets.every(node=>!node.hidden));assert.equal(saved,'1');checks++;
    toggle.checked=false;change();assert.ok(classes.has('is-admin-orders-guidance-hidden'));assert.ok(targets.every(node=>node.hidden));assert.equal(saved,'0');checks++;
}
console.log(`PASS ${checks} orders guidance default, stored preference, blocked storage and toggle checks`);
