SET @has_mixer_enabled = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_enabled'
);
SET @sql = IF(@has_mixer_enabled = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_enabled` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO `crypto_assets` (`code`, `name`, `coingecko_id`, `rate_currency_code`, `is_active`) VALUES
  ('BNB', 'BNB', 'binancecoin', 'USD', 1),
  ('POL', 'Polygon', 'polygon-ecosystem-token', 'USD', 1)
ON DUPLICATE KEY UPDATE
  `name` = IF(COALESCE(TRIM(`name`), '') = '', VALUES(`name`), `name`),
  `coingecko_id` = CASE
    WHEN UPPER(`code`) IN ('BNB', 'POL') THEN VALUES(`coingecko_id`)
    ELSE IF(COALESCE(TRIM(`coingecko_id`), '') = '', VALUES(`coingecko_id`), `coingecko_id`)
  END,
  `rate_currency_code` = IF(COALESCE(TRIM(`rate_currency_code`), '') = '', 'USD', `rate_currency_code`),
  `is_active` = CASE WHEN UPPER(`code`) IN ('BNB', 'POL') THEN 1 ELSE `is_active` END;

SET @has_mixer_allowed_input_assets = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_allowed_input_assets'
);
SET @sql = IF(@has_mixer_allowed_input_assets = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_allowed_input_assets` VARCHAR(64) DEFAULT ''BTC,DOGE'' AFTER `mixer_enabled`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_allowed_output_assets = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_allowed_output_assets'
);
SET @sql = IF(@has_mixer_allowed_output_assets = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_allowed_output_assets` VARCHAR(120) DEFAULT ''POLYGON_POL'' AFTER `mixer_allowed_input_assets`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_allowed_networks = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_allowed_networks'
);
SET @sql = IF(@has_mixer_allowed_networks = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_allowed_networks` VARCHAR(120) DEFAULT ''polygon'' AFTER `mixer_allowed_output_assets`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_fee_percent = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_fee_percent'
);
SET @sql = IF(@has_mixer_fee_percent = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_fee_percent` DECIMAL(5,2) NOT NULL DEFAULT 1.50 AFTER `mixer_allowed_networks`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_max_payout_usd = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_max_payout_usd'
);
SET @sql = IF(@has_mixer_max_payout_usd = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_max_payout_usd` DECIMAL(12,2) NOT NULL DEFAULT 500.00 AFTER `mixer_fee_percent`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_daily_payout_limit_usd = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_daily_payout_limit_usd'
);
SET @sql = IF(@has_mixer_daily_payout_limit_usd = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_daily_payout_limit_usd` DECIMAL(12,2) NOT NULL DEFAULT 1500.00 AFTER `mixer_max_payout_usd`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_show_pool_liquidity = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_show_pool_liquidity'
);
SET @sql = IF(@has_mixer_show_pool_liquidity = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_show_pool_liquidity` TINYINT(1) NOT NULL DEFAULT 1 AFTER `mixer_daily_payout_limit_usd`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_btc_confirmations_required = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_btc_confirmations_required'
);
SET @sql = IF(@has_mixer_btc_confirmations_required = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_btc_confirmations_required` SMALLINT UNSIGNED NOT NULL DEFAULT 3 AFTER `mixer_show_pool_liquidity`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_doge_confirmations_required = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_doge_confirmations_required'
);
SET @sql = IF(@has_mixer_doge_confirmations_required = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_doge_confirmations_required` SMALLINT UNSIGNED NOT NULL DEFAULT 20 AFTER `mixer_btc_confirmations_required`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_quote_lifetime_minutes = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_quote_lifetime_minutes'
);
SET @sql = IF(@has_mixer_quote_lifetime_minutes = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_quote_lifetime_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER `mixer_doge_confirmations_required`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_detection_provider = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_detection_provider'
);
SET @sql = IF(@has_mixer_detection_provider = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_detection_provider` VARCHAR(40) NOT NULL DEFAULT ''esplora'' AFTER `mixer_quote_lifetime_minutes`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_btc_deposit_address = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_btc_deposit_address'
);
SET @sql = IF(@has_mixer_btc_deposit_address = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_btc_deposit_address` VARCHAR(128) DEFAULT NULL AFTER `mixer_detection_provider`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_doge_deposit_address = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_doge_deposit_address'
);
SET @sql = IF(@has_mixer_doge_deposit_address = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_doge_deposit_address` VARCHAR(128) DEFAULT NULL AFTER `mixer_btc_deposit_address`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_polygon_network_mode = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_polygon_network_mode'
);
SET @sql = IF(@has_mixer_polygon_network_mode = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_polygon_network_mode` VARCHAR(20) NOT NULL DEFAULT ''mainnet'' AFTER `mixer_doge_deposit_address`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_polygon_vault_contract = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_polygon_vault_contract'
);
SET @sql = IF(@has_mixer_polygon_vault_contract = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_polygon_vault_contract` VARCHAR(64) DEFAULT NULL AFTER `mixer_polygon_network_mode`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_polygon_rpc_url = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_polygon_rpc_url'
);
SET @sql = IF(@has_mixer_polygon_rpc_url = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_polygon_rpc_url` VARCHAR(255) DEFAULT NULL AFTER `mixer_polygon_vault_contract`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_polygon_testnet_vault_contract = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_polygon_testnet_vault_contract'
);
SET @sql = IF(@has_mixer_polygon_testnet_vault_contract = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_polygon_testnet_vault_contract` VARCHAR(64) DEFAULT NULL AFTER `mixer_polygon_rpc_url`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_polygon_testnet_rpc_url = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_polygon_testnet_rpc_url'
);
SET @sql = IF(@has_mixer_polygon_testnet_rpc_url = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_polygon_testnet_rpc_url` VARCHAR(255) DEFAULT NULL AFTER `mixer_polygon_testnet_vault_contract`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_bsc_network_mode = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_bsc_network_mode'
);
SET @sql = IF(@has_mixer_bsc_network_mode = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_bsc_network_mode` VARCHAR(20) NOT NULL DEFAULT ''mainnet'' AFTER `mixer_polygon_testnet_rpc_url`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_bsc_vault_contract = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_bsc_vault_contract'
);
SET @sql = IF(@has_mixer_bsc_vault_contract = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_bsc_vault_contract` VARCHAR(64) DEFAULT NULL AFTER `mixer_bsc_network_mode`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_bsc_rpc_url = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_bsc_rpc_url'
);
SET @sql = IF(@has_mixer_bsc_rpc_url = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_bsc_rpc_url` VARCHAR(255) DEFAULT NULL AFTER `mixer_bsc_vault_contract`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_bsc_testnet_vault_contract = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_bsc_testnet_vault_contract'
);
SET @sql = IF(@has_mixer_bsc_testnet_vault_contract = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_bsc_testnet_vault_contract` VARCHAR(64) DEFAULT NULL AFTER `mixer_bsc_rpc_url`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_bsc_testnet_rpc_url = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_bsc_testnet_rpc_url'
);
SET @sql = IF(@has_mixer_bsc_testnet_rpc_url = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_bsc_testnet_rpc_url` VARCHAR(255) DEFAULT NULL AFTER `mixer_bsc_testnet_vault_contract`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_notification_email = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_notification_email'
);
SET @sql = IF(@has_mixer_notification_email = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_notification_email` VARCHAR(191) DEFAULT NULL AFTER `mixer_bsc_testnet_rpc_url`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_mixer_auto_payout_enabled = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_settings' AND COLUMN_NAME = 'mixer_auto_payout_enabled'
);
SET @sql = IF(@has_mixer_auto_payout_enabled = 0, 'ALTER TABLE `app_settings` ADD COLUMN `mixer_auto_payout_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `mixer_notification_email`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `admin_mixer_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` VARCHAR(40) NOT NULL,
  `created_by_admin_user_id` INT UNSIGNED DEFAULT NULL,
  `input_asset` VARCHAR(12) NOT NULL,
  `output_asset` VARCHAR(40) NOT NULL,
  `payout_network` VARCHAR(40) NOT NULL DEFAULT 'polygon',
  `payout_network_mode` VARCHAR(20) NOT NULL DEFAULT 'mainnet',
  `payout_chain_id` INT UNSIGNED DEFAULT NULL,
  `recipient_address` VARCHAR(128) NOT NULL,
  `deposit_address` VARCHAR(128) DEFAULT NULL,
  `deposit_derivation_path` VARCHAR(120) DEFAULT NULL,
  `expected_input_amount` DECIMAL(24,12) DEFAULT NULL,
  `detected_input_amount` DECIMAL(24,12) DEFAULT NULL,
  `payout_amount` DECIMAL(24,12) DEFAULT NULL,
  `quote_rate_usd` DECIMAL(24,12) DEFAULT NULL,
  `quote_output_rate_usd` DECIMAL(24,12) DEFAULT NULL,
  `fee_percent` DECIMAL(5,2) NOT NULL DEFAULT 1.50,
  `fee_amount_usd` DECIMAL(12,2) DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
  `confirmations` INT UNSIGNED NOT NULL DEFAULT 0,
  `confirmations_required` INT UNSIGNED NOT NULL DEFAULT 3,
  `deposit_txid` VARCHAR(191) DEFAULT NULL,
  `payout_txid` VARCHAR(191) DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `detected_at` DATETIME DEFAULT NULL,
  `confirmed_at` DATETIME DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_mixer_orders_public_id` (`public_id`),
  KEY `idx_admin_mixer_orders_status` (`status`),
  KEY `idx_admin_mixer_orders_deposit_address` (`deposit_address`),
  KEY `idx_admin_mixer_orders_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_admin_mixer_orders_payout_network = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_mixer_orders' AND COLUMN_NAME = 'payout_network'
);
SET @sql = IF(@has_admin_mixer_orders_payout_network = 0, 'ALTER TABLE `admin_mixer_orders` ADD COLUMN `payout_network` VARCHAR(40) NOT NULL DEFAULT ''polygon'' AFTER `output_asset`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_admin_mixer_orders_payout_network_mode = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_mixer_orders' AND COLUMN_NAME = 'payout_network_mode'
);
SET @sql = IF(@has_admin_mixer_orders_payout_network_mode = 0, 'ALTER TABLE `admin_mixer_orders` ADD COLUMN `payout_network_mode` VARCHAR(20) NOT NULL DEFAULT ''mainnet'' AFTER `payout_network`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_admin_mixer_orders_payout_chain_id = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_mixer_orders' AND COLUMN_NAME = 'payout_chain_id'
);
SET @sql = IF(@has_admin_mixer_orders_payout_chain_id = 0, 'ALTER TABLE `admin_mixer_orders` ADD COLUMN `payout_chain_id` INT UNSIGNED DEFAULT NULL AFTER `payout_network_mode`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_admin_mixer_orders_quote_output_rate_usd = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_mixer_orders' AND COLUMN_NAME = 'quote_output_rate_usd'
);
SET @sql = IF(@has_admin_mixer_orders_quote_output_rate_usd = 0, 'ALTER TABLE `admin_mixer_orders` ADD COLUMN `quote_output_rate_usd` DECIMAL(24,12) DEFAULT NULL AFTER `quote_rate_usd`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_admin_mixer_orders_confirmations_required = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_mixer_orders' AND COLUMN_NAME = 'confirmations_required'
);
SET @sql = IF(@has_admin_mixer_orders_confirmations_required = 0, 'ALTER TABLE `admin_mixer_orders` ADD COLUMN `confirmations_required` INT UNSIGNED NOT NULL DEFAULT 3 AFTER `confirmations`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `admin_mixer_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(60) NOT NULL,
  `payload_json` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_mixer_events_order_id` (`order_id`),
  KEY `idx_admin_mixer_events_event_type` (`event_type`),
  KEY `idx_admin_mixer_events_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_mixer_deposit_txs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `input_asset` VARCHAR(12) NOT NULL,
  `deposit_address` VARCHAR(128) NOT NULL,
  `txid` VARCHAR(191) NOT NULL,
  `vout` INT UNSIGNED NOT NULL DEFAULT 0,
  `amount` DECIMAL(24,12) NOT NULL DEFAULT 0.000000000000,
  `confirmations` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(30) NOT NULL DEFAULT 'seen',
  `first_seen_at` DATETIME DEFAULT NULL,
  `confirmed_at` DATETIME DEFAULT NULL,
  `raw_json` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_mixer_deposit_txs_asset_tx_vout` (`input_asset`, `txid`, `vout`),
  KEY `idx_admin_mixer_deposit_txs_order_id` (`order_id`),
  KEY `idx_admin_mixer_deposit_txs_address` (`deposit_address`),
  KEY `idx_admin_mixer_deposit_txs_status` (`status`),
  KEY `idx_admin_mixer_deposit_txs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_mixer_payout_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `payout_network` VARCHAR(40) NOT NULL,
  `payout_network_mode` VARCHAR(20) NOT NULL DEFAULT 'mainnet',
  `payout_chain_id` INT UNSIGNED DEFAULT NULL,
  `output_asset` VARCHAR(40) NOT NULL,
  `token_address` VARCHAR(64) DEFAULT NULL,
  `recipient_address` VARCHAR(128) NOT NULL,
  `payout_amount` DECIMAL(24,12) NOT NULL DEFAULT 0.000000000000,
  `txid` VARCHAR(191) DEFAULT NULL,
  `request_hash` VARCHAR(191) DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `error_message` VARCHAR(1000) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_mixer_payout_attempts_order_id` (`order_id`),
  KEY `idx_admin_mixer_payout_attempts_status` (`status`),
  KEY `idx_admin_mixer_payout_attempts_txid` (`txid`),
  KEY `idx_admin_mixer_payout_attempts_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
