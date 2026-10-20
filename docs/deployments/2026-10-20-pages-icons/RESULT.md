# Pages tile appearance deployment — 2026-10-20

Deployed runtime revision `7b2ccac` to demo.subme.pro and dashboard.subme.pro. Eleven committed runtime files were installed; existing file hashes were checked against `c6f8ffd` before installation and against the new manifest afterwards. Two new files were previously absent.

## Scope and data preservation

- Fifteen allowlisted media, shop, promotion and device icons, plus an optional red text-only NEW badge per homepage page.
- Appearance controls are visible only for the customer homepage section. Changing sections preserves the current icon selection.
- Two additive columns: `static_pages.menu_icon` and `static_pages.menu_badge_new`. No existing content was replaced, and no settings or business data were imported from localhost or another site.
- Independent hosting-side file/database backups were created under `.subme-deploy-backups/pages-icons-20261020-7b2ccac/`, separately for each site.
- Existing page content hashes, distinct database identities, settings, provider configuration, protected files and cron entries were verified unchanged. Remote provider features remain OFF.

## Verification

- Local isolated suite passed 84 assertions, including all icons, independent page preferences, badge toggling, unsafe icon fallback, rendered homepage tiles, deletion/locale/authorization regressions and repeated migration execution.
- Mobile browser checks verified fifteen choices, selection retention, and hiding controls in Payments, Applications and regular-page sections.
- Both deployed editors rendered fifteen icon radio options and the NEW switch through authenticated HTTP.
- Public HTTP, six served assets, authenticated Dashboard/Orders/Payments, quick payment categories and CSRF checks passed on both sites.
- Existing renewal/countdown checks passed: active expiry base, expired fulfilment-time base, automatic preview, manual override and paid period. Admin/client countdowns matched for all eligible live rows (demo 0, dashboard 4); no active paid cash order was missing an expiry date.
- No production test content, orders or payments were created.

The first demo validation stopped because the CLI probe did not load localization helpers. The deployed files remained in place; the probe dependency and deployment rollback guard were corrected. All manifest, probe, HTTP, protected-content/configuration and cron checks were then completed successfully before marking demo complete. Dashboard used the corrected scripts and completed in one run.

Scripts are retained as an audit record. Do not rerun a completed release. An additive schema may remain after a file rollback; existing data is not restored or overwritten by these scripts.
