<?php
/** Requires an isolated canonical _test_ database. No live chain queries or messages. */
declare(strict_types=1);
if (strpos((string)getenv('DB_NAME'), '_test_') === false) throw new RuntimeException('Separate _test_ database required.');
require_once dirname(__DIR__) . '/admin/bootstrap.php';
$db=Mysql_ks::get_instance();
if (($argv[1]??'')==='--ticket-status') {
    $request=json_decode(base64_decode($argv[2]),true);
    app_start_session();$_SESSION=['id'=>$request['customer']??0];
    if(!empty($request['throttle']))$_SESSION['crypto_ticket_status_checked']=microtime(true);
    $_SERVER['REQUEST_METHOD']='GET';$_GET=['ticket_status'=>$request['ticket']??201];
    ob_start();register_shutdown_function(static function(){echo json_encode(['status'=>http_response_code()?:200,'data'=>json_decode(ob_get_clean(),true)]);});
    require dirname(__DIR__).'/check_payment.php';exit;
}
if (($argv[1]??'')==='--balance') {
    echo json_encode(app_apply_customer_balance_runtime_event($db,101,1,'credit','audit-crypto',$argv[2],'Concurrent test'));exit;
}
if (($argv[1]??'')==='--claim') {
    $payment=admin_payment_find($db,'crypto',(int)$argv[2]);
    echo json_encode(admin_crypto_auto_accept_store_transaction($db,$payment,['hash'=>str_repeat('e',64),'amount_crypto'=>.002,'amount_fiat'=>100,'timestamp'=>time()-60,'confirmations'=>6]));exit;
}
if (($argv[1]??'')==='--accept') { echo json_encode(admin_payment_apply_quick_action($db,'crypto',200,'accept',1,'audit',['received_crypto_amount'=>'.002','received_fiat_amount'=>'100']));exit; }
if (($argv[1]??'')==='--scanner') { echo json_encode(admin_crypto_auto_accept_recent_payments($db,['crypto_auto_accept_enabled'=>1],['limit'=>2]));exit; }
if (($argv[1]??'')==='--create') {
    $asset=(int)$db->select_user("SELECT id FROM crypto_assets WHERE code='BTC'")['id'];
    echo json_encode(app_create_crypto_deposit_request($db,['customer_id'=>102,'crypto_asset_id'=>$asset,'wallet_address_id'=>201,'wallet_assignment_id'=>201,'requested_fiat_amount'=>100,'fiat_currency_id'=>2,'exchange_rate'=>50000,'requested_crypto_amount'=>.002,'status'=>'pending','expires_at'=>date('Y-m-d H:i:s',time()+3600)]));exit;
}
if (($argv[1]??'')==='--customer-page') {
    require_once dirname(__DIR__).'/vendor/autoload.php';
    require_once dirname(__DIR__).'/bootstrap/localization.php';
    $settings=admin_app_settings($db);$user=['id'=>102,'logged'=>true];
    $reseller=['currency_id'=>2,'currency_short'=>'EUR','currency_symbol'=>'€'];
    $t=(require dirname(__DIR__).'/locales/en.php')['messages'];
    $site='payments_crypto';$time_s=time();$time=date('Y-m-d H:i:s');
    $_GET=($argv[2]??'')!==''?['ticket_before'=>(int)$argv[2]]:[];$_POST=[];
    $smarty=new \Smarty\Smarty();$smarty->setTemplateDir(dirname(__DIR__).'/templates');
    $smarty->setCompileDir(app_crypto_runtime_dir());$smarty->assign('t',$t);$smarty->assign('reseller',$reseller);$smarty->assign('csrf_token','audit');
    app_crypto_network_budget(0,0);$GLOBALS['app_crypto_api_observations']=[];
    $before=Mysql_ks::$licznik;$started=microtime(true);ob_start();require dirname(__DIR__).'/pages/profil/payments_crypto.php';$html=ob_get_clean();
    echo json_encode(['html'=>$html,'queries'=>Mysql_ks::$licznik-$before,'elapsed_ms'=>round((microtime(true)-$started)*1000,2),'network'=>$GLOBALS['app_crypto_api_observations'],'next'=>$smarty->getTemplateVars('crypto_ticket_next'),'rows'=>count((array)$smarty->getTemplateVars('crypto_payments'))]);exit;
}
app_crypto_network_budget(0,0);
$checks=0;
function ck(bool $value,string $label):void {global $checks;if(!$value)throw new RuntimeException($label);$checks++;}
function sql(string $query):void {global $db;if(!$db->query($query))throw new RuntimeException($db->error);}
function cached_http(string $url,string $body,?array $payload=null,bool $text=false):void {
    if ((parse_url($url,PHP_URL_HOST)??'')==='api.coingecko.com') {
        $parts=parse_url($url);parse_str($parts['query']??'', $query);ksort($query);$url='https://api.coingecko.com'.($parts['path']??'/').'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);
    }
    $key=hash('sha256',$url.'|'.json_encode($payload).'|'.(int)$text);
    app_crypto_runtime_write(app_crypto_runtime_dir().'/request-'.$key.'.json',['expires'=>microtime(true)+3600,'body'=>$body]);
}
function no_remote_btc(string $address):void {
    cached_http('https://mempool.space/api/address/'.rawurlencode($address).'/txs','[]');
    cached_http('https://blockchain.info/rawaddr/'.rawurlencode($address).'?limit=25&offset=0','{"txs":[]}');
}
function child(string $mode,string $arg=''):array {
    $proc=proc_open([PHP_BINARY,__FILE__,$mode,$arg],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    return [$proc,$pipes];
}
function finish(array $child) {
    [$proc,$pipes]=$child;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);$code=proc_close($proc);
    if($code!==0 || json_decode($out,true)===null)throw new RuntimeException('Child failed: '.$out.$err);
    return json_decode($out,true);
}
require_once dirname(__DIR__).'/vendor/autoload.php';
$templateCheck=new \Smarty\Smarty();$templateCheck->setTemplateDir(dirname(__DIR__).'/templates');$templateCheck->setCompileDir(app_crypto_runtime_dir());
foreach(['profil/orders_payment.tpl','profil/payments_crypto.tpl','profil/crypto_detected.tpl','top_page.tpl'] as $templateName){$templateCheck->createTemplate($templateName)->compileTemplateSource();}
admin_app_settings($db);app_ensure_customer_runtime_columns($db);app_ensure_customer_balance_runtime_table($db);app_ensure_payment_request_runtime_columns($db);
sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(1,1,'audit-owner','crypto-owner@example.invalid','unused') ON DUPLICATE KEY UPDATE role_id=1");
sql("INSERT INTO customers(id,email,password_hash,status) VALUES(101,'crypto-one@example.invalid','unused','active'),(102,'crypto-two@example.invalid','unused','active') ON DUPLICATE KEY UPDATE balance_amount=0");
sql("UPDATE app_settings SET default_currency_id=2,crypto_wallet_shared_assignments_enabled=0,demo_messenger_showcase_enabled=0");
sql("UPDATE crypto_deposit_requests SET status='cancelled' WHERE id<200");
$btc=(int)$db->select_user("SELECT id FROM crypto_assets WHERE code='BTC'")['id'];
for($i=200;$i<208;$i++) {
    $customer=$i===201?102:101;$address='crypto-audit-wallet-'.$i;no_remote_btc($address);
    sql("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,network_code,status) VALUES({$i},{$btc},'{$address}','bitcoin','assigned')");
    sql("INSERT INTO crypto_wallet_assignments(id,wallet_address_id,customer_id,status) VALUES({$i},{$i},{$customer},'active')");
    sql("INSERT INTO crypto_deposit_requests(id,customer_id,crypto_asset_id,wallet_address_id,wallet_assignment_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,status,requested_at,expires_at) VALUES({$i},{$customer},{$btc},{$i},{$i},100,2,50000,.002,'pending',DATE_SUB(NOW(),INTERVAL 20 MINUTE),DATE_ADD(NOW(),INTERVAL 60 MINUTE))");
}
$p=admin_payment_find($db,'crypto',200);$row=['hash'=>str_repeat('a',64),'direction'=>'incoming','amount_crypto'=>.002,'confirmations'=>1,'timestamp'=>time()-60];
ck(admin_crypto_verified_incoming_row($p,$row)===null,'One Bitcoin confirmation is insufficient');$row['confirmations']=2;
ck(admin_crypto_verified_incoming_row($p,$row)!==null,'Two Bitcoin confirmations accepted');
ck(admin_crypto_verified_incoming_row($p,array_merge($row,['direction'=>'outgoing']))===null,'Outgoing transfers rejected');
ck(admin_crypto_verified_incoming_row($p,array_merge($row,['amount_crypto'=>INF]))===null,'Infinite transfers rejected');
ck(admin_crypto_verified_incoming_row($p,array_merge($row,['hash'=>'invalid']))===null,'Malformed transaction hash rejected');
foreach([['DOGE','dogecoin',6],['TRX','tron',20]] as [$asset,$network,$minimum]) {
 $payment=array_merge($p,['asset_code'=>$asset,'network_code'=>$network]);
 ck(admin_crypto_verified_incoming_row($payment,array_merge($row,['confirmations'=>$minimum-1]))===null,$asset.' insufficient confirmations');
 ck(admin_crypto_verified_incoming_row($payment,array_merge($row,['confirmations'=>$minimum]))!==null,$asset.' finality threshold');
}
$sol=array_merge($p,['asset_code'=>'SOL','network_code'=>'solana']);$solrow=array_merge($row,['hash'=>str_repeat('2',88),'confirmation_status'=>'finalized','transfer_verified'=>true]);
ck(admin_crypto_verified_incoming_row($sol,$solrow)!==null,'Finalized native SOL transfer accepted');
ck(admin_crypto_verified_incoming_row($sol,array_merge($solrow,['confirmation_status'=>'confirmed']))===null,'Unfinalized SOL rejected');
ck(admin_crypto_verified_incoming_row($sol,array_merge($solrow,['transfer_verified'=>false]))===null,'Rent/balance-only SOL changes rejected');
$eth=array_merge($p,['asset_code'=>'ETH','network_code'=>'ethereum','wallet_address'=>'0x'.str_repeat('b',40)]);$ethrow=array_merge($row,['hash'=>'0x'.str_repeat('a',64)]);
$receipt=['hash'=>$ethrow['hash'],'success'=>true,'confirmations'=>12,'timestamp'=>time()-60,'to'=>$eth['wallet_address'],'from'=>'0x'.str_repeat('c',40),'rawValue'=>'10000000000000000'];
ck(abs(admin_crypto_verified_incoming_row($eth,$ethrow,fn()=>$receipt)['amount_crypto']-.01)<1e-12,'ETH amount is taken from successful receipt');
foreach([['success'=>false],['confirmations'=>11],['hash'=>'0x'.str_repeat('d',64)],['to'=>'0x'.str_repeat('d',40)],['rawValue'=>'0']] as $change) ck(admin_crypto_verified_incoming_row($eth,$ethrow,fn()=>array_merge($receipt,$change))===null,'Invalid ETH receipt rejected');
$usdt=array_merge($eth,['asset_code'=>'USDT']);$contract=admin_crypto_accept_preview_known_token_contract('USDT','ethereum');
$tokenReceipt=array_merge($receipt,['operations'=>[['tokenInfo'=>['address'=>$contract,'decimals'=>18],'from'=>$receipt['from'],'to'=>$eth['wallet_address'],'value'=>'1000000']]]);
ck(admin_crypto_verified_incoming_row($usdt,$ethrow,fn()=>$tokenReceipt)['amount_crypto']===1.0,'Token contract fixes decimals despite untrusted metadata');
$tokenReceipt['operations'][0]['tokenInfo']['address']='0x'.str_repeat('f',40);
ck(admin_crypto_verified_incoming_row($usdt,$ethrow,fn()=>$tokenReceipt)===null,'Counterfeit token rejected');
$hash=str_repeat('a',64);
sql("INSERT INTO crypto_deposit_transactions(wallet_address_id,crypto_asset_id,transaction_hash,amount_crypto,amount_fiat,confirmations,received_at) VALUES(200,{$btc},'{$hash}',.002,999999,1,UTC_TIMESTAMP())");
$settings=['crypto_auto_accept_window_minutes'=>60,'crypto_auto_accept_min_amount_percent'=>80];
ck(admin_crypto_auto_accept_candidate($db,$p,$settings)===null,'Scanner rejects unconfirmed database candidate');
sql("UPDATE crypto_deposit_transactions SET confirmations=2 WHERE transaction_hash='{$hash}'");
$candidate=admin_crypto_auto_accept_candidate($db,$p,$settings);
ck(is_array($candidate) && abs($candidate['amount_fiat']-100)<.001,'Candidate fiat uses its locked rate, not cached/imported fiat');
sql("UPDATE crypto_deposit_transactions SET received_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 60 SECOND) WHERE transaction_hash='{$hash}'");
ck(admin_crypto_auto_accept_candidate($db,$p,$settings)===null,'Future block timestamps rejected');
sql("UPDATE crypto_deposit_transactions SET received_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),amount_crypto=.00159 WHERE transaction_hash='{$hash}'");
ck(admin_crypto_auto_accept_candidate($db,$p,$settings)===null,'Below configured payment threshold rejected');
sql("UPDATE crypto_deposit_transactions SET amount_crypto=.0016 WHERE transaction_hash='{$hash}'");
ck(admin_crypto_auto_accept_candidate($db,$p,$settings)!==null,'Exact 80 percent threshold accepted');
sql("UPDATE crypto_deposit_transactions SET amount_crypto=.002 WHERE transaction_hash='{$hash}'");
$other=admin_payment_find($db,'crypto',201);
ck(admin_crypto_auto_accept_store_transaction($db,$p,$candidate),'Transaction can be claimed by matching wallet/request');
ck(!admin_crypto_auto_accept_store_transaction($db,$other,$candidate),'Claim cannot be reassigned to another request/customer');
ck((int)$db->select_user("SELECT deposit_request_id FROM crypto_deposit_transactions WHERE transaction_hash='{$hash}'")['deposit_request_id']===200,'Original transaction ownership preserved');
$children=[child('--claim','200'),child('--claim','201')];$claimResults=array_map('finish',$children);
ck(count(array_filter($claimResults))===1,'Concurrent transaction claims have one owner');
$children=[];for($i=0;$i<8;$i++)$children[]=child('--balance','credit-'.$i);$results=array_map('finish',$children);
ck(count(array_filter($results,fn($r)=>!empty($r['ok'])))===8,'Concurrent independent balance events succeed');
ck((float)$db->select_user('SELECT balance_amount FROM customers WHERE id=101')['balance_amount']===8.0,'Concurrent credits do not lose updates');
$children=[];for($i=0;$i<8;$i++)$children[]=child('--balance','credit-'.$i);$results=array_map('finish',$children);
ck(count(array_filter($results,fn($r)=>!empty($r['already_applied'])))===8,'Replayed balance events are idempotent');
ck((float)$db->select_user('SELECT balance_amount FROM customers WHERE id=101')['balance_amount']===8.0,'Replays do not credit again');
sql("ALTER TABLE customer_balance_runtime_events ADD CONSTRAINT crypto_audit_failure CHECK (source_key <> 'fail-credit')");
$result=app_apply_customer_balance_runtime_event($db,101,1,'credit','audit-crypto','fail-credit','Injected failure');
ck(empty($result['ok']) && (float)$db->select_user('SELECT balance_amount FROM customers WHERE id=101')['balance_amount']===8.0,'Failed event insertion rolls back balance');
sql('ALTER TABLE customer_balance_runtime_events DROP CHECK crypto_audit_failure');
$children=[child('--accept'),child('--accept')];$approvalResults=array_map('finish',$children);
$result=admin_payment_apply_quick_action($db,'crypto',200,'accept',1,'audit',['received_crypto_amount'=>'.002','received_fiat_amount'=>'100']);
ck(!empty($result['ok']),'Approval remains retryable/idempotent after concurrent requests');
ck((float)$db->select_user('SELECT balance_amount FROM customers WHERE id=101')['balance_amount']===108.0,'Concurrent approval credits one payment once');
ck((int)$db->select_user("SELECT COUNT(*) n FROM customer_balance_runtime_events WHERE source_type='payment_approval' AND source_key='crypto:200'")['n']===1,'Exactly one journal event for the payment');
// Detection and acceptance are separate: OFF records a verified transfer without crediting.
sql("UPDATE crypto_deposit_requests SET blockchain_checked_at=NOW() WHERE id BETWEEN 200 AND 207");
sql("UPDATE crypto_deposit_requests SET status='pending',blockchain_checked_at=NULL WHERE id=201");
$manualHash=str_repeat('f',64);
sql("INSERT INTO crypto_deposit_transactions(wallet_address_id,crypto_asset_id,transaction_hash,amount_crypto,amount_fiat,confirmations,received_at) VALUES(201,{$btc},'{$manualHash}',.002,100,2,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE))");
$manualBefore=(float)$db->select_user('SELECT balance_amount FROM customers WHERE id=102')['balance_amount'];
$manual=admin_crypto_auto_accept_recent_payments($db,array_merge($settings,['crypto_auto_accept_enabled'=>0]),['limit'=>1]);
$review=$db->select_user('SELECT status,received_crypto_amount,expires_at FROM crypto_deposit_requests WHERE id=201');
ck($manual['detected']===1&&$manual['accepted']===0&&$review['status']==='awaiting_review','OFF detects payment and queues administrator verification');
ck((float)$review['received_crypto_amount']===.002&&$review['expires_at']===null,'Detected ticket has actual amount and no further send deadline');
ck((float)$db->select_user('SELECT balance_amount FROM customers WHERE id=102')['balance_amount']===$manualBefore,'OFF does not credit customer balance');
$ticketStatus=finish(child('--ticket-status',base64_encode(json_encode(['customer'=>102]))));
ck($ticketStatus['status']===200&&$ticketStatus['data']['detected']===true,'Authenticated customer can poll detected status');
ck(finish(child('--ticket-status',base64_encode(json_encode(['customer'=>101]))))['status']===404,'Customer cannot poll another customer ticket');
ck(finish(child('--ticket-status',base64_encode(json_encode(['customer'=>0]))))['status']===401,'Ticket status rejects anonymous requests');
ck(finish(child('--ticket-status',base64_encode(json_encode(['customer'=>102,'ticket'=>'invalid']))))['status']===422,'Ticket status validates identifier');
ck(finish(child('--ticket-status',base64_encode(json_encode(['customer'=>102,'throttle'=>true]))))['status']===429,'Ticket status limits rapid requests');
require_once dirname(__DIR__).'/admin/crypto_quick.php';
$detectedRows=admin_crypto_quick_rows($db,'new');$detectedRows=array_values(array_filter($detectedRows,fn($r)=>(int)$r['id']===201));
$detectedHtml=admin_crypto_quick_html($detectedRows,'new',admin_crypto_quick_labels('pl'));
ck(str_contains($detectedHtml,'Wykryto wpłatę')&&!str_contains($detectedHtml,'data-crypto-quick-expires='),'Admin sees detected payment without a misleading send deadline');
$manualPage=finish(child('--customer-page'));
file_put_contents('/tmp/subme-detected-customer-ui.html',$manualPage['html']);
ck(str_contains($manualPage['html'],'Payment detected')&&str_contains($manualPage['html'],'awaiting verification')&&!str_contains($manualPage['html'],'data-copy-text'),'Customer sees receipt pending verification instead of payment instructions');
sql("UPDATE crypto_deposit_requests SET blockchain_checked_at=NULL WHERE id=201");
$manualAgain=admin_crypto_auto_accept_recent_payments($db,array_merge($settings,['crypto_auto_accept_enabled'=>0]),['limit'=>1]);
ck($manualAgain['checked']===0,'OFF does not repeatedly scan an already detected ticket');
$automatic=admin_crypto_auto_accept_recent_payments($db,array_merge($settings,['crypto_auto_accept_enabled'=>1]),['limit'=>1]);
ck($automatic['accepted']===1&&$db->select_user('SELECT status FROM crypto_deposit_requests WHERE id=201')['status']==='approved','ON accepts the previously detected verified transfer');
ck((float)$db->select_user('SELECT balance_amount FROM customers WHERE id=102')['balance_amount']===$manualBefore+100,'ON credits the actual locked-rate payment exactly once');
sql("UPDATE crypto_deposit_requests SET blockchain_checked_at=NULL WHERE id=201");
$automaticAgain=admin_crypto_auto_accept_recent_payments($db,array_merge($settings,['crypto_auto_accept_enabled'=>1]),['limit'=>1]);
ck($automaticAgain['accepted']===0&&(float)$db->select_user('SELECT balance_amount FROM customers WHERE id=102')['balance_amount']===$manualBefore+100,'Repeated scanner never credits an approved request twice');

