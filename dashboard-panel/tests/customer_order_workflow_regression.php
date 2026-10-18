<?php
/** Disposable database only; no real accounts, chain requests or delivered emails. */
declare(strict_types=1);
if (strpos((string)getenv('DB_NAME'), '_test_') === false) throw new RuntimeException('Isolated _test_ database required.');
require_once dirname(__DIR__) . '/admin/bootstrap.php';
require_once dirname(__DIR__) . '/pages/profil/orders_add.php';
$db = Mysql_ks::get_instance();
$settings = ['active_sale'=>1,'sales_enabled'=>1,'active_trials'=>1,'default_currency_id'=>2];
if (($argv[1] ?? '') === '--pay') {
    $a = json_decode(base64_decode($argv[2]),true);
    echo json_encode(app_pay_order_from_customer_balance($db,$a[0],$a[1],$a[2],$settings,$a[3])); exit;
}
if (($argv[1] ?? '') === '--create') {
    $p = $db->select_user('SELECT *,price_amount AS price,duration_hours AS duration FROM products WHERE id=1');
    echo json_encode(orders_create_pending_order($db,(int)$argv[2],$p)); exit;
}
$checks = 0;
function ck(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
function sql(string $q): void { global $db; if (!$db->query($q)) throw new RuntimeException($db->error . ': ' . $q); }
function bal(int $c): float { global $db; return (float)$db->select_user("SELECT balance_amount FROM customers WHERE id={$c}")['balance_amount']; }
function orderFixture(int $id, string $status='active', float $balance=100): array {
    global $db;
    $c = $id+1000; $paid = $status==='pending_payment'?'unpaid':'paid';
    $expiry = date('Y-m-d H:i:s',strtotime($status==='expired'?'-2 days':'+5 days'));
    sql("INSERT INTO customers(id,email,password_hash,status,balance_amount) VALUES({$c},'checkout{$id}@example.invalid','fixture','active',{$balance})");
    sql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,fulfillment_status,expires_at,paid_at) VALUES({$id},{$c},1,10,2,'{$status}','{$paid}','".($paid==='paid'?'delivered':'pending')."','{$expiry}',".($paid==='paid'?'NOW()':'NULL').")");
    return [$c,$expiry];
}
function pay(int $id, string $expiry, int $product=2, ?array $options=null): array {
    global $db,$settings;
    return app_pay_order_from_customer_balance($db,$id+1000,$id,$product,$options ?? $settings,$expiry);
}
function race(string $mode, array $args): void {
    $children=[];
    for($i=0;$i<6;$i++) {
        $arg=$mode==='--pay'?base64_encode(json_encode($args)):(string)$args[0];
        $p=proc_open([PHP_BINARY,__FILE__,$mode,$arg],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$children[]=[$p,$pipes];
    }
    foreach($children as [$p,$pipes]) {
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);
        ck(proc_close($p)===0 && is_array(json_decode($out,true)),'Concurrent checkout completed: '.$out.$err);
    }
}
app_ensure_order_renewal_runtime_columns($db);app_ensure_customer_balance_runtime_table($db);app_ensure_sales_runtime_columns($db);app_ensure_customer_provider_visibility_runtime_table($db);
sql('SET FOREIGN_KEY_CHECKS=0');
sql("INSERT INTO product_providers(id,name,slug,is_active) VALUES(1,'Training','checkout-training',1),(2,'Other','checkout-other',1)");
sql("INSERT INTO products(id,provider_id,name,slug,price_amount,currency_id,duration_hours,is_active,product_type) VALUES(1,1,'Original','checkout-original',10,2,720,1,'subscription'),(2,1,'90 days','checkout-renewal',30,2,2160,1,'subscription'),(3,2,'Wrong provider','checkout-other',30,2,720,1,'subscription'),(4,1,'Two days','checkout-two-days',5,2,48,1,'subscription')");
// Initial payment reserves funds and leaves manual activation intact.
[$c,$expiry]=orderFixture(10,'pending_payment');
ck(!empty(pay(10,$expiry)['ok']),'Initial balance payment succeeds');
$o=$db->select_user('SELECT * FROM orders WHERE id=10');
ck(bal($c)===70.0 && $o['payment_status']==='paid' && $o['status']==='pending_payment' && $o['fulfillment_status']==='pending','Initial payment awaits admin activation');
ck((int)$o['product_id']===2 && (float)$o['total_amount']===30.0,'Selected package and amount are saved together');
ck(!empty(pay(10,$expiry)['already_applied']) && bal($c)===70.0,'Initial payment replay never debits twice');
$p=$db->select_user('SELECT *,price_amount AS price,duration_hours AS duration FROM products WHERE id=1');
ck(empty(orders_create_pending_order($db,$c,$p)['ok']),'Paid pending activation blocks another new order');
// A renewal paid from balance waits for provider work, including on expired orders.
foreach([20=>'active',21=>'expired'] as $id=>$status) {
    [$c,$expiry]=orderFixture($id,$status);
    ck(!empty(pay($id,$expiry)['ok']),'Balance renewal accepted for '.$status);
    $r=app_order_find_pending_renewal($db,$id);$o=$db->select_user("SELECT * FROM orders WHERE id={$id}");
    ck($r['status']==='paid_pending_activation' && $r['payment_request_type']==='balance' && $o['expires_at']===$expiry && $o['status']===$status,'Paid renewal does not change provider expiry or status');
    ck(admin_order_renewal_balance_reserved($db,$r) && bal($c)===70.0,'Admin recognizes the full reservation');
    ck(!empty(pay($id,$expiry)['already_applied']) && bal($c)===70.0,'Pending renewal retry is idempotent');
    ck(!empty(admin_extend_order($db,$id,2,true,'',$expiry,(int)$r['id'])['ok']) && bal($c)===70.0,'Admin applies paid extension without charging a second time');
    ck(empty(pay($id,$expiry)['ok']) && bal($c)===70.0,'Old checkout form cannot purchase another renewal after activation');
}
// Stale snapshot still blocks repeats for short packages within the renewal window.
[$c,$expiry]=orderFixture(22);ck(!empty(pay(22,$expiry,4)['ok']),'Short extension is reserved');
$r=app_order_find_pending_renewal($db,22);ck(!empty(admin_extend_order($db,22,4,true,'',$expiry,(int)$r['id'])['ok']),'Admin applies short extension');
ck(empty(pay(22,$expiry,4)['ok']) && bal($c)===95.0,'Short package replay cannot charge again');
// Invalid and unavailable situations leave the balance and expiry untouched.
[$c,$expiry]=orderFixture(30,'active',5);ck(empty(pay(30,$expiry)['ok']) && bal($c)===5.0 && !app_order_find_pending_renewal($db,30),'Insufficient balance leaves no orphan renewal');
[$c,$expiry]=orderFixture(31);ck(empty(pay(31,$expiry,3)['ok']) && bal($c)===100.0,'Other provider package rejected');
ck(empty(app_pay_order_from_customer_balance($db,$c,20,2,$settings,$expiry)['ok']),'Another customer order rejected');
ck(empty(pay(31,$expiry,2,['active_sale'=>0])['ok']),'Disabled sales reject checkout');
sql('UPDATE products SET is_active=0 WHERE id=2');ck(empty(pay(31,$expiry)['ok']) && bal($c)===100.0,'Disabled product rejected');sql('UPDATE products SET is_active=1 WHERE id=2');
sql("INSERT INTO customer_provider_visibility(customer_id,provider_id,is_visible) VALUES({$c},1,0)");ck(empty(pay(31,$expiry)['ok']),'Hidden provider rejected');sql("DELETE FROM customer_provider_visibility WHERE customer_id={$c}");
sql('UPDATE products SET is_trial=1 WHERE id=2');ck(empty(pay(31,$expiry,2,['active_sale'=>1,'active_trials'=>0])['ok']),'Disabled trial rejected');sql('UPDATE products SET is_trial=0 WHERE id=2');
sql("UPDATE orders SET payment_status='refunded' WHERE id=31");ck(empty(pay(31,$expiry)['ok']) && bal($c)===100.0,'Refunded order cannot be repaid as an extension');
// Failures after debit must roll back the entire checkout, not issue a best-effort refund.
foreach([40=>'orders',41=>'order_renewals'] as $id=>$table) {
    [$c,$expiry]=orderFixture($id,$table==='orders'?'pending_payment':'active');
    $constraint='checkout_failure_'.$id;
    $condition=$table==='orders'?"customer_id <> {$c} OR payment_status = 'unpaid'":"order_id <> {$id} OR status <> 'paid_pending_activation'";
    sql("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$condition})");
    ck(empty(pay($id,$expiry)['ok']),'Injected write failure is reported');
    ck(bal($c)===100.0 && (int)$db->select_user("SELECT COUNT(*) n FROM customer_balance_runtime_events WHERE customer_id={$c}")['n']===0,'Failure rolls back the debit and ledger');
    ck($db->select_user("SELECT expires_at FROM orders WHERE id={$id}")['expires_at']===$expiry && !app_order_find_pending_renewal($db,$id),'Failure preserves expiry and removes pending renewal');
    sql("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
    ck(!empty(pay($id,$expiry)['ok']) && bal($c)===70.0,'Retry after failure charges exactly once');
}
// Concurrent requests for the same order and separate orders share the customer lock.
[$c,$expiry]=orderFixture(50);race('--pay',[$c,50,2,$expiry]);
ck(bal($c)===70.0 && (int)$db->select_user('SELECT COUNT(*) n FROM order_renewals WHERE order_id=50')['n']===1,'Concurrent renewal requests reserve only once');
[$c,$expiry]=orderFixture(51,'pending_payment');race('--pay',[$c,51,2,$expiry]);
ck(bal($c)===70.0 && (int)$db->select_user("SELECT COUNT(*) n FROM customer_balance_runtime_events WHERE customer_id={$c}")['n']===1,'Concurrent initial checkout debits only once');
sql("INSERT INTO customers(id,email,password_hash,status,balance_amount) VALUES(1060,'create-checkout@example.invalid','fixture','active',100)");race('--create',[1060]);
ck((int)$db->select_user('SELECT COUNT(*) n FROM orders WHERE customer_id=1060')['n']===1,'Concurrent order creation reuses the pending order');
// One-off products keep no artificial subscription expiry.
sql("INSERT INTO products(id,provider_id,name,slug,price_amount,currency_id,duration_hours,is_active,product_type) VALUES(5,1,'Materials','checkout-materials',20,2,0,1,'product')");
[$c,$expiry]=orderFixture(61,'pending_payment');sql("UPDATE orders SET product_id=5,expires_at=NULL WHERE id=61");
ck(!empty(pay(61,'',5)['ok']) && bal($c)===80.0 && $db->select_user('SELECT expires_at FROM orders WHERE id=61')['expires_at']===null,'One-off product payment retains no-expiry semantics');
// Exercise the actual order handler and Smarty templates against hostile note text.
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap/tenant.php';
require_once dirname(__DIR__) . '/bootstrap/localization.php';
chdir(dirname(__DIR__));
$smarty = new \Smarty\Smarty;
$smarty->setTemplateDir(__DIR__ . '/../templates');
$compile = sys_get_temp_dir() . '/' . getenv('DB_NAME') . '-smarty';
if (!is_dir($compile)) mkdir($compile,0700,true);
$smarty->setCompileDir($compile);
$smarty->setErrorReporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$t = []; $site = 'orders'; $time_s = time(); $time = date('Y-m-d H:i:s');
$user = ['id'=>1060,'logged'=>true,'customer_type'=>'client'];
$_SESSION = ['__csrf_frontend'=>'checkout-csrf']; $_GET = [];
$_POST = ['order_note_save'=>1,'order_note_id'=>50,'order_note'=>'changed','_csrf'=>'invalid'];
$smarty->assign('t',$t); $smarty->assign('csrf_token','checkout-csrf');
ob_start();include 'pages/profil/orders.php';ob_end_clean();
ck($smarty->getTemplateVars('alert_error')!==null && $db->select_user('SELECT customer_note FROM orders WHERE id=50')['customer_note']===null,'Note update rejects invalid CSRF');
$created = $db->select_user('SELECT * FROM orders WHERE customer_id=1060 LIMIT 1');
$probe = '</textarea><img src=x onerror="alert(1)">';
$db->update_using_id(['customer_note'],[$probe],'orders',(int)$created['id']);
$_POST=[];$_GET=['del_order'=>(int)$created['id']];
ob_start();include 'pages/profil/orders.php';$html=ob_get_clean();
ck((int)$db->select_user("SELECT COUNT(*) n FROM orders WHERE id=".(int)$created['id'])['n']===1,'Legacy GET never deletes an unpaid order');
ck(strpos($html,$probe)===false && strpos($html,'&lt;/textarea&gt;')!==false,'Customer note is escaped in the table and readonly textarea');
ck(strpos($html,'orders-user-table__expiry-col')!==false && strpos($html,'orders-user-status-icon')===false,'Subscription list renders expiry column without a redundant price check');
$smarty->assign('order_catalog_product_type','credits');
$html = $smarty->fetch('profil/orders.tpl');
ck(strpos($html,$probe)===false && strpos($html,'orders-history-table')!==false,'Credits/history layout also escapes notes');
// Expired access still clearly shows that its renewal is already paid.
[$c,$expiry]=orderFixture(80,'expired');ck(!empty(pay(80,$expiry)['ok']),'Expired subscription renewal is reserved for display');
$_POST=[];$_GET=[];$user=['id'=>1080,'logged'=>true,'customer_type'=>'client'];
include 'pages/profil/orders_check.php';
$smarty->assign('order_catalog_product_type','subscription');
$html=$smarty->fetch('profil/orders.tpl');
$dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
$summary=$xpath->query("//div[contains(concat(' ',normalize-space(@class),' '),' orders-user-summary ')]")->item(0);
ck($summary && strpos($summary->textContent,'Waiting for extension')!==false,'Expired row shows paid renewal waiting for extension, without asking for another payment');
// Batch ticket flags stay customer-scoped, including cancelled historical tickets.
sql("INSERT INTO bank_accounts(id,currency_id,account_holder_name,bank_name) VALUES(1,2,'Fixture','Fixture')");
$orderForMap=(int)$created['id'];
sql("INSERT INTO bank_transfer_requests(id,customer_id,order_id,bank_account_id,requested_amount,currency_id,status) VALUES(900,1050,{$orderForMap},1,10,2,'cancelled')");
$_GET=[];$_POST=[];$user=['id'=>1060,'logged'=>true,'customer_type'=>'client'];
include 'pages/profil/orders_check.php';
ck(empty($wygrane[0]['has_generated_payment_request']),'Request map ignores another customer request');
sql('UPDATE bank_transfer_requests SET customer_id=1060 WHERE id=900');
include 'pages/profil/orders_check.php';
ck(!empty($wygrane[0]['has_generated_payment_request']) && empty($wygrane[0]['can_delete_order']),'Historical own ticket still blocks deleting its order');
// Run the real payment controller with no wallets/network assets in this test DB.
sql('UPDATE crypto_assets SET is_active=0');
function checkoutRequest(int $id, array $post): array {
    global $db,$settings,$smarty,$user,$t,$time_s;
    $smarty = new \Smarty\Smarty;
    $user = ['id'=>$id+1000,'logged'=>true,'customer_type'=>'client'];
    $_GET = ['payment'=>$id]; $_POST = array_merge(['id'=>$id],$post);
    include 'pages/profil/orders_payment.php';
    return ['error'=>$smarty->getTemplateVars('alert_error'),'redirect'=>$smarty->getTemplateVars('payment_redirect_url'),
        'can_request'=>$smarty->getTemplateVars('payment_can_request'),'notice'=>$smarty->getTemplateVars('payment_state_notice')];
}
[$c,$expiry]=orderFixture(70,'pending_payment');
$r=checkoutRequest(70,['create_balance_payment'=>1,'payment_product_id'=>2,'payment_expected_expires_at'=>$expiry,'_csrf'=>'invalid']);
ck($r['error']!==null && bal($c)===100.0,'Payment controller rejects invalid CSRF');
$r=checkoutRequest(70,['create_balance_payment'=>1,'payment_product_id'=>2,'_csrf'=>'checkout-csrf']);
ck($r['error']!==null && bal($c)===100.0,'Missing checkout snapshot asks for refresh without charging');
$r=checkoutRequest(70,['create_balance_payment'=>1,'payment_product_id'=>3,'payment_expected_expires_at'=>$expiry,'_csrf'=>'checkout-csrf']);
ck($r['error']!==null && bal($c)===100.0,'Forged package cannot silently fall back to another paid package');
$r=checkoutRequest(70,['create_balance_payment'=>1,'payment_product_id'=>2,'payment_expected_expires_at'=>$expiry,'_csrf'=>'checkout-csrf']);
ck(bal($c)===70.0 && $r['redirect']==='/order-payment-70','Real initial checkout reserves once and redirects to its status');
$key='payment:'.substr(hash('sha256',(string)getenv('DB_NAME')),0,16).':'.$c;
ck((int)$db->select_user("SELECT IS_FREE_LOCK('{$key}') free_lock")['free_lock']===1,'Controller releases nested customer payment locks');
[$c,$expiry]=orderFixture(71);
$r=checkoutRequest(71,['create_balance_payment'=>1,'payment_product_id'=>2,'payment_expected_expires_at'=>$expiry,'_csrf'=>'checkout-csrf']);
ck(bal($c)===70.0 && $r['redirect']==='/orders' && app_order_find_pending_renewal($db,71)['status']==='paid_pending_activation','Real renewal checkout waits for admin instead of extending access');
[$c,$expiry]=orderFixture(72,'pending_payment');sql('UPDATE product_providers SET is_active=0 WHERE id=1');
$r=checkoutRequest(72,['create_bank_payment'=>1,'payment_product_id'=>2,'_csrf'=>'checkout-csrf']);
ck(!$r['can_request'] && $r['notice']==='packages_unavailable' && bal($c)===100.0,'Disabled provider has no fabricated payment package');
sql('UPDATE product_providers SET is_active=1 WHERE id=1');sql("UPDATE orders SET status='cancelled' WHERE id=72");
$r=checkoutRequest(72,['create_crypto_payment'=>1,'payment_product_id'=>2,'_csrf'=>'checkout-csrf']);
ck(!$r['can_request'] && (int)$db->select_user('SELECT COUNT(*) n FROM crypto_deposit_requests WHERE order_id=72')['n']===0,'Cancelled order cannot generate a crypto payment request');
foreach (glob($compile.'/*') ?: [] as $f) if (is_file($f)) unlink($f);
rmdir($compile);
echo "PASS: {$checks} customer checkout workflow assertions\n";
