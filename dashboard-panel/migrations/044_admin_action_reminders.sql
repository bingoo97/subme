-- Global recurring action reminders; keep existing behaviour enabled by default.
SET @action_reminder_ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='app_settings' AND column_name='admin_action_reminders_enabled'), 'SELECT 1', 'ALTER TABLE app_settings ADD COLUMN admin_action_reminders_enabled TINYINT(1) NOT NULL DEFAULT 1');
PREPARE action_reminder_stmt FROM @action_reminder_ddl;
EXECUTE action_reminder_stmt;
DEALLOCATE PREPARE action_reminder_stmt;
