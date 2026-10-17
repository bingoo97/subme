-- A bounded, fair queue avoids repeatedly scanning the same oldest requests.
SET @crypto_queue_ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'crypto_deposit_requests' AND column_name = 'blockchain_checked_at'),
    'SELECT 1', 'ALTER TABLE crypto_deposit_requests ADD COLUMN blockchain_checked_at DATETIME DEFAULT NULL');
PREPARE crypto_queue_stmt FROM @crypto_queue_ddl;
EXECUTE crypto_queue_stmt;
DEALLOCATE PREPARE crypto_queue_stmt;
SET @crypto_queue_ddl = IF(EXISTS(SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'crypto_deposit_requests' AND index_name = 'idx_crypto_scan_queue'),
    'SELECT 1', 'CREATE INDEX idx_crypto_scan_queue ON crypto_deposit_requests (status, blockchain_checked_at, requested_at, id)');
PREPARE crypto_queue_stmt FROM @crypto_queue_ddl;
EXECUTE crypto_queue_stmt;
DEALLOCATE PREPARE crypto_queue_stmt;

SET @crypto_queue_ddl = IF(EXISTS(SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'crypto_deposit_transactions' AND index_name = 'idx_crypto_wallet_received'),
    'SELECT 1', 'CREATE INDEX idx_crypto_wallet_received ON crypto_deposit_transactions (wallet_address_id, received_at, id)');
PREPARE crypto_queue_stmt FROM @crypto_queue_ddl;
EXECUTE crypto_queue_stmt;
DEALLOCATE PREPARE crypto_queue_stmt;

SET @crypto_queue_ddl = IF(EXISTS(SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'crypto_deposit_requests' AND index_name = 'idx_crypto_customer_history'),
    'SELECT 1', 'CREATE INDEX idx_crypto_customer_history ON crypto_deposit_requests (customer_id, id)');
PREPARE crypto_queue_stmt FROM @crypto_queue_ddl;
EXECUTE crypto_queue_stmt;
DEALLOCATE PREPARE crypto_queue_stmt;
