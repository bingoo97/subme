SET NAMES utf8mb4;

SET @sample_password_hash = '$2y$12$QW4OM4PNg5D6e12P4jrxJeZMUvrBwijOkdApTvL5bHGj01hOnkEE2';

INSERT INTO `customers` (
  `email`,
  `password_hash`,
  `password_hash_algorithm`,
  `locale_code`,
  `country_code`,
  `status`,
  `balance_amount`,
  `email_verified_at`,
  `registered_at`,
  `last_login_at`,
  `legacy_source_table`,
  `legacy_source_id`
) VALUES
  ('dashboard-sample-anna@example.test', @sample_password_hash, 'password_hash', 'en', 'AT', 'active', 15.00, NOW(), DATE_SUB(NOW(), INTERVAL 120 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), 'dashboard_sample_customer', 7001),
  ('dashboard-sample-marek@example.test', @sample_password_hash, 'password_hash', 'pl', 'PL', 'active', 5.00, NOW(), DATE_SUB(NOW(), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 'dashboard_sample_customer', 7002),
  ('dashboard-sample-lisa@example.test', @sample_password_hash, 'password_hash', 'en', 'DE', 'active', 0.00, NOW(), DATE_SUB(NOW(), INTERVAL 65 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), 'dashboard_sample_customer', 7003),
  ('dashboard-sample-tomasz@example.test', @sample_password_hash, 'password_hash', 'pl', 'PL', 'active', 22.50, NOW(), DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 4 HOUR), 'dashboard_sample_customer', 7004)
ON DUPLICATE KEY UPDATE
  `email` = VALUES(`email`),
  `password_hash` = VALUES(`password_hash`),
  `password_hash_algorithm` = VALUES(`password_hash_algorithm`),
  `locale_code` = VALUES(`locale_code`),
  `country_code` = VALUES(`country_code`),
  `status` = VALUES(`status`),
  `balance_amount` = VALUES(`balance_amount`),
  `email_verified_at` = VALUES(`email_verified_at`),
  `registered_at` = VALUES(`registered_at`),
  `last_login_at` = VALUES(`last_login_at`);

SET @sample_customer_anna = (SELECT id FROM customers WHERE legacy_source_table = 'dashboard_sample_customer' AND legacy_source_id = 7001 LIMIT 1);
SET @sample_customer_marek = (SELECT id FROM customers WHERE legacy_source_table = 'dashboard_sample_customer' AND legacy_source_id = 7002 LIMIT 1);
SET @sample_customer_lisa = (SELECT id FROM customers WHERE legacy_source_table = 'dashboard_sample_customer' AND legacy_source_id = 7003 LIMIT 1);
SET @sample_customer_tomasz = (SELECT id FROM customers WHERE legacy_source_table = 'dashboard_sample_customer' AND legacy_source_id = 7004 LIMIT 1);

SET @currency_usd = COALESCE((SELECT id FROM currencies WHERE code = 'USD' LIMIT 1), (SELECT id FROM currencies ORDER BY id ASC LIMIT 1));
SET @provider_subs_pro = (SELECT id FROM product_providers WHERE name = 'Subs PRO' LIMIT 1);
SET @provider_gen = (SELECT id FROM product_providers WHERE name = 'Gen' LIMIT 1);
SET @provider_smart = (SELECT id FROM product_providers WHERE name = 'Smart PL' LIMIT 1);

SET @product_subs_month = COALESCE(
  (SELECT id FROM products WHERE provider_id = @provider_subs_pro AND name = '1 Month' LIMIT 1),
  (SELECT id FROM products WHERE provider_id = @provider_subs_pro ORDER BY price_amount ASC, id ASC LIMIT 1)
);
SET @product_subs_long = COALESCE(
  (SELECT id FROM products WHERE provider_id = @provider_subs_pro AND name = '3 Months' LIMIT 1),
  (SELECT id FROM products WHERE provider_id = @provider_subs_pro ORDER BY price_amount DESC, id ASC LIMIT 1)
);
SET @product_gen_main = COALESCE(
  (SELECT id FROM products WHERE provider_id = @provider_gen AND name = '3 Months' LIMIT 1),
  (SELECT id FROM products WHERE provider_id = @provider_gen ORDER BY price_amount ASC, id ASC LIMIT 1)
);
SET @product_smart_main = (
  SELECT id
  FROM products
  WHERE provider_id = @provider_smart
  ORDER BY price_amount ASC, id ASC
  LIMIT 1
);

