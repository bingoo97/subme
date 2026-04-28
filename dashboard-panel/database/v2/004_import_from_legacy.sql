SET NAMES utf8mb4;
SET SQL_SAFE_UPDATES = 0;

USE `__NEW_DB__`;

INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`, `is_active`, `created_at`)
SELECT
  legacy.`id`,
  UPPER(TRIM(legacy.`short_name`)),
  legacy.`name`,
  legacy.`symbol`,
  IF(legacy.`status` = 1, 1, 0),
  NOW()
FROM `__LEGACY_DB__`.`currency` AS legacy
ON DUPLICATE KEY UPDATE
  `code` = VALUES(`code`),
  `name` = VALUES(`name`),
  `symbol` = VALUES(`symbol`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `admin_roles` (`id`, `name`, `slug`, `access_level`, `created_at`)
SELECT
  legacy.`id`,
  legacy.`nazwa`,
  CONCAT('legacy-role-', legacy.`id`),
  CASE
    WHEN legacy.`id` = 1 THEN 1000
    WHEN legacy.`liczba_gw` >= 2 THEN 900
    ELSE 500
  END,
  NOW()
FROM `__LEGACY_DB__`.`admin_rangi` AS legacy
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `access_level` = VALUES(`access_level`);

UPDATE `app_settings` AS target
JOIN `__LEGACY_DB__`.`settings` AS legacy
  ON legacy.`id` = 1
LEFT JOIN (
  SELECT
    `id`,
    `name`,
    `logo_url`,
    `lang`,
    `status`
  FROM `__LEGACY_DB__`.`resellers`
  WHERE `status` = 1
  ORDER BY `id` ASC
  LIMIT 1
) AS reseller
  ON 1 = 1
SET
  target.`site_name` = COALESCE(NULLIF(TRIM(reseller.`name`), ''), NULLIF(TRIM(legacy.`page_name`), ''), target.`site_name`),
  target.`site_title` = COALESCE(NULLIF(TRIM(legacy.`page_title`), ''), target.`site_title`),
  target.`site_url` = COALESCE(NULLIF(TRIM(legacy.`page_url`), ''), target.`site_url`),
  target.`site_logo_url` = COALESCE(NULLIF(TRIM(legacy.`page_logo`), ''), NULLIF(TRIM(reseller.`logo_url`), ''), target.`site_logo_url`),
  target.`site_description` = COALESCE(NULLIF(TRIM(legacy.`page_desc`), ''), target.`site_description`),
  target.`site_keywords` = COALESCE(NULLIF(TRIM(legacy.`page_keywords`), ''), target.`site_keywords`),
  target.`support_email` = COALESCE(NULLIF(TRIM(legacy.`admin_email`), ''), target.`support_email`),
  target.`default_currency_id` = COALESCE(NULLIF(legacy.`currency`, 0), target.`default_currency_id`),
  target.`default_locale_code` = CASE COALESCE(reseller.`lang`, 0)
    WHEN 1 THEN 'pl'
    WHEN 2 THEN 'de'
    ELSE 'en'
  END,
  target.`maintenance_mode` = IF(legacy.`technical_break` = 1, 1, 0),
  target.`registration_enabled` = IF(legacy.`active_register` = 1, 1, 0),
  target.`sales_enabled` = IF(legacy.`active_sale` = 1, 1, 0),
  target.`trials_enabled` = IF(legacy.`active_trials` = 1, 1, 0),
  target.`homepage_verification_enabled` = IF(legacy.`homepage_verify` = 1, 1, 0),
  target.`results_per_page` = GREATEST(CAST(legacy.`liczba_wynikow` AS UNSIGNED), 1),
  target.`news_feed_limit` = GREATEST(legacy.`count_news`, 1),
  target.`email_rate_limit` = GREATEST(legacy.`email_limit`, 1),
  target.`smtp_host` = NULLIF(TRIM(legacy.`smtp_host`), ''),
  target.`smtp_port` = NULLIF(legacy.`smtp_port`, 0),
  target.`smtp_username` = NULLIF(TRIM(legacy.`smtp_login`), ''),
  target.`smtp_password` = NULLIF(TRIM(legacy.`smtp_haslo`), ''),
  target.`api_key` = NULLIF(TRIM(legacy.`apikey`), '');

INSERT INTO `admin_users` (
  `id`,
  `role_id`,
  `login_name`,
  `email`,
  `password_hash`,
  `password_hash_algorithm`,
  `phone_number`,
  `avatar_url`,
  `status`,
  `last_login_at`,
  `legacy_source_table`,
  `legacy_source_id`,
  `created_at`,
  `updated_at`
)
SELECT
  legacy.`id`,
  COALESCE(NULLIF(legacy.`ranga`, 0), 1),
  legacy.`login`,
  CASE
    WHEN legacy.`email` IS NULL OR TRIM(legacy.`email`) = '' THEN CONCAT(legacy.`login`, '+legacy-admin-', legacy.`id`, '@local.invalid')
    WHEN duplicate_email.`duplicate_count` > 1 THEN CONCAT(legacy.`login`, '+legacy-admin-', legacy.`id`, '@local.invalid')
    ELSE legacy.`email`
  END,
  legacy.`haslo`,
  'legacy_sha1',
  NULLIF(TRIM(legacy.`telefon`), ''),
  NULLIF(TRIM(legacy.`avatar`), ''),
  'active',
  STR_TO_DATE(
    NULLIF(CAST(legacy.`last_log` AS CHAR), '0000-00-00 00:00:00'),
    '%Y-%m-%d %H:%i:%s'
  ),
  'admin',
  legacy.`id`,
  legacy.`date_add`,
  COALESCE(
    STR_TO_DATE(
      NULLIF(CAST(legacy.`last_log` AS CHAR), '0000-00-00 00:00:00'),
      '%Y-%m-%d %H:%i:%s'
    ),
    legacy.`date_add`
  )
FROM `__LEGACY_DB__`.`admin` AS legacy
LEFT JOIN (
  SELECT `email`, COUNT(*) AS `duplicate_count`
  FROM `__LEGACY_DB__`.`admin`
  GROUP BY `email`
) AS duplicate_email
  ON duplicate_email.`email` = legacy.`email`;

INSERT INTO `customers` (
  `id`,
  `email`,
  `password_hash`,
  `password_hash_algorithm`,
  `locale_code`,
  `country_code`,
  `ip_address`,
  `status`,
  `balance_amount`,
  `email_verified_at`,
  `registered_at`,
  `last_login_at`,
  `is_newsletter_subscribed`,
  `legacy_source_table`,
  `legacy_source_id`,
  `created_at`,
  `updated_at`
)
SELECT
  legacy.`id`,
  legacy.`email`,
  legacy.`password`,
  'legacy_sha1',
  CASE legacy.`lang`
    WHEN 1 THEN 'pl'
    WHEN 2 THEN 'de'
    ELSE 'en'
  END,
  NULLIF(TRIM(legacy.`country`), ''),
  NULLIF(TRIM(legacy.`ip`), ''),
  CASE legacy.`status`
    WHEN 1 THEN 'active'
    WHEN 2 THEN 'blocked'
    ELSE 'inactive'
  END,
  0.00,
  CASE
    WHEN legacy.`status` = 1 THEN legacy.`date_register`
    ELSE NULL
  END,
  legacy.`date_register`,
  STR_TO_DATE(
    NULLIF(CAST(legacy.`last_login` AS CHAR), '0000-00-00 00:00:00'),
    '%Y-%m-%d %H:%i:%s'
  ),
  0,
  'users',
  legacy.`id`,
  legacy.`date_register`,
  COALESCE(
    STR_TO_DATE(
      NULLIF(CAST(legacy.`last_login` AS CHAR), '0000-00-00 00:00:00'),
      '%Y-%m-%d %H:%i:%s'
    ),
    legacy.`date_register`
  )
FROM `__LEGACY_DB__`.`users` AS legacy
WHERE legacy.`email` IS NOT NULL
  AND TRIM(legacy.`email`) <> '';

INSERT INTO `product_providers` (
  `id`,
  `name`,
  `slug`,
  `description`,
  `logo_url`,
  `dashboard_url`,
  `supports_manual_delivery`,
  `supports_url_replacement`,
  `is_active`,
  `legacy_source_table`,
  `legacy_source_id`,
  `created_at`,
  `updated_at`
)
SELECT
  legacy.`id`,
  legacy.`name`,
  CONCAT('legacy-provider-', legacy.`id`),
  NULLIF(legacy.`desc`, ''),
  NULLIF(TRIM(legacy.`icon`), ''),
  NULLIF(TRIM(legacy.`dashboard`), ''),
  1,
  IF(legacy.`replace_url` = 1, 1, 0),
  1,
  'products_providers',
  legacy.`id`,
  NOW(),
  NOW()
FROM `__LEGACY_DB__`.`products_providers` AS legacy;

INSERT INTO `products` (
  `id`,
  `provider_id`,
  `name`,
  `slug`,
  `description`,
  `duration_hours`,
  `price_amount`,
  `currency_id`,
  `product_type`,
  `provisioning_mode`,
  `is_trial`,
  `is_active`,
  `source_system`,
  `legacy_source_table`,
  `legacy_source_id`,
  `created_at`,
  `updated_at`
)
SELECT
  legacy.`id`,
  NULLIF(legacy.`provider_id`, 0),
  legacy.`name`,
  CONCAT('legacy-product-', legacy.`id`),
  NULLIF(legacy.`desc`, ''),
  GREATEST(legacy.`duration`, 0),
  legacy.`price`,
  COALESCE(NULLIF((SELECT `currency` FROM `__LEGACY_DB__`.`settings` ORDER BY `id` ASC LIMIT 1), 0), 2),
  'subscription',
  'manual',
  IF(legacy.`trial` = 1, 1, 0),
  IF(legacy.`status` = 1, 1, 0),
  'legacy',
  'products',
  legacy.`id`,
  NOW(),
  NOW()
FROM `__LEGACY_DB__`.`products` AS legacy;

INSERT INTO `orders` (
  `id`,
  `customer_id`,
  `product_id`,
  `order_reference`,
  `source_system`,
  `legacy_source_table`,
  `legacy_source_id`,
  `total_amount`,
  `currency_id`,
  `status`,
  `payment_status`,
  `fulfillment_status`,
  `customer_note`,
  `delivery_link`,
  `delivery_link_visible`,
  `started_at`,
  `expires_at`,
  `paid_at`,
  `payment_method`,
  `created_at`,
  `updated_at`
)
SELECT
  legacy.`id`,
  legacy.`user_id`,
  legacy.`product_id`,
  CONCAT('legacy-products-users-', legacy.`id`),
  'legacy',
  'products_users',
  legacy.`id`,
  legacy.`price`,
  COALESCE(NULLIF((SELECT `currency` FROM `__LEGACY_DB__`.`settings` ORDER BY `id` ASC LIMIT 1), 0), 2),
  CASE
    WHEN legacy.`payment` = 0 THEN 'pending_payment'
    WHEN legacy.`shipment` = 0 THEN 'processing'
    WHEN legacy.`status` = 2 THEN 'expired'
    WHEN legacy.`status` = 1 THEN 'active'
    ELSE 'completed'
  END,
  CASE
    WHEN legacy.`payment` = 1 THEN 'paid'
    ELSE 'unpaid'
  END,
  CASE
    WHEN legacy.`shipment` = 1 THEN 'delivered'
    ELSE 'pending'
  END,
  NULLIF(TRIM(legacy.`note`), ''),
  NULLIF(TRIM(legacy.`link_url`), ''),
  0,
  legacy.`date_add`,
  STR_TO_DATE(
    NULLIF(CAST(legacy.`date_end` AS CHAR), '0000-00-00 00:00:00'),
    '%Y-%m-%d %H:%i:%s'
  ),
  CASE
    WHEN legacy.`payment` = 1 THEN legacy.`date_add`
    ELSE NULL
  END,
  CASE
    WHEN EXISTS (
      SELECT 1
      FROM `__LEGACY_DB__`.`invoices` AS invoice
      WHERE invoice.`order_id` = legacy.`id`
    ) THEN 'crypto'
    ELSE NULL
  END,
  legacy.`date_add`,
  COALESCE(
    STR_TO_DATE(
      NULLIF(CAST(legacy.`date_end` AS CHAR), '0000-00-00 00:00:00'),
      '%Y-%m-%d %H:%i:%s'
    ),
    legacy.`date_add`
  )
FROM `__LEGACY_DB__`.`products_users` AS legacy
INNER JOIN `customers` AS customer
  ON customer.`id` = legacy.`user_id`;

INSERT INTO `orders` (
  `id`,
  `customer_id`,
  `product_id`,
  `order_reference`,
  `source_system`,
  `legacy_source_table`,
  `legacy_source_id`,
  `legacy_product_reference`,
  `service_id`,
  `total_amount`,
  `currency_id`,
  `status`,
  `payment_status`,
  `fulfillment_status`,
  `customer_note`,
  `delivery_link`,
  `transaction_reference`,
  `device_mac`,
  `file_type`,
  `adult_content_enabled`,
  `started_at`,
  `expires_at`,
  `paid_at`,
  `created_at`,
  `updated_at`
)
SELECT
  legacy.`id`,
  legacy.`user`,
  NULL,
  CONCAT('legacy-produkty-user-', legacy.`id`),
  'legacy',
  'produkty_user',
  legacy.`id`,
  legacy.`aukcja`,
  legacy.`serwis`,
  legacy.`cena`,
  COALESCE(NULLIF((SELECT `currency` FROM `__LEGACY_DB__`.`settings` ORDER BY `id` ASC LIMIT 1), 0), 2),
  CASE
    WHEN legacy.`platnosc` = 0 THEN 'pending_payment'
    WHEN legacy.`wysylka` = 0 THEN 'processing'
    WHEN legacy.`status` = 2 THEN 'expired'
    WHEN legacy.`status` = 1 THEN 'active'
    ELSE 'completed'
  END,
  CASE
    WHEN legacy.`platnosc` = 1 THEN 'paid'
    ELSE 'unpaid'
  END,
  CASE
    WHEN legacy.`wysylka` = 1 THEN 'delivered'
    ELSE 'pending'
  END,
  NULLIF(TRIM(legacy.`dodatkowe_info`), ''),
  NULLIF(TRIM(legacy.`url_link`), ''),
  NULLIF(TRIM(legacy.`id_transakcji`), ''),
  NULLIF(TRIM(legacy.`mac`), ''),
  CAST(legacy.`typ_pliku` AS CHAR),
  IF(legacy.`erotyka` = 1, 1, 0),
  legacy.`data_rozpoczecia`,
  STR_TO_DATE(
    NULLIF(CAST(legacy.`data_zakonczenia` AS CHAR), '0000-00-00 00:00:00'),
    '%Y-%m-%d %H:%i:%s'
  ),
  CASE
    WHEN legacy.`platnosc` = 1 THEN legacy.`data_rozpoczecia`
    ELSE NULL
  END,
  legacy.`data_rozpoczecia`,
  COALESCE(
    STR_TO_DATE(
      NULLIF(CAST(legacy.`data_zakonczenia` AS CHAR), '0000-00-00 00:00:00'),
      '%Y-%m-%d %H:%i:%s'
    ),
    legacy.`data_rozpoczecia`
  )
FROM `__LEGACY_DB__`.`produkty_user` AS legacy
INNER JOIN `customers` AS customer
  ON customer.`id` = legacy.`user`;

INSERT INTO `order_renewals` (
  `id`,
  `order_id`,
  `customer_id`,
  `price_amount`,
  `currency_id`,
  `status`,
  `requested_at`,
  `applied_at`,
  `legacy_source_table`,
  `legacy_source_id`
)
SELECT
  legacy.`id`,
  legacy.`id_order`,
  legacy.`user_id`,
  legacy.`price`,
  COALESCE(NULLIF((SELECT `currency` FROM `__LEGACY_DB__`.`settings` ORDER BY `id` ASC LIMIT 1), 0), 2),
  'applied',
  legacy.`date`,
  legacy.`date`,
  'products_extend',
  legacy.`id`
FROM `__LEGACY_DB__`.`products_extend` AS legacy
INNER JOIN `orders` AS `order_record`
  ON `order_record`.`id` = legacy.`id_order`
INNER JOIN `customers` AS customer
  ON customer.`id` = legacy.`user_id`;

INSERT INTO `crypto_assets` (
  `id`,
  `code`,
  `name`,
  `coingecko_id`,
  `logo_url`,
  `current_rate_fiat`,
  `rate_currency_code`,
  `rate_updated_at`,
  `is_active`,
  `legacy_source_table`,
  `legacy_source_id`,
  `created_at`,
  `updated_at`
)
SELECT
  legacy.`id`,
  UPPER(TRIM(legacy.`symbol`)),
  legacy.`name`,
  CASE UPPER(TRIM(legacy.`symbol`))
    WHEN 'BTC' THEN 'bitcoin'
    WHEN 'BCH' THEN 'bitcoin-cash'
    WHEN 'LTC' THEN 'litecoin'
    WHEN 'ETH' THEN 'ethereum'
    WHEN 'DOGE' THEN 'dogecoin'
    WHEN 'BNB' THEN 'binancecoin'
    WHEN 'USDT' THEN 'tether'
    WHEN 'CRO' THEN 'crypto-com-chain'
    WHEN 'SOL' THEN 'solana'
    WHEN 'USDC' THEN 'usd-coin'
    WHEN 'MATIC' THEN 'matic-network'
    WHEN 'XRP' THEN 'ripple'
    ELSE LOWER(TRIM(legacy.`symbol`))
  END,
  NULLIF(TRIM(legacy.`logo_url`), ''),
  legacy.`rate`,
  'USD',
  legacy.`date_rate`,
  IF(legacy.`status` = 1, 1, 0),
  'cryptocurrency',
  legacy.`id`,
  legacy.`date_rate`,
  legacy.`date_rate`
FROM `__LEGACY_DB__`.`cryptocurrency` AS legacy
ON DUPLICATE KEY UPDATE
  `code` = VALUES(`code`),
  `name` = VALUES(`name`),
  `coingecko_id` = VALUES(`coingecko_id`),
  `logo_url` = VALUES(`logo_url`),
  `current_rate_fiat` = VALUES(`current_rate_fiat`),
  `rate_currency_code` = VALUES(`rate_currency_code`),
  `rate_updated_at` = VALUES(`rate_updated_at`),
  `is_active` = VALUES(`is_active`);

INSERT IGNORE INTO `crypto_wallet_addresses` (
  `crypto_asset_id`,
  `label`,
  `owner_full_name`,
  `address`,
  `network_code`,
  `memo_tag`,
  `wallet_provider`,
  `status`,
  `is_reusable`,
  `qr_url`,
  `notes`,
  `last_checked_at`,
  `created_at`,
  `updated_at`
)
SELECT
  legacy.`id`,
  CONCAT(UPPER(TRIM(legacy.`symbol`)), ' Legacy Primary Address'),
  NULL,
  TRIM(legacy.`adress_code`),
  CASE UPPER(TRIM(legacy.`symbol`))
    WHEN 'BTC' THEN 'bitcoin'
    WHEN 'BCH' THEN 'bitcoin-cash'
    WHEN 'LTC' THEN 'litecoin'
    WHEN 'DOGE' THEN 'dogecoin'
    WHEN 'ETH' THEN 'ethereum'
    WHEN 'BNB' THEN 'bnb'
    WHEN 'CRO' THEN 'cronos'
    WHEN 'SOL' THEN 'solana'
    WHEN 'MATIC' THEN 'polygon'
    WHEN 'XRP' THEN 'ripple'
    WHEN 'USDT' THEN CASE
      WHEN TRIM(legacy.`adress_code`) LIKE 'T%' THEN 'tron'
      WHEN LOWER(TRIM(legacy.`adress_code`)) LIKE '0x%' THEN 'ethereum'
      ELSE 'ethereum'
    END
    WHEN 'USDC' THEN CASE
      WHEN LOWER(TRIM(legacy.`adress_code`)) LIKE '0x%' THEN 'ethereum'
      ELSE 'ethereum'
    END
    ELSE ''
  END,
  NULL,
  NULLIF(TRIM(legacy.`wallet`), ''),
  'available',
  0,
  NULLIF(TRIM(legacy.`qr_url`), ''),
  NULLIF(legacy.`note`, ''),
  legacy.`date_rate`,
  legacy.`date_rate`,
  legacy.`date_rate`
FROM `__LEGACY_DB__`.`cryptocurrency` AS legacy
WHERE legacy.`adress_code` IS NOT NULL
  AND TRIM(legacy.`adress_code`) <> '';

INSERT IGNORE INTO `crypto_wallet_addresses` (
  `crypto_asset_id`,
  `label`,
  `owner_full_name`,
  `address`,
  `network_code`,
  `memo_tag`,
  `wallet_provider`,
  `status`,
  `is_reusable`,
  `notes`,
  `created_at`,
  `updated_at`
)
SELECT DISTINCT
  CASE
    WHEN LOWER(TRIM(legacy.`address`)) REGEXP '^(bc1|[13])' THEN 1
    WHEN LOWER(TRIM(legacy.`address`)) REGEXP '^(q|p)' THEN 2
    ELSE 1
  END,
  CONCAT('Legacy Invoice Address ', legacy.`id`),
  NULL,
  TRIM(legacy.`address`),
  CASE
    WHEN LOWER(TRIM(legacy.`address`)) REGEXP '^(bc1|[13])' THEN 'bitcoin'
    WHEN LOWER(TRIM(legacy.`address`)) REGEXP '^(q|p)' THEN 'bitcoin-cash'
    ELSE 'bitcoin'
  END,
  NULL,
  NULL,
  'available',
  0,
  CONCAT('Imported from legacy invoice #', legacy.`id`),
  legacy.`date_add`,
  legacy.`date_add`
FROM `__LEGACY_DB__`.`invoices` AS legacy
WHERE legacy.`address` IS NOT NULL
  AND TRIM(legacy.`address`) <> ''
  AND TRIM(legacy.`address`) NOT LIKE 'HTTP/%'
  AND CHAR_LENGTH(TRIM(legacy.`address`)) >= 20;

INSERT IGNORE INTO `crypto_wallet_addresses` (
  `crypto_asset_id`,
  `label`,
  `address`,
  `memo_tag`,
  `wallet_provider`,
  `status`,
  `is_reusable`,
  `notes`,
  `created_at`,
  `updated_at`
)
SELECT DISTINCT
  CASE
    WHEN LOWER(TRIM(legacy.`addr`)) REGEXP '^(bc1|[13])' THEN 1
    WHEN LOWER(TRIM(legacy.`addr`)) REGEXP '^(q|p)' THEN 2
    ELSE 1
  END,
  CONCAT('Legacy Payment Address ', legacy.`id`),
  TRIM(legacy.`addr`),
  NULL,
  NULL,
  'available',
  0,
  CONCAT('Imported from legacy payment #', legacy.`id`),
  legacy.`date`,
  legacy.`date`
FROM `__LEGACY_DB__`.`payments` AS legacy
WHERE legacy.`addr` IS NOT NULL
  AND TRIM(legacy.`addr`) <> ''
  AND TRIM(legacy.`addr`) NOT LIKE 'HTTP/%'
  AND CHAR_LENGTH(TRIM(legacy.`addr`)) >= 20;

INSERT INTO `crypto_wallet_assignments` (
  `wallet_address_id`,
  `customer_id`,
  `order_id`,
  `assignment_reason`,
  `status`,
  `assigned_at`,
  `released_at`,
  `assignment_note`
)
SELECT
  wallet.`id`,
  legacy.`user_id`,
  MIN(`order_record`.`id`),
  'legacy_invoice',
  CASE
    WHEN MAX(CASE WHEN legacy.`status` IN (0, 1) THEN 1 ELSE 0 END) = 1 THEN 'active'
    ELSE 'released'
  END,
  MIN(legacy.`date_add`),
  CASE
    WHEN MAX(CASE WHEN legacy.`status` IN (0, 1) THEN 1 ELSE 0 END) = 1 THEN NULL
    ELSE MAX(legacy.`date_add`)
  END,
  CONCAT('Imported from legacy invoices for address ', MIN(TRIM(legacy.`address`)))
FROM `__LEGACY_DB__`.`invoices` AS legacy
INNER JOIN `customers` AS customer
  ON customer.`id` = legacy.`user_id`
INNER JOIN `crypto_wallet_addresses` AS wallet
  ON wallet.`address` = TRIM(legacy.`address`)
LEFT JOIN `orders` AS `order_record`
  ON `order_record`.`id` = legacy.`order_id`
WHERE legacy.`address` IS NOT NULL
  AND TRIM(legacy.`address`) <> ''
  AND TRIM(legacy.`address`) NOT LIKE 'HTTP/%'
  AND CHAR_LENGTH(TRIM(legacy.`address`)) >= 20
GROUP BY wallet.`id`, legacy.`user_id`;

INSERT INTO `crypto_deposit_requests` (
  `id`,
  `customer_id`,
  `order_id`,
  `crypto_asset_id`,
  `wallet_address_id`,
  `wallet_assignment_id`,
  `requested_fiat_amount`,
  `fiat_currency_id`,
  `exchange_rate`,
  `requested_crypto_amount`,
  `assignment_mode`,
  `status`,
  `requested_at`,
  `confirmed_at`,
  `cancelled_at`,
  `legacy_source_table`,
  `legacy_source_id`,
  `request_note`
)
SELECT
  legacy.`id`,
  legacy.`user_id`,
  CASE
    WHEN `order_record`.`id` IS NULL THEN NULL
    ELSE legacy.`order_id`
  END,
  wallet.`crypto_asset_id`,
  wallet.`id`,
  (
    SELECT assignment.`id`
    FROM `crypto_wallet_assignments` AS assignment
    WHERE assignment.`wallet_address_id` = wallet.`id`
      AND assignment.`customer_id` = legacy.`user_id`
    ORDER BY assignment.`assigned_at` ASC
    LIMIT 1
  ),
  legacy.`price`,
  COALESCE(NULLIF((SELECT `currency` FROM `__LEGACY_DB__`.`settings` ORDER BY `id` ASC LIMIT 1), 0), 2),
  legacy.`rate`,
  CASE
    WHEN legacy.`rate` > 0 THEN ROUND(legacy.`price` / legacy.`rate`, 8)
    ELSE 0
  END,
  'manual',
  CASE
    WHEN legacy.`status` = 2 THEN 'confirmed'
    WHEN legacy.`status` < 0 THEN 'cancelled'
    ELSE 'pending'
  END,
  legacy.`date_add`,
  CASE
    WHEN legacy.`status` = 2 THEN legacy.`date_add`
    ELSE NULL
  END,
  CASE
    WHEN legacy.`status` < 0 THEN legacy.`date_add`
    ELSE NULL
  END,
  'invoices',
  legacy.`id`,
  CONCAT('Legacy invoice code: ', legacy.`code`)
FROM `__LEGACY_DB__`.`invoices` AS legacy
INNER JOIN `customers` AS customer
  ON customer.`id` = legacy.`user_id`
INNER JOIN `crypto_wallet_addresses` AS wallet
  ON wallet.`address` = TRIM(legacy.`address`)
LEFT JOIN `orders` AS `order_record`
  ON `order_record`.`id` = legacy.`order_id`
WHERE legacy.`address` IS NOT NULL
  AND TRIM(legacy.`address`) <> ''
  AND TRIM(legacy.`address`) NOT LIKE 'HTTP/%'
  AND CHAR_LENGTH(TRIM(legacy.`address`)) >= 20;

INSERT INTO `crypto_deposit_transactions` (
  `deposit_request_id`,
  `wallet_address_id`,
  `customer_id`,
  `crypto_asset_id`,
  `transaction_hash`,
  `amount_crypto`,
  `amount_fiat`,
  `confirmations`,
  `status`,
  `received_at`,
  `raw_payload`
)
SELECT
  (
    SELECT deposit_request.`id`
    FROM `crypto_deposit_requests` AS deposit_request
    WHERE deposit_request.`wallet_address_id` = wallet.`id`
    ORDER BY deposit_request.`requested_at` DESC
    LIMIT 1
  ),
  wallet.`id`,
  (
    SELECT deposit_request.`customer_id`
    FROM `crypto_deposit_requests` AS deposit_request
    WHERE deposit_request.`wallet_address_id` = wallet.`id`
    ORDER BY deposit_request.`requested_at` DESC
    LIMIT 1
  ),
  wallet.`crypto_asset_id`,
  CONCAT(TRIM(legacy.`txid`), '-', legacy.`id`),
  ROUND(legacy.`value` / 100000000, 8),
  NULL,
  CASE
    WHEN legacy.`status` = 2 THEN 1
    ELSE 0
  END,
  CASE
    WHEN legacy.`status` = 2 THEN 'confirmed'
    ELSE 'detected'
  END,
  legacy.`date`,
  CONCAT('Imported from legacy payments row #', legacy.`id`)
FROM `__LEGACY_DB__`.`payments` AS legacy
INNER JOIN `crypto_wallet_addresses` AS wallet
  ON wallet.`address` = TRIM(legacy.`addr`)
WHERE legacy.`addr` IS NOT NULL
  AND TRIM(legacy.`addr`) <> ''
  AND TRIM(legacy.`addr`) NOT LIKE 'HTTP/%'
  AND CHAR_LENGTH(TRIM(legacy.`addr`)) >= 20;

INSERT INTO `support_conversations` (
  `conversation_type`,
  `customer_id`,
  `assigned_admin_id`,
  `subject`,
  `status`,
  `priority`,
  `legacy_source_table`,
  `legacy_source_id`,
  `last_customer_message_at`,
  `created_at`,
  `updated_at`
)
SELECT
  'live_chat',
  legacy.`user1`,
  legacy.`user2`,
  CONCAT('Legacy live chat ', legacy.`user1`, '-', legacy.`user2`),
  CASE
    WHEN MAX(legacy.`status`) = 1 THEN 'closed'
    ELSE 'open'
  END,
  'normal',
  'produkty_chat_thread',
  MIN(legacy.`id`),
  MAX(legacy.`data`),
  MIN(legacy.`data`),
  MAX(legacy.`data`)
FROM `__LEGACY_DB__`.`produkty_chat` AS legacy
INNER JOIN `customers` AS customer
  ON customer.`id` = legacy.`user1`
LEFT JOIN `admin_users` AS admin_user
  ON admin_user.`id` = legacy.`user2`
GROUP BY legacy.`user1`, legacy.`user2`;

INSERT INTO `support_conversations` (
  `conversation_type`,
  `assigned_admin_id`,
  `subject`,
  `status`,
  `priority`,
  `legacy_source_table`,
  `legacy_source_id`,
  `last_admin_message_at`,
  `created_at`,
  `updated_at`
)
SELECT
  'internal_admin',
  legacy.`user2`,
  CONCAT('Legacy admin chat ', legacy.`user1`, '-', legacy.`user2`),
  CASE
    WHEN MAX(legacy.`status`) = 1 THEN 'closed'
    ELSE 'open'
  END,
  'normal',
  'produkty_chat_admin_thread',
  MIN(legacy.`id`),
  MAX(legacy.`data`),
  MIN(legacy.`data`),
  MAX(legacy.`data`)
FROM `__LEGACY_DB__`.`produkty_chat_admin` AS legacy
INNER JOIN `admin_users` AS sender_admin
  ON sender_admin.`id` = legacy.`user1`
INNER JOIN `admin_users` AS target_admin
  ON target_admin.`id` = legacy.`user2`
GROUP BY legacy.`user1`, legacy.`user2`;

INSERT INTO `support_messages` (
  `conversation_id`,
  `sender_type`,
  `customer_id`,
  `admin_user_id`,
  `message_body`,
  `is_read`,
  `legacy_source_table`,
  `legacy_source_id`,
  `created_at`
)
SELECT
  (
    SELECT conversation.`id`
    FROM `support_conversations` AS conversation
    WHERE conversation.`legacy_source_table` = 'produkty_chat_thread'
      AND conversation.`customer_id` = legacy.`user1`
      AND conversation.`assigned_admin_id` = legacy.`user2`
    LIMIT 1
  ),
  'customer',
  legacy.`user1`,
  NULL,
  legacy.`tresc`,
  IF(legacy.`status` = 1, 1, 0),
  'produkty_chat',
  legacy.`id`,
  legacy.`data`
FROM `__LEGACY_DB__`.`produkty_chat` AS legacy
INNER JOIN `customers` AS customer
  ON customer.`id` = legacy.`user1`;

INSERT INTO `support_messages` (
  `conversation_id`,
  `sender_type`,
  `customer_id`,
  `admin_user_id`,
  `message_body`,
  `is_read`,
  `legacy_source_table`,
  `legacy_source_id`,
  `created_at`
)
SELECT
  (
    SELECT conversation.`id`
    FROM `support_conversations` AS conversation
    WHERE conversation.`legacy_source_table` = 'produkty_chat_admin_thread'
      AND conversation.`assigned_admin_id` = legacy.`user2`
      AND conversation.`subject` = CONCAT('Legacy admin chat ', legacy.`user1`, '-', legacy.`user2`)
    LIMIT 1
  ),
  'admin',
  NULL,
  legacy.`user1`,
  legacy.`tresc`,
  IF(legacy.`status` = 1, 1, 0),
  'produkty_chat_admin',
  legacy.`id`,
  legacy.`data`
FROM `__LEGACY_DB__`.`produkty_chat_admin` AS legacy
INNER JOIN `admin_users` AS admin_user
  ON admin_user.`id` = legacy.`user1`;

INSERT INTO `news_posts` (
  `title`,
  `slug`,
  `body`,
  `visibility`,
  `is_active`,
  `published_at`,
  `legacy_source_table`,
  `legacy_source_id`,
  `created_at`,
  `updated_at`
)
SELECT
  LEFT(TRIM(legacy.`title`), 191),
  CONCAT('legacy-news-', legacy.`id`),
  legacy.`text`,
  'customer',
  IF(legacy.`status` = 1, 1, 0),
  legacy.`date`,
  'news',
  legacy.`id`,
  legacy.`date`,
  legacy.`date`
FROM `__LEGACY_DB__`.`news` AS legacy;

INSERT INTO `news_posts` (
  `title`,
  `slug`,
  `body`,
  `visibility`,
  `is_active`,
  `published_at`,
  `legacy_source_table`,
  `legacy_source_id`,
  `created_at`,
  `updated_at`
)
SELECT
  LEFT(TRIM(legacy.`title`), 191),
  CONCAT('legacy-reseller-news-', legacy.`id`),
  legacy.`text`,
  'customer',
  IF(legacy.`status` = 1, 1, 0),
  legacy.`date`,
  'resellers_news',
  legacy.`id`,
  legacy.`date`,
  legacy.`date`
FROM `__LEGACY_DB__`.`resellers_news` AS legacy;

INSERT INTO `static_pages` (
  `slug`,
  `title`,
  `body`,
  `page_type`,
  `is_system`,
  `is_active`,
  `legacy_source_table`,
  `legacy_source_id`,
  `created_at`,
  `updated_at`
)
SELECT
  CONCAT('faq-', legacy.`id`),
  LEFT(TRIM(legacy.`tytul`), 191),
  legacy.`tresc`,
  'faq',
  0,
  1,
  'faq',
  legacy.`id`,
  NOW(),
  NOW()
FROM `__LEGACY_DB__`.`faq` AS legacy;

INSERT INTO `email_templates` (
  `template_key`,
  `name`,
  `subject`,
  `body_html`,
  `is_system`,
  `is_active`,
  `legacy_source_table`,
  `legacy_source_id`,
  `created_at`,
  `updated_at`
)
SELECT
  CONCAT('legacy-email-', legacy.`id`),
  LEFT(TRIM(legacy.`nazwa`), 191),
  LEFT(TRIM(legacy.`nazwa`), 191),
  legacy.`tresc`,
  0,
  1,
  'komunikaty',
  legacy.`id`,
  NOW(),
  NOW()
FROM `__LEGACY_DB__`.`komunikaty` AS legacy;

UPDATE `crypto_wallet_addresses` AS wallet
LEFT JOIN (
  SELECT DISTINCT `wallet_address_id`
  FROM `crypto_wallet_assignments`
  WHERE `status` IN ('reserved', 'active')
) AS active_assignment
  ON active_assignment.`wallet_address_id` = wallet.`id`
SET wallet.`status` = CASE
  WHEN active_assignment.`wallet_address_id` IS NOT NULL THEN 'assigned'
  WHEN wallet.`disabled_at` IS NOT NULL THEN 'disabled'
  ELSE 'available'
END;

INSERT IGNORE INTO `schema_migrations` (`migration_name`) VALUES
  ('001_core_schema'),
  ('002_seed_defaults'),
  ('003_operational_views'),
  ('004_import_from_legacy');
