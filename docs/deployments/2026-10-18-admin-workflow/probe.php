<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$app=$argv[1];
require $app.'/admin/bootstrap.php';
$db=Mysql_ks::get_instance();
// Warm compatibility metadata before entering the read-only snapshot.
app_ensure_sales_runtime_columns($db);
app_ensure_order_renewal_runtime_columns($db);
app_ensure_customer_runtime_columns($db);
admin_default_currency_row($db);
if(!$db->query('SET TRANSACTION READ ONLY')||!$db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT'))throw new RuntimeException('Cannot open read-only probe');
$started=microtime(true);$queries=Mysql_ks::$licznik;
AdminDashboardSalesQueryCache::$enabled=true;
try{
    foreach([30,90,365] as $days){
        $series=admin_dashboard_sales_series($db,$days);
        if(count($series)!==$days)throw new RuntimeException('Wrong reporting window');
    }
    if(count(admin_dashboard_monthly_sales_series($db,12))!==12||count(admin_dashboard_period_metrics($db))!==4)throw new RuntimeException('Invalid sales summaries');
    if(!is_array(admin_billing_stats($db)))throw new RuntimeException('Billing probe failed');
    $tasks=admin_topbar_notification_order_rows($db,8);
    $count=admin_topbar_notification_order_count($db);
    if(count($tasks)>8||count($tasks)>$count)throw new RuntimeException('Task count mismatch');
    $html=admin_topbar_order_tasks_html($tasks,$count,admin_load_messages('pl'));
    if(!str_contains($html,'data-admin-topbar-orders-section'))throw new RuntimeException('Task rendering failed');
    $flags=$db->select_user('SELECT provider_remote_login_enabled,provider_remote_subscriptions_enabled FROM app_settings WHERE id=1');
    if($db->error||(int)$flags['provider_remote_login_enabled']!==0||(int)$flags['provider_remote_subscriptions_enabled']!==0)throw new RuntimeException('Provider module is not OFF');
    echo json_encode(['read_only_probe_ok'=>true,'chart_days'=>[30,90,365],'monthly_buckets'=>12,'period_metrics'=>4,'tasks_visible'=>count($tasks),'task_count_consistent'=>true,'provider_login'=>'OFF','provider_subscriptions'=>'OFF','queries'=>Mysql_ks::$licznik-$queries,'seconds'=>round(microtime(true)-$started,3),'session_lifetime'=>app_session_cookie_lifetime()],JSON_THROW_ON_ERROR),PHP_EOL;
}finally{
    AdminDashboardSalesQueryCache::$enabled=false;AdminDashboardSalesQueryCache::$rows=[];
    $db->query('ROLLBACK');
}
