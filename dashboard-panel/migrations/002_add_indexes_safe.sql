-- 002_add_indexes_safe.sql
-- Safe, idempotent index migration for MySQL 8.x
-- Adds only missing indexes.

DELIMITER $$

DROP PROCEDURE IF EXISTS add_index_if_missing $$
CREATE PROCEDURE add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_columns VARCHAR(255)
)
BEGIN
    DECLARE v_exists INT DEFAULT 0;
    DECLARE v_sql TEXT;

    SELECT COUNT(*)
      INTO v_exists
      FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = p_table
       AND index_name = p_index;

    IF v_exists = 0 THEN
        SET v_sql = CONCAT(
            'ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_columns, ')'
        );
        SET @sql_stmt = v_sql;
        PREPARE s FROM @sql_stmt;
        EXECUTE s;
        DEALLOCATE PREPARE s;
    END IF;
END $$

CALL add_index_if_missing('produkty_user', 'idx_produkt_user_user_status', '`user`, `status`') $$
CALL add_index_if_missing('produkty_user', 'idx_produkt_user_auction_status', '`aukcja`, `status`') $$
CALL add_index_if_missing('produkty_user', 'idx_produkt_user_payment_shipment_status', '`platnosc`, `wysylka`, `status`') $$
CALL add_index_if_missing('produkty_user', 'idx_produkt_user_end_status', '`data_zakonczenia`, `status`') $$
CALL add_index_if_missing('produkty_user', 'idx_produkt_user_txid', '`id_transakcji`') $$

CALL add_index_if_missing('products_users', 'idx_products_users_user_status', '`user_id`, `status`') $$
CALL add_index_if_missing('products_users', 'idx_products_users_res_user', '`res_id`, `user_id`') $$
CALL add_index_if_missing('products_users', 'idx_products_users_product_status', '`product_id`, `status`') $$
CALL add_index_if_missing('products_users', 'idx_products_users_payment_shipment_status', '`payment`, `shipment`, `status`') $$
CALL add_index_if_missing('products_users', 'idx_products_users_end_status', '`date_end`, `status`') $$

CALL add_index_if_missing('email_send', 'idx_email_send_user_email_status', '`user`, `email_id`, `status`') $$
CALL add_index_if_missing('email_send', 'idx_email_send_status_date', '`status`, `data`') $$
CALL add_index_if_missing('email_send', 'idx_email_send_email_status', '`email_id`, `status`') $$

CALL add_index_if_missing('user_online', 'idx_user_online_user_status', '`user`, `status`') $$
CALL add_index_if_missing('user_online', 'idx_user_online_status', '`status`') $$

CALL add_index_if_missing('produkty_chat', 'idx_produkty_chat_user2_status_date', '`user2`, `status`, `data`') $$
CALL add_index_if_missing('produkty_chat', 'idx_produkty_chat_user1_user2', '`user1`, `user2`') $$

CALL add_index_if_missing('wplaty', 'idx_wplaty_user_status', '`user_id`, `status`') $$
CALL add_index_if_missing('wplaty', 'idx_wplaty_order_status', '`id_zamowienia`, `status`') $$

CALL add_index_if_missing('wplaty_crypto', 'idx_wplaty_crypto_user_status', '`user_id`, `status`') $$
CALL add_index_if_missing('wplaty_crypto', 'idx_wplaty_crypto_crypto_status', '`crypto_id`, `status`') $$
CALL add_index_if_missing('wplaty_crypto', 'idx_wplaty_crypto_date_status', '`date`, `status`') $$

CALL add_index_if_missing('resellers_news', 'idx_resellers_news_res_status_date', '`res_id`, `status`, `date`') $$

CALL add_index_if_missing('users', 'idx_users_res_status', '`res_id`, `status`') $$
CALL add_index_if_missing('users', 'idx_users_email', '`email`') $$

DROP PROCEDURE IF EXISTS add_index_if_missing $$

DELIMITER ;

