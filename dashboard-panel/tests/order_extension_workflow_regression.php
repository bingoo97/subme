<?php
/** Destructive fixtures are permitted only in a dedicated, disposable test database. */
declare(strict_types=1);
if (strpos((string)getenv('DB_NAME'), '_test_') === false) throw new RuntimeException('Isolated _test_ database required.');
require_once dirname(__DIR__) . '/admin/bootstrap.php';
$db = Mysql_ks::get_instance();
if (($argv[1] ?? '') === '--extend') {
    $args = json_decode(base64_decode($argv[2]), true);
    echo json_encode(admin_extend_order($db, $args[0], 2, true, '', $args[1], $args[2] ?? 0));
    exit;
}
$checks = 0;
function ck(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; }
function sql(string $query): void { global $db; if (!$db->query($query)) throw new RuntimeException($db->error . ': ' . $query); }
function fixture(int $id, float $balance = 0, bool $expired = false): array {
    global $db;
    $customer = $id + 1000;
    $expiry = $expired ? date('Y-m-d H:i:s', strtotime('-10 days')) : '2030-11-17 21:31:00';
    $status = $expired ? 'expired' : 'active';
    sql("INSERT INTO customers(id,email,password_hash,balance_amount) VALUES({$customer},'fixture{$id}@example.invalid','fixture',{$balance})");
    sql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,fulfillment_status,expires_at,paid_at) VALUES({$id},{$customer},1,10,2,'{$status}','paid','delivered','{$expiry}',NOW())");
    return [$id, $expiry, $customer];
}
function balance(int $customer): float { global $db; return (float)$db->select_user("SELECT balance_amount FROM customers WHERE id={$customer}")['balance_amount']; }
function renewal(int $id, int $order, string $status = 'paid_pending_activation'): array {
    global $db;
    $customer = $order + 1000;
    sql("INSERT INTO order_renewals(id,order_id,customer_id,product_id,price_amount,currency_id,duration_hours,status,target_expires_at,paid_at,payment_request_type,payment_request_id) VALUES({$id},{$order},{$customer},2,30,2,2160,'{$status}','2031-02-17 21:31:00',NOW(),'bank',{$id})");
    return $db->select_user("SELECT * FROM order_renewals WHERE id={$id}");
}
function race(int $order, string $expiry, int $renewal = 0): void {
    global $db;
    $children = [];
    for ($i = 0; $i < 6; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, '--extend', base64_encode(json_encode([$order,$expiry,$renewal]))], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
        $children[] = [$process,$pipes];
    }
    foreach ($children as [$process,$pipes]) {
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) fclose($pipe);
        ck(proc_close($process) === 0 && is_array(json_decode($output,true)), 'Concurrent request completed: ' . $error . $output);
    }
}
app_ensure_sales_runtime_columns($db); app_ensure_order_renewal_runtime_columns($db); app_ensure_customer_balance_runtime_table($db);
sql('SET FOREIGN_KEY_CHECKS=0');
sql("INSERT INTO product_providers(id,name,slug) VALUES(1,'Test provider','test-provider'),(2,'Other provider','other-provider')");
sql("INSERT INTO products(id,provider_id,name,slug,price_amount,currency_id,duration_hours,is_active) VALUES(1,1,'Original','fixture-original',10,2,720,1),(2,1,'Extension','fixture-extension',30,2,2160,1),(3,2,'Wrong provider','fixture-wrong',30,2,2160,1)");
sql("INSERT INTO bank_accounts(id,currency_id,account_holder_name,bank_name) VALUES(1,2,'Fixture','Fixture')");
// Real payment finalization credits and reserves exactly once.
[$id,$expiry,$customer] = fixture(10);
$r = renewal(10,$id,'pending_payment');
sql("INSERT INTO bank_transfer_requests(id,customer_id,order_id,bank_account_id,requested_amount,currency_id,status,approved_at) VALUES(10,{$customer},{$id},1,30,2,'approved',NOW())");
$approved = admin_finalize_successful_payment($db,'bank',10);
ck(!empty($approved['ok']), 'Bank renewal approval succeeds: '.json_encode($approved));
ck(balance($customer) === 0.0, 'Approved payment is reserved, leaving no spare balance.');
$r = $db->select_user('SELECT * FROM order_renewals WHERE id=10');
ck($r['status'] === 'paid_pending_activation' && admin_order_renewal_balance_reserved($db,$r), 'Paid renewal is recognized as settled.');
$context = admin_order_pending_renewal_context($db,admin_order_find($db,$id));
ck($context['balance_reserved'] === true, 'UI uses settled state for the correct renewal.');
ck(!empty(admin_finalize_successful_payment($db,'bank',10)['ok']) && balance($customer) === 0.0, 'Repeated approval does not add or debit money again.');
sql("UPDATE orders SET support_note='Existing private note' WHERE id={$id}");
$result = admin_extend_order($db,$id,2,false,'',$expiry,10,'Provider extended manually');
ck($db->select_user("SELECT support_note FROM orders WHERE id={$id}")['support_note']==="Existing private note\nProvider extended manually",'Paid renewal appends the private support annotation');
ck(!empty($result['ok']) && balance($customer) === 0.0, 'Settled renewal applies with zero balance and no second debit.');
ck((int)$db->select_user("SELECT COUNT(*) n FROM customer_balance_runtime_events WHERE customer_id={$customer} AND direction='debit'")['n']===1,'Exactly one renewal debit exists.');
ck(!empty(admin_extend_order($db,$id,2,false,'',$expiry,10,'Provider extended manually')['already_applied']), 'Repeated form submission returns already applied.');
ck((int)$db->select_user("SELECT COUNT(*) n FROM order_renewals WHERE order_id={$id}")['n']===1,'Repeat does not create a new renewal.');
ck(substr_count($db->select_user("SELECT support_note FROM orders WHERE id={$id}")['support_note'],'Provider extended manually')===1,'Repeated paid renewal does not duplicate the private note');
// Unrelated top-up is available balance, not proof that an extension was settled.
[$id,$expiry,$customer] = fixture(20);
ck(!empty(app_apply_customer_balance_runtime_event($db,$customer,100,'credit','payment_approval','topup:20','Fixture')['ok']), 'Top-up succeeds.');
ck(!empty(admin_extend_order($db,$id,2,true,'',$expiry,0,'Private ordinary annotation')['ok']) && balance($customer)===70.0, 'Ordinary top-up pays the extension once from balance.');
ck(empty(admin_extend_order($db,$id,2,true,'',$expiry)['ok']) && balance($customer)===70.0, 'Stale ordinary form cannot pay or extend twice.');
// Low balance must never silently turn into a free extension.
[$id,$expiry,$customer] = fixture(30,10);
ck(empty(admin_extend_order($db,$id,2,true,'',$expiry)['ok']) && balance($customer)===10.0,'Insufficient balance fails without debit.');
ck($db->select_user("SELECT expires_at FROM orders WHERE id={$id}")['expires_at']===$expiry,'Insufficient balance preserves expiry.');
ck(!empty(admin_extend_order($db,$id,2,false,'',$expiry)['ok']) && balance($customer)===10.0,'Explicit free extension is still available.');
ck((float)$db->select_user("SELECT price_amount FROM order_renewals WHERE order_id={$id}")['price_amount']===0.0,'Explicit free extension is not recorded as paid sales revenue.');
// Fault injection: balance, original sale snapshot, expiry and renewal must roll back together.
foreach (['orders','order_renewals'] as $table) {
    $id = $table === 'orders' ? 40 : 41;
    [$id,$expiry,$customer] = fixture($id,100);
    $condition = $table === 'orders' ? "customer_id <> {$customer} OR expires_at = '{$expiry}'" : "order_id <> {$id}";
    sql("ALTER TABLE {$table} ADD CONSTRAINT fixture_reject_extension CHECK ({$condition})");
    try {
        ck(empty(admin_extend_order($db,$id,2,true,'',$expiry)['ok']), 'Injected '.$table.' failure is reported.');
        ck(balance($customer)===100.0,'Injected '.$table.' failure rolls back balance.');
        ck($db->select_user("SELECT expires_at FROM orders WHERE id={$id}")['expires_at']===$expiry,'Injected failure preserves expiry.');
        ck((int)$db->select_user("SELECT COUNT(*) n FROM customer_balance_runtime_events WHERE customer_id={$customer} AND direction='debit'")['n']===0,'Injected failure leaves no debit event.');
        ck((int)$db->select_user("SELECT COUNT(*) n FROM order_renewals WHERE order_id={$id}")['n']===0,'Injected failure leaves no renewal.');
    } finally { sql("ALTER TABLE {$table} DROP CHECK fixture_reject_extension"); }
    ck(!empty(admin_extend_order($db,$id,2,true,'',$expiry)['ok']) && balance($customer)===70.0,'Retry after rollback charges only once.');
}
// A balance-paid reservation uses its own canonical key and must not be charged again.
[$id,$expiry,$customer] = fixture(50);
$r=renewal(50,$id);
sql("INSERT INTO customer_balance_runtime_events(customer_id,source_type,source_key,direction,amount) VALUES({$customer},'order_extension','renewal:balance:50','debit',30)");
ck(admin_order_renewal_balance_reserved($db,$r),'Customer-balance renewal reservation is recognized.');
ck(!empty(admin_extend_order($db,$id,2,true,'',$expiry,50)['ok']) && balance($customer)===0.0,'Paid balance reservation is consumed without another debit even if a stale UI says Yes.');
// A false or partial reservation is never accepted as full payment.
[$id,$expiry,$customer] = fixture(60,100); $r=renewal(60,$id);
sql("INSERT INTO customer_balance_runtime_events(customer_id,source_type,source_key,direction,amount) VALUES({$customer},'order_extension','renewal:60','debit',5)");
ck(!admin_order_renewal_balance_reserved($db,$r),'Partial reservation is not settled.');
ck(empty(admin_extend_order($db,$id,2,false,'',$expiry,60)['ok']) && balance($customer)===100.0,'Partial reservation blocks activation without another debit.');
[$id,$expiry,$customer] = fixture(70,100);
ck(empty(admin_extend_order($db,$id,3,true,'',$expiry)['ok']) && balance($customer)===100.0,'Other provider rejected without touching balance.');
ck(empty(admin_extend_order($db,$id,2,true,'2000-01-01T12:00',$expiry)['ok']),'Invalid expiry rejected before debit.');
// Concurrent requests from the same form only extend and debit once.
[$id,$expiry,$customer] = fixture(80,100); race($id,$expiry);
ck(balance($customer)===70.0,'Concurrent ordinary requests debit exactly once.');
ck((int)$db->select_user("SELECT COUNT(*) n FROM order_renewals WHERE order_id={$id}")['n']===1,'Concurrent ordinary requests create one renewal.');
[$id,$expiry,$customer] = fixture(90); $r=renewal(90,$id);
sql("INSERT INTO customer_balance_runtime_events(customer_id,source_type,source_key,direction,amount) VALUES({$customer},'order_extension','renewal:90','debit',30)");
race($id,$expiry,90);
ck(balance($customer)===0.0,'Concurrent paid requests do not debit again.');
ck((int)$db->select_user("SELECT COUNT(*) n FROM order_renewals WHERE order_id={$id}")['n']===1,'Concurrent paid requests do not create another renewal.');
// Optional support annotation commits with the extension, never on a failure.
[$id,$expiry,$customer]=fixture(95,100);
sql("UPDATE orders SET support_note='Earlier annotation' WHERE id={$id}");
ck(!empty(admin_extend_order($db,$id,2,true,'',$expiry,0,"New private line\nSecond line")['ok']),'Ordinary extension saves annotation');
ck($db->select_user("SELECT support_note FROM orders WHERE id={$id}")['support_note']==="Earlier annotation\nNew private line\nSecond line",'Existing private note and multiline annotation are preserved');
ck(!str_contains((string)$db->select_user("SELECT renewal_note FROM order_renewals WHERE order_id={$id}")['renewal_note'],'New private line'),'Private annotation is not copied into renewal history');
[$id,$expiry,$customer]=fixture(96,0);
ck(empty(admin_extend_order($db,$id,2,true,'',$expiry,0,'Should not save')['ok']),'Insufficient balance rejects extension with annotation');
ck(trim((string)$db->select_user("SELECT support_note FROM orders WHERE id={$id}")['support_note'])==='','Failed extension does not write annotation');
[$id,$expiry,$customer]=fixture(97,100);
ck(empty(admin_extend_order($db,$id,2,true,'',$expiry,0,str_repeat('a',4001))['ok'])&&balance($customer)===100.0,'Oversized annotation is rejected before any debit');
echo "PASS: {$checks} order extension workflow checks\n";
