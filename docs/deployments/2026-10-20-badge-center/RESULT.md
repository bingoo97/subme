# Homepage NEW badge centering — 2026-10-20

Runtime `cf3e156` deployed successfully on demo.subme.pro and dashboard.subme.pro. Only `public_html/assets/css/customer-polish.css` was replaced, with old/new committed hashes verified.

NEW remains on the right, vertically centered with `top:50%; transform:translateY(-50%)`. A local 390px browser preview using the actual stylesheet measured a zero-pixel difference between badge and tile vertical centers.

Both sites passed served asset hashes, public HTTP, authenticated Pages editor (15 icons and NEW switch), Dashboard/Orders/Payments, payment category/CSRF checks and existing renewal/countdown regression probes. Existing page content, separate database identities, settings, provider configuration, protected files and cron entries were preserved. No schema changes, data imports or production financial mutations were made.

Private backups remain separately under `.subme-deploy-backups/badge-center-20261020-cf3e156/` on the hosting account. Scripts are an audit record; do not rerun completed deployments.
