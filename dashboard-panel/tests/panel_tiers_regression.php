<?php
declare(strict_types=1);
if(!str_contains((string)getenv('DB_NAME'),'_test_')||!getenv('APP_CRYPTO_CACHE_DIR'))throw new RuntimeException('Isolated database and cache required.');
require_once dirname(__DIR__).'/admin/bootstrap.php';
app_crypto_network_budget(0,0);
$db=Mysql_ks::get_instance();
if(($argv[1]??'')==='--smtp'){
    $capture=(string)$argv[2];
    if(!str_starts_with($capture,app_crypto_runtime_dir().'/'))throw new RuntimeException('Isolated capture path required.');
    $server=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if(!$server)throw new RuntimeException('Cannot start isolated SMTP.');
    echo substr(strrchr(stream_socket_get_name($server,false),':'),1)."\n";flush();
    $client=stream_socket_accept($server,10);if(!$client)exit(2);stream_set_timeout($client,10);
    fwrite($client,"220 localhost test SMTP\r\n");$body='';
    while(($line=fgets($client))!==false){
        $command=strtoupper(strtok(trim($line),' '));
        if(in_array($command,['EHLO','HELO'],true))fwrite($client,"250-localhost\r\n250-AUTH LOGIN PLAIN\r\n250 OK\r\n");
        elseif($command==='AUTH'){
            if(str_starts_with(strtoupper(trim($line)),'AUTH LOGIN')){fwrite($client,"334 VXNlcm5hbWU6\r\n");fgets($client);fwrite($client,"334 UGFzc3dvcmQ6\r\n");fgets($client);}
            fwrite($client,"235 Authenticated\r\n");
        }elseif($command==='DATA'){
            fwrite($client,"354 End with dot\r\n");
            while(($data=fgets($client))!==false&&trim($data)!=='.'){ $body.=$data;if(strlen($body)>1000000)exit(3); }
            file_put_contents($capture,$body);fwrite($client,"250 Queued\r\n");
        }elseif($command==='QUIT'){fwrite($client,"221 Bye\r\n");break;}
        else fwrite($client,"250 OK\r\n");
    }
    fclose($client);fclose($server);exit;
}
if(($argv[1]??'')==='--create'){
    try{echo json_encode(['id'=>app_panel_tiers_create($db,1,'BTC',1)['id']]);}catch(Throwable $e){echo json_encode(['error'=>$e->getMessage()]);}exit;
}
if(($argv[1]??'')==='--apply'){
    $r=$db->select_user('SELECT * FROM panel_subscription_requests WHERE id='.(int)$argv[2]);
    $candidates=app_panel_tiers_candidates($r,json_decode(base64_decode($argv[3]),true));
    echo json_encode(['applied'=>app_panel_tiers_apply($db,$r,$candidates)]);exit;
}
if(($argv[1]??'')==='--page'){
    admin_start_session();$_SESSION['admin_user_id']=1;$_SESSION['admin_locale']='pl';$_SESSION['admin_csrf_token']='fixture';
    $_SERVER['HTTP_HOST']='localhost';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/admin/index.php';$_SERVER['DOCUMENT_ROOT']=dirname(__DIR__,2).'/public_html';
    $_GET=['page'=>$argv[2]??'dashboard','admin'=>'true'];$_POST=[];
    $errors=[];set_error_handler(static function($severity,$message)use(&$errors){if(error_reporting()&$severity)$errors[]=$message;return false;});
    ob_start();register_shutdown_function(static function()use(&$errors):void{$html=ob_get_clean();if(session_status()===PHP_SESSION_ACTIVE)session_destroy();echo json_encode(['ok'=>$errors===[]&&str_contains($html,'id="adminPanelTierPaymentModal"'),'error'=>$errors]);});
    require dirname(__DIR__).'/admin/index.php';exit;
}
if(($argv[1]??'')==='--endpoint'){
    admin_start_session();$_SESSION['admin_csrf_token']='fixture';$_SESSION['admin_locale']='pl';
    if(($argv[2]??'')!=='anonymous')$_SESSION['admin_user_id']=($argv[2]??'')==='limited'?2:1;
    $_SERVER['REQUEST_METHOD']=($argv[2]??'')==='get'?'GET':'POST';
    $_POST=['action'=>'load','_csrf'=>($argv[2]??'')==='csrf'?'wrong':'fixture'];
    ob_start();register_shutdown_function(static function():void{$body=ob_get_clean();$status=http_response_code();if(session_status()===PHP_SESSION_ACTIVE)session_destroy();else { $file=session_save_path().'/sess_'.session_id(); if(is_file($file))unlink($file); }echo json_encode(['status'=>$status,'body'=>json_decode($body,true)]);});
    require dirname(__DIR__).'/admin/panel_subscription_endpoint.php';exit;
}
function endpoint(string $mode): array {
    $process=proc_open([PHP_BINARY,__FILE__,'--endpoint',$mode],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);$exit=proc_close($process);$data=json_decode($out,true);if($exit||!is_array($data))throw new RuntimeException('Endpoint: '.$out.$err);return $data;
}

