<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$app=$argv[1];require $app.'/admin/bootstrap.php';require $app.'/admin/crypto_quick.php';
$db=Mysql_ks::get_instance();$counts=admin_crypto_quick_counts($db);
$view=admin_crypto_reminder_view($db,'read-only-deploy-probe',admin_load_messages('pl'),$counts,'pl');
if(empty($view['ok'])||!in_array($view['filter'],[null,'new','expired','payout'],true))throw new RuntimeException('Reminder query failed');
if($view['filter']!==null&&(!is_string($view['html'])||!is_array($view['copy'])))throw new RuntimeException('Reminder rendering failed');
foreach(['new','expired','payout','cancelled'] as $filter){
 $rows=admin_crypto_quick_rows($db,$filter,1);
 $html=admin_crypto_quick_html($rows,$filter,admin_crypto_quick_labels('pl'),'read-only-deploy-probe',admin_load_messages('pl'));
 if($rows&&!str_contains($html,'admin-crypto-quick__purpose'))throw new RuntimeException('Missing payment purpose');
}
$web=trim(file_get_contents($app.'/.public-root-path'));$js=file_get_contents($web.'/assets/js/admin-crypto-reminder.js');
if(!str_contains($js,'const interval = 10 * 60 * 1000;')||str_contains($js,'testMode'))throw new RuntimeException('Reminder still in testing mode');
echo "REMINDER_TEN_MINUTES_PURPOSE_PRIORITY_READ_ONLY_QUERIES_OK\n";
