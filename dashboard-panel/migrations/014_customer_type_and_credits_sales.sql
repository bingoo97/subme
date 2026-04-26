SET @has_customer_type = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'customers'
    AND COLUMN_NAME = 'customer_type'
);

SET @sql = IF(
  @has_customer_type = 0,
  'ALTER TABLE `customers` ADD COLUMN `customer_type` VARCHAR(20) NOT NULL DEFAULT ''client'' AFTER `status`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `customers`
SET `customer_type` = 'client'
WHERE `customer_type` IS NULL
   OR `customer_type` = ''
   OR `customer_type` = 'customer';

SET @has_credits_sales_enabled = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'app_settings'
    AND COLUMN_NAME = 'credits_sales_enabled'
);

SET @sql = IF(
  @has_credits_sales_enabled = 0,
  'ALTER TABLE `app_settings` ADD COLUMN `credits_sales_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sales_enabled`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
