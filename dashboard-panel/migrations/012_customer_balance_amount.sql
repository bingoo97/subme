SET @customer_balance_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customers'
      AND COLUMN_NAME = 'balance_amount'
);

SET @customer_balance_sql := IF(
    @customer_balance_exists = 0,
    'ALTER TABLE `customers` ADD COLUMN `balance_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `status`',
    'SELECT 1'
);

PREPARE customer_balance_stmt FROM @customer_balance_sql;
EXECUTE customer_balance_stmt;
DEALLOCATE PREPARE customer_balance_stmt;
