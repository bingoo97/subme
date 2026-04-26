ALTER TABLE `app_settings`
  ADD COLUMN IF NOT EXISTS `support_chat_retention_days` TINYINT UNSIGNED NOT NULL DEFAULT 7 AFTER `support_chat_enabled`;

UPDATE `app_settings`
SET `support_chat_retention_days` = LEAST(GREATEST(COALESCE(`support_chat_retention_days`, 7), 1), 30);
