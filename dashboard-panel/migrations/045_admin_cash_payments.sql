CREATE TABLE IF NOT EXISTS admin_cash_payments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 operation_key CHAR(64) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NULL,
 renewal_id BIGINT UNSIGNED NULL,
 purpose VARCHAR(24) NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 currency_id INT UNSIGNED NOT NULL,
 created_by_admin_user_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(id), UNIQUE KEY cash_operation(operation_key),
 KEY cash_customer(customer_id,created_at), KEY cash_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
