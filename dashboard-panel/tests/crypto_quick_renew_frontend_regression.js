'use strict';
const fs = require('fs'), vm = require('vm'), assert = require('assert');
const source = fs.readFileSync('public_html/assets/js/admin-crypto-quick.js', 'utf8');
const start = source.indexOf('        let renewing = false;');
const end = source.indexOf("        if (refresh) refresh.addEventListener", start);
function setup(action = 'renew', currentFilter = 'expired') {
    let submit, resolve, loads = 0, selected = '', cleared = 0;
    const requests = [], result = { hidden: true, dataset: {} };
    const button = { disabled: false, setAttribute() {}, removeAttribute() {} };
    const form = { action: 'http://localhost/admin/?page=payments', querySelector: () => button };
    const list = { setAttribute() {} }; let badgeCounts;
    const panel = { hidden: false, dataset: { error: 'Unable to renew' }, contains: f => f === form,
        querySelector: () => result, addEventListener: (name, fn) => { submit = fn; } };
    const fields = new Map([['admin_payment_quick_action','1'],['_csrf','csrf'],['quick_action',action],['payment_id','77']]);
    vm.runInNewContext(source.slice(start, end), { panel, AbortController, filter: currentFilter, revision: 0, controller: null, list, refresh: null, syncHints() {}, startCountdown() {}, closeActions() {}, updateBadge: counts => { badgeCounts = counts; }, Date,
        cache: { clear() { cleared++; }, set() {} }, selectFilter: f => { selected = f; }, load: async () => { loads++; },
        FormData: class { constructor() { this.fields = new Map(fields); } delete(k) { this.fields.delete(k); } set(k,v) { this.fields.set(k,v); } get(k) { return this.fields.get(k); } },
        setTimeout: () => 1, clearTimeout() {},
        fetch: (url, options) => { requests.push({url,options}); return new Promise(r => { resolve = r; }); }
    });
    return { result, button, requests, list, get badgeCounts() { return badgeCounts; }, fire() { return submit({target:{closest:()=>form},preventDefault(){}}); },
        reply(ok, data) { resolve({ok,json:async()=>data}); },
        get loads() { return loads; }, get selected() { return selected; }, get cleared() { return cleared; } };
}
(async () => {
    const t = setup(), pending = t.fire();
    assert.equal(t.button.disabled, true);
    await t.fire(); assert.equal(t.requests.length,1);
    const req=t.requests[0]; assert.equal(req.options.method,'POST'); assert.equal(req.options.credentials,'same-origin');
    assert.equal(req.options.body.fields.get('_csrf'),'csrf');
    assert.equal(req.options.body.fields.get('admin_payment_quick_action_ajax'),'1');
    assert.equal(req.options.body.fields.has('admin_payment_quick_action'),false);
    t.reply(true,{ok:true,message:'Renewed'}); await pending;
    assert.equal(t.selected,'new'); assert.equal(t.loads,1); assert.equal(t.cleared,1);
    assert.equal(t.result.dataset.tone,'success'); assert.equal(t.result.textContent,'Renewed');
    assert.equal(t.button.disabled,false);
    const bad=setup(), failed=bad.fire(); bad.reply(false,{ok:false,message:'Rate unavailable'}); await failed;
    assert.equal(bad.loads,0); assert.equal(bad.selected,''); assert.equal(bad.result.dataset.tone,'error');
    assert.equal(bad.result.textContent,'Rate unavailable'); assert.equal(bad.button.disabled,false);
    const template=fs.readFileSync('dashboard-panel/admin/crypto_quick.php','utf8');
    assert.ok(template.includes('data-crypto-quick-action'));
    for (const [action, category] of [['cancel','new'],['cancel','expired'],['archive','payout'],['delete','expired'],['delete','cancelled']]) {
        const t=setup(action,category), pending=t.fire();
        const counts={new:0,expired:0,cancelled:action==='cancel'?1:0,payout:0};
        assert.equal(t.requests[0].options.body.fields.get('crypto_quick_filter'),category);
        t.reply(true,{ok:true,message:'Saved',crypto_quick:{filter:category,counts,html:'updated list'}});
        await pending;
        assert.equal(t.list.innerHTML,'updated list');
        assert.deepEqual(t.badgeCounts,counts);
        assert.equal(t.loads,0); // Mutation response supplies list and badge without a second read.
        assert.equal(t.selected,''); // Cancel/archive/delete preserve the current tab.
        assert.equal(t.button.disabled,false);
    }
    const deletion=setup('delete','cancelled'), deletionFailed=deletion.fire();
    deletion.reply(false,{ok:false,message:'Unable to delete'});await deletionFailed;
    assert.equal(deletion.loads,0);assert.equal(deletion.list.innerHTML,undefined);
    assert.equal(deletion.result.dataset.tone,'error');assert.equal(deletion.button.disabled,false);
    console.log('PASS: inline renew/cancel/archive/delete POST/CSRF, duplicate-submit guard, immediate list and badge updates, current-tab preservation, errors and button recovery. No real payment changed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
