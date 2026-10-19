<?php
/** Render real entry points on fixtures; never copy user data or call market APIs. */
declare(strict_types=1);
if(strpos((string)getenv('DB_NAME'),'_test_')===false)throw new RuntimeException('Separate test database required.');
if(($argv[1]??'')==='--page') {
    $r=json_decode(base64_decode($argv[2]),true);
    $sessionDir=(string)getenv('APP_CRYPTO_CACHE_DIR').'/sessions';if(!is_dir($sessionDir))mkdir($sessionDir,0700,true);
    session_save_path($sessionDir);session_start();
    $_SESSION=$r['admin']?['admin_user_id'=>1,'admin_locale'=>'pl','admin_csrf_token'=>'fixture']:['id'=>1,'lang'=>'pl','__csrf_frontend'=>'fixture'];
    $_SERVER['HTTP_HOST']='localhost';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['REQUEST_URI']=$r['admin']?'/admin/?page='.$r['page']:'/'.$r['page'];
    $_GET=['site'=>$r['page']];if($r['admin'])parse_str('page='.$r['page'],$_GET);$_POST=[];
    $GLOBALS['app_crypto_network_budget']=['deadline'=>0,'remaining'=>0];
    $warnings=[];set_error_handler(static function($level,$message,$file,$line)use(&$warnings):bool {if(error_reporting()&$level)$warnings[]=basename($file).':'.$line.' '.$message;return true;},E_WARNING|E_NOTICE|E_USER_WARNING);
    $started=microtime(true);ob_start();register_shutdown_function(static function()use($started,&$warnings):void {
        $html=ob_get_clean();$fatal=error_get_last();if(session_status()===PHP_SESSION_ACTIVE)session_destroy();
        echo json_encode(['status'=>http_response_code()?:200,'queries'=>class_exists('Mysql_ks',false)?Mysql_ks::$licznik:0,'ms'=>round((microtime(true)-$started)*1000,1),'bytes'=>strlen((string)$html),'html'=>strpos((string)$html,'<html')!==false,'payment_type_filters'=>str_contains((string)$html,'admin-payments-filter-group--types'),'warnings'=>$warnings,'fatal'=>$fatal&&in_array($fatal['type'],[E_ERROR,E_PARSE,E_COMPILE_ERROR,E_CORE_ERROR])?$fatal:null]);
    });
    chdir(dirname(__DIR__));require $r['admin']?dirname(__DIR__).'/admin/index.php':dirname(__DIR__,2).'/public_html/index.php';exit;
}
require_once dirname(__DIR__).'/admin/bootstrap.php';$db=Mysql_ks::get_instance();
function sql(string $q):void {global $db;if(!$db->query($q))throw new RuntimeException($db->error.': '.$q);}
admin_ensure_user_runtime_columns($db);app_ensure_sales_runtime_columns($db);app_ensure_order_renewal_runtime_columns($db);app_ensure_customer_runtime_columns($db);admin_app_settings($db);
sql('SET FOREIGN_KEY_CHECKS=0');sql('INSERT IGNORE INTO app_settings(id) VALUES(1)');
sql("UPDATE app_settings SET maintenance_mode=0,admin_panel_subscription_paid_until='2035-12-31',support_chat_enabled=1,customer_messenger_enabled=1,customer_global_group_enabled=1,demo_messenger_showcase_enabled=0,billing_enabled=1,sales_enabled=1,site_url='http://localhost:8088',default_currency_id=2");
sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(1,1,'smoke-admin','admin@example.invalid','unused')");
sql("INSERT INTO product_providers(id,name,slug,is_active) VALUES(1,'Fixture provider','smoke-provider',1)");
sql("INSERT INTO products(id,provider_id,name,slug,price_amount,currency_id,duration_hours,is_active) VALUES(1,1,'Fixture training','smoke-training',18,2,720,1)");
for($i=1;$i<=20;$i++){
 sql("INSERT INTO customers(id,email,public_handle,password_hash,status,locale_code,balance_amount) VALUES({$i},'smoke{$i}@example.invalid','smoke{$i}','unused','active','pl',100)");
 sql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,fulfillment_status,paid_at,expires_at) VALUES({$i},{$i},1,18,2,'active','paid','delivered',NOW(),DATE_ADD(NOW(),INTERVAL 3 DAY))");
}
$btc=(int)$db->select_user("SELECT id FROM crypto_assets WHERE code='BTC'")['id'];
sql("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,status,network_code) VALUES(1,{$btc},'fixture-public-wallet','available','BTC')");
sql("INSERT INTO crypto_deposit_requests(id,customer_id,crypto_asset_id,wallet_address_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,status,requested_at) VALUES(1,1,{$btc},1,18,2,60000,.0003,'pending',NOW())");
$checks=0;
foreach([false=>['','orders','payments_crypto','history','settings','news','faq','instructions','contact','referrals'],true=>['dashboard','orders','payments','users','products','billing','news','pages','email-templates','faq','help','settings','live-chat','bank-accounts','crypto-wallets','cryptocurrencies','users&customer_id=1','payments&payment_type=crypto&payment_id=1','products&edit_provider=1','billing&billing_tab=customers']] as $admin=>$pages)foreach($pages as $page){
 $p=proc_open([PHP_BINARY,__FILE__,'--page',base64_encode(json_encode(['admin'=>(bool)$admin,'page'=>$page]))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);$code=proc_close($p);$r=json_decode($out,true);
 if($code||!is_array($r)||$r['status']!==200||!$r['html']||$r['fatal']||$r['warnings'])throw new RuntimeException(($admin?'admin':'client').'/'.$page.': '.$out.$err);
 $checks++;echo ($admin?'admin':'client').'/'.($page?:'home').': '.$r['queries'].' SQL / '.$r['ms'].' ms / '.round($r['bytes']/1024)." KiB\n";
}
foreach([[1,0],[1,1],[0,1],[0,0]] as [$cryptoEnabled,$bankEnabled]){
 sql("UPDATE app_settings SET crypto_payments_enabled={$cryptoEnabled},bank_transfers_enabled={$bankEnabled} WHERE id=1");
 $p=proc_open([PHP_BINARY,__FILE__,'--page',base64_encode(json_encode(['admin'=>true,'page'=>'payments']))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);$code=proc_close($p);$r=json_decode($out,true);
 if($code||!is_array($r)||$r['status']!==200||$r['fatal']||$r['warnings']||$r['payment_type_filters']!==($cryptoEnabled===1&&$bankEnabled===1))throw new RuntimeException('Payment type selector visibility failed: '.$out.$err);
 $checks++;
}
echo "PASS {$checks} complete page renders without PHP warnings\n";
