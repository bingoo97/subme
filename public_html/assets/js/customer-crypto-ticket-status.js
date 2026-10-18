(() => {
    'use strict';
    const ticket = document.querySelector('[data-customer-crypto-ticket]');
    if (!ticket) return;
    let busy = false, stopped = false;
    const check = async () => {
        if (busy || stopped || document.hidden || !navigator.onLine) return;
        busy = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch('/check_payment.php?ticket_status=' + encodeURIComponent(ticket.dataset.customerCryptoTicket), { credentials: 'same-origin', signal: controller.signal, headers: { Accept: 'application/json' } });
            if ([401, 403, 404].includes(response.status)) { stopped = true; clearInterval(timer); return; }
            if (!response.ok) return;
            const data = await response.json();
            if (data.ok && typeof data.status === 'string' && (data.status !== ticket.dataset.ticketStatus || String(Number(!!data.detected)) !== ticket.dataset.ticketDetected)) {
                stopped = true; clearInterval(timer); window.location.reload();
            }
        } catch (error) { /* Keep the displayed ticket and retry on the next bounded interval. */ }
        finally { clearTimeout(timeout); busy = false; }
    };
    const timer = setInterval(check, 30000);
    window.addEventListener('pagehide', () => { stopped = true; clearInterval(timer); });
})();
