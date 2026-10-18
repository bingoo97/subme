# Migrations Order

Run in this order:

1. `001_preflight_schema_audit.sql` (read-only)
2. `002_add_indexes_safe.sql` (idempotent, safe performance step)
3. `003_english_rename_plan.sql` (planning reference only, not executable migration)
4. `004_single_tenant_english_phase1.sql` (executable compatibility migration)
5. `005_single_tenant_english_views.sql` (additional read-model views)
6. `018_admin_mixer_settings_and_orders.sql` (admin Mixer settings and order/event tables)
7. `019_enforce_crypto_wallet_exclusivity.sql` (crypto wallet assignment cleanup)
8. `020_disable_customer_payment_crypto_assets.sql` (disable BNB, POL and USDC as customer payment assets)
9. `021_mixer_min_order_usd.sql` (configurable Swapper minimum order amount)
10. `022_crypto_auto_accept_settings.sql` (crypto payment auto-accept settings)
11. `023_mixer_encrypted_signer_secret.sql` (optional encrypted Swapper signer secret stored from admin panel)
12. `024_default_swap_assets.sql` (default Asset A/B settings for LI.FI Bridge and Swapper)
13. `027_admin_billing_module.sql` (admin billing base schema)
14. `028_billing_profiles_and_item_catalog.sql` (multiple issuer profiles and reusable billing items)
15. `029_billing_ireland_tax_profiles.sql` (Ireland VAT profile defaults)
16. `030_billing_tax_assistant_profile.sql` (business/VAT profile and issue review fields)
17. `031_billing_gross_price_entry.sql` (exact gross-price entry for linked orders and payments)
18. `032_billing_guided_issue_workflow.sql` (guided issue audit fields and EU destination standard VAT rates)
19. `033_add_tron_trx_payments.sql` (optional TRON/TRX customer payments and CoinGecko rate source)
20. `034_billing_default_ireland.sql` (Ireland defaults for newly created billing issuer profiles)
21. `035_billing_document_email_delivery.sql` (auditable invoice PDF delivery by email)
22. `036_billing_order_email_automation.sql` (optional invoice issue/email flow during order activation)
23. `037_admin_swapper_solana_input.sql` (SOL input support, case-sensitive deposit IDs and Swapper fee-visibility setting)
24. `038_hinkal_private_send_settings.sql` (independent Hinkal fee, fee-visibility and notification email settings)
25. `039_hinkal_private_send_input_asset.sql` (admin-selected Hinkal input asset; POL by default, with USDT as an option)

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

`041_panel_subscription_notifications.sql` adds the paid-until notification marker and an optional unique email queue key. Expired panel access enables customer maintenance; each active administrator receives one queued message per expiry date. Delivery uses the existing maintenance/email worker, with no SMTP calls from page requests. Existing email queue inserts keep a null key and their current behavior.

### 042 — Panel access tiers (local)

`042_panel_subscription_tiers.sql` adds separate configuration, paid-term history, payment requests and claimed transfers for panel access. Automation defaults OFF. It does not import customer data or post customer revenue. Settings → Admin Mode initializes the configuration in the current panel currency and backfills paid-term history. See `docs/ADMIN_PANEL_SUBSCRIPTION_TIERS.md` for renewal limits, partial payments, email behavior and local test coverage. No hosting deployment is included in this change.

### 043 — Crypto request status width

`043_crypto_request_status_width.sql` widens only `crypto_deposit_requests.status` from 20 to 32 characters so the 21-character `awaiting_confirmation` status is preserved. Existing request values, amounts and dates remain unchanged. Apply on each database before deploying the updated runtime. The ALTER is safe to repeat.
