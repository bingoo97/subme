-- Preserve the original sale when an existing subscription is renewed.

-- Idempotent; historical recovery is performed by the application helper.

SET @sales_ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'initial_sale_product_id'), 'SELECT 1', 'ALTER TABLE `orders` ADD COLUMN `initial_sale_product_id` INT UNSIGNED DEFAULT NULL');
PREPARE sales_stmt FROM @sales_ddl;
EXECUTE sales_stmt;
DEALLOCATE PREPARE sales_stmt;

SET @sales_ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'initial_sale_amount'), 'SELECT 1', 'ALTER TABLE `orders` ADD COLUMN `initial_sale_amount` DECIMAL(10,2) DEFAULT NULL');
PREPARE sales_stmt FROM @sales_ddl;
EXECUTE sales_stmt;
DEALLOCATE PREPARE sales_stmt;

SET @sales_ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'initial_sale_currency_id'), 'SELECT 1', 'ALTER TABLE `orders` ADD COLUMN `initial_sale_currency_id` TINYINT UNSIGNED DEFAULT NULL');
PREPARE sales_stmt FROM @sales_ddl;
EXECUTE sales_stmt;
DEALLOCATE PREPARE sales_stmt;

SET @sales_ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'initial_sale_paid_at'), 'SELECT 1', 'ALTER TABLE `orders` ADD COLUMN `initial_sale_paid_at` DATETIME DEFAULT NULL');
PREPARE sales_stmt FROM @sales_ddl;
EXECUTE sales_stmt;
DEALLOCATE PREPARE sales_stmt;

SET @sales_ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'order_renewals' AND column_name = 'paid_at'), 'SELECT 1', 'ALTER TABLE `order_renewals` ADD COLUMN `paid_at` DATETIME DEFAULT NULL');
PREPARE sales_stmt FROM @sales_ddl;
EXECUTE sales_stmt;
DEALLOCATE PREPARE sales_stmt;
