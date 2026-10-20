# Compact checkout summary and cancellation notice

Completed: 2026-10-20T09:52:41.935320+02:00.
Runtime: `98fca87`; baseline: `c17f273`.

- Deployed exactly five committed runtime files to demo.subme.pro, then dashboard.subme.pro: checkout controller/template, PL/EN locale files and customer-polish.css. Each previous and installed file hash matched the expected manifest.
- Compact order selection summary; currency symbols formatted by the shared formatter. Crypto-only option has a reduced-motion-aware pulse and bold red availability text. Available balance payment has green action/text and wallet icon, and suppresses the crypto-only notice.
- Cancellation fallback leaves notice rendering to its template so it appears once.
- Local validation: 67 complete page renders without PHP warnings, including actual disposable-order cancellation in redirect and already-sent-header paths plus redirect destination notice. Three additional isolated rendered fixtures verified crypto-only, crypto+balance and balance-only notice conditions and euro symbol. Mobile/desktop widths 320/390/768/1440 checked during implementation; screenshots retained in the task visualization folder.
- No production order cancellation or new payment generation performed. No SQL migration/import/restore, no copying local database, settings, credentials, sessions, uploads or vendor files.
- Both targets retained distinct database identities. Application/provider/panel configuration fingerprints verified unchanged; provider switches remain OFF. Protected mysql/environment/pointer/.htaccess hashes and CRON unchanged.
- Separate previous-file backups and consistent SQL snapshots retained privately on the hosting account under .subme-deploy-backups/checkout-summary-20261020-01/<site>. COMPLETE markers and gzip integrity passed.
- Changed Smarty template compiled on each server. Installed checkout styles, translated availability notes, currency display and prior settled-renewal guard verified by CLI probes.
- Authenticated admin pages, profile/search, notifications, quick-payment categories and reminder state passed per target. Seventeen public assets matched server hashes; anonymous endpoint/CSRF protections and secure host-only 30-day session continuity passed.
- External root and admin GET requests returned HTTP 200 on both domains; customer CSS fetched externally matched release bytes. Final file/settings/CRON/backup checks passed. No error_log files modified after successful deployment completion.
- Temporary uploaded archive removed. Source and deployment record pushed to GitHub; SSH control session closed after checks.
