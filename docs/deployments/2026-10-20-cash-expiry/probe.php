<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit(1);
$app=$argv[1];require $app.'/admin/bootstrap.php';app_crypto_network_budget(0,0);$db=Mysql_ks::get_instance();
$code=file_get_contents($app.'/admin/bootstrap.php');
foreach(["// Deferred orders start their package on fulfilment.","admin_normalize_datetime_input(\$order['started_at'] ?? null)","admin_normalize_datetime_input(\$order['expires_at'] ?? null)","admin_normalize_datetime_input(\$order['paid_at'] ?? null)"] as $marker)if(!str_contains($code,$marker))throw new RuntimeException('Expiry fix missing');
$three=app_subscription_expiry_from_base_timestamp(app_timestamp_from_runtime_datetime('2026-10-20 10:30:00'),2160);
if($three!=='2027-01-20 10:30:00')throw new RuntimeException('Three-month calendar duration failed');
$sample=['product_type'=>'subscription','started_at'=>'2026-10-20 10:30:00','expires_at'=>$three,'duration_hours'=>2160];
foreach([admin_order_progress_data($sample),app_order_progress_data($sample)] as $progress)if(empty($progress['has_expiry'])||$progress['remaining_seconds']<=0)throw new RuntimeException('Three-month countdown not available');
$rows=$db->select_full_user("SELECT o.id,o.started_at,o.created_at,o.expires_at,p.product_type,p.duration_hours FROM orders o JOIN products p ON p.id=o.product_id WHERE o.status='active' AND o.payment_status='paid' AND o.expires_at IS NOT NULL AND p.product_type='subscription' AND p.duration_hours>0 ORDER BY o.id DESC LIMIT 50");
if($db->error)throw new RuntimeException('Countdown read failed');
foreach($rows as $row){$admin=admin_order_progress_data($row);$client=app_order_progress_data($row);
 if(!$admin['has_expiry']||!$client['has_expiry']||abs($admin['remaining_seconds']-$client['remaining_seconds'])>2)throw new RuntimeException('Live admin/client countdown mismatch');
}
$missing=$db->select_user("SELECT COUNT(*) n FROM orders o JOIN products p ON p.id=o.product_id WHERE o.payment_method='cash' AND o.status='active' AND o.payment_status='paid' AND o.expires_at IS NULL AND p.duration_hours>0 AND p.product_type='subscription'");
if($db->error||(int)$missing['n']!==0)throw new RuntimeException('Existing cash order requires expiry repair');
echo 'THREE_MONTHS_AND_LIVE_ADMIN_CLIENT_COUNTDOWNS_OK rows='.count($rows)." missing_cash_expiries=0 NO_FINANCIAL_MUTATIONS\n";
