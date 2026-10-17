const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../public_html/assets/js/admin.js'), 'utf8');
const code = source.slice(source.indexOf('    function initSearch()'), source.indexOf('    function initPaymentsCustomerSearch()'));
function element() {
    return { value: '', innerHTML: '', hidden: false, attrs: {}, events: {},
        getAttribute(name) { return this.attrs[name] || null; },
        setAttribute(name, value) { this.attrs[name] = value; },
        removeAttribute(name) { delete this.attrs[name]; },
        addEventListener(name, fn) { this.events[name] = fn; }, focus() {} };
}
const input = element(), reset = element(), results = element(), content = element();
const timers = new Map(), requests = [];
let timerId = 0, checks = 0;
const context = {
    q: selector => ({ '[data-admin-search-input]': input, '[data-admin-search-reset]': reset,
        '[data-admin-search-results]': results, '[data-admin-default-content]': content })[selector],
    setHidden: (el, value) => { el.hidden = value; },
    escapeHtml: value => String(value), toQuery: value => new URLSearchParams(value).toString(),
    window: { AbortController, setTimeout: fn => { timers.set(++timerId, fn); return timerId; }, clearTimeout: id => timers.delete(id) },
    jsonFetch: (url, options) => new Promise(resolve => requests.push({ url, options, resolve }))
};
vm.runInNewContext(code + '\ninitSearch();', context);
function type(value) { input.value = value; input.events.input(); }
function runDebounce() { for (const [id, fn] of [...timers]) { timers.delete(id); fn(); } }
async function settle() { for (let i = 0; i < 8; i++) await Promise.resolve(); }
function check(condition, message) { assert.ok(condition, message); checks++; }
(async () => {
    type('first'); runDebounce();
    type('second');
    requests[0].resolve({ ok: true, html: 'STALE' }); await settle();
    check(!results.innerHTML.includes('STALE'), 'Old response cannot replace a newer query during debounce');
    check(requests[0].options.signal.aborted, 'Typing a new query cancels the previous fetch');
    runDebounce(); requests[1].resolve({ ok: true, html: 'CURRENT' }); await settle();
    check(results.innerHTML === 'CURRENT' && !results.attrs['aria-busy'], 'Latest response renders and clears busy state');
    type('third'); runDebounce(); reset.events.click();
    requests[2].resolve({ ok: true, html: 'RESURRECTED' }); await settle();
    check(results.hidden && results.innerHTML === '' && !content.attrs.hidden, 'Clearing search prevents delayed results from reopening');
    const before = requests.length;
    type('a'); runDebounce();
    check(requests.length === before && results.innerHTML.includes('at least 2'), 'Single nonnumeric character shows guidance without a request');
    type('1'); runDebounce();
    check(requests.length === before + 1, 'One-digit IDs remain searchable');
    input.events.keydown({ key: 'Escape', preventDefault() {} });
    check(input.value === '' && results.hidden, 'Escape restores page content');
    type('enter'); input.events.keydown({ key: 'Enter', preventDefault() {} });
    const afterEnter = requests.length;
    check(timers.size === 1, 'Enter clears pending debounce, leaving only fetch timeout');
    requests[requests.length - 1].resolve({ ok: false }); await settle();
    check(!results.attrs['aria-busy'] && results.innerHTML.includes('Search error'), 'Failure remains readable and clears busy state');
    type('queued'); type(''); runDebounce();
    check(requests.length === afterEnter, 'Empty search cancels a queued request');
    console.log(`PASS: ${checks} global search request/reset/keyboard assertions`);
})().catch(error => { console.error(error); process.exitCode = 1; });
