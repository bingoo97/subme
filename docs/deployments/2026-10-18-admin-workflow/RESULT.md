# Deployment verification — 18 October 2026

Runtime revision: `43f6bd5a9d9869e9fc2304375519ef8a94ce9ffc`, published to `bingoo97/subme` main and fetched on hosting. Demo deployed and passed verification before dashboard was installed. Each target received exactly the 32 allowlisted files, with installed hashes matching the selected revision. The hosting working checkout remains untouched at `aa766a6b3991c7a878321823532bd3c849f202bc`.

For both `demo.subme.pro` and `dashboard.subme.pro`:

- `provider_remote_login_enabled = 0` and `provider_remote_subscriptions_enabled = 0`; both schema defaults remain `0`.
- Database configuration, backend/public pointers, `.htaccess`, and cron are unchanged.
- Each target retained its own database identity, and the two database identities differ. Existing settings other than the two requested switches and encrypted provider configuration fingerprints are unchanged, including after HTTP verification.
- Only additive migration 041 ran against that target's existing connection. No local business data, SQL dump/seed, environment, credentials, uploads, sessions or caches were imported.
- Customer home and administrator login returned HTTP 200. Cookies retain 30-day lifetime, Secure, HttpOnly, SameSite=Lax and host-only scope, with continuity verified on repeated requests.
- All seven deployed public CSS/JavaScript assets matched the release hashes over HTTPS.
- Anonymous topbar and provider requests returned the expected JSON authorization errors.
- An existing administrator session opened dashboard, orders, payments, products, users, settings and billing successfully. Authenticated notification refresh returned a valid task fragment/revision. No order, payment, renewal or provider operation was submitted on hosting.
- A read-only transaction verified 30-, 90- and 365-day chart windows, 12 monthly buckets, four period metrics, billing summaries and bounded task rendering/counts. Each probe used 12 queries: approximately 0.007 seconds on demo and 0.012 seconds on dashboard. These timings cover the probe queries, not complete browser page loads.
- No recently updated `error_log` files were found in the checked backend/webroot directories and their immediate subdirectories during verification.

Local pre-deployment validation included all 18 isolated regression suites, the final 178 notification/workflow assertions plus 16 frontend notification assertions, and 30 complete page renders without PHP warnings. The deployment guard itself passed an isolated test covering previously ON switches becoming OFF, repeated migration, fingerprint preservation and detection of an unrelated settings change. Source PHP/JavaScript and deployment Bash syntax checks passed.

Target-specific rollback code backups:

- `~/.subme-deploy-backups/workflow-20261018-43f6bd5a9d98/demo`
- `~/.subme-deploy-backups/workflow-20261018-43f6bd5a9d98/dashboard`

The local working tree's unrelated configuration, dependency, landing-page and swapper changes were not included in this release.
