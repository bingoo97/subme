# Verified deferred-order expiry fix

Both demo.subme.pro and dashboard.subme.pro run application revision 3be399c (previous runtime a3542b2). Only dashboard-panel/admin/bootstrap.php was deployed, extracted from the committed Git revision and checked against the exact previous runtime hash. No migrations, settings changes or business-data imports were performed.

When a fixed-term paid order is marked completed, the backend now initializes a missing expiry from the fulfilment start and selected product duration. A stale blank form preserves established start, expiry and payment dates. Three-month packages use three calendar months. Products without a duration retain their existing no-expiry behavior; explicit custom expiry dates remain editable.

Local validation passed 134 crypto/payment checks, 62 cash checks and 58 extension workflow checks. The regression now fulfils cash orders with blank expiry fields, reproduces a three-month package, checks admin/customer progress availability, replays fulfilment without extra balance debits or extra days, repairs a missing expiry using the stored start and preserves custom expiry dates. The previously reported local order was corrected locally from its own stored fulfilment start; this record was not transferred to either subdomain.

Each site has separate private file/database backups under .subme-deploy-backups/cash-expiry-20261020-3be399c. Protected configuration and database identities remain unchanged and distinct. Provider remote-login/subscription features remain OFF. Both sites passed runtime file hashes, PHP lint, public client/admin HTTP checks, public asset hashes, authenticated Dashboard/Orders/Payments checks, quick payment categories and cash CSRF rejection.

Read-only production probes verified the three-month calculation and countdown helpers on both servers. Four existing active dated subscriptions on dashboard produced matching admin/client countdowns; demo had no eligible active dated subscriptions. Neither site had active fixed-term cash orders with missing expiry dates, so no production data repair was necessary. No production test orders, payments, credits or debits were created.
