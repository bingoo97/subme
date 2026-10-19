# Crypto payment reminders deployment

Completed: 2026-10-19T08:28:21+02:00.

Runtime release: `ed7980c3e7bf6be82f074c69968b099b9debc041` (GitHub `main`).

## Targets and isolation

- `https://demo.subme.pro` and `https://dashboard.subme.pro`: both completed.
- 16 runtime files from the explicit manifest; no database imports or migrations.
- Each target retained its own database identity, application settings, panel subscription configuration and provider credentials/configuration.
- Protected MySQL configuration, backend/public pointers, .htaccess, environment files and cron were preserved.
- Provider remote login and automatic provider subscriptions remain OFF on both targets.
- Existing customer/admin sessions, uploads and payment data were not copied between sites.

## Backups

Separate previous-runtime files and consistent full SQL snapshots are stored privately on the hosting account under `.subme-deploy-backups/crypto-reminders-20261019-01/demo` and `dashboard`. Both have a COMPLETE marker and runtime revision. A runtime rollback is available; no database restore was performed.

## Verification

- All 16 installed files match the manifest on each target; all 12 public asset responses match installed hashes.
- Reminder interval restored to 10 minutes everywhere, including localhost. The temporary 10-second branch was removed; persistent reminder keys separate old test deadlines from production deadlines.
- Authenticated dashboard, orders, payments, products, users, settings and billing read successfully on each target.
- Authenticated notifications, all four crypto categories, reminder priority/state, profile and global search checks passed.
- Anonymous endpoint restrictions, panel payment CSRF and secure 30-day host-only session cookies/continuity passed.
- Changed customer payment templates compiled successfully using each server's deployed Smarty runtime.
- Private state fingerprints rechecked after HTTP tests and unchanged.
- Local isolated suites: 195 notification/workflow checks, 69 crypto/finality/concurrency checks, 30 complete page renders; frontend reminder suite 40 checks plus overview, badge, inline actions, status guidance and acceptance suites passed.
- All financial actions were tested against isolated local fixtures only. No production payment was accepted, cancelled, archived or renewed and no order was extended for verification.
