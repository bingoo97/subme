<?php
/** Real approval/archive handlers; the imported harness requires a disposable _test_ DB. */
declare(strict_types=1);
require __DIR__.'/dashboard_sales_regression.php';
app_crypto_network_budget(0,0);
app_ensure_customer_runtime_columns($db);
app_ensure_payment_request_runtime_columns($db);
admin_app_settings($db);
sql('DELETE FROM orders');sql('DELETE FROM order_renewals');sql('DELETE FROM customer_balance_runtime_events');
sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(1,1,'sales-audit','sales-audit@example.invalid','unused') ON DUPLICATE KEY UPDATE role_id=1");
sql("UPDATE app_settings SET default_currency_id=2,crypto_wallet_shared_assignments_enabled=0,demo_messenger_showcase_enabled=0");
sql("INSERT INTO bank_accounts(id,currency_id,account_holder_name,bank_name) VALUES(300,2,'Sales fixture','Sales fixture')");
$btc=(int)$db->select_user("SELECT id FROM crypto_assets WHERE code='BTC'")['id'];
$asOf=new DateTimeImmutable('+30 seconds');
function paymentSales():array {global $db,$asOf;return admin_dashboard_period_metrics($db,$asOf);}
function assertSaleCount(int $count,float $amount,string $label):void {
    foreach(paymentSales() as $period=>$row){
        check($row['paid_orders']===$count,$label.' '.$period.' count');
        check(abs((float)($row['revenue_by_currency']['EUR']??0)-$amount)<.001,$label.' '.$period.' revenue');
    }
}
foreach([300,301,302,303] as $id){
    sql("INSERT INTO customers(id,email,password_hash,status,balance_amount) VALUES({$id},'sales{$id}@example.invalid','unused','active',0)");
    if($id!==303)sql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,fulfillment_status,created_at) VALUES({$id},{$id},2,30,2,'pending_payment','unpaid','pending','2025-01-01 12:00:00')");
    if($id===301){
        sql("INSERT INTO bank_transfer_requests(id,customer_id,order_id,bank_account_id,requested_amount,currency_id,status,requested_at) VALUES({$id},{$id},{$id},300,30,2,'awaiting_review','2025-01-01 12:00:00')");
    }else{
        $order=$id===303?'NULL':(string)$id;
        sql("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,network_code,status) VALUES({$id},{$btc},'sales-fixture-wallet-{$id}','bitcoin','assigned')");
        sql("INSERT INTO crypto_wallet_assignments(id,wallet_address_id,customer_id,status) VALUES({$id},{$id},{$id},'active')");
        sql("INSERT INTO crypto_deposit_requests(id,customer_id,order_id,crypto_asset_id,wallet_address_id,wallet_assignment_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,status,requested_at,expires_at) VALUES({$id},{$id},{$order},{$btc},{$id},{$id},30,2,50000,.0006,'pending','2025-01-01 12:00:00',DATE_ADD(NOW(),INTERVAL 1 HOUR))");
    }
}
assertSaleCount(0,0,'Unpaid requests');
foreach(['crypto'=>300,'bank'=>301] as $type=>$id){
    $result=admin_payment_accept_with_order($db,$type,$id,1,'sales-audit');
    check(!empty($result['ok']),$type.' approval succeeded: '.json_encode($result));
    $order=$db->select_user("SELECT status,payment_status,paid_at FROM orders WHERE id={$id}");
    check($order['payment_status']==='paid'&&$order['status']==='pending_payment',$type.' sale exists before fulfilment');
    check(substr($order['paid_at'],0,10)===date('Y-m-d'),$type.' uses approval date, not old request/creation date');
    $n=$type==='crypto'?1:2;
    assertSaleCount($n,30*$n,$type.' approved and not archived');
    $before=paymentSales();
    if($type==='crypto')check(empty(admin_payment_apply_quick_action($db,$type,$id,'archive',1)['ok']),'Unfulfilled crypto order cannot be archived prematurely');
    sql("UPDATE orders SET status='active',fulfillment_status='delivered',expires_at='2030-01-01 00:00:00' WHERE id={$id}");
    check(paymentSales()===$before,$type.' fulfilment does not change sale');
    $result=admin_payment_apply_quick_action($db,$type,$id,'archive',1,'sales-audit');
    check(!empty($result['ok']),$type.' archive succeeded: '.json_encode($result));
    check(paymentSales()===$before,$type.' archive preserves all four periods and amounts');
    check($db->select_user("SELECT paid_at FROM orders WHERE id={$id}")['paid_at']===$order['paid_at'],$type.' archive does not move payment date');
    check(!empty(admin_payment_apply_quick_action($db,$type,$id,'archive',1)['ok']),$type.' repeated archive succeeds');
    check(paymentSales()===$before,$type.' repeated archive never duplicates sale');
}
$result=admin_payment_accept_with_order($db,'crypto',302,1,'sales-audit',['received_crypto_amount'=>'.0004','received_fiat_amount'=>'20']);
check(!empty($result['ok'])&&!empty($result['underpaid_order']),'Short payment accepted but order remains underpaid');
assertSaleCount(2,60,'Underpayment is not a fully paid sale');
$result=admin_payment_accept_with_order($db,'crypto',303,1,'sales-audit');
check(!empty($result['ok']),'Standalone balance top-up accepted');
assertSaleCount(2,60,'Balance top-up does not double-count future product purchase');
// Paid renewals count before applying; archival must preserve both ledgers and sales.
foreach(['bank'=>304,'crypto'=>305] as $type=>$id){
    $order=$type==='bank'?301:300;
    sql("INSERT INTO order_renewals(id,order_id,customer_id,product_id,price_amount,currency_id,duration_hours,status,requested_at,payment_request_type,payment_request_id) VALUES({$id},{$order},{$order},2,30,2,2160,'pending_payment','2025-01-01 12:00:00','{$type}',{$id})");
    if($type==='bank'){
        sql("INSERT INTO bank_transfer_requests(id,customer_id,order_id,bank_account_id,requested_amount,currency_id,status,requested_at) VALUES({$id},{$order},{$order},300,30,2,'awaiting_review','2025-01-01 12:00:00')");
    }else{
        sql("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,network_code,status) VALUES({$id},{$btc},'sales-renewal-wallet','bitcoin','assigned')");
        sql("INSERT INTO crypto_wallet_assignments(id,wallet_address_id,customer_id,status) VALUES({$id},{$id},{$order},'active')");
        sql("INSERT INTO crypto_deposit_requests(id,customer_id,order_id,crypto_asset_id,wallet_address_id,wallet_assignment_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,status,requested_at,expires_at) VALUES({$id},{$order},{$order},{$btc},{$id},{$id},30,2,50000,.0006,'pending','2025-01-01 12:00:00',DATE_ADD(NOW(),INTERVAL 1 HOUR))");
    }
    check(!empty(admin_payment_accept_with_order($db,$type,$id,1)['ok']),$type.' renewal approval succeeds');
    $n=$type==='bank'?3:4;
    assertSaleCount($n,30*$n,$type.' paid renewal awaiting fulfilment');
    $before=paymentSales();
    $paidAt=$db->select_user("SELECT paid_at FROM order_renewals WHERE id={$id}")['paid_at'];
    sql("UPDATE order_renewals SET status='applied',applied_at=NOW() WHERE id={$id}");
    $ledger=$db->select_user("SELECT COUNT(*) n,SUM(amount) total FROM customer_balance_runtime_events WHERE customer_id={$order}");
    $balance=$db->select_user("SELECT balance_amount FROM customers WHERE id={$order}");
    $archived=admin_payment_apply_quick_action($db,$type,$id,'archive',1);
    check(!empty($archived['ok']),$type.' renewal request archived: '.json_encode($archived));
    check(!empty(admin_payment_apply_quick_action($db,$type,$id,'archive',1)['ok']),$type.' repeated renewal archive succeeds');
    check(paymentSales()===$before,$type.' applying and archiving renewal preserves all periods');
    check($db->select_user("SELECT paid_at FROM order_renewals WHERE id={$id}")['paid_at']===$paidAt,$type.' renewal approval date preserved');
    check($db->select_user("SELECT COUNT(*) n,SUM(amount) total FROM customer_balance_runtime_events WHERE customer_id={$order}")===$ledger,$type.' archive never books another credit or debit');
    check($db->select_user("SELECT balance_amount FROM customers WHERE id={$order}")===$balance,$type.' archive preserves balance');
}
$series=admin_dashboard_sales_series($db,30,$asOf);
check(end($series)['paid_orders']===$before['daily']['paid_orders'],'Chart count matches daily card');
check(end($series)['revenue_by_currency']===$before['daily']['revenue_by_currency'],'Chart amount matches daily card');
echo "PASS: {$checks} sales and payment lifecycle checks\n";
