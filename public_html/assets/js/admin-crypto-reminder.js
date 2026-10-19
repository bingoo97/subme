(() => {
    'use strict';
    const init = () => {
        const panel = document.querySelector('[data-crypto-quick]');
        const dialog = document.querySelector('[data-crypto-reminder]');
        if (!panel || !dialog || dialog.dataset.enabled === '0' || typeof dialog.showModal !== 'function') return;
        // Keep it independent of responsive topbar wrappers and their visibility/overflow.
        document.body.appendChild(dialog);
        const interval = 30 * 60 * 1000;
        const key = `subme-crypto-reminder:${location.pathname}:${panel.dataset.adminId}:${interval}`;
        let fallback = Date.now() + interval, busy = false, stopped = false;
        const read = () => {
            try { const value = Number(localStorage.getItem(key)); if (value > 0) return value; } catch (_) {}
            return fallback;
        };
        const postpone = () => {
            fallback = Date.now() + interval;
            try { localStorage.setItem(key, String(fallback)); } catch (_) {}
        };
        // Establish the first deadline once; navigation must not restart it.
        try { if (!localStorage.getItem(key)) postpone(); } catch (_) {}
        const payment = dialog.querySelector('[data-reminder-payment]');
        const primary = dialog.querySelector('[data-reminder-primary]');
        const error = dialog.querySelector('[data-reminder-error]');
        const receipt = dialog.querySelector('[data-reminder-receipt]');
        const syncReceipt = () => {
            if (!receipt) return;
            const detected = dialog.dataset.tone === 'new' && payment.querySelector('.admin-crypto-quick__detected');
            const approved = dialog.dataset.tone === 'payout';
            receipt.hidden = !detected && !approved;
            const row = payment.querySelector('article');
            const summary = row?.dataset.receiptMessage;
            const title = receipt.querySelector('[data-reminder-receipt-title]');
            const purpose = receipt.querySelector('[data-reminder-receipt-purpose]');
            if (title && purpose) {
                title.hidden = purpose.hidden = !approved;
                title.textContent = row?.dataset.receiptTitle || '';
                purpose.textContent = row?.dataset.receiptPurpose || '';
                purpose.classList.toggle('admin-payment-topup-badge', row?.dataset.paymentKind === 'topup');
                const orderId = row?.dataset.receiptOrderId || '';
                if (approved && /^[1-9]\d*$/.test(orderId)) {
                    purpose.setAttribute('href', `/admin/?page=orders&order_id=${encodeURIComponent(orderId)}`);
                } else {
                    purpose.removeAttribute('href');
                }
            }
            receipt.querySelector('[data-reminder-receipt-text]').textContent = approved ? (title && row?.dataset.receiptDetails ? row.dataset.receiptDetails : summary || receipt.dataset.approvedLabel) : receipt.dataset.detectedLabel;
        };
        const closeMenus = () => {
            dialog.querySelectorAll('[data-crypto-payment-actions]').forEach(menu => { menu.hidden = true; });
            dialog.querySelectorAll('[data-crypto-payment-more]').forEach(button => button.setAttribute('aria-expanded', 'false'));
        };
        const dismiss = () => { closeMenus(); if (dialog.open) dialog.close(); postpone(); };
        // Bootstrap can start opening before .show is added (backdrop animation).
        // Give every other modal priority, including subscription/acceptance dialogs.
        document.addEventListener('show.bs.modal', () => {
            if (dialog.open) dismiss();
        }, true);
        const yieldToDialog = event => {
            if (event.target !== dialog && event.target?.tagName === 'DIALOG' &&
                (event.newState === 'open' || event.target.open) && dialog.open) dismiss();
        };
        document.addEventListener('beforetoggle', yieldToDialog, true);
        document.addEventListener('toggle', yieldToDialog, true);
        dialog.addEventListener('close', () => { closeMenus(); postpone(); });
        dialog.addEventListener('cancel', postpone);
        dialog.addEventListener('scroll', closeMenus, { passive: true });
        window.addEventListener('resize', closeMenus);
        dialog.querySelectorAll('[data-reminder-close]').forEach(button => button.addEventListener('click', dismiss));
        // Close the native dialog before the existing Bootstrap acceptance modal opens.
        dialog.addEventListener('click', event => {
            if (event.target.closest('[data-crypto-quick-accept]')) dismiss();
            const button = event.target.closest('[data-crypto-payment-more]');
            if (!button) {
                if (!event.target.closest('[data-crypto-payment-actions]')) closeMenus();
                return;
            }
            const menu = button.closest('article').querySelector('[data-crypto-payment-actions]');
            const open = menu.hidden;
            closeMenus();
            if (!open) return;
            menu.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            const box = dialog.getBoundingClientRect(), anchor = button.getBoundingClientRect();
            const width = Math.min(245, box.width - 32);
            menu.style.width = `${width}px`;
            menu.style.maxHeight = `${Math.min(innerHeight - 48, box.height - 24)}px`;
            const height = menu.getBoundingClientRect().height;
            menu.style.left = `${Math.max(box.left + 12, Math.min(anchor.right - width, box.right - width - 12))}px`;
            menu.style.top = `${Math.max(box.top + 12, Math.min(anchor.bottom + 6, box.bottom - height - 12))}px`;
        });
        primary.addEventListener('click', event => {
            const accept = payment.querySelector('[data-crypto-quick-accept]');
            if (primary.dataset.single === '1' && accept) { event.preventDefault(); accept.click(); }
        });
        const updatePayment = view => {
            if (!view || view.filter !== dialog.dataset.tone || !view.counts || typeof view.html !== 'string') return false;
            if (Number(view.counts[view.filter]) === 0) { dismiss(); return true; }
            closeMenus();
            payment.innerHTML = view.html.replace(/(id|aria-controls)="cryptoQuickActions/g, '$1="cryptoReminderActions');
            // Keep the focused reminder compact: the next most recent payment replaces the completed one.
            payment.querySelectorAll('article').forEach((row, index) => { if (index > 0) row.remove(); });
            primary.dataset.single = view.filter === 'new' && Number(view.counts.new) === 1 && payment.querySelector('[data-crypto-quick-accept]') ? '1' : '0';
            primary.textContent = primary.dataset.single === '1' ? primary.dataset.singleLabel : primary.dataset.listLabel;
            syncReceipt();
            return true;
        };
        const renderSnapshot = data => {
            if (!data || !data.ok) return false;
            if (!data.filter) { dismiss(); return true; }
            if (!['new','expired','payout'].includes(data.filter) || !data.copy || !data.html) return false;
            dialog.dataset.tone = data.filter;
            dialog.dataset.workflow = data.workflow || 'payment';
            dialog.querySelector('[data-reminder-title]').textContent = data.copy.title;
            dialog.querySelector('[data-reminder-text]').textContent = data.copy.text;
            dialog.querySelector('[data-reminder-icon]').className = `bi ${data.workflow === 'order' ? 'bi-box-seam' : data.filter === 'new' ? 'bi-wallet2' : data.filter === 'expired' ? 'bi-exclamation-circle' : 'bi-wallet-fill'}`;
            payment.innerHTML = data.html;
            syncReceipt();
            const single = data.filter === 'new' && Number(data.counts.new) === 1 && payment.querySelector('[data-crypto-quick-accept]');
            primary.dataset.single = single ? '1' : '0';
            primary.dataset.listLabel = data.copy.action;
            primary.textContent = single ? primary.dataset.singleLabel : data.copy.action;
            const scope = { new: 'open', expired: 'expired', payout: 'approved' }[data.filter];
            primary.href = data.order_url || `/admin/?page=payments&payment_type_filter=crypto&payment_scope=${scope}`;
            error.hidden = true;
            document.dispatchEvent(new CustomEvent('admin:crypto-quick-updated', { detail: { counts: data.counts } }));
            return true;
        };
        // Reuse the existing protected POST action and update the flyout on success.
        dialog.addEventListener('submit', async event => {
            const form = event.target.closest('[data-crypto-quick-action]');
            if (!form) return;
            event.preventDefault();
            if (busy) return;
            busy = true;
            closeMenus();
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            error.hidden = true;
            const request = new AbortController(), timeout = setTimeout(() => request.abort(), 20000);
            try {
                const body = new FormData(form);
                body.delete('admin_payment_quick_action');
                body.set('admin_payment_quick_action_ajax', '1');
                body.set('crypto_quick_inline', '1');
                body.set('crypto_reminder_inline', '1');
                body.set('crypto_quick_filter', dialog.dataset.tone);
                const response = await fetch(form.getAttribute('action') || '/admin/?page=payments', { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: request.signal });
                const data = await response.json().catch(() => null);
                if (!data || typeof data !== 'object') throw new Error(panel.dataset.actionError || panel.dataset.error);
                if (!response.ok || !data.ok) throw new Error(data.message || panel.dataset.error);
                document.dispatchEvent(new CustomEvent('admin:crypto-quick-updated', { detail: data.crypto_quick }));
                // The admin may have closed this reminder, or another modal may have taken priority.
                if (!dialog.open) return;
                if (!(data.crypto_reminder && renderSnapshot(data.crypto_reminder)) && !updatePayment(data.crypto_quick)) {
                    // The write succeeded: retry only a read, never the payment action itself.
                    const refresh = await fetch(`${panel.dataset.url}?filter=${encodeURIComponent(dialog.dataset.tone)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: request.signal });
                    const view = await refresh.json();
                    if (!refresh.ok || !view.ok) throw new Error(panel.dataset.error);
                    document.dispatchEvent(new CustomEvent('admin:crypto-quick-updated', { detail: view }));
                    if (!dialog.open) return;
                    if (!updatePayment(view)) throw new Error(panel.dataset.error);
                }
                if (dialog.open) {
                    error.textContent = data.message;
                    error.dataset.tone = 'success';
                    error.hidden = !data.message;
                    (payment.querySelector('[data-crypto-payment-more]') || primary).focus();
                }
            } catch (failure) {
                error.textContent = failure.name === 'Error' ? (failure.message || panel.dataset.error) : (panel.dataset.actionError || panel.dataset.error);
                error.dataset.tone = 'error';
                error.hidden = false;
            } finally { clearTimeout(timeout); button.disabled = false; busy = false; }
        });
        const blocked = (allowReminder = false) => document.hidden || !navigator.onLine || (!allowReminder && dialog.open) ||
            document.querySelector('.modal.show, .modal[aria-modal="true"], .modal-backdrop, body.modal-open, dialog[open]:not([data-crypto-reminder]), [data-admin-topbar-flyout]:not([hidden])') ||
            document.activeElement?.matches('input,textarea,select,[contenteditable="true"]');
        let nextRefresh = 0;
        const check = async () => {
            const refreshing = dialog.open;
            if (refreshing) {
                postpone();
                if (stopped || busy || Date.now() < nextRefresh || blocked(true) || dialog.querySelector('[data-crypto-payment-more][aria-expanded="true"]')) return;
            } else if (stopped || busy || Date.now() < read() || blocked()) return;
            // Claim this interval across tabs before any asynchronous request.
            postpone();
            nextRefresh = Date.now() + 60000;
            busy = true;
            const request = new AbortController(), timeout = setTimeout(() => request.abort(), 12000);
            try {
                const response = await fetch(`${panel.dataset.url}?reminder=1`, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: request.signal });
                if (response.status === 401 || response.status === 403) stopped = true;
                if (!response.ok) return;
                const data = await response.json();
                if (!data.ok || blocked(refreshing) || (refreshing && !dialog.open)) return;
                if (!data.filter) { if (refreshing) dismiss(); return; }
                if (!['new', 'expired', 'payout'].includes(data.filter) || !data.html || !data.copy) return;
                renderSnapshot(data);
                if (!dialog.open) dialog.showModal();
            } catch (_) { /* Retry at the next interval; never interrupt the admin on network failure. */ }
            finally { clearTimeout(timeout); busy = false; }
        };
        window.setInterval(check, 15000);
        document.addEventListener('visibilitychange', check);
        window.addEventListener('focus', check);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
