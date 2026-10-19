<?php
declare(strict_types=1);
function admin_crypto_quick_labels(?string $locale = null): array
{
    return ($locale ?? admin_current_locale())==='pl'
        ? ['order_action_status'=>'Do realizacji', 'manual_approved'=>'Płatność zatwierdzona', 'purpose_order'=>'Za zamówienie', 'purpose_topup'=>'Doładowanie konta', 'auto_approved'=>'Zatwierdzono automatycznie', 'order_next'=>'Dokończ realizację u providera i zatwierdź zamówienie.', 'order_done'=>'Zamówienie nie wymaga dalszej realizacji.', 'topup_done'=>'Saldo klienta zostało zasilone.', 'open_order'=>'Dokończ zamówienie', 'title'=>'Płatności krypto','intro'=>'Ostatnie płatności klientów','detected'=>'Wykryto wpłatę · do zatwierdzenia','remaining'=>'Pozostało: {minutes} min','deadline_passed'=>'Termin minął','new'=>'Nowe','expired'=>'Wygasłe','cancelled'=>'Anulowane','payout'=>'Do wypłaty','empty'=>'Brak płatności w tej kategorii','empty_help'=>'Gdy pojawią się płatności klientów, zobaczysz je tutaj.','loading'=>'Pobieranie płatności…','error'=>'Nie udało się pobrać płatności. Spróbuj ponownie.','show_hints'=>'Pokazuj podpowiedzi','all'=>'Wszystkie płatności','close'=>'Zamknij','retry'=>'Odśwież','payout_help'=>'To zatwierdzone wpłaty klientów. Jeśli zamówienie wymaga realizacji, najpierw dokończ je u providera i oznacz jako zrealizowane lub aktywne w Zamówieniach. Doładowania konta nie wymagają realizacji zamówienia. Następnie wypłać środki na swój główny portfel i przenieś płatność do archiwum.','review'=>'Do weryfikacji','details'=>'Zobacz płatność','auto_accept_help'=>'Automatyczna akceptacja jest wyłączona. Możesz ją włączyć w ustawieniach, aby jednoznacznie dopasowane wpłaty były zatwierdzane automatycznie.','auto_accept_link'=>'Przejdź do automatycznej akceptacji','new_title'=>'Płatności oczekujące','new_help'=>'Sprawdź wpłatę przed akceptacją. Po zatwierdzeniu płatność pojawi się w sekcji „Do wypłaty”. Kursy kryptowalut są zmienne. Jeśli minęło 60 minut, a klient nie wysłał jeszcze środków i nadal chce zapłacić, skorzystaj z opcji „Odnów płatność”, aby ponownie przeliczyć kwotę według aktualnego kursu. Przed odnowieniem upewnij się w explorerze, że wcześniejsza wpłata nie dotarła.','expired_title'=>'Termin płatności minął','expired_help'=>'Sprawdź, czy klient wysłał środki, zanim ponowisz prośbę o wpłatę. Szczegóły znajdziesz po otwarciu płatności. Jeśli od kilku godzin na Twój portfel nie wpłynęły żadne środki, przenieś płatność do anulowanych, aby jeszcze odczekać, albo usuń ją całkowicie.','cancelled_title'=>'Płatności anulowane','cancelled_help'=>'Anulowane płatności są automatycznie usuwane po 72h od oznaczenia jako anulowane. Przed usunięciem sprawdź w explorerze, czy na dany adres na pewno nie wpłynęły żadne środki.','received_title'=>'Płatność otrzymana','delete'=>'Usuń','delete_confirm'=>'Usunąć tę płatność? Najpierw sprawdź adres w explorerze. Tej operacji nie można cofnąć.','verify'=>'Szczegóły płatności','accept'=>'Zaakceptuj płatność','renew'=>'Odnów płatność','cancel'=>'Anuluj płatność','actions'=>'Opcje płatności','archive'=>'Przenieś do Archiwum','explorer'=>'Sprawdź w explorerze','archive_help'=>'Przenieś do archiwum dopiero po wypłacie na swój główny portfel.']
        : ['order_action_status'=>'Fulfilment required', 'manual_approved'=>'Payment approved', 'purpose_order'=>'Order payment', 'purpose_topup'=>'Balance top-up', 'auto_approved'=>'Approved automatically', 'order_next'=>'Complete fulfilment with the provider and confirm the order.', 'order_done'=>'No further order fulfilment is required.', 'topup_done'=>'Customer balance has been credited.', 'open_order'=>'Complete order', 'title'=>'Crypto payments','intro'=>'Recent customer payments','detected'=>'Payment detected · review required','remaining'=>'Remaining: {minutes} min','deadline_passed'=>'Deadline passed','new'=>'New','expired'=>'Expired','cancelled'=>'Cancelled','payout'=>'To withdraw','empty'=>'No payments in this category','empty_help'=>'Customer payments will appear here.','loading'=>'Loading payments…','error'=>'Unable to load payments. Please try again.','show_hints'=>'Show hints','all'=>'All payments','close'=>'Close','retry'=>'Refresh','payout_help'=>'These are approved customer payments. For an unfinished order, first complete fulfilment with the provider and mark the order as fulfilled or active in Orders. Balance top-ups do not require order fulfilment. Then transfer funds to your main wallet and archive the payment.','review'=>'Review required','details'=>'View payment','auto_accept_help'=>'Automatic acceptance is off. Enable it in settings to automatically approve clearly matched transfers.','auto_accept_link'=>'Open automatic acceptance settings','new_title'=>'Awaiting payment','new_help'=>'Check the transfer before accepting it. Accepted payments appear under To withdraw. Cryptocurrency rates change. After 60 minutes, if the customer has not sent any funds and still wants to pay, use Renew payment to recalculate the amount at the current rate. Before renewing, check the explorer to make sure no earlier payment was received.','expired_title'=>'Payment deadline passed','expired_help'=>'Check whether the customer sent funds before asking them to pay again. Open the payment for details. If no funds have reached your wallet for several hours, move the payment to Cancelled to wait longer, or delete it permanently.','cancelled_title'=>'Cancelled payments','cancelled_help'=>'Cancelled payments are automatically deleted 72 hours after being marked as cancelled. Before deletion, check the address in the explorer to make sure no funds were received.','received_title'=>'Payment received','delete'=>'Delete','delete_confirm'=>'Delete this payment? Check the address in the explorer first. This cannot be undone.','verify'=>'Payment details','accept'=>'Accept payment','renew'=>'Renew payment','cancel'=>'Cancel payment','actions'=>'Payment options','archive'=>'Move to archive','explorer'=>'Check explorer','archive_help'=>'Archive only after transferring the funds to your main wallet.'];
}
function admin_crypto_quick_conditions(): array
{
    return [
        'new'=>"r.status IN ('pending','awaiting_confirmation','awaiting_review') AND (r.expires_at IS NULL OR r.expires_at>NOW())",
        'expired'=>"(r.status='expired' OR (r.status IN ('pending','awaiting_confirmation','awaiting_review') AND r.expires_at<=NOW()))",
        'cancelled'=>"r.status='cancelled'",
        'payout'=>"r.status='approved'",
    ];
}
function admin_crypto_quick_counts(Mysql_ks $db): array
{
    $parts = [];
    foreach (admin_crypto_quick_conditions() as $filter => $condition) {
        $parts[] = "COALESCE(SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END),0) AS `{$filter}`";
    }
    // Only actionable requests, without joins, histories or external API calls.
    $row = $db->select_user("SELECT " . implode(',', $parts) . " FROM crypto_deposit_requests r WHERE r.status IN ('pending','awaiting_confirmation','awaiting_review','expired','approved','cancelled')");
    $counts = [];
    foreach (array_keys(admin_crypto_quick_conditions()) as $filter) {
        $counts[$filter] = max(0, (int)($row[$filter] ?? 0));
    }
    return $counts;
}
function admin_crypto_quick_priority(array $counts): string
{
    foreach (['new','expired','payout','cancelled'] as $filter) {
        if ((int)($counts[$filter] ?? 0) > 0) return $filter;
    }
    return 'new';
}
function admin_crypto_quick_rows(Mysql_ks $db, string $filter, int $limit = 10, bool $detectedFirst = false, int $requestId = 0): array
{
    $limit=max(1,min(10,$limit));
    $order=$detectedFirst&&$filter==='new'?"CASE WHEN r.status='awaiting_review' AND r.received_crypto_amount>0 THEN 0 ELSE 1 END,r.id DESC":"r.id DESC";
    $conditions=admin_crypto_quick_conditions();
    if(!isset($conditions[$filter]))throw new InvalidArgumentException('Invalid filter.');
    $where=$conditions[$filter].($requestId>0?" AND r.id=".(int)$requestId:"");
    return $db->select_full_user("SELECT r.id,r.customer_id,r.order_id,r.exchange_rate,r.status,r.requested_at,r.expires_at,r.confirmed_at,r.requested_crypto_amount,r.received_crypto_amount,r.requested_fiat_amount,r.received_fiat_amount,c.public_handle,c.email,a.code AS asset_code,f.code AS currency_code,w.address AS wallet_address,w.network_code,
        EXISTS(SELECT 1 FROM crypto_deposit_transactions tx WHERE tx.deposit_request_id=r.id AND tx.status='auto_accepted') AS auto_accepted,
        (orders.id IS NOT NULL AND (".admin_topbar_order_task_where_sql().")) AS order_needs_action
        FROM (SELECT r.id,r.customer_id,r.order_id,r.exchange_rate,r.status,r.requested_at,r.expires_at,r.confirmed_at,r.requested_crypto_amount,r.received_crypto_amount,r.requested_fiat_amount,r.received_fiat_amount,r.crypto_asset_id,r.fiat_currency_id,r.wallet_address_id FROM crypto_deposit_requests r WHERE {$where} ORDER BY {$order} LIMIT {$limit}) r
        LEFT JOIN customers c ON c.id=r.customer_id
        LEFT JOIN orders ON orders.id=r.order_id
        LEFT JOIN crypto_wallet_addresses w ON w.id=r.wallet_address_id
        INNER JOIN crypto_assets a ON a.id=r.crypto_asset_id
        INNER JOIN currencies f ON f.id=r.fiat_currency_id ORDER BY {$order}");
}
function admin_crypto_quick_html(array $rows, string $filter, ?array $labels = null, string $csrf = '', array $messages = []): string
{
    $l=$labels ?? admin_crypto_quick_labels();
    if($filter==='new'&&!$messages) $messages=admin_load_messages(admin_current_locale());
    ob_start();
    if(!$rows){ ?>
        <div class="admin-crypto-quick__empty"><span><i class="bi bi-wallet2" aria-hidden="true"></i></span><strong><?php echo admin_e($l['empty']); ?></strong><p><?php echo admin_e($l['empty_help']); ?></p></div>
    <?php }
    foreach($rows as $row){
        $orderId=(int)($row['order_id']??0);$needsAction=!empty($row['order_needs_action']);$autoAccepted=!empty($row['auto_accepted']);
        $id=(int)$row['id'];$code=(string)$row['asset_code'];$paid=$filter==='payout';
        $detected=$filter==='new'&&(string)$row['status']==='awaiting_review'&&(float)($row['received_crypto_amount']??0)>0;
        $crypto=($paid||$detected)?($row['received_crypto_amount']??$row['requested_crypto_amount']):$row['requested_crypto_amount'];
        $fiat=($paid||$detected)?($row['received_fiat_amount']??$row['requested_fiat_amount']):$row['requested_fiat_amount'];
        $handle=trim((string)($row['public_handle']??''));$identity=$handle!==''?'@'.$handle:(string)($row['email']??'#'.$row['customer_id']);
        $status=in_array($row['status'],['awaiting_review','awaiting_confirmation'],true)&&$filter==='new'?$l['review']:$l[$filter];
        if($paid&&$orderId>0&&$needsAction)$status=$l['order_action_status'];
        $expiresTimestamp=0;
        if($filter==='new'&&trim((string)($row['expires_at']??''))!==''){
            try{$expiresTimestamp=app_timestamp_from_runtime_datetime((string)$row['expires_at']);}catch(Exception $e){}
        }
        $receiptMessage=$paid?($l[$autoAccepted?'auto_approved':'manual_approved'].'. '.$l[$orderId>0?'purpose_order':'purpose_topup'].($orderId>0?' #'.$orderId:'').'. '.$l[$orderId>0?($needsAction?'order_next':'order_done'):'topup_done']):'';
        $receiptHeading=$paid?$l[$autoAccepted?'auto_approved':'manual_approved']:'';
        $receiptPurpose=$l[$orderId>0?'purpose_order':'purpose_topup'].($orderId>0?' #'.$orderId:'');
        $receiptDetails=$paid?$l[$orderId>0?($needsAction?'order_next':'order_done'):'topup_done']:'';
        $rawAmount=(string)$crypto;$amount=str_contains($rawAmount,'.')?rtrim(rtrim($rawAmount,'0'),'.'):$rawAmount;
        ?>
        <article class="admin-crypto-quick__item admin-crypto-quick__item--actions" data-receipt-message="<?php echo admin_e($receiptMessage); ?>" data-receipt-title="<?php echo admin_e($receiptHeading); ?>" data-receipt-purpose="<?php echo admin_e($receiptPurpose); ?>" data-receipt-order-id="<?php echo $orderId>0?$orderId:0; ?>" data-receipt-details="<?php echo admin_e($receiptDetails); ?>" data-payment-kind="<?php echo $orderId>0?'order':'topup'; ?>" data-auto-accepted="<?php echo $paid&&$autoAccepted?'1':'0'; ?>" data-order-needs-action="<?php echo $needsAction?'1':'0'; ?>"><a class="admin-crypto-quick__main" href="/admin/?page=payments&amp;payment_type=crypto&amp;payment_id=<?php echo $id; ?>" aria-label="<?php echo admin_e($l['details'].' #'.$id); ?>">
            <img src="<?php echo admin_e(app_crypto_logo_by_code($code)); ?>" width="30" height="30" alt="" loading="lazy">
            <div class="admin-crypto-quick__identity"><div class="admin-crypto-quick__title"><strong title="<?php echo admin_e($identity); ?>"><?php echo admin_e($identity); ?></strong><span class="admin-crypto-quick__status is-<?php echo $paid&&$needsAction?'action':admin_e($filter); ?>"><?php echo admin_e($status); ?></span><?php if($filter==='new'&&!$detected): ?><span class="admin-crypto-quick__waiting" aria-hidden="true"></span><?php endif; ?></div><small><?php echo admin_e(admin_format_datetime_text((string)($paid?($row['confirmed_at']??$row['requested_at']):$row['requested_at']))); ?></small>
                <?php if($detected): ?><span class="admin-crypto-quick__detected"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?php echo admin_e($l['detected']); ?></span><?php endif; ?>
                <?php if($expiresTimestamp>0&&!$detected): ?><span class="admin-crypto-quick__remaining" data-crypto-quick-expires="<?php echo $expiresTimestamp; ?>" data-remaining-label="<?php echo admin_e($l['remaining']); ?>" data-expired-label="<?php echo admin_e($l['deadline_passed']); ?>"><?php echo admin_e($expiresTimestamp>time()?str_replace('{minutes}',(string)(int)ceil(($expiresTimestamp-time())/60),$l['remaining']):$l['deadline_passed']); ?></span><?php endif; ?>
            </div>
            <div class="admin-crypto-quick__amount"><strong><?php echo admin_e($amount.' '.$code); ?></strong><small><?php echo admin_e(admin_format_money_value($fiat,(string)$row['currency_code'])); ?></small></div>
        </a>
        <?php $explorer=admin_crypto_wallet_explorer_url($code,(string)($row['network_code']??''),(string)($row['wallet_address']??'')); ?>
            <button type="button" class="admin-chat-inbox__header-more admin-crypto-quick__more" data-crypto-payment-more aria-expanded="false" aria-controls="cryptoQuickActions<?php echo $id; ?>" aria-label="<?php echo admin_e($l['actions'].' · '.$identity); ?>" title="<?php echo admin_e($l['actions']); ?>"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
            <div class="admin-crypto-quick__actions" data-crypto-payment-actions id="cryptoQuickActions<?php echo $id; ?>" role="group" aria-label="<?php echo admin_e($l['actions']); ?>" hidden>
                <?php if($filter==='new'):
                    $payload=admin_build_crypto_accept_payload(array_merge($row,[
                        'payment_type'=>'crypto','requested_amount_value'=>$row['requested_fiat_amount'],
                        'requested_amount_crypto'=>$row['requested_crypto_amount'],'customer_email'=>$row['email']??'',
                    ]),$messages);
                ?>
                    <form method="post" action="/admin/?page=payments">
                        <input type="hidden" name="_csrf" value="<?php echo admin_e($csrf); ?>">
                        <input type="hidden" name="payment_type" value="crypto">
                        <input type="hidden" name="payment_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="admin_payment_quick_action" value="1">
                        <input type="hidden" name="quick_action" value="accept">
                        <input type="hidden" name="payment_type_filter" value="crypto">
                        <input type="hidden" name="payment_scope" value="new">
                        <button type="button" class="admin-crypto-quick__action admin-crypto-quick__action--accept" data-crypto-quick-accept data-admin-crypto-accept-payload="<?php echo admin_e(json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)); ?>"><i class="bi bi-check-circle" aria-hidden="true"></i><?php echo admin_e($l['accept']); ?><i class="bi bi-chevron-double-right" aria-hidden="true"></i></button>
                    </form>
                <?php endif; ?>
                <?php if($paid&&$orderId>0&&$needsAction): ?><a class="admin-crypto-quick__action admin-crypto-quick__action--renew" href="/admin/?page=orders&amp;order_id=<?php echo $orderId; ?>"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i><?php echo admin_e($l['open_order']); ?></a><?php endif; ?>
                <?php if(!$paid): ?><a class="admin-crypto-quick__action admin-crypto-quick__action--details" href="/admin/?page=payments&amp;payment_type=crypto&amp;payment_id=<?php echo $id; ?>"><i class="bi <?php echo $filter==='new'?'bi-shield-check':'bi-receipt'; ?>" aria-hidden="true"></i><?php echo admin_e($filter==='new'?$l['verify']:$l['details']); ?></a><?php endif; ?>
                <?php
                $actions = [];
                if($paid&&!($orderId>0&&$needsAction)) $actions[]=['archive','bi-archive','archived'];
                if(in_array((string)$row['status'],['expired','cancelled'],true)) $actions[]=['renew','bi-arrow-clockwise','new'];
                if(in_array($filter,['new','expired'],true)) $actions[]=['cancel','bi-x-circle','cancelled'];
                foreach($actions as [$action,$icon,$scope]): ?>
                    <form method="post" action="/admin/?page=payments" data-crypto-quick-action><input type="hidden" name="_csrf" value="<?php echo admin_e($csrf); ?>"><input type="hidden" name="payment_type" value="crypto"><input type="hidden" name="payment_id" value="<?php echo $id; ?>"><input type="hidden" name="admin_payment_quick_action" value="1"><input type="hidden" name="quick_action" value="<?php echo $action; ?>"><input type="hidden" name="payment_type_filter" value="crypto"><input type="hidden" name="payment_scope" value="<?php echo $scope; ?>"><button type="submit" class="admin-crypto-quick__action admin-crypto-quick__action--<?php echo $action; ?><?php echo $action==='archive'?' btn btn-dark':''; ?>"<?php if($action==='archive'): ?> title="<?php echo admin_e($l['archive_help']); ?>"<?php endif; ?>><i class="bi <?php echo $icon; ?>" aria-hidden="true"></i><?php echo admin_e($l[$action]); ?></button></form>
                <?php endforeach; ?>
                <?php if($explorer!==''): ?><a class="admin-crypto-quick__action admin-crypto-quick__action--explorer" href="<?php echo admin_e($explorer); ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> <?php echo admin_e($l['explorer']); ?></a><?php endif; ?>
                <?php if(in_array($filter,['expired','cancelled'],true)): ?>
                    <form method="post" action="/admin/?page=payments" data-crypto-quick-action><input type="hidden" name="_csrf" value="<?php echo admin_e($csrf); ?>"><input type="hidden" name="payment_type" value="crypto"><input type="hidden" name="payment_id" value="<?php echo $id; ?>"><input type="hidden" name="admin_payment_quick_action" value="1"><input type="hidden" name="quick_action" value="delete"><input type="hidden" name="payment_type_filter" value="crypto"><input type="hidden" name="payment_scope" value="<?php echo admin_e($filter); ?>"><button type="submit" class="admin-crypto-quick__action admin-crypto-quick__action--delete" data-admin-confirm="<?php echo admin_e($l['delete_confirm']); ?>"><i class="bi bi-trash3" aria-hidden="true"></i><?php echo admin_e($l['delete']); ?></button></form>
                <?php endif; ?>
            </div>
            <div class="admin-crypto-quick__context">
                <?php if($orderId>0): ?>
                    <a class="admin-crypto-quick__purpose" href="/admin/?page=orders&amp;order_id=<?php echo $orderId; ?>"><i class="bi bi-receipt" aria-hidden="true"></i><?php echo admin_e($l['purpose_order']); ?> #<?php echo $orderId; ?></a>
                <?php else: ?>
                    <span class="admin-crypto-quick__purpose"><i class="bi bi-wallet2" aria-hidden="true"></i><?php echo admin_e($l['purpose_topup']); ?></span>
                <?php endif; ?>
                <?php if($paid&&$autoAccepted): ?><span class="admin-crypto-quick__auto"><i class="bi bi-check-circle" aria-hidden="true"></i><?php echo admin_e($l['auto_approved']); ?></span><?php endif; ?>
                <?php if($paid): ?>
                    <?php if($orderId>0&&$needsAction): ?><a class="admin-crypto-quick__next-step" href="/admin/?page=orders&amp;order_id=<?php echo $orderId; ?>"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span><?php echo admin_e($l['order_next']); ?></span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    <?php else: ?><span class="admin-crypto-quick__completed"><?php echo admin_e($l[$orderId>0?'order_done':'topup_done']); ?></span><?php endif; ?>
                <?php endif; ?>
            </div>
        </article>
    <?php }
    return (string)ob_get_clean();
}

/** Reminders exclude cancelled requests: there is no immediate admin action for them. */
function admin_crypto_reminder_filter(array $counts): ?string
{
    foreach (['new', 'expired', 'payout'] as $filter) {
        if ((int)($counts[$filter] ?? 0) > 0) return $filter;
    }
    return null;
}
function admin_crypto_reminder_copy(?string $locale = null): array
{
    return ($locale ?? admin_current_locale()) === 'pl' ? [
        'order' => ['title'=>'Dokończ opłacone zamówienie', 'text'=>'Wpłata została już zatwierdzona. Dokończ realizację lub przedłużenie u providera, a następnie zatwierdź zamówienie w Zamówieniach; archiwizacją płatności zajmij się po wypłacie środków.', 'action'=>'Dokończ zamówienie'],
        'new' => ['title'=>'Sprawdź nowe płatności', 'text'=>'Sprawdź w explorerze, czy wpłata dotarła, i zatwierdź ją po weryfikacji. Jeśli dotyczy zamówienia, następnie dokończ jego realizację w Zamówieniach.', 'action'=>'Przejdź do płatności'],
        'expired' => ['title'=>'Sprawdź wygasłe płatności', 'text'=>'Zweryfikuj w explorerze, czy klient nie wysłał środków z opóźnieniem. Jeśli nie ma wpłaty, anuluj płatność — zostanie usunięta po 72h, dając czas na ponowne sprawdzenie — albo usuń ją po upewnieniu się, że nic nie wpłynęło.', 'action'=>'Sprawdź wygasłe płatności'],
        'payout' => ['title'=>'Masz środki do wypłaty', 'text'=>'Te wpłaty zostały już zatwierdzone i znajdują się na portfelach, na które płacili klienci. Po przeniesieniu środków na swój główny portfel przenieś płatności do archiwum.', 'action'=>'Przejdź do środków do wypłaty'],
        'detected_notice'=>'Wykryto wpłatę — do zatwierdzenia. Sprawdź otrzymaną kwotę i zaakceptuj płatność.', 'approved_notice'=>'Płatność zatwierdzona. Wypłać środki na swój główny portfel, a następnie przenieś płatność do archiwum.',
        'single'=>'Zweryfikuj i zaakceptuj płatność', 'later'=>'Przypomnij za 10 minut', 'close'=>'Zamknij',
    ] : [
        'order' => ['title'=>'Complete the paid order', 'text'=>'The payment has been approved. Complete fulfilment or renewal with the provider, then confirm the order in Orders; archive the payment after withdrawing funds.', 'action'=>'Complete order'],
        'new' => ['title'=>'Review new payments', 'text'=>'Check the explorer to verify that funds arrived before accepting the payment. If it belongs to an order, then complete its fulfilment in Orders.', 'action'=>'Open payments'],
        'expired' => ['title'=>'Review expired payments', 'text'=>'Check the explorer for any delayed transfers. If no funds arrived, cancel the payment to allow another check before automatic deletion after 72 hours, or delete it once you are sure nothing was received.', 'action'=>'Review expired payments'],
        'payout' => ['title'=>'Funds are ready to withdraw', 'text'=>'These payments have been accepted and are held in the wallets customers paid into. Transfer funds to your main wallet before archiving the payments.', 'action'=>'Open payments to withdraw'],
        'detected_notice'=>'Payment detected — approval required. Review the received amount and accept the payment.', 'approved_notice'=>'Payment approved. Transfer funds to your main wallet, then archive the payment.',
        'single'=>'Review and accept payment', 'later'=>'Remind me in 10 minutes', 'close'=>'Close',
    ];
}

/** Choose the next task, keeping real receipts and order fulfilment ahead of housekeeping. */
function admin_crypto_reminder_view(Mysql_ks $db, string $csrf, array $messages, ?array $counts = null, ?string $locale = null): array
{
    $locale=$locale??admin_current_locale();
    $counts=$counts??admin_crypto_quick_counts($db);
    $category=null;$rows=[];$workflow='payment';$orderUrl='';
    $newRows=!empty($counts['new'])?admin_crypto_quick_rows($db,'new',1,true):[];
    if($newRows&&(string)$newRows[0]['status']==='awaiting_review'&&(float)($newRows[0]['received_crypto_amount']??0)>0){
        $category='new';$rows=$newRows;
    } else {
        if(!empty($counts['payout'])){
            // Indexed payment status / order PK lookups; no histories or chain requests.
            $orderPayment=$db->select_user("SELECT r.id FROM crypto_deposit_requests r
                INNER JOIN orders ON orders.id=r.order_id
                WHERE r.status='approved' AND (".admin_topbar_order_task_where_sql().")
                ORDER BY r.id ASC LIMIT 1");
            if(!empty($orderPayment['id'])){
                $rows=admin_crypto_quick_rows($db,'payout',1,false,(int)$orderPayment['id']);
                if($rows){$category='payout';$workflow='order';$orderUrl='/admin/?page=orders&order_id='.(int)$rows[0]['order_id'];}
            }
        }
        if(!$rows&&$newRows){$category='new';$rows=$newRows;}
        if(!$rows&&!empty($counts['expired'])){$category='expired';$rows=admin_crypto_quick_rows($db,'expired',1);}
        if(!$rows&&!empty($counts['payout'])){$category='payout';$rows=admin_crypto_quick_rows($db,'payout',1);}
    }
    $copy=admin_crypto_reminder_copy($locale);
    $content=$category!==null?($workflow==='order'?$copy['order']:$copy[$category]):null;
    $html=$rows?admin_crypto_quick_html($rows,$category,admin_crypto_quick_labels($locale),$csrf,$messages):'';
    $html=str_replace('cryptoQuickActions','cryptoReminderActions',$html);
    return ['ok'=>true,'filter'=>$category,'counts'=>$counts,'html'=>$html,'copy'=>$content,'workflow'=>$workflow,'order_url'=>$orderUrl];
}
