# Receipt guidance icons

Final runtime: `efd8bcea1e3bb0df1df3b6ae0679b6950432c563`. One PHP file.

- Fulfilled order guidance has a green plain check, without a circle.
- Credited balance guidance has a green wallet icon to its left.
- The intermediate commit 17e5f0c was superseded before installation.
- No database changes in this follow-up.

Verified on demo.subme.pro and dashboard.subme.pro at 2026-10-19T20:41:35.223850+02:00.

- Each site has a separate private file backup and consistent SQL snapshot, with COMPLETE marker.
- Distinct database identities, existing settings, provider configuration, panel configuration, protected connection files and cron were verified unchanged. Provider remote modules remain OFF.
- No local database, sessions, customer records, uploads or payment/order mutations were transferred or used for production tests.
- Authenticated dashboard/orders/payments/users/products/settings/billing/profile/search, notification endpoints, crypto filters/reminder state, public asset hashes, endpoint protection and 30-day session continuity passed.
