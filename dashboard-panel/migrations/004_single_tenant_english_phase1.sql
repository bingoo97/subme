-- 004_single_tenant_english_phase1.sql
-- Executable migration:
-- - normalizes key tables toward InnoDB + utf8mb4
-- - adds English compatibility columns
-- - creates English compatibility views for future code switch
-- - keeps old Polish schema fully usable for backward compatibility

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$
CREATE PROCEDURE add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    DECLARE v_table_exists INT DEFAULT 0;
    DECLARE v_column_exists INT DEFAULT 0;
    DECLARE v_sql TEXT;

    SELECT COUNT(*)
      INTO v_table_exists
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = p_table;

    IF v_table_exists > 0 THEN
        SELECT COUNT(*)
          INTO v_column_exists
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = p_table
           AND column_name = p_column;

        IF v_column_exists = 0 THEN
            SET v_sql = CONCAT(
                'ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition
            );
            SET @sql_stmt = v_sql;
            PREPARE s FROM @sql_stmt;
            EXECUTE s;
            DEALLOCATE PREPARE s;
        END IF;
    END IF;
END $$

DROP PROCEDURE IF EXISTS execute_if_table_exists $$
CREATE PROCEDURE execute_if_table_exists(
    IN p_table VARCHAR(64),
    IN p_sql TEXT
)
BEGIN
    DECLARE v_table_exists INT DEFAULT 0;

    SELECT COUNT(*)
      INTO v_table_exists
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = p_table;

    IF v_table_exists > 0 THEN
        SET @sql_stmt = p_sql;
        PREPARE s FROM @sql_stmt;
        EXECUTE s;
        DEALLOCATE PREPARE s;
    END IF;
END $$

DROP PROCEDURE IF EXISTS recreate_view_if_source_exists $$
CREATE PROCEDURE recreate_view_if_source_exists(
    IN p_source_table VARCHAR(64),
    IN p_view_name VARCHAR(64),
    IN p_select_sql LONGTEXT
)
BEGIN
    DECLARE v_source_exists INT DEFAULT 0;

    SELECT COUNT(*)
      INTO v_source_exists
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = p_source_table;

    IF v_source_exists > 0 THEN
        SET @drop_view_sql = CONCAT('DROP VIEW IF EXISTS `', p_view_name, '`');
        PREPARE s1 FROM @drop_view_sql;
        EXECUTE s1;
        DEALLOCATE PREPARE s1;

        SET @create_view_sql = CONCAT('CREATE VIEW `', p_view_name, '` AS ', p_select_sql);
        PREPARE s2 FROM @create_view_sql;
        EXECUTE s2;
        DEALLOCATE PREPARE s2;
    END IF;
END $$

CALL execute_if_table_exists('produkty_user', 'ALTER TABLE `produkty_user` ENGINE=InnoDB') $$
CALL execute_if_table_exists('products', 'ALTER TABLE `products` ENGINE=InnoDB') $$
CALL execute_if_table_exists('podstrony', 'ALTER TABLE `podstrony` ENGINE=InnoDB') $$
CALL execute_if_table_exists('komunikaty', 'ALTER TABLE `komunikaty` ENGINE=InnoDB') $$
CALL execute_if_table_exists('refy', 'ALTER TABLE `refy` ENGINE=InnoDB') $$
CALL execute_if_table_exists('goscie_online', 'ALTER TABLE `goscie_online` ENGINE=InnoDB') $$
CALL execute_if_table_exists('resellers', 'ALTER TABLE `resellers` ENGINE=InnoDB') $$
CALL execute_if_table_exists('users', 'ALTER TABLE `users` ENGINE=InnoDB') $$

