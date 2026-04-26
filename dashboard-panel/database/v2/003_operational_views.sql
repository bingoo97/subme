SET NAMES utf8mb4;

DROP VIEW IF EXISTS `available_crypto_wallet_pool`;
DROP VIEW IF EXISTS `available_bank_account_pool`;
DROP VIEW IF EXISTS `customer_crypto_wallets`;
DROP VIEW IF EXISTS `customer_bank_accounts`;
DROP VIEW IF EXISTS `open_crypto_deposit_requests`;
DROP VIEW IF EXISTS `open_bank_transfer_requests`;

CREATE VIEW `available_crypto_wallet_pool` AS
SELECT
  wallet.id,
  asset.code AS crypto_asset_code,
  asset.name AS crypto_asset_name,
  wallet.label,
  wallet.owner_full_name,
  wallet.address,
  wallet.network_code,
  wallet.memo_tag,
  wallet.wallet_provider,
  wallet.is_reusable,
  wallet.last_assigned_at,
  wallet.last_checked_at,
  wallet.created_at
FROM `crypto_wallet_addresses` AS wallet
INNER JOIN `crypto_assets` AS asset
  ON asset.id = wallet.crypto_asset_id
LEFT JOIN `crypto_wallet_assignments` AS assignment
  ON assignment.wallet_address_id = wallet.id
 AND assignment.status IN ('reserved', 'active')
WHERE wallet.status = 'available'
  AND wallet.disabled_at IS NULL
  AND assignment.id IS NULL;

CREATE VIEW `customer_crypto_wallets` AS
SELECT
  assignment.id AS wallet_assignment_id,
  assignment.customer_id,
  customer.email AS customer_email,
  asset.code AS crypto_asset_code,
  asset.name AS crypto_asset_name,
  wallet.id AS wallet_address_id,
  wallet.label,
  wallet.owner_full_name,
  wallet.address,
  wallet.network_code,
  wallet.memo_tag,
  wallet.wallet_provider,
  assignment.assignment_reason,
  assignment.status,
  assignment.assigned_at,
  assignment.released_at,
  assignment.assignment_note
FROM `crypto_wallet_assignments` AS assignment
INNER JOIN `customers` AS customer
  ON customer.id = assignment.customer_id
INNER JOIN `crypto_wallet_addresses` AS wallet
  ON wallet.id = assignment.wallet_address_id
INNER JOIN `crypto_assets` AS asset
  ON asset.id = wallet.crypto_asset_id;

CREATE VIEW `available_bank_account_pool` AS
SELECT
  account.id,
  currency.code AS currency_code,
  currency.name AS currency_name,
  account.label,
  account.account_holder_name,
  account.bank_name,
  account.bank_address,
  account.country_code,
  account.iban,
  account.account_number,
  account.swift_bic,
  account.payment_reference_template,
  account.transfer_instructions,
  account.last_assigned_at,
  account.created_at
FROM `bank_accounts` AS account
INNER JOIN `currencies` AS currency
  ON currency.id = account.currency_id
LEFT JOIN `bank_account_assignments` AS assignment
  ON assignment.bank_account_id = account.id
 AND assignment.status IN ('reserved', 'active')
WHERE account.status = 'available'
  AND account.disabled_at IS NULL
  AND assignment.id IS NULL;

CREATE VIEW `customer_bank_accounts` AS
SELECT
  assignment.id AS bank_account_assignment_id,
  assignment.customer_id,
  customer.email AS customer_email,
  account.id AS bank_account_id,
  currency.code AS currency_code,
  account.label,
  account.account_holder_name,
  account.bank_name,
  account.bank_address,
  account.country_code,
  account.iban,
  account.account_number,
  account.routing_number,
  account.swift_bic,
  account.payment_reference_template,
  account.transfer_instructions,
  assignment.assignment_reason,
  assignment.status,
  assignment.assigned_at,
  assignment.released_at,
  assignment.assignment_note
FROM `bank_account_assignments` AS assignment
INNER JOIN `customers` AS customer
  ON customer.id = assignment.customer_id
INNER JOIN `bank_accounts` AS account
  ON account.id = assignment.bank_account_id
INNER JOIN `currencies` AS currency
  ON currency.id = account.currency_id;

CREATE VIEW `open_crypto_deposit_requests` AS
SELECT
  request.id,
  request.customer_id,
  customer.email AS customer_email,
  request.order_id,
  asset.code AS crypto_asset_code,
  request.requested_fiat_amount,
  request.requested_crypto_amount,
  request.exchange_rate,
  request.status,
  wallet.address,
  wallet.network_code,
  wallet.memo_tag,
  request.requested_at,
  request.expires_at
FROM `crypto_deposit_requests` AS request
INNER JOIN `customers` AS customer
  ON customer.id = request.customer_id
INNER JOIN `crypto_assets` AS asset
  ON asset.id = request.crypto_asset_id
INNER JOIN `crypto_wallet_addresses` AS wallet
  ON wallet.id = request.wallet_address_id
WHERE request.status IN ('pending', 'awaiting_confirmation');

CREATE VIEW `open_bank_transfer_requests` AS
SELECT
  request.id,
  request.customer_id,
  customer.email AS customer_email,
  request.order_id,
  request.requested_amount,
  currency.code AS currency_code,
  request.payment_reference,
  request.status,
  account.account_holder_name,
  account.bank_name,
  account.bank_address,
  account.country_code,
  account.iban,
  account.account_number,
  account.swift_bic,
  account.transfer_instructions,
  request.requested_at,
  request.expires_at
FROM `bank_transfer_requests` AS request
INNER JOIN `customers` AS customer
  ON customer.id = request.customer_id
INNER JOIN `bank_accounts` AS account
  ON account.id = request.bank_account_id
INNER JOIN `currencies` AS currency
  ON currency.id = request.currency_id
WHERE request.status IN ('pending_payment', 'awaiting_review');
