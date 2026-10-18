<?php
declare(strict_types=1);
function admin_panel_tiers_text(string $key): string
{
    $pl=($GLOBALS['panel_tiers_locale']??admin_current_locale())==='pl';
    $texts=[
        'title'=>['Przedłuż dostęp do panelu','Renew panel access'],
        'intro'=>['Wybierz okres dostępu i sposób płatności.','Choose your access period and payment method.'],
        'usage'=>['Opłacone subskrypcje','Paid subscriptions'],
        'month'=>['1 miesiąc','1 month'],'quarter'=>['3 miesiące','3 months'],
        'near'=>['Zbliżasz się do wyższego tieru. Dostępne przedłużenie: 1 miesiąc.','You are near the next tier. Renew for 1 month.'],
        'too_early'=>['Przedłużenie jest dostępne, gdy zostaje najwyżej 30 dni dostępu.','Renewal is available when at most 30 days of access remain.'],
        'disabled'=>['Automatyczne płatności za panel są wyłączone.','Automatic panel payments are disabled.'],
        'rate'=>['Kurs jest chwilowo niedostępny. Spróbuj ponownie za chwilę.','The exchange rate is temporarily unavailable. Try again shortly.'],
        'receivers'=>['Brak poprawnie skonfigurowanych portfeli do płatności za panel. Użyj osobnego adresu, nieprzypisanego do płatności klientów.','No panel payment wallets are configured. Use a dedicated address, separate from customer payments.'],
        'period'=>['Przy obecnym wykorzystaniu limitu możesz wybrać tylko 1 miesiąc.','At this usage level, only 1 month is available.'],
        'changed'=>['Ustawienia zmieniły się. Odśwież okno płatności.','Settings changed. Refresh the payment window.'],
        'currency'=>['Waluta panelu zmieniła się. Zapisz ponownie ceny tierów w Admin Mode.','The panel currency changed. Save tier prices again in Admin Mode.'],
        'price'=>['Podaj poprawną, dodatnią cenę dla każdego tieru.','Enter a valid positive price for each tier.'],
        'limits'=>['Limity muszą być dodatnie, a Tier 2 musi mieć wyższy limit niż Tier 1.','Limits must be positive and Tier 2 must exceed Tier 1.'],
        'wallet'=>['Sprawdź format i sieć adresów portfeli.','Check wallet address formats and networks.'],
        'pending_config'=>['Najpierw zakończ bieżącą płatność za panel. Jej ustawienia i kurs są już zablokowane.','Finish the current panel payment first. Its settings and exchange rate are locked.'],
        'storage'=>['Nie udało się zapisać danych. Spróbuj ponownie.','Unable to save. Try again.'],
        'session'=>['Sesja wygasła. Odśwież stronę.','Session expired. Refresh the page.'],
        'busy'=>['Poczekaj chwilę i spróbuj ponownie.','Wait a moment and try again.'],
        'generate'=>['Wygeneruj płatność','Generate payment'],
        'invoice_amount'=>['Pełna kwota płatności','Full invoice amount'],
        'qr_help'=>['QR zawiera adres. Kwotę skopiuj z danych poniżej.','The QR contains the address. Copy the amount below.'],
        'partial'=>['Wpłata częściowa — potrzebna dopłata','Partial payment — top-up required'],
        'remaining'=>['Do pełnej kwoty brakuje','Remaining to pay in full'],
        'received'=>['Otrzymano','Received'],
        'days'=>['Dodane dni','Days added'],
        'review_closed'=>['Weryfikacja zamknięta. Data dostępu nie została zmieniona.','Review closed. Access date was not changed.'],
        'close_review'=>['Zamknij weryfikację bez dodawania dni','Close review without granting days'],
        'cancel_expired'=>['Anuluj płatność i wróć','Cancel payment and go back'],
        'cancel'=>['Wstecz - zmień sposób płatności','Back - change payment method'],
        'cancelled'=>['Płatność anulowana. Wybierz okres i sposób płatności — także tę samą kryptowalutę. Wygenerujemy nową kwotę według aktualnego kursu i nowy czas na wpłatę. Jeśli środki zostały już wysłane, nie płać ponownie; poprzednia płatność pozostaje sprawdzana.','Payment cancelled. Choose an access period and payment method, including the same cryptocurrency. A new amount and payment deadline will be generated. If you already sent funds, do not pay again; the previous invoice remains monitored.'],
        'cancelled_expired'=>['Anulowana płatność — zamknięta','Cancelled payment — closed'],
        'cancelled_review'=>['Wpłata do anulowanej płatności — wymaga weryfikacji','Transfer to a cancelled invoice — review required'],
        'cannot_cancel'=>['Ta płatność ma już wpłatę lub zmienił się jej status. Sprawdź płatność i kontynuuj jej rozliczenie.','This payment has received funds or its status changed. Check the payment and continue settling it.'],
        'receiver_wait'=>['Poprzednia płatność w tej kryptowalucie jest jeszcze sprawdzana. Wybierz inną kryptowalutę.','The previous payment in this cryptocurrency is still being checked. Choose another cryptocurrency.'],
        'pending'=>['Oczekujemy na wpłatę...','Waiting for payment...'],
        'confirming'=>['Czas minął — sprawdzamy wysłaną wpłatę','Time is up — checking the sent payment'],
        'confirming_help'=>['Czas na wysłanie wpłaty minął. Jeśli środki zostały już wysłane, poczekaj na potwierdzenie sieci — nadal sprawdzamy płatność. Nie wysyłaj jej ponownie. Po zakończeniu weryfikacji bez wykrytej wpłaty wrócisz do wyboru okresu i sposobu płatności.','The payment window has ended. If funds were already sent, wait for network confirmation — we are still checking your payment. Do not send it again. If no payment is detected by the end of verification, you will return to the access period and payment method selection.'],
        'review'=>['Wpłata wykryta — wymaga weryfikacji','Payment detected — review required'],
        'review_help'=>['Wpłata jest w weryfikacji. Nie wykonuj ponownej płatności.','Your payment is under review. Do not pay again.'],
        'paid'=>['Płatność potwierdzona. Dostęp przedłużony.','Payment confirmed. Access renewed.'],
        'expired'=>['Weryfikacja zakończona — nie wykryto wpłaty. Wybierz okres i sposób płatności. Jeśli środki zostały wysłane, skontaktuj się z obsługą przed kolejną wpłatą.','Verification ended — no payment was detected. Choose an access period and payment method. If you sent funds, contact support before sending another payment.'],
        'check'=>['Odśwież status','Refresh status'],
        'checking'=>['Sprawdzanie…','Checking…'],
        'send_network'=>['Prosimy wysłać środki w sieci:','Please send funds on the network:'],
        'scan_help'=>['Wyślij podaną kwotę w wybranej sieci.','Send the stated amount on the selected network.'],
        'scan_confirm'=>['Płatność potwierdzimy automatycznie.','Your payment will be confirmed automatically.'],
        'partial_help'=>['Dopłać brakującą kwotę na ten sam adres.','Send the remaining amount to the same address.'],
        'period_label'=>['Okres dostępu','Access period'],
        'crypto_label'=>['Wybierz kryptowalutę','Choose cryptocurrency'],
        'access_until'=>['Dostęp opłacony do','Access paid until'],
        'unavailable'=>['Płatność jest chwilowo niedostępna. Skontaktuj się z administratorem.','Payment is currently unavailable. Contact the administrator.'],
        'copy_error'=>['Nie udało się skopiować. Zaznacz i skopiuj dane ręcznie.','Unable to copy. Select and copy the details manually.'],
        'invoice_label'=>['Dane do płatności','Payment details'],
        'day_count'=>['dni','days'],
        'maintenance'=>['Jeśli strona klienta była w przerwie technicznej, po odnowieniu sprawdź jej przełącznik w ustawieniach.','If the customer site was under maintenance, review its switch in settings after renewal.'],
        'refresh'=>['Odśwież panel','Refresh panel'],
        'error'=>['Nie udało się sprawdzić płatności. Ponowimy próbę.','Unable to check the payment. We will retry.'],
        'network'=>['Sieć','Network'],'amount'=>['Kwota do wpłaty','Amount to send'],
        'address'=>['Adres odbiorcy','Receiving address'],'rate_label'=>['Kurs wymiany','Exchange rate'],
        'time_left'=>['Pozostały czas','Time remaining'],'until'=>['Opłać do','Pay before'],'copy'=>['Kopiuj','Copy'],
        'limit'=>['Do {n} subskrypcji','Up to {n} subscriptions'],
        'unlimited'=>['Powyżej limitu Tier 2','Above the Tier 2 limit'],
        'per_month'=>['/ miesiąc','/ month'],
        'no_date'=>['Wybierz okres dostępu','Choose your access period'],
        'manual_help'=>['Gdy automat jest ON, datę dostępu aktualizuje potwierdzona płatność. Wyłącz automat, aby ustawić datę ręcznie.','When automation is ON, confirmed payments update access. Switch it OFF to set the date manually.'],
        'settings_title'=>['Automatyczne przedłużanie dostępu','Automatic access renewal'],
        'settings_help'=>['OFF: dotychczasowy link zewnętrzny i ręczna data. ON: tiery, płatność krypto i automatyczne dodawanie miesięcy.','OFF: external payment link and a manual date. ON: tiers, crypto payments and automatic calendar-month renewal.'],
        'wallet_help'=>['Tylko poprawne, uzupełnione adresy będą dostępne. Każda instalacja panelu wymaga osobnych adresów, nieużywanych do wpłat klientów — to pozwala rozliczać dopłaty jednoznacznie.','Only valid, non-empty addresses are offered. Use dedicated addresses for each panel installation, separate from customer wallets, so top-ups can be matched unambiguously.'],
        'email_notice'=>['Email do konta admin po potwierdzeniu płatności','Email the admin account after payment confirmation'],
        'history'=>['Płatności za dostęp do panelu','Panel access payments'],
    ];
    return ($texts[$key]??$texts['storage'])[$pl?0:1];
}
function admin_panel_tiers_state(Mysql_ks $db): array
{
    $config=app_panel_tiers_config($db);$settings=$db->select_user('SELECT * FROM app_settings WHERE id=1');
    if(empty($config['enabled']))throw new DomainException('disabled');
    // One bounded synchronization when the modal is opened, not on its polling requests.
    $usage=app_panel_tiers_usage($db);
    $assets=[];foreach(app_panel_tiers_receivers($db,$config) as $code=>$spec)$assets[]=['code'=>$code,'label'=>$spec['label'],'name'=>['BTC'=>'Bitcoin','SOL'=>'Solana','DOGE'=>'Dogecoin','USDT'=>'Tether'][$code],'logo'=>app_crypto_logo_by_code($code)];
    $pending=app_panel_tiers_pending($db);
    $unresolved=$db->select_user("SELECT * FROM panel_subscription_requests WHERE status='cancelled_review' AND received_crypto>0 ORDER BY id DESC LIMIT 1");
    if($db->error)throw new RuntimeException('storage');
    if($unresolved)$pending=null;
    $latest=$unresolved??$pending??$db->select_user('SELECT * FROM panel_subscription_requests ORDER BY id DESC LIMIT 1');
    $request=null;
    if($latest){$keys=['id','status','tier','months','amount_fiat','currency_code','asset_code','network_code','wallet_address','amount_crypto','received_crypto','quoted_days','granted_days','exchange_rate','created_at','pay_before','new_paid_until'];$request=array_intersect_key($latest,array_flip($keys));$request['remaining_crypto']=app_panel_tiers_remaining($latest);$request['can_cancel']=(bool)$pending&&in_array($latest['status'],['pending','confirming'],true)&&(float)$latest['received_crypto']===0.0;if($request['can_cancel']){$observed=$db->select_user('SELECT request_id FROM panel_subscription_observations WHERE request_id='.(int)$latest['id'].' LIMIT 1');if($db->error)throw new RuntimeException('storage');$request['can_cancel']=!$observed;}}
    return ['ok'=>true,'server_time_ms'=>(int)floor(microtime(true)*1000),'policy'=>app_panel_tiers_policy($config,$usage,is_array($settings)?$settings:[]),'assets'=>$assets,'request'=>$request,'pending'=>(bool)$pending];
}