CALL execute_if_table_exists('produkty_user', 'ALTER TABLE `produkty_user` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('products_users', 'ALTER TABLE `products_users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('products', 'ALTER TABLE `products` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('wplaty', 'ALTER TABLE `wplaty` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('wplaty_crypto', 'ALTER TABLE `wplaty_crypto` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('podstrony', 'ALTER TABLE `podstrony` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('komunikaty', 'ALTER TABLE `komunikaty` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('refy', 'ALTER TABLE `refy` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('goscie_online', 'ALTER TABLE `goscie_online` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('produkty_chat', 'ALTER TABLE `produkty_chat` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('produkty_chat_admin', 'ALTER TABLE `produkty_chat_admin` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('resellers', 'ALTER TABLE `resellers` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('resellers_news', 'ALTER TABLE `resellers_news` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('users', 'ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$
CALL execute_if_table_exists('settings', 'ALTER TABLE `settings` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') $$

CALL add_column_if_missing('products', 'tenant_id', 'INT NULL AFTER `res_id`') $$
CALL add_column_if_missing('products', 'is_active', 'TINYINT(4) NULL AFTER `status`') $$

CALL add_column_if_missing('products_users', 'tenant_id', 'INT NULL AFTER `res_id`') $$
CALL add_column_if_missing('products_users', 'created_at', 'TIMESTAMP NULL AFTER `date_add`') $$
CALL add_column_if_missing('products_users', 'end_at', 'DATETIME NULL AFTER `date_end`') $$
CALL add_column_if_missing('products_users', 'payment_status', 'TINYINT(4) NULL AFTER `payment`') $$
CALL add_column_if_missing('products_users', 'shipment_status', 'TINYINT(4) NULL AFTER `shipment`') $$
CALL add_column_if_missing('products_users', 'is_active', 'TINYINT(4) NULL AFTER `status`') $$

CALL add_column_if_missing('produkty_user', 'service_id', 'INT NULL AFTER `serwis`') $$
CALL add_column_if_missing('produkty_user', 'product_id', 'INT NULL AFTER `aukcja`') $$
CALL add_column_if_missing('produkty_user', 'user_id', 'INT NULL AFTER `user`') $$
CALL add_column_if_missing('produkty_user', 'price_amount', 'DECIMAL(10,2) NULL AFTER `cena`') $$
CALL add_column_if_missing('produkty_user', 'transaction_id', 'VARCHAR(60) NULL AFTER `id_transakcji`') $$
CALL add_column_if_missing('produkty_user', 'link_url', 'LONGTEXT NULL AFTER `url_link`') $$
CALL add_column_if_missing('produkty_user', 'file_type', 'TINYINT(4) NULL AFTER `typ_pliku`') $$
CALL add_column_if_missing('produkty_user', 'adult_content', 'TINYINT(4) NULL AFTER `erotyka`') $$
CALL add_column_if_missing('produkty_user', 'device_mac', 'VARCHAR(120) NULL AFTER `mac`') $$
CALL add_column_if_missing('produkty_user', 'additional_info', 'TEXT NULL AFTER `dodatkowe_info`') $$
CALL add_column_if_missing('produkty_user', 'start_at', 'TIMESTAMP NULL AFTER `data_rozpoczecia`') $$
CALL add_column_if_missing('produkty_user', 'end_at', 'DATETIME NULL AFTER `data_zakonczenia`') $$
CALL add_column_if_missing('produkty_user', 'payment_status', 'TINYINT(4) NULL AFTER `platnosc`') $$
CALL add_column_if_missing('produkty_user', 'delivery_status', 'TINYINT(4) NULL AFTER `wysylka`') $$
CALL add_column_if_missing('produkty_user', 'is_active', 'TINYINT(4) NULL AFTER `status`') $$

CALL add_column_if_missing('wplaty', 'order_id', 'INT NULL AFTER `id_zamowienia`') $$
CALL add_column_if_missing('wplaty', 'created_at', 'DATETIME NULL AFTER `data`') $$
CALL add_column_if_missing('wplaty', 'is_active', 'TINYINT(4) NULL AFTER `status`') $$

CALL add_column_if_missing('wplaty_crypto', 'product_id', 'INT NULL AFTER `package_id`') $$
CALL add_column_if_missing('wplaty_crypto', 'product_price', 'DECIMAL(12,2) NULL AFTER `package_price`') $$
CALL add_column_if_missing('wplaty_crypto', 'created_at', 'TIMESTAMP NULL AFTER `date`') $$
CALL add_column_if_missing('wplaty_crypto', 'is_active', 'TINYINT(4) NULL AFTER `status`') $$

CALL add_column_if_missing('podstrony', 'name', 'VARCHAR(60) NULL AFTER `nazwa`') $$
CALL add_column_if_missing('podstrony', 'content', 'LONGTEXT NULL AFTER `tresc`') $$

CALL add_column_if_missing('komunikaty', 'name', 'VARCHAR(90) NULL AFTER `nazwa`') $$
CALL add_column_if_missing('komunikaty', 'content', 'TEXT NULL AFTER `tresc`') $$

CALL add_column_if_missing('refy', 'service_id', 'TINYINT(4) NULL AFTER `serwis`') $$
CALL add_column_if_missing('refy', 'referrer_user_id', 'INT NULL AFTER `user1`') $$
CALL add_column_if_missing('refy', 'referred_user_id', 'INT NULL AFTER `user2`') $$
CALL add_column_if_missing('refy', 'is_active', 'TINYINT(4) NULL AFTER `status`') $$

CALL add_column_if_missing('goscie_online', 'ip_address', 'TEXT NULL AFTER `ip`') $$
CALL add_column_if_missing('goscie_online', 'last_seen_at', 'TIMESTAMP NULL AFTER `time`') $$

CALL add_column_if_missing('produkty_chat', 'sender_user_id', 'INT NULL AFTER `user1`') $$
CALL add_column_if_missing('produkty_chat', 'recipient_user_id', 'INT NULL AFTER `user2`') $$
CALL add_column_if_missing('produkty_chat', 'message_text', 'LONGTEXT NULL AFTER `tresc`') $$
CALL add_column_if_missing('produkty_chat', 'created_at', 'DATETIME NULL AFTER `data`') $$
CALL add_column_if_missing('produkty_chat', 'is_active', 'TINYINT(4) NULL AFTER `status`') $$

CALL add_column_if_missing('produkty_chat_admin', 'sender_user_id', 'INT NULL AFTER `user1`') $$
CALL add_column_if_missing('produkty_chat_admin', 'recipient_user_id', 'INT NULL AFTER `user2`') $$
CALL add_column_if_missing('produkty_chat_admin', 'message_text', 'LONGTEXT NULL AFTER `tresc`') $$
CALL add_column_if_missing('produkty_chat_admin', 'created_at', 'TIMESTAMP NULL AFTER `data`') $$
CALL add_column_if_missing('produkty_chat_admin', 'is_active', 'TINYINT(4) NULL AFTER `status`') $$

CALL add_column_if_missing('resellers', 'tenant_id', 'INT NULL AFTER `id`') $$
CALL add_column_if_missing('resellers', 'registered_at', 'TIMESTAMP NULL AFTER `date_register`') $$
CALL add_column_if_missing('resellers', 'last_login_at', 'DATETIME NULL AFTER `last_login`') $$
CALL add_column_if_missing('resellers', 'ip_address', 'VARCHAR(60) NULL AFTER `ip`') $$
CALL add_column_if_missing('resellers', 'currency_id', 'TINYINT(4) NULL AFTER `currency`') $$
CALL add_column_if_missing('resellers', 'locale_code', 'VARCHAR(5) NULL AFTER `lang`') $$
CALL add_column_if_missing('resellers', 'is_active', 'TINYINT(4) NULL AFTER `status`') $$

CALL add_column_if_missing('resellers_news', 'tenant_id', 'INT NULL AFTER `res_id`') $$
CALL add_column_if_missing('resellers_news', 'created_at', 'TIMESTAMP NULL AFTER `date`') $$
CALL add_column_if_missing('resellers_news', 'is_active', 'TINYINT(4) NULL AFTER `status`') $$

CALL add_column_if_missing('users', 'tenant_id', 'INT NULL AFTER `res_id`') $$
CALL add_column_if_missing('users', 'registered_at', 'TIMESTAMP NULL AFTER `date_register`') $$
CALL add_column_if_missing('users', 'last_login_at', 'DATETIME NULL AFTER `last_login`') $$
CALL add_column_if_missing('users', 'ip_address', 'VARCHAR(60) NULL AFTER `ip`') $$
CALL add_column_if_missing('users', 'locale_code', 'VARCHAR(5) NULL AFTER `lang`') $$
CALL add_column_if_missing('users', 'is_active', 'TINYINT(4) NULL AFTER `status`') $$

CALL add_column_if_missing('settings', 'results_per_page', 'INT NULL AFTER `liczba_wynikow`') $$
CALL add_column_if_missing('settings', 'smtp_password', 'VARCHAR(50) NULL AFTER `smtp_haslo`') $$
CALL add_column_if_missing('settings', 'api_key', 'VARCHAR(260) NULL AFTER `apikey`') $$

UPDATE products
   SET tenant_id = res_id,
       is_active = status
 WHERE tenant_id IS NULL
    OR is_active IS NULL $$

UPDATE products_users
   SET tenant_id = res_id,
       created_at = date_add,
       end_at = date_end,
       payment_status = payment,
       shipment_status = shipment,
       is_active = status
 WHERE tenant_id IS NULL
    OR created_at IS NULL
    OR end_at IS NULL
    OR payment_status IS NULL
    OR shipment_status IS NULL
    OR is_active IS NULL $$

UPDATE produkty_user
   SET service_id = serwis,
       product_id = aukcja,
       user_id = user,
       price_amount = cena,
       transaction_id = id_transakcji,
       link_url = url_link,
       file_type = typ_pliku,
       adult_content = erotyka,
       device_mac = mac,
       additional_info = dodatkowe_info,
       start_at = data_rozpoczecia,
       end_at = data_zakonczenia,
       payment_status = platnosc,
       delivery_status = wysylka,
       is_active = status
 WHERE service_id IS NULL
    OR product_id IS NULL
    OR user_id IS NULL
    OR price_amount IS NULL
    OR transaction_id IS NULL
    OR link_url IS NULL
    OR file_type IS NULL
    OR adult_content IS NULL
    OR device_mac IS NULL
    OR additional_info IS NULL
    OR start_at IS NULL
    OR end_at IS NULL
    OR payment_status IS NULL
    OR delivery_status IS NULL
    OR is_active IS NULL $$

UPDATE wplaty
   SET order_id = id_zamowienia,
       created_at = data,
       is_active = status
 WHERE order_id IS NULL
    OR created_at IS NULL
    OR is_active IS NULL $$

UPDATE wplaty_crypto
   SET product_id = package_id,
       product_price = package_price,
       created_at = date,
       is_active = status
 WHERE product_id IS NULL
    OR product_price IS NULL
    OR created_at IS NULL
    OR is_active IS NULL $$

UPDATE podstrony
   SET name = nazwa,
       content = tresc
 WHERE name IS NULL
    OR content IS NULL $$

UPDATE komunikaty
   SET name = nazwa,
       content = tresc
 WHERE name IS NULL
    OR content IS NULL $$

UPDATE refy
   SET service_id = serwis,
       referrer_user_id = user1,
       referred_user_id = user2,
       is_active = status
 WHERE service_id IS NULL
    OR referrer_user_id IS NULL
    OR referred_user_id IS NULL
    OR is_active IS NULL $$

UPDATE goscie_online
   SET ip_address = ip,
       last_seen_at = time
 WHERE ip_address IS NULL
    OR last_seen_at IS NULL $$

UPDATE produkty_chat
   SET sender_user_id = user1,
       recipient_user_id = user2,
       message_text = tresc,
       created_at = data,
       is_active = status
 WHERE sender_user_id IS NULL
    OR recipient_user_id IS NULL
    OR message_text IS NULL
    OR created_at IS NULL
    OR is_active IS NULL $$

UPDATE produkty_chat_admin
   SET sender_user_id = user1,
       recipient_user_id = user2,
       message_text = tresc,
       created_at = data,
       is_active = status
 WHERE sender_user_id IS NULL
    OR recipient_user_id IS NULL
    OR message_text IS NULL
    OR created_at IS NULL
    OR is_active IS NULL $$

UPDATE resellers
   SET tenant_id = id,
       registered_at = date_register,
       last_login_at = last_login,
       ip_address = ip,
       currency_id = currency,
       locale_code = CASE COALESCE(lang, 0)
           WHEN 1 THEN 'pl'
           WHEN 2 THEN 'de'
           ELSE 'en'
       END,
       is_active = status
 WHERE tenant_id IS NULL
    OR registered_at IS NULL
    OR last_login_at IS NULL
    OR ip_address IS NULL
    OR currency_id IS NULL
    OR locale_code IS NULL
    OR is_active IS NULL $$

UPDATE resellers_news
   SET tenant_id = res_id,
       created_at = date,
       is_active = status
 WHERE tenant_id IS NULL
    OR created_at IS NULL
    OR is_active IS NULL $$

UPDATE users
   SET tenant_id = res_id,
       registered_at = date_register,
       last_login_at = last_login,
       ip_address = ip,
       locale_code = CASE COALESCE(lang, 0)
           WHEN 1 THEN 'pl'
           WHEN 2 THEN 'de'
           ELSE 'en'
       END,
       is_active = status
 WHERE tenant_id IS NULL
    OR registered_at IS NULL
    OR last_login_at IS NULL
    OR ip_address IS NULL
    OR locale_code IS NULL
    OR is_active IS NULL $$

UPDATE settings
   SET results_per_page = CAST(liczba_wynikow AS UNSIGNED),
       smtp_password = smtp_haslo,
       api_key = apikey
 WHERE results_per_page IS NULL
    OR smtp_password IS NULL
    OR api_key IS NULL $$

CALL recreate_view_if_source_exists(
    'podstrony',
    'pages',
    'SELECT `id`, `nazwa` AS `name`, `tresc` AS `content` FROM `podstrony`'
) $$

CALL recreate_view_if_source_exists(
    'komunikaty',
    'messages',
    'SELECT `id`, `nazwa` AS `name`, `tresc` AS `content` FROM `komunikaty`'
) $$

CALL recreate_view_if_source_exists(
    'refy',
    'referrals',
    'SELECT `id`, `serwis` AS `service_id`, `user1` AS `referrer_user_id`, `user2` AS `referred_user_id`, `status` AS `is_active` FROM `refy`'
) $$

CALL recreate_view_if_source_exists(
    'goscie_online',
    'guest_visitors_online',
    'SELECT `id`, `ip` AS `ip_address`, `time` AS `last_seen_at` FROM `goscie_online`'
) $$

CALL recreate_view_if_source_exists(
    'produkty_chat',
    'support_messages',
    'SELECT `id`, `user1` AS `sender_user_id`, `user2` AS `recipient_user_id`, `tresc` AS `message_text`, `data` AS `created_at`, `status` AS `is_active` FROM `produkty_chat`'
) $$

CALL recreate_view_if_source_exists(
    'produkty_chat_admin',
    'admin_support_messages',
    'SELECT `id`, `user1` AS `sender_user_id`, `user2` AS `recipient_user_id`, `tresc` AS `message_text`, `data` AS `created_at`, `status` AS `is_active` FROM `produkty_chat_admin`'
) $$

CALL recreate_view_if_source_exists(
    'produkty_user',
    'legacy_order_items',
    'SELECT `id`, `serwis` AS `service_id`, `aukcja` AS `product_id`, `user` AS `user_id`, `cena` AS `price_amount`, `id_transakcji` AS `transaction_id`, `url_link` AS `link_url`, `typ_pliku` AS `file_type`, `erotyka` AS `adult_content`, `mac` AS `device_mac`, `dodatkowe_info` AS `additional_info`, `data_rozpoczecia` AS `start_at`, `data_zakonczenia` AS `end_at`, `platnosc` AS `payment_status`, `wysylka` AS `delivery_status`, `status` AS `is_active` FROM `produkty_user`'
) $$

CALL recreate_view_if_source_exists(
    'wplaty',
    'legacy_payments',
    'SELECT `id`, `user_id`, `id_zamowienia` AS `order_id`, `data` AS `created_at`, `status` AS `is_active` FROM `wplaty`'
) $$

CALL recreate_view_if_source_exists(
    'wplaty_crypto',
    'crypto_topups',
    'SELECT `id`, `user_id`, `package_id` AS `product_id`, `package_price` AS `product_price`, `crypto_id`, `crypto_rate`, `crypto_amount`, `date` AS `created_at`, `status` AS `is_active` FROM `wplaty_crypto`'
) $$

CALL recreate_view_if_source_exists(
    'resellers_news',
    'tenant_news',
    'SELECT `id`, `res_id` AS `tenant_id`, `title`, `text`, `date` AS `created_at`, `status` AS `is_active` FROM `resellers_news`'
) $$

CALL recreate_view_if_source_exists(
    'resellers',
    'installation_profile',
    'SELECT resellers.id AS tenant_id, resellers.id, resellers.name, resellers.email, resellers.password, resellers.logo_url, resellers.date_register AS registered_at, resellers.last_login AS last_login_at, resellers.ip AS ip_address, resellers.country, resellers.currency AS currency_id, currency.short_name AS currency_short, currency.symbol AS currency_symbol, CASE COALESCE(resellers.lang, 0) WHEN 1 THEN ''pl'' WHEN 2 THEN ''de'' ELSE ''en'' END AS locale_code, resellers.status AS is_active FROM resellers LEFT JOIN currency ON resellers.currency = currency.id WHERE resellers.status = 1 ORDER BY resellers.id ASC LIMIT 1'
) $$

DROP PROCEDURE IF EXISTS recreate_view_if_source_exists $$
DROP PROCEDURE IF EXISTS execute_if_table_exists $$
DROP PROCEDURE IF EXISTS add_column_if_missing $$

DELIMITER ;
