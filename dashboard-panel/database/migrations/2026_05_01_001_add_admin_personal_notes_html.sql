ALTER TABLE `admin_users`
ADD COLUMN `personal_notes_html` LONGTEXT NULL
AFTER `public_handle`;
