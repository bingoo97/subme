# Deployment result — 2026-10-19

Successfully deployed the 39-file runtime manifest to demo.subme.pro, verified it, then deployed dashboard.subme.pro. SSH hosting checkout was not modified. New files and each existing replacement were installed atomically with staged hash verification. Retired Rango assets were backed up and removed.

Private backups on hosting: `~/.subme-deploy-backups/admin-final-20261019-01/{demo,dashboard}`. Each contains original runtime files and a compressed, consistent database snapshot. The two databases were verified to have distinct identities. Local data, database credentials, environment, deployment pointers, uploads, sessions, .htaccess and cron were not copied. Existing settings, provider configuration and panel paid-through dates have matching before/after fingerprints.

Migrations 042 (new panel-payment tables) and 043 (32-character crypto-request status) applied independently to each database. Tier configuration initialized in each site's own currency, automation OFF, without local receiver addresses. Provider remote login and automatic provider subscriptions remain OFF.

Verification passed on both sites:
- 39 installed file hashes; 11 public asset hashes, including all new scripts and QR generator.
- Public customer/admin pages, authenticated Dashboard/Orders/Payments/Products/Users/Settings/Billing, user profile and global search.
- Four authenticated quick-crypto filters with valid rows/count payloads; notification state.
- Anonymous endpoint rejection, panel-payment CSRF rejection without initiating a scan or invoice.
- Secure host-only 30-day session cookies and continuity.
- Read-only reporting probe: 30/90/365 daily windows, 12 monthly buckets, four period totals and consistent order-task count; 12 queries, 0.008–0.010 seconds locally on hosting.

Deployment corrections: the SQL comment splitter initially stopped migration 043 before any runtime replacement; it was corrected and migrations rerun idempotently. The new public QR directory initially inherited private staging permissions, causing HTTP 404; public directory permissions were corrected to 0755 and all 11 HTTP asset hashes then passed. A bounded read-check retry respects the quick-view's existing 2-second rate limit.

Local tests: 225 tier/payment/email assertions, 62 panel subscription checks, 58 order extension checks, 31 performance/concurrency checks, 190 administrator authorization checks, 68 customer crypto checks, 55 customer Messenger checks and 30 complete fixture page renders. No production payment was accepted/cancelled/renewed/archived, no order extended and no test email sent during verification. Real payment/provider/SMTP integrations were not exercised.

Cron is preserved at its existing five-minute hosting cadence. No new recurring jobs or external payment receivers were configured. Database backup contains business data and remains private on hosting; it is not committed to GitHub.
