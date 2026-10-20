'use strict';
const fs = require('fs'), vm = require('vm'), assert = require('assert');
const source = fs.readFileSync('public_html/assets/js/customer-order-access.js', 'utf8');
let checks = 0;
const check = (value, message) => { assert.ok(value, message); checks++; };
function fixture(mode) {
    let click, copied = [], removed = 0, selected = 0, fallback = 0, timer;
    const classes = new Set(), label = {textContent:'Copy'}, icon = {className:'fa fa-clone'};
    const input = {value:'sample-secret',closest:()=>true,focus(){},select(){selected++;}};
    const button = {disabled:false,dataset:{orderAccessCopy:'sample',copyLabel:'Copy',copiedLabel:'Copied',copyErrorLabel:'Copy manually'},
        classList:{add:k=>classes.add(k),remove:(...keys)=>keys.forEach(k=>classes.delete(k)),contains:k=>classes.has(k)},
        querySelector:s=>s==='i'?icon:label,removeAttribute(k){delete this[k];},focus(){}};
    const document = {addEventListener(k,fn){click=fn;},getElementById:()=>input,body:{appendChild(){}},
        createElement(){return {style:{},select(){},remove(){removed++;}};},
        execCommand(){fallback++;return mode!=='denied';}};
    const navigator = mode==='missing'?{}:{clipboard:{writeText:async text=>{if(mode!=='success')throw Error('Denied');copied.push(text);}}};
    vm.runInNewContext(source,{document,navigator,WeakMap,Error,clearTimeout(){},setTimeout(fn,ms){timer={fn,ms};return 1;}});
    return {run:()=>click({target:{closest:()=>button}}),button,classes,label,icon,input,copied,
        state:()=>({removed,selected,fallback,timer})};
}
(async()=>{
    for(const mode of ['success','rejected','missing','denied']){
        const f=fixture(mode);await f.run();
        check(!f.button.disabled,mode+': button is enabled after completion');
        if(mode==='denied'){
            check(f.classes.has('is-error')&&f.state().selected===1,'Denied clipboard selects the value for manual copy');
            check(!f.classes.has('is-copied'),'Failure never claims the value was copied');
        }else{
            check(f.classes.has('is-copied')&&f.label.textContent==='Copied'&&f.icon.className==='fa fa-check',mode+': clear success feedback');
        }
        if(mode==='success')check(f.copied[0]==='sample-secret'&&f.state().fallback===0,'Native clipboard copies the exact field value');
        else check(f.state().fallback===1&&f.state().removed===1,mode+': fallback cleans up the temporary field');
        check(f.state().timer.ms===5000,mode+': feedback lasts five seconds');
        f.state().timer.fn();
        check(f.label.textContent==='Copy'&&f.classes.size===0,mode+': original button state is restored');
    }
    const f=fixture('success');f.button.disabled=true;await f.run();check(f.copied.length===0,'Busy button prevents duplicate clipboard writes');
    check(!/fetch\(|console\.|localStorage|sessionStorage/.test(source),'Credentials are not sent, logged or persisted');
    console.log(`PASS ${checks} customer access clipboard assertions`);
})().catch(e=>{console.error(e);process.exitCode=1;});

// Product selection reveals the next action without changing the checkout submission.
{
    const picker = fs.readFileSync('public_html/assets/js/add_new.js', 'utf8');
    const start = picker.indexOf('var productContinueScrollFrame = 0;');
    const end = picker.indexOf('function selectProductOption(', start);
    let calls = [], frames = new Map(), nextFrame = 0, reduce = false;
    const next = {disabled:false,scrollIntoView:options=>calls.push(options)};
    const context = {document:{getElementById:()=>next},window:{cancelAnimationFrame:id=>frames.delete(id),requestAnimationFrame:fn=>{frames.set(++nextFrame,fn);return nextFrame;},matchMedia:()=>({matches:reduce})}};
    vm.runInNewContext(picker.slice(start,end),context);
    context.revealProductContinue();context.revealProductContinue();
    assert.strictEqual(frames.size,1,'Repeated selection callbacks schedule only one scroll');
    [...frames.values()][0]();assert.strictEqual(calls[0].behavior,'smooth');
    reduce=true;context.revealProductContinue();[...frames.values()].pop()();assert.strictEqual(calls[1].behavior,'auto');
    next.disabled=true;context.revealProductContinue();[...frames.values()].pop()();assert.strictEqual(calls.length,2,'Disabled next action is never revealed');
    console.log('PASS product selection scroll, duplicate callback and reduced motion checks');
}

// Legacy collapse controls must toggle an existing Bootstrap instance, not just create it.
{
    const bridge=fs.readFileSync('public_html/assets/js/bootstrap5-legacy-bridge.js','utf8');
    const start=bridge.indexOf('\tfunction toggleCollapse(trigger)'),end=bridge.indexOf('\n\t// jQuery tooltip compatibility',start);
    function classList(initial=[]) {const values=new Set(initial);return {contains:k=>values.has(k),add:k=>values.add(k),toggle(k,on){if(on===undefined)on=!values.has(k);on?values.add(k):values.delete(k);}};}
    for(const native of [true,false]){
        const target={classList:classList(),style:{}},attrs={},trigger={classList:classList(['collapsed']),getAttribute:k=>attrs[k]||null,setAttribute:(k,v)=>attrs[k]=v};
        let toggles=0;
        const context={resolveTarget:()=>target,window:{}};
        if(native)context.window.bootstrap={Collapse:{getOrCreateInstance(_target,options){assert.strictEqual(options.toggle,false);return {toggle(){toggles++;target.classList.toggle('show');}};}}};
        vm.runInNewContext(bridge.slice(start,end),context);
        context.toggleCollapse(trigger);assert.strictEqual(attrs['aria-expanded'],'true');assert.ok(!trigger.classList.contains('collapsed'));
        context.toggleCollapse(trigger);assert.strictEqual(attrs['aria-expanded'],'false');assert.ok(trigger.classList.contains('collapsed'));
        if(native)assert.strictEqual(toggles,2,'Reusing Bootstrap still closes on second click');
        else assert.strictEqual(target.style.display,'none');
        target.classList.add('collapsing');context.toggleCollapse(trigger);assert.strictEqual(attrs['aria-expanded'],'false','Transition clicks do not corrupt expanded state');
    }
    console.log('PASS legacy Bootstrap and fallback expand/collapse behavior');
}