SET @price_subs_month = COALESCE((SELECT price_amount FROM products WHERE id = @product_subs_month LIMIT 1), 10.00);
SET @price_subs_long = COALESCE((SELECT price_amount FROM products WHERE id = @product_subs_long LIMIT 1), 35.00);
SET @price_gen_main = COALESCE((SELECT price_amount FROM products WHERE id = @product_gen_main LIMIT 1), 25.00);
SET @price_smart_main = COALESCE((SELECT price_amount FROM products WHERE id = @product_smart_main LIMIT 1), 20.00);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_anna, @product_subs_month, 'DASH-9101', 'native', 'crypto', @price_subs_month, @currency_usd,
       'active', 'paid', 'fulfilled', 'Dashboard sample order - today',
       DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_ADD(DATE_SUB(NOW(), INTERVAL 2 HOUR), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR),
       'dashboard_sample_order', 9101
FROM DUAL
WHERE @sample_customer_anna IS NOT NULL AND @product_subs_month IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_tomasz, @product_subs_long, 'DASH-9102', 'native', 'bank_transfer', @price_subs_long, @currency_usd,
       'active', 'paid', 'fulfilled', 'Dashboard sample order - today premium',
       DATE_SUB(NOW(), INTERVAL 7 HOUR), DATE_ADD(DATE_SUB(NOW(), INTERVAL 7 HOUR), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 7 HOUR), DATE_SUB(NOW(), INTERVAL 7 HOUR),
       'dashboard_sample_order', 9102
FROM DUAL
WHERE @sample_customer_tomasz IS NOT NULL AND @product_subs_long IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_marek, @product_gen_main, 'DASH-9103', 'native', 'crypto', @price_gen_main, @currency_usd,
       'pending_payment', 'paid', 'pending', 'Paid and waiting for activation',
       NULL, DATE_ADD(DATE_SUB(NOW(), INTERVAL 1 DAY), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY),
       'dashboard_sample_order', 9103
FROM DUAL
WHERE @sample_customer_marek IS NOT NULL AND @product_gen_main IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_lisa, @product_gen_main, 'DASH-9104', 'native', 'crypto', @price_gen_main, @currency_usd,
       'active', 'paid', 'fulfilled', 'This week sample sale',
       DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 3 DAY), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY),
       'dashboard_sample_order', 9104
FROM DUAL
WHERE @sample_customer_lisa IS NOT NULL AND @product_gen_main IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_anna, @product_subs_month, 'DASH-9105', 'native', 'bank_transfer', @price_subs_month, @currency_usd,
       'active', 'paid', 'fulfilled', 'This week sample sale',
       DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 5 DAY), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY),
       'dashboard_sample_order', 9105
FROM DUAL
WHERE @sample_customer_anna IS NOT NULL AND @product_subs_month IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_marek, @product_subs_long, 'DASH-9106', 'native', 'crypto', @price_subs_long, @currency_usd,
       'active', 'paid', 'fulfilled', 'This month sample sale',
       DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 10 DAY), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY),
       'dashboard_sample_order', 9106
FROM DUAL
WHERE @sample_customer_marek IS NOT NULL AND @product_subs_long IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_lisa, @product_subs_month, 'DASH-9107', 'native', 'crypto', @price_subs_month, @currency_usd,
       'expired', 'paid', 'fulfilled', 'Expired paid order',
       DATE_SUB(NOW(), INTERVAL 42 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 42 DAY), DATE_SUB(NOW(), INTERVAL 42 DAY),
       'dashboard_sample_order', 9107
