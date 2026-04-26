# DB Optimization and English Naming Plan

## Current State (audit summary)

- Schema is mixed:
  - table names: partly English (`users`, `products_users`), partly Polish (`produkty_user`, `wplaty`, `refy`, `podstrony`).
  - column names: partly English (`user_id`, `date_end`), partly Polish (`id_zamowienia`, `platnosc`, `wysylka`, `data_zakonczenia`).
- Storage engines are mixed (`MyISAM` + `InnoDB`).
- Charsets/collations are mixed (`utf8`, `latin1`, `latin2`, `utf8mb4`).
- Most tables only have `PRIMARY KEY(id)` and miss secondary indexes for common filters/joins.
- There are duplicate/parallel business tables (`products_users` and legacy `produkty_user`) increasing maintenance risk.

## High-Impact Improvements (safe first phase)

These changes can be introduced before any table/column rename:

1. **Add missing secondary indexes** on hot tables used by filters/sorting.
2. **Unify engine to InnoDB** for transactional consistency and row-level locking.
3. **Unify charset/collation to utf8mb4** (recommended: `utf8mb4_unicode_ci`).
4. **Keep old names for now** and optimize first, then rename in a controlled migration.

## Suggested Indexes

Based on query patterns in the codebase:

### `produkty_user` (legacy orders)
- `(user, status)`
- `(aukcja, status)`
- `(platnosc, wysylka, status)`
- `(data_zakonczenia, status)`
- `(id_transakcji)`

### `products_users` (newer orders)
- `(user_id, status)`
- `(res_id, user_id)`
- `(product_id, status)`
- `(payment, shipment, status)`
- `(date_end, status)`

### `email_send`
- `(user, email_id, status)`
- `(status, data)`
- `(email_id, status)`

### `user_online`
- `(user, status)`
- `(status)`

### `produkty_chat`
- `(user2, status, data)`
- `(user1, user2)`

### `wplaty`
- `(user_id, status)`
- `(id_zamowienia, status)`

### `wplaty_crypto`
- `(user_id, status)`
- `(crypto_id, status)`
- `(date, status)`

### `resellers_news`
- `(res_id, status, date)`

### `users`
- `(res_id, status)`
- `(email)` (unique if business logic allows)

## Professional English Naming Target (phase 2)

Recommended target naming (examples):

- `produkty_user` -> `order_items_legacy` (or fully migrate to `products_users` and retire legacy table)
- `wplaty` -> `payments_legacy`
- `wplaty_crypto` -> `crypto_payments`
- `refy` -> `referrals`
- `podstrony` -> `pages`
- `komunikaty` -> `messages`
- `data_zakonczenia` -> `end_at`
- `data_rozpoczecia` -> `start_at`
- `platnosc` -> `payment_status`
- `wysylka` -> `delivery_status`
- `id_zamowienia` -> `order_id`

## Recommended Rename Strategy (no downtime / low risk)

1. **Preparation**
   - Inventory all SQL usages in PHP code (done partially).
   - Freeze schema changes during migration window.

2. **Dual-schema compatibility**
   - Create new English tables/columns.
   - Migrate data with backfill scripts.
   - Add compatibility layer (temporary DB views or code mapping), if needed.

3. **Application switch**
   - Update PHP code to read/write only English schema.
   - Run regression tests (frontend/admin + cron + callbacks/webhooks).

4. **Cleanup**
   - Remove old Polish schema objects.
   - Keep one canonical schema naming convention.

## Important Note

A direct one-shot rename of all Polish table/column names in the current app would be high risk and likely break production flows.  
The professional approach is phased migration with compatibility and rollback.

