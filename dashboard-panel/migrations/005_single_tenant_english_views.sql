-- 005_single_tenant_english_views.sql
-- Additional English compatibility views for application-facing reads.
-- Assumes phase 004 has already been applied.

DROP VIEW IF EXISTS `application_settings`;
CREATE VIEW `application_settings` AS
SELECT
    `id`,
    `technical_break` AS `maintenance_mode`,
    `admin_email`,
    `page_title`,
    `page_name`,
    `page_url`,
    `page_logo`,
    `page_desc` AS `page_description`,
    `page_keywords` AS `seo_keywords`,
    COALESCE(`results_per_page`, CAST(`liczba_wynikow` AS UNSIGNED)) AS `results_per_page`,
    `currency` AS `currency_id`,
    `smtp_port`,
    `smtp_host`,
    `smtp_login`,
    COALESCE(`smtp_password`, `smtp_haslo`) AS `smtp_password`,
    `active_register` AS `registration_enabled`,
    `active_sale` AS `sales_enabled`,
    `active_trials` AS `trials_enabled`,
    `email_limit`,
    `count_news` AS `news_limit`,
    `homepage_verify` AS `homepage_verification_enabled`,
    COALESCE(`api_key`, `apikey`) AS `api_key`
FROM `settings`;

DROP VIEW IF EXISTS `email_templates`;
CREATE VIEW `email_templates` AS
SELECT
    `id`,
    COALESCE(`name`, `nazwa`) AS `name`,
    COALESCE(`content`, `tresc`) AS `content`
FROM `komunikaty`;

DROP VIEW IF EXISTS `tenant_users`;
CREATE VIEW `tenant_users` AS
SELECT
    `id`,
    COALESCE(`tenant_id`, `res_id`) AS `tenant_id`,
    `email`,
    `password`,
    COALESCE(`registered_at`, `date_register`) AS `registered_at`,
    COALESCE(`last_login_at`, `last_login`) AS `last_login_at`,
    COALESCE(`ip_address`, `ip`) AS `ip_address`,
    `country`,
    COALESCE(
        `locale_code`,
        CASE COALESCE(`lang`, 0)
            WHEN 1 THEN 'pl'
            WHEN 2 THEN 'de'
            ELSE 'en'
        END
    ) AS `locale_code`,
    COALESCE(`is_active`, `status`) AS `is_active`
FROM `users`;

DROP VIEW IF EXISTS `tenant_products`;
CREATE VIEW `tenant_products` AS
SELECT
    `id`,
    COALESCE(`tenant_id`, `res_id`) AS `tenant_id`,
    `provider_id`,
    `name`,
    `desc`,
    `trial`,
    `duration`,
    `price`,
    COALESCE(`is_active`, `status`) AS `is_active`
FROM `products`;

DROP VIEW IF EXISTS `product_orders`;
CREATE VIEW `product_orders` AS
SELECT
    `id`,
    COALESCE(`tenant_id`, `res_id`) AS `tenant_id`,
    `product_id`,
    `user_id`,
    `price`,
    `note`,
    `link_url`,
    COALESCE(`created_at`, `date_add`) AS `created_at`,
    COALESCE(`end_at`, `date_end`) AS `end_at`,
    COALESCE(`payment_status`, `payment`) AS `payment_status`,
    COALESCE(`shipment_status`, `shipment`) AS `shipment_status`,
    COALESCE(`is_active`, `status`) AS `is_active`
FROM `products_users`;
