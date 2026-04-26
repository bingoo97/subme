SET @has_history_visible_from := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'app_settings'
      AND COLUMN_NAME = 'history_visible_from'
);

SET @alter_sql := IF(
    @has_history_visible_from = 0,
    'ALTER TABLE `app_settings` ADD COLUMN `history_visible_from` DATETIME NULL DEFAULT NULL AFTER `support_chat_retention_days`',
    'SELECT 1'
);

PREPARE stmt FROM @alter_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `app_settings`
SET `history_visible_from` = NOW()
WHERE `id` = 1;

DELETE FROM `customer_activity_logs`;
