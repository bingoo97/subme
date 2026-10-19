# Payment workflow and customer order deployment

Runtime: `b4c69a113353da5b8759b2cd8eb528fa4e20bf99`. 15 runtime files.

- Recurring action reminders use 30 minutes, controlled by a global Settings switch. Withdrawal housekeeping starts after 72 hours, oldest approval first; unfinished paid order work retains priority.
- Introduced one app_settings column, admin_action_reminders_enabled, with default ON. All pre-existing settings and data preserved. No imports.
- Inline archive/delete/cancel/renew workflows, malformed-response recovery without POST retry, requested amount tooltip and crypto-first amount display.
- Manual payment amount editing remains available without Admin Mode for operators with payment access; amount edits do not automatically adjust customer balance, as requested.
- Paid renewals override misleading completed alerts, restore the Extend tab, and preserve existing active access. Modal active status label is Fulfilled.
- Customer order waiting badges, spinner, search icon, compact warning and currency symbols.
- Local regressions: 78 customer checkout, 269 notification workflow, 58 extension, 195 admin authorization, 69 crypto safety checks, 35 page renders and frontend suites passed.

Verified on demo.subme.pro and dashboard.subme.pro at 2026-10-19T20:41:35.223850+02:00.

- Each site has a separate private file backup and consistent SQL snapshot, with COMPLETE marker.
- Distinct database identities, existing settings, provider configuration, panel configuration, protected connection files and cron were verified unchanged. Provider remote modules remain OFF.
- No local database, sessions, customer records, uploads or payment/order mutations were transferred or used for production tests.
- Authenticated dashboard/orders/payments/users/products/settings/billing/profile/search, notification endpoints, crypto filters/reminder state, public asset hashes, endpoint protection and 30-day session continuity passed.