// Clear only this suite's remaining pending requests to exercise fair queue rotation.
sql('UPDATE crypto_deposit_requests SET blockchain_checked_at=NULL WHERE id BETWEEN 201 AND 207');
$first=admin_crypto_auto_accept_recent_payments($db,array_merge($settings,['crypto_auto_accept_enabled'=>1]),['limit'=>2]);
$checkedFirst=array_column($db->select_full_user('SELECT id FROM crypto_deposit_requests WHERE id BETWEEN 201 AND 207 AND blockchain_checked_at IS NOT NULL'),'id');
$second=admin_crypto_auto_accept_recent_payments($db,array_merge($settings,['crypto_auto_accept_enabled'=>1]),['limit'=>2]);
$checkedSecond=array_column($db->select_full_user('SELECT id FROM crypto_deposit_requests WHERE id BETWEEN 201 AND 207 AND blockchain_checked_at IS NOT NULL'),'id');
ck($first['checked']===2 && $second['checked']===2 && count($checkedSecond)===count($checkedFirst)+2,'Scanner advances to other requests instead of starving queue');
$key='crypto-scan:'.substr(hash('sha256',(string)getenv('DB_NAME')),0,32);$db->select_user("SELECT GET_LOCK('{$key}',0)");
$busy=finish(child('--scanner'));ck(!empty($busy['busy']) && $busy['checked']===0,'Concurrent maintenance scanner skips without waiting');$db->select_user("SELECT RELEASE_LOCK('{$key}')");
$explain=$db->select_full_user("EXPLAIN SELECT id FROM crypto_deposit_requests WHERE status='pending' AND blockchain_checked_at IS NULL ORDER BY blockchain_checked_at,id LIMIT 6");
ck(strpos((string)($explain[0]['possible_keys']??''),'idx_crypto_scan_queue')!==false,'Scan queue has an applicable composite index');
app_crypto_network_budget(0,0);
// Customer submissions serialize against other customer/admin requests.
sql("UPDATE crypto_deposit_requests SET status='cancelled' WHERE customer_id=102");
$children=[];for($i=0;$i<8;$i++)$children[]=child('--create');$created=array_map('finish',$children);
ck(count(array_filter($created))===1,'Eight parallel customer submits create exactly one ticket');
ck((int)$db->select_user("SELECT COUNT(*) n FROM crypto_deposit_requests WHERE customer_id=102 AND status='pending'")['n']===1,'One open customer ticket remains');
ck(empty(app_apply_customer_balance_runtime_event($db,101,.001,'credit','audit-crypto','round-zero','Tiny value')['ok']),'Rounded-zero balance event is rejected');
$ticketPage=finish(child('--customer-page'));
ck(strpos($ticketPage['html'],'payment-request-card')!==false && $ticketPage['network']===[],'Active customer ticket renders without fetching prices or blockchain');
echo 'Active ticket: '.$ticketPage['queries'].' SQL queries / '.$ticketPage['elapsed_ms']." ms (isolated local fixture)\n";
// Keyset history is bounded and remains accessible across pages.
sql("UPDATE crypto_deposit_requests SET status='cancelled' WHERE customer_id=102");
for($i=0;$i<61;$i++)sql("INSERT INTO crypto_deposit_requests(customer_id,crypto_asset_id,wallet_address_id,wallet_assignment_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,status,requested_at) VALUES(102,{$btc},201,201,100,1,50000,.002,'approved',NOW())");
$historyPage=finish(child('--customer-page'));
ck($historyPage['rows']===50 && $historyPage['next']>0,'Customer history loads at most fifty requests with older-page cursor');
ck(strpos($historyPage['html'],'100.00 $')!==false,'Customer history displays request currency instead of hard-coded euro');
$olderPage=finish(child('--customer-page',(string)$historyPage['next']));
ck($olderPage['rows']>0 && $olderPage['rows']<50 && $olderPage['next']===0,'Older history page returns remaining requests without duplicates');
// A USD tool must not replace the shop EUR quote in the database.
sql("UPDATE crypto_assets SET is_active=IF(code='BTC',1,0),current_rate_fiat=50000,rate_currency_code='EUR',rate_updated_at=UTC_TIMESTAMP()");
foreach(glob(app_crypto_runtime_dir().'/rates-*.json')?:[] as $snapshot)unlink($snapshot);
$url='https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_last_updated_at=true&precision=full';
cached_http($url,json_encode(['bitcoin'=>['usd'=>55000,'last_updated_at'=>time()]]));
$rates=app_refresh_crypto_rates($db,'USD');
ck((float)$rates[0]['current_rate_fiat']===55000.0 && $rates[0]['rate_currency_code']==='USD','Customer receives the correct currency quote');
$stored=$db->select_user("SELECT current_rate_fiat,rate_currency_code FROM crypto_assets WHERE id={$btc}");
ck((float)$stored['current_rate_fiat']===50000.0 && $stored['rate_currency_code']==='EUR','Foreign-currency quote leaves shop database rate unchanged');
app_crypto_wallet_read_maintenance($db,102,admin_app_settings($db));
$beforeUpdates=(int)$db->select_user("SHOW SESSION STATUS LIKE 'Com_update'")['Value'];
$beforeInserts=(int)$db->select_user("SHOW SESSION STATUS LIKE 'Com_insert'")['Value'];
app_load_customer_crypto_assets($db,102,'EUR',admin_app_settings($db));
app_load_customer_crypto_assets($db,102,'EUR',admin_app_settings($db));
ck($beforeUpdates===(int)$db->select_user("SHOW SESSION STATUS LIKE 'Com_update'")['Value'] && $beforeInserts===(int)$db->select_user("SHOW SESSION STATUS LIKE 'Com_insert'")['Value'],'Warm customer quote/catalog reads perform no INSERT or UPDATE');
// Decode actual RPC-shaped responses: only successful, parsed native transfers count.
$signatures=[];$transactions=[];
for($i=1;$i<=4;$i++) {
    $signatures[]=['signature'=>str_repeat((string)($i+1),88),'blockTime'=>time()-30,'err'=>null,'confirmationStatus'=>'finalized'];
    $instruction=['program'=>'system','parsed'=>['type'=>'transfer','info'=>['source'=>'sender','destination'=>'sol-audit-wallet','lamports'=>1000000000]]];
    $meta=['err'=>null,'preBalances'=>[0,2000000000],'postBalances'=>[1000000000,1000000000]];
    if($i===2)$meta['err']=['InstructionError'=>[0,'failure']];
    if($i===3)unset($meta['err']);
    $transactions[]=['jsonrpc'=>'2.0','id'=>$i,'result'=>['transaction'=>['message'=>['accountKeys'=>['sol-audit-wallet','sender'],'instructions'=>$i===4?[]:[$instruction]]],'meta'=>$meta]];
}
$signatureQuery=['jsonrpc'=>'2.0','id'=>1,'method'=>'getSignaturesForAddress','params'=>['sol-audit-wallet',['commitment'=>'finalized','limit'=>8]]];
cached_http('https://api.mainnet-beta.solana.com',json_encode(['result'=>$signatures]),$signatureQuery);
$batch=[];foreach($signatures as $index=>$sig)$batch[]=['jsonrpc'=>'2.0','id'=>$index+1,'method'=>'getTransaction','params'=>[$sig['signature'],['encoding'=>'jsonParsed','commitment'=>'finalized','maxSupportedTransactionVersion'=>0]]];
cached_http('https://api.mainnet-beta.solana.com',json_encode($transactions),$batch);
$parsed=admin_crypto_accept_preview_sol_rows(array_merge($sol,['wallet_address'=>'sol-audit-wallet','id'=>987654]));
ck(count($parsed)===2,'RPC decoder omits failed and incomplete SOL receipts');
$verified=array_values(array_filter($parsed,fn($r)=>!empty($r['transfer_verified'])));
ck(count($verified)===1 && $verified[0]['amount_crypto']===1.0,'Rent-only balance delta is not a verified SOL transfer');
$ethHistory=[['hash'=>'0x'.str_repeat('a',64),'success'=>false,'timestamp'=>time()-30,'from'=>'sender','to'=>$eth['wallet_address'],'value'=>1],['hash'=>'0x'.str_repeat('b',64),'success'=>true,'timestamp'=>time()-30,'from'=>'sender','to'=>$eth['wallet_address'],'value'=>.01]];
cached_http('https://api.ethplorer.io/getAddressTransactions/'.rawurlencode($eth['wallet_address']).'?apiKey=freekey&limit=100&showZeroValues=true',json_encode($ethHistory));
$parsedEth=admin_crypto_accept_preview_eth_native_rows(array_merge($eth,['id'=>987655]));
ck(count($parsedEth)===1 && $parsedEth[0]['hash']==='0x'.str_repeat('b',64),'ETH history excludes failed transfers');
$GLOBALS['app_crypto_api_observations']=[];
admin_crypto_preview_network_status_rows([],false);
ck($GLOBALS['app_crypto_api_observations']===[],'Settings HTML renders network statuses without external calls');
echo "PASS: {$checks} crypto payment/finality/concurrency assertions\n";
