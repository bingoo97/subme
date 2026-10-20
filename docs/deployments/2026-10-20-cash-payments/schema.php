<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit(1);
$app=$argv[1];$stage=$argv[2];require $app.'/config/mysql.php';$db=Mysql_ks::get_instance();
function counts(Mysql_ks $db):array {
 $out=[];foreach(['customers','orders','order_renewals','crypto_deposit_requests','bank_transfer_requests','customer_balance_runtime_events','customer_activity_logs'] as $table){
  $r=$db->select_user("SELECT COUNT(*) n FROM {$table}");if($db->error)throw new RuntimeException('Preflight count failed');$out[$table]=(int)$r['n'];
 }return $out;
}
$before=counts($db);
$sql=file_get_contents($stage.'/dashboard-panel/migrations/045_admin_cash_payments.sql');
if(!str_starts_with($sql,'CREATE TABLE IF NOT EXISTS admin_cash_payments (')||substr_count($sql,';')!==1)throw new RuntimeException('Unexpected migration');
for($i=0;$i<2;$i++)if(!$db->query($sql))throw new RuntimeException('Cash receipt migration failed');
if(counts($db)!==$before)throw new RuntimeException('Business row counts changed during migration; inspect concurrent activity');
$index=$db->select_full_user("SHOW INDEX FROM admin_cash_payments WHERE Key_name='cash_operation' AND Non_unique=0");
if(count($index)!==1||$index[0]['Column_name']!=='operation_key')throw new RuntimeException('Cash idempotency key missing');
$count=$db->select_user('SELECT COUNT(*) n FROM admin_cash_payments');
echo 'CASH_SCHEMA_IDEMPOTENT_UNIQUE_OPERATION_KEY_OK_EXISTING_BUSINESS_COUNTS_PRESERVED cash_rows='.(int)$count['n'].PHP_EOL;
