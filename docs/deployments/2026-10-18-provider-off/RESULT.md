# Deployment verification — 18 October 2026

Runtime revision: `5706fe6440a8e0ab0e2f7c17d61b2c6e28ab46d9`, published to `bingoo97/subme` main and fetched through the hosting account's existing Git remote. Both `demo.subme.pro` and `dashboard.subme.pro` completed the 12-file allowlisted deployment.

For each target, the installed hashes match the selected Git revision. Preflight hashes matched the earlier deployed code, so unrelated live changes were not overwritten. Database configuration, `.backend-path`, `.public-root-path`, `.htaccess` and cron hashes/content match their protected snapshots. The dirty server checkout and main HEAD remain at `aa766a6b3991c7a878321823532bd3c849f202bc`; no pull/reset or directory synchronization was performed.

Both `provider_remote_login_enabled` and `provider_remote_subscriptions_enabled` are `0`, with schema defaults `0`, independently on each target. The local development flags were also set OFF. Other settings hashes and existing encrypted provider configuration row counts are unchanged. The additive provider schema was installed using each target's own connection; no local data dump, provider credentials, encryption key, worker token, environment, upload or session files were transferred. The browser/OCR worker remains local and was not installed or started on hosting.

Target-specific backups:

- `~/.subme-deploy-backups/provider-off-20261018-5706fe6440a8/demo`
- `~/.subme-deploy-backups/provider-off-20261018-5706fe6440a8/dashboard`

Both sites returned HTTP 200 for the customer page and admin login. Public `admin.css`, `admin.js` and `provider-browser.js` hashes match the release. Customer/admin cookies retain `Max-Age=2592000`, Secure, HttpOnly, SameSite=Lax and host-only scope; repeated requests preserve the session ID. Both new endpoints return JSON `session` with HTTP 403 to unauthenticated POSTs. No `error_log` files were present in the checked webroot/backend roots and admin subdirectories. Authenticated admin workflows were not replayed using existing session files, and no provider login, subscription mutation, payment approval or email delivery was performed by the live checks.

Before deployment, an isolated schema fixture verified clean migration, repeated migration, OFF enforcement from previously ON flags, unrelated-settings detection and cleanup of its database/grant/files. Provider implementation regressions were completed during development: PHP core 137, endpoint 30, browser base 74, image CAPTCHA 18, OCR 29, guard/protocol 45, connection 19, subscription 38 and selection UI 8 assertions. These are cumulative development results; the deployment did not rerun browser/OCR work against production providers.
