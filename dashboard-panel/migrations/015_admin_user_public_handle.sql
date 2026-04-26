SET @has_public_handle = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'admin_users'
    AND COLUMN_NAME = 'public_handle'
);

SET @sql = IF(
  @has_public_handle = 0,
  'ALTER TABLE `admin_users` ADD COLUMN `public_handle` VARCHAR(80) DEFAULT NULL AFTER `email`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `admin_users`
SET `public_handle` = CONCAT('support-', `id`)
WHERE `public_handle` IS NULL
   OR TRIM(`public_handle`) = '';
