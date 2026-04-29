SET @migration_name = '008_admin_dashboard_change_log';

INSERT INTO `schema_migrations` (`migration_name`)
SELECT @migration_name
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1
  FROM `schema_migrations`
  WHERE `migration_name` = @migration_name
);

CREATE TABLE IF NOT EXISTS `admin_dashboard_change_log` (
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
