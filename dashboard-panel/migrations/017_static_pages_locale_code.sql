SET @has_static_pages_locale_code = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'static_pages'
    AND COLUMN_NAME = 'locale_code'
);

SET @sql = IF(
  @has_static_pages_locale_code = 0,
  'ALTER TABLE `static_pages` ADD COLUMN `locale_code` VARCHAR(8) NOT NULL DEFAULT ''pl'' AFTER `page_type`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `static_pages`
SET `locale_code` = 'pl'
WHERE `locale_code` IS NULL OR `locale_code` = '';

UPDATE `static_pages`
SET `locale_code` = 'en'
WHERE `slug` REGEXP '-en$';

UPDATE `static_pages`
SET `locale_code` = 'pl'
WHERE `slug` REGEXP '-pl$';
