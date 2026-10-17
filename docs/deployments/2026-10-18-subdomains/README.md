# Admin changes — demo/dashboard, 18 October 2026

Scope: the explicit runtime file list in `files.txt`, current admin UI, notifications, chat, search, billing, sales reporting, bounded crypto polling/rates and 30-day renewable customer/admin sessions.

Deployment uses a selected Git revision exported to a private staging directory. It does not pull over the dirty server checkout, synchronize directories, import SQL dumps, copy configuration, or transfer user content. Each target retains its own `config/mysql.php`, backend/public pointers, uploads, secrets and runtime storage. The landing page and other domains are outside the target allowlist.

The three listed SQL migrations only add absent reporting/queue columns and indexes. They run independently against each target's existing connection. No seed/demo SQL, historical data recovery or payment processing is invoked by deployment. Pre-migration table definitions and copies of replaced files remain in each target's private backup directory.

Existing account-owned administrator sessions in the hosting's PHP session stores are copied, without deleting originals or overwriting newer sessions, into the application session directory before the admin session handler changes. Cookies remain host-only, Secure on HTTPS, HttpOnly and SameSite=Lax. Both cookie and PHP retention are 2,592,000 seconds; cookie expiry renews at most once per hour of use.

Validation before deployment: PHP syntax for runtime files; 66 billing assertions; 6 billing UI, 10 search UI and 30 chat UI assertions; 10 isolated session lifetime, host-only-cookie and two-day continuity assertions. Broader sales/crypto regression suites were run during the source changes. Live checks must confirm code hashes, protected config hashes, HTTP, cookie lifetime and continuity, and runtime errors separately for both hosts.

For rollback, use the target-specific backup `files/` tree and `created-files.txt`; restore only those code files. Additive SQL columns/indexes can remain when restoring the older code. Do not restore another target's configuration or data.
