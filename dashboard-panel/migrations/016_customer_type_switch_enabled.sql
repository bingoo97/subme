SET @has_customer_type_switch_enabled = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'app_settings'
    AND COLUMN_NAME = 'customer_type_switch_enabled'
);

SET @sql = IF(
  @has_customer_type_switch_enabled = 0,
  'ALTER TABLE `app_settings` ADD COLUMN `customer_type_switch_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `apps_page_enabled`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
