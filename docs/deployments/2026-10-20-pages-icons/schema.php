<?php
declare(strict_types=1); if(PHP_SAPI!=='cli')exit(1);
$app=$argv[1];require $app.'/admin/bootstrap.php';$db=Mysql_ks::get_instance();
app_ensure_static_pages_runtime_columns($db);
foreach(['menu_icon','menu_badge_new'] as $column)if(!schema_column_exists($db,'static_pages',$column))throw new RuntimeException('Missing page appearance column');
echo "ADDITIVE_PAGE_APPEARANCE_COLUMNS_OK_EXISTING_CONTENT_PRESERVED\n";
