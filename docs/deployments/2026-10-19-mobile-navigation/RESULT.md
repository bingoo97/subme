# Mobile admin navigation deployment

Completed: 2026-10-19T09:18:55+02:00.
Runtime release: `124ac8202d38991d578ff6cfc8584810b43f1b6f` (GitHub main).

## Targets and data isolation

- https://demo.subme.pro and https://dashboard.subme.pro completed successfully.
- Six explicit runtime files, including the new navigation partial. No database import or migration.
- Separate database identities, settings, panel subscription configuration and provider configuration retained; fingerprints rechecked after authenticated HTTP requests.
- Provider remote login and provider subscription automation remain OFF.
- Protected configuration, environment files, backend/public pointers, .htaccess and cron unchanged.
- No customer data, uploads or sessions copied between localhost or either subdomain.
- Separate previous-runtime and consistent SQL backups saved privately under `.subme-deploy-backups/mobile-navigation-20261019-01/demo` and `dashboard`; both COMPLETE.

## Functionality

- Round light-grey 48px navigation button without shadow, in the lower-left safe area on phones/tablets.
- Dashboard to Orders, Orders list to Payments, Payments list to Orders. Details/forms/other pages use Back with a same-origin admin-history guard and safe fallback.
- Hidden with modals, native dialogs, chat, notifications, converter, sidebar, search results or input focus. Desktop mouse layouts keep it hidden.
- No new polling, database writes or external service requests for navigation.

## Verification

- Local: 16 history/modifier/fallback checks, 8 render/permission scenarios, 44 chat frontend checks and 30 complete page renders without PHP warnings passed.
- Hosting: eight navigation render/permission scenarios and authenticated HTML checks passed on both sites, including customer profile Back.
- Authenticated dashboard, orders, payments, products, users, settings, billing, notifications, crypto quick filters, reminder priorities, profile and search passed.
- Dashboard rolling-week/calendar-month probe, anonymous endpoint restrictions, panel-payment CSRF protection and 30-day secure session continuity passed.
- All six installed hashes match the release manifest; twelve public assets match the installed files. Changed CSS/JS additionally fetched from the local machine and matched the release.
- Existing-data tests were read-only; no production payment/order mutation was performed.
