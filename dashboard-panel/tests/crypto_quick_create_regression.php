<?php
/** Isolated database and cached rates only; never contact a payment network. */
declare(strict_types=1);
if(strpos((string)getenv('DB_NAME'),'_test_')===false)throw new RuntimeException('Isolated _test_ database required.');
if(($argv[1]??'')==='--customer-chat') {
 $GLOBALS['app_crypto_network_budget']=['deadline'=>0,'remaining'=>0];$r=json_decode(base64_decode($argv[2]),true);
 $dir=getenv('APP_CRYPTO_CACHE_DIR').'/sessions';if(!is_dir($dir))mkdir($dir,0700,true);session_save_path($dir);session_start();
 $_SESSION=['id'=>$r['customer'],'__csrf_frontend'=>'fixture','lang'=>'pl'];$_GET=array_merge(['format'=>'json'],$r['get']??[]);$_POST=[];
 $_SERVER['REQUEST_METHOD']='GET';$_SERVER['HTTP_HOST']='localhost';$_SERVER['REQUEST_URI']='/check_chat.php';chdir(dirname(__DIR__));ob_start();
 register_shutdown_function(static function(){ $out=ob_get_clean();if(session_status()===PHP_SESSION_ACTIVE)session_destroy();echo json_encode(['status'=>http_response_code()?:200,'body'=>json_decode($out,true),'raw'=>json_decode($out,true)===null?$out:null]); });
 require dirname(__DIR__).'/check_chat.php';exit;
}
if(($argv[1]??'')==='--endpoint') {
 $r=json_decode(base64_decode($argv[2]),true);
 $dir=getenv('APP_CRYPTO_CACHE_DIR').'/sessions';if(!is_dir($dir))mkdir($dir,0700,true);session_save_path($dir);session_start();
 $_SESSION=['admin_user_id'=>$r['admin_id']??1,'admin_locale'=>'pl','admin_csrf_token'=>'fixture'];
 $_GET=$r['get']??[];$_POST=$r['post']??[];$_SERVER['REQUEST_METHOD']=$_POST?'POST':'GET';$_SERVER['HTTP_HOST']='localhost';
 $GLOBALS['app_crypto_network_budget']=['deadline'=>0,'remaining'=>0];
 ob_start();register_shutdown_function(static function(){echo json_encode(['status'=>http_response_code()?:200,'data'=>json_decode(ob_get_clean(),true)]);});
 require dirname(__DIR__).'/admin/search.php';exit;
}
if(($argv[1]??'')==='--preview') {
 $dir=getenv('APP_CRYPTO_CACHE_DIR').'/sessions';if(!is_dir($dir))mkdir($dir,0700,true);session_save_path($dir);session_start();
 $_SESSION=['admin_user_id'=>1,'admin_locale'=>'pl','admin_csrf_token'=>'fixture'];
 $_SERVER['HTTP_HOST']='localhost';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REMOTE_ADDR']='127.0.0.1';$previewPage=getenv('APP_UI_PAGE')==='payments'?'payments':'dashboard';$_SERVER['REQUEST_URI']='/admin/?page='.$previewPage;$_GET=['page'=>$previewPage];$_POST=[];
 $GLOBALS['app_crypto_network_budget']=['deadline'=>0,'remaining'=>0];
 ob_start();register_shutdown_function(static function(){file_put_contents('/tmp/subme-quick-create-preview/index.html',ob_get_clean());});
 require dirname(__DIR__).'/admin/index.php';exit;
}
if(($argv[1]??'')==='--race') {
 require_once dirname(__DIR__).'/admin/bootstrap.php';$db=Mysql_ks::get_instance();app_crypto_network_budget(0,0);
 $btc=(int)$db->select_user("SELECT id FROM crypto_assets WHERE code='BTC'")['id'];
 echo json_encode(admin_quick_create_crypto_payment_request($db,8,$btc,'',1,[],1,'new_order'));exit;
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

app_crypto_network_budget(0,0); require_once dirname(__DIR__).'/admin/crypto_quick.php';
$checks=0;
function ck(bool $ok,string $message):void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function endpoint(array $r):array {
 $p=proc_open([PHP_BINARY,__FILE__,'--endpoint',base64_encode(json_encode($r))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $f)fclose($f);$code=proc_close($p);$data=json_decode($out,true);
 ck(!$code&&is_array($data),'Endpoint completed: '.$out.$err);return $data;
}
function create(int $c,string $purpose,int $product=1,int $order=0,$amount=999):array {
 global $db,$btc;return admin_quick_create_crypto_payment_request($db,$c,$btc,$amount,1,[], $product,$purpose,$order);
}
function countRows(string $table,string $where):int {global $db;return (int)$db->select_user("SELECT COUNT(*) n FROM {$table} WHERE {$where}")['n'];}
function checkClientInstructions(int $customerId,array $created,int $orderId):void {
 global $db;
 ck(!empty($created['live_chat_message_created']),'Creation reports chat card saved');
 $request=$db->select_user('SELECT r.*,w.address,w.network_code,a.code,f.code AS currency_code,f.symbol AS currency_symbol FROM crypto_deposit_requests r JOIN crypto_wallet_addresses w ON w.id=r.wallet_address_id JOIN crypto_assets a ON a.id=r.crypto_asset_id JOIN currencies f ON f.id=r.fiat_currency_id WHERE r.id='.(int)$created['request_id']);
 $row=$db->select_user("SELECT m.*,c.customer_id AS owner_id,c.conversation_type FROM support_messages m JOIN support_conversations c ON c.id=m.conversation_id WHERE c.customer_id={$customerId} ORDER BY m.id DESC LIMIT 1");
 ck($row&&$row['sender_type']==='admin'&&(int)$row['owner_id']===$customerId&&$row['conversation_type']==='live_chat'&&(int)$row['is_read']===0,'Correct customer receives an unread support message');
 $card=app_chat_card_decode($row['message_body']);ck($card&&$card['kind']==='payment_request'&&$card['payment_kind']==='crypto'&&$card['locale_code']==='pl','Message is the standard localized crypto payment card');
 $values=array_column($card['fields'],'value');
 ck(in_array($request['address'],$values,true)&&in_array(sprintf('%.8f',(float)$request['requested_crypto_amount']).' '.$request['code'],$values,true),'Card address and token amount equal the committed ticket');
 ck(in_array(app_crypto_network_label($request['network_code']),$values,true),'Card names the receiving network');
 ck(in_array(app_format_money_value($request['requested_fiat_amount'],$request['currency_symbol'],$request['currency_code']),$values,true),'Card fiat amount and currency equal committed ticket');
 $suffix=$orderId>0?'/orders?payment='.$orderId:'/payments_crypto#tickets';ck(str_ends_with($card['button_url'],$suffix),'Card opens the correct order or balance payment');
 $html=chat_format_message_html($row['message_body']);ck(str_contains($html,$request['address'])&&str_contains($html,'chat-payment-card'),'Client renderer displays the payment card');
 $count=countRows('support_messages',"customer_id={$customerId}");
 $retry=create($customerId,'topup',0,0,20);ck(empty($retry['ok'])&&countRows('support_messages',"customer_id={$customerId}")===$count,'Repeated create cannot duplicate the chat card');
}
function clientChat(int $customerId):array{
 $payload=base64_encode(json_encode(['customer'=>$customerId,'get'=>['action'=>'fetch']]));
 $p=proc_open([PHP_BINARY,__FILE__,'--customer-chat',$payload],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $f)fclose($f);$code=proc_close($p);$data=json_decode($out,true);ck(!$code&&is_array($data)&&$data['raw']===null,'Customer chat endpoint returns clean JSON: '.$out.$err);return $data;
}

sql("UPDATE app_settings SET crypto_payments_enabled=1,bank_transfers_enabled=0,support_chat_enabled=0");
sql("UPDATE crypto_assets SET is_active=IF(code='BTC',1,0),current_rate_fiat=50000,rate_currency_code='EUR',rate_updated_at=UTC_TIMESTAMP()");
sql("UPDATE crypto_deposit_requests SET status='cancelled'");
for($i=1;$i<=20;$i++){
 $w=100+$i;
 sql("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,status,network_code) VALUES({$w},{$btc},'fixture-quick-wallet-{$i}','assigned','bitcoin')");
 sql("INSERT INTO crypto_wallet_assignments(wallet_address_id,customer_id,status) VALUES({$w},{$i},'active')");
}
$dir='/tmp/subme-quick-create-preview';if(!is_dir($dir))mkdir($dir,0700,true);
$search=endpoint(['get'=>['action'=>'crypto_quick_create_search','q'=>'smoke']]);ck($search['status']===200&&str_contains($search['data']['html'],'data-admin-payment-customer-select'),'Search contains selectable customer cards');
ck(str_contains($search['data']['html'],'data-create-customer-profile href="/admin/?page=users&amp;customer_id='),'Customer handles link to Users profile');
file_put_contents($dir.'/search.json',json_encode($search['data']));
$options=endpoint(['get'=>['action'=>'crypto_quick_create_options','customer_id'=>2]]);ck($options['status']===200&&count($options['data']['items'])===1&&$options['data']['items'][0]['has_existing_assignment']===1,'Assigned wallet appears without a free pool');file_put_contents($dir.'/options.json',json_encode($options['data']));
ck(count($options['data']['product_options'])===1,'Only visible products are offered');
$r=create(2,'topup',0,0,25);ck(!empty($r['ok']), 'Top-up created: '.json_encode($r));
$row=$db->select_user('SELECT * FROM crypto_deposit_requests WHERE id='.(int)$r['request_id']);
ck(empty($row['order_id'])&&(float)$row['requested_fiat_amount']===25.0,'Top-up amount and purpose stored');checkClientInstructions(2,$r,0);
ck(strtotime($row['expires_at'])-strtotime($row['requested_at'])===3600,'Ticket lasts exactly 60 minutes');
ck((float)$db->select_user('SELECT balance_amount FROM customers WHERE id=2')['balance_amount']===100.0,'Creation does not credit balance');
$before=countRows('orders','customer_id=3');$apiNew=endpoint(['post'=>['action'=>'crypto_quick_create_submit','customer_id'=>3,'purpose'=>'new_order','product_id'=>1,'asset_id'=>$btc,'_csrf'=>'fixture']]);ck($apiNew['status']===200,'New order created through shared UI endpoint');$r=$apiNew['data'];ck(!empty($r['ok']),'New order created: '.json_encode($r));
$row=$db->select_user('SELECT * FROM orders WHERE id='.(int)$r['order_id']);ck($row['status']==='pending_payment'&&$row['payment_status']==='unpaid'&&empty($row['expires_at'])&&(float)$row['total_amount']===18.0,'New order uses server price and awaits payment, with no running expiry');
ck(countRows('orders','customer_id=3')===$before+1,'Exactly one new order');checkClientInstructions(3,$r,(int)$r['order_id']);
ck(empty(create(3,'new_order')['ok'])&&countRows('orders','customer_id=3')===$before+1,'Repeated create does not duplicate order');
sql("UPDATE orders SET status='pending_payment',payment_status='unpaid',paid_at=NULL,total_amount=31 WHERE id=4");
$r=create(4,'existing_order',1,4);ck(!empty($r['ok'])&&(int)$r['order_id']===4,'Existing unpaid order linked: '.json_encode($r));
$row=$db->select_user('SELECT * FROM crypto_deposit_requests WHERE id='.(int)$r['request_id']);ck((float)$row['requested_fiat_amount']===31.0,'Existing order server amount overrides posted product/amount');checkClientInstructions(4,$r,4);
ck(empty(create(5,'existing_order',1,4)['ok']),'Another customer order rejected');
ck(empty(create(5,'existing_order',1,5)['ok']),'Already paid order rejected');
app_ensure_customer_provider_visibility_runtime_table($db);sql("INSERT INTO customer_provider_visibility(customer_id,provider_id,is_visible) VALUES(6,1,0)");
ck(empty(create(6,'new_order')['ok']),'Hidden provider rejected');
ck(admin_quick_crypto_payment_data($db,6)['product_options']===[],'Hidden providers absent from options');
$balance=(float)$db->select_user('SELECT balance_amount FROM customers WHERE id=7')['balance_amount'];
$before=countRows('orders','customer_id=7');

ck(empty(create(7,'new_order')['ok']),'Link failure reported');
ck(countRows('orders','customer_id=7')===$before&&countRows('crypto_deposit_requests','customer_id=7')===0,'Link failure rolls back both request and order');ck(countRows('support_messages','customer_id=7')===0,'Rolled-back payment never sends misleading payment instructions');
sql('UPDATE app_settings SET support_chat_enabled=1');
foreach([2,3,4] as $clientId){$chat=clientChat($clientId);ck(!empty($chat['body']['ok'])&&str_contains($chat['body']['html'],'fixture-quick-wallet-'.$clientId),'Actual client messenger fetch contains the committed wallet instructions');}
$private=clientChat(5);ck(!str_contains($private['body']['html'],'fixture-quick-wallet-3'),'Other clients cannot read the order payment instructions');sql('UPDATE app_settings SET support_chat_enabled=0');
$children=[];for($i=0;$i<4;$i++){$p=proc_open([PHP_BINARY,__FILE__,'--race'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$children[]=[$p,$pipes];}
$success=0;foreach($children as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $f)fclose($f);ck(proc_close($p)===0,'Concurrent request completes: '.$err);$success+=!empty(json_decode($out,true)['ok'])?1:0;}
ck($success===1&&countRows('crypto_deposit_requests','customer_id=8')===1&&countRows('orders','customer_id=8')===2,'Parallel clicks create one ticket and one order');ck(countRows('support_messages','customer_id=8')===1,'Parallel clicks create only one customer instruction card');
$r=endpoint(['post'=>['action'=>'crypto_quick_create_submit','customer_id'=>9,'purpose'=>'topup','asset_id'=>$btc,'amount'=>20,'_csrf'=>'wrong']]);ck($r['status']===403&&countRows('crypto_deposit_requests','customer_id=9')===0,'CSRF blocks mutation');
$r=endpoint(['post'=>['action'=>'crypto_quick_create_submit','customer_id'=>9,'purpose'=>'topup','asset_id'=>$btc,'amount'=>20,'_csrf'=>'fixture']]);ck($r['status']===200&&!empty($r['data']['ok'])&&str_contains($r['data']['crypto_quick']['html'],'@smoke9'),'Endpoint returns fresh list and counts');file_put_contents($dir.'/created.json',json_encode($r['data']));
sql("UPDATE crypto_deposit_requests SET status='awaiting_review',received_crypto_amount=.00001,expires_at=DATE_SUB(NOW(),INTERVAL 4 HOUR) WHERE customer_id=9");
ck(empty(create(9,'new_order')['ok']),'Expired but detected transfer blocks new ticket');
$pendingOptions=endpoint(['get'=>['action'=>'crypto_quick_create_options','customer_id'=>9]]);
ck(!empty($pendingOptions['data']['has_pending_payment'])&&(int)$pendingOptions['data']['pending_payment_id']>0,'Blocked wizard offers the detected existing payment for review');
// Unassigned customer can use a free wallet; it is reserved once at creation.
sql('DELETE FROM crypto_wallet_assignments WHERE customer_id=10');sql("UPDATE crypto_wallet_addresses SET status='available' WHERE id=110");
$before=countRows('crypto_wallet_assignments','customer_id=10');$r=create(10,'topup',0,0,15);ck(!empty($r['ok']),'Free wallet assigned');ck(countRows('crypto_wallet_assignments','customer_id=10')===$before+1,'Only one assignment is created');
ck(empty(create(10,'topup',0,0,15)['ok'])&&countRows('crypto_wallet_assignments','customer_id=10')===$before+1,'Repeated create never duplicates wallet assignment');
sql('DELETE FROM crypto_wallet_assignments WHERE customer_id=11');sql("UPDATE crypto_wallet_addresses SET status='disabled',disabled_at=NOW() WHERE status='available' OR id=111");
$options=admin_quick_crypto_payment_data($db,11);ck($options['items']===[],'Assets without available or assigned wallet are omitted');
ck(empty(create(11,'topup',0,0,15)['ok']),'Unavailable wallet cannot create a ticket');
sql("UPDATE crypto_wallet_addresses SET status='disabled',disabled_at=NOW() WHERE id=112");ck(empty(create(12,'topup',0,0,15)['ok']),'Disabled assigned wallet rejected');
sql("INSERT INTO admin_roles(id,name,slug,access_level) VALUES(99,'No payments','fixture-no-payments',0)");
sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(99,99,'no-payments','denied@example.invalid','unused')");
$r=endpoint(['admin_id'=>99,'get'=>['action'=>'crypto_quick_create_options','customer_id'=>13]]);ck($r['status']===403,'Unauthorized role cannot read payment options');
$r=endpoint(['admin_id'=>99,'post'=>['action'=>'crypto_quick_create_submit','customer_id'=>13,'purpose'=>'new_order','product_id'=>1,'asset_id'=>$btc,'_csrf'=>'fixture']]);ck($r['status']===403&&countRows('crypto_deposit_requests','customer_id=13')===0,'Unauthorized role cannot create payment or order');
$r=endpoint(['post'=>['action'=>'crypto_quick_create_submit','customer_id'=>0,'purpose'=>'invalid','_csrf'=>'fixture']]);ck($r['status']===422,'Invalid purpose rejected');
sql("UPDATE products SET price_amount=350 WHERE id=1");
$r=create(15,'new_order');ck(!empty($r['ok']),'Server-priced packages above the manual top-up range work');
$row=$db->select_user('SELECT requested_fiat_amount FROM crypto_deposit_requests WHERE id='.(int)$r['request_id']);ck((float)$row['requested_fiat_amount']===350.0,'Server-priced package amount is exact');
sql("UPDATE products SET price_amount=18,currency_id=1 WHERE id=1");ck(empty(create(16,'new_order')['ok']),'Product in another currency rejected');sql('UPDATE products SET currency_id=2 WHERE id=1');
sql('UPDATE product_providers SET is_active=0 WHERE id=1');ck(empty(create(16,'new_order')['ok']),'Inactive provider rejected');sql('UPDATE product_providers SET is_active=1 WHERE id=1');
ck(empty(create(16,'topup',0,0,-10)['ok']),'Negative top-up rejected');
sql("UPDATE customers SET status='inactive' WHERE id=17");ck(empty(create(17,'new_order')['ok']),'Inactive customer cannot create ticket');
ck(countRows('crypto_wallet_assignments','customer_id=13')===1,'Options/search never create extra wallet assignments');
$empty=admin_crypto_quick_html([],'new',null,'fixture');ck(str_contains($empty,'data-crypto-create-open'),'Empty New category has create button');ck(!str_contains(admin_crypto_quick_html([],'expired',null,'fixture'),'data-crypto-create-open'),'Other empty categories are unchanged');
$large=$db->select_user('SELECT id,order_id FROM crypto_deposit_requests WHERE customer_id=15 LIMIT 1');
sql("UPDATE crypto_deposit_requests SET status='expired',expires_at=DATE_SUB(NOW(),INTERVAL 1 HOUR) WHERE id=".(int)$large['id']);
$renewed=admin_payment_apply_quick_action($db,'crypto',(int)$large['id'],'renew',1,'fixture');
ck(!empty($renewed['ok']),'Large server-priced order ticket can be renewed after expiry: '.json_encode($renewed));
$renewedRow=$db->select_user('SELECT * FROM crypto_deposit_requests WHERE id='.(int)($renewed['payment_id']??0));
ck((float)$renewedRow['requested_fiat_amount']===350.0&&(int)$renewedRow['order_id']===(int)$large['order_id']&&strtotime($renewedRow['expires_at'])-strtotime($renewedRow['requested_at'])===3600,'Renewal retains the order, amount and 60-minute ticket');
$orderCount=countRows('orders','customer_id=13');$cancelledOrder=create(13,'new_order');
ck(!empty($cancelledOrder['ok']),'Order/ticket created for cancellation and retry');
ck(!empty(admin_payment_apply_quick_action($db,'crypto',(int)$cancelledOrder['request_id'],'cancel',1,'fixture')['ok']),'Created order ticket can be cancelled');
$afterCancel=$db->select_user('SELECT * FROM orders WHERE id='.(int)$cancelledOrder['order_id']);
ck($afterCancel['status']==='pending_payment'&&$afterCancel['payment_status']==='unpaid'&&empty($afterCancel['payment_method']),'Cancelling the ticket leaves an unpaid reusable order, without activation');
$retry=create(13,'existing_order',0,(int)$cancelledOrder['order_id']);
ck(!empty($retry['ok'])&&countRows('orders','customer_id=13')===$orderCount+1,'Retry reuses the existing order instead of duplicating it');
ck(countRows('crypto_deposit_requests','id='.(int)$cancelledOrder['request_id'])===1,'Cancelling and retrying retains the previous ticket for review');
$invalidShape=endpoint(['post'=>['action'=>'crypto_quick_create_submit','customer_id'=>[13],'purpose'=>'topup','_csrf'=>'fixture']]);
ck($invalidShape['status']===422,'Malformed array payload is rejected without mutation or PHP warnings');
ck(admin_format_money_value_with_symbol(18,'EUR','EUR')==='18.00 €','Payments uses currency symbols for legacy currency rows');
ck(admin_format_money_value_with_symbol(null,'EUR','EUR')==='— €','Empty amount preserves its placeholder with the currency symbol');
// Continue the actual created order through payment and fulfilment.
$linked=$db->select_user("SELECT * FROM crypto_deposit_requests WHERE customer_id=3 LIMIT 1");
$orderId=(int)$linked['order_id'];$requestId=(int)$linked['id'];
ck($db->select_user("SELECT payment_method FROM orders WHERE id={$orderId}")['payment_method']==='crypto','Order records its crypto payment method');
$settings=array_merge(admin_app_settings($db),['active_sale'=>1,'active_trials'=>1]);$user=['id'=>3,'logged'=>true,'customer_type'=>'client'];
$reseller=['currency_id'=>2,'currency_short'=>'EUR','currency_symbol'=>'€'];$t=[];$time_s=time();$time=date('Y-m-d H:i:s');
require_once dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__).'/bootstrap/tenant.php';
require_once dirname(__DIR__).'/bootstrap/localization.php';
$smarty=new \Smarty\Smarty;$_GET=['payment'=>$orderId];$_POST=[];
require dirname(__DIR__).'/pages/profil/orders_payment.php';
$customerRequest=$smarty->getTemplateVars('payment_crypto_request');
ck((int)($customerRequest['id']??0)===$requestId&&$smarty->getTemplateVars('payment_active_request_method')==='crypto','Customer checkout reuses the administrator-created order ticket');
$first=admin_payment_apply_quick_action($db,'crypto',$requestId,'accept',1,'fixture',['received_crypto_amount'=>(string)$linked['requested_crypto_amount'],'received_fiat_amount'=>'18']);
ck(!empty($first['ok']),'Created order payment can be accepted: '.json_encode($first));
$paidOrder=admin_order_find($db,$orderId);
ck($paidOrder['payment_status']==='paid'&&$paidOrder['status']==='pending_payment'&&empty($paidOrder['expires_at']),'Accepting payment leaves provider fulfilment pending without starting expiry');
$balanceAfter=(float)$db->select_user('SELECT balance_amount FROM customers WHERE id=3')['balance_amount'];
$again=admin_payment_apply_quick_action($db,'crypto',$requestId,'accept',1,'fixture',['received_crypto_amount'=>(string)$linked['requested_crypto_amount'],'received_fiat_amount'=>'18']);
ck(!empty($again['ok'])&&(float)$db->select_user('SELECT balance_amount FROM customers WHERE id=3')['balance_amount']===$balanceAfter,'Reaccepting a created ticket does not credit twice');
$payoutRows=admin_crypto_quick_rows($db,'payout',10,false,$requestId);
ck(!empty($payoutRows[0]['order_needs_action']),'Approved order payment prompts fulfilment first');
ck(!str_contains(admin_crypto_quick_html($payoutRows,'payout',null,'fixture'),'value="archive"'),'Archive action waits until order fulfilment');
$activated=admin_save_order_info($db,$orderId,array_merge($paidOrder,['total_amount'=>'18','status'=>'active','expires_at'=>date('Y-m-d H:i:s',time()+720*3600)]),1,'fixture');
ck(!empty($activated['ok']),'Paid order can be fulfilled: '.json_encode($activated));
$payoutRows=admin_crypto_quick_rows($db,'payout',10,false,$requestId);
ck(empty($payoutRows[0]['order_needs_action'])&&str_contains(admin_crypto_quick_html($payoutRows,'payout',null,'fixture'),'value="archive"'),'After fulfilment the payment offers withdrawal and archive');
$beforeArchive=(float)$db->select_user('SELECT balance_amount FROM customers WHERE id=3')['balance_amount'];
ck(!empty(admin_payment_apply_quick_action($db,'crypto',$requestId,'archive',1,'fixture')['ok'])&&(float)$db->select_user('SELECT balance_amount FROM customers WHERE id=3')['balance_amount']===$beforeArchive,'Archiving does not credit or debit the payment again');
$partial=$db->select_user('SELECT * FROM crypto_deposit_requests WHERE customer_id=4 LIMIT 1');
$partialResult=admin_payment_apply_quick_action($db,'crypto',(int)$partial['id'],'accept',1,'fixture',['received_crypto_amount'=>'.0002','received_fiat_amount'=>'10']);
ck(!empty($partialResult['ok'])&&!empty($partialResult['underpaid_order'])&&$db->select_user('SELECT payment_status FROM orders WHERE id=4')['payment_status']==='unpaid','Accepted partial order payment does not mark the full order as paid');
$partialHtml=admin_crypto_quick_html(admin_crypto_quick_rows($db,'payout',10,false,(int)$partial['id']),'payout',null,'fixture');
ck(str_contains($partialHtml,admin_crypto_quick_labels()['order_unpaid'])&&!str_contains($partialHtml,admin_crypto_quick_labels()['order_done']),'Partial payment guidance asks for the remaining payment, never says the order is completed');
// Replacement must not disguise unpaid tickets as withdrawn or purge recent history.
$old=create(18,'topup',0,0,12);$oldId=(int)$old['request_id'];
sql("UPDATE crypto_deposit_requests SET requested_at=DATE_SUB(NOW(),INTERVAL 2 HOUR),expires_at=DATE_SUB(NOW(),INTERVAL 1 HOUR) WHERE id={$oldId}");
ck(!empty(create(18,'topup',0,0,14)['ok']),'Expired unpaid ticket can be replaced');
ck($db->select_user("SELECT status FROM crypto_deposit_requests WHERE id={$oldId}")['status']==='expired','Unpaid replaced ticket remains Expired, never Archived');
$old=create(19,'topup',0,0,12);$oldId=(int)$old['request_id'];
sql("UPDATE crypto_deposit_requests SET status='cancelled',cancelled_at=NOW() WHERE id={$oldId}");
ck(!empty(create(19,'topup',0,0,14)['ok'])&&countRows('crypto_deposit_requests',"id={$oldId}")===1,'New ticket retains cancelled predecessor for the 72-hour review window');
sql("UPDATE crypto_deposit_requests SET cancelled_at=DATE_SUB(NOW(),INTERVAL 73 HOUR) WHERE id={$oldId}");
ck(app_delete_cancelled_crypto_requests_for_asset($db,19,$btc)===1,'Only cancelled history older than 72 hours is cleaned');
$old=create(20,'topup',0,0,12);$oldId=(int)$old['request_id'];
sql("UPDATE crypto_deposit_requests SET status='cancelled',cancelled_at=DATE_SUB(NOW(),INTERVAL 73 HOUR),received_crypto_amount=.0001 WHERE id={$oldId}");
ck(app_delete_cancelled_crypto_requests_for_asset($db,20,$btc)===0&&countRows('crypto_deposit_requests',"id={$oldId}")===1,'Replacing tickets never silently deletes recorded funds');
$options=endpoint(['get'=>['action'=>'crypto_quick_create_options','customer_id'=>17]]);
ck(empty($options['data']['ok']),'Inactive customers have no usable wizard options');
sql('UPDATE app_settings SET crypto_payments_enabled=0');
$options=endpoint(['get'=>['action'=>'crypto_quick_create_options','customer_id'=>13]]);
ck(empty($options['data']['ok']),'Disabled crypto returns clean JSON without undefined fields');
sql('UPDATE app_settings SET crypto_payments_enabled=1');
// Shared mode must reuse assigned addresses without requiring the old reusable flag.
$walletStates=$db->select_full_user('SELECT id,status,disabled_at FROM crypto_wallet_addresses');
sql("UPDATE crypto_wallet_addresses SET status='disabled',disabled_at=NOW()");
sql("INSERT INTO customers(id,email,public_handle,password_hash,status) VALUES(31001,'shared-one@example.invalid','shared-one','unused','active'),(31002,'shared-two@example.invalid','shared-two','unused','active')");
sql("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,status,network_code,is_reusable) VALUES(900,{$btc},'fixture-shared-wallet','assigned','bitcoin',0)");
sql("INSERT INTO crypto_wallet_assignments(wallet_address_id,customer_id,status) VALUES(900,2,'active')");
sql("INSERT INTO crypto_deposit_requests(customer_id,crypto_asset_id,wallet_address_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,status,requested_at,expires_at) VALUES(2,{$btc},900,20,2,50000,.0004,'archived',DATE_SUB(NOW(),INTERVAL 3 DAY),DATE_SUB(NOW(),INTERVAL 3 DAY))");
sql("UPDATE app_settings SET crypto_wallet_shared_assignments_enabled=0");
$off=admin_quick_crypto_payment_data($db,31001);ck($off['items']===[]&&!$off['wallet_shared'],'OFF does not reuse another customer address/history');
sql("UPDATE app_settings SET crypto_wallet_shared_assignments_enabled=1");
$on=endpoint(['get'=>['action'=>'crypto_quick_create_options','customer_id'=>31001]]);ck($on['status']===200&&$on['data']['wallet_shared']&&count($on['data']['items'])===1,'ON offers assigned shared wallet despite empty free pool and reusable=0');
$shared=create(31001,'topup',0,0,23);ck(!empty($shared['ok']),'Shared wallet with old history creates a ticket: '.json_encode($shared));
$sharedRow=$db->select_user('SELECT * FROM crypto_deposit_requests WHERE id='.(int)$shared['request_id']);ck((int)$sharedRow['wallet_address_id']===900,'Existing wallet reused; no duplicated address');
ck(countRows('crypto_wallet_assignments',"wallet_address_id=900 AND customer_id=31001 AND status='active'")===1&&countRows('crypto_wallet_assignments',"wallet_address_id=900 AND customer_id=2 AND status='active'")===1,'Sharing retains old assignment and creates only one new assignment');
$busy=admin_quick_crypto_payment_data($db,31002);ck($busy['wallet_shared']&&$busy['items']===[],'Occupied shared address is not offered to a second concurrent ticket');
ck(empty(create(31002,'topup',0,0,23)['ok']),'Concurrent competing ticket stays blocked');
foreach($walletStates as $wallet){$db->update_using_id(['status','disabled_at'],[$wallet['status'],$wallet['disabled_at']],'crypto_wallet_addresses',(int)$wallet['id']);}
sql("UPDATE crypto_wallet_addresses SET status='disabled',disabled_at=NOW() WHERE id=900");sql("UPDATE app_settings SET crypto_wallet_shared_assignments_enabled=0");
echo "PASS {$checks} quick payment creation, linking, wallet reuse, concurrency and endpoint checks\n";

if(getenv('APP_UI_EXPORT')==='1'){
 sql("UPDATE crypto_deposit_requests SET status='cancelled'");
 sql("UPDATE orders SET status='pending_payment',payment_status='unpaid',paid_at=NULL WHERE id=14");
 $p=proc_open([PHP_BINARY,__FILE__,'--preview'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);foreach($pipes as $f){echo stream_get_contents($f);fclose($f);}if(proc_close($p))throw new RuntimeException('Preview failed');
}
