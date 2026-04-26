# New Core Database

This is the new canonical database schema for the product.

## Design goals

- one installation = one client
- English, professional table and column names
- no multi-reseller model
- built around the real product surface:
  - customers
  - orders
  - live chat
  - crypto payments
  - bank transfers
  - wallet address pool
  - content pages
  - news
  - email templates
  - admin panel

## Intentionally removed from the new core

These old modules are not part of the new canonical schema:

- `resellers`
- `forum`
- `live_box`
- legacy “auction” style product variants
- mixed Polish/English duplicate tables
- old one-off helpers that do not match the current UI

If one of these modules is still needed later, it should be re-added as a clean, English feature and not copied 1:1 from legacy.

## Wallet pool model

The schema supports the exact crypto flow you described:

- `crypto_wallet_addresses`
  - pool of free and assigned crypto addresses
- `crypto_wallet_assignments`
  - one customer can receive many wallet assignments over time
  - one wallet can be assigned, released, and re-used if allowed
- `crypto_deposit_requests`
  - one deposit/payment request can get its own dedicated address
- `crypto_deposit_transactions`
  - real blockchain transactions matched to the request/address

This means:

- you can keep a pool of free wallet addresses
- assign a new address for every new crypto payment if you want
- keep multiple historical wallet assignments per customer
- track address usage cleanly and safely

## Manual admin wallet control

This schema is now explicitly designed for manual wallet management by the admin:

- wallet addresses are inserted into the pool by the admin
- wallet assignments can be tracked with `assigned_by_admin_user_id`
- wallet releases can be tracked with `released_by_admin_user_id`
- one customer can have multiple wallet addresses across different crypto assets
- one wallet cannot have more than one active assignment at the same time
- one wallet cannot have more than one open deposit request at the same time
- the global app setting `crypto_wallet_assignment_mode` defaults to `manual_only`

## Manual admin bank transfer control

The new core also supports optional manual bank transfer payments:

- admin inserts bank accounts into a managed pool
- admin can manually assign a bank account to a customer
- one customer can hold different assigned bank accounts over time
- the customer can receive bank transfer details directly in the UI
- payment approval is fully manual and tracked with admin approval fields
- one bank account cannot have more than one active assignment at the same time
- one bank account cannot have more than one open transfer request at the same time

## Files

- `001_core_schema.sql`
  - creates the new database structure
- `002_seed_defaults.sql`
  - inserts default currencies, roles, basic navigation, templates, and sample crypto assets
- `003_operational_views.sql`
  - creates helper views for wallet pool, bank account pool, customer assignments, and open payment requests
- `004_import_from_legacy.sql`
  - imports the current legacy data set into the new schema
- `init_v2_in_docker.sh`
  - creates `reseller_v2`, grants permissions, applies schema files, and imports legacy data in the Docker MySQL container
- `LEGACY_MAPPING.md`
  - old schema to new schema mapping reference
- `ADMIN_SQL_SNIPPETS.md`
  - example SQL for manual wallet and bank account insert, assignment, payment creation, approval, and release workflows

## Suggested setup

```sql
CREATE DATABASE reseller_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reseller_v2;
SOURCE dashboard-panel/database/v2/001_core_schema.sql;
SOURCE dashboard-panel/database/v2/002_seed_defaults.sql;
SOURCE dashboard-panel/database/v2/003_operational_views.sql;
```

## Docker setup

If you are using the local Docker setup from this project, run:

```bash
chmod +x dashboard-panel/database/v2/init_v2_in_docker.sh
dashboard-panel/database/v2/init_v2_in_docker.sh
```

Defaults:

- container: `reseller_db_local`
- legacy database: `marbodz_reseller`
- new database: `reseller_v2`

You can override them with env vars, for example:

```bash
DB_CONTAINER=reseller_db_local LEGACY_DB=marbodz_reseller NEW_DB=reseller_v2 dashboard-panel/database/v2/init_v2_in_docker.sh
```

Important:

- this creates and fills the new database in Docker
- it also grants the existing app user access to `reseller_v2`
- the running application still uses the legacy database until the PHP code is switched to the new schema

## Recommended next step

After creating the new database:

1. build an importer from the legacy database to this schema
2. switch the app module by module to the new schema
3. remove the temporary compatibility layer only after full verification
