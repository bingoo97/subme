SET @add_crypto_shared_col = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'app_settings'
        AND COLUMN_NAME = 'crypto_wallet_shared_assignments_enabled'
    ),
    'SELECT 1',
    'ALTER TABLE `app_settings` ADD COLUMN `crypto_wallet_shared_assignments_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `bank_transfers_enabled`'
  )
);
PREPARE stmt FROM @add_crypto_shared_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_bank_shared_col = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'app_settings'
        AND COLUMN_NAME = 'bank_account_shared_assignments_enabled'
    ),
    'SELECT 1',
    'ALTER TABLE `app_settings` ADD COLUMN `bank_account_shared_assignments_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `crypto_wallet_shared_assignments_enabled`'
  )
);
PREPARE stmt FROM @add_bank_shared_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `app_settings`
SET
  `crypto_wallet_shared_assignments_enabled` = COALESCE(`crypto_wallet_shared_assignments_enabled`, 0),
  `bank_account_shared_assignments_enabled` = COALESCE(`bank_account_shared_assignments_enabled`, 1)
WHERE `id` = 1;

SET @drop_crypto_unique = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'crypto_wallet_assignments'
        AND INDEX_NAME = 'uniq_crypto_wallet_assignments_wallet_active_lock'
    ),
    'ALTER TABLE `crypto_wallet_assignments` DROP INDEX `uniq_crypto_wallet_assignments_wallet_active_lock`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @drop_crypto_unique;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_bank_unique = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'bank_account_assignments'
        AND INDEX_NAME = 'uniq_bank_account_assignments_account_active_lock'
    ),
    'ALTER TABLE `bank_account_assignments` DROP INDEX `uniq_bank_account_assignments_account_active_lock`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @drop_bank_unique;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
