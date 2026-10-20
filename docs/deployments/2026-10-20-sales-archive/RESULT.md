# Settled renewal archival: demo and dashboard

Completed: 2026-10-20T09:15:12.091129+02:00.
Runtime revision: `61ddc1d` (application fix `ea2397f`; follow-up test-only clock stabilization).
Application baseline: `55eb72b`.

- Deployed exactly one runtime file: dashboard-panel/admin/bootstrap.php, sequentially to demo then dashboard. Baseline and installed SHA-256 hashes verified on both targets.
- Applied-renewal payment is recognized by its exact order, customer, payment type/id and an existing credited ledger event. Archiving/re-saving does not misclassify it as a second original-purchase payment, rebook the balance or move the sale date.
- Local isolated suites: 126 sales/payment lifecycle assertions, 611 sales interval/chart assertions, 58 extension workflow checks, 69 crypto finality/concurrency checks passed. Fixed test fixture SQL clock to avoid second-boundary nondeterminism; no report date logic changed.
- No database migration, SQL import, restore, local settings/session/upload transfer, test payment creation or mutation of live financial records.
- Both databases remain distinct. Existing settings, provider credentials/configuration and panel configuration fingerprints verified unchanged before/after HTTP checks. Provider switches remain OFF.
- Separate previous-file backups and consistent SQL snapshots remain private on the host under .subme-deploy-backups/sales-archive-20261020-01/<site>; both COMPLETE and gzip integrity checked.
- Protected connection/environment/pointer/.htaccess files and CRON unchanged.
- Installed code guard, rolling seven-day/monthly calendar statistics, chart agreement and previous UI rules verified by CLI probes.
- Authenticated admin dashboard/orders/payments/products/users/settings/billing/profile/search and notification/crypto-filter/reminder endpoints passed on each site, with 17 public asset hashes, anonymous protections, invalid CSRF and 30-day secure host-only session continuity checks.
- Independent external root and admin requests returned HTTP 200 for both domains. Final installed hash, backups, settings fingerprints and CRON checks passed; no application error_log files modified after successful deployment completion.
- Source and deployment record pushed to GitHub. Temporary upload removed; SSH control connection closed after verification.
