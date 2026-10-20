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

$active=['status'=>'active','payment_status'=>'paid','product_type'=>'subscription','provider_id'=>1,'expires_at'=>'2030-11-01 12:00:00'];
$package=['id'=>1,'is_active'=>1,'product_type'=>'subscription','provider_id'=>1,'duration_hours'=>2160,'is_trial'=>0];
if(app_order_extension_target_expiry($active,$package)!=='2031-02-01 12:00:00')throw new RuntimeException('Active renewal base failed');
$expired=$active;$expired['status']='expired';$expired['expires_at']='2020-01-01 12:00:00';
$before=time();$target=app_order_extension_target_expiry($expired,$package);$after=time();
if($target<app_subscription_expiry_from_base_timestamp($before,2160)||$target>app_subscription_expiry_from_base_timestamp($after,2160))throw new RuntimeException('Expired renewal base failed');
if(admin_order_extension_requested_expiry(['extend_predicted_expiry'=>'2030-01-01T12:00','extend_automatic_expiry'=>'2030-01-01T12:00'])!=='')throw new RuntimeException('Stale automatic date not ignored');
if(admin_order_extension_requested_expiry(['extend_predicted_expiry'=>'2030-02-01T12:00','extend_automatic_expiry'=>'2030-01-01T12:00'])!=='2030-02-01T12:00')throw new RuntimeException('Custom expiry not preserved');
$web=$argv[2]??trim(file_get_contents($app.'/.public-root-path'));
$js=file_get_contents($web.'/assets/js/admin.js');
$index=file_get_contents($app.'/admin/index.php');
if(!str_contains($js,'if (automaticField) automaticField.value = previewField.value;')||!str_contains($index,'admin_order_extension_requested_expiry($_POST)')||!str_contains($index,'data-admin-order-extend-automatic'))throw new RuntimeException('Automatic renewal form integration missing');
if(!str_contains($code,"\$paidPackage['duration_hours'] = (int)(\$pendingRenewal['duration_hours'] ?? \$durationHours);"))throw new RuntimeException('Paid package duration not preserved');
echo "ACTIVE_EXPIRED_RENEWAL_BASES_AUTOMATIC_PREVIEW_CUSTOM_OVERRIDE_AND_PAID_PERIOD_OK_NO_MUTATIONS\n";

$view=file_get_contents($app.'/admin/views/crypto_quick.php');
$css=file_get_contents($web.'/assets/css/admin.css');
$create=strpos($view,'admin-crypto-quick__footer--create');$all=strpos($view,'<a class="admin-crypto-quick__footer"');
if($create===false||$all===false||$create>=$all||!str_contains(substr($view,$create,180),'data-crypto-create-open'))throw new RuntimeException('Footer create action order/handler mismatch');
foreach(['height:32px;min-height:32px;padding:5px 16px;','flex:0 0 32px;width:32px','justify-content:center;'] as $marker)if(!str_contains($css,$marker))throw new RuntimeException('Compact aligned footer styles missing');
echo "PERMANENT_CREATE_FOOTER_32PX_ROWS_AND_ALIGNED_ICONS_OK\n";

foreach(['display:grid;grid-template-columns:minmax(0,1fr);gap:8px;',':is(:link,:visited,:hover,:focus,:active){text-decoration:none;}','min-width:0;margin:0;padding:12px 10px;gap:10px;'] as $marker)if(!str_contains($css,$marker))throw new RuntimeException('Separated customer card styles missing');
echo "CUSTOMER_CARDS_SEPARATED_AND_HANDLE_UNDERLINE_REMOVED_OK\n";

require_once $app.'/bootstrap/localization.php';
if(count(app_page_tile_icons())!==15)throw new RuntimeException('Wrong tile icon count');
foreach(array_keys(app_page_tile_icons()) as $icon)if(app_page_tile_icon($icon)!==$icon)throw new RuntimeException('Icon allowlist mismatch');
if(app_page_tile_icon('untrusted')!=='play-circle-o')throw new RuntimeException('Unsafe icon fallback');
foreach(['pl','en'] as $locale){$tiles=app_customer_homepage_pages($db,$locale);foreach($tiles as $tile)if(!array_key_exists('badge_new',$tile)||!array_key_exists('icon',$tile))throw new RuntimeException('Homepage tile fields missing');}
echo "HOMEPAGE_TILE_ICONS_AND_BADGE_READS_OK_NO_CONTENT_CREATED\n";
