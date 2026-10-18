'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../public_html/assets/js/panel-subscription.js'), 'utf8');
const scheduler = source.slice(source.indexOf('    const canAutoCheck ='), source.indexOf('    const toggleBusy ='));
const request = source.slice(source.indexOf('    const request = async'), source.indexOf("    document.addEventListener('click'"));
assert.ok(scheduler && request, 'Actual payment scheduler and request controller are present');
let assertions = 0;
const ck = (value, message) => { assert.ok(value, message); assertions++; };
const settle = async () => { for (let i = 0; i < 16; i++) await Promise.resolve(); };
function harness() {
    let now = 1000000, serial = 0;
    const timers = new Map(), requests = [], retry = {}, notices = [];
    const context = {
        busy: false, timer: null, state: null, sequence: 0, controller: null,
        nextPollAt: 0, pollFailures: 0, pollingStopped: false,
        document: { hidden: false }, navigator: { onLine: true },
        modal: { visible: true, dataset: { csrf: 'fixture' }, classList: { contains: () => context.modal.visible } },
        Date: { now: () => now }, URLSearchParams, AbortController, labels: { error: 'retry' },
        toggleBusy() {}, scrollToNotice() {}, find: () => retry,
        showNotice: (...args) => notices.push(args),
        render: next => { context.state = next; },
        setTimeout(fn, delay) { const id = ++serial; timers.set(id, { at: now + delay, fn }); return id; },
        clearTimeout(id) { timers.delete(id); },
        fetch: (url, options) => new Promise((resolve, reject) => {
            const entry = { action: options.body.get('action'), options, resolve, reject };
            options.signal.addEventListener('abort', () => { const error = new Error('aborted'); error.name = 'AbortError'; reject(error); });
            requests.push(entry);
        })
    };
    vm.createContext(context); vm.runInContext(scheduler + request, context);
    const pending = status => ({ ok: true, scan_ok: true, pending: !['paid', 'expired'].includes(status), request: { status } });
    const answer = async (status = 'pending', code = 200, extra = {}) => {
        requests.at(-1).resolve({ ok: code >= 200 && code < 300, status: code, json: async () => ({ ...pending(status), ...extra }) });
        await settle();
    };
    const advance = async duration => {
        const end = now + duration;
        while (true) {
            const next = [...timers].sort((a, b) => a[1].at - b[1].at).find(([, t]) => t.at <= end);
            if (!next) break;
            now = next[1].at; timers.delete(next[0]); next[1].fn(); await settle();
        }
        now = end; await settle();
    };
    return { context, requests, timers, notices, answer, advance, call: expression => vm.runInContext(expression, context), remaining: () => Math.min(...[...timers.values()].map(t => t.at - now)) };
}
(async () => {
    let h = harness(); h.call("request('load')"); await h.answer();
    ck(h.remaining() === 2000, 'Opening an outstanding invoice schedules its first automatic check');
    await h.advance(2000);
    ck(h.requests.length === 2 && h.requests[1].action === 'check', 'Payment detection starts automatically without clicking a button');
    h.call("request('check')"); ck(h.requests.length === 2, 'In-flight check cannot be duplicated');
    await h.answer(); ck(h.remaining() === 60000, 'Normal automatic checks are one minute apart');
    await h.advance(59999); ck(h.requests.length === 2, 'No request before the poll interval');
    await h.advance(1); ck(h.requests.length === 3, 'Subsequent check is automatic');
    await h.answer('partial'); await h.advance(60000);
    ck(h.requests.length === 4, 'Partial payment and top-ups remain under automatic monitoring');
    await h.answer('confirming'); await h.advance(60000);
    ck(h.requests.length === 5, 'Confirmation monitoring continues automatically after the payment timer expires');
    await h.answer('paid'); ck(h.timers.size === 0, 'Confirmed payment stops all polling');

    h = harness(); h.call("request('create')"); await h.answer();
    ck(h.remaining() === 60000, 'New invoice does not immediately waste a blockchain check before payment');
    h.context.document.hidden = true; h.call('schedulePoll()');
    await h.advance(120000); ck(h.requests.length === 1 && h.timers.size === 0, 'Hidden tab sends no polling requests');
    h.context.document.hidden = false; h.call('schedulePoll()');
    ck(h.remaining() === 2000, 'Returning to an overdue tab schedules one bounded refresh');
    h.call('schedulePoll()'); h.call('schedulePoll()');
    await h.advance(2000); ck(h.requests.length === 2, 'Repeated visibility events cannot stack requests');
    await h.answer(); h.context.navigator.onLine = false; h.call('schedulePoll()');
    await h.advance(600000); ck(h.requests.length === 2, 'Offline tab sends no polling traffic');
    h.context.navigator.onLine = true; h.call('schedulePoll()'); await h.advance(2000);
    ck(h.requests.length === 3, 'Monitoring resumes automatically after connectivity returns');
    await h.answer(); h.context.modal.visible = false; h.call('schedulePoll()');
    await h.advance(600000); ck(h.requests.length === 3, 'Closed modal sends no polling traffic');

    h = harness(); h.call("request('load')");
    h.requests[0].reject(new Error('offline')); await settle();
    ck(h.remaining() === 60000, 'Transient initial load failure retries without user action');
    await h.advance(60000); ck(h.requests[1].action === 'load', 'Failed load retries load rather than an unknown invoice');
    h.requests[1].reject(new Error('offline')); await settle(); ck(h.remaining() === 120000, 'Repeated failures increase the retry interval');
    await h.advance(120000); await h.answer('pending', 503, { ok: false });
    ck(h.remaining() === 240000, 'Server error uses exponential backoff');
    await h.advance(240000); await h.answer('pending', 429, { ok: false });
    ck(h.remaining() === 300000, 'Rate-limit retry is capped at five minutes');
    await h.advance(300000); await h.answer(); ck(h.remaining() === 2000, 'Successful load recovers the normal monitoring schedule');
    await h.advance(2000); await h.answer('pending', 403, { ok: false });
    ck(h.timers.size === 0, 'Expired CSRF/session stops retries until the page is refreshed');

    h = harness(); h.call("request('load')"); await h.answer(); await h.advance(2000);
    await h.advance(20000); ck(!h.context.busy && h.remaining() === 60000, 'Stalled fetch is aborted and automatically retried');
    await h.advance(60000); await h.answer('pending', 200, { scan_ok: false });
    ck(h.remaining() === 120000, 'Blockchain-provider failures back off even when JSON transport succeeds');
    await h.advance(120000); await h.answer('review'); ck(h.timers.size === 0, 'Manual review pauses automatic blockchain polling');

    h = harness(); h.call("request('load')"); const stale = h.requests[0];
    h.context.sequence++; h.context.controller.abort(); h.context.busy = false; await settle();
    h.call("request('load')"); await h.answer('paid');
    stale.resolve({ ok: true, json: async () => ({ ok: true, pending: true, request: { status: 'pending' } }) }); await settle();
    ck(h.context.state.request.status === 'paid' && h.timers.size === 0, 'A late response from a closed modal cannot restart monitoring');
    // Exercise the actual local countdown without fetch or database stubs.
    const clock = { textContent: '' }, intervals = new Map();
    let localNow = Date.parse('2026-10-18T10:00:00Z'), visible = true, intervalId = 0;
    let deadlineNotices = 0;
    const c = { showPaymentWindowEnded: () => { deadlineNotices++; }, countdownTimer: null, serverClockOffset: 300000,
        state: { pending: true, request: { pay_before: '2026-10-18 10:35:00' } },
        invoice: { hidden: false }, document: { hidden: false }, find: () => clock,
        modal: { classList: { contains: () => visible } },
        Date: { parse: Date.parse, now: () => localNow },
        clearInterval: id => intervals.delete(id),
        setInterval: fn => { intervals.set(++intervalId, fn); return intervalId; }
    };
    vm.createContext(c);
    vm.runInContext(source.slice(source.indexOf('    const countdownText ='), source.indexOf('    const showNotice =')), c);
    const sync = () => vm.runInContext('syncCountdown()', c);
    visible = false; sync();
    ck(clock.textContent === '30:00' && intervals.size === 0, 'Fast response paints countdown before the modal opening animation completes');
    const modalEvents = {}; let pollResumed = 0;
    c.modal.addEventListener = (name, fn) => { modalEvents[name] = fn; };
    c.schedulePoll = () => { pollResumed++; };
    const shownStart = source.indexOf("    modal.addEventListener('shown.bs.modal'");
    vm.runInContext(source.slice(shownStart, source.indexOf('\n', shownStart)), c);
    visible = true; modalEvents['shown.bs.modal']();
    ck(intervals.size === 1 && pollResumed === 1, 'Opening completion starts countdown and automatic polling without manual refresh');
    sync(); ck(clock.textContent === '30:00' && intervals.size === 1, 'Countdown uses server-clock offset and starts at thirty minutes');
    localNow += 1000; [...intervals.values()][0](); ck(clock.textContent === '29:59', 'Countdown ticks locally once per second');
    sync(); ck(intervals.size === 1, 'Refreshing invoice state never duplicates countdown intervals');
    c.document.hidden = true; sync(); ck(intervals.size === 0, 'Hidden tab stops its countdown interval');
    localNow += 17000; c.document.hidden = false; sync(); ck(clock.textContent === '29:42', 'Visible tab recalculates from the absolute deadline without timer drift');
    localNow = Date.parse('2026-10-18T10:30:00Z'); [...intervals.values()][0]();
    ck(clock.textContent === '00:00' && intervals.size === 0, 'Countdown stops at zero and never becomes negative');
    ck(deadlineNotices === 1, 'Reaching zero immediately shows the confirmation-waiting notice without an HTTP refresh');
    localNow += 30000; sync(); ck(clock.textContent === '00:00' && intervals.size === 0, 'Expired deadline stays at zero without extra timers');
    c.state.request.pay_before = '2026-10-18 11:00:00'; sync(); ck(intervals.size === 1, 'A restored invoice can restart its countdown');
    visible = false; sync(); ck(intervals.size === 0, 'Closed modal stops the local countdown');
    visible = true; c.state.pending = false; sync(); ck(intervals.size === 0, 'Confirmed payment stops the local countdown');
    c.state.pending = true; c.invoice.hidden = true; sync(); ck(intervals.size === 0, 'Review state has no countdown interval');
    ck(vm.runInContext("countdownText(NaN, Date.now())", c) === '00:00', 'Invalid deadline fails closed to zero');
    ck(vm.runInContext('countdownText(Date.now()+172800000, Date.now())', c) === '48:00:00', 'Long top-up deadlines use readable hours instead of thousands of minutes');
    const expiredNodes = { 'payment-help': { replaceChildren: (...nodes) => { expiredNodes.help = nodes.map(n => n.textContent); } }, 'network-reminder': { hidden: false } };
    const expiredNotices = [];
    const expiryContext = { state: { pending: true, scan_ok: true }, invoice: { hidden: false },
        labels: { confirming: 'Checking sent payment', confirming_help: 'Wait; do not pay again', scan_confirm: 'Automatic confirmation' },
        find: key => expiredNodes[key], text: (tag, value) => ({ textContent: value }), showNotice: (...args) => expiredNotices.push(args) };
    vm.createContext(expiryContext);
    vm.runInContext(source.slice(source.indexOf('    const showPaymentWindowEnded ='), source.indexOf('    const coinLogo =')), expiryContext);
    vm.runInContext('showPaymentWindowEnded()', expiryContext);
    ck(expiredNotices[0][0] === 'Checking sent payment' && expiredNotices[0][2] === true, 'Expired window has a clear active-checking status and waiting icon');
    ck(expiredNodes.help[0] === 'Wait; do not pay again' && expiredNodes['network-reminder'].hidden, 'Expiry replaces send instructions and prevents prompting another transfer');
    ck(expiryContext.state.pending, 'Local countdown expiry never cancels server payment monitoring');
    expiryContext.state.pending = false; vm.runInContext('showPaymentWindowEnded()', expiryContext);
    ck(expiredNotices.length === 1, 'Expiry notice cannot overwrite payment success');
    console.log(`PASS ${assertions} automatic panel-payment polling, countdown, backoff, visibility and race assertions`);
})().catch(error => { console.error(error); process.exitCode = 1; });
