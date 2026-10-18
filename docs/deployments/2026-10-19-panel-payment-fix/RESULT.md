# Panel payment correction deployment — 2026-10-19

Deployed five runtime files first to demo.subme.pro, then dashboard.subme.pro. Each target matched the expected c6672f6 baseline before installation. New expiry view, action spacing, alert top margin, larger desktop recipient address and fresh-invoice-after-cancellation logic are installed. No migrations were needed.

Both targets have private file and consistent compressed database backups under `~/.subme-deploy-backups/panel-payment-fix-20261019-01/{demo,dashboard}`. Target database identities are distinct. Protected settings, panel configuration (excluding normal scan/sync timestamps), provider configuration, config/mysql.php, deployment pointers, .htaccess and crontab passed before/after checks. No local database rows, receiver addresses, sessions or credentials were uploaded. Tier automation remains OFF on both installations, matching their pre-deployment state. Provider switches remain OFF.

Checks passed on each site:
- All five installed runtime hashes, PHP lint and 11 public asset hashes.
- Corrected modal template renders with the expiry message view and both return labels; new payment-assignment helpers are present.
- Customer/admin public pages, authenticated Dashboard, Orders, Payments, Products, Users, Settings and Billing; profile and global search.
- Authenticated notification state and all four quick-crypto categories; anonymous endpoint protection and panel-payment CSRF rejection.
- Secure host-only 30-day session cookies and continuity.

Local regression evidence: 237 panel tier/payment/history/concurrency/finality/email checks, 54 frontend polling/expiry/return eligibility checks and 62 subscription checks. Tests cover a fresh invoice for the same coin after cancellation, a new deadline and unique amount, selected allowed period, old/new transfer ownership, and ambiguous partial-payment review. Production verification did not create, cancel or pay invoices, extend access, mutate customer orders or send test emails. Actual network receipts and SMTP were not tested on hosting.

Rollback: restore the five original files from that site's private backup. No schema rollback or data import is required. Hosting repository checkout was left untouched.
