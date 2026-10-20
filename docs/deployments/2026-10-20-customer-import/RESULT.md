# Deployment verified: demo and dashboard

Runtime revision: b4a346bf741727b0cf406fc236d8151ada6ba47f.
Previous revision on both sites: 48e1931.

Deployed 25 allowlisted runtime files extracted directly from Git, covering customer import and the previously prepared crypto payment creator/manual-review fixes. No config, environment, uploads, sessions, database dumps, tests or localhost data were uploaded. No SQL migration was required.

Both sites passed:

- Existing runtime baseline comparison before replacement; exact package/runtime SHA-256 matches afterwards.
- Private backup of target files and each site's own database (60 tables each), stored only in the hosting account.
- Unchanged database identity, settings, provider configuration, panel configuration, connection configuration, routing pointers, .htaccess and crontab.
- Provider remote login and automatic subscriptions remained OFF.
- Public client and admin HTTP 200; five changed public CSS/JS assets matched runtime checksums.
- Authenticated Users import preview/form/script markers; anonymous import and invalid CSRF rejected.
- Parser checks for 5000 TXT emails and CSV/SQL/TSV status 2; source encryption round trip on the hosting PHP runtime.
- Authenticated Dashboard, Orders, Payments and all four quick-payment categories.

No production users, orders, payment tickets, mail or balances were created by verification. Parser checks ran in memory. Backups and deployment stage remain private under the account's .subme-deploy-backups/customer-import-20261020-b4a346b and .subme-deploy-stage/customer-import-20261020-b4a346b directories.

Predeployment local checks included 21,500 multi-format imported fixture accounts (6996 preserved blocks), the base 5000-account import suite, 12 status/login security checks, 14 frontend checks, 86 quick-payment creation checks and 67 complete page renders. All test databases were removed afterwards.
