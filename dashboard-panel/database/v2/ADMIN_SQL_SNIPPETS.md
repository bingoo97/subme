# Manual Payment Administration SQL

These snippets match the current operating model:

- admin manually adds wallet addresses to the pool
- admin manually assigns a wallet to a customer
- one customer can have multiple wallets across different crypto assets
- a dedicated address can be used for each new crypto payment

## 1. Add a new wallet address to the pool

```sql
INSERT INTO crypto_wallet_addresses (
  crypto_asset_id,
  label,
  owner_full_name,
  address,
  memo_tag,
  wallet_provider,
  status,
  is_reusable,
  created_by_admin_user_id,
  notes
) VALUES (
  1,
  'TrustWallet 22',
  'Oliver Bennett',
  'bc1qexampleaddress',
  NULL,
  'Trust Wallet',
  'available',
  0,
  1,
  'Imported manually by admin'
);
```

## 2. List free wallet addresses for one crypto asset

```sql
SELECT *
FROM available_crypto_wallet_pool
WHERE crypto_asset_code = 'BTC'
ORDER BY created_at ASC;
```

## 3. Manually assign one wallet to a customer

```sql
INSERT INTO crypto_wallet_assignments (
  wallet_address_id,
  customer_id,
  order_id,
  assignment_reason,
  status,
  assigned_by_admin_user_id,
  assignment_note
) VALUES (
  15,
  42,
  NULL,
  'manual_customer_wallet',
  'active',
  1,
  'Manual BTC wallet assignment for the customer'
);

UPDATE crypto_wallet_addresses
SET
  status = 'assigned',
  last_assigned_at = NOW()
WHERE id = 15;
```

## 4. Create a dedicated crypto deposit request for a customer

```sql
INSERT INTO crypto_deposit_requests (
  customer_id,
  order_id,
  crypto_asset_id,
  wallet_address_id,
  wallet_assignment_id,
  requested_fiat_amount,
  fiat_currency_id,
  exchange_rate,
  requested_crypto_amount,
  assignment_mode,
  status,
  created_by_admin_user_id,
  expires_at,
  request_note
) VALUES (
  42,
  1001,
  1,
  15,
  20,
  50.00,
  2,
  60000.00000000,
  0.00083333,
  'manual',
  'pending',
  1,
  DATE_ADD(NOW(), INTERVAL 30 MINUTE),
  'New dedicated BTC address for this payment'
);
```

## 5. Show all wallets assigned to one customer

```sql
SELECT *
FROM customer_crypto_wallets
WHERE customer_id = 42
ORDER BY assigned_at DESC;
```

## 6. Release a wallet address

```sql
UPDATE crypto_wallet_assignments
SET
  status = 'released',
  released_at = NOW(),
  released_by_admin_user_id = 1,
  assignment_note = CONCAT(COALESCE(assignment_note, ''), '\nReleased by admin')
WHERE id = 20;

UPDATE crypto_wallet_addresses
SET status = 'available'
WHERE id = 15
  AND is_reusable = 1;
```

## 7. Disable a wallet address permanently

```sql
UPDATE crypto_wallet_addresses
SET
  status = 'disabled',
  disabled_at = NOW(),
  notes = CONCAT(COALESCE(notes, ''), '\nDisabled by admin')
WHERE id = 15;
```

## 8. Add a bank account to the pool

```sql
INSERT INTO bank_accounts (
  currency_id,
  label,
  account_holder_name,
  bank_name,
  bank_address,
  country_code,
  iban,
  account_number,
  swift_bic,
  payment_reference_template,
  transfer_instructions,
  status,
  created_by_admin_user_id,
  notes
) VALUES (
  2,
  'EUR Account 001',
  'Best Pro Sp. z o.o.',
  'Example Bank',
  'Vienna, Austria',
  'AT',
  'AT611904300234573201',
  NULL,
  'BKAUATWW',
  'ORDER-%customer_id%-%request_id%',
  'Send an exact bank transfer and include the provided payment reference.',
  'available',
  1,
  'Manual bank account import'
);
```

## 9. List free bank accounts for one currency

```sql
SELECT *
FROM available_bank_account_pool
WHERE currency_code = 'EUR'
ORDER BY created_at ASC;
```

## 10. Manually assign one bank account to a customer

```sql
INSERT INTO bank_account_assignments (
  bank_account_id,
  customer_id,
  order_id,
  assignment_reason,
  status,
  assigned_by_admin_user_id,
  assignment_note
) VALUES (
  7,
  42,
  1001,
  'manual_bank_transfer',
  'active',
  1,
  'Dedicated EUR account for this customer'
);

UPDATE bank_accounts
SET
  status = 'assigned',
  last_assigned_at = NOW()
WHERE id = 7;
```

## 11. Create a bank transfer payment request

```sql
INSERT INTO bank_transfer_requests (
  customer_id,
  order_id,
  bank_account_id,
  bank_account_assignment_id,
  requested_amount,
  currency_id,
  payment_reference,
  status,
  created_by_admin_user_id,
  expires_at,
  customer_transfer_note
) VALUES (
  42,
  1001,
  7,
  9,
  50.00,
  2,
  'ORDER-42-1001',
  'pending_payment',
  1,
  DATE_ADD(NOW(), INTERVAL 2 DAY),
  'Send the transfer and wait for manual approval'
);
```

## 12. Mark a bank transfer as submitted by the customer

```sql
UPDATE bank_transfer_requests
SET
  status = 'awaiting_review',
  submitted_at = NOW(),
  payer_name = 'John Doe',
  payer_bank_name = 'Customer Bank',
  customer_transfer_note = 'Transfer sent from my private account'
WHERE id = 12;
```

## 13. Approve a bank transfer manually

```sql
UPDATE bank_transfer_requests
SET
  status = 'approved',
  approved_at = NOW(),
  approved_by_admin_user_id = 1,
  admin_review_note = 'Transfer confirmed in the bank dashboard'
WHERE id = 12;

UPDATE orders
SET
  payment_method = 'bank_transfer',
  payment_status = 'paid',
  paid_at = NOW(),
  status = 'processing'
WHERE id = 1001;
```

## 14. Show all bank accounts assigned to one customer

```sql
SELECT *
FROM customer_bank_accounts
WHERE customer_id = 42
ORDER BY assigned_at DESC;
```

## 15. Release a bank account

```sql
UPDATE bank_account_assignments
SET
  status = 'released',
  released_at = NOW(),
  released_by_admin_user_id = 1,
  assignment_note = CONCAT(COALESCE(assignment_note, ''), '\nReleased by admin')
WHERE id = 9;

UPDATE bank_accounts
SET status = 'available'
WHERE id = 7;
```
