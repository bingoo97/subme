# Migrations Order

Run in this order:

1. `001_preflight_schema_audit.sql` (read-only)
2. `002_add_indexes_safe.sql` (idempotent, safe performance step)
3. `003_english_rename_plan.sql` (planning reference only, not executable migration)
4. `004_single_tenant_english_phase1.sql` (executable compatibility migration)
5. `005_single_tenant_english_views.sql` (additional read-model views)

## Notes

- Execute first on staging.
- Take full DB backup before step 2.
- Step 3 is intentionally a plan, not auto-run SQL.
- Step 4 is the first real English naming implementation and stays backward compatible with the old Polish schema.
- Step 5 adds extra English read views for progressive code migration.

## Runner

Use the CLI migration runner:

```bash
php dashboard-panel/migrations/migrate.php status
php dashboard-panel/migrations/migrate.php --dry-run
php dashboard-panel/migrations/migrate.php
```

Runner behavior:

- auto-creates `schema_migrations`
- executes only real migrations
- skips `001_preflight_schema_audit.sql` and `003_english_rename_plan.sql`
- verifies checksums of already applied executable migrations

