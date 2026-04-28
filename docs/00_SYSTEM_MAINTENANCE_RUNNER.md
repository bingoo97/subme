# System Maintenance Runner

Single source of truth for recurring background maintenance:

- CLI runner:
  - `dashboard-panel/scripts/system_maintenance.php`
- Runtime helper:
  - `app_run_maintenance_cycle()` in `dashboard-panel/bootstrap/application.php`

## What it does

One run executes:

- outbound email queue processing
- archiving expired crypto and bank payment requests
- expiring overdue active subscriptions
- live chat cleanup based on `support_chat_retention_hours` from settings (`1h`, `24h`, `3d`, `7d`, `30d`)
- retention cleanup for history, payments and expired orders when the related settings flags are ON

## Admin panel

In `Admin -> Settings` there is a manual button:

- `Run maintenance now`

This uses the same runtime helper as the CLI runner, so manual and cron behavior stay consistent.

## Cron / worker

Recommended command:

```bash
php /var/www/html/dashboard-panel/scripts/system_maintenance.php
```

In local Docker this is already used by the worker container loop.

## Safety actions

`Admin -> Settings` also contains destructive manual actions protected by text confirmation:

- clear `/history`
- delete dashboard sample/test data

These actions are intentionally separate from the maintenance runner and are never executed automatically.
