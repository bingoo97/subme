SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `outbound_email_queue`;
DROP TABLE IF EXISTS `email_templates`;
DROP TABLE IF EXISTS `static_pages`;
DROP TABLE IF EXISTS `news_posts`;
DROP TABLE IF EXISTS `admin_dashboard_change_log`;
DROP TABLE IF EXISTS `support_messages`;
DROP TABLE IF EXISTS `support_conversations`;
DROP TABLE IF EXISTS `bank_transfer_requests`;
DROP TABLE IF EXISTS `bank_account_assignments`;
DROP TABLE IF EXISTS `bank_accounts`;
DROP TABLE IF EXISTS `crypto_deposit_transactions`;
DROP TABLE IF EXISTS `crypto_deposit_requests`;
DROP TABLE IF EXISTS `crypto_wallet_assignments`;
DROP TABLE IF EXISTS `crypto_wallet_addresses`;
DROP TABLE IF EXISTS `crypto_assets`;
DROP TABLE IF EXISTS `referrals`;
DROP TABLE IF EXISTS `order_renewals`;
DROP TABLE IF EXISTS `order_status_events`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `product_providers`;
DROP TABLE IF EXISTS `customer_activity_logs`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `admin_navigation_items`;
DROP TABLE IF EXISTS `admin_users`;
DROP TABLE IF EXISTS `admin_roles`;
DROP TABLE IF EXISTS `app_settings`;
DROP TABLE IF EXISTS `currencies`;
DROP TABLE IF EXISTS `schema_migrations`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `schema_migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration_name` VARCHAR(191) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_schema_migrations_name` (`migration_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `currencies` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` CHAR(3) NOT NULL,
  `name` VARCHAR(60) NOT NULL,
  `symbol` VARCHAR(10) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_currencies_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `app_settings` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `site_name` VARCHAR(120) NOT NULL,
  `site_title` VARCHAR(160) NOT NULL,
  `site_url` VARCHAR(255) NOT NULL,
  `site_logo_url` VARCHAR(255) DEFAULT NULL,
  `site_description` VARCHAR(500) DEFAULT NULL,
  `site_keywords` VARCHAR(500) DEFAULT NULL,
  `support_email` VARCHAR(191) NOT NULL,
  `default_currency_id` TINYINT UNSIGNED NOT NULL,
  `default_locale_code` VARCHAR(5) NOT NULL DEFAULT 'en',
  `maintenance_mode` TINYINT(1) NOT NULL DEFAULT 0,
  `registration_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `sales_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `credits_sales_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `trials_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `support_chat_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `customer_messenger_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `customer_direct_chat_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `customer_group_chat_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `customer_global_group_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `messenger_voice_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `demo_messenger_showcase_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `demo_messenger_showcase_last_tick_at` DATETIME DEFAULT NULL,
  `contact_form_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `referrals_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `apps_page_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `apps_url` VARCHAR(255) DEFAULT NULL,
  `customer_type_switch_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `application_instructions_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `page_guidance_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `payment_test_mode_notice_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `crypto_daily_backup_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `crypto_daily_backup_email` VARCHAR(191) DEFAULT NULL,
  `crypto_daily_backup_last_processed_date` DATE DEFAULT NULL,
  `manual_database_backup_last_downloaded_at` DATETIME DEFAULT NULL,
  `history_cleanup_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `payments_cleanup_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `expired_orders_cleanup_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `support_chat_retention_days` TINYINT UNSIGNED NOT NULL DEFAULT 7,
  `support_chat_retention_hours` SMALLINT UNSIGNED NOT NULL DEFAULT 168,
  `history_visible_from` DATETIME NULL DEFAULT NULL,
  `crypto_payments_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `bank_transfers_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `crypto_wallet_shared_assignments_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `bank_account_shared_assignments_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `crypto_wallet_assignment_mode` VARCHAR(30) NOT NULL DEFAULT 'manual_only',
  `homepage_verification_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `results_per_page` INT UNSIGNED NOT NULL DEFAULT 20,
  `news_feed_limit` INT UNSIGNED NOT NULL DEFAULT 10,
  `email_rate_limit` INT UNSIGNED NOT NULL DEFAULT 50,
  `smtp_host` VARCHAR(191) DEFAULT NULL,
  `smtp_port` INT UNSIGNED DEFAULT NULL,
  `smtp_username` VARCHAR(191) DEFAULT NULL,
  `smtp_password` VARCHAR(255) DEFAULT NULL,
  `api_key` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_app_settings_currency` FOREIGN KEY (`default_currency_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_roles` (
  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `access_level` SMALLINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` SMALLINT UNSIGNED NOT NULL,
  `login_name` VARCHAR(80) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `public_handle` VARCHAR(80) DEFAULT NULL,
  `personal_notes_html` LONGTEXT DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `password_hash_algorithm` VARCHAR(50) NOT NULL DEFAULT 'password_hash',
  `phone_number` VARCHAR(30) DEFAULT NULL,
  `avatar_url` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME DEFAULT NULL,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_users_login_name` (`login_name`),
  UNIQUE KEY `uniq_admin_users_email` (`email`),
  KEY `idx_admin_users_role_id` (`role_id`),
  CONSTRAINT `fk_admin_users_role` FOREIGN KEY (`role_id`) REFERENCES `admin_roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_navigation_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_key` VARCHAR(50) NOT NULL DEFAULT 'main',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `title` VARCHAR(120) NOT NULL,
  `icon_class` VARCHAR(191) DEFAULT NULL,
  `route_name` VARCHAR(120) NOT NULL,
  `minimum_access_level` SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_navigation_items_group_sort` (`group_key`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_dashboard_change_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `change_date` DATE NOT NULL,
  `change_text` TEXT NOT NULL,
  `admin_user_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_dashboard_change_log_change_date` (`change_date`, `id`),
  KEY `idx_admin_dashboard_change_log_admin_user_id` (`admin_user_id`),
  CONSTRAINT `fk_admin_dashboard_change_log_admin_user` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(191) NOT NULL,
  `public_handle` VARCHAR(80) DEFAULT NULL,
  `avatar_url` VARCHAR(255) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `password_hash_algorithm` VARCHAR(50) NOT NULL DEFAULT 'password_hash',
  `locale_code` VARCHAR(5) NOT NULL DEFAULT 'en',
  `country_code` CHAR(2) DEFAULT NULL,
  `ip_address` VARCHAR(64) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `customer_type` VARCHAR(20) NOT NULL DEFAULT 'client',
  `balance_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `email_verified_at` DATETIME DEFAULT NULL,
  `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME DEFAULT NULL,
  `is_newsletter_subscribed` TINYINT(1) NOT NULL DEFAULT 0,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_customers_email` (`email`),
  UNIQUE KEY `uniq_customers_legacy_source` (`legacy_source_table`, `legacy_source_id`),
  KEY `idx_customers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `admin_user_id` INT UNSIGNED DEFAULT NULL,
  `actor_type` VARCHAR(20) NOT NULL DEFAULT 'system',
  `action_key` VARCHAR(80) NOT NULL,
  `description` TEXT NOT NULL,
  `ip_address` VARCHAR(64) DEFAULT NULL,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer_activity_logs_customer_id_created_at` (`customer_id`, `created_at`),
  KEY `idx_customer_activity_logs_admin_user_id` (`admin_user_id`),
  CONSTRAINT `fk_customer_activity_logs_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_customer_activity_logs_admin_user` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_providers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `logo_url` VARCHAR(255) DEFAULT NULL,
  `dashboard_url` VARCHAR(255) DEFAULT NULL,
  `supports_manual_delivery` TINYINT(1) NOT NULL DEFAULT 1,
  `supports_delivery_links` TINYINT(1) NOT NULL DEFAULT 1,
  `supports_url_replacement` TINYINT(1) NOT NULL DEFAULT 0,
  `url_replacement_from` VARCHAR(255) DEFAULT NULL,
  `url_replacement_to` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_product_providers_slug` (`slug`),
  UNIQUE KEY `uniq_product_providers_legacy_source` (`legacy_source_table`, `legacy_source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_provider_visibility` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `provider_id` INT UNSIGNED NOT NULL,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_customer_provider_visibility_customer_provider` (`customer_id`, `provider_id`),
  KEY `idx_customer_provider_visibility_customer_visible` (`customer_id`, `is_visible`),
  KEY `idx_customer_provider_visibility_provider_visible` (`provider_id`, `is_visible`),
  CONSTRAINT `fk_customer_provider_visibility_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_customer_provider_visibility_provider` FOREIGN KEY (`provider_id`) REFERENCES `product_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(191) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `description` LONGTEXT DEFAULT NULL,
  `duration_hours` INT UNSIGNED NOT NULL DEFAULT 0,
  `price_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency_id` TINYINT UNSIGNED NOT NULL,
  `product_type` VARCHAR(30) NOT NULL DEFAULT 'subscription',
  `provisioning_mode` VARCHAR(30) NOT NULL DEFAULT 'manual',
  `is_trial` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `source_system` VARCHAR(30) NOT NULL DEFAULT 'native',
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_products_slug` (`slug`),
  UNIQUE KEY `uniq_products_legacy_source` (`legacy_source_table`, `legacy_source_id`),
  KEY `idx_products_provider_id` (`provider_id`),
  KEY `idx_products_currency_id` (`currency_id`),
  KEY `idx_products_is_active` (`is_active`),
  CONSTRAINT `fk_products_provider` FOREIGN KEY (`provider_id`) REFERENCES `product_providers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `order_reference` VARCHAR(100) DEFAULT NULL,
  `source_system` VARCHAR(30) NOT NULL DEFAULT 'native',
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `legacy_product_reference` INT UNSIGNED DEFAULT NULL,
  `service_id` INT UNSIGNED DEFAULT NULL,
  `payment_method` VARCHAR(30) DEFAULT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency_id` TINYINT UNSIGNED NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending_payment',
  `payment_status` VARCHAR(30) NOT NULL DEFAULT 'unpaid',
  `fulfillment_status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `customer_note` TEXT DEFAULT NULL,
  `support_note` TEXT DEFAULT NULL,
  `delivery_link` TEXT DEFAULT NULL,
  `delivery_link_visible` TINYINT(1) NOT NULL DEFAULT 0,
  `transaction_reference` VARCHAR(120) DEFAULT NULL,
  `device_mac` VARCHAR(120) DEFAULT NULL,
  `file_type` VARCHAR(40) DEFAULT NULL,
  `adult_content_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `started_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_orders_legacy_source` (`legacy_source_table`, `legacy_source_id`),
  KEY `idx_orders_customer_id` (`customer_id`),
  KEY `idx_orders_product_id` (`product_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_payment_method` (`payment_method`),
  KEY `idx_orders_payment_status` (`payment_status`),
  KEY `idx_orders_fulfillment_status` (`fulfillment_status`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_status_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `admin_user_id` INT UNSIGNED DEFAULT NULL,
  `old_status` VARCHAR(30) DEFAULT NULL,
  `new_status` VARCHAR(30) NOT NULL,
  `event_note` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_status_events_order_id` (`order_id`),
  CONSTRAINT `fk_order_status_events_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_status_events_admin_user` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_renewals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `price_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency_id` TINYINT UNSIGNED NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'applied',
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `applied_at` DATETIME DEFAULT NULL,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_order_renewals_legacy_source` (`legacy_source_table`, `legacy_source_id`),
  KEY `idx_order_renewals_order_id` (`order_id`),
  KEY `idx_order_renewals_customer_id` (`customer_id`),
  CONSTRAINT `fk_order_renewals_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_renewals_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_order_renewals_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `referrals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_customer_id` INT UNSIGNED NOT NULL,
  `referred_customer_id` INT UNSIGNED NOT NULL,
  `qualified_order_id` BIGINT UNSIGNED DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `note` TEXT DEFAULT NULL,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `qualified_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_referrals_referred_customer_id` (`referred_customer_id`),
  UNIQUE KEY `uniq_referrals_legacy_source` (`legacy_source_table`, `legacy_source_id`),
  KEY `idx_referrals_referrer_customer_id` (`referrer_customer_id`),
  CONSTRAINT `fk_referrals_referrer` FOREIGN KEY (`referrer_customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_referrals_referred` FOREIGN KEY (`referred_customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_referrals_qualified_order` FOREIGN KEY (`qualified_order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `crypto_assets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `coingecko_id` VARCHAR(30) DEFAULT NULL,
  `logo_url` VARCHAR(255) DEFAULT NULL,
  `current_rate_fiat` DECIMAL(18,8) DEFAULT NULL,
  `rate_currency_code` CHAR(3) NOT NULL DEFAULT 'USD',
  `rate_updated_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_crypto_assets_code` (`code`),
  UNIQUE KEY `uniq_crypto_assets_legacy_source` (`legacy_source_table`, `legacy_source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `crypto_wallet_addresses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `crypto_asset_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(191) DEFAULT NULL,
  `owner_full_name` VARCHAR(191) DEFAULT NULL,
  `address` VARCHAR(255) NOT NULL,
  `network_code` VARCHAR(40) NOT NULL DEFAULT '',
  `memo_tag` VARCHAR(191) DEFAULT NULL,
  `wallet_provider` VARCHAR(120) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'available',
  `is_reusable` TINYINT(1) NOT NULL DEFAULT 0,
  `qr_url` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by_admin_user_id` INT UNSIGNED DEFAULT NULL,
  `last_assigned_at` DATETIME DEFAULT NULL,
  `last_checked_at` DATETIME DEFAULT NULL,
  `disabled_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_crypto_wallet_addresses_address` (`address`),
  KEY `idx_crypto_wallet_addresses_asset_status` (`crypto_asset_id`, `status`),
  KEY `idx_crypto_wallet_addresses_created_by_admin_user_id` (`created_by_admin_user_id`),
  CONSTRAINT `fk_crypto_wallet_addresses_asset` FOREIGN KEY (`crypto_asset_id`) REFERENCES `crypto_assets` (`id`),
  CONSTRAINT `fk_crypto_wallet_addresses_created_by_admin_user` FOREIGN KEY (`created_by_admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `crypto_wallet_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `wallet_address_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `assignment_reason` VARCHAR(30) NOT NULL DEFAULT 'deposit',
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `assigned_by_admin_user_id` INT UNSIGNED DEFAULT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `released_by_admin_user_id` INT UNSIGNED DEFAULT NULL,
  `released_at` DATETIME DEFAULT NULL,
  `assignment_note` TEXT DEFAULT NULL,
  `active_assignment_lock` TINYINT GENERATED ALWAYS AS (
    CASE
      WHEN `status` IN ('reserved', 'active') THEN 1
      ELSE NULL
    END
  ) STORED,
  PRIMARY KEY (`id`),
  KEY `idx_crypto_wallet_assignments_wallet_status` (`wallet_address_id`, `status`),
  KEY `idx_crypto_wallet_assignments_customer_status` (`customer_id`, `status`),
  KEY `idx_crypto_wallet_assignments_assigned_by_admin_user_id` (`assigned_by_admin_user_id`),
  KEY `idx_crypto_wallet_assignments_released_by_admin_user_id` (`released_by_admin_user_id`),
  CONSTRAINT `fk_crypto_wallet_assignments_wallet` FOREIGN KEY (`wallet_address_id`) REFERENCES `crypto_wallet_addresses` (`id`),
  CONSTRAINT `fk_crypto_wallet_assignments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_crypto_wallet_assignments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_crypto_wallet_assignments_assigned_by_admin_user` FOREIGN KEY (`assigned_by_admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_crypto_wallet_assignments_released_by_admin_user` FOREIGN KEY (`released_by_admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `crypto_deposit_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `crypto_asset_id` INT UNSIGNED NOT NULL,
  `wallet_address_id` BIGINT UNSIGNED NOT NULL,
  `wallet_assignment_id` BIGINT UNSIGNED DEFAULT NULL,
  `requested_fiat_amount` DECIMAL(10,2) NOT NULL,
  `fiat_currency_id` TINYINT UNSIGNED NOT NULL,
  `exchange_rate` DECIMAL(18,8) NOT NULL,
  `requested_crypto_amount` DECIMAL(18,8) NOT NULL,
  `assignment_mode` VARCHAR(20) NOT NULL DEFAULT 'manual',
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_by_admin_user_id` INT UNSIGNED DEFAULT NULL,
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME DEFAULT NULL,
  `confirmed_at` DATETIME DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `request_note` TEXT DEFAULT NULL,
  `open_request_lock` TINYINT GENERATED ALWAYS AS (
    CASE
      WHEN `status` IN ('pending', 'awaiting_confirmation', 'awaiting_review') THEN 1
      ELSE NULL
    END
  ) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_crypto_deposit_requests_legacy_source` (`legacy_source_table`, `legacy_source_id`),
  UNIQUE KEY `uniq_crypto_deposit_requests_wallet_open_lock` (`wallet_address_id`, `open_request_lock`),
  KEY `idx_crypto_deposit_requests_customer_status` (`customer_id`, `status`),
  KEY `idx_crypto_deposit_requests_wallet_status` (`wallet_address_id`, `status`),
  KEY `idx_crypto_deposit_requests_created_by_admin_user_id` (`created_by_admin_user_id`),
  CONSTRAINT `fk_crypto_deposit_requests_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_crypto_deposit_requests_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_crypto_deposit_requests_asset` FOREIGN KEY (`crypto_asset_id`) REFERENCES `crypto_assets` (`id`),
  CONSTRAINT `fk_crypto_deposit_requests_wallet` FOREIGN KEY (`wallet_address_id`) REFERENCES `crypto_wallet_addresses` (`id`),
  CONSTRAINT `fk_crypto_deposit_requests_wallet_assignment` FOREIGN KEY (`wallet_assignment_id`) REFERENCES `crypto_wallet_assignments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_crypto_deposit_requests_fiat_currency` FOREIGN KEY (`fiat_currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_crypto_deposit_requests_created_by_admin_user` FOREIGN KEY (`created_by_admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `crypto_deposit_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `deposit_request_id` BIGINT UNSIGNED DEFAULT NULL,
  `wallet_address_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `crypto_asset_id` INT UNSIGNED NOT NULL,
  `transaction_hash` VARCHAR(191) NOT NULL,
  `amount_crypto` DECIMAL(18,8) NOT NULL,
  `amount_fiat` DECIMAL(10,2) DEFAULT NULL,
  `confirmations` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'detected',
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `raw_payload` LONGTEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_crypto_deposit_transactions_hash` (`transaction_hash`),
  KEY `idx_crypto_deposit_transactions_request_id` (`deposit_request_id`),
  KEY `idx_crypto_deposit_transactions_wallet_id` (`wallet_address_id`),
  CONSTRAINT `fk_crypto_deposit_transactions_request` FOREIGN KEY (`deposit_request_id`) REFERENCES `crypto_deposit_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_crypto_deposit_transactions_wallet` FOREIGN KEY (`wallet_address_id`) REFERENCES `crypto_wallet_addresses` (`id`),
  CONSTRAINT `fk_crypto_deposit_transactions_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_crypto_deposit_transactions_asset` FOREIGN KEY (`crypto_asset_id`) REFERENCES `crypto_assets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bank_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `currency_id` TINYINT UNSIGNED NOT NULL,
  `label` VARCHAR(191) DEFAULT NULL,
  `account_holder_name` VARCHAR(191) NOT NULL,
  `bank_name` VARCHAR(191) NOT NULL,
  `bank_address` VARCHAR(255) DEFAULT NULL,
  `country_code` CHAR(2) DEFAULT NULL,
  `iban` VARCHAR(50) DEFAULT NULL,
  `account_number` VARCHAR(80) DEFAULT NULL,
  `routing_number` VARCHAR(80) DEFAULT NULL,
  `swift_bic` VARCHAR(40) DEFAULT NULL,
  `payment_reference_template` VARCHAR(191) DEFAULT NULL,
  `transfer_instructions` TEXT DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'available',
  `notes` TEXT DEFAULT NULL,
  `created_by_admin_user_id` INT UNSIGNED DEFAULT NULL,
  `last_assigned_at` DATETIME DEFAULT NULL,
  `disabled_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bank_accounts_iban` (`iban`),
  KEY `idx_bank_accounts_currency_status` (`currency_id`, `status`),
  KEY `idx_bank_accounts_created_by_admin_user_id` (`created_by_admin_user_id`),
  CONSTRAINT `fk_bank_accounts_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_bank_accounts_created_by_admin_user` FOREIGN KEY (`created_by_admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bank_account_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_account_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `assignment_reason` VARCHAR(30) NOT NULL DEFAULT 'bank_transfer',
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `assigned_by_admin_user_id` INT UNSIGNED DEFAULT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `released_by_admin_user_id` INT UNSIGNED DEFAULT NULL,
  `released_at` DATETIME DEFAULT NULL,
  `assignment_note` TEXT DEFAULT NULL,
  `active_assignment_lock` TINYINT GENERATED ALWAYS AS (
    CASE
      WHEN `status` IN ('reserved', 'active') THEN 1
      ELSE NULL
    END
  ) STORED,
  PRIMARY KEY (`id`),
  KEY `idx_bank_account_assignments_account_status` (`bank_account_id`, `status`),
  KEY `idx_bank_account_assignments_customer_status` (`customer_id`, `status`),
  KEY `idx_bank_account_assignments_assigned_by_admin_user_id` (`assigned_by_admin_user_id`),
  KEY `idx_bank_account_assignments_released_by_admin_user_id` (`released_by_admin_user_id`),
  CONSTRAINT `fk_bank_account_assignments_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  CONSTRAINT `fk_bank_account_assignments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_bank_account_assignments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bank_account_assignments_assigned_by_admin_user` FOREIGN KEY (`assigned_by_admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bank_account_assignments_released_by_admin_user` FOREIGN KEY (`released_by_admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bank_transfer_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `bank_account_id` BIGINT UNSIGNED NOT NULL,
  `bank_account_assignment_id` BIGINT UNSIGNED DEFAULT NULL,
  `requested_amount` DECIMAL(10,2) NOT NULL,
  `currency_id` TINYINT UNSIGNED NOT NULL,
  `payment_reference` VARCHAR(191) DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending_payment',
  `created_by_admin_user_id` INT UNSIGNED DEFAULT NULL,
  `approved_by_admin_user_id` INT UNSIGNED DEFAULT NULL,
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME DEFAULT NULL,
  `submitted_at` DATETIME DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `rejected_at` DATETIME DEFAULT NULL,
  `payer_name` VARCHAR(191) DEFAULT NULL,
  `payer_bank_name` VARCHAR(191) DEFAULT NULL,
  `customer_transfer_note` TEXT DEFAULT NULL,
  `admin_review_note` TEXT DEFAULT NULL,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `open_request_lock` TINYINT GENERATED ALWAYS AS (
    CASE
      WHEN `status` IN ('pending_payment', 'awaiting_review') THEN 1
      ELSE NULL
    END
  ) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bank_transfer_requests_legacy_source` (`legacy_source_table`, `legacy_source_id`),
  UNIQUE KEY `uniq_bank_transfer_requests_account_open_lock` (`bank_account_id`, `open_request_lock`),
  KEY `idx_bank_transfer_requests_customer_status` (`customer_id`, `status`),
  KEY `idx_bank_transfer_requests_order_status` (`order_id`, `status`),
  KEY `idx_bank_transfer_requests_created_by_admin_user_id` (`created_by_admin_user_id`),
  KEY `idx_bank_transfer_requests_approved_by_admin_user_id` (`approved_by_admin_user_id`),
  CONSTRAINT `fk_bank_transfer_requests_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_bank_transfer_requests_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bank_transfer_requests_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  CONSTRAINT `fk_bank_transfer_requests_assignment` FOREIGN KEY (`bank_account_assignment_id`) REFERENCES `bank_account_assignments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bank_transfer_requests_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_bank_transfer_requests_created_by_admin_user` FOREIGN KEY (`created_by_admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bank_transfer_requests_approved_by_admin_user` FOREIGN KEY (`approved_by_admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `support_conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_type` VARCHAR(30) NOT NULL DEFAULT 'live_chat',
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `assigned_admin_id` INT UNSIGNED DEFAULT NULL,
  `subject` VARCHAR(191) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  `priority` VARCHAR(20) NOT NULL DEFAULT 'normal',
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `last_customer_message_at` DATETIME DEFAULT NULL,
  `last_admin_message_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_support_conversations_customer_status` (`customer_id`, `status`),
  KEY `idx_support_conversations_order_id` (`order_id`),
  KEY `idx_support_conversations_assigned_admin_status` (`assigned_admin_id`, `status`),
  CONSTRAINT `fk_support_conversations_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_support_conversations_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_support_conversations_admin` FOREIGN KEY (`assigned_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `support_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `sender_type` VARCHAR(20) NOT NULL,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `admin_user_id` INT UNSIGNED DEFAULT NULL,
  `message_body` LONGTEXT NOT NULL,
  `attachment_path` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_support_messages_legacy_source` (`legacy_source_table`, `legacy_source_id`),
  KEY `idx_support_messages_conversation_id_created_at` (`conversation_id`, `created_at`),
  CONSTRAINT `fk_support_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `support_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_support_messages_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_support_messages_admin` FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `support_quick_replies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `locale_code` VARCHAR(8) NOT NULL DEFAULT 'en',
  `title` VARCHAR(191) NOT NULL,
  `message_body` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 100,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_support_quick_replies_locale_title` (`locale_code`, `title`),
  KEY `idx_support_quick_replies_active_sort` (`is_active`, `locale_code`, `sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `news_posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(191) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `body` LONGTEXT NOT NULL,
  `visibility` VARCHAR(20) NOT NULL DEFAULT 'customer',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `published_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_news_posts_slug` (`slug`),
  UNIQUE KEY `uniq_news_posts_legacy_source` (`legacy_source_table`, `legacy_source_id`),
  KEY `idx_news_posts_visibility_active_published_at` (`visibility`, `is_active`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `static_pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(191) NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `body` LONGTEXT NOT NULL,
  `page_type` VARCHAR(30) NOT NULL DEFAULT 'custom',
  `locale_code` VARCHAR(8) NOT NULL DEFAULT 'pl',
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_static_pages_slug` (`slug`),
  UNIQUE KEY `uniq_static_pages_legacy_source` (`legacy_source_table`, `legacy_source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_help_topics` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question` VARCHAR(191) NOT NULL,
  `answer_html` LONGTEXT NOT NULL,
  `audience_code` VARCHAR(20) NOT NULL DEFAULT 'all',
  `keywords` VARCHAR(500) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 100,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_help_topics_active_sort` (`is_active`, `sort_order`, `id`),
  KEY `idx_admin_help_topics_audience` (`audience_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_key` VARCHAR(120) NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `subject` VARCHAR(191) NOT NULL,
  `body_html` LONGTEXT NOT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `legacy_source_table` VARCHAR(64) DEFAULT NULL,
  `legacy_source_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email_templates_template_key` (`template_key`),
  UNIQUE KEY `uniq_email_templates_legacy_source` (`legacy_source_table`, `legacy_source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_template_translations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` INT UNSIGNED NOT NULL,
  `locale_code` VARCHAR(5) NOT NULL,
  `subject` VARCHAR(191) NOT NULL,
  `body_text` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email_template_translations_template_locale` (`template_id`, `locale_code`),
  CONSTRAINT `fk_email_template_translations_template` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `outbound_email_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` INT UNSIGNED DEFAULT NULL,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `to_email` VARCHAR(191) NOT NULL,
  `subject` VARCHAR(191) NOT NULL,
  `body_html` LONGTEXT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_error` TEXT DEFAULT NULL,
  `scheduled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_outbound_email_queue_status_scheduled_at` (`status`, `scheduled_at`),
  CONSTRAINT `fk_outbound_email_queue_template` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_outbound_email_queue_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_outbound_email_queue_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
