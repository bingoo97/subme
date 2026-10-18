<?php
/** Paid tasks, actual approval/completion, endpoint authorization and bounded reads. */
declare(strict_types=1);
if(strpos((string)getenv('DB_NAME'),'_test_')===false)throw new RuntimeException('Separate test database required.');
require_once dirname(__DIR__).'/admin/bootstrap.php';$db=Mysql_ks::get_instance();
if(($argv[1]??'')==='--endpoint') {
    $r=json_decode(base64_decode($argv[2]),true);admin_start_session();
    $_SESSION=['admin_user_id'=>$r['admin']??1,'admin_locale'=>'pl','admin_csrf_token'=>'notification-fixture'];
    $orderPage=isset($r['save_order'])||isset($r['order_page'])||isset($r['extend_order']);
    $_SERVER['HTTP_HOST']='localhost';$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['REQUEST_METHOD']=isset($r['payment'])||isset($r['save_order'])||isset($r['extend_order'])||isset($r['edit_payment'])?'POST':'GET';$_GET=['page'=>$orderPage?'orders':'payments'];$_POST=[];
    if($orderPage)$_GET['order_id']=(int)($r['save_order']??$r['order_page']??$r['extend_order']);
    $_SERVER['REQUEST_URI']='/admin/?'.http_build_query($_GET);
    if(isset($r['save_order']))$_POST=['admin_save_order_info'=>1,'order_id'=>$r['save_order'],'total_amount'=>'10','status'=>$r['order_status']??'active','payment_status'=>'paid','fulfillment_status'=>'pending','_csrf'=>'notification-fixture'];
    if(isset($r['extend_order']))$_POST=['admin_extend_order'=>1,'order_id'=>$r['extend_order'],'extend_product_id'=>2,'extend_charge_balance'=>'1','extend_expected_expires_at'=>$r['expiry'],'extend_expected_renewal_id'=>$r['renewal'],'_csrf'=>'notification-fixture'];
    if(isset($r['edit_payment']))$_POST=['admin_save_payment'=>1,'payment_type'=>$r['payment_type']??'bank','payment_id'=>$r['edit_payment'],'status'=>$r['payment_status'],'_csrf'=>'notification-fixture'];
    if(isset($r['payment']))$_POST=['admin_payment_quick_action_ajax'=>1,'payment_type'=>'bank','payment_id'=>$r['payment'],'quick_action'=>'accept','_csrf'=>'notification-fixture'];
    ob_start();register_shutdown_function(static function()use($orderPage):void {
        $body=ob_get_clean();$closed=session_status()!==PHP_SESSION_ACTIVE;
        if($closed && session_id()!=='')session_start();if(session_status()===PHP_SESSION_ACTIVE)session_destroy();
        echo json_encode(['status'=>http_response_code()?:200,'body'=>json_decode((string)$body,true),'html'=>$orderPage?$body:null,'raw'=>!$orderPage&&json_decode((string)$body,true)===null?substr((string)$body,0,1800):null,'closed'=>$closed]);
    });
    require dirname(__DIR__).(isset($r['payment'])||isset($r['edit_payment'])||$orderPage?'/admin/index.php':'/admin/topbar_state.php');exit;
}
$checks=0;function ck(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function sql(string $q):void {global $db;if(!$db->query($q))throw new RuntimeException($db->error.': '.$q);}
function endpoint(array $r=[]):array {
    $p=proc_open([PHP_BINARY,__FILE__,'--endpoint',base64_encode(json_encode($r))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);
    if(proc_close($p)!==0||!is_array($r=json_decode($out,true)))throw new RuntimeException($out.$err);return $r;
}
function ids():array {global $db;return array_map('intval',array_column(admin_topbar_notification_order_rows($db,20),'id'));}
function assert_completed_order_page(array $page,int $orderId):void {
    $dom=new DOMDocument();@$dom->loadHTML($page['html']);$xpath=new DOMXPath($dom);
    ck($xpath->query("//*[@data-admin-topbar-order-item='{$orderId}']")->length===0,'Saved and reloaded page removes the completed notification immediately');
    ck($xpath->query("//*[@id='adminOrderModal{$orderId}']")->length===1,'Saved and reloaded page renders the completed order modal');
    ck($xpath->query("//*[@data-bs-target='#adminOrderModal{$orderId}' and contains(@class,'admin-orders-details--awaiting')]")->length===0,'Completed order details button has no attention animation class');
    ck($xpath->query("//*[@id='adminOrderModal{$orderId}']//*[@data-admin-order-main-status and contains(@class,'is-success')]")->length===1,'Completed status is success instead of pulsing pending');
    ck($xpath->query("//*[@id='adminOrderModal{$orderId}']//*[contains(@class,'admin-order-modal__provider-btn') and contains(@class,'btn-warning')]")->length===0,'Completed modal has no pulsing provider action');
    ck($xpath->query("//*[@id='adminOrderModal{$orderId}']//*[@data-admin-order-status-hint]")->length===0,'Completed modal has no animated direction arrow');
}
function assert_no_order_attention(array $page,int $orderId):void {
    $dom=new DOMDocument();@$dom->loadHTML($page['html']);$x=new DOMXPath($dom);
    ck($x->query("//*[@id='adminOrderModal{$orderId}']")->length===1,'Cancelled/revoked order remains inspectable');
    ck($x->query("//*[@data-admin-topbar-order-item='{$orderId}']")->length===0,'Cancelled/revoked order has no fulfillment notification');
    ck($x->query("//*[@data-bs-target='#adminOrderModal{$orderId}' and contains(@class,'admin-orders-details--awaiting')]")->length===0,'Cancelled/revoked preview button stops pulsing');
    ck($x->query("//*[@id='adminOrderModal{$orderId}']//*[@data-admin-order-main-status and contains(@class,'is-action-required')]")->length===0,'Cancelled/revoked status stops pulsing');
    ck($x->query("//*[@id='adminOrderModal{$orderId}']//*[contains(@class,'admin-order-modal__provider-btn') and contains(@class,'btn-warning')]")->length===0,'Cancelled/revoked modal has no pulsing provider action');
    ck($x->query("//*[@id='adminOrderModal{$orderId}']//*[@data-admin-order-status-hint and not(@hidden)]")->length===0,'Cancelled/revoked order has no visible moving arrow');
}
app_ensure_sales_runtime_columns($db);app_ensure_order_renewal_runtime_columns($db);app_ensure_customer_balance_runtime_table($db);app_ensure_customer_runtime_columns($db);app_fetch_settings($db);
sql('SET FOREIGN_KEY_CHECKS=0');sql('INSERT IGNORE INTO app_settings(id) VALUES(1)');
sql("UPDATE app_settings SET maintenance_mode=0,bank_transfers_enabled=1,customer_global_group_enabled=0,admin_panel_subscription_paid_until='2035-12-31',manual_database_backup_last_downloaded_at=NOW(),default_currency_id=2,site_url='http://example.invalid'");
sql("INSERT INTO admin_roles(id,name,slug,access_level) VALUES(3,'Limited','notification-limited',100) ON DUPLICATE KEY UPDATE access_level=100");
sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(1,1,'fixture','fixture@example.invalid','unused'),(3,3,'limited','limited@example.invalid','unused')");
sql("INSERT INTO product_providers(id,name,slug,dashboard_url,is_active) VALUES(1,'Fixture provider','notification-provider','https://provider.example.invalid/admin/',1)");
sql("INSERT INTO products(id,provider_id,name,slug,price_amount,currency_id,duration_hours,is_active,product_type) VALUES(1,1,'Original','notification-original',10,2,720,1,'subscription'),(2,1,'Renewal','notification-renewal',30,2,2160,1,'subscription'),(3,1,'Credits','notification-credits',15,2,0,1,'credits')");
sql("INSERT INTO customers(id,email,public_handle,password_hash,status,balance_amount) VALUES(1,'one@example.invalid','one','unused','active',0),(2,'two@example.invalid','two','unused','active',0)");
sql("INSERT INTO bank_accounts(id,currency_id,account_holder_name,bank_name) VALUES(1,2,'Fixture','Fixture')");
$expiry=date('Y-m-d H:i:s',time()+86400);
sql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,fulfillment_status,expires_at,paid_at) VALUES(1,1,1,10,2,'pending_payment','unpaid','pending',NULL,NULL),(2,2,1,10,2,'active','paid','delivered','{$expiry}',DATE_SUB(NOW(),INTERVAL 5 DAY)),(3,1,1,10,2,'expired','paid','delivered',DATE_SUB(NOW(),INTERVAL 1 DAY),DATE_SUB(NOW(),INTERVAL 10 DAY)),(4,1,1,10,2,'cancelled','paid','pending',NULL,NOW()),(5,1,1,10,2,'active','paid','delivered','{$expiry}',NOW()),(6,1,1,10,2,'active','unpaid','delivered','{$expiry}',NULL)");
sql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,fulfillment_status) VALUES(7,1,1,10,2,'completed','paid','shipped')");
sql("INSERT INTO order_renewals(id,order_id,customer_id,product_id,price_amount,currency_id,duration_hours,status,paid_at,payment_request_type,payment_request_id) VALUES(20,2,2,2,30,2,2160,'pending_payment',NULL,'bank',20),(30,3,1,2,30,2,2160,'paid_pending_activation',DATE_SUB(NOW(),INTERVAL 2 DAY),'balance',0),(40,4,1,2,30,2,2160,'paid_pending_activation',NOW(),'balance',0),(60,6,1,2,30,2,2160,'paid_pending_activation',NOW(),'balance',0)");
sql("INSERT INTO bank_transfer_requests(id,customer_id,order_id,bank_account_id,requested_amount,currency_id,status) VALUES(1,1,1,1,10,2,'awaiting_review'),(20,2,2,1,30,2,'awaiting_review')");
ck(ids()===[3],'Only paid expired renewal needs action before approvals');
ck(admin_topbar_notification_order_count($db)===1,'Task counter includes expired paid renewal');
$r=endpoint(['payment'=>20]);ck(!empty($r['body']['ok']),'Actual renewal payment approval succeeds');
ck(in_array(2,ids(),true),'Paid renewal of an active order appears in tasks');
ck(!empty($r['body']['order']['task_html'])&&str_contains($r['body']['order']['task_html'],'Przedłuż subskrypcję'),'Approval response immediately includes the renewal next step');
$row=admin_topbar_notification_order_row_by_id($db,2);$task=admin_topbar_order_task($row,admin_load_messages('pl'));
ck($task['kind']==='renewal'&&str_contains($task['product'],'Renewal')&&str_contains($task['step_two'],'Przedłuż'),'Renewal uses its paid package and correct confirmation tab');
ck(str_contains($task['amount'],'30.00'),'Task displays the paid renewal amount instead of the initial order price');
ck($task['payment_url']==='/admin/?page=payments&payment_type=bank&payment_id=20','Task points to the renewal payment, not an older initial payment');
ck(ids()[0]===3,'Older unfinished task stays first after a newer approval');
$r=endpoint(['payment'=>1]);ck(!empty($r['body']['ok'])&&in_array(1,ids(),true),'Actual initial approval creates an activation task');
$task=admin_topbar_order_task(admin_topbar_notification_order_row_by_id($db,1),admin_load_messages('pl'));
ck(str_contains($task['amount'],'10.00'),'Initial task displays the order price');
ck($task['kind']==='activation'&&str_contains($task['step_two'],'AKTYWNE'),'Initial order receives activation instructions');
ck(!in_array(4,ids(),true)&&!in_array(5,ids(),true)&&!in_array(6,ids(),true),'Cancelled, fulfilled and unpaid orders do not produce tasks');
ck(admin_topbar_notification_order_count($db)===3,'Exactly one task per actionable order');
// Reversal must synchronize unfinished work, without duplicating financial entries on re-approval.
foreach([1,20] as $paymentId){
    $orderId=$paymentId===1?1:2;
    foreach(['awaiting_review','pending_payment'] as $reversedStatus){
        $beforeReverse=endpoint();
        $ledgerBefore=$db->select_user('SELECT COUNT(*) n FROM customer_balance_runtime_events')['n'];
        $balanceBefore=$db->select_user('SELECT SUM(balance_amount) amount FROM customers')['amount'];
        $reversed=endpoint(['edit_payment'=>$paymentId,'payment_status'=>$reversedStatus]);
        ck($reversed['status']===302&&$db->select_user("SELECT status FROM bank_transfer_requests WHERE id={$paymentId}")['status']===$reversedStatus,'Real payment editor saves the revoked approval');
        ck(admin_topbar_notification_order_row_by_id($db,$orderId)===null,'Reversed initial/renewal approval removes the actionable order');
        $afterReverse=endpoint();
        ck($afterReverse['body']['orders_count']===$beforeReverse['body']['orders_count']-1&&$afterReverse['body']['orders_revision']!==$beforeReverse['body']['orders_revision'],'Reversal decreases the live task counter and invalidates stale tasks');
        assert_no_order_attention(endpoint(['order_page'=>$orderId]),$orderId);
        $acceptedAgain=endpoint(['payment'=>$paymentId]);
        ck(!empty($acceptedAgain['body']['ok'])&&admin_topbar_notification_order_row_by_id($db,$orderId)!==null,'Re-approval restores exactly the required fulfillment task '.$paymentId.' '.json_encode($acceptedAgain));
        ck($db->select_user('SELECT COUNT(*) n FROM customer_balance_runtime_events')['n']===$ledgerBefore&&$db->select_user('SELECT SUM(balance_amount) amount FROM customers')['amount']===$balanceBefore,'Reversal and re-approval preserve the ledger and do not credit or debit twice');
    }
}
$beforeExtension=endpoint();
$extendRequest=['extend_order'=>2,'expiry'=>$expiry,'renewal'=>20];
$completed=endpoint($extendRequest);
ck($completed['status']===200&&$db->select_user('SELECT status FROM order_renewals WHERE id=20')['status']==='applied'&&!in_array(2,ids(),true),'Actual Extend tab POST applies renewal and clears its task');
foreach([$completed,endpoint(['order_page'=>2])] as $page)assert_completed_order_page($page,2);
$afterExtension=endpoint();
ck($afterExtension['body']['orders_count']===$beforeExtension['body']['orders_count']-1&&$afterExtension['body']['orders_revision']!==$beforeExtension['body']['orders_revision']&&!str_contains($afterExtension['body']['orders_html'],'data-admin-topbar-order-item="2"'),'Applied renewal decreases the live counter and removes its task and pulsing action');
$extendedExpiry=$db->select_user('SELECT expires_at FROM orders WHERE id=2')['expires_at'];
$repeat=endpoint($extendRequest);
ck($repeat['status']===200&&$db->select_user('SELECT expires_at FROM orders WHERE id=2')['expires_at']===$extendedExpiry&&!in_array(2,ids(),true),'Repeated confirmation neither extends twice nor restores the notification');
assert_completed_order_page($repeat,2);
ck((float)$db->select_user('SELECT balance_amount FROM customers WHERE id=2')['balance_amount']===0.0,'Completing paid renewal does not debit twice');
$beforeCompletion=endpoint();
$done=endpoint(['save_order'=>1]);
$saved=$db->select_user('SELECT status,payment_status,fulfillment_status FROM orders WHERE id=1');
ck($done['status']===200&&$saved['status']==='active'&&$saved['payment_status']==='paid'&&$saved['fulfillment_status']==='delivered','Actual modal POST saves Active and normalizes delivery without a separate shipping change');
ck(!in_array(1,ids(),true),'Saved activation clears its task');
$afterCompletion=endpoint();
ck($afterCompletion['body']['orders_count']===$beforeCompletion['body']['orders_count']-1&&$afterCompletion['body']['orders_revision']!==$beforeCompletion['body']['orders_revision'],'Completion decreases the live task count and invalidates the fragment revision');
ck(!str_contains($afterCompletion['body']['orders_html'],'data-admin-topbar-order-item="1"'),'Completed order is absent from the live notification fragment');
foreach([$done,endpoint(['order_page'=>1])] as $page)assert_completed_order_page($page,1);
$row=admin_topbar_notification_order_row_by_id($db,3);$html=admin_topbar_order_tasks_html([$row],1,admin_load_messages('pl'));
ck(str_contains($html,'data-task-kind="renewal"')&&str_contains($html,'provider.example.invalid')&&str_contains($html,'Do zrobienia'),'Warning task renders provider and order destinations');
$row['dashboard_url']='javascript:alert(1)';$row['public_handle']='"><script>bad</script>';
$html=admin_topbar_order_tasks_html([$row],1,admin_load_messages('pl'));
ck(!str_contains($html,'javascript:')&&!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'),'Task content and provider links are escaped');
$row['dashboard_url']='http://provider.example.invalid:2052/admin';ck(admin_topbar_order_task($row,[])['provider_url']===$row['dashboard_url'],'Configured HTTP provider on a custom port remains available');
for($i=100;$i<120;$i++)sql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,fulfillment_status,paid_at) VALUES({$i},1,1,10,2,'pending_payment','paid','pending',NOW())");
$before=Mysql_ks::$licznik;$rows=admin_topbar_notification_order_rows($db,8);$queries=Mysql_ks::$licznik-$before;
ck(count($rows)===8&&admin_topbar_notification_order_count($db)===21,'Eight visible tasks do not truncate the actual counter');
ck($queries===1,'A task list is one query, without per-order reads');
$html=admin_topbar_order_tasks_html($rows,21,admin_load_messages('pl'));ck(str_contains($html,'admin-topbar-task__all'),'Overflow has a link to all orders');
$r=endpoint();ck(!empty($r['body']['ok'])&&$r['body']['orders_count']===21&&$r['closed'],'Live refresh returns tasks and releases the session');
ck(strlen($r['body']['orders_revision'])===64&&$r['body']['orders_revision']===endpoint()['body']['orders_revision'],'Unchanged task list has a stable revision');
ck(endpoint(['admin'=>0])['status']===401,'Anonymous refresh denied');
ck(endpoint(['admin'=>3])['body']['orders_count']===0,'Accounts without Orders access do not receive task details');
// Expired subscriptions follow the same completion path once their approved funds are available.
ck(!empty(app_apply_customer_balance_runtime_event($db,1,30,'credit','payment_approval','fixture-expired-renewal','Fixture')['ok']),'Expired renewal fixture has its paid funds');
$expiredBefore=endpoint();$expiredExpiry=$db->select_user('SELECT expires_at FROM orders WHERE id=3')['expires_at'];
$expiredDone=endpoint(['extend_order'=>3,'expiry'=>$expiredExpiry,'renewal'=>30]);
$expiredSaved=$db->select_user('SELECT status,payment_status,fulfillment_status,expires_at FROM orders WHERE id=3');
ck($expiredDone['status']===200&&$expiredSaved['status']==='active'&&$expiredSaved['payment_status']==='paid'&&$expiredSaved['fulfillment_status']==='delivered'&&strtotime($expiredSaved['expires_at'])>time()&&$db->select_user('SELECT status FROM order_renewals WHERE id=30')['status']==='applied','Expired paid subscription becomes active after the real Extend POST');
foreach([$expiredDone,endpoint(['order_page'=>3])] as $page)assert_completed_order_page($page,3);
$expiredAfter=endpoint();ck($expiredAfter['body']['orders_count']===$expiredBefore['body']['orders_count']-1&&!str_contains($expiredAfter['body']['orders_html'],'data-admin-topbar-order-item="3"'),'Expired renewal completion removes its notification and decreases the live counter');
// Cancel a paid initial order and a paid renewal through the real order form.
foreach([200=>false,201=>true] as $orderId=>$renewal){
    sql("INSERT INTO customers(id,email,password_hash,status,balance_amount) VALUES({$orderId},'cancel{$orderId}@example.invalid','unused','active',0)");
    $initialStatus=$renewal?'active':'pending_payment';$initialDelivery=$renewal?'delivered':'pending';
    sql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,fulfillment_status,expires_at,paid_at) VALUES({$orderId},{$orderId},1,10,2,'{$initialStatus}','unpaid','{$initialDelivery}','{$expiry}',NOW())");
    if($renewal){
        sql("UPDATE orders SET payment_status='paid' WHERE id={$orderId}");
        sql("INSERT INTO order_renewals(id,order_id,customer_id,product_id,price_amount,currency_id,duration_hours,status,payment_request_type,payment_request_id) VALUES({$orderId},{$orderId},{$orderId},2,30,2,2160,'pending_payment','bank',{$orderId})");
    }
    $amount=$renewal?30:10;
    sql("INSERT INTO bank_transfer_requests(id,customer_id,order_id,bank_account_id,requested_amount,currency_id,status) VALUES({$orderId},{$orderId},{$orderId},1,{$amount},2,'awaiting_review')");
    ck(!empty(endpoint(['payment'=>$orderId])['body']['ok'])&&admin_topbar_notification_order_row_by_id($db,$orderId)!==null,'Cancellation fixture starts with a real approved task');
    $beforeCancel=endpoint();$cancelled=endpoint(['save_order'=>$orderId,'order_status'=>'cancelled']);
    ck($cancelled['status']===200&&$db->select_user("SELECT status FROM orders WHERE id={$orderId}")['status']==='cancelled','Real order form cancels the initial/renewal order');
    foreach([$cancelled,endpoint(['order_page'=>$orderId])] as $page)assert_no_order_attention($page,$orderId);
    $afterCancel=endpoint();ck($afterCancel['body']['orders_count']===$beforeCancel['body']['orders_count']-1,'Cancellation removes exactly its own task from the live counter');
    ck(admin_order_status_visual(['status'=>'cancelled','payment_status'=>'paid','fulfillment_status'=>'pending','has_paid_pending_renewal'=>1])['class']==='admin-order-status-icon--cancelled','Cancelled status takes precedence over pending delivery or stale renewal flags');
}
// Cancelling the accepted renewal payment must preserve the already active base subscription.
sql("INSERT INTO customers(id,email,password_hash,status,balance_amount) VALUES(203,'renewal-cancel-payment@example.invalid','unused','active',0)");
sql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,fulfillment_status,expires_at,paid_at) VALUES(203,203,1,10,2,'active','paid','delivered','{$expiry}',NOW())");
sql("INSERT INTO order_renewals(id,order_id,customer_id,product_id,price_amount,currency_id,duration_hours,status,payment_request_type,payment_request_id) VALUES(203,203,203,2,30,2,2160,'pending_payment','bank',203)");
sql("INSERT INTO bank_transfer_requests(id,customer_id,order_id,bank_account_id,requested_amount,currency_id,status) VALUES(203,203,203,1,30,2,'awaiting_review')");
ck(!empty(endpoint(['payment'=>203])['body']['ok'])&&admin_topbar_notification_order_row_by_id($db,203)!==null,'Accepted renewal cancellation fixture requires fulfillment');
$beforeRenewalCancel=endpoint();$cancelRenewalPayment=endpoint(['edit_payment'=>203,'payment_status'=>'cancelled']);
ck($cancelRenewalPayment['status']===302&&$db->select_user('SELECT status FROM order_renewals WHERE id=203')['status']==='cancelled','Cancelling accepted payment cancels only its pending renewal');
$baseOrder=$db->select_user('SELECT status,payment_status,expires_at FROM orders WHERE id=203');
ck($baseOrder['status']==='active'&&$baseOrder['payment_status']==='paid'&&$baseOrder['expires_at']===$expiry,'Cancellation preserves the previously fulfilled subscription and expiry');
assert_no_order_attention(endpoint(['order_page'=>203]),203);
ck(endpoint()['body']['orders_count']===$beforeRenewalCancel['body']['orders_count']-1,'Cancelled renewal payment removes exactly its fulfillment task');
// Reopening a historical initial payment must not undo access already fulfilled.
$historical=endpoint(['edit_payment'=>1,'payment_status'=>'awaiting_review']);
$historicalOrder=$db->select_user('SELECT status,payment_status,fulfillment_status FROM orders WHERE id=1');
ck($historical['status']===302&&$historicalOrder['status']==='active'&&$historicalOrder['payment_status']==='paid'&&$historicalOrder['fulfillment_status']==='delivered','Revising a historical payment does not revoke completed customer access');
assert_no_order_attention(endpoint(['order_page'=>1]),1);
// Crypto uses the same reversal synchronization, with a real wallet record and no external requests.
$btc=(int)$db->select_user("SELECT id FROM crypto_assets WHERE code='BTC'")['id'];
sql("INSERT INTO customers(id,email,password_hash,status,balance_amount) VALUES(202,'crypto-revoke@example.invalid','unused','active',0)");
sql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,fulfillment_status,paid_at) VALUES(202,202,1,10,2,'pending_payment','paid','pending',NOW())");
sql("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,network_code,status) VALUES(202,{$btc},'reversal-fixture-wallet','bitcoin','assigned')");
sql("INSERT INTO crypto_deposit_requests(id,customer_id,order_id,crypto_asset_id,wallet_address_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,status) VALUES(202,202,202,{$btc},202,10,2,50000,.0002,'approved')");
ck(admin_topbar_notification_order_row_by_id($db,202)!==null,'Crypto approved order initially requires action');
$cryptoReversed=endpoint(['edit_payment'=>202,'payment_type'=>'crypto','payment_status'=>'cancelled']);
ck($cryptoReversed['status']===302&&$db->select_user('SELECT payment_status FROM orders WHERE id=202')['payment_status']==='unpaid','Crypto editor reversal synchronizes unpaid order state');
assert_no_order_attention(endpoint(['order_page'=>202]),202);
echo "PASS {$checks} notification workflow, priority, completion and live-refresh assertions / {$queries} task-list query\n";
