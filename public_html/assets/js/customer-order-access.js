(() => {
    'use strict';
    const resetTimers = new WeakMap();
    const fallbackCopy = value => {
        const field = document.createElement('textarea');
        field.value = value;
        field.readOnly = true;
        field.style.cssText = 'position:fixed;left:-9999px;top:0;';
        document.body.appendChild(field);
        try {
            field.select();
            if (!document.execCommand('copy')) throw new Error('Clipboard unavailable');
        } finally { field.remove(); }
    };
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-order-access-copy]');
        if (!button || button.disabled) return;
        const input = document.getElementById(button.dataset.orderAccessCopy);
        if (!input || !input.closest('.user-order-modal')) return;
        const label = button.querySelector('[data-order-access-copy-label]');
        const icon = button.querySelector('i');
        if (!label || !icon) return;
        clearTimeout(resetTimers.get(button));
        button.disabled = true;
        try {
            try {
                if (!navigator.clipboard || !navigator.clipboard.writeText) throw new Error('Use fallback');
                await navigator.clipboard.writeText(input.value);
            } catch (_) { fallbackCopy(input.value); }
            button.classList.remove('is-error');
            button.classList.add('is-copied');
            icon.className = 'fa fa-check';
            label.textContent = button.dataset.copiedLabel;
            button.removeAttribute('title');
        } catch (_) {
            button.classList.remove('is-copied');
            button.classList.add('is-error');
            icon.className = 'fa fa-exclamation';
            label.textContent = button.dataset.copyLabel;
            button.title = button.dataset.copyErrorLabel;
            // Select the readonly value for manual copying if browser permissions deny both paths.
            input.focus({ preventScroll: true });
            input.select();
        } finally {
            button.disabled = false;
            if (!button.classList.contains('is-error')) button.focus({ preventScroll: true });
            resetTimers.set(button, setTimeout(() => {
                button.classList.remove('is-copied', 'is-error');
                icon.className = 'fa fa-clone';
                label.textContent = button.dataset.copyLabel;
                button.removeAttribute('title');
            }, 5000));
        }
    });
})();
