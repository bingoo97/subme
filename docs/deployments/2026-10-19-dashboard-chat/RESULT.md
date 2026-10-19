# Dashboard and admin chat deployment

Completed: 2026-10-19T08:47:52+02:00.

Runtime release: `adb2545174bbe28838b895fa17546c90e8e6700e` (GitHub main).

## Targets and isolation

- https://demo.subme.pro and https://dashboard.subme.pro: completed successfully.
- Five runtime files from the explicit manifest. No database imports or migrations.
- Separate database identities, application settings, panel subscription configuration and provider configuration preserved, including checks after authenticated HTTP requests.
- Provider remote login and automatic provider subscriptions remain OFF on both targets.
- MySQL configuration, environment files, backend/public-root pointers, .htaccess and cron unchanged.
- Customer data, uploads and sessions were not copied between localhost or either subdomain.

## Changes

- Weekly dashboard metric uses the last seven calendar days including today; monthly uses the current complete calendar month. Visible date ranges, accurate small averages and multi-currency display colours.
- Light dashboard tabs with matching grey top/bottom borders; positive/zero chart summary values are green/red.
- Compact chat inbox typography, tighter identity spacing and centred date/remove actions.
- Mobile composer removes redundant bottom safe-area padding when the keyboard reduces the visual viewport.
- Lightweight chat transitions, no permanent per-message will-change or blur, reduced-motion support and cancellation of previous conversation entry animations.

## Backups

Separate previous-runtime files and consistent compressed SQL snapshots are stored privately on the hosting account under `.subme-deploy-backups/dashboard-chat-20261019-01/demo` and `dashboard`. Both contain COMPLETE and runtime-revision markers. No database restore was performed.

## Verification

- 611 local sales assertions, 44 chat frontend assertions, 7017 chart positioning assertions and 10 chart interaction assertions passed.
- 30 complete local page renders passed without PHP warnings; changed PHP/JavaScript syntax checks passed.
- Both hosting probes confirmed weekly metrics match seven daily chart buckets and monthly boundaries are correct.
- Installed file hashes match the manifest; all twelve public assets matched their installed hashes. The three changed CSS/JS responses were also verified independently from the local machine.
- Authenticated dashboard, orders, payments, products, users, settings and billing loaded successfully on both targets.
- Dashboard period markup and chat date/remove action markup passed on both targets.
- Authenticated notifications, four crypto categories, reminder priority/state, profile and global search passed.
- Anonymous endpoint restrictions, panel-payment CSRF protection and secure 30-day host-only cookies/session continuity passed.
- No production payment was accepted, cancelled, archived or renewed; no order was extended during testing.
