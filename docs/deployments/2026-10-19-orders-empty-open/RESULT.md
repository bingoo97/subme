# Orders view deployment

Completed: 2026-10-19T11:58:53+02:00.
Runtime: `a628ad1094b8d12556c6739f6ab6f192b12cd8ff` (GitHub main).

- Successfully deployed to https://demo.subme.pro and https://dashboard.subme.pro.
- One runtime file: dashboard-panel/admin/index.php.
- Zero open orders render neither heading/table nor the empty warning or open-order help. A nonempty queue retains its header, table and help. Also includes the guidance-flash fix.
- Local tests passed: 35 complete page renders, including empty and nonempty open-order queues; PHP lint and scoped diff checks passed.
- Both authenticated HTTP probes verified the hidden initial guidance class. The empty-open release additionally checks header/table consistency, absence of the empty warning and suppression of open-order help without open orders.
- Dashboard, orders, payments, products, users, settings, billing, notifications, crypto quick view, profile/search, protected endpoints and twelve public asset hashes passed hosting checks.
- Separate database identities, app settings, panel/provider configurations, protected files and cron verified unchanged after both releases. Provider remote modules remain OFF.
- No SQL import/migration, customer-data/session/upload transfer or production payment/order mutation for testing.
- Separate private runtime files and consistent SQL backups exist under `.subme-deploy-backups/orders-empty-open-20261019-01/demo` and `dashboard`, both with COMPLETE markers.
