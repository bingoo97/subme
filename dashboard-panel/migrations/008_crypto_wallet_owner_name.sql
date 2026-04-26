SET @wallet_owner_column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'crypto_wallet_addresses'
    AND COLUMN_NAME = 'owner_full_name'
);

SET @wallet_owner_column_sql := IF(
  @wallet_owner_column_exists = 0,
  'ALTER TABLE `crypto_wallet_addresses` ADD COLUMN `owner_full_name` VARCHAR(191) DEFAULT NULL AFTER `label`',
  'SELECT 1'
);

PREPARE wallet_owner_column_stmt FROM @wallet_owner_column_sql;
EXECUTE wallet_owner_column_stmt;
DEALLOCATE PREPARE wallet_owner_column_stmt;

UPDATE `crypto_wallet_addresses`
SET `owner_full_name` = CASE
  WHEN `id` % 8 = 0 THEN 'Oliver Bennett'
  WHEN `id` % 8 = 1 THEN 'Emma Fischer'
  WHEN `id` % 8 = 2 THEN 'Lucas Carter'
  WHEN `id` % 8 = 3 THEN 'Mia Wagner'
  WHEN `id` % 8 = 4 THEN 'Leo Quinn'
  WHEN `id` % 8 = 5 THEN 'Nina Nowak'
  WHEN `id` % 8 = 6 THEN 'Ava Mercer'
  ELSE 'Sofia Meyer'
END
WHERE COALESCE(TRIM(`owner_full_name`), '') = '';

DROP VIEW IF EXISTS `available_crypto_wallet_pool`;
DROP VIEW IF EXISTS `customer_crypto_wallets`;

CREATE VIEW `available_crypto_wallet_pool` AS
SELECT
  wallet.id,
  asset.code AS crypto_asset_code,
  asset.name AS crypto_asset_name,
  wallet.label,
  wallet.owner_full_name,
  wallet.address,
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
