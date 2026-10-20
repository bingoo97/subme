# Deployment verification — 2026-10-20

Both demo.subme.pro and dashboard.subme.pro completed deployment successfully.

- Runtime revision: `687be62` (previous runtime: `7a7f952`). Only the two files in `files.txt` were replaced, using committed contents and verified SHA-256 manifests.
- Crypto quick panel now has a permanent create-payment shortcut, 32px footer rows and icons aligned with the preferences switch.
- Existing renewal fix remains installed: active subscriptions extend from their current expiry; expired subscriptions extend from fulfilment time. Automatic expiry previews and manual custom dates passed read-only checks.
- Local verification passed 134 crypto creation checks, 101 cash checks, 58 extension checks and both extension-preview and payment-creation frontend regression suites.
- Both sites passed public HTTP checks, authenticated Dashboard/Orders/Payments rendering, quick payment category checks and CSRF checks. The new footer was verified in rendered pages.
- Read-only admin/client countdown comparisons passed for all eligible live rows (demo: 0; dashboard: 4); neither site had an active cash order missing its expiry date.
- Separate database identities, settings, provider configuration, protected configuration files and cron entries were verified unchanged. Provider remote features remain OFF. No database import, migration or production financial test mutation was performed.
- Private file and database backups remain on the host under `.subme-deploy-backups/crypto-footer-20261020-687be62/`, separately for each site.

The included deployment scripts are an audit record; completed deployments must not be rerun.
