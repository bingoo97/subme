# Mobile navigation size and shadow deployment

Completed: 2026-10-19T09:29:55+02:00.
Runtime: `b214c6f7a985db3c82174c982bda9c0e01930b47` (GitHub main).

- Successfully deployed to https://demo.subme.pro and https://dashboard.subme.pro.
- Only `public_html/assets/css/admin.css` changed: 65px circle, 26px icon, border matching fill, larger soft shadow, compact pressed shadow and enough bottom content clearance.
- Existing overlay-hiding rules and navigation behavior preserved.
- Local navigation suites passed: 16 frontend checks and 8 render/permission scenarios.
- Hosting probes confirmed size, icon, matched border, shadow and navigation render/permission cases on both targets.
- Authenticated dashboard/orders/payments/products/users/settings/billing/profile/search/notification checks and anonymous endpoint restrictions passed.
- Twelve public assets matched installed hashes; the changed CSS was additionally fetched independently from the local machine and matched the release hash.
- Separate database identities, settings, provider configuration and panel configuration verified unchanged after HTTP checks. Provider remote modules remain OFF.
- Configuration files, environment, .htaccess, backend/public pointers, cron, customer data, sessions and uploads were not deployed or transferred between sites.
- No database import or migration. No financial mutations performed for tests.
- Separate private runtime and SQL backups exist under `.subme-deploy-backups/mobile-navigation-style-20261019-01/demo` and `dashboard`, with COMPLETE markers.
