# Cryptocurrency selection scroll and payment button

Completed 2026-10-20. Runtime `48e1931`; baseline `98fca87`.

- Installed exactly two committed files on demo.subme.pro and dashboard.subme.pro: customer checkout template and customer-polish.css. Previous and new SHA-256 hashes verified.
- Clicking a crypto card, including the selected card, reveals the payment button with coalesced animation frames and reduced-motion support. Button text is 16px and weight 700; existing yellow styling remains.
- Local: 67 complete page renders passed without PHP warnings. Phone 390x844 and desktop 1440x900 checked on an isolated rendered fixture; no payment generated.
- Each target: template compiled; new source and style probes passed; authenticated admin pages, notifications, crypto quick categories, reminder state, search and profile checks passed. Seventeen public asset hashes matched. Anonymous endpoint/CSRF and 30-day host-only session checks passed.
- External root/admin HTTP 200 and released CSS hash verified on both domains.
- Separate private prior-file backups and consistent database snapshots retained under .subme-deploy-backups/crypto-scroll-20261020-01/<site>; COMPLETE markers and gzip integrity verified.
- No migration, SQL import, local data transfer or financial test mutation. Separate database identities, application/provider/panel settings, protected config files and CRON preserved. Provider switches remain OFF.
- No error_log files changed after completion. Temporary uploaded archive removed; SSH control session closed after verification.
