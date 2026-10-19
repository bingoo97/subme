<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require $argv[1].'/config/mysql.php';
$db=Mysql_ks::get_instance();
$column=$db->select_user("SHOW COLUMNS FROM app_settings LIKE 'admin_action_reminders_enabled'");
if(!$column&&!$db->query("ALTER TABLE app_settings ADD COLUMN admin_action_reminders_enabled TINYINT(1) NOT NULL DEFAULT 1"))throw new RuntimeException('Reminder setting schema failed');
echo "REMINDER_SETTING_COLUMN_READY_NO_ROW_IMPORTS\n";
