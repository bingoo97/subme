# Customer card deployment — 2026-10-20

Successfully deployed runtime revision `c6f8ffd` to demo.subme.pro and dashboard.subme.pro. Only `public_html/assets/css/admin.css` was replaced, with the previous `687be62` file hash checked before installation and the committed new hash checked afterwards.

- Customer search cards now have an actual 8px grid gap, no overlapping borders, un-underlined blue profile handles, compact buttons and adjusted spacing.
- Local browser checks passed at 320, 390 and 1440px: 8px gaps, no card overflow, no handle underline. No financial records were created for this visual check.
- Both sites passed public HTTP, authenticated Dashboard/Orders/Payments, payment category and CSRF checks. Served assets match installed runtime contents.
- Existing renewal checks passed on both sites: active/expired expiry bases, automatic previews, custom overrides, paid periods and admin/client countdown consistency. Eligible live rows: demo 0, dashboard 4; missing active cash expiry dates: 0.
- Separate database identities, settings, provider configuration, protected files and cron entries were preserved. No migrations, database imports or production financial mutations were performed.
- Private backups are retained separately on the hosting account under `.subme-deploy-backups/customer-cards-20261020-c6f8ffd/`.

Deployment scripts are retained as an audit record; do not rerun completed releases.
