<?php
/** Disposable _test_ database and cached fake chain receipts only. */
declare(strict_types=1);
if(!str_contains((string)getenv('DB_NAME'),'_test_'))throw new RuntimeException('Isolated database required');
require_once dirname(__DIR__).'/admin/bootstrap.php';
$db=Mysql_ks::get_instance();
if(($argv[1]??'')==='--render'){
 require_once dirname(__DIR__).'/vendor/autoload.php';require_once dirname(__DIR__).'/bootstrap/localization.php';
 $settings=admin_app_settings($db);$user=['id'=>(int)$argv[2],'logged'=>true];
 $reseller=['currency_id'=>2,'currency_short'=>'EUR','currency_symbol'=>'€'];
 $t=(require dirname(__DIR__).'/locales/pl.php')['messages'];$site='payments_crypto';$time_s=time();$time=date('Y-m-d H:i:s');$_GET=[];$_POST=[];
 $smarty=new \Smarty\Smarty();$smarty->setTemplateDir(dirname(__DIR__).'/templates');$smarty->setCompileDir(app_crypto_runtime_dir());
 $smarty->assign('t',$t);$smarty->assign('reseller',$reseller);$smarty->assign('csrf_token','scenario');
 app_crypto_network_budget(0,0);ob_start();require dirname(__DIR__).'/pages/profil/payments_crypto.php';$html=ob_get_clean();echo json_encode(['html'=>$html]);exit;
}
function scenario_sql(string $q):void{global $db;if(!$db->query($q))throw new RuntimeException($db->error);}
function scenario_check(bool $condition,string $label):void{global $checks;if(!$condition)throw new RuntimeException($label);$checks++;}
function scenario_cache(string $url,string $body):void{$key=hash('sha256',$url.'|null|0');app_crypto_runtime_write(app_crypto_runtime_dir().'/request-'.$key.'.json',['expires'=>microtime(true)+3600,'body'=>$body]);}
admin_app_settings($db);app_ensure_customer_runtime_columns($db);app_ensure_customer_balance_runtime_table($db);app_ensure_payment_request_runtime_columns($db);
scenario_sql('UPDATE app_settings SET crypto_payments_enabled=1,crypto_wallet_shared_assignments_enabled=0,default_currency_id=2');
$btc=(int)$db->select_user("SELECT id FROM crypto_assets WHERE code='BTC'")['id'];
$checks=0;$results=[];$seq=9000;
foreach([false,true] as $auto){foreach([100,90,50] as $amount){
 $id=++$seq;$address='scenario-wallet-'.$id;$crypto=$amount/50000;$hash=hash('sha256','scenario-'.$id);
 scenario_sql("UPDATE crypto_deposit_requests SET blockchain_checked_at=NOW()");
 scenario_sql("INSERT INTO customers(id,email,password_hash,status,balance_amount) VALUES({$id},'scenario{$id}@example.invalid','unused','active',0)");
 scenario_sql("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,network_code,status) VALUES({$id},{$btc},'{$address}','bitcoin','assigned')");
 scenario_sql("INSERT INTO crypto_wallet_assignments(id,wallet_address_id,customer_id,status) VALUES({$id},{$id},{$id},'active')");
 scenario_sql("INSERT INTO crypto_deposit_requests(id,customer_id,crypto_asset_id,wallet_address_id,wallet_assignment_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,status,requested_at,expires_at) VALUES({$id},{$id},{$btc},{$id},{$id},100,2,50000,.002,'pending',DATE_SUB(NOW(),INTERVAL 20 MINUTE),DATE_ADD(NOW(),INTERVAL 40 MINUTE))");
 scenario_sql("INSERT INTO crypto_deposit_transactions(wallet_address_id,crypto_asset_id,transaction_hash,amount_crypto,amount_fiat,confirmations,received_at) VALUES({$id},{$btc},'{$hash}',{$crypto},0,2,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE))");
 scenario_cache('https://mempool.space/api/address/'.$address.'/txs','[]');scenario_cache('https://blockchain.info/rawaddr/'.$address.'?limit=25&offset=0','{"txs":[]}');
 $settings=['crypto_auto_accept_enabled'=>(int)$auto,'crypto_payments_enabled'=>1,'crypto_auto_accept_min_amount_percent'=>80,'crypto_auto_accept_window_minutes'=>60];
 $scan=admin_crypto_auto_accept_recent_payments($db,$settings,['limit'=>1]);
 $request=$db->select_user("SELECT status,received_fiat_amount,expires_at FROM crypto_deposit_requests WHERE id={$id}");
 $balance=(float)$db->select_user("SELECT balance_amount FROM customers WHERE id={$id}")['balance_amount'];
 $approved=$auto&&$amount>=80;
 scenario_check($request['status']===($approved?'approved':'awaiting_review'),'Correct state for each scenario');
 scenario_check($balance===($approved?(float)$amount:0.0),'Only actual approved funds are credited');
 scenario_check((float)$request['received_fiat_amount']===(float)$amount,'Actual receipt amount is recorded');
 $p=proc_open([PHP_BINARY,__FILE__,'--render',(string)$id],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);if(proc_close($p)!==0)throw new RuntimeException($output.$error);$view=json_decode($output,true);if(!$view)throw new RuntimeException($output);
 scenario_check(!$approved?str_contains($view['html'],'Wykryto wpłatę'):str_contains($view['html'],'Opłacone'),'Customer rendered expected state');
 if($amount<100)scenario_check(str_contains($view['html'],'Różnica')&&str_contains($view['html'],number_format(100-$amount,2,'.','')),'Customer sees exact underpayment difference');
 if(!$approved)scenario_check(!str_contains($view['html'],'data-copy-text'),'Review screen does not ask for a duplicate transfer');
 $name=($auto?'on':'off').'-'.$amount;$file='/tmp/crypto-customer-'.$name.'.html';file_put_contents($file,$view['html']);
 $results[]=['scenario'=>$name,'requested'=>100,'received'=>$amount,'balance'=>$balance,'status'=>$request['status'],'file'=>$file];
}}
// Complete the manual partial-payment path and check exactly-once credit.
$manualInput=['received_crypto_amount'=>'.001','received_fiat_amount'=>'50'];
$manual=admin_payment_apply_quick_action($db,'crypto',9003,'accept',0,'scenario:manual',$manualInput);
scenario_check(!empty($manual['ok']),'Administrator can approve a partial receipt');
scenario_check((float)$db->select_user('SELECT balance_amount FROM customers WHERE id=9003')['balance_amount']===50.0,'Manual approval credits only the received 50 EUR');
admin_payment_apply_quick_action($db,'crypto',9003,'accept',0,'scenario:manual',$manualInput);
scenario_check((float)$db->select_user('SELECT balance_amount FROM customers WHERE id=9003')['balance_amount']===50.0,'Repeated manual approval does not credit twice');
// Additional history-only fixtures cover archived, expired and cancelled visuals.
foreach([9101=>'archived',9102=>'expired',9103=>'cancelled'] as $id=>$status){
 $days=$id-9100;$received=$status==='archived'?'40':'NULL';$receivedCrypto=$status==='archived'?'.0008':'NULL';
 scenario_sql("INSERT INTO crypto_deposit_requests(id,customer_id,crypto_asset_id,wallet_address_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,received_fiat_amount,received_crypto_amount,status,requested_at,expires_at) VALUES({$id},9004,{$btc},9004,40,2,50000,.0008,{$received},{$receivedCrypto},'{$status}',DATE_SUB(NOW(),INTERVAL {$days} DAY),DATE_SUB(NOW(),INTERVAL {$days} DAY))");
}
$p=proc_open([PHP_BINARY,__FILE__,'--render','9004'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$body=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);if(proc_close($p)!==0)throw new RuntimeException($body.$err);$history=json_decode($body,true);
scenario_check(str_contains($history['html'],'Zarchiwizowane')&&str_contains($history['html'],'Anulowane')&&str_contains($history['html'],'Wygasłe'),'History renders archived, cancelled and expired states');
file_put_contents('/tmp/crypto-customer-history.html',$history['html']);
file_put_contents('/tmp/crypto-customer-scenarios.json',json_encode($results));
echo 'PASS '.$checks.' customer full/partial receipt, mode and rendered-view assertions'.PHP_EOL;
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;
