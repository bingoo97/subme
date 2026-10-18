(() => {
    'use strict';
    const modal = document.querySelector('[data-panel-tiers]');
    if (!modal) return;
    const labels = JSON.parse(modal.dataset.labels || '{}');
    const find = key => modal.querySelector(`[data-panel-${key}]`);
    const form = find('form'), summary = find('summary'), notice = find('notice'), invoice = find('invoice');
    const periodChoices = find('periods'), assetChoices = find('assets');
    const assetInfo = {
        BTC: { name: 'Bitcoin', logo: '/img/crypto/btc.png', network: 'Bitcoin' },
        SOL: { name: 'Solana', logo: '/img/crypto/sol.png', network: 'Solana' },
        DOGE: { name: 'Dogecoin', logo: '/img/crypto/doge.png', network: 'Dogecoin' },
        USDT: { name: 'Tether', logo: '/img/crypto/tether.png', network: 'Ethereum · ERC-20' }
    };
    let busy = false, timer = null, state = null, sequence = 0, controller = null, invoiceDetailsRevision = null, nextPollAt = 0, pollFailures = 0, pollingStopped = false, countdownTimer = null, serverClockOffset = 0;
    const copyTimers = new Map();
    const clearCopyFeedback = () => { copyTimers.forEach(clearTimeout); copyTimers.clear(); };
    const text = (tag, value, className) => {
        const node = document.createElement(tag); node.textContent = value;
        if (tag === 'i') node.setAttribute('aria-hidden', 'true');
        if (className) node.className = className;
        return node;
    };
    const selected = name => form.querySelector(`input[name="${name}"]:checked`)?.value || '';
    const periodLabel = months => labels[Number(months) === 1 ? 'month' : 'quarter'];
    const amount = (value, currency) => new Intl.NumberFormat(document.documentElement.lang || 'pl', { style: 'currency', currency }).format(Number(value));
    const date = value => new Date(value.replace(' ', 'T') + 'Z').toLocaleString();
    const calendarDate = value => new Date(`${value}T12:00:00`).toLocaleDateString(document.documentElement.lang || 'pl');
    const cryptoAmount = (value, code) => Number(value).toFixed({ BTC: 8, SOL: 9, DOGE: 8, USDT: 6 }[code]);
    const countdownText = (deadline, now) => {
        const seconds = Number.isFinite(deadline) ? Math.max(0, Math.ceil((deadline - now) / 1000)) : 0;
        const minutesSeconds = `${String(Math.floor(seconds / 60) % 60).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
        return seconds >= 3600 ? `${String(Math.floor(seconds / 3600)).padStart(2, '0')}:${minutesSeconds}` : minutesSeconds;
    };
    const stopCountdown = () => { clearInterval(countdownTimer); countdownTimer = null; };
    const syncCountdown = () => {
        stopCountdown();
        const clock = find('countdown');
        if (!clock || !state?.pending || invoice.hidden) return;
        const deadline = Date.parse(state.request.pay_before.replace(' ', 'T') + 'Z');
        const update = () => {
            clock.textContent = countdownText(deadline, Date.now() + serverClockOffset);
            if (clock.textContent === '00:00') { stopCountdown(); showPaymentWindowEnded(); }
        };
        update();
        if (clock.textContent !== '00:00' && !document.hidden && modal.classList.contains('show')) countdownTimer = setInterval(update, 1000);
    };
    const showNotice = (value, tone = '', waiting = false) => {
        const slot = find(invoice.hidden ? 'notice-top' : 'notice-payment');
        if (notice.parentElement !== slot) slot.append(notice);
        const icon = tone === 'success' ? 'bi-check-circle-fill' : tone === 'info' ? 'bi-clock' : 'bi-exclamation-circle';
        notice.replaceChildren(text('i', '', waiting ? 'admin-panel-tier-notice__spinner' : `bi ${icon}`), text('span', value));
        notice.className = `admin-panel-tier-notice ${tone}`; notice.hidden = !value;
    };
    const canReturnAfterExpiry = request => {
        const deadline = Date.parse(String(request?.pay_before || '').replace(' ', 'T') + 'Z');
        return !!request?.can_cancel && Number(request.received_crypto || 0) === 0
            && Number.isFinite(deadline) && Date.now() + serverClockOffset >= deadline;
    };
    const showPaymentWindowEnded = () => {
        if (!state?.pending || invoice.hidden) return;
        stopCountdown(); clearCopyFeedback();
        find('payment-body').hidden = true;
        find('payment-head').hidden = true;
        find('qr').replaceChildren();
        find('invoice-details').replaceChildren();
        invoiceDetailsRevision = null;
        find('cancel').hidden = !canReturnAfterExpiry(state.request);
        find('cancel').classList.add('is-expired');
        find('cancel-label').textContent = labels.cancel_expired;
        const ended = find('ended');
        ended.hidden = false;
        ended.replaceChildren(
            text('i', '', 'bi bi-shield-check admin-panel-tier-modal__ended-icon'),
            text('strong', labels.confirming),
            text('p', labels.confirming_help),
            text('small', labels.scan_confirm)
        );
        // Waiting for finality is a separate view, never another invitation to send funds.
        if (state.scan_ok !== false) showNotice('');
    };
    const coinLogo = code => {
        const image = document.createElement('img'); image.src = assetInfo[code].logo;
        image.alt = ''; image.width = 28; image.height = 28; image.decoding = 'async';
        return image;
    };
    const radioCard = (name, value, checked, contents, className) => {
        const label = text('label', '', className);
        const input = document.createElement('input'); input.type = 'radio'; input.name = name; input.value = value; input.checked = checked; input.required = true;
        const inner = text('span', '', `${className}__inner`);
        inner.append(...contents, text('i', '', 'bi bi-check-circle-fill admin-panel-tier-choice-check'));
        label.append(input, inner); return label;
    };
    const detail = (title, value, copyValue = null, className = '') => {
        const row = text('div', '', `admin-panel-tier-modal__detail ${className}`);
        row.append(text('dt', title), text('dd', value));
        if (copyValue !== null) {
            const button = text('button', '', 'btn admin-panel-tier-modal__copy'); button.type = 'button'; button.title = labels.copy; button.setAttribute('aria-label', `${labels.copy}: ${title}`);
            button.append(text('i', '', 'bi bi-copy'));
            button.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(copyValue);
                    if (!modal.classList.contains('show') || !row.isConnected) return;
                    clearTimeout(copyTimers.get(button));
                    button.replaceChildren(text('i', '', 'bi bi-check-lg'), Object.assign(text('span', 'Copied'), { ariaLive: 'polite' }));
                    button.classList.add('is-copied'); row.classList.add('is-copied');
                    button.setAttribute('aria-label', `Copied: ${title}`);
                    const feedbackTimer = setTimeout(() => {
                        button.replaceChildren(text('i', '', 'bi bi-copy'));
                        button.classList.remove('is-copied'); row.classList.remove('is-copied');
                        button.setAttribute('aria-label', `${labels.copy}: ${title}`); copyTimers.delete(button);
                    }, 5000);
                    copyTimers.set(button, feedbackTimer);
                }
                catch (_) { showNotice(labels.copy_error || labels.error, 'warning'); }
            });
            row.append(button);
        }
        return row;
    };
    const renderSummary = () => {
        if (!state) return;
        const p = state.policy, r = state.request;
        const receipt = r && (state.pending || r.status === 'paid');
        const months = receipt ? Number(r.months) : Number(selected('months') || p.allowed_months[0]);
        const cost = receipt ? r.amount_fiat : Number(p.price) * months;
        const tier = receipt ? r.tier : p.tier, currency = receipt ? r.currency_code : p.currency;
        const identity = text('div', '', 'admin-panel-tier-modal__summary-copy');
        identity.append(text('strong', `Tier ${tier}`), text('span', `${labels.usage}: ${p.usage}${p.limit === null ? '' : ' / ' + p.limit}`));
        const total = text('div', '', 'admin-panel-tier-modal__summary-total');
        total.append(text('strong', amount(cost, currency)), text('span', periodLabel(months)));
        summary.replaceChildren(identity, total);
    };
    const render = next => {
        state = next; find('retry').hidden = true;
        serverClockOffset = Number.isFinite(next.server_time_ms) ? next.server_time_ms - Date.now() : 0;
        const p = next.policy, r = next.request, pending = next.pending;
        const previousPeriod = selected('months'), previousAsset = selected('asset');
        const chosenPeriod = p.allowed_months.map(String).includes(previousPeriod) ? previousPeriod : String(p.allowed_months[0]);
        periodChoices.replaceChildren(...p.allowed_months.map(n => radioCard('months', n, String(n) === chosenPeriod, [text('strong', periodLabel(n)), text('span', amount(Number(p.price) * n, p.currency))], 'admin-panel-tier-period')));
        const assets = next.assets.filter(a => assetInfo[a.code]);
        const chosenAsset = assets.some(a => a.code === previousAsset) ? previousAsset : assets[0]?.code;
        assetChoices.replaceChildren(...assets.map(a => {
            const identity = text('span', '', 'admin-panel-tier-asset__identity');
            identity.append(text('strong', assetInfo[a.code].name), text('small', `${a.code} · ${a.label}`));
            return radioCard('asset', a.code, a.code === chosenAsset, [coinLogo(a.code), identity], 'admin-panel-tier-asset');
        }));
        renderSummary();
        form.hidden = pending || !p.can_renew || (r && r.status === 'cancelled_review');
        invoice.hidden = !pending || r.status === 'review';
        find('refresh').hidden = !r || r.status !== 'paid';
        if (pending) {
            showNotice(r.status === 'review' ? `${labels.review}. ${labels.review_help}` : labels[r.status] || labels.pending, ['review', 'partial'].includes(r.status) ? 'warning' : 'info', ['pending', 'confirming'].includes(r.status));
            if (!invoice.hidden) {
                const windowEnded = Date.parse(r.pay_before.replace(' ', 'T') + 'Z') <= Date.now() + serverClockOffset;
                find('ended').hidden = !windowEnded;
                find('payment-body').hidden = windowEnded;
                find('payment-head').hidden = windowEnded;
                if (windowEnded) {
                    showPaymentWindowEnded();
                } else {
                find('payment-help').replaceChildren(text('span', labels[r.status === 'partial' ? 'partial_help' : r.status === 'confirming' ? 'confirming_help' : 'scan_help']), text('span', labels.scan_confirm));
                find('network-reminder').hidden = false;
                const reminder = text('strong', `${labels.send_network} `);
                reminder.append(text('span', assetInfo[r.asset_code].network));
                find('network-reminder').replaceChildren(reminder);
                const head = find('payment-head');
                const networkBadge = text('span', `${labels.network}: `);
                networkBadge.append(text('strong', assetInfo[r.asset_code].network));
                head.replaceChildren(coinLogo(r.asset_code), text('strong', assetInfo[r.asset_code].name), networkBadge);
                const due = r.remaining_crypto ?? cryptoAmount(Math.max(0, Number(r.amount_crypto) - Number(r.received_crypto || 0)), r.asset_code);
                const details = find('invoice-details');
                const revision = JSON.stringify([r.id, r.status, due, r.received_crypto, r.wallet_address, r.pay_before, r.exchange_rate]);
                if (revision !== invoiceDetailsRevision) {
                invoiceDetailsRevision = revision; clearCopyFeedback();
                const deadlineRow = detail(labels.until, date(r.pay_before), null, 'is-deadline');
                const clock = text('span', '', 'admin-panel-tier-modal__countdown');
                clock.dataset.panelCountdown = ''; clock.setAttribute('role', 'timer'); clock.setAttribute('aria-live', 'off'); clock.setAttribute('aria-label', labels.time_left);
                deadlineRow.append(clock);
                details.replaceChildren(detail(r.status === 'partial' ? labels.remaining : labels.amount, `${due} ${r.asset_code}`, due, 'is-amount'), detail(labels.address, r.wallet_address, r.wallet_address, 'is-address'), deadlineRow, detail(labels.rate_label, `1 ${r.asset_code} = ${amount(r.exchange_rate, r.currency_code)}`));
                if (Number(r.received_crypto) > 0) details.append(detail(labels.received, `${cryptoAmount(r.received_crypto, r.asset_code)} ${r.asset_code}`));
                }
                find('cancel').hidden = !r.can_cancel;
                find('cancel').classList.remove('is-expired');
                find('cancel-label').textContent = labels.cancel;
                const qr = qrcode(0, 'M'); qr.addData(r.wallet_address); qr.make();
                find('qr').innerHTML = qr.createSvgTag({ cellSize: 4, margin: 16, scalable: true });
                find('qr').querySelector('svg').setAttribute('aria-label', labels.address);
                find('qr').querySelector('svg').setAttribute('role', 'img');
                }
            }
        } else if (r && r.status === 'paid') showNotice(`${labels.paid} ${labels.access_until}: ${calendarDate(r.new_paid_until)}.`, 'success');
        else if (!p.can_renew) showNotice(labels.too_early, 'info');
        else if (!assets.length) showNotice(labels.unavailable, 'warning');
        else if (p.near_limit) showNotice(labels.near, 'info');
        else if (r && r.status === 'cancelled_review') showNotice(`${labels.cancelled_review}. ${labels.review_help}`, 'warning');
        else if (r && r.status === 'cancelled') showNotice(labels.cancelled, 'info');
        else if (r && r.status === 'expired') showNotice(labels.expired, 'warning');
        else showNotice('');
        if (next.scan_ok === false) showNotice(labels.error, 'warning');
        syncCountdown();
    };
    const canAutoCheck = () => !pollingStopped && (!state || (state.pending && state.request.status !== 'review'));
    const schedulePoll = () => {
        clearTimeout(timer); timer = null;
        if (!canAutoCheck() || !modal.classList.contains('show') || document.hidden || navigator.onLine === false) return;
        timer = setTimeout(() => {
            timer = null;
            if (!document.hidden && navigator.onLine !== false && modal.classList.contains('show') && canAutoCheck()) request(state ? 'check' : 'load');
        }, Math.max(2000, nextPollAt - Date.now()));
    };
    const toggleBusy = value => {
        form.querySelectorAll('fieldset').forEach(fieldset => { fieldset.disabled = value; });
        find('generate').disabled = value || !state || !state.policy.can_renew || !state.assets.some(a => assetInfo[a.code]);
        find('check').disabled = value; find('retry').disabled = value; find('cancel').disabled = value;
    };
    const scrollToNotice = () => {
        const body = modal.querySelector('.modal-body');
        const top = body.scrollTop + notice.getBoundingClientRect().top - body.getBoundingClientRect().top - 12;
        body.scrollTo({ top: Math.max(0, top), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    };
    const request = async (action, values = {}, scrollStatus = false) => {
        if (busy) return;
        busy = true;
        let succeeded = false, retryable = true;
        const version = ++sequence;
        const activeController = new AbortController(); controller = activeController;
        const timeout = setTimeout(() => activeController.abort(), 20000);
        toggleBusy(true);
        try {
            const body = new URLSearchParams({ action, _csrf: modal.dataset.csrf, ...values });
            const response = await fetch('/admin/panel_subscription.php', { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json' }, body, signal: activeController.signal });
            const payload = await response.json();
            if (version !== sequence) return;
            if (!response.ok || !payload.ok) {
                retryable = ![400, 401, 403, 404, 405, 413, 422].includes(response.status);
                throw new Error(payload.message || labels.error);
            }
            succeeded = payload.scan_ok !== false;
            render(payload);
            if (scrollStatus) scrollToNotice();
        } catch (error) {
            if (version === sequence) { if (scrollStatus) scrollToNotice(); showNotice(error.name === 'AbortError' ? labels.error : error.message || labels.error, 'warning'); find('retry').hidden = !!state; }
        } finally {
            clearTimeout(timeout);
            if (version === sequence) {
                busy = false; controller = null; toggleBusy(false); clearTimeout(timer);
                pollingStopped = !retryable;
                pollFailures = succeeded ? 0 : Math.min(pollFailures + 1, 4);
                const delay = succeeded ? (action === 'load' ? 2000 : 60000) : Math.min(300000, 60000 * 2 ** (pollFailures - 1));
                nextPollAt = Date.now() + delay;
                schedulePoll();
            }
        }
    };
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href="#adminPanelTierPaymentModal"]');
        if (!link) return;
        event.preventDefault(); event.stopPropagation();
        const reminder = document.getElementById('adminPanelSubscriptionReminderModal');
        const open = () => bootstrap.Modal.getOrCreateInstance(modal).show();
        if (reminder?.classList.contains('show')) { reminder.addEventListener('hidden.bs.modal', open, { once: true }); bootstrap.Modal.getOrCreateInstance(reminder).hide(); }
        else open();
    });
    modal.addEventListener('show.bs.modal', () => {
        sequence++; controller?.abort(); controller = null; busy = false; clearTimeout(timer);
        stopCountdown(); state = null; nextPollAt = 0; pollFailures = 0; pollingStopped = false; invoiceDetailsRevision = null; form.hidden = true; invoice.hidden = true; summary.replaceChildren(); find('refresh').hidden = true;
        showNotice(labels.checking, 'info', true); request('load');
    });
    modal.addEventListener('shown.bs.modal', () => { schedulePoll(); syncCountdown(); });
    modal.addEventListener('hidden.bs.modal', () => { stopCountdown(); clearCopyFeedback(); sequence++; controller?.abort(); controller = null; busy = false; clearTimeout(timer); });
    document.addEventListener('visibilitychange', () => { schedulePoll(); syncCountdown(); });
    window.addEventListener('online', schedulePoll);
    window.addEventListener('offline', () => { clearTimeout(timer); timer = null; });
    form.addEventListener('change', renderSummary);
    form.addEventListener('submit', event => { event.preventDefault(); request('create', { months: selected('months'), asset: selected('asset') }); });
    find('cancel').addEventListener('click', () => request('cancel', { request_id: String(state?.request?.id || '') }));
    find('check').addEventListener('click', () => request('check', {}, true));
    find('retry').addEventListener('click', () => request('load'));
})();