$n=0;
function ck(bool $v,string $message):void{global $n;if(!$v)throw new RuntimeException($message);$n++;}
function sql(string $q):void{global $db;if(!$db->query($q))throw new RuntimeException('Fixture SQL: '.$db->error);}
function fails(callable $fn,string $code):void{try{$fn();}catch(DomainException $e){ck($e->getMessage()===$code,'Expected '.$code.', got '.$e->getMessage());return;}throw new RuntimeException('Expected failure '.$code);}
app_ensure_sales_runtime_columns($db);app_ensure_order_renewal_runtime_columns($db);app_fetch_settings($db);
sql('SET FOREIGN_KEY_CHECKS=0');sql("INSERT IGNORE INTO app_settings(id,site_name) VALUES(1,'Tier fixture')");
$eur=$db->select_user("SELECT id FROM currencies WHERE code='EUR'");$currency=(int)$eur['id'];
sql("UPDATE app_settings SET default_currency_id={$currency},default_locale_code='pl',maintenance_mode=0,admin_panel_subscription_paid_until=NULL");
sql('DROP TABLE IF EXISTS panel_subscription_observations,panel_subscription_transfers,panel_subscription_requests,panel_subscription_entitlements,panel_subscription_config');
foreach(['panel_subscription_observations','panel_subscription_transfers','panel_subscription_requests','panel_subscription_entitlements','panel_subscription_config'] as $name)unset($GLOBALS['schema_object_exists_cache'][$name]);
app_panel_tiers_ensure_schema($db);app_panel_tiers_ensure_schema($db);
$c=app_panel_tiers_config($db);ck(empty($c['enabled'])&&(int)$c['notify_admin']===1,'OFF by default, notification ON');
ck($c['price_1']==='29.00'&&(int)$c['limit_1']===50&&$c['currency_code']==='EUR','Default prices/limits/currency');
foreach([0=>[1,false],44=>[1,false],45=>[1,true],50=>[1,true],51=>[2,true],55=>[2,true],56=>[2,false],224=>[2,false],225=>[2,true],250=>[2,true],251=>[3,true],259=>[3,true],275=>[3,true],276=>[3,false]] as $count=>$expected){$p=app_panel_tiers_policy($c,$count,[]);ck($p['tier']===$expected[0]&&$p['near_limit']===$expected[1],'Tier boundary '.$count);ck($p['allowed_months']===($expected[1]?[1]:[1,3]),'Period boundary '.$count);}
$p=app_panel_tiers_policy(array_merge($c,['limit_1'=>10,'limit_2'=>20]),19,[]);ck($p['tier']===2&&$p['allowed_months']===[1],'Custom thresholds');
$now=(new DateTimeImmutable('2026-01-01 12:00:00'))->getTimestamp();
ck(!app_panel_tiers_policy($c,0,['admin_panel_subscription_paid_until'=>'2026-02-01'],$now)['can_renew'],'More than thirty days blocked');
ck(app_panel_tiers_policy($c,0,['admin_panel_subscription_paid_until'=>'2026-01-30'],$now)['can_renew'],'Thirty days allowed');
foreach([['2026-01-31',1,'2026-03-03'],['2028-01-31',1,'2028-03-02'],['2026-01-31',3,'2026-05-01'],['2026-03-29',1,'2026-04-29']] as [$date,$months,$expected])ck(app_panel_tiers_extended_date($date,$months,new DateTimeImmutable('2025-01-01'))===$expected,'Calendar month clamp '.$date);
ck(app_panel_tiers_extended_date('2020-01-01',1,new DateTimeImmutable('2026-10-18'))==='2026-11-18','Expired date starts today');
$btc='1BoatSLRHtKNngkdXEeobR76b53LETtpyT';
ck(app_panel_tiers_validate_wallet('BTC',$btc),'Base58 BTC checksum');
ck(!app_panel_tiers_validate_wallet('BTC',substr($btc,0,-1).'X'),'Wrong BTC checksum rejected');
ck(app_panel_tiers_validate_wallet('USDT','0xdAC17F958D2ee523a2206206994597C13D831ec7'),'EVM format');
ck(!app_panel_tiers_validate_wallet('USDT','0x'.str_repeat('0',40)),'Zero EVM rejected');
ck(app_panel_tiers_validate_wallet('SOL','So11111111111111111111111111111111111111112'),'32 byte SOL address');
ck(!app_panel_tiers_validate_wallet('SOL',str_repeat('z',44)),'Non 32 byte SOL rejected');
sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash,status) VALUES(1,1,'admin','admin@example.invalid','unused','active')");
sql("INSERT INTO customers(id,email,public_handle,password_hash,status) VALUES(1,'one@example.invalid','one','unused','active')");
sql("INSERT INTO products(id,name,slug,price_amount,currency_id,duration_hours,product_type,is_active) VALUES(1,'Subscription','tier-sub',10,{$currency},720,'subscription',1),(2,'Credits','tier-credits',10,{$currency},0,'credits',1)");
sql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,expires_at,paid_at,created_at) VALUES
 (1,1,1,10,{$currency},'active','paid',DATE_ADD(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 20 DAY),DATE_SUB(NOW(),INTERVAL 20 DAY)),
 (2,1,1,10,{$currency},'active','paid',DATE_ADD(NOW(),INTERVAL 10 DAY),DATE_SUB(NOW(),INTERVAL 20 DAY),DATE_SUB(NOW(),INTERVAL 20 DAY)),
 (3,1,1,10,{$currency},'expired','paid',DATE_SUB(NOW(),INTERVAL 20 DAY),DATE_SUB(NOW(),INTERVAL 60 DAY),DATE_SUB(NOW(),INTERVAL 60 DAY)),
 (4,1,1,10,{$currency},'active','unpaid',DATE_ADD(NOW(),INTERVAL 10 DAY),NULL,NOW()),
 (5,1,2,10,{$currency},'completed','paid',NULL,NOW(),NOW())");
app_panel_tiers_capture($db);ck(app_panel_tiers_usage($db)===2,'Each paid subscription counts, same customer, no credits/unpaid/expired');
sql("UPDATE orders SET status='expired',payment_status='unpaid',expires_at=DATE_SUB(NOW(),INTERVAL 1 DAY),paid_at=NULL WHERE id IN(1,2)");
app_panel_tiers_capture($db);ck(app_panel_tiers_usage($db)===2,'Status/payment/expiry toggles do not shrink ledger');
sql('DELETE FROM orders WHERE id=1');app_panel_tiers_capture($db);ck(app_panel_tiers_usage($db)===2,'Deletion preserves entitlement');
sql('DELETE FROM panel_subscription_entitlements WHERE order_id=2');
sql("INSERT INTO bank_transfer_requests(id,customer_id,order_id,bank_account_id,requested_amount,currency_id,status) VALUES(1,1,2,1,10,{$currency},'approved')");
app_panel_tiers_capture($db);ck(app_panel_tiers_usage($db)===2,'Approved linked payment counts even after order flags were cleared');

sql("INSERT INTO order_renewals(id,order_id,customer_id,product_id,price_amount,currency_id,duration_hours,status,paid_at,target_expires_at) VALUES(1,3,1,1,10,{$currency},720,'paid_pending_activation',NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY))");
app_panel_tiers_capture($db);ck(app_panel_tiers_usage($db)===3,'Paid renewal pending activation counts once');
sql("UPDATE order_renewals SET status='cancelled',paid_at=NULL,target_expires_at=NULL WHERE id=1");app_panel_tiers_capture($db);ck(app_panel_tiers_usage($db)===3,'Reversal cannot erase recorded paid term');
$input=['panel_auto_enabled'=>'1','panel_notify_admin'=>'1','panel_price_1'=>'29','panel_price_2'=>'59','panel_price_3'=>'99','panel_limit_1'=>'50','panel_limit_2'=>'250','panel_wallet_btc'=>$btc];
fails(fn()=>app_panel_tiers_save_config($db,array_merge($input,['panel_price_1'=>'-1']),'EUR'),'price');
fails(fn()=>app_panel_tiers_save_config($db,array_merge($input,['panel_limit_2'=>'40']),'EUR'),'limits');
fails(fn()=>app_panel_tiers_save_config($db,array_merge($input,['panel_wallet_btc'=>'bad']),'EUR'),'wallet');
fails(fn()=>app_panel_tiers_save_config($db,array_merge($input,['panel_wallet_btc'=>'']),'EUR'),'receivers');
app_panel_tiers_save_config($db,$input,'EUR');
sql("INSERT INTO admin_roles(id,name,slug,access_level) VALUES(99,'Limited','tier-limited',100)");
sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash,status) VALUES(2,99,'limited','limited@example.invalid','unused','active')");
foreach(['anonymous'=>401,'limited'=>401,'csrf'=>403,'get'=>405,'valid'=>200] as $mode=>$expected){$response=endpoint($mode);ck($response['status']===$expected,'Endpoint authentication/CSRF/method '.$mode);}
foreach(['dashboard','settings','orders'] as $page){
    $process=proc_open([PHP_BINARY,__FILE__,'--page',$page],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);$exit=proc_close($process);$data=json_decode($out,true);if($exit||empty($data['ok']))throw new RuntimeException('Enabled page render: '.$page.' '.$out.$err);ck(true,'Enabled page render '.$page);
}


