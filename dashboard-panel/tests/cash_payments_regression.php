<?php
declare(strict_types=1);
if(strpos((string)getenv('DB_NAME'),'_test_')===false)throw new RuntimeException('Isolated _test_ database required.');
require_once dirname(__DIR__).'/admin/bootstrap.php';
require_once dirname(__DIR__).'/admin/cash_payments.php';
$db=Mysql_ks::get_instance();app_crypto_network_budget(0,0);
if(($argv[1]??'')==='--cash-race'){echo json_encode(admin_create_cash_payment($db,32004,1,['purpose'=>'topup','operation_key'=>str_repeat('7',32),'amount'=>'10']));exit;}
function cashCheck(bool $ok,string $message):void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function cashSql(string $q):void {global $db;if(!$db->query($q))throw new RuntimeException($db->error);}
function cashBalance(int $id):float {global $db;return (float)$db->select_user("SELECT balance_amount FROM customers WHERE id={$id}")['balance_amount'];}
function cashCreate(int $id,string $purpose,string $key,array $extra=[]):array {global $db;return admin_create_cash_payment($db,$id,1,array_merge(['purpose'=>$purpose,'operation_key'=>str_pad($key,32,'a'),'amount'=>'23.50','product_id'=>0,'order_id'=>0],$extra));}
$checks=0;
admin_cash_payment_schema($db);
for($i=32001;$i<=32008;$i++)cashSql("INSERT INTO customers(id,email,public_handle,password_hash,status,locale_code,balance_amount) VALUES({$i},'cash{$i}@example.invalid','cash{$i}','unused','active','pl',100)");
$rollback=cashCreate(32008,'topup','8');cashCheck(empty($rollback['ok'])&&cashBalance(32008)===100.0,'History failure rolls back cash credit');
cashCheck((int)$db->select_user("SELECT COUNT(*) n FROM admin_cash_payments WHERE customer_id=32008")['n']===0,'History failure rolls back receipt');
$requests=(int)$db->select_user('SELECT COUNT(*) n FROM crypto_deposit_requests')['n'];
$r=cashCreate(32001,'topup','1');cashCheck(!empty($r['ok']),'Cash topup saved: '.json_encode($r));cashCheck(cashBalance(32001)===123.50,'Topup credits balance');
$again=cashCreate(32001,'topup','1');cashCheck(!empty($again['already_applied'])&&cashBalance(32001)===123.50,'Replay never double credits');
cashCheck(empty(cashCreate(32001,'topup','1',['amount'=>'24'])['ok']),'Same operation cannot change amount');
cashCheck(empty(cashCreate(32001,'topup','2',['amount'=>'23foo'])['ok']),'Invalid numeric suffix rejected');
cashCheck(empty(cashCreate(32001,'topup','3',['amount'=>'0'])['ok']),'Zero rejected');
cashCheck(empty(cashCreate(32001,'topup','4',['amount'=>'201'])['ok']),'Limit enforced');
cashCheck(empty(cashCreate(32001,'bad','5')['ok']),'Purpose whitelist');
cashSql("UPDATE customers SET status='blocked' WHERE id=32002");cashCheck(empty(cashCreate(32002,'topup','6')['ok']),'Blocked customer rejected');
cashCheck((int)$db->select_user('SELECT COUNT(*) n FROM crypto_deposit_requests')['n']===$requests,'No crypto tickets created');
cashCheck((int)$db->select_user("SELECT COUNT(*) n FROM customer_activity_logs WHERE customer_id=32001 AND action_key='cash_payment_received' AND description LIKE '%Gotówka%'")['n']===1,'Cash history once');
$normalized=cashCreate(32004,'topup','9',['order_id'=>1,'product_id'=>1]);cashCheck(!empty($normalized['ok']),'Topup ignores unrelated order inputs');
$normalizedReceipt=$db->select_user('SELECT * FROM admin_cash_payments WHERE id='.(int)$normalized['receipt_id']);cashCheck($normalizedReceipt['order_id']===null,'Topup never attaches an unrelated order');
$endpointFile=__DIR__.'/crypto_quick_create_regression.php';
$call=static function(array $request)use($endpointFile):array {
 $p=proc_open([PHP_BINARY,$endpointFile,'--endpoint',base64_encode(json_encode($request))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $f)fclose($f);if(proc_close($p))throw new RuntimeException($out.$err);return json_decode($out,true);
};
$base=['action'=>'crypto_quick_create_submit','payment_method'=>'cash','customer_id'=>32003,'purpose'=>'topup','amount'=>'12.50','operation_key'=>str_repeat('b',32),'_csrf'=>'fixture'];
$denied=$call(['post'=>array_merge($base,['_csrf'=>'bad'])]);cashCheck($denied['status']===403&&cashBalance(32003)===100.0,'Cash endpoint requires CSRF');
$bad=$call(['post'=>array_merge($base,['operation_key'=>['bad']])]);cashCheck($bad['status']===422,'Scalar validation for cash token');
$good=$call(['post'=>$base]);cashCheck($good['status']===200&&!empty($good['data']['cash'])&&cashBalance(32003)===112.50,'Cash topup through real endpoint');
$repeat=$call(['post'=>$base]);cashCheck(!empty($repeat['data']['already_applied'])&&cashBalance(32003)===112.50,'Endpoint replay idempotent without cooldown error');
$opts=$call(['get'=>['action'=>'crypto_quick_create_options','customer_id'=>32003]]);cashCheck($opts['status']===200,'Options available without assigned wallet');
// Cash orders credit now; fulfilment debits exactly once.
$new=cashCreate(32005,'new_order','c',['product_id'=>1]);cashCheck(!empty($new['ok']),'New cash order: '.json_encode($new));$newId=(int)$new['order_id'];
$newOrder=admin_order_find($db,$newId);cashCheck($newOrder['status']==='pending_payment'&&$newOrder['payment_status']==='paid'&&$newOrder['payment_method']==='cash','Cash order paid, awaiting fulfilment');
cashCheck(cashBalance(32005)===118.0,'New cash order adds funds until fulfilment');
cashCheck(count(admin_topbar_notification_order_rows($db,8,$newId))===1,'New paid cash order appears in work notifications');
cashCheck(!empty(admin_order_balance_activation_context($db,$newOrder)['is_awaiting_activation']),'Open order suggests fulfilment');
$activate=['status'=>'active','payment_status'=>'paid','fulfillment_status'=>'delivered','total_amount'=>'18','payment_method'=>'cash','expires_at'=>date('Y-m-d H:i:s',strtotime('+30 days'))];
$done=admin_save_order_info($db,$newId,$activate,1);cashCheck(!empty($done['ok'])&&cashBalance(32005)===100.0,'Fulfilment debits cash once: '.json_encode($done));
$done=admin_save_order_info($db,$newId,$activate,1);cashCheck(!empty($done['ok'])&&cashBalance(32005)===100.0,'Repeated fulfilment does not debit again');
$paidAgain=cashCreate(32005,'new_order','c',['product_id'=>1]);cashCheck(!empty($paidAgain['already_applied'])&&cashBalance(32005)===100.0,'Replay after fulfilment preserves balance');
// Existing unpaid order: paid from cash, without creating a second order.
cashSql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,fulfillment_status) VALUES(33001,32006,1,18,2,'pending_payment','unpaid','pending')");
$existing=cashCreate(32006,'existing_order','d',['order_id'=>33001]);cashCheck(!empty($existing['ok'])&&(int)$existing['order_id']===33001&&cashBalance(32006)===118.0,'Cash pays selected unpaid order');
// Both renewal modes use the exact selected package and preserve original sale.
for($i=33002;$i<=33003;$i++)cashSql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,fulfillment_status,paid_at,expires_at) VALUES({$i},32007,1,18,2,'active','paid','delivered',NOW(),DATE_ADD(NOW(),INTERVAL 3 DAY))");
$expiry=(string)$db->select_user('SELECT expires_at FROM orders WHERE id=33002')['expires_at'];
$pending=cashCreate(32007,'existing_order','e',['order_id'=>33002,'product_id'=>1,'expected_expiry'=>$expiry]);cashCheck(!empty($pending['ok'])&&!$pending['renewal_applied']&&cashBalance(32007)===118.0,'Renewal checkbox OFF credits funds');
$renewal=app_order_find_pending_renewal($db,33002);cashCheck($renewal['status']==='paid_pending_activation'&&$renewal['payment_request_type']==='cash','Cash renewal paid, awaiting manual extension');
cashCheck($db->select_user('SELECT expires_at FROM orders WHERE id=33002')['expires_at']===$expiry,'OFF preserves expiry');
$context=admin_order_pending_renewal_context($db,admin_order_find($db,33002));cashCheck($context['has_paid_pending_activation']&&$context['payment_type']==='cash'&&$context['payment_amount']===18.0,'Renewal context exposes cash receipt and pending action');
cashCheck(empty(cashCreate(32007,'existing_order','f',['order_id'=>33002,'product_id'=>1,'expected_expiry'=>$expiry])['ok']),'Pending renewal cannot receive a second cash payment');
cashCheck(count(admin_topbar_notification_order_rows($db,8,33002))===1,'Paid cash renewal appears in work notifications');
$extended=admin_extend_order($db,33002,1,true,'',$expiry,(int)$renewal['id']);cashCheck(!empty($extended['ok'])&&cashBalance(32007)===100.0,'Later manual extension debits once: '.json_encode($extended));
$extendedAgain=admin_extend_order($db,33002,1,true,'',$expiry,(int)$renewal['id']);cashCheck(!empty($extendedAgain['ok'])&&cashBalance(32007)===100.0,'Repeated pending extension does not double debit');
cashCheck(admin_topbar_notification_order_rows($db,8,33002)===[],'Completed cash renewal leaves work notifications');
$expiry2=(string)$db->select_user('SELECT expires_at FROM orders WHERE id=33003')['expires_at'];
$immediate=cashCreate(32007,'existing_order','0',['order_id'=>33003,'product_id'=>1,'expected_expiry'=>$expiry2,'apply_renewal'=>'1']);cashCheck(!empty($immediate['ok'])&&!empty($immediate['renewal_applied'])&&cashBalance(32007)===100.0,'Checkbox ON credits and debits atomically: '.json_encode($immediate));
$after=$db->select_user('SELECT * FROM orders WHERE id=33003');cashCheck($after['expires_at']>$expiry2&&$after['initial_sale_amount']==='18.00','Immediate extension changes expiry and preserves first sale');
$immediateAgain=cashCreate(32007,'existing_order','0',['order_id'=>33003,'product_id'=>1,'expected_expiry'=>$expiry2,'apply_renewal'=>'1']);cashCheck(!empty($immediateAgain['already_applied'])&&$db->select_user('SELECT expires_at FROM orders WHERE id=33003')['expires_at']===$after['expires_at'],'Immediate extension replay does not add days twice');
cashCheck(empty(cashCreate(32007,'existing_order','a',['order_id'=>33003,'product_id'=>1,'expected_expiry'=>$expiry2,'apply_renewal'=>'1'])['ok']),'Stale expiry rejected before credit');
cashCheck((int)$db->select_user('SELECT COUNT(*) n FROM crypto_deposit_requests')['n']===$requests,'Orders and both renewal modes create no crypto requests');
cashCheck(admin_topbar_notification_order_rows($db,8,33003)===[],'Immediate cash renewal requires no further fulfilment alert');
$deniedCash=$call(['admin_id'=>99,'post'=>array_merge($base,['operation_key'=>str_repeat('f',32)])]);cashCheck($deniedCash['status']===403,'Unauthorized role cannot record cash');
require_once dirname(__DIR__).'/admin/dashboard_sales.php';
$source=admin_dashboard_sales_source_sql($db);$reported=$db->select_user("SELECT COUNT(*) n,SUM(amount) total FROM ({$source}) s WHERE order_id IN ({$newId},33001,33002,33003)");
cashCheck((int)$reported['n']===6 && (float)$reported['total']===108.0,'Cash purchases and renewals reported once, without counting balance credits again');
cashCheck(empty(cashCreate(32004,'existing_order','abc',['order_id'=>33003,'product_id'=>1,'expected_expiry'=>$after['expires_at']])['ok']),'Cannot pay or extend another customer order');
cashSql("INSERT INTO customer_provider_visibility(customer_id,provider_id,is_visible) VALUES(32004,1,0)");
cashCheck(empty(cashCreate(32004,'new_order','abd',['product_id'=>1])['ok']),'Hidden provider rejected server side');
$beforeRace=cashBalance(32004);$racers=[];
for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'--cash-race'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$racers[]=[$proc,$pipes];}
foreach($racers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $f)fclose($f);cashCheck(proc_close($proc)===0&&is_array(json_decode($out,true)),'Parallel cash request finishes safely: '.$err);}
cashCheck(cashBalance(32004)===$beforeRace+10.0,'Concurrent identical cash submissions credit once');
$beforeFailure=(int)$db->select_user('SELECT COUNT(*) n FROM orders')['n'];
$failedNew=cashCreate(32008,'new_order','6',['product_id'=>1]);cashCheck(empty($failedNew['ok'])&&cashBalance(32008)===100.0&&(int)$db->select_user('SELECT COUNT(*) n FROM orders')['n']===$beforeFailure,'Failed cash order rolls back order and balance');
require_once dirname(__DIR__).'/bootstrap/localization.php';
$history=app_customer_history_rows($db,32001,[]);cashCheck((bool)array_filter($history,static fn($entry)=>str_contains(json_encode($entry,JSON_UNESCAPED_UNICODE),'Gotówka')),'Customer History displays cash entry');
echo "PASS {$checks} cash payment checks\n";
