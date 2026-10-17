<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
[$self, $app, $stage, $backup] = $argv;
require $app . '/config/mysql.php';
$db = Mysql_ks::get_instance();
$schema = [];
foreach (['orders','order_renewals','crypto_deposit_requests','crypto_deposit_transactions'] as $table) {
    $row = $db->select_user('SHOW CREATE TABLE `' . $table . '`');
    if (!$row) throw new RuntimeException('Required table missing: ' . $table);
    $schema[$table] = $row;
}
file_put_contents($backup . '/schema-before.json', json_encode($schema, JSON_PRETTY_PRINT));
foreach (['019_preserve_original_sales.sql','020_dashboard_sales_query_index.sql','021_crypto_scan_queue.sql'] as $file) {
    $sql = file_get_contents($stage . '/dashboard-panel/database/migrations/' . $file);
    $sql = preg_replace('/^\s*--[^\n]*$/m', '', $sql);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        if (trim($statement) === '') continue;
        $result = $db->query($statement);
        if ($result === false) throw new RuntimeException('Migration failed: ' . $file . ': ' . $db->error);
        if ($result instanceof mysqli_result) $result->free();
    }
    echo 'SCHEMA_OK ' . $file . PHP_EOL;
}
