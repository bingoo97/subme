-- Provider remote login foundation: configuration only, no attempt history.
SET @provider_remote_ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='app_settings' AND column_name='provider_remote_login_enabled'), 'SELECT 1', 'ALTER TABLE app_settings ADD COLUMN provider_remote_login_enabled TINYINT(1) NOT NULL DEFAULT 0');
PREPARE provider_remote_stmt FROM @provider_remote_ddl;
EXECUTE provider_remote_stmt;
DEALLOCATE PREPARE provider_remote_stmt;
SET @provider_remote_ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='app_settings' AND column_name='provider_remote_subscriptions_enabled'), 'SELECT 1', 'ALTER TABLE app_settings ADD COLUMN provider_remote_subscriptions_enabled TINYINT(1) NOT NULL DEFAULT 0');
PREPARE provider_remote_stmt FROM @provider_remote_ddl;
EXECUTE provider_remote_stmt;
DEALLOCATE PREPARE provider_remote_stmt;
CREATE TABLE IF NOT EXISTS provider_remote_login_configs (
 provider_id INT UNSIGNED NOT NULL PRIMARY KEY,
 config_json TEXT NOT NULL,
 credentials_encrypted TEXT NOT NULL,
 CONSTRAINT fk_provider_remote_config FOREIGN KEY (provider_id) REFERENCES product_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
