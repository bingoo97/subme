<?php
/** Isolated chat endpoint and payment regression suite; never run on application data. */
declare(strict_types=1);
if (strpos((string)getenv('DB_NAME'), '_test_') === false) { throw new RuntimeException('A separate _test_ database is required.'); }
require_once dirname(__DIR__) . '/admin/bootstrap.php';
require_once dirname(__DIR__) . '/admin/chat_security.php';
$db = Mysql_ks::get_instance();
if (($argv[1] ?? '') === '--endpoint') {
    $request = json_decode(base64_decode($argv[2]), true);
    admin_start_session();
    $_SESSION = ['admin_user_id' => $request['admin'] ?? 1, 'admin_csrf_token' => 'audit-csrf', 'admin_locale' => 'pl'];
    $_SERVER['REQUEST_METHOD'] = $request['method'] ?? 'GET';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_GET = $request['get'] ?? [];
    $_POST = $request['post'] ?? [];
    ob_start();
    register_shutdown_function(static function (): void {
        $body = ob_get_contents();
        if (ob_get_level()) { ob_end_clean(); }
        $error = error_get_last();
        if (session_status() !== PHP_SESSION_ACTIVE && session_id() !== '') { session_start(); }
        if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
        echo json_encode(['status' => http_response_code() ?: 200, 'body' => json_decode((string)$body, true), 'error' => $error]);
    });
    require dirname(__DIR__) . '/admin/chat.php';
    exit;
}
$checks = 0;
function audit(bool $condition, string $label): void { global $checks; if (!$condition) { throw new RuntimeException($label); } $checks++; }
function runsql(string $sql): void { global $db; if (!$db->query($sql)) { throw new RuntimeException($db->error . ': ' . $sql); } }
function countRows(string $table): int { global $db; return (int)$db->select_user("SELECT COUNT(*) n FROM {$table}")['n']; }
function endpoint(array $request): array {
    $process = proc_open([PHP_BINARY, __FILE__, '--endpoint', base64_encode(json_encode($request))], [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
    $result = json_decode($out, true);
    if (!is_array($result) || $code !== 0) { throw new RuntimeException('Endpoint failed: ' . $out . $err); }
    return $result;
}
function postChat(string $action, int $conversation = 1, array $extra = [], int $admin = 1): array {
    return endpoint(['admin'=>$admin,'method'=>'POST','post'=>array_merge(['action'=>$action,'conversation_id'=>$conversation,'_csrf'=>'audit-csrf'], $extra)]);
}
chat_ensure_message_interactions_runtime($db);
admin_ensure_user_runtime_columns($db);
admin_app_settings($db);
runsql('SET FOREIGN_KEY_CHECKS=0');
foreach (['support_message_reactions','support_messages','support_conversation_members','support_conversations','crypto_deposit_requests','crypto_wallet_assignments','crypto_wallet_addresses','orders','customers','admin_users','products'] as $table) { runsql("DELETE FROM {$table}"); }
runsql("INSERT INTO admin_roles(id,name,slug,access_level) VALUES(3,'No chat','audit-low',100) ON DUPLICATE KEY UPDATE access_level=100");
runsql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(1,1,'audit-owner','owner@example.invalid','unused'),(2,2,'audit-other','other@example.invalid','unused'),(3,3,'audit-low','low@example.invalid','unused')");
runsql("INSERT INTO customers(id,email,password_hash,status,locale_code) VALUES(1,'chat-one@example.invalid','unused','active','pl'),(2,'chat-two@example.invalid','unused','active','en'),(3,'chat-three@example.invalid','unused','active','pl'),(4,'chat-four@example.invalid','unused','active','pl')");
runsql("INSERT INTO support_conversations(id,conversation_type,customer_id) VALUES(1,'live_chat',1),(2,'group_chat',NULL),(3,'live_chat',2),(4,'live_chat',3),(5,'live_chat',4)");
runsql("INSERT INTO support_conversation_members(conversation_id,participant_key,participant_type,admin_user_id,invite_status) VALUES(2,'admin:1','admin',1,'accepted')");
runsql("INSERT INTO support_messages(id,conversation_id,sender_type,admin_user_id,message_body,message_type,audio_path) VALUES(1,2,'admin',1,'private','audio','/uploads/chat/audio/missing-audit.webm')");
runsql("UPDATE app_settings SET maintenance_mode=0, crypto_payments_enabled=1, demo_messenger_showcase_enabled=0, default_currency_id=2, customer_global_group_enabled=0, crypto_wallet_shared_assignments_enabled=0, site_url='http://example.invalid'");
runsql("UPDATE crypto_assets SET current_rate_fiat=50000,rate_currency_code='EUR',rate_updated_at=UTC_TIMESTAMP(),is_active=IF(code='BTC',1,0)");
$btc = (int)$db->select_user("SELECT id FROM crypto_assets WHERE code='BTC'")['id'];
runsql("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,status,network_code) VALUES(1,{$btc},'audit-wallet-one','available','BTC'),(2,{$btc},'audit-wallet-two','available','BTC'),(3,{$btc},'audit-wallet-three','available','BTC')");
runsql("INSERT INTO products(id,name,slug,price_amount,currency_id,duration_hours,is_active) VALUES(1,'Audit package','audit-package',18,2,720,1),(2,'Other currency','audit-usd',20,1,720,1)");
foreach(admin_chat_mutating_actions() as $action) {
    audit(endpoint(['get'=>['action'=>$action,'conversation_id'=>2]])['status']===405, 'GET must not mutate: '.$action);
    audit(endpoint(['method'=>'POST','post'=>['action'=>$action,'conversation_id'=>2]])['status']===419, 'CSRF required: '.$action);
}
audit(countRows('support_messages')===1 && countRows('support_conversations')===5, 'Rejected requests must not mutate data');
audit(endpoint(['admin'=>0,'get'=>['action'=>'inbox_state']])['status']===401,'Unauthenticated denied');
audit(endpoint(['admin'=>3,'get'=>['action'=>'inbox_state']])['status']===403,'Insufficient role denied');
foreach(['fetch','quick_replies'] as $action) { audit(endpoint(['admin'=>2,'get'=>['action'=>$action,'conversation_id'=>2]])['status']===403,'Foreign private group denied: '.$action); }
audit(postChat('delete_conversation',2,[],2)['status']===403,'Foreign private group cannot be deleted');
audit(endpoint(['admin'=>2,'get'=>['action'=>'voice_file','message_id'=>1]])['status']===404,'Foreign group voice denied');
runsql("INSERT INTO support_conversation_members(conversation_id,participant_key,participant_type,admin_user_id,invite_status) VALUES(2,'admin:2','admin',2,'pending')");
audit(endpoint(['admin'=>2,'get'=>['action'=>'fetch','conversation_id'=>2]])['status']===403,'Pending invitation does not expose history');
audit(postChat('send',2,['message'=>'blocked'],2)['status']===403,'Pending member cannot post');
audit(endpoint(['admin'=>1,'get'=>['action'=>'fetch','conversation_id'=>2]])['status']===200,'Accepted member can fetch');
audit(postChat('send',1,['message'=>'   '])['status']===422,'Empty message rejected');
audit(postChat('upload',1)['status']===422,'Missing attachment rejected');
audit(postChat('voice_upload',1)['status']===422,'Missing or disabled voice upload rejected');
audit(postChat('send',1,['message'=>str_repeat('x',40001)])['status']===422,'Oversize message rejected');
$response=postChat('send',1,['message'=>'<script>alert(1)</script> & " audit']);
audit($response['status']===200 && !empty($response['body']['ok']), 'Sending succeeds');
$html=(string)($response['body']['html'] ?? $response['body']['conversation_html'] ?? '');
if($html==='') { $html=json_encode($response['body']); }
audit(strpos($html,'<script>alert(1)</script>')===false && strpos($html,'&lt;script&gt;')!==false,'Message HTML is escaped');
$messageId=(int)$db->select_user('SELECT MAX(id) id FROM support_messages')['id'];
audit(postChat('edit_message',3,['message_id'=>$messageId,'message'=>'wrong'])['status']===422,'Message from another conversation cannot be edited');
audit(postChat('edit_message',1,['message_id'=>$messageId,'message'=>'edited'])['status']===200,'Edit succeeds');
audit(postChat('delete_message',1,['message_id'=>$messageId])['status']===200,'Delete succeeds');
foreach(['127.0.0.1','10.0.0.1','172.16.0.1','192.168.1.1','169.254.169.254','100.64.0.1','224.0.0.1','::1','::ffff:127.0.0.1','::ffff:7f00:1','fe80::1','fc00::1'] as $ip) { audit(!chat_public_ip_allowed($ip),'Private address blocked: '.$ip); }
audit(chat_public_ip_allowed('8.8.8.8'),'Public address permitted');
foreach(['javascript:alert(1)','JaVaScRiPt:alert(1)','data:text/html,unsafe','vbscript:unsafe'] as $url) {
    $html=chat_format_message_html(app_chat_card_encode(['kind'=>'payment_request','logo_url'=>$url,'button_url'=>$url,'button_label'=>'click']));
    audit(strpos($html,'href=')===false && strpos($html,'src=')===false,'Unsafe card URL rejected: '.$url);
}
$html=chat_format_message_html(app_chat_card_encode(['kind'=>'payment_request','button_url'=>'/payments_crypto#tickets','button_label'=>'Pay']));
audit(strpos($html,'href="/payments_crypto#tickets"')!==false,'Valid payment card link is retained');

foreach(['http://127.0.0.1/','http://169.254.169.254/','file:///etc/passwd','http://[::1]/'] as $url) { audit(chat_preview_document_fetch($url)===null,'Unsafe preview blocked: '.$url); }
foreach(['0','-1','201','NaN','INF','10oops','1e9999'] as $amount) { audit(admin_chat_payment_amount_normalize($amount)===0.0,'Invalid payment amount: '.$amount); }
audit(admin_chat_payment_amount_normalize('10,50')===10.5,'Decimal comma preserved');
$preview=admin_chat_crypto_payment_preview($db,1,$btc,18,[]);
audit(!empty($preview['ok']), 'Crypto preview succeeds: '.json_encode($preview));
audit($preview['currency_code']==='EUR' && abs((float)$preview['crypto_amount']-.00036)<1e-10,'Crypto preview uses correct rate and currency');
$otherCurrency=admin_chat_create_crypto_payment_request($db,1,1,$btc,18,1,[],2);
audit(empty($otherCurrency['ok']) && countRows('crypto_deposit_requests')===0,'Foreign currency product rejected');
audit(empty(admin_chat_create_crypto_payment_request($db,1,2,$btc,18,1,[])['ok']),'Conversation/customer mismatch rejected');
$result=admin_chat_create_crypto_payment_request($db,1,1,$btc,18,1,[],1);
audit(!empty($result['ok']), 'Crypto request with order succeeds: '.json_encode($result));
$request=$db->select_user('SELECT * FROM crypto_deposit_requests WHERE id='.(int)$result['request_id']);
audit((int)$request['customer_id']===1 && (int)$request['order_id']===(int)$result['order_id'] && (int)$result['order_id']>0,'Request links to correct customer and order');
audit((float)$request['requested_fiat_amount']===18.0 && (int)$request['fiat_currency_id']===2 && abs((float)$request['requested_crypto_amount']-.00036)<1e-10,'Saved amounts match preview');
audit(countRows('crypto_wallet_assignments')===1, 'One wallet assignment created');
audit(empty(admin_chat_create_crypto_payment_request($db,1,1,$btc,18,1,[],1)['ok']) && countRows('crypto_deposit_requests')===1,'Repeated request does not duplicate');
// Force a chat write failure after assigning a new wallet: everything must roll back.
$before=[countRows('crypto_deposit_requests'),countRows('crypto_wallet_assignments'),countRows('orders')];
runsql("ALTER TABLE support_messages ADD CONSTRAINT audit_chat_fail CHECK (conversation_id <> 3)");
$failed=admin_chat_create_crypto_payment_request($db,3,2,$btc,18,1,[],1);
runsql('ALTER TABLE support_messages DROP CHECK audit_chat_fail');
audit(empty($failed['ok']), 'Injected chat failure reported');
audit($before===[countRows('crypto_deposit_requests'),countRows('crypto_wallet_assignments'),countRows('orders')], 'Failed request leaves no partial assignment/request/order');
$before=[countRows('crypto_deposit_requests'),countRows('crypto_wallet_assignments'),countRows('orders'),countRows('support_messages')];
runsql("ALTER TABLE orders ADD CONSTRAINT audit_link_fail CHECK (order_reference NOT LIKE '%-2')");
try {
    $failed=admin_chat_create_crypto_payment_request($db,3,2,$btc,18,1,[],1);
    audit(empty($failed['ok']), 'Injected order creation failure reported');
    audit($before===[countRows('crypto_deposit_requests'),countRows('crypto_wallet_assignments'),countRows('orders'),countRows('support_messages')], 'Order creation failure rolls back all four records');
} finally { runsql('ALTER TABLE orders DROP CHECK audit_link_fail'); }
// Two independent processes emulate two rapid submissions with separate sessions.
$workers=[];
for($i=0;$i<2;$i++) {
    $request=['admin'=>1,'method'=>'POST','post'=>['action'=>'create_crypto_payment_request','conversation_id'=>3,'asset_id'=>$btc,'amount'=>18,'product_id'=>1,'_csrf'=>'audit-csrf']];
    $process=proc_open([PHP_BINARY,__FILE__,'--endpoint',base64_encode(json_encode($request))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $workers[]=[$process,$pipes];
}
$successes=0;
foreach($workers as [$process,$pipes]) {
    $response=json_decode(stream_get_contents($pipes[1]),true);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    audit($exit===0 && is_array($response), 'Concurrent endpoint returned valid JSON: '.$err);
    if(!empty($response['body']['ok'])) $successes++;
}
audit($successes===1,'Exactly one concurrent crypto request succeeds');
audit(countRows('crypto_deposit_requests')===2 && countRows('crypto_wallet_assignments')===2 && countRows('orders')===2,'Concurrent submissions do not duplicate orders, requests or assignments');
$request=$db->select_user('SELECT * FROM crypto_deposit_requests WHERE customer_id=2 LIMIT 1');
runsql('UPDATE crypto_deposit_requests SET expires_at=NULL WHERE id='.(int)$request['id']);
audit(admin_customer_has_pending_crypto_payment($db,2),'Open request without expiry still blocks duplication');
runsql("UPDATE crypto_deposit_requests SET expires_at=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE customer_id=1");
$replacement=admin_chat_create_crypto_payment_request($db,1,1,$btc,10.5,1,[],1);
audit(!empty($replacement['ok']), 'Expired request can be replaced without reassigning the wallet');
audit((float)$db->select_user('SELECT total_amount FROM orders WHERE id='.(int)$replacement['order_id'])['total_amount']===10.5,'Manually adjusted request amount is also the order amount');
audit(countRows('crypto_wallet_assignments')===2, 'Expired replacement retains one assignment per customer');
$presets=admin_chat_payment_product_presets($db,admin_chat_payment_currency_context($db));
audit(count($presets)===1 && (int)$presets[0]['product_id']===1,'Product presets never mix currencies');
// Two customers competing for the final wallet cannot share or steal its assignment.
$beforeRequests=countRows('crypto_deposit_requests');$workers=[];
foreach([4,5] as $conversation) {
    $request=['admin'=>1,'method'=>'POST','post'=>['action'=>'create_crypto_payment_request','conversation_id'=>$conversation,'asset_id'=>$btc,'amount'=>10,'_csrf'=>'audit-csrf']];
    $process=proc_open([PHP_BINARY,__FILE__,'--endpoint',base64_encode(json_encode($request))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $workers[]=[$process,$pipes];
}
$successes=0;
foreach($workers as [$process,$pipes]) {
    $response=json_decode(stream_get_contents($pipes[1]),true);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    audit($exit===0 && is_array($response),'Wallet race returned controlled JSON: '.$err);
    if(!empty($response['body']['ok'])) $successes++;
}
audit($successes===1 && countRows('crypto_deposit_requests')===$beforeRequests+1,'Only one customer receives the final wallet');
$invalid=$db->select_user("SELECT COUNT(*) n FROM crypto_deposit_requests r INNER JOIN crypto_wallet_assignments a ON a.id=r.wallet_assignment_id WHERE r.customer_id<>a.customer_id");
audit((int)$invalid['n']===0,'A request never points to another customer assignment');
runsql("UPDATE crypto_assets SET is_active=1,current_rate_fiat=.8,rate_updated_at=UTC_TIMESTAMP() WHERE code='USDT'");
$usdt=(int)$db->select_user("SELECT id FROM crypto_assets WHERE code='USDT'")['id'];
runsql("INSERT INTO customers(id,email,password_hash,status) VALUES(5,'usdt@example.invalid','unused','active')");
runsql("INSERT INTO support_conversations(id,customer_id,conversation_type) VALUES(6,5,'live_chat')");
runsql("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,network_code,memo_tag,status) VALUES(4,{$usdt},'audit-token-wallet','ethereum','audit-tag','available')");
$preview=admin_chat_crypto_payment_preview($db,5,$usdt,16,[]);
audit(!empty($preview['ok']) && $preview['network_code']==='ethereum' && $preview['memo_tag']==='audit-tag','Token preview preserves network and memo');
audit(abs((float)$preview['crypto_amount']-20)<1e-8,'Token amount uses its fiat exchange rate');
$result=admin_chat_create_crypto_payment_request($db,6,5,$usdt,16,1,[]);
audit(!empty($result['ok']), 'Token payment request succeeds');
$card=app_chat_card_decode((string)$db->select_user('SELECT message_body FROM support_messages WHERE conversation_id=6 ORDER BY id DESC LIMIT 1')['message_body']);
$values=array_column($card['fields'],'value');
audit(in_array('Ethereum (ERC20)',$values,true) && in_array('audit-tag',$values,true),'Delivered card includes the network and memo');
$assignedPreview=admin_chat_crypto_payment_preview($db,5,$usdt,16,[]);
audit($assignedPreview['memo_tag']==='audit-tag','Existing wallet assignment retains its memo');
runsql("INSERT INTO support_conversations(id,conversation_type) VALUES(10,'live_chat')");
runsql("INSERT INTO support_messages(id,conversation_id,sender_type,admin_user_id,message_body) VALUES(1000,10,'admin',1,'audit removable')");
runsql("INSERT INTO support_message_reactions(message_id,actor_key,actor_type,admin_user_id,reaction_code) VALUES(1000,'admin:1','admin',1,'like')");
audit(postChat('delete_conversation',10)['status']===200,'Authorized conversation deletion succeeds');
audit((int)$db->select_user('SELECT COUNT(*) n FROM support_message_reactions WHERE message_id=1000')['n']===0,'Deleting a conversation also removes reactions');
fwrite(STDOUT, "PASS: {$checks} chat/security/payment assertions\n");
