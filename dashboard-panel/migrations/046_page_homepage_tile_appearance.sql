-- Per-page homepage tile icon and optional NEW badge. Existing pages keep their appearance.
SET @page_tile_ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='static_pages' AND column_name='menu_icon'), 'SELECT 1', "ALTER TABLE static_pages ADD COLUMN menu_icon VARCHAR(40) NOT NULL DEFAULT 'file-text-o'");
PREPARE page_tile_stmt FROM @page_tile_ddl;
EXECUTE page_tile_stmt;
DEALLOCATE PREPARE page_tile_stmt;
SET @page_tile_ddl = IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='static_pages' AND column_name='menu_badge_new'), 'SELECT 1', 'ALTER TABLE static_pages ADD COLUMN menu_badge_new TINYINT(1) NOT NULL DEFAULT 0');
PREPARE page_tile_stmt FROM @page_tile_ddl;
EXECUTE page_tile_stmt;
DEALLOCATE PREPARE page_tile_stmt;
