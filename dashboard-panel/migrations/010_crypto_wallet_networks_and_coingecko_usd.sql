SET @has_network_code := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'crypto_wallet_addresses'
    AND COLUMN_NAME = 'network_code'
);

SET @add_network_code_sql := IF(
  @has_network_code = 0,
  'ALTER TABLE `crypto_wallet_addresses` ADD COLUMN `network_code` VARCHAR(40) NOT NULL DEFAULT '''' AFTER `address`',
  'SELECT 1'
);

PREPARE add_network_code_stmt FROM @add_network_code_sql;
EXECUTE add_network_code_stmt;
DEALLOCATE PREPARE add_network_code_stmt;

UPDATE `crypto_assets`
SET `coingecko_id` = CASE UPPER(TRIM(`code`))
  WHEN 'BTC' THEN 'bitcoin'
  WHEN 'BCH' THEN 'bitcoin-cash'
  WHEN 'LTC' THEN 'litecoin'
  WHEN 'ETH' THEN 'ethereum'
  WHEN 'DOGE' THEN 'dogecoin'
  WHEN 'BNB' THEN 'binancecoin'
  WHEN 'USDT' THEN 'tether'
  WHEN 'CRO' THEN 'crypto-com-chain'
  WHEN 'SOL' THEN 'solana'
  WHEN 'USDC' THEN 'usd-coin'
  WHEN 'MATIC' THEN 'matic-network'
  WHEN 'XRP' THEN 'ripple'
  ELSE `coingecko_id`
END
WHERE COALESCE(TRIM(`coingecko_id`), '') = '';

UPDATE `crypto_assets`
SET `rate_currency_code` = 'USD'
WHERE COALESCE(TRIM(`rate_currency_code`), '') <> 'USD';

UPDATE `crypto_wallet_addresses` AS wallet
INNER JOIN `crypto_assets` AS asset
  ON asset.`id` = wallet.`crypto_asset_id`
SET wallet.`network_code` = CASE
  WHEN UPPER(TRIM(asset.`code`)) = 'BTC' THEN 'bitcoin'
  WHEN UPPER(TRIM(asset.`code`)) = 'BCH' THEN 'bitcoin-cash'
  WHEN UPPER(TRIM(asset.`code`)) = 'LTC' THEN 'litecoin'
  WHEN UPPER(TRIM(asset.`code`)) = 'DOGE' THEN 'dogecoin'
  WHEN UPPER(TRIM(asset.`code`)) = 'ETH' THEN 'ethereum'
  WHEN UPPER(TRIM(asset.`code`)) = 'BNB' THEN 'bnb'
  WHEN UPPER(TRIM(asset.`code`)) = 'CRO' THEN 'cronos'
  WHEN UPPER(TRIM(asset.`code`)) = 'SOL' THEN 'solana'
  WHEN UPPER(TRIM(asset.`code`)) = 'MATIC' THEN 'polygon'
  WHEN UPPER(TRIM(asset.`code`)) = 'XRP' THEN 'ripple'
  WHEN UPPER(TRIM(asset.`code`)) = 'USDT' THEN CASE
    WHEN wallet.`address` LIKE 'T%' THEN 'tron'
    WHEN LOWER(wallet.`address`) LIKE '0x%' THEN 'ethereum'
    WHEN LOWER(wallet.`address`) LIKE 'bnb%' THEN 'bnb'
    WHEN LOWER(wallet.`address`) LIKE 'cro%' THEN 'cronos'
    WHEN wallet.`address` REGEXP '^[1-9A-HJ-NP-Za-km-z]{32,}$' THEN 'solana'
    ELSE 'ethereum'
  END
  WHEN UPPER(TRIM(asset.`code`)) = 'USDC' THEN CASE
    WHEN LOWER(wallet.`address`) LIKE '0x%' THEN 'ethereum'
    WHEN LOWER(wallet.`address`) LIKE 'bnb%' THEN 'bnb'
    WHEN wallet.`address` LIKE 'T%' THEN 'tron'
    WHEN wallet.`address` REGEXP '^[1-9A-HJ-NP-Za-km-z]{32,}$' THEN 'solana'
    ELSE 'ethereum'
  END
  ELSE wallet.`network_code`
END
WHERE COALESCE(TRIM(wallet.`network_code`), '') = '';

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
