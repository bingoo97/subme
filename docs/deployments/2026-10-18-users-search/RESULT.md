# Deployment result — 2026-10-18

Runtime revision: `b06d95cea15b7f472f6b87495a5199b8c4868f19`.

Successfully deployed six manifest files to demo.subme.pro, verified, then deployed and verified dashboard.subme.pro. Both private backups are under `~/.subme-deploy-backups/users-search-20261018-b06d95cea15b/{demo,dashboard}`. Hosting checkout was not changed.

Checks passed on both sites:
- Installed file hashes and seven public asset hashes.
- Separate database identities; existing settings and provider configuration fingerprints preserved.
- Config/mysql.php, deployment pointers, .htaccess and cron unchanged.
- Provider login and automatic subscriptions OFF, including schema defaults.
- Customer/admin HTTP 200; secure, host-only 30-day cookies and session continuity.
- Authenticated dashboard, orders, payments, products, users, settings and billing pages.
- Seven-column Users layout, user profile and authenticated global search.
- Notification state and anonymous endpoint protection.
- Read-only statistics probe: 30/90/365-day charts, 12 monthly buckets, four period summaries, consistent task count; 12 queries, 0.007 seconds per site.

Local checks: PHP/JS/Bash syntax, 30 isolated page renders, 10 search assertions, 16 notification assertions, 20 customer Messenger assertions and visual checks at 320/390/768/1440 pixels. Production verification did not approve payments, renew subscriptions or modify orders.
