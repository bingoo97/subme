# Crypto payment order links and compact menu deployment

Completed: 2026-10-19T11:30:29+02:00.
Runtime: `aa6287411052c6865d5f3b25b5aee1240042904e` (GitHub main).

- Deployed successfully to https://demo.subme.pro and https://dashboard.subme.pro.
- Four explicit runtime files: crypto quick renderer, reminder view, reminder JavaScript and admin CSS.
- Order-purpose badges link to the associated order across all crypto tabs, shared reminder payment rows and approved receipt notices. Top-ups retain plain badges; dynamic row changes clear stale links.
- Orders page action menu aligns right; payment-detail action menu has an 8px radius on mobile.
- Local verification: 205 workflow assertions, 43 reminder frontend checks, 25 overview checks, 10 acceptance checks, syntax checks and 30 complete page renders without PHP warnings passed.
- Server verification: authenticated dashboard/orders/payments/products/users/settings/billing/profile/search/notification checks and all four crypto quick filters passed on both sites. Purpose links checked against each rendered row's linked order ID.
- Installed hashes and twelve public asset hashes passed; changed CSS/JS independently fetched from the local machine also match the release.
- Separate database identities, application settings, provider configuration and panel subscription configuration were retained and rechecked after HTTP tests. Provider remote modules remain OFF.
- Protected configuration, environment, backend/public pointers, .htaccess and cron unchanged. No database migration/import, customer-data/session/upload transfer, or production financial mutation.
- Separate private runtime and consistent SQL backups saved under `.subme-deploy-backups/payment-order-links-20261019-01/demo` and `dashboard`; both have COMPLETE markers.