FROM DUAL
WHERE @sample_customer_lisa IS NOT NULL AND @product_subs_month IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_tomasz, @product_gen_main, 'DASH-9108', 'native', 'bank_transfer', @price_gen_main, @currency_usd,
       'active', 'paid', 'fulfilled', 'Month trend sample',
       DATE_SUB(NOW(), INTERVAL 17 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 17 DAY), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 17 DAY), DATE_SUB(NOW(), INTERVAL 17 DAY),
       'dashboard_sample_order', 9108
FROM DUAL
WHERE @sample_customer_tomasz IS NOT NULL AND @product_gen_main IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_anna, @product_subs_month, 'DASH-9109', 'native', 'crypto', @price_subs_month, @currency_usd,
       'active', 'paid', 'fulfilled', 'Month trend sample',
       DATE_SUB(NOW(), INTERVAL 24 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 24 DAY), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 24 DAY), DATE_SUB(NOW(), INTERVAL 24 DAY),
       'dashboard_sample_order', 9109
FROM DUAL
WHERE @sample_customer_anna IS NOT NULL AND @product_subs_month IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_marek, @product_gen_main, 'DASH-9110', 'native', 'crypto', @price_gen_main, @currency_usd,
       'active', 'paid', 'fulfilled', 'Year trend sample',
       DATE_SUB(NOW(), INTERVAL 45 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 45 DAY), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 45 DAY),
       'dashboard_sample_order', 9110
FROM DUAL
WHERE @sample_customer_marek IS NOT NULL AND @product_gen_main IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_lisa, @product_subs_long, 'DASH-9111', 'native', 'bank_transfer', @price_subs_long, @currency_usd,
       'expired', 'paid', 'fulfilled', 'Older yearly sample',
       DATE_SUB(NOW(), INTERVAL 110 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 110 DAY), DATE_SUB(NOW(), INTERVAL 110 DAY),
       'dashboard_sample_order', 9111
FROM DUAL
WHERE @sample_customer_lisa IS NOT NULL AND @product_subs_long IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_tomasz, @product_smart_main, 'DASH-9112', 'native', NULL, @price_smart_main, @currency_usd,
       'pending_payment', 'unpaid', 'pending', 'Pending payment sample',
       NULL, DATE_ADD(NOW(), INTERVAL 30 DAY), NULL, DATE_SUB(NOW(), INTERVAL 8 HOUR),
       'dashboard_sample_order', 9112
FROM DUAL
WHERE @sample_customer_tomasz IS NOT NULL AND @product_smart_main IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);

INSERT INTO `orders` (
  `customer_id`, `product_id`, `order_reference`, `source_system`, `payment_method`, `total_amount`, `currency_id`,
  `status`, `payment_status`, `fulfillment_status`, `customer_note`,
  `started_at`, `expires_at`, `paid_at`, `created_at`,
  `legacy_source_table`, `legacy_source_id`
)
SELECT @sample_customer_anna, @product_subs_month, 'DASH-9113', 'native', 'crypto', @price_subs_month, @currency_usd,
       'cancelled', 'unpaid', 'cancelled', 'Cancelled sample order',
       NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 15 DAY),
       'dashboard_sample_order', 9113
FROM DUAL
WHERE @sample_customer_anna IS NOT NULL AND @product_subs_month IS NOT NULL AND @currency_usd IS NOT NULL
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`), `product_id` = VALUES(`product_id`), `order_reference` = VALUES(`order_reference`),
  `payment_method` = VALUES(`payment_method`), `total_amount` = VALUES(`total_amount`), `currency_id` = VALUES(`currency_id`),
  `status` = VALUES(`status`), `payment_status` = VALUES(`payment_status`), `fulfillment_status` = VALUES(`fulfillment_status`),
  `customer_note` = VALUES(`customer_note`), `started_at` = VALUES(`started_at`), `expires_at` = VALUES(`expires_at`),
  `paid_at` = VALUES(`paid_at`), `created_at` = VALUES(`created_at`);
