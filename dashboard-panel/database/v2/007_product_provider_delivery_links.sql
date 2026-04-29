SET @migration_name = '007_product_provider_delivery_links';

INSERT INTO `schema_migrations` (`migration_name`)
SELECT @migration_name
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1
  FROM `schema_migrations`
  WHERE `migration_name` = @migration_name
);

SET @supports_delivery_links_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_providers'
    AND COLUMN_NAME = 'supports_delivery_links'
);

SET @supports_delivery_links_sql = IF(
  @supports_delivery_links_exists = 0,
  'ALTER TABLE `product_providers` ADD COLUMN `supports_delivery_links` TINYINT(1) NOT NULL DEFAULT 1 AFTER `supports_manual_delivery`',
  'SELECT 1'
);

PREPARE stmt_supports_delivery_links FROM @supports_delivery_links_sql;
EXECUTE stmt_supports_delivery_links;
DEALLOCATE PREPARE stmt_supports_delivery_links;
