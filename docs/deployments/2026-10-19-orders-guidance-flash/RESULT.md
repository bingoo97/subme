# Orders view deployment

Completed: 2026-10-19T11:58:53+02:00.
Runtime: `f26b7d82f6c4aac3bce81d6dc3e458f2f9b7dedb` (GitHub main).

- Successfully deployed to https://demo.subme.pro and https://dashboard.subme.pro.
- One runtime file: dashboard-panel/admin/index.php.
- Orders response starts with is-admin-orders-guidance-hidden before JavaScript. Stored ON/OFF and toggle behavior are retained.
- Local tests passed: 25 guidance preference/default/blocked-storage checks and 34 complete page renders; PHP lint and scoped diff checks passed.
- Both authenticated HTTP probes verified the hidden initial guidance class. The empty-open release additionally checks header/table consistency, absence of the empty warning and suppression of open-order help without open orders.
- Dashboard, orders, payments, products, users, settings, billing, notifications, crypto quick view, profile/search, protected endpoints and twelve public asset hashes passed hosting checks.
- Separate database identities, app settings, panel/provider configurations, protected files and cron verified unchanged after both releases. Provider remote modules remain OFF.
- No SQL import/migration, customer-data/session/upload transfer or production payment/order mutation for testing.
- Separate private runtime files and consistent SQL backups exist under `.subme-deploy-backups/orders-guidance-flash-20261019-01/demo` and `dashboard`, both with COMPLETE markers.
