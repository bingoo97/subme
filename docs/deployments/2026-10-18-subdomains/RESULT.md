# Live deployment verification

Deployed runtime revision: `3b7616f91da2feb71f6a2c683d21c7ae2db9e0f9`.

Both `demo.subme.pro` and `dashboard.subme.pro` completed deployment. All 34 allowlisted file hashes match the staged Git revision. Protected database configuration and backend/public pointer hashes match their pre-deployment values. Each site retains a private, target-specific backup under `~/.subme-deploy-backups/admin-20261018-3b7616f91da2/`.

The isolated Git bundle was imported into `refs/deployments/admin-20261018` without changing the dirty server worktree or its main branch. No SQL data dump, local fixture, upload, environment file or user content was transferred. Only the three additive schema migrations were executed separately on the two existing databases.

Live checks passed for the customer page, admin login routes, public asset hashes, 30-day customer/admin cookies, Secure/HttpOnly/SameSite=Lax, host-only domain scope and repeated-request admin session continuity. One existing administrator session was preserved before changing the PHP session storage. No new `error_log` files/updates were observed in the backend/webroot after deployment.

Direct read-only sales queries returned 30 daily and 12 monthly buckets on both live databases in 0.011–0.012 seconds. Billing summary reads completed in 0.001–0.003 seconds. No document issuance, payment acceptance, mail sending or maintenance worker was invoked by these checks.

GitHub publication is a separate step requiring write access to `bingoo97/subme`; the deployment itself is already live. Local main contains the runtime commit, while the checked-in server main branch is intentionally left unchanged to preserve unrelated work.
