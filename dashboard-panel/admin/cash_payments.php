<?php
declare(strict_types=1);

/** Cash receipts are separate from blockchain requests. All accounting is atomic. */
function admin_cash_payment_schema(Mysql_ks $db): void
{
    if (!schema_object_exists($db, 'admin_cash_payments')) {
        if (!$db->query(file_get_contents(__DIR__.'/../migrations/045_admin_cash_payments.sql'))) throw new RuntimeException('Cash schema unavailable.');
        $GLOBALS['schema_object_exists_cache']['admin_cash_payments']=true;
    }
    app_ensure_customer_provider_visibility_runtime_table($db);
    app_ensure_customer_balance_runtime_table($db);
    app_ensure_order_renewal_runtime_columns($db);
    app_ensure_sales_runtime_columns($db);
}

function admin_cash_payment_orders(Mysql_ks $db, int $customerId): array
{
    app_ensure_customer_provider_visibility_runtime_table($db);
    $currency=(int)admin_chat_payment_currency_context($db)['currency_id'];
    $rows=$db->select_full_user("SELECT o.*,p.name AS product_name,p.provider_id,p.product_type,p.is_trial FROM orders o JOIN products p ON p.id=o.product_id
        WHERE o.customer_id={$customerId} AND o.currency_id={$currency} AND o.total_amount>0
        AND (COALESCE(p.provider_id,0)=0 OR (EXISTS(SELECT 1 FROM product_providers pp WHERE pp.id=p.provider_id AND pp.is_active=1)
          AND NOT EXISTS(SELECT 1 FROM customer_provider_visibility v WHERE v.customer_id=o.customer_id AND v.provider_id=p.provider_id AND v.is_visible=0)))
        AND ((o.status='pending_payment' AND o.payment_status='unpaid') OR (o.status IN ('active','expired') AND o.payment_status='paid'))
        ORDER BY o.id DESC LIMIT 100");
    return array_values(array_filter($rows?:[], static fn($o)=>($o['payment_status']==='unpaid') || app_order_can_self_extend($o)));
}

function admin_create_cash_payment(Mysql_ks $db, int $customerId, int $adminId, array $input): array
{
    $fail=static fn(string $message)=>['ok'=>false,'message'=>$message];
    $purpose=(string)($input['purpose']??''); $token=(string)($input['operation_key']??'');
    $orderId=(int)($input['order_id']??0); $productId=(int)($input['product_id']??0);
    $rawAmount=(string)($input['amount']??'');
    $applyRenewal=(string)($input['apply_renewal']??'0')==='1';
    if($purpose==='topup'){$orderId=0;$productId=0;}
    elseif($purpose==='new_order')$orderId=0;
    if (!in_array($purpose,['topup','new_order','existing_order'],true) || !preg_match('/^[a-f0-9-]{32,64}$/iD',$token)) return $fail('Nieprawidłowe dane operacji.');
    if ($purpose==='topup' && (!preg_match('/^\d+(?:\.\d{1,2})?$/D',$rawAmount) || (float)$rawAmount<1 || (float)$rawAmount>200)) return $fail('Podaj kwotę od 1 do 200.');
    $operation=hash('sha256',$adminId.':'.$token);
    $fingerprint=hash('sha256',json_encode([$customerId,$purpose,$orderId,$productId,$purpose==='topup'?number_format((float)$rawAmount,2,'.',''):'',$applyRenewal]));
    admin_cash_payment_schema($db);
    $currencyContext=admin_chat_payment_currency_context($db);
    $key='payment:'.substr(hash('sha256',(string)getenv('DB_NAME')),0,16).':'.$customerId;
    $lock=$db->select_user("SELECT GET_LOCK('".$db->escape($key)."',0) acquired");
    if(empty($lock['acquired']))return $fail('Inna operacja płatności jest w toku. Spróbuj ponownie za chwilę.');
    $tx=false;
    try {
        if(!$db->query('START TRANSACTION'))throw new RuntimeException('Nie udało się rozpocząć zapisu.');$tx=true;
        $prior=$db->select_user("SELECT * FROM admin_cash_payments WHERE operation_key='{$operation}' FOR UPDATE");
        if($prior) {
            if(!hash_equals($prior['request_hash'],$fingerprint))throw new RuntimeException('Ten formularz został już zapisany z innymi danymi. Otwórz nową płatność.');
            if(!$db->query('COMMIT'))throw new RuntimeException('Nie udało się odczytać wyniku płatności.');$tx=false;
            $priorRenewal=!empty($prior['renewal_id'])?$db->select_user('SELECT status FROM order_renewals WHERE id='.(int)$prior['renewal_id']):null;
            return ['ok'=>true,'cash'=>true,'already_applied'=>true,'receipt_id'=>(int)$prior['id'],'order_id'=>(int)$prior['order_id'],'customer_id'=>$customerId,'renewal'=>!empty($prior['renewal_id']),'renewal_applied'=>($priorRenewal['status']??'')==='applied'];
        }
        $customer=$db->select_user("SELECT * FROM customers WHERE id={$customerId} AND status='active' FOR UPDATE");
        if(!$customer)throw new RuntimeException('Klient jest niedostępny.');
        if(admin_quick_payment_has_open_receipt($db,$customerId) || $db->select_user("SELECT id FROM bank_transfer_requests WHERE customer_id={$customerId} AND status IN ('pending','pending_payment','awaiting_confirmation','awaiting_review') LIMIT 1"))
            throw new RuntimeException('Najpierw sprawdź lub anuluj oczekującą płatność klienta.');
        $currency=(int)$currencyContext['currency_id'];$renewal=false;$order=null;$product=null;$renewalId=0;
        if($purpose==='existing_order') {
            $db->select_user("SELECT id FROM orders WHERE id={$orderId} FOR UPDATE");
            foreach(admin_cash_payment_orders($db,$customerId) as $candidate)if((int)$candidate['id']===$orderId)$order=$candidate;
            if(!$order)throw new RuntimeException('Zamówienie jest niedostępne albo zmieniło status.');
            $renewal=app_order_can_self_extend($order);
            if($renewal && (string)($input['expected_expiry']??'')!==(string)($order['expires_at']??''))throw new RuntimeException('Data ważności zamówienia zmieniła się. Wybierz zamówienie ponownie.');
            if(app_order_find_pending_renewal($db,$orderId))throw new RuntimeException('Zamówienie ma już oczekujące przedłużenie. Najpierw je rozlicz.');
            if(!$renewal) {
                if(admin_find_latest_approved_order_payment($db,$orderId))throw new RuntimeException('Zamówienie ma już zatwierdzoną wpłatę. Sprawdź rozliczenie przed przyjęciem kolejnej.');
                $productId=(int)$order['product_id'];
            }
        }
        $amount=round((float)$rawAmount,2);
        if($purpose!=='topup') {
            $product=$db->select_user("SELECT * FROM products WHERE id={$productId} AND is_active=1 FOR UPDATE");
            if(!$product || !empty($product['is_trial']) || (int)$product['currency_id']!==$currency || !app_customer_provider_is_visible($db,$customerId,(int)$product['provider_id'])
                || ((int)$product['provider_id']>0 && !$db->select_user('SELECT id FROM product_providers WHERE is_active=1 AND id='.(int)$product['provider_id'])))throw new RuntimeException('Wybrany pakiet jest niedostępny.');
            if($renewal && ((int)$product['provider_id']!==(int)$order['provider_id'] || !app_order_product_can_extend($product)))throw new RuntimeException('Wybierz pakiet do przedłużenia od tego samego providera.');
            $amount=$purpose==='existing_order'&&!$renewal?(float)$order['total_amount']:(float)$product['price_amount'];
            if(!is_finite($amount)||$amount<=0)throw new RuntimeException('Nieprawidłowa cena pakietu.');
            if($purpose==='new_order') {
                $created=admin_create_order($db,['customer_id'=>$customerId,'product_id'=>$productId,'defer_expiry'=>true],$amount);
                if(empty($created['ok']))throw new RuntimeException('Nie udało się utworzyć zamówienia.');$orderId=(int)$created['order_id'];
            }
        }
        if(!$db->insert(['operation_key','request_hash','customer_id','order_id','purpose','amount','currency_id','created_by_admin_user_id'],
            [$operation,$fingerprint,$customerId,$orderId?:null,$purpose,$amount,$currency,$adminId],'admin_cash_payments'))throw new RuntimeException('Nie udało się zapisać pokwitowania.');
        $receiptId=(int)$db->id();$now=app_current_datetime_string();
        $note='Gotówka: '.admin_format_money_value($amount,(string)$currencyContext['currency_code']).($orderId?' — zamówienie #'.$orderId:' — doładowanie konta');
        $credit=admin_apply_customer_balance_runtime_event($db,$customerId,$amount,'credit','cash_payment',(string)$receiptId,$note,$adminId,'',true);
        if(empty($credit['ok']) || !empty($credit['already_applied']))throw new RuntimeException('Nie udało się zaksięgować wpłaty.');
        if($purpose!=='topup') {
            if($renewal) {
                $prepared=app_order_upsert_pending_renewal($db,$order,$product,$note);
                if(empty($prepared['ok']))throw new RuntimeException('Nie udało się zapisać przedłużenia.');$renewalId=(int)$prepared['renewal_id'];
            }
            if($renewal) {
                app_preserve_initial_order_sale($db,$orderId);
                $saved=$db->update_using_id(['status','paid_at','payment_request_type','payment_request_id'],['paid_pending_activation',$now,'cash',$receiptId],'order_renewals',$renewalId)
                    && $db->update_using_id(['renewal_id'],[$renewalId],'admin_cash_payments',$receiptId);
                if($applyRenewal) {
                    $debit=admin_apply_customer_balance_runtime_event($db,$customerId,$amount,'debit','order_extension','renewal:balance:'.$renewalId,$note,$adminId,'',true);
                    if(empty($debit['ok'])||!empty($debit['already_applied']))throw new RuntimeException('Przedłużenie zostało już rozliczone.');
                    $expiry=$prepared['target_expires_at'];
                    $saved=$saved && $db->update_using_id(['product_id','total_amount','currency_id','expires_at','status','payment_method'],[$productId,$amount,$currency,$expiry,'active','cash'],'orders',$orderId)
                        && $db->update_using_id(['status','applied_at'],['applied',$now],'order_renewals',$renewalId);
                    $note.=' — przedłużono do '.$expiry;
                } else $note.=' — środki na saldzie, przedłużenie czeka na realizację';
            } else {
                $saved=$db->update_using_id(['payment_status','payment_method','paid_at','fulfillment_status'],['paid','cash',$now,'pending'],'orders',$orderId);
                $note.=' — opłacone, oczekuje na realizację; środki na saldzie';
            }
            if(!$saved)throw new RuntimeException('Nie udało się zapisać zamówienia.');
        }
        if($orderId>0)app_panel_tiers_capture($db,$orderId);
        if(!$db->insert(['customer_id','admin_user_id','actor_type','action_key','description'],[$customerId,$adminId,'admin',$renewal&&$applyRenewal?'order_extended':'cash_payment_received',$note],'customer_activity_logs'))throw new RuntimeException('Nie udało się zapisać historii.');
        admin_activity_log($db,0,$adminId,'cash_payment_received',$note,'');
        if(!$db->query('COMMIT'))throw new RuntimeException('Nie udało się zakończyć zapisu.');$tx=false;
        return ['ok'=>true,'cash'=>true,'receipt_id'=>$receiptId,'order_id'=>$orderId,'customer_id'=>$customerId,'renewal'=>$renewal,'renewal_applied'=>$renewal&&$applyRenewal];
    } catch(Throwable $e) {
        if($tx)$db->query('ROLLBACK');
        return $fail($e instanceof RuntimeException?$e->getMessage():'Nie udało się zapisać płatności gotówką. Sprawdź historię przed ponowną próbą.');
    } finally {$db->select_user("SELECT RELEASE_LOCK('".$db->escape($key)."') released");}
}
