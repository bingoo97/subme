# Shared purpose badge size

Runtime: `b891fc5106eb6633cb2fc68f9c57f3ad7f20417e`. One CSS file.

- Order and balance top-up purpose badges use matching compact dimensions, including mobile and reminder receipts.
- No database changes in this follow-up.

Verified on demo.subme.pro and dashboard.subme.pro at 2026-10-19T20:41:35.223850+02:00.

- Each site has a separate private file backup and consistent SQL snapshot, with COMPLETE marker.
- Distinct database identities, existing settings, provider configuration, panel configuration, protected connection files and cron were verified unchanged. Provider remote modules remain OFF.
- No local database, sessions, customer records, uploads or payment/order mutations were transferred or used for production tests.
- Authenticated dashboard/orders/payments/users/products/settings/billing/profile/search, notification endpoints, crypto filters/reminder state, public asset hashes, endpoint protection and 30-day session continuity passed.
