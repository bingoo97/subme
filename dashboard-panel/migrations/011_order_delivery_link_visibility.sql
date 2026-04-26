SET @delivery_link_visible_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'delivery_link_visible'
);

SET @delivery_link_visible_sql := IF(
  @delivery_link_visible_exists = 0,
  'ALTER TABLE `orders` ADD COLUMN `delivery_link_visible` TINYINT(1) NOT NULL DEFAULT 0 AFTER `delivery_link`',
  'SELECT 1'
);

PREPARE delivery_link_visible_stmt FROM @delivery_link_visible_sql;
EXECUTE delivery_link_visible_stmt;
DEALLOCATE PREPARE delivery_link_visible_stmt;
