# Legacy To New Mapping

This is the target mapping from the old schema to the new canonical database.

## Direct mappings

| Legacy table | New table | Notes |
|---|---|---|
| `currency` | `currencies` | direct dictionary mapping |
| `settings` + active `resellers` row | `app_settings` | multi-reseller branding collapsed into single-installation settings |
| `admin_rangi` | `admin_roles` | access levels normalized |
| `admin` | `admin_users` | passwords can be imported first as legacy hashes |
| `menu_admin` | `admin_navigation_items` | admin menu becomes explicit navigation config |
| `users` | `customers` | single customer table for the product |
| `history` | `customer_activity_logs` | generic customer event log |
| `products_providers` | `product_providers` | provider configuration kept |
| `products` | `products` | current product model kept and cleaned |
| `products_users` | `orders` | current order model becomes canonical order source |
| `products_extend` | `order_renewals` | renewals kept as separate normalized records |
| `refy` | `referrals` | referral model normalized |
| `cryptocurrency` | `crypto_assets` | crypto asset dictionary |
| `wplaty_crypto` | `crypto_deposit_requests` | old crypto payment requests |
| `produkty_chat` | `support_conversations` + `support_messages` | customer live chat import |
| `produkty_chat_admin` | `support_conversations` + `support_messages` | internal/admin chat import if needed |
| `news` | `news_posts` | general news source |
| `resellers_news` | `news_posts` | tenant-specific news flattened into single-installation news |
| `podstrony` | `static_pages` | static content pages |
| `komunikaty` | `email_templates` | system email templates |

## Legacy order compatibility

| Legacy table | New table | Notes |
|---|---|---|
| `produkty_user` | `orders` | imported as `source_system = legacy`; fields like `device_mac`, `transaction_reference`, `file_type`, `adult_content_enabled` stay available |
| `wplaty` | not a standalone table | should become payment/order timeline or be merged into order/payment state during import |
| `invoices` | `crypto_deposit_requests` or future payment ledger | depends on current production usage |
| `crypto_payments` | `crypto_deposit_transactions` | best treated as blockchain/callback transaction log |

## New-only tables

These tables do not exist in the old schema and are introduced because the new product needs them:

- `crypto_wallet_addresses`
- `crypto_wallet_assignments`
- `crypto_deposit_transactions`
- `bank_accounts`
- `bank_account_assignments`
- `bank_transfer_requests`
- `order_status_events`
- `outbound_email_queue`

## New operational rules

- wallet address pool is maintained manually by the admin
- bank account pool is maintained manually by the admin
- customers can have many wallet assignments across different crypto assets
- customers can have many assigned bank accounts over time
- active wallet ownership is tracked in `crypto_wallet_assignments`
- active bank account ownership is tracked in `bank_account_assignments`
- open deposit requests are tracked separately in `crypto_deposit_requests`
- open bank transfer requests are tracked separately in `bank_transfer_requests`
- the old schema has no clean equivalent for these controls, so they stay new-only

## Modules intentionally not carried into the new core

These are excluded on purpose because they do not match the current product direction:

- `resellers`
- `forum`
- `live_box`
- legacy product auction/size structures
- duplicated mixed-language table families

If any of them becomes required again, it should be rebuilt cleanly in English instead of copied forward from legacy.