ck(array_keys(app_panel_tiers_receivers($db,app_panel_tiers_config($db)))===['BTC'],'Only configured receivers offered');
sql("UPDATE crypto_assets SET current_rate_fiat=50000,rate_currency_code='EUR',rate_updated_at=UTC_TIMESTAMP() WHERE code='BTC'");
sql("UPDATE app_settings SET admin_panel_subscription_paid_until=DATE_ADD(CURDATE(),INTERVAL 40 DAY)");
fails(fn()=>app_panel_tiers_create($db,1,'BTC',1),'too_early');
sql("UPDATE app_settings SET admin_panel_subscription_paid_until=NULL");
// Two independent processes race the same installation; both must receive the same invoice.
$children=[];for($i=0;$i<2;$i++){$process=proc_open([PHP_BINARY,__FILE__,'--create'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$children[]=[$process,$pipes];}
$ids=[];foreach($children as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);$exit=proc_close($process);$data=json_decode($out,true);if($exit||!isset($data['id']))throw new RuntimeException('Concurrent create: '.$out.$err);$ids[]=$data['id'];}
ck(count(array_unique($ids))===1,'Concurrent clicks return one invoice');
$r=app_panel_tiers_pending($db);
ck(app_timestamp_from_utc_datetime($r['pay_before'])-app_timestamp_from_utc_datetime($r['created_at'])===1800,'New invoice allows exactly thirty minutes to pay');
ck((int)$r['tier']===1&&(int)$r['months']===1&&$r['amount_fiat']==='29.00','Server fixes price and tier');
ck((float)$r['amount_crypto']>=29/50000,'Rounded amount covers entire invoice');
fails(fn()=>app_panel_tiers_save_config($db,array_merge($input,['panel_price_1'=>'30']),'EUR'),'pending_config');
app_panel_tiers_save_config($db,array_merge($input,['panel_auto_enabled'=>'0','panel_price_1'=>'30']),'EUR');
ck(empty(app_panel_tiers_config($db)['enabled'])&&app_panel_tiers_config($db)['price_1']==='29.00','Emergency OFF preserves pending invoice configuration');
ck(app_panel_tiers_process($db)['checked']===0,'Emergency OFF stops pending scan');
app_panel_tiers_save_config($db,$input,'EUR');

$base=['hash'=>str_repeat('a',64),'direction'=>'incoming','amount_crypto'=>(float)$r['amount_crypto'],'timestamp'=>time(),'confirmations'=>2];
$good=app_panel_tiers_candidates($r,[$base]);ck($good!==[],'Exact confirmed BTC accepted');
foreach([
 ['timestamp'=>app_timestamp_from_utc_datetime($r['created_at'])-1],
 ['timestamp'=>app_timestamp_from_utc_datetime($r['pay_before'])+1],
 ['direction'=>'outgoing'],['amount_crypto'=>0],['confirmations'=>1],['amount_crypto'=>NAN],['hash'=>'invalid']
] as $invalid)ck(app_panel_tiers_candidates($r,[array_merge($base,$invalid)])===[],'Invalid candidate rejected '.implode(',',array_keys($invalid)));
ck(count(app_panel_tiers_candidates($r,[$base,array_merge($base,['hash'=>str_repeat('b',64)])]))===2,'Two verified top-ups may be aggregated');
ck(app_panel_tiers_candidates($r,[$base],fn()=>null)===[],'Failed verification rejected');
$children=[];
for($i=0;$i<4;$i++){$proc=proc_open([PHP_BINARY,__FILE__,'--apply',(string)$r['id'],base64_encode(json_encode($good))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$children[]=[$proc,$pipes];}
$settled=0;foreach($children as [$proc,$pipes]){$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);$exit=proc_close($proc);$result=json_decode($output,true);if($exit||!is_array($result))throw new RuntimeException('Concurrent settlement '.$output.$error);$settled+=(int)$result['applied'];}
ck($settled===1,'Four concurrent detections grant access once');
ck(!app_panel_tiers_apply($db,$r,$good),'Repeat settlement is idempotent');
ck(app_panel_tiers_pending($db)===null,'Paid invoice releases open slot');
$paid=$db->select_user('SELECT * FROM panel_subscription_requests WHERE id='.(int)$r['id']);
ck($paid['new_paid_until']===app_panel_tiers_extended_date(null,1),'Applied correct calendar month');
ck((int)$db->select_user("SELECT COUNT(*) AS n FROM outbound_email_queue WHERE dedupe_key='panel-access-paid:".(int)$r['id']."'")['n']===1,'One queued email to login admin');
ck($db->select_user("SELECT to_email FROM outbound_email_queue WHERE dedupe_key='panel-access-paid:".(int)$r['id']."'")['to_email']==='admin@example.invalid','Email recipient is admin account');
// One-month purchase cannot be stacked immediately when >30 days remain.
sql("UPDATE app_settings SET admin_panel_subscription_paid_until=DATE_ADD(CURDATE(),INTERVAL 40 DAY)");fails(fn()=>app_panel_tiers_create($db,1,'BTC',1),'too_early');
sql("UPDATE app_settings SET admin_panel_subscription_paid_until=NULL");
// Near-limit rules checked server-side even when form is forged.
sql("UPDATE panel_subscription_config SET limit_1=3,limit_2=10,notify_admin=0 WHERE id=1");
fails(fn()=>app_panel_tiers_create($db,1,'BTC',3),'period');
$r=app_panel_tiers_create($db,1,'BTC',1);
ck($r['amount_crypto']!==$paid['amount_crypto'],'Never reuses a previous invoice amount');
$good=app_panel_tiers_candidates($r,[array_merge($base,['hash'=>str_repeat('b',64),'amount_crypto'=>(float)$r['amount_crypto'],'timestamp'=>time()])]);
ck(app_panel_tiers_apply($db,$r,$good),'Second distinct payment can renew');
ck((int)$db->select_user("SELECT COUNT(*) AS n FROM outbound_email_queue WHERE dedupe_key LIKE 'panel-access-paid:%'")['n']===1,'Email switch OFF suppresses new receipt');
sql("UPDATE app_settings SET admin_panel_subscription_paid_until=NULL");
$r=app_panel_tiers_create($db,1,'BTC',1);
sql("UPDATE panel_subscription_config SET notify_admin=1 WHERE id=1");
$part=app_panel_tiers_candidates($r,[array_merge($base,['hash'=>str_repeat('d',64),'amount_crypto'=>(float)$r['amount_crypto']*.5,'timestamp'=>time()])]);
ck(!app_panel_tiers_apply($db,$r,$part),'Below 90% does not grant access');
$r=app_panel_tiers_pending($db);ck($r['status']==='partial'&&(float)$r['received_crypto']>0,'Underpayment is recorded for top-up');
ck((int)$db->select_user("SELECT COUNT(*) AS n FROM outbound_email_queue WHERE dedupe_key LIKE 'panel-access-partial:%'")['n']===1,'One partial payment email queued');

$partialMail=$db->select_user("SELECT body_html FROM outbound_email_queue WHERE dedupe_key LIKE 'panel-access-partial:".(int)$r['id'].":%' LIMIT 1")['body_html'];
ck(str_contains($partialMail,app_panel_tiers_remaining($r))&&str_contains($partialMail,'Dostęp nie został jeszcze przedłużony.'),'Partial email has exact remainder and no false renewal');
ck(!str_contains($partialMail,'Dostęp przedłużony do'),'Partial email does not show an empty extension date');
$partialDeadline=$r['pay_before'];$partialFinality=$r['check_until'];
$firstReceived=$r['received_crypto'];
ck(!app_panel_tiers_apply($db,$r,$part),'Same partial transaction cannot count twice');
ck(app_panel_tiers_pending($db)['received_crypto']===$firstReceived,'Duplicate partial leaves total unchanged');
ck(app_panel_tiers_pending($db)['pay_before']===$partialDeadline&&app_panel_tiers_pending($db)['check_until']===$partialFinality,'Duplicate partial does not prolong payment windows');

$topup=app_panel_tiers_candidates($r,[array_merge($base,['hash'=>str_repeat('e',64),'amount_crypto'=>(float)$r['amount_crypto']*.45,'timestamp'=>time()])]);
ck(app_panel_tiers_apply($db,$r,$topup),'95% combined payment grants proportional days');
$paidPartial=$db->select_user('SELECT * FROM panel_subscription_requests WHERE id='.(int)$r['id']);
ck((int)$paidPartial['granted_days']===(int)floor((int)$r['quoted_days']*.95),'Days rounded down for underpayment');
$receiptEmail=$db->select_user("SELECT body_html FROM outbound_email_queue WHERE dedupe_key='panel-access-paid:".(int)$r['id']."'");
ck(str_contains($receiptEmail['body_html'],(string)$paidPartial['granted_days'])&&str_contains($receiptEmail['body_html'],$paidPartial['new_paid_until']),'Email includes actual days and access date');
ck((int)$db->select_user("SELECT COUNT(*) AS n FROM outbound_email_queue WHERE dedupe_key LIKE 'panel-access-partial:%'")['n']===1,'Repeated partial scan sends no duplicate email');
ck(str_contains($receiptEmail['body_html'],str_repeat('d',64))&&str_contains($receiptEmail['body_html'],str_repeat('e',64)),'Final email lists both initial payment and top-up');
ck(str_contains($receiptEmail['body_html'],'<strong>'.$paidPartial['granted_days'].'</strong> / '.$paidPartial['quoted_days']),'Final email gives exact awarded/full days');
$dateOnce=$paidPartial['new_paid_until'];
for($i=0;$i<3;$i++)ck(!app_panel_tiers_apply($db,$r,array_merge($part,$topup)),'Replaying all partial transfers after settlement does not apply again');
ck($db->select_user('SELECT admin_panel_subscription_paid_until AS d FROM app_settings WHERE id=1')['d']===$dateOnce,'Repeated top-ups leave access date unchanged');


ck(app_panel_tiers_granted_days(31,90,100)===27,'90% of 31 grants 27 days');
ck(app_panel_tiers_granted_days(30,99,100)===29,'99% of 30 grants 29 days');
ck(app_panel_tiers_granted_days(30,89.99,100)===0,'Below 90% requires top-up');
ck(app_panel_tiers_granted_days(31,110,100)===31,'Overpayment cannot buy extra days');
foreach([6,8,9] as $decimals){
    $full=app_panel_tiers_units_amount(100000,$decimals);
    ck(app_panel_tiers_granted_days(30,app_panel_tiers_units_amount(89999,$decimals),$full,$decimals)===0,'One native unit under 90% stays unpaid');
    ck(app_panel_tiers_granted_days(30,app_panel_tiers_units_amount(90000,$decimals),$full,$decimals)===27,'Exactly 90% grants correct days');
    ck(app_panel_tiers_granted_days(30,app_panel_tiers_units_amount(99999,$decimals),$full,$decimals)===29,'One native unit below full payment rounds days down');
    ck(app_panel_tiers_granted_days(30,$full,$full,$decimals)===30,'Exact full native amount grants full period');
}
ck(app_panel_tiers_amount_units('0.000000001',8)===null,'Sub-satoshi strings cannot inflate a Bitcoin receipt');
ck(app_panel_tiers_granted_days(31,NAN,1)===0&&app_panel_tiers_granted_days(31,INF,1)===0,'Non-finite amounts grant no access');

sql("UPDATE app_settings SET admin_panel_subscription_paid_until=NULL");
$r=app_panel_tiers_create($db,1,'BTC',1);
$rollbackCandidate=app_panel_tiers_candidates($r,[array_merge($base,['hash'=>str_repeat('f',64),'amount_crypto'=>(float)$r['amount_crypto'],'timestamp'=>time()])]);
sql('RENAME TABLE outbound_email_queue TO tier_fixture_email_queue');
try {
    $failed=false;try{app_panel_tiers_apply($db,$r,$rollbackCandidate);}catch(Throwable $e){$failed=true;}
    ck($failed,'Queue write failure aborts settlement');
    ck($db->select_user('SELECT admin_panel_subscription_paid_until AS d FROM app_settings WHERE id=1')['d']===null,'Failed settlement rolls back access date');
    ck((int)$db->select_user('SELECT COUNT(*) AS n FROM panel_subscription_transfers WHERE request_id='.(int)$r['id'])['n']===0,'Failed settlement rolls back transfer claim');
    ck(app_panel_tiers_pending($db)['status']==='pending','Failed settlement keeps invoice retryable');
} finally {sql('RENAME TABLE tier_fixture_email_queue TO outbound_email_queue');}
ck(app_panel_tiers_apply($db,$r,$rollbackCandidate),'Retry after storage recovery settles exactly once');
// Execute the actual scan/parse/confirm/sum/settle pipeline against cached blockchain fixtures.
sql("UPDATE app_settings SET admin_panel_subscription_paid_until=NULL");$scanRequest=app_panel_tiers_create($db,1,'BTC',1);
$scanId=(int)$scanRequest['id'];
sql("UPDATE panel_subscription_requests SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),pay_before=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 20 MINUTE) WHERE id={$scanId}");
$scanRequest=app_panel_tiers_pending($db);$native=app_panel_tiers_amount_units($scanRequest['amount_crypto'],8);
$firstUnits=intdiv($native,2);$topUpUnits=$native-$firstUnits;
function tier_cache(string $url,$body,bool $plain=false):void{
    $key=hash('sha256',$url.'|null|'.(int)$plain);
    app_crypto_runtime_write(app_crypto_runtime_dir().'/request-'.$key.'.json',['expires'=>microtime(true)+3600,'body'=>is_string($body)?$body:json_encode($body)]);
}
function tier_chain_rows(array $invoice,array $transfers):void{
    foreach(glob(admin_crypto_accept_preview_cache_dir().'/preview-*.json')?:[] as $file)unlink($file);
    $rows=[];
    foreach($transfers as $transfer){[$hash,$units,$confirmations,$address]=$transfer;$confirmed=$transfer[5]??true;$stamp=$confirmed?($transfer[4]??time()-30):0;$rows[]=['txid'=>$hash,'status'=>['confirmed'=>$confirmed,'block_height'=>100-$confirmations+1,'block_time'=>$stamp],'vin'=>[['prevout'=>['scriptpubkey_address'=>'external-sender','value'=>$units]]],'vout'=>[['scriptpubkey_address'=>$address,'value'=>$units]]];}
    tier_cache('https://mempool.space/api/address/'.rawurlencode($invoice['wallet_address']).'/txs',$rows);
    tier_cache('https://mempool.space/api/blocks/tip/height','100',true);
    tier_cache('https://blockchain.info/rawaddr/'.rawurlencode($invoice['wallet_address']).'?limit=25&offset=0',['txs'=>[]]);
}
$h1=hash('sha256','first confirmed instalment');$h2=hash('sha256','final confirmed top-up');$wrong=hash('sha256','wrong destination');
tier_chain_rows($scanRequest,[[$wrong,$native,2,'different-wallet'],[$h1,$firstUnits,1,$btc]]);
$scan=app_panel_tiers_process($db);ck($scan['checked']===1&&$scan['accepted']===0,'Actual scan ignores wrong destination and insufficient confirmations');
ck(app_panel_tiers_pending($db)['received_crypto']==='0.000000000','Unconfirmed transfer does not count as received');
$beforeThrottle=Mysql_ks::$licznik;
$scan=app_panel_tiers_process($db);ck($scan['checked']===0,'Back-to-back checks share one database lease');
ck(Mysql_ks::$licznik-$beforeThrottle<=2,'Fresh scan lease exits with at most two cheap database queries');
sql("UPDATE panel_subscription_config SET last_scan_at=NULL WHERE id=1");
sql("UPDATE panel_subscription_requests SET last_checked_at=NULL WHERE id={$scanId}");
tier_chain_rows($scanRequest,[[$h1,$firstUnits,2,$btc]]);
$scan=app_panel_tiers_process($db);ck($scan['accepted']===0&&app_panel_tiers_pending($db)['status']==='partial','Confirmed half payment becomes partial via actual parser');
$scanRequest=app_panel_tiers_pending($db);
sql("UPDATE panel_subscription_config SET last_scan_at=NULL WHERE id=1");
sql("UPDATE panel_subscription_requests SET last_checked_at=NULL WHERE id={$scanId}");
tier_chain_rows($scanRequest,[[$h1,$firstUnits,2,$btc],[$h2,$topUpUnits,2,$btc]]);
$scan=app_panel_tiers_process($db);ck($scan['accepted']===1,'Confirmed top-up settles through actual blockchain pipeline');
$scanPaid=$db->select_user("SELECT * FROM panel_subscription_requests WHERE id={$scanId}");
ck((int)$scanPaid['granted_days']===(int)$scanPaid['quoted_days'],'Full aggregate payment grants full days');
$afterDate=$scanPaid['new_paid_until'];$queueBefore=(int)$db->select_user('SELECT COUNT(*) AS n FROM outbound_email_queue')['n'];
for($i=0;$i<3;$i++)ck(app_panel_tiers_process($db)['checked']===0,'Repeated worker scan after payment does not reapply');
ck($db->select_user('SELECT admin_panel_subscription_paid_until AS d FROM app_settings WHERE id=1')['d']===$afterDate,'Repeated worker preserves paid-through date');
ck((int)$db->select_user('SELECT COUNT(*) AS n FROM outbound_email_queue')['n']===$queueBefore,'Repeated worker sends no additional receipt');
$rows=[['hash'=>$h1],['hash'=>$h2],['hash'=>hash('sha256','not claimed')]];
ck(count(app_panel_tiers_unclaimed_rows($db,$scanPaid,$rows))===1,'Claimed transfers are filtered before expensive receipt lookups');
// An on-time mempool transfer can be mined/confirmed after 00:00 without losing its invoice.
sql("UPDATE app_settings SET admin_panel_subscription_paid_until=NULL");$delayed=app_panel_tiers_create($db,1,'BTC',1);$delayId=(int)$delayed['id'];
sql("UPDATE panel_subscription_requests SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) WHERE id={$delayId}");
$delayed=app_panel_tiers_pending($db);$delayedHash=hash('sha256','on time broadcast confirmed after deadline');$delayedUnits=app_panel_tiers_amount_units($delayed['amount_crypto'],8);
sql('UPDATE panel_subscription_config SET last_scan_at=NULL WHERE id=1');
tier_chain_rows($delayed,[[$delayedHash,$delayedUnits,0,$btc,null,false]]);
$observed=app_panel_tiers_process($db);
ck($observed['accepted']===0&&app_panel_tiers_pending($db)['received_crypto']==='0.000000000','Mempool observation never credits unconfirmed funds');
ck(isset(app_panel_tiers_observations($db,$delayed)[$delayedHash]),'On-time unconfirmed transaction retains its first-seen evidence');
$firstSeen=app_panel_tiers_observations($db,$delayed)[$delayedHash];$queriesBefore=Mysql_ks::$licznik;
$known=app_panel_tiers_observe($db,$delayed,[array_merge($base,['hash'=>$delayedHash,'amount_crypto'=>$delayed['amount_crypto'],'timestamp'=>time()])]);
ck($known[$delayedHash]===$firstSeen&&Mysql_ks::$licznik-$queriesBefore<=1,'Known observation preserves first-seen time without repeat writes');
$probe=$delayed;$probe['id']=$delayId+10000;
ck(app_panel_tiers_unclaimed_rows($db,$probe,[['hash'=>$delayedHash]])===[],'Observed transfer cannot be reassigned to another invoice');
// Advance only the disposable fixture clock relative to its stored deadline.
sql("UPDATE panel_subscription_requests SET pay_before=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 SECOND) WHERE id={$delayId}");
sql("UPDATE panel_subscription_observations SET first_seen_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 40 SECOND) WHERE request_id={$delayId}");
$delayed=app_panel_tiers_pending($db);
sql('UPDATE panel_subscription_config SET last_scan_at=NULL WHERE id=1');
tier_chain_rows($delayed,[[$delayedHash,$delayedUnits,1,$btc,time()-10]]);
$waiting=app_panel_tiers_process($db);
ck($waiting['accepted']===0&&app_panel_tiers_pending($db)['status']==='confirming','00:00 continues checking an on-time transfer without sufficient confirmations');
sql('UPDATE panel_subscription_config SET last_scan_at=NULL WHERE id=1');
tier_chain_rows($delayed,[[$delayedHash,$delayedUnits,2,$btc,time()-10]]);
$lateConfirmation=app_panel_tiers_process($db);
ck($lateConfirmation['accepted']===1,'On-time broadcast mined and confirmed after deadline renews access');
$delayedPaid=$db->select_user("SELECT * FROM panel_subscription_requests WHERE id={$delayId}");
ck((int)$delayedPaid['granted_days']===(int)$delayedPaid['quoted_days'],'Delayed confirmation grants the full purchased period once');
ck(!app_panel_tiers_apply($db,$delayed,[array_merge($base,['hash'=>$delayedHash,'amount_crypto'=>$delayed['amount_crypto'],'timestamp'=>time()-10])]),'Repeated delayed confirmation cannot add days again');
$unknownLate=array_merge($base,['hash'=>hash('sha256','unknown late transfer'),'amount_crypto'=>$delayed['amount_crypto'],'timestamp'=>time()-10]);
ck(app_panel_tiers_candidates($delayed,[$unknownLate],static fn($p,$r)=>$r,app_panel_tiers_observations($db,$delayed))===[],'A newly late transfer without on-time evidence is not auto-credited');
$bounded=$delayed;$bounded['check_until']=gmdate('Y-m-d H:i:s',time()-15);
ck(!app_panel_tiers_in_window($bounded,time()-10,$delayedHash,app_panel_tiers_observations($db,$delayed)),'First-seen evidence never bypasses the finality cutoff');
ck(app_panel_tiers_in_window($delayed,app_timestamp_from_utc_datetime($delayed['pay_before']),'boundary',[]),'Exact payment-deadline timestamp is accepted');
// An unrelated late receipt must not hide an otherwise eligible full payment in the same scan.
sql("UPDATE app_settings SET admin_panel_subscription_paid_until=NULL");$mixed=app_panel_tiers_create($db,1,'BTC',1);$mixedId=(int)$mixed['id'];
sql("UPDATE panel_subscription_requests SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),pay_before=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 SECOND) WHERE id={$mixedId}");
$mixed=app_panel_tiers_pending($db);$mixedUnits=app_panel_tiers_amount_units($mixed['amount_crypto'],8);
sql('UPDATE panel_subscription_config SET last_scan_at=NULL WHERE id=1');
tier_chain_rows($mixed,[[hash('sha256','eligible alongside late'),$mixedUnits,2,$btc,time()-30],[hash('sha256','unrelated late alongside eligible'),1,2,$btc,time()-10]]);
ck(app_panel_tiers_process($db)['accepted']===1,'Eligible full payment takes priority over unrelated late receipts');
// A verified late transfer with no timely observation is retained for manual reconciliation.
sql("UPDATE app_settings SET admin_panel_subscription_paid_until=NULL");$lateInvoice=app_panel_tiers_create($db,1,'BTC',1);$lateId=(int)$lateInvoice['id'];
sql("UPDATE panel_subscription_requests SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),pay_before=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 SECOND) WHERE id={$lateId}");
$lateInvoice=app_panel_tiers_pending($db);$unseenHash=hash('sha256','late receipt requiring timing review');
sql('UPDATE panel_subscription_config SET last_scan_at=NULL WHERE id=1');
tier_chain_rows($lateInvoice,[[$unseenHash,app_panel_tiers_amount_units($lateInvoice['amount_crypto'],8),2,$btc,time()-10]]);
$lateReview=app_panel_tiers_process($db);$lateState=app_panel_tiers_pending($db);
ck($lateReview['accepted']===0&&$lateState['status']==='review'&&(float)$lateState['received_crypto']>0,'Unproven late payment is recorded for review rather than ignored or auto-credited');
ck($db->select_user('SELECT admin_panel_subscription_paid_until AS d FROM app_settings WHERE id=1')['d']===null,'Unproven timing never grants automatic access');
$reviewMail=$db->select_user("SELECT body_html FROM outbound_email_queue WHERE dedupe_key='panel-access-review:{$lateId}'");
ck(str_contains($reviewMail['body_html']??'','brakuje dowodu nadania'),'Owner review email explains the missing on-time evidence');
sql("UPDATE panel_subscription_requests SET status='review_closed',open_slot=NULL WHERE id={$lateId}");

// A new request cannot reuse a previously claimed transaction, even if its time still matches.
sql("UPDATE app_settings SET admin_panel_subscription_paid_until=NULL");$reuse=app_panel_tiers_create($db,1,'BTC',1);
$reused=[array_merge($base,['hash'=>$h1,'amount_crypto'=>(float)$reuse['amount_crypto'],'timestamp'=>time()])];
ck(!app_panel_tiers_apply($db,$reuse,$reused),'Transaction claimed by an earlier invoice cannot renew another invoice');
ck(app_panel_tiers_pending($db)['received_crypto']==='0.000000000','Reused transfer contributes zero to new invoice');
sql('UPDATE panel_subscription_requests SET status=\'expired\',open_slot=NULL WHERE id='.(int)$reuse['id']);
sql("UPDATE app_settings SET admin_panel_subscription_paid_until=NULL");$r=app_panel_tiers_create($db,1,'BTC',1);
sql("INSERT INTO panel_subscription_entitlements(order_id,customer_id,paid_through) VALUES(999,1,DATE_ADD(NOW(),INTERVAL 10 DAY))");
$good=app_panel_tiers_candidates($r,[array_merge($base,['hash'=>str_repeat('c',64),'amount_crypto'=>(float)$r['amount_crypto'],'timestamp'=>time()])]);
ck(!app_panel_tiers_apply($db,$r,$good),'Growth to next tier pauses automatic settlement');
ck(app_panel_tiers_pending($db)['status']==='review','Payment requiring review stays visible');
$reviewRequest=app_panel_tiers_pending($db);
fails(static fn()=>app_panel_tiers_close_review($db,(int)$reviewRequest['id']-1),'changed');
ck(app_panel_tiers_pending($db)['id']===$reviewRequest['id'],'A stale review action cannot close another invoice');
app_panel_tiers_close_review($db,(int)$reviewRequest['id']);
ck(app_panel_tiers_pending($db)===null,'Explicit review closure frees only the selected invoice');
ck($db->select_user('SELECT admin_panel_subscription_paid_until AS d FROM app_settings WHERE id=1')['d']===null,'Underpriced quote cannot grant access');
$before=Mysql_ks::$licznik;$result=app_panel_tiers_process($db);ck($result['checked']===0,'Review has no blockchain polling');
// Cancellation frees the slot but retains ownership of late transfers on the old receiver.
sql("UPDATE panel_subscription_requests SET status='review_closed',open_slot=NULL WHERE open_slot=1");
sql("DELETE FROM panel_subscription_entitlements WHERE order_id=999");
$cancellable=app_panel_tiers_create($db,1,'BTC',1);$cancelId=(int)$cancellable['id'];
sql("UPDATE panel_subscription_requests SET pay_before=DATE_SUB(pay_before,INTERVAL 2 MINUTE) WHERE id={$cancelId}");
$cancellable=app_panel_tiers_pending($db);
fails(static fn()=>app_panel_tiers_cancel($db,$cancelId+10000),'cannot_cancel');
ck(app_panel_tiers_pending($db)['id']===$cancellable['id'],'Stale cancellation cannot cancel a different invoice');
app_panel_tiers_cancel($db,$cancelId);
ck(app_panel_tiers_pending($db)===null,'Cancel frees the single open payment slot');
$cancelled=$db->select_user("SELECT * FROM panel_subscription_requests WHERE id={$cancelId}");
ck($cancelled['status']==='cancelled','Cancellation retains invoice history');
ck(isset(app_panel_tiers_receivers($db,app_panel_tiers_config($db))['BTC']),'Cancelled cryptocurrency remains available for selection');
sql("UPDATE crypto_assets SET rate_updated_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR) WHERE code='BTC'");
app_crypto_network_budget(0,0);
$restored=app_panel_tiers_create($db,1,'BTC',1);
sql("UPDATE crypto_assets SET rate_updated_at=UTC_TIMESTAMP() WHERE code='BTC'");
ck($restored['id']===$cancellable['id'],'Selecting BTC again restores the same invoice');
ck((float)$restored['exchange_rate']>0,'Restoring a locked quote works without a fresh market-rate fetch');
ck($restored['pay_before']===$cancellable['pay_before']&&$restored['check_until']===$cancellable['check_until'],'Returning to BTC preserves its original remaining time and finality window');
ck($restored['amount_crypto']===$cancellable['amount_crypto']&&$restored['exchange_rate']===$cancellable['exchange_rate'],'Restoring preserves the original quote');
ck((int)$db->select_user("SELECT COUNT(*) AS n FROM panel_subscription_requests WHERE id>{$cancelId}")['n']===0,'Restoring does not create overlapping payment windows');
app_panel_tiers_cancel($db,$cancelId);
sql("UPDATE panel_subscription_requests SET pay_before=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id={$cancelId}");
$expiredReturn=app_panel_tiers_create($db,1,'BTC',1);
ck($expiredReturn['status']==='confirming'&&app_timestamp_from_utc_datetime($expiredReturn['pay_before'])<time(),'Returning after deadline keeps zero remaining time and checks confirmations');
app_panel_tiers_cancel($db,$cancelId);
// Restore the fixture's original deadline for the later on-chain receipt test.
sql("UPDATE panel_subscription_requests SET pay_before='".$db->escape($cancellable['pay_before'])."' WHERE id={$cancelId}");
$cancelled=$db->select_user("SELECT * FROM panel_subscription_requests WHERE id={$cancelId}");
sql("UPDATE panel_subscription_config SET wallet_sol='So11111111111111111111111111111111111111112' WHERE id=1");
sql("UPDATE crypto_assets SET current_rate_fiat=150,rate_currency_code='EUR',rate_updated_at=UTC_TIMESTAMP() WHERE code='SOL'");
$other=app_panel_tiers_create($db,1,'SOL',1);
ck($other['asset_code']==='SOL','Another cryptocurrency can be selected after cancellation');
sql("UPDATE panel_subscription_requests SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) WHERE id={$cancelId}");
$cancelled=$db->select_user("SELECT * FROM panel_subscription_requests WHERE id={$cancelId}");
$lateHash=hash('sha256','late cancelled payment');
$late=[array_merge($base,['hash'=>$lateHash,'amount_crypto'=>$cancelled['amount_crypto'],'timestamp'=>time()])];
tier_chain_rows($cancelled,[[$lateHash,app_panel_tiers_amount_units($cancelled['amount_crypto'],8),2,$btc]]);
sql('UPDATE panel_subscription_config SET last_scan_at=NULL WHERE id=1');
$lateScan=app_panel_tiers_process($db);
ck($lateScan['checked']===1&&$lateScan['accepted']===0,'Actual scan reconciles cancelled invoice without granting access');
ck(app_panel_tiers_process($db)['checked']===0,'Cancelled and active invoices share the same global scan throttle');
ck($db->select_user("SELECT status FROM panel_subscription_requests WHERE id={$cancelId}")['status']==='cancelled_review','Late payment retained for review');
ck(app_panel_tiers_pending($db)['id']===$other['id'],'Late cancelled payment cannot replace the current invoice');
ck($db->select_user('SELECT admin_panel_subscription_paid_until AS d FROM app_settings WHERE id=1')['d']===null,'Late cancelled transfer does not change access');
$mail=$db->select_user("SELECT body_html FROM outbound_email_queue WHERE dedupe_key LIKE 'panel-access-review:{$cancelId}:%' LIMIT 1");
ck(str_contains($mail['body_html']??'','anulowanej płatności'),'Review receipt explains the late cancelled transfer');
$count=(int)$db->select_user('SELECT COUNT(*) AS n FROM outbound_email_queue')['n'];
app_panel_tiers_apply($db,$cancelled,$late);
ck((int)$db->select_user('SELECT COUNT(*) AS n FROM outbound_email_queue')['n']===$count,'Repeated cancelled transfer sends no duplicate receipt');
app_panel_tiers_close_review($db,$cancelId);
ck($db->select_user("SELECT status FROM panel_subscription_requests WHERE id={$cancelId}")['status']==='review_closed','Owner can close a reconciled late-cancelled payment by its explicit ID');
ck($db->select_user('SELECT admin_panel_subscription_paid_until AS d FROM app_settings WHERE id=1')['d']===null,'Closing late-cancelled review never grants days implicitly');
app_panel_tiers_cancel($db,(int)$other['id']);
sql("UPDATE panel_subscription_requests SET check_until=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE status IN ('cancelled','cancelled_review')");
sql('UPDATE panel_subscription_config SET last_scan_at=NULL WHERE id=1');
ck(app_panel_tiers_process($db)['checked']===0,'Expired cancelled invoices close without network scans');
ck(isset(app_panel_tiers_receivers($db,app_panel_tiers_config($db))['BTC']),'Receiver available after the finality window ends');
// A fetch that crosses the finality deadline cannot settle a stale snapshot.
sql("UPDATE app_settings SET admin_panel_subscription_paid_until=NULL");$staleFinality=app_panel_tiers_create($db,1,'BTC',1);$staleId=(int)$staleFinality['id'];
sql("UPDATE panel_subscription_requests SET check_until=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id={$staleId}");
ck(!app_panel_tiers_apply($db,$staleFinality,[array_merge($base,['hash'=>hash('sha256','stale finality worker'),'amount_crypto'=>$staleFinality['amount_crypto'],'timestamp'=>time()])]),'A stale worker cannot grant days after the finality deadline');
app_panel_tiers_process($db);
sql("UPDATE panel_subscription_requests SET status='expired',open_slot=NULL WHERE id={$staleId}");
// A confirmed partial payment cannot be discarded to switch cryptocurrency.
$partial=app_panel_tiers_create($db,1,'BTC',1);$partUnits=intdiv(app_panel_tiers_amount_units($partial['amount_crypto'],8),2);
$part=[array_merge($base,['hash'=>hash('sha256','cannot cancel partial'),'amount_crypto'=>app_panel_tiers_units_amount($partUnits,8),'timestamp'=>time()])];
app_panel_tiers_apply($db,$partial,$part);$beforePartial=app_panel_tiers_pending($db);
fails(static fn()=>app_panel_tiers_cancel($db,(int)$partial['id']),'cannot_cancel');
ck(!app_panel_tiers_apply($db,$partial,$part),'Stale snapshot of the first partial transfer remains idempotent');
$afterPartial=app_panel_tiers_pending($db);
ck($afterPartial['check_until']===$beforePartial['check_until']&&$afterPartial['received_crypto']===$beforePartial['received_crypto'],'Duplicate partial never extends top-up window or adds money');
sql("UPDATE panel_subscription_config SET enabled=0 WHERE id=1");ck(app_panel_tiers_process($db)['checked']===0,'OFF performs no blockchain scan');
// End-to-end native SOL, DOGE and ERC-20 USDT using real decoder-shaped cached responses.
function tier_post_cache(string $url,array $body,array $payload):void{
    $key=hash('sha256',$url.'|'.json_encode($payload).'|0');
    app_crypto_runtime_write(app_crypto_runtime_dir().'/request-'.$key.'.json',['expires'=>microtime(true)+3600,'body'=>json_encode($body)]);
}
function tier_reset_scan(Mysql_ks $db):void{
    $db->query('UPDATE panel_subscription_config SET last_scan_at=NULL WHERE id=1');
    foreach(glob(admin_crypto_accept_preview_cache_dir().'/preview-*.json')?:[] as $f)unlink($f);
}
function tier_asset_chain(array $r,string $hash,int $units,string $phase):void{
    $wallet=$r['wallet_address'];$timestamp=time()-30;
    if($r['asset_code']==='SOL'){
        $signature=['signature'=>$hash,'blockTime'=>$timestamp,'err'=>null,'confirmationStatus'=>$phase==='unfinalized'?'confirmed':'finalized'];
        $query=['jsonrpc'=>'2.0','id'=>1,'method'=>'getSignaturesForAddress','params'=>[$wallet,['commitment'=>'finalized','limit'=>8]]];
        tier_post_cache('https://api.mainnet-beta.solana.com',['result'=>[$signature]],$query);
        $batch=[['jsonrpc'=>'2.0','id'=>1,'method'=>'getTransaction','params'=>[$hash,['encoding'=>'jsonParsed','commitment'=>'finalized','maxSupportedTransactionVersion'=>0]]]];
        $instruction=['program'=>'system','parsed'=>['type'=>'transfer','info'=>['source'=>'external-sender','destination'=>$wallet,'lamports'=>$units]]];
        $receipt=['transaction'=>['message'=>['accountKeys'=>[$wallet,'external-sender'],'instructions'=>[$instruction]]],'meta'=>['err'=>$phase==='failed'?['InstructionError'=>[0,'failure']]:null,'preBalances'=>[0,$units+5000],'postBalances'=>[$units,0]]];
        tier_post_cache('https://api.mainnet-beta.solana.com',[['jsonrpc'=>'2.0','id'=>1,'result'=>$receipt]],$batch);
    }elseif($r['asset_code']==='DOGE'){
        tier_cache('https://opreturn.net/api/doge/addr/'.rawurlencode($wallet).'/1/',['success'=>true,'addr'=>['tx'=>[['txid'=>$hash,'value'=>$units,'receive_block'=>195]]]]);
        tier_cache('https://opreturn.net/api/doge/blockheight/',['blockheight'=>200]);
        tier_cache('https://opreturn.net/api/doge/tx/'.$hash.'/1/',['success'=>true,'tx'=>['time'=>$timestamp,'confirmations'=>$phase==='unfinalized'?5:6,'vin'=>[['address'=>'external-sender']]]]);
    }else{
        $contract=admin_crypto_accept_preview_known_token_contract('USDT','ethereum');
        $operation=['timestamp'=>$timestamp,'tokenInfo'=>['address'=>$contract,'decimals'=>6],'from'=>'0x'.str_repeat('2',40),'to'=>$wallet,'value'=>(string)$units,'transactionHash'=>$hash];
        tier_cache('https://api.ethplorer.io/getAddressHistory/'.rawurlencode($wallet).'?apiKey=freekey&limit=100',['operations'=>[$operation]]);
        $receiptOperation=$operation;
        if($phase==='counterfeit')$receiptOperation['tokenInfo']['address']='0x'.str_repeat('f',40);
        tier_cache('https://api.ethplorer.io/getTxInfo/'.$hash.'?apiKey=freekey',['success'=>$phase!=='failed','confirmations'=>$phase==='unfinalized'?11:12,'hash'=>$hash,'timestamp'=>$timestamp,'operations'=>[$receiptOperation]]);
    }
}
sql("UPDATE panel_subscription_requests SET status='review_closed',open_slot=NULL WHERE open_slot=1");
sql("UPDATE panel_subscription_config SET enabled=1,notify_admin=1,limit_1=50,limit_2=250,wallet_sol='So11111111111111111111111111111111111111112',wallet_doge='D6hLULEGDRbk86j58t5iWmeinqM6acA16V',wallet_usdt='0x1111111111111111111111111111111111111111' WHERE id=1");
$customerClaimsBefore=(int)$db->select_user('SELECT COUNT(*) AS n FROM crypto_deposit_transactions')['n'];
foreach(['SOL'=>150,'DOGE'=>.1,'USDT'=>.92] as $code=>$rate){
    sql("UPDATE crypto_assets SET current_rate_fiat={$rate},rate_currency_code='EUR',rate_updated_at=UTC_TIMESTAMP() WHERE code='{$code}'");
    sql("UPDATE app_settings SET admin_panel_subscription_paid_until=NULL");
    app_crypto_network_budget(0,0);
    $chain=app_panel_tiers_create($db,1,$code,$code==='USDT'?3:1);$chainId=(int)$chain['id'];
    sql("UPDATE panel_subscription_requests SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) WHERE id={$chainId}");
    $chain=app_panel_tiers_pending($db);$spec=app_panel_tiers_assets()[$code];$units=app_panel_tiers_amount_units($chain['amount_crypto'],$spec['decimals']);
    $hash=$code==='SOL'?str_repeat('7',88):($code==='USDT'?'0x':'').hash('sha256','end-to-end '.$code);
    tier_reset_scan($db);tier_asset_chain($chain,$hash,$units,'unfinalized');
    $scan=app_panel_tiers_process($db);
    ck($scan['accepted']===0&&app_panel_tiers_pending($db)['received_crypto']==='0.000000000',$code.' insufficient finality never credits funds');
    if($code!=='DOGE'){
        tier_reset_scan($db);tier_asset_chain($chain,$hash,$units,'failed');
        ck(app_panel_tiers_process($db)['accepted']===0,$code.' failed on-chain transfer does not renew access');
    }
    if($code==='USDT'){
        tier_reset_scan($db);tier_asset_chain($chain,$hash,$units,'counterfeit');
        ck(app_panel_tiers_process($db)['accepted']===0,'Counterfeit ERC-20 receipt cannot renew access');
    }
    tier_reset_scan($db);tier_asset_chain($chain,$hash,$units,'success');
    $scan=app_panel_tiers_process($db);
    ck($scan['accepted']===1,$code.' real decode/verification/settlement pipeline adds days automatically');
    $settled=$db->select_user("SELECT * FROM panel_subscription_requests WHERE id={$chainId}");
    ck((int)$settled['granted_days']===(int)$chain['quoted_days']&&$settled['new_paid_until']===app_panel_tiers_extended_date(null,(int)$chain['months']),$code.' grants exact full one/three-month days');
    $receipt=$db->select_user("SELECT id,body_html FROM outbound_email_queue WHERE dedupe_key='panel-access-paid:{$chainId}'");
    $assetReceiptIds[$code]=(int)$receipt['id'];
    ck(str_contains($receipt['body_html']??'',$hash)&&str_contains($receipt['body_html'],$settled['new_paid_until']),$code.' receipt contains its transaction and final access date');
    ck(app_panel_tiers_process($db)['accepted']===0,$code.' repeat scan never adds a second period');
    ck((int)$db->select_user('SELECT COUNT(*) AS n FROM crypto_deposit_transactions')['n']===$customerClaimsBefore,$code.' panel payment never appears in customer revenue');
}
sql("UPDATE panel_subscription_config SET enabled=0 WHERE id=1");
// Drive the actual email worker through loopback-only SMTP, never an external inbox.
function tier_test_smtp_start(string $capture): array{
    $process=proc_open([PHP_BINARY,__FILE__,'--smtp',$capture],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $port=(int)trim((string)fgets($pipes[1]));
    if($port<1)throw new RuntimeException('SMTP test failed to start: '.stream_get_contents($pipes[2]));
    return [$process,$pipes,$port];
}
$capture=app_crypto_runtime_dir().'/panel-receipt.eml';
[$smtp,$pipes,$port]=tier_test_smtp_start($capture);
sql("UPDATE app_settings SET smtp_host='127.0.0.1',smtp_port={$port},smtp_username='sender@example.invalid',smtp_password='isolated-fixture' WHERE id=1");
try{$delivery=app_email_process_queue($db,1,$assetReceiptIds['USDT']);}
finally{if(is_resource($smtp)){foreach($pipes as $pipe)fclose($pipe);proc_close($smtp);}}
ck($delivery['sent']===1,'Actual email worker delivers the panel receipt through isolated SMTP');
$mime=quoted_printable_decode((string)file_get_contents($capture));
ck(str_contains($mime,'admin@example.invalid')&&str_contains($mime,$settled['new_paid_until'])&&str_contains($mime,$hash),'Delivered receipt contains admin recipient, transaction and actual renewed date');
ck($db->select_user('SELECT status FROM outbound_email_queue WHERE id='.$assetReceiptIds['USDT'])['status']==='sent','Delivered email is marked sent');
ck(app_email_process_queue($db,1,$assetReceiptIds['USDT'])['processed']===0,'A sent panel receipt cannot be delivered a second time');
// Closed loopback port provides a deterministic SMTP failure without contacting any remote host.
$accessBeforeFailure=$db->select_user('SELECT admin_panel_subscription_paid_until AS d FROM app_settings WHERE id=1')['d'];
$failedDelivery=app_email_process_queue($db,1,$assetReceiptIds['SOL']);
$queuedRetry=$db->select_user('SELECT status,attempt_count FROM outbound_email_queue WHERE id='.$assetReceiptIds['SOL']);
ck($failedDelivery['failed']===1&&$queuedRetry['status']==='pending'&&(int)$queuedRetry['attempt_count']===1,'SMTP failure schedules a bounded retry rather than losing the receipt');
ck($db->select_user('SELECT admin_panel_subscription_paid_until AS d FROM app_settings WHERE id=1')['d']===$accessBeforeFailure,'SMTP delivery failure never reverses a paid access extension');
unlink($capture);
echo "PASS {$n} tier pricing, financial history, concurrency, strict payment, expiry and email assertions\n";
