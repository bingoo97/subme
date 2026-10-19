# Inline crypto payment deletion deployment

Completed: 2026-10-19T11:42:52+02:00.
Runtime: `454a2c3849a9fa2f31056355f3a8987a382234cb` (GitHub main).

- Successfully deployed three runtime files to https://demo.subme.pro and https://dashboard.subme.pro.
- Expired/cancelled quick-view delete forms now use the existing protected inline POST handler. List/counts refresh immediately without navigation; confirmation remains. Shared reminder rows use the same path.
- Order-purpose and fulfilment links no longer underline in hover/focus/active/visited states; keyboard focus indication remains.
- Local tests passed: 209 notification workflow assertions, 43 reminder frontend checks, 25 overview checks and inline renew/cancel/archive/delete POST/CSRF, duplicate-submit, current-tab, list/badge and error-recovery scenarios.
- Both hosting checks passed for installed inline delete forms/confirmation, link styles, authenticated dashboard/orders/payments/products/users/settings/billing/profile/search/notifications and all four crypto tabs.
- Installed files and twelve public assets match hashes; changed CSS/JS independently fetched from the local machine also match.
- Separate database identities and app/panel/provider settings verified preserved after HTTP checks. Provider remote modules remain OFF.
- Configuration, environment, backend/public pointers, .htaccess and cron unchanged. No database import/migration or transfer of customer data, sessions or uploads.
- Destructive behavior tested only against isolated local fixtures; no production payment deleted or otherwise mutated for verification.
- Separate private runtime and consistent SQL backups under `.subme-deploy-backups/inline-payment-delete-20261019-01/demo` and `dashboard`, both marked COMPLETE.
