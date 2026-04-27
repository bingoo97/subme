SET NAMES utf8mb4;

-- Add commonly used crypto assets for customer payments.
INSERT INTO `crypto_assets` (
  `code`,
  `name`,
  `coingecko_id`,
  `rate_currency_code`,
  `is_active`
) VALUES
  ('BTC',  'Bitcoin',            'bitcoin',         'USD', 1),
  ('ETH',  'Ethereum',           'ethereum',        'USD', 1),
  ('DOGE', 'Dogecoin',           'dogecoin',        'USD', 1),
  ('BNB',  'Binance Coin',       'binancecoin',     'USD', 1),
  ('USDT', 'Tether',             'tether',          'USD', 1),
  ('CRO',  'Crypto.com Coin',    'crypto-com-chain','USD', 1),
  ('SOL',  'Solana',             'solana',          'USD', 1),
  ('USDC', 'USD Coin',           'usd-coin',        'USD', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `coingecko_id` = VALUES(`coingecko_id`),
  `rate_currency_code` = VALUES(`rate_currency_code`),
  `is_active` = VALUES(`is_active`);

-- Add one sample wallet per crypto asset.
INSERT INTO `crypto_wallet_addresses` (
  `crypto_asset_id`,
  `label`,
  `owner_full_name`,
  `address`,
  `network_code`,
  `memo_tag`,
  `wallet_provider`,
  `status`,
  `is_reusable`,
  `notes`
)
SELECT asset.`id`, src.`label`, src.`owner_full_name`, src.`address`, src.`network_code`, src.`memo_tag`, src.`wallet_provider`, 'available', 1, src.`notes`
FROM (
  SELECT 'BTC' AS `code`,  'TrustWallet 22' AS `label`,  'Oliver Bennett' AS `owner_full_name`, 'bc1q8w6y7n4p5t9v2k3m6r8s0d4f1h7j9l2c5x8z3q' AS `address`, 'bitcoin' AS `network_code`, NULL AS `memo_tag`, 'Trust Wallet' AS `wallet_provider`, 'Sample seed wallet' AS `notes`
  UNION ALL
  SELECT 'ETH',  'MetaMask 5', 'Emma Fischer', '0x9fC4E8b3A2d1F6c7B8e9D0a1B2c3D4e5F6a7B8c9', 'ethereum', NULL, 'MetaMask', 'Sample seed wallet'
  UNION ALL
  SELECT 'DOGE', 'TrustWallet 14', 'Nina Nowak', 'D9xv1w6Qp3Lr8kT2mY5nC7bH4sA9eU2qW', 'dogecoin', NULL, 'Trust Wallet', 'Sample seed wallet'
  UNION ALL
  SELECT 'BNB',  'TrustWallet 31', 'Lucas Carter', '0x3aB6D7E8f90123456789AbCdEf0123456789aBCd', 'bnb', NULL, 'Trust Wallet', 'Sample seed wallet'
  UNION ALL
  SELECT 'USDT', 'TrustWallet 11', 'Mia Wagner', 'TXw7V5R4c9Q2s6M8n1J3k5P7z9A4d2F6h', 'tron', NULL, 'Trust Wallet', 'Sample seed wallet'
  UNION ALL
  SELECT 'CRO',  'Crypto.com 8', 'Leo Quinn', 'cro1jv4x9s8n5q2k6m3w7d0r4t8p1y5z9h2c6l4m8', 'cronos', NULL, 'Crypto.com', 'Sample seed wallet'
  UNION ALL
  SELECT 'SOL',  'Phantom 3', 'Ava Mercer', '9xQeWvG816bUx9EPf5p8e4Lr7GQm7wVnN4JtJ8f9r2Zs', 'solana', NULL, 'Phantom', 'Sample seed wallet'
  UNION ALL
  SELECT 'USDC', 'MetaMask 12', 'Sofia Meyer', '0x7b2C3d4E5f6A7b8C9d0E1f2A3b4C5d6E7f8A9b0C', 'ethereum', NULL, 'MetaMask', 'Sample seed wallet'
) AS src
INNER JOIN `crypto_assets` AS asset
  ON asset.`code` = src.`code`
LEFT JOIN `crypto_wallet_addresses` AS existing_wallet
  ON existing_wallet.`address` = src.`address`
WHERE existing_wallet.`id` IS NULL;

-- Add sample bank accounts.
INSERT INTO `bank_accounts` (
  `currency_id`,
  `label`,
  `account_holder_name`,
  `bank_name`,
  `bank_address`,
  `country_code`,
  `iban`,
  `account_number`,
  `routing_number`,
  `swift_bic`,
  `payment_reference_template`,
  `transfer_instructions`,
  `status`,
  `notes`
)
SELECT
  currency.`id`,
  src.`label`,
  src.`account_holder_name`,
  src.`bank_name`,
  src.`bank_address`,
  src.`country_code`,
  src.`iban`,
  src.`account_number`,
  src.`routing_number`,
  src.`swift_bic`,
  src.`payment_reference_template`,
  src.`transfer_instructions`,
  'available',
  'Sample seeded account'
FROM (
  SELECT 'EUR' AS `currency_code`, 'Primary EUR Settlement' AS `label`, 'Demo Subscription Services Sp. z o.o.' AS `account_holder_name`, 'Santander Bank Polska' AS `bank_name`, 'al. Jana Pawla II 17, 00-854 Warsaw' AS `bank_address`, 'PL' AS `country_code`, 'PL61109010140000071219812874' AS `iban`, '109010140000071219812874' AS `account_number`, NULL AS `routing_number`, 'WBKPPLPP' AS `swift_bic`, 'INV-{ORDER_ID}-C{CUSTOMER_ID}' AS `payment_reference_template`, 'Use payment title exactly as shown in your order details.' AS `transfer_instructions`
  UNION ALL
  SELECT 'EUR', 'Secondary EUR Reserve', 'Demo Subscription Services Ltd', 'Deutsche Bank', 'Taunusanlage 12, 60325 Frankfurt am Main', 'DE', 'DE89370400440532013000', '532013000', NULL, 'COBADEFFXXX', 'INV-{ORDER_ID}-C{CUSTOMER_ID}', 'SEPA transfer only. Include the reference in transfer title.'
  UNION ALL
  SELECT 'USD', 'USD International', 'Demo Subscription Services LLC', 'JPMorgan Chase Bank', '270 Park Ave, New York, NY 10017', 'US', NULL, '021000021998877665', '021000021', 'CHASUS33', 'INV-{ORDER_ID}-C{CUSTOMER_ID}', 'Wire transfer in USD. Add the payment reference.'
) AS src
INNER JOIN `currencies` AS currency
  ON currency.`code` = src.`currency_code`
LEFT JOIN `bank_accounts` AS existing_account
  ON existing_account.`account_number` = src.`account_number`
WHERE existing_account.`id` IS NULL;

-- Assign one BTC wallet to demo@demo.com.
INSERT INTO `crypto_wallet_assignments` (
  `wallet_address_id`,
  `customer_id`,
  `assignment_reason`,
  `status`,
  `assigned_at`,
  `assignment_note`
)
SELECT
  wallet.`id`,
  customer.`id`,
  'manual_seed',
  'active',
  NOW(),
  'Seed assignment for demo customer'
FROM `customers` AS customer
INNER JOIN `crypto_assets` AS asset
  ON asset.`code` = 'BTC'
INNER JOIN `crypto_wallet_addresses` AS wallet
  ON wallet.`crypto_asset_id` = asset.`id`
 AND wallet.`address` = 'bc1q8w6y7n4p5t9v2k3m6r8s0d4f1h7j9l2c5x8z3q'
LEFT JOIN `crypto_wallet_assignments` AS existing_assignment
  ON existing_assignment.`wallet_address_id` = wallet.`id`
 AND existing_assignment.`customer_id` = customer.`id`
 AND existing_assignment.`status` IN ('reserved', 'active')
WHERE customer.`email` = 'demo@demo.com'
  AND existing_assignment.`id` IS NULL;

-- Assign one EUR bank account to demo@demo.com.
INSERT INTO `bank_account_assignments` (
  `bank_account_id`,
  `customer_id`,
  `assignment_reason`,
  `status`,
  `assigned_at`,
  `assignment_note`
)
SELECT
  account.`id`,
  customer.`id`,
  'manual_seed',
  'active',
  NOW(),
  'Seed assignment for demo customer'
FROM `customers` AS customer
INNER JOIN `bank_accounts` AS account
  ON account.`account_number` = '109010140000071219812874'
LEFT JOIN `bank_account_assignments` AS existing_assignment
  ON existing_assignment.`bank_account_id` = account.`id`
 AND existing_assignment.`customer_id` = customer.`id`
 AND existing_assignment.`status` IN ('reserved', 'active')
WHERE customer.`email` = 'demo@demo.com'
  AND existing_assignment.`id` IS NULL;

