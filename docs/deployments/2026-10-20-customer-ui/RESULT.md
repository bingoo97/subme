# Customer UI deployment: demo and dashboard

Completed: 2026-10-20T08:53:50.107685+02:00.
Runtime revision: `55eb72b`, code baseline: `8d70038`.
Release backup/stage identifier: `customer-ui-20261020-02`.

## Scope and isolation

- Deployed 37 explicitly listed Git runtime files, including the customer visual layer, compact order columns with visible No expiry text, simple history, checkout/access/top-up modals, news, other customer pages and Pages management/homepage tiles.
- Source commits pushed to GitHub. Archive built from committed Git objects, excluding unrelated dirty files.
- No local database, environment, connection file, session, upload, vendor directory or generated template cache copied to either subdomain. No migrations, SQL imports, database restores, payment approvals or page deletions performed.
- Distinct demo/dashboard database identities verified. All existing application/provider/panel settings fingerprints, protected file hashes, backend pointers, .htaccess and CRON preserved. Both provider remote switches remain OFF.
- Separate private consistent database snapshots and previous-file backups remain on the server for each site. Both final COMPLETE markers and gzip integrity verified.
- Initial demo attempt encountered an absent new partials directory during temporary-file preparation and automatically restored previous runtime files without changing database data. Directory creation fixed before the final successful sequential demo/dashboard deployment.

## Verification

- Local: 64 complete page renders without PHP warnings; 62 Pages deletion/locale/authorization assertions; 23 clipboard assertions plus product-scroll/reduced-motion and legacy Bootstrap/fallback collapse checks passed.
- All 37 baseline files matched before deployment on each target. Final installed hashes matched the release on both sites.
- Twenty changed customer templates compiled successfully with server Smarty before deployment and on both installed applications afterward.
- Seventeen public assets fetched over HTTPS and matched server hashes per site, including new CSS and customer JavaScript. Public/admin HTTP status, host-only secure 30-day session continuity, anonymous endpoint protections and invalid payment CSRF rejection passed.
- Authenticated admin dashboard, orders, payments, products, users, settings, billing, profile, global search, notifications, quick-payment filters and reminder state passed on both sites using existing valid admin sessions. No financial mutations were submitted.
- Demo customer browser session: orders, history, settings, news, apps, instructions and payment history rendered with the new CSS and no whole-page horizontal overflow at mobile width. Screenshot saved separately in the task visualization directory.
- Dashboard customer browser correctly displayed its verification gate; no customer credentials entered and no gate bypassed. Customer template compilation and public asset verification passed there.
- Protected configuration fingerprints rechecked after HTTP/browser tests; no newly modified application error_log files after successful deployment completion.
- Temporary upload archive removed. Private backups retained.

## Dashboard customer follow-up

- After the user completed verification/login in their browser, tested the actual dashboard customer session at CSS viewport widths 320, 390 and 430 px.
- Orders displayed its empty state without horizontal page overflow. This account had no orders and no crypto payment requests in its payment history.
- Opened the top-up modal, selected Solana, continued to the amount screen, selected the 25 preset, returned to crypto selection, selected Bitcoin and confirmed the amount remained 25. Selected crypto summary updated correctly.
- Modal remained within horizontal viewport bounds; editable amount font was 16px. Captured the amount-screen screenshot and closed the modal without submitting its payment-generation form.
- No order, payment request or deposit created; no existing financial records changed. QR/countdown screen could not be tested on this account because it has no existing ticket, respecting the user's request not to generate test payments.
