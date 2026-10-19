# Responsive payment toolbar deployment

Completed: 2026-10-19T11:54:08+02:00.
Runtime: `980da029f29e9c20c89cb48d58e74c1532d653b3` (GitHub main).

- Deployed three explicit runtime files to https://demo.subme.pro and https://dashboard.subme.pro: admin index, admin CSS and admin JavaScript.
- Phone/tablet payment status filters stay in one horizontally scrollable row without visible scrollbars. Active colours match crypto quick view; Archived uses black. Payment type filters stay grey and are omitted when only one payment method is available.
- Active filter is revealed on page load and resize; locally verified Approved is fully visible at 320px after reload. Mobile and tablet checks showed no page overflow.
- Mobile navigation now uses the right safe-area edge, white fill and 1px #e6e6e6 border, retaining the 65px size and shadow. Mobile panel-card top margin explicitly 1rem.
- Local verification passed: 34 complete page render scenarios including all four crypto/bank switch combinations; 216 notification workflow assertions; 54 badge checks; 43 reminder frontend checks; 16 mobile navigation checks; inline mutation and syntax checks.
- Hosting verification passed on both: authenticated dashboard/orders/payments/products/users/settings/billing/profile/search/notifications, seven scope markers and active Approved/Archived pages, all four quick payment categories and protected endpoints.
- Installed hashes and twelve public assets verified; changed CSS/JS independently fetched from the local machine match the release.
- Separate database identities, app settings, panel settings and provider configurations rechecked after HTTP tests and unchanged. Provider remote modules remain OFF.
- Protected configuration, environment, backend/public pointers, .htaccess and cron preserved. No SQL import/migration, session/upload/customer-data transfer, or production financial mutation.
- Separate private runtime files and consistent SQL backups under `.subme-deploy-backups/payment-toolbar-20261019-01/demo` and `dashboard`; both marked COMPLETE.
