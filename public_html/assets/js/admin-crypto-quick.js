(() => {
    'use strict';
    const init = () => {
        const panel = document.querySelector('[data-crypto-quick]');
        if (!panel) return;
        const root = panel.closest('[data-admin-topbar-flyout-root]');
        const toggle = root.querySelector('[data-admin-topbar-flyout-toggle]');
        const list = panel.querySelector('[data-crypto-quick-list]');
        const refresh = panel.querySelector('[data-crypto-quick-refresh]');
        const close = panel.querySelector('[data-admin-topbar-flyout-close]');
        const filters = [...panel.querySelectorAll('[data-crypto-quick-filter]')];
        const hint = panel.querySelector('[data-crypto-quick-hint]');
        let hints = {};
        try { hints = JSON.parse(hint.dataset.hints || '{}'); } catch (error) {}
        const hintsToggle = panel.querySelector('[data-crypto-quick-hints-toggle]');
        const hintsKey = `subme-admin-crypto-hints:${window.location?.pathname || '/admin/'}:${panel.dataset.adminId || '0'}`;
        let hintsVisible = true;
        // Persist per administrator in this browser; session storage is a fallback.
        for (const name of ['localStorage', 'sessionStorage']) {
            try {
                const stored = window[name].getItem(hintsKey);
                if (stored === '0' || stored === '1') { hintsVisible = stored === '1'; break; }
            } catch (error) { /* Storage can be blocked in private browser contexts. */ }
        }
        const syncHints = () => {
            panel.querySelectorAll('.admin-crypto-quick__hint').forEach(node => {
                node.hidden = !hintsVisible || !hints[filter];
            });
            if (hintsToggle) {
                hintsToggle.checked = hintsVisible;
                hintsToggle.setAttribute('aria-checked', String(hintsVisible));
            }
        };
        if (hintsToggle) hintsToggle.addEventListener('change', () => {
            hintsVisible = hintsToggle.checked;
            syncHints();
            for (const name of ['localStorage', 'sessionStorage']) {
                try { window[name].setItem(hintsKey, hintsVisible ? '1' : '0'); } catch (error) {}
            }
        });
        window.addEventListener('storage', event => {
            if (event.key === hintsKey) {
                hintsVisible = event.newValue !== '0';
                syncHints();
            }
        });
        const mobile = window.matchMedia('(max-width: 767px)');
        const cache = new Map();
        let counts = {}, labels = {};
        try { counts = JSON.parse(panel.dataset.counts || '{}'); labels = JSON.parse(panel.dataset.labels || '{}'); } catch (error) {}
        const badge = root.querySelector('[data-crypto-quick-badge]');
        const categoryCount = panel.querySelector('[data-crypto-quick-category-count]');
        const updateCategoryCount = () => {
            if (!categoryCount) return;
            const count = Math.max(0, Math.floor(Number(counts[filter]) || 0));
            categoryCount.textContent = String(count);
            categoryCount.dataset.tone = filter;
            categoryCount.setAttribute('aria-label', `${labels[filter] || filter}: ${count}`);
        };
        const priority = () => ['new', 'expired', 'payout', 'cancelled'].find(key => Number(counts[key]) > 0) || 'new';
        const updateBadge = data => {
            counts = Object.fromEntries(['new', 'expired', 'payout', 'cancelled'].map(key => [key, Math.max(0, Math.floor(Number(data[key]) || 0))]));
            updateCategoryCount();
            const key = priority(), count = counts[key];
            if (badge) { badge.hidden = count === 0; badge.textContent = String(count); badge.dataset.tone = key; }
            if (labels.title) {
                const text = count ? `${labels.title}: ${labels[key]} (${count})` : labels.title;
                toggle.setAttribute('aria-label', text); toggle.setAttribute('title', text);
            }
        };
        let filter = priority(), controller = null, revision = 0;
        updateBadge(counts);
        document.addEventListener('admin:crypto-quick-updated', event => {
            const view = event.detail;
            if (!view || !view.counts) return;
            cache.clear();
            updateBadge(view.counts);
            if (!panel.hidden) load(true);
        });
        syncHints();
        const selectFilter = value => {
            filter = value;
            updateCategoryCount();
            filters.forEach(item => item.setAttribute('aria-pressed', String(item.dataset.cryptoQuickFilter === value)));
        };
        const closeActions = () => {
            panel.querySelectorAll('[data-crypto-payment-more]').forEach(button => button.setAttribute('aria-expanded', 'false'));
            panel.querySelectorAll('[data-crypto-payment-actions]').forEach(menu => { menu.hidden = true; });
        };
        const positionActions = (button, menu) => {
            const anchor = button.getBoundingClientRect();
            const bounds = panel.getBoundingClientRect();
            const width = Math.min(245, bounds.width - 24, window.innerWidth - 24);
            menu.style.width = `${Math.max(0, width)}px`;
            const minTop = Math.max(12, bounds.top + 12);
            const maxBottom = Math.min(window.innerHeight - 12, bounds.bottom - 12);
            menu.style.maxHeight = `${Math.max(0, maxBottom - minTop)}px`;
            const height = menu.getBoundingClientRect().height;
            const left = Math.max(bounds.left + 12, Math.min(anchor.right - width, bounds.right - width - 12));
            let top = anchor.bottom + 6;
            if (top + height > maxBottom) top = anchor.top - height - 6;
            top = Math.max(minTop, Math.min(top, maxBottom - height));
            menu.style.left = `${Math.max(12, left)}px`;
            menu.style.top = `${top}px`;
        };
        list.addEventListener('scroll', closeActions, { passive: true });
        window.addEventListener('resize', closeActions);
        panel.addEventListener('click', event => {
            const button = event.target.closest('[data-crypto-payment-more]');
            if (button) {
                const item = button.closest('.admin-crypto-quick__item');
                const menu = item.querySelector('[data-crypto-payment-actions]');
                const open = menu.hidden;
                closeActions(); menu.hidden = !open; button.setAttribute('aria-expanded', String(open));
                if (open) positionActions(button, menu);
            }
        });
        document.addEventListener('click', event => {
            if (!event.target.closest('[data-crypto-payment-more], [data-crypto-payment-actions]')) closeActions();
        });
        let countdownTimer = null;
        const stopCountdown = () => {
            if (countdownTimer !== null) { window.clearInterval(countdownTimer); countdownTimer = null; }
        };
        const updateCountdowns = () => {
            panel.querySelectorAll('[data-crypto-quick-expires]').forEach(node => {
                const remaining = Math.max(0, Math.ceil((Number(node.dataset.cryptoQuickExpires) * 1000 - Date.now()) / 60000));
                node.textContent = remaining > 0 ? node.dataset.remainingLabel.replace('{minutes}', String(remaining)) : node.dataset.expiredLabel;
                const spinner = node.closest('.admin-crypto-quick__identity')?.querySelector('.admin-crypto-quick__waiting');
                if (spinner) spinner.hidden = remaining === 0;
            });
        };
        const startCountdown = () => {
            stopCountdown();
            updateCountdowns();
            if (!panel.hidden && filter === 'new' && panel.querySelectorAll('[data-crypto-quick-expires]').length) {
                countdownTimer = window.setInterval(updateCountdowns, 1000);
            }
        };
        const message = text => { const p = document.createElement('p'); p.className = 'admin-crypto-quick__message'; p.textContent = text; list.replaceChildren(p); };
        const load = async (force = false) => {
            if (panel.hidden) return;
            closeActions();
            stopCountdown();
            const ticket = ++revision;
            if (controller) controller.abort();
            controller = null; if (refresh) refresh.disabled = false; list.setAttribute('aria-busy', 'false');
            const settingsLink = hint.querySelector('[data-crypto-quick-settings-link]');
            if (settingsLink) settingsLink.hidden = filter !== 'new';
            const explanation = hints[filter];
            syncHints();
            if (explanation) {
                hint.dataset.tone = filter;
                hint.querySelector('[data-crypto-quick-hint-title]').textContent = explanation.title;
                hint.querySelector('[data-crypto-quick-hint-text]').textContent = explanation.text;
                hint.querySelector('i').className = `bi ${explanation.icon}`;
            }
            const entry = cache.get(filter);
            if (!force && entry && Date.now() - entry.time < 30000) { list.innerHTML = entry.html; startCountdown(); list.setAttribute('aria-busy', 'false'); if (refresh) refresh.disabled = false; return; }
            if (!navigator.onLine) { message(panel.dataset.error); return; }
            controller = new AbortController(); const request = controller;
            const timeout = setTimeout(() => request.abort(), 12000);
            message(panel.dataset.loading); list.setAttribute('aria-busy', 'true'); if (refresh) refresh.disabled = true;
            try {
                const response = await fetch(`${panel.dataset.url}?filter=${encodeURIComponent(filter)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: request.signal });
                if (!response.ok) throw new Error('Request failed');
                const data = await response.json();
                if (ticket !== revision || panel.hidden) return;
                if (!data.ok || data.filter !== filter || typeof data.html !== 'string') throw new Error('Invalid response');
                if (data.counts && typeof data.counts === 'object') updateBadge(data.counts);
                cache.set(filter, { html: data.html, time: Date.now() }); list.innerHTML = data.html; startCountdown();
            } catch (error) { if (ticket === revision && !panel.hidden) message(panel.dataset.error); }
            finally { clearTimeout(timeout); if (ticket === revision) { controller = null; if (refresh) refresh.disabled = false; list.setAttribute('aria-busy', 'false'); } }
        };
        filters.forEach(button => button.addEventListener('click', () => {
            selectFilter(button.dataset.cryptoQuickFilter);
            load();
        }));
        let renewing = false;
        const renewalResult = panel.querySelector('[data-crypto-quick-result]');
        panel.addEventListener('submit', async event => {
            const form = event.target.closest('[data-crypto-quick-action]');
            if (!form || !panel.contains(form)) return;
            event.preventDefault();
            if (renewing) return;
            renewing = true;
            const button = form.querySelector('button[type="submit"]');
            if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
            if (renewalResult) renewalResult.hidden = true;
            const request = new AbortController();
            const timeout = setTimeout(() => request.abort(), 20000);
            let uncertain = true;
            try {
                const body = new FormData(form);
                body.delete('admin_payment_quick_action');
                body.set('admin_payment_quick_action_ajax', '1');
                const action = body.get('quick_action');
                const nextFilter = action === 'renew' ? 'new' : filter;
                body.set('crypto_quick_inline', '1');
                body.set('crypto_quick_filter', nextFilter);
                const response = await fetch(form.getAttribute('action') || '/admin/?page=payments', {
                    method: 'POST', body, credentials: 'same-origin',
                    headers: { Accept: 'application/json' }, signal: request.signal,
                });
                const data = await response.json().catch(() => null);
                if (!data || typeof data !== 'object') throw new Error(panel.dataset.actionError || panel.dataset.error);
                uncertain = false;
                if (!response.ok || !data.ok) throw new Error(data.message || panel.dataset.error);
                cache.clear();
                if (renewalResult) {
                    renewalResult.textContent = data.message;
                    renewalResult.dataset.tone = 'success';
                    renewalResult.hidden = false;
                }
                if (action === 'renew') selectFilter('new');
                // Invalidate reads started before the mutation completed.
                ++revision;
                if (controller) controller.abort();
                controller = null;
                closeActions();
                const view = data.crypto_quick;
                if (view && view.counts) updateBadge(view.counts);
                if (view && view.filter === filter && typeof view.html === 'string') {
                    cache.set(filter, { html: view.html, time: Date.now() });
                    list.innerHTML = view.html;
                    list.setAttribute('aria-busy', 'false');
                    if (refresh) refresh.disabled = false;
                    syncHints();
                    if (!panel.hidden) startCountdown();
                } else {
                    await load(true);
                }
            } catch (error) {
                // A write may already have succeeded. Refresh only the read; never repeat the POST.
                if (uncertain) { cache.clear(); await load(true); }
                if (renewalResult) {
                    renewalResult.textContent = uncertain || error.name !== 'Error' ? (panel.dataset.actionError || panel.dataset.error) : (error.message || panel.dataset.error);
                    renewalResult.dataset.tone = 'error';
                    renewalResult.hidden = false;
                }
            } finally {
                clearTimeout(timeout);
                renewing = false;
                if (button) { button.disabled = false; button.removeAttribute('aria-busy'); }
            }
        });
        if (refresh) refresh.addEventListener('click', () => load(true));
        const syncDisplay = () => {
            document.body.classList.toggle('has-crypto-quick-open', !panel.hidden && mobile.matches);
            panel.setAttribute('aria-modal', String(!panel.hidden && mobile.matches));
        };
        let openRefreshTimer = null;
        const sync = () => {
            syncDisplay();
            if (openRefreshTimer !== null) { window.clearInterval(openRefreshTimer); openRefreshTimer = null; }
            if (panel.hidden) { closeActions(); stopCountdown(); ++revision; if (controller) controller.abort(); controller = null; if (refresh) refresh.disabled = false; }
            else {
                selectFilter(priority()); load();
                openRefreshTimer = window.setInterval(() => {
                    if (!document.hidden && navigator.onLine && !controller) load();
                }, 60000);
            }
        };
        new MutationObserver(sync).observe(panel, { attributes: true, attributeFilter: ['hidden'] });
        mobile.addEventListener('change', syncDisplay);
        document.addEventListener('keydown', event => {
            if (panel.hidden) return;
            if (event.key === 'Escape') {
                const openButton = panel.querySelector('[data-crypto-payment-more][aria-expanded="true"]');
                if (openButton) { closeActions(); openButton.focus(); event.preventDefault(); return; }
                close.click(); toggle.focus();
            }
            if (event.key === 'Tab' && mobile.matches) {
                const nodes = [...panel.querySelectorAll('button:not(:disabled),a[href],input:not(:disabled)')].filter(node => node.getClientRects().length);
                const first = nodes[0], last = nodes[nodes.length - 1];
                if (!panel.contains(document.activeElement) || (!event.shiftKey && document.activeElement === last)) { event.preventDefault(); first?.focus(); }
                else if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            }
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
