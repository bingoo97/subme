SET NAMES utf8mb4;

SET @has_url_replacement_from := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'product_providers'
    AND column_name = 'url_replacement_from'
);
SET @sql_url_replacement_from := IF(
  @has_url_replacement_from = 0,
  'ALTER TABLE `product_providers` ADD COLUMN `url_replacement_from` VARCHAR(255) DEFAULT NULL AFTER `supports_url_replacement`',
  'SELECT 1'
);
PREPARE stmt_url_replacement_from FROM @sql_url_replacement_from;
EXECUTE stmt_url_replacement_from;
DEALLOCATE PREPARE stmt_url_replacement_from;

SET @has_url_replacement_to := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'product_providers'
    AND column_name = 'url_replacement_to'
);
SET @sql_url_replacement_to := IF(
  @has_url_replacement_to = 0,
  'ALTER TABLE `product_providers` ADD COLUMN `url_replacement_to` VARCHAR(255) DEFAULT NULL AFTER `url_replacement_from`',
  'SELECT 1'
);
PREPARE stmt_url_replacement_to FROM @sql_url_replacement_to;
EXECUTE stmt_url_replacement_to;
DEALLOCATE PREPARE stmt_url_replacement_to;
