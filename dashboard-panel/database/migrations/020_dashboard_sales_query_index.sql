-- Payment lookups and duplicate detection must not scan the full renewal history per row.
SET @sales_index_ddl = IF(EXISTS(SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'order_renewals'
      AND index_name = 'idx_order_renewals_payment_request'),
    'SELECT 1',
    'CREATE INDEX idx_order_renewals_payment_request ON order_renewals (payment_request_type, payment_request_id, status, id)');
PREPARE sales_index_stmt FROM @sales_index_ddl;
EXECUTE sales_index_stmt;
DEALLOCATE PREPARE sales_index_stmt;
