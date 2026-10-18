const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../public_html/assets/js/admin.js'), 'utf8');
const start = source.indexOf('        var cryptoAcceptModalController = (function () {');
const end = source.indexOf('        function closeFlyout(', start);
assert.ok(start > 0 && end > start, 'Modal controller found');
function element() {
    return { value: '', textContent: '', innerHTML: '', attrs: {}, events: {}, hidden: false, disabled: false,
        classList: { add() {}, remove() {}, toggle() {} },
        getAttribute(name) { return this.attrs[name] ?? null; }, setAttribute(name, value) { this.attrs[name] = value; },
        removeAttribute(name) { delete this.attrs[name]; }, addEventListener(name, fn) { this.events[name] = fn; }, focus() {}, select() {} };
}
function harness(mobile = false) {
    const nodes = new Map(), requests = [], alerts = [];
    const q = selector => { if (!nodes.has(selector)) nodes.set(selector, element()); return nodes.get(selector); };
    const modal = q('[data-admin-crypto-accept-modal]'); modal.attrs['data-preview-url'] = '/admin/';
    const context = { q, qa: () => [], hasBootstrap: false, setHidden: (node, hidden) => { node.hidden = hidden; },
        escapeHtml: value => String(value), URLSearchParams, FormData: class { *[Symbol.iterator]() {} },
        fetch: (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve: body => resolve({ json: () => Promise.resolve(body) }), reject })),
        window: { matchMedia: () => ({ matches: !mobile }), setTimeout: fn => fn(), alert: text => alerts.push(text) } };
    vm.runInNewContext(source.slice(start, end), context);
    const crypto = q('[data-admin-crypto-accept-crypto-input]'), fiat = q('[data-admin-crypto-accept-fiat-input]');
    function open(extra = {}) {
        const button = element(); button.attrs['data-admin-crypto-accept-payload'] = JSON.stringify({
            requested_crypto_amount: '.002', requested_fiat_amount: '100', exchange_rate: 50000, asset_code: 'BTC', ...extra });
        context.cryptoAcceptModalController.open(button, element());
    }
    return { open, nodes, q, requests, modal, crypto, fiat, alerts };
}
let checks = 0;
function check(condition, label) { assert.ok(condition, label); checks++; }
async function settle() { for (let i = 0; i < 12; i++) await Promise.resolve(); }
function answer(h, candidate = '') { h.requests.at(-1).resolve({ ok: true, html: '<div>Preview</div>', preview: { candidate_crypto_amount: candidate } }); }
(async () => {
    let h = harness(); h.open();
    check(h.crypto.value === '' && h.fiat.value === '', 'Requested amounts never masquerade as received amounts while loading');
    answer(h); await settle();
    check(h.crypto.value === '' && h.fiat.value === '', 'No candidate leaves both received amounts empty');
    check(h.requests.length === 1 && h.requests[0].options.body.includes('admin_crypto_address_preview_ajax=1'), 'Opening performs one read-only preview request');
    h.q('[data-admin-crypto-accept-submit]').events.click({ preventDefault() {} });
    check(h.requests.length === 1 && h.alerts.length === 1, 'An empty received amount cannot be approved');
    h = harness(); h.open(); answer(h, '.002'); await settle();
    check(h.crypto.value === '0.002' && h.fiat.value === '100.00', 'A matching transfer fills actual amounts even when equal to the quote');
    h = harness(); h.open(); answer(h, '.0016'); await settle();
    check(h.crypto.value === '0.0016' && h.fiat.value === '80.00', 'Partial payment uses actual received amount and locked exchange rate');
    check(!h.q('[data-admin-crypto-accept-crypto-mismatch]').hidden, 'Partial payment shows the difference from the quote');
    h = harness(); h.open(); h.crypto.value = '.001'; h.crypto.events.input(); answer(h, '.002'); await settle();
    check(h.crypto.value === '.001' && h.fiat.value === '50.00', 'Delayed preview cannot overwrite manually entered crypto');
    h = harness(); h.open(); h.fiat.value = '75.00'; h.fiat.events.input(); answer(h, '.002'); await settle();
    check(h.fiat.value === '75.00', 'Delayed preview preserves manually entered FIAT');
    h = harness(); h.open({ received_crypto_amount: '.001', received_fiat_amount: '55.25' }); answer(h, '.002'); await settle();
    check(h.crypto.value === '.001' && h.fiat.value === '55.25', 'Saved received values are not replaced with a quote or chain suggestion');
    h = harness(); h.open(); const stale = h.requests[0]; h.modal.events['hidden.bs.modal'](); h.open();
    stale.resolve({ ok: true, html: 'STALE', preview: { candidate_crypto_amount: '999' } }); await settle();
    check(h.crypto.value === '' && !h.q('[data-admin-crypto-accept-explorer-panel]').innerHTML.includes('STALE'), 'Response from a previous modal cannot fill a reopened request');
    answer(h, '.0016'); await settle();
    check(h.crypto.value === '0.0016', 'Only the current preview can fill the reopened modal');
    h = harness(); h.open(); h.requests[0].reject(new Error('offline')); await settle();
    check(h.crypto.value === '' && h.fiat.value === '' && h.q('[data-admin-crypto-accept-explorer-panel]').innerHTML.includes('fallback'), 'Network error leaves a visible fallback and no invented payment');
    h = harness(true); let focused = false; h.crypto.focus = () => { focused = true; }; h.open();
    check(!focused, 'Mobile preview does not automatically open the keyboard');
    console.log(`PASS: ${checks} crypto preview frontend/race/manual-input assertions`);
})().catch(error => { console.error(error); process.exitCode = 1; });
