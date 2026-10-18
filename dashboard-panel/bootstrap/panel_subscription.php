<?php
declare(strict_types=1);

/** No network, migrations or scans on ordinary customer/dashboard GET requests. */
function app_panel_tiers_installed(Mysql_ks $db): bool
{
    return schema_object_exists($db, 'panel_subscription_config');
}

function app_panel_tiers_ensure_schema(Mysql_ks $db): void
{
    foreach (['panel_subscription_config', 'panel_subscription_entitlements', 'panel_subscription_requests', 'panel_subscription_transfers', 'panel_subscription_observations'] as $table) {
        if (schema_object_exists($db, $table)) continue;
        $sql = file_get_contents(dirname(__DIR__) . '/migrations/042_panel_subscription_tiers.sql');
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '' && !$db->query($statement)) throw new RuntimeException('Cannot install panel payments.');
        }
        foreach (['panel_subscription_config', 'panel_subscription_entitlements', 'panel_subscription_requests', 'panel_subscription_transfers', 'panel_subscription_observations'] as $created) $GLOBALS['schema_object_exists_cache'][$created] = true;
        break;
    }
    if (!schema_column_exists($db, 'panel_subscription_config', 'last_scan_at')) {
        if (!$db->query('ALTER TABLE panel_subscription_config ADD last_scan_at DATETIME DEFAULT NULL')) throw new RuntimeException('Cannot update panel payments.');
        schema_forget_column_cache('panel_subscription_config', 'last_scan_at');
    }
    // Query directly after DDL: schema metadata may have cached a missing table.
    if (!$db->query("INSERT IGNORE INTO panel_subscription_config (id,currency_code)
        SELECT 1, COALESCE(c.code,'EUR') FROM app_settings s LEFT JOIN currencies c ON c.id=s.default_currency_id WHERE s.id=1")) {
        throw new RuntimeException('Cannot initialize panel payments.');
    }
    if ($db->affected_rows === 1) app_panel_tiers_capture($db);
}

function app_panel_tiers_config(Mysql_ks $db): array
{
    if (!app_panel_tiers_installed($db)) return ['enabled' => 0];
    $row = $db->select_user('SELECT * FROM panel_subscription_config WHERE id=1');
    return is_array($row) ? $row : ['enabled' => 0];
}

/** A full period contains the days in each selected base calendar month, including leap years. */
function app_panel_tiers_duration_days(?string $paidUntil, int $months, ?DateTimeImmutable $now = null): int
{
    if (!in_array($months,[1,3],true)) throw new InvalidArgumentException('Invalid period.');
    $now=$now??new DateTimeImmutable('now',new DateTimeZone(app_runtime_timezone_name()));
    $base=new DateTimeImmutable(max($paidUntil??'', $now->format('Y-m-d')).' 00:00:00',$now->getTimezone());
    $month=$base->modify('first day of this month');$days=0;
    for($i=0;$i<$months;$i++){$days+=(int)$month->format('t');$month=$month->modify('+1 month');}
    return $days;
}
function app_panel_tiers_extended_date(?string $paidUntil, int $months, ?DateTimeImmutable $now = null, ?int $grantedDays = null): string
{
    $now=$now??new DateTimeImmutable('now',new DateTimeZone(app_runtime_timezone_name()));
    $days=$grantedDays??app_panel_tiers_duration_days($paidUntil,$months,$now);
    $base=new DateTimeImmutable(max($paidUntil??'', $now->format('Y-m-d')).' 00:00:00',$now->getTimezone());
    return $base->modify('+'.$days.' days')->format('Y-m-d');
}
/** Integer native units avoid floating-point errors at the 90% and whole-day boundaries. */
function app_panel_tiers_amount_units($amount, int $decimals): ?int
{
    if ($decimals < 0 || $decimals > 9) return null;
    if (is_float($amount)) {
        if (!is_finite($amount) || $amount < 0) return null;
        $amount = number_format($amount, $decimals, '.', '');
    }
    if (!is_string($amount) && !is_int($amount)) return null;
    if (!preg_match('/^([0-9]+)(?:\.([0-9]+))?$/D', (string)$amount, $parts)) return null;
    $fraction = $parts[2] ?? '';
    if (trim(substr($fraction, $decimals), '0') !== '') return null;
    $digits = ltrim($parts[1] . str_pad(substr($fraction, 0, $decimals), $decimals, '0'), '0');
    if ($digits === '') return 0;
    $maximum = (string)PHP_INT_MAX;
    if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) return null;
    return (int)$digits;
}
function app_panel_tiers_units_amount(int $units, int $decimals): string
{
    $digits = str_pad((string)max(0, $units), $decimals + 1, '0', STR_PAD_LEFT);
    return $decimals > 0 ? substr($digits, 0, -$decimals) . '.' . substr($digits, -$decimals) : $digits;
}
function app_panel_tiers_remaining(array $request): string
{
    $decimals = app_panel_tiers_assets()[$request['asset_code']]['decimals'];
    $requested = app_panel_tiers_amount_units($request['amount_crypto'], $decimals);
    $received = app_panel_tiers_amount_units($request['received_crypto'] ?? '0', $decimals);
    if ($requested === null || $received === null) throw new RuntimeException('Invalid payment amount.');
    return app_panel_tiers_units_amount(max(0, $requested - $received), $decimals);
}
function app_panel_tiers_granted_days(int $quotedDays, $received, $requested, int $decimals = 9): int
{
    $received = app_panel_tiers_amount_units($received, $decimals);
    $requested = app_panel_tiers_amount_units($requested, $decimals);
    if ($quotedDays < 1 || $quotedDays > 366 || $received === null || $requested === null || $requested <= 0 || $requested > 9000000000000000) return 0;
    if ($received >= $requested) return $quotedDays;
    if ($received * 10 < $requested * 9) return 0;
    return intdiv($quotedDays * $received, $requested);
}

function app_panel_tiers_policy(array $config, int $usage, array $settings, ?int $now = null): array
{
    $now = $now ?? time();
    $first = max(1, (int)($config['limit_1'] ?? 50));
    $second = max($first + 1, (int)($config['limit_2'] ?? 250));
    $tier = $usage <= $first ? 1 : ($usage <= $second ? 2 : 3);
    $limit = $tier === 1 ? $first : ($tier === 2 ? $second : null);
    $near = ($usage >= (int)ceil($first*.9) && $usage <= (int)floor($first*1.1))
        || ($usage >= (int)ceil($second*.9) && $usage <= (int)floor($second*1.1));
    $until = app_panel_subscription_paid_until_timestamp($settings);
    $days = $until === null ? 0 : max(0, (int)ceil(($until - $now) / 86400));
    return ['tier'=>$tier, 'usage'=>$usage, 'limit'=>$limit, 'near_limit'=>$near,
        'allowed_months'=>$near ? [1] : [1,3], 'can_renew'=>$days <= 30,
        'days_left'=>$days, 'period_days'=>[1=>app_panel_tiers_duration_days($settings['admin_panel_subscription_paid_until']??null,1,(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone(app_runtime_timezone_name()))),3=>app_panel_tiers_duration_days($settings['admin_panel_subscription_paid_until']??null,3,(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone(app_runtime_timezone_name())))], 'price'=>(string)($config['price_'.$tier] ?? [1=>'29.00',2=>'59.00',3=>'99.00'][$tier]),
        'currency'=>(string)($config['currency_code'] ?? 'EUR')];
}

/** Monotonic paid-through ledger. Status/payment reversals and deletions cannot shorten it. */
function app_panel_tiers_capture(Mysql_ks $db, int $orderId = 0, int $customerId = 0): void
{
    if (!app_panel_tiers_installed($db)) return;
    $where = $orderId > 0 ? ' AND o.id=' . $orderId : ($customerId > 0 ? ' AND o.customer_id='.$customerId : '');
    // Existing paid orders, balance-paid activations and immutable initial sale dates are evidence.
    $sql = "INSERT INTO panel_subscription_entitlements (order_id,customer_id,paid_through)
        SELECT o.id,o.customer_id,
          GREATEST(COALESCE(o.expires_at,'1000-01-01'),
            DATE_ADD(COALESCE(o.initial_sale_paid_at,o.paid_at,
              (SELECT MAX(COALESCE(cr.confirmed_at,cr.requested_at)) FROM crypto_deposit_requests cr WHERE cr.order_id=o.id AND (cr.confirmed_at IS NOT NULL OR cr.status IN ('confirmed','approved','paid','completed','archived'))),
              (SELECT MAX(COALESCE(br.approved_at,br.requested_at)) FROM bank_transfer_requests br WHERE br.order_id=o.id AND (br.approved_at IS NOT NULL OR br.status IN ('confirmed','approved','paid','completed','archived'))),
              o.started_at,o.created_at),INTERVAL GREATEST(COALESCE(p.duration_hours,0),1) HOUR))
        FROM orders o LEFT JOIN products p ON p.id=COALESCE(o.initial_sale_product_id,o.product_id)
        WHERE COALESCE(p.product_type,'subscription')='subscription'
          AND (o.payment_status='paid' OR o.initial_sale_paid_at IS NOT NULL OR o.paid_at IS NOT NULL
           OR EXISTS(SELECT 1 FROM crypto_deposit_requests cr WHERE cr.order_id=o.id AND (cr.confirmed_at IS NOT NULL OR cr.status IN ('confirmed','approved','paid','completed','archived')))
           OR EXISTS(SELECT 1 FROM bank_transfer_requests br WHERE br.order_id=o.id AND (br.approved_at IS NOT NULL OR br.status IN ('confirmed','approved','paid','completed','archived')))) {$where}
        ON DUPLICATE KEY UPDATE paid_through=GREATEST(paid_through,VALUES(paid_through))";
    if (!$db->query($sql)) throw new RuntimeException('Cannot capture paid subscriptions.');
    // Paid renewals count even while activation is pending or after a later status reversal.
    $sql = "INSERT INTO panel_subscription_entitlements (order_id,customer_id,paid_through)
        SELECT r.order_id,r.customer_id,MAX(GREATEST(COALESCE(r.target_expires_at,'1000-01-01'),
            DATE_ADD(COALESCE(r.paid_at,r.applied_at,r.requested_at),INTERVAL GREATEST(r.duration_hours,1) HOUR)))
        FROM order_renewals r WHERE (r.paid_at IS NOT NULL OR r.status IN ('paid_pending_activation','applied'))"
        . ($orderId > 0 ? ' AND r.order_id='.$orderId : ($customerId > 0 ? ' AND r.customer_id='.$customerId : '')) . " GROUP BY r.order_id,r.customer_id
        ON DUPLICATE KEY UPDATE paid_through=GREATEST(paid_through,VALUES(paid_through))";
    if (!$db->query($sql)) throw new RuntimeException('Cannot capture paid renewals.');
}

function app_panel_tiers_usage(Mysql_ks $db): int
{
    $row = $db->select_user('SELECT COUNT(*) AS total FROM panel_subscription_entitlements WHERE paid_through>NOW()');
    if (!is_array($row)) throw new RuntimeException('Cannot count paid subscriptions.');
    return (int)$row['total'];
}

function app_panel_tiers_assets(): array
{
    return [
        'BTC'=>['network'=>'bitcoin','label'=>'Bitcoin','wallet'=>'wallet_btc','decimals'=>8],
        'SOL'=>['network'=>'solana','label'=>'Solana','wallet'=>'wallet_sol','decimals'=>9],
        'DOGE'=>['network'=>'dogecoin','label'=>'Dogecoin','wallet'=>'wallet_doge','decimals'=>8],
        'USDT'=>['network'=>'ethereum','label'=>'Ethereum · ERC-20','wallet'=>'wallet_usdt','decimals'=>6],
    ];
}

function app_panel_tiers_validate_wallet(string $asset, string $address): bool
{
    if ($address === '') return true;
    if ($asset === 'USDT') return (bool)preg_match('/^0x[0-9a-fA-F]{40}$/D',$address) && !preg_match('/^0x0{40}$/iD',$address);
    if ($asset === 'SOL') {
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/D',$address)) return false;
        // Decode base58 to validate the 32-byte public key, including leading zero bytes.
        $bytes=[0];$alphabet='123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        for($i=0;$i<strlen($address);$i++){$carry=strpos($alphabet,$address[$i]);for($j=0;$j<count($bytes);$j++){$carry+=$bytes[$j]*58;$bytes[$j]=$carry&255;$carry>>=8;}while($carry>0){$bytes[]=$carry&255;$carry>>=8;}}
        $leading=strspn($address,'1');$size=count($bytes)+$leading-(count($bytes)===1&&$bytes[0]===0?1:0);
        return $size===32;
    }
    if ($asset === 'BTC' && preg_match('/^bc1[ac-hj-np-z02-9]{11,71}$/D',strtolower($address))) {
        if ($address !== strtolower($address) && $address !== strtoupper($address)) return false;
        $alphabet='qpzry9x8gf2tvdw0s3jn54khce6mua7l';$data=[];
        foreach(str_split(substr(strtolower($address),3)) as $char){$v=strpos($alphabet,$char);if($v===false)return false;$data[]=$v;}
        $polymod=1;$values=array_merge([3,3,0,2,3],$data);
        foreach($values as $v){$top=$polymod>>25;$polymod=(($polymod&0x1ffffff)<<5)^$v;foreach([0x3b6a57b2,0x26508e6d,0x1ea119fa,0x3d4233dd,0x2a1462b3] as $i=>$g)if(($top>>$i)&1)$polymod^=$g;}
        $version=$data[0]??99;if($version>16||$polymod!==($version===0?1:0x2bc830a3))return false;
        $payload=array_slice($data,1,-6);$bits=count($payload)*5;$size=intdiv($bits,8);$padding=$bits%8;
        return $size>=2&&$size<=40&&($version!==0||in_array($size,[20,32],true))&&$padding<5&&(($payload[count($payload)-1]??0)&((1<<$padding)-1))===0;
    }
    if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{26,35}$/D',$address)) return false;
    $bytes=[0];$alphabet='123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    foreach(str_split($address) as $char){$carry=strpos($alphabet,$char);for($j=0;$j<count($bytes);$j++){$carry+=$bytes[$j]*58;$bytes[$j]=$carry&255;$carry>>=8;}while($carry>0){$bytes[]=$carry&255;$carry>>=8;}}
    $raw=str_repeat("\0",strspn($address,'1')).implode('',array_map('chr',array_reverse($bytes)));
    if(strlen($raw)!==25)return false;
    $version=ord($raw[0]);$allowed=$asset==='BTC'?[0,5]:[30,22];
    return in_array($version,$allowed,true)&&hash_equals(substr(hash('sha256',hash('sha256',substr($raw,0,21),true),true),0,4),substr($raw,21));
}

function app_panel_tiers_receivers(Mysql_ks $db, array $config): array
{
    $available=[];
    foreach(app_panel_tiers_assets() as $asset=>$spec){
        $address=trim((string)($config[$spec['wallet']]??''));
        if($address===''||!app_panel_tiers_validate_wallet($asset,$address))continue;
        // Panel revenue must use dedicated addresses, never a customer deposit wallet.
        $comparison=in_array($asset,['USDT','BTC'],true)?'LOWER(address)=LOWER':'BINARY address=BINARY';
        $conflict=$db->select_user("SELECT id FROM crypto_wallet_addresses WHERE {$comparison}('".$db->escape($address)."') LIMIT 1");
        if($conflict)continue;
        $available[$asset]=$spec+['address'=>$address];
    }
    return $available;
}

function app_panel_tiers_pending(Mysql_ks $db): ?array
{
    $row=$db->select_user('SELECT * FROM panel_subscription_requests WHERE open_slot=1 LIMIT 1');
    return is_array($row)?$row:null;
}

function app_panel_tiers_save_config(Mysql_ks $db, array $input, string $currency, array $manualAccess = []): void
{
    foreach($input as $value)if(!is_scalar($value)&&$value!==null)throw new DomainException('storage');
    $values=[];
    foreach([1,2,3] as $tier){$raw=str_replace(',','.',trim((string)($input['panel_price_'.$tier]??'')));if(!preg_match('/^[0-9]{1,7}(\.[0-9]{1,2})?$/D',$raw)||(float)$raw<=0)throw new DomainException('price');$values['price_'.$tier]=number_format((float)$raw,2,'.','');}
    $first=filter_var($input['panel_limit_1']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000000]]);
    $second=filter_var($input['panel_limit_2']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>2,'max_range'=>10000001]]);
    if($first===false||$second===false||$second<=$first)throw new DomainException('limits');
    $values+=['limit_1'=>$first,'limit_2'=>$second,'currency_code'=>$currency,'enabled'=>empty($input['panel_auto_enabled'])?0:1,'notify_admin'=>empty($input['panel_notify_admin'])?0:1];
    foreach(app_panel_tiers_assets() as $asset=>$spec){$address=trim((string)($input['panel_'.$spec['wallet']]??''));if(!app_panel_tiers_validate_wallet($asset,$address))throw new DomainException('wallet');$values[$spec['wallet']]=($asset==='BTC'&&str_starts_with(strtolower($address),'bc1'))?strtolower($address):$address;}
    if($values['enabled']&&!app_panel_tiers_receivers($db,$values))throw new DomainException('receivers');
    $db->start();
    try{
        $db->select_user('SELECT id FROM panel_subscription_config WHERE id=1 FOR UPDATE');
        if(app_panel_tiers_pending($db)){
            $old=app_panel_tiers_config($db);
            foreach($values as $key=>$value){
                if(in_array($key,['enabled','notify_admin'],true))continue;
                if(empty($values['enabled'])){$values[$key]=$old[$key];continue;}
                if((string)$old[$key] !== (string)$value)throw new DomainException('pending_config');
            }
        }
        if(!$db->update_using_id(array_keys($values),array_values($values),'panel_subscription_config',1))throw new RuntimeException('storage');
        app_panel_tiers_capture($db);
        if($manualAccess){
            $fields=['admin_panel_subscription_renewal_url'];$accessValues=[$manualAccess['url']!==''?$manualAccess['url']:null];
            if(empty($values['enabled'])){$fields[]='admin_panel_subscription_paid_until';$accessValues[]=$manualAccess['date']!==''?$manualAccess['date']:null;}
            if(!$db->update_using_id($fields,$accessValues,'app_settings',1))throw new RuntimeException('storage');
        }
        if(!$db->query('COMMIT'))throw new RuntimeException('storage');
    }catch(Throwable $e){$db->query('ROLLBACK');throw $e;}
}

/** Lock rate outside the transaction; serialize requests and recompute policy inside it. */
function app_panel_tiers_create(Mysql_ks $db, int $adminId, string $asset, int $months): array
{
    $config=app_panel_tiers_config($db);
    if(empty($config['enabled']))throw new DomainException('disabled');
    $currencyRow=$db->select_user('SELECT c.code,s.admin_panel_subscription_paid_until FROM app_settings s JOIN currencies c ON c.id=s.default_currency_id WHERE s.id=1');
    if($existing=app_panel_tiers_pending($db))return $existing;
    if(!app_panel_tiers_policy($config,0,is_array($currencyRow)?$currencyRow:[])['can_renew'])throw new DomainException('too_early');
    if(($currencyRow['code']??'')!==$config['currency_code'])throw new DomainException('currency');
    $receivers=app_panel_tiers_receivers($db,$config);
    if(!isset($receivers[$asset]))throw new DomainException('receivers');
    $restorable=$db->select_user("SELECT id FROM panel_subscription_requests WHERE wallet_address='".$db->escape($receivers[$asset]['address'])."' AND network_code='".$db->escape($receivers[$asset]['network'])."' AND status IN ('cancelled','cancelled_review') AND check_until>=UTC_TIMESTAMP() LIMIT 1");
    $rate=0.0;
    if(!$restorable){
        $rates=admin_refresh_crypto_asset_rates($db,$config['currency_code'],900,[$asset],true,true);
        $rateRow=null;foreach($rates as $r)if(strtoupper((string)$r['code'])===$asset)$rateRow=$r;
        if(!$rateRow||!app_crypto_rate_is_usable($rateRow,$config['currency_code'],1800))throw new DomainException('rate');
        $rate=(float)$rateRow['current_rate_fiat'];
    }
    $db->start();
    try{
        $fresh=$db->select_user('SELECT * FROM panel_subscription_config WHERE id=1 FOR UPDATE');
        if(!$fresh||array_diff_assoc(array_diff_key($fresh,['last_scan_at'=>true]),array_diff_key($config,['last_scan_at'=>true])))throw new DomainException('changed');
        $pending=app_panel_tiers_pending($db);if($pending){$db->query('COMMIT');return $pending;}
        if(!isset(app_panel_tiers_receivers($db,$fresh)[$asset]))throw new DomainException('receivers');
        $settings=$db->select_user('SELECT * FROM app_settings WHERE id=1 FOR UPDATE');
        app_panel_tiers_capture($db);
        $policy=app_panel_tiers_policy($config,app_panel_tiers_usage($db),$settings);
        if(!$policy['can_renew'])throw new DomainException('too_early');
        if(!in_array($months,$policy['allowed_months'],true))throw new DomainException('period');
        // Restore the same receiver's cancelled quote instead of creating overlapping payment windows.
        $old=$db->select_user("SELECT * FROM panel_subscription_requests WHERE wallet_address='".$db->escape($receivers[$asset]['address'])."' AND network_code='".$db->escape($receivers[$asset]['network'])."' AND status IN ('cancelled','cancelled_review') AND check_until>=UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1 FOR UPDATE");
        if($old){
            if($old['status']==='cancelled_review'||(float)$old['received_crypto']>0)throw new DomainException('cannot_cancel');
            if($policy['tier']>(int)$old['tier'])throw new DomainException('changed');
            if(!in_array((int)$old['months'],$policy['allowed_months'],true))throw new DomainException('period');
            $oldId=(int)$old['id'];
            // Returning to the same quote must not reset its payment or finality deadline.
            if(!$db->query("UPDATE panel_subscription_requests SET status=IF(pay_before>UTC_TIMESTAMP(),'pending','confirming'),open_slot=1 WHERE id={$oldId}"))throw new RuntimeException('storage');
            $restored=app_panel_tiers_pending($db);
            if(!$restored||!$db->query('COMMIT'))throw new RuntimeException('storage');
            return $restored;
        }
        if($rate<=0)throw new DomainException('changed');
        $total=round((float)$policy['price']*$months,2);$decimals=$receivers[$asset]['decimals'];$factor=10**$decimals;
        // Tiny identifying amount: no reused amount on this receiver, including expired invoices.
        $rawUnits=ceil($total/$rate*$factor);
        if(!is_finite($rawUnits)||$rawUnits<1||$rawUnits>8999999999999800)throw new DomainException('rate');
        $units=(int)$rawUnits+random_int(1,99);
        $crypto=app_panel_tiers_units_amount($units,$decimals);
        for($i=0;$i<100;$i++){
            $exists=$db->select_user("SELECT id FROM panel_subscription_requests WHERE asset_code='".$db->escape($asset)."' AND wallet_address='".$db->escape($receivers[$asset]['address'])."' AND amount_crypto='{$crypto}' LIMIT 1");
            if(!$exists)break;$units++;$crypto=app_panel_tiers_units_amount($units,$decimals);
        }
        if($exists)throw new DomainException('busy');
        $fields=['open_slot','admin_user_id','tier','usage_count','months','quoted_days','amount_fiat','currency_code','asset_code','network_code','wallet_address','amount_crypto','exchange_rate','created_at','pay_before','check_until'];
        $values=[1,$adminId,$policy['tier'],$policy['usage'],$months,app_panel_tiers_duration_days($settings['admin_panel_subscription_paid_until']??null,$months),$total,$config['currency_code'],$asset,$receivers[$asset]['network'],$receivers[$asset]['address'],$crypto,$rate,gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s',time()+1800),gmdate('Y-m-d H:i:s',time()+172800)];
        if(!$db->insert($fields,$values,'panel_subscription_requests'))throw new RuntimeException('storage');
        $pending=app_panel_tiers_pending($db);if(!$pending)throw new RuntimeException('storage');
        if(!$db->query('COMMIT'))throw new RuntimeException('storage');
        return $pending;
    }catch(Throwable $e){$db->query('ROLLBACK');throw $e;}
}

/** Cancelling an invoice cannot cancel an on-chain transfer. Retain it for bounded late-payment review. */
function app_panel_tiers_cancel(Mysql_ks $db, int $requestId): void
{
    $db->start();
    try {
        $config=$db->select_user('SELECT * FROM panel_subscription_config WHERE id=1 FOR UPDATE');
        $row=$db->select_user('SELECT * FROM panel_subscription_requests WHERE id='.$requestId.' FOR UPDATE');
        if(empty($config['enabled']))throw new DomainException('disabled');
        if(!$row||!(int)$row['open_slot']||!in_array($row['status'],['pending','confirming'],true))throw new DomainException('cannot_cancel');
        $transfer=$db->select_user('SELECT id FROM panel_subscription_transfers WHERE request_id='.$requestId.' LIMIT 1');
        if((float)$row['received_crypto']>0||$transfer)throw new DomainException('cannot_cancel');
        if(!$db->query("UPDATE panel_subscription_requests SET status='cancelled',open_slot=NULL WHERE id={$requestId}"))throw new RuntimeException('storage');
        if(!$db->query('COMMIT'))throw new RuntimeException('storage');
    } catch(Throwable $e) {$db->query('ROLLBACK');throw $e;}
}

/** Explicit identity prevents a stale history form from closing a different payment. */
function app_panel_tiers_close_review(Mysql_ks $db, int $requestId): void
{
    $db->start();
    try {
        $db->select_user('SELECT id FROM panel_subscription_config WHERE id=1 FOR UPDATE');
        if($requestId<1)throw new DomainException('changed');
        if(!$db->query("UPDATE panel_subscription_requests SET status='review_closed',open_slot=NULL WHERE id={$requestId} AND ((status='review' AND open_slot=1) OR status='cancelled_review')"))throw new RuntimeException('storage');
        if($db->affected_rows!==1)throw new DomainException('changed');
        if(!$db->query('COMMIT'))throw new RuntimeException('storage');
    }catch(Throwable $e){$db->query('ROLLBACK');throw $e;}
}

function app_panel_tiers_payment_row(array $request): array
{
    return ['id'=>0,'wallet_address'=>$request['wallet_address'],'asset_code'=>$request['asset_code'],
        'network_code'=>$request['network_code'],'exchange_rate'=>$request['exchange_rate'],
        'requested_amount_crypto'=>$request['amount_crypto'],'requested_amount_value'=>$request['amount_fiat'],
        'requested_at'=>date('Y-m-d H:i:s',app_timestamp_from_utc_datetime($request['created_at']))];
}

function app_panel_tiers_remote_rows(array $request): array
{
    $payment=app_panel_tiers_payment_row($request);
    switch($request['asset_code']){
        case 'BTC': return admin_crypto_accept_preview_btc_remote_rows($payment,72,true);
        case 'DOGE': return admin_crypto_accept_preview_doge_remote_rows($payment,72,true,true);
        case 'SOL': return admin_crypto_accept_preview_sol_rows($payment,72,'finalized',120);
        case 'USDT': return admin_crypto_accept_preview_eth_token_rows($payment,72);
    }
    return [];
}

/** Save an on-time observation once; unconfirmed transfers never add money or days. */
function app_panel_tiers_observe(Mysql_ks $db, array $request, array $rows): array
{
    $now=time();$start=app_timestamp_from_utc_datetime($request['created_at']);$end=app_timestamp_from_utc_datetime($request['pay_before']);
    $seen=app_panel_tiers_observations($db,$request);
    if($now<$start||$now>$end)return $seen;
    $values=[];
    foreach(array_slice($rows,0,100) as $row){
        $hash=trim((string)($row['hash']??''));if($request['asset_code']!=='SOL')$hash=strtolower($hash);
        if(isset($seen[$hash]))continue;
        $timestamp=(int)($row['timestamp']??0);$amount=(float)($row['amount_crypto']??0);
        if(!preg_match('/^[a-zA-Z0-9]{32,100}$/D',$hash)||($row['direction']??'')!=='incoming'||!is_finite($amount)||$amount<=0||$timestamp<$start||$timestamp>min($now,$end))continue;
        $values[$hash]='('.(int)$request['id'].",'".$db->escape($request['network_code'])."','".$db->escape($hash)."',UTC_TIMESTAMP())";
    }
    if($values&&!$db->query('INSERT IGNORE INTO panel_subscription_observations (request_id,network_code,transaction_hash,first_seen_at) VALUES '.implode(',',array_values($values))))throw new RuntimeException('Cannot retain transfer observation.');
    return $values?app_panel_tiers_observations($db,$request):$seen;
}
function app_panel_tiers_observations(Mysql_ks $db, array $request): array
{
    $rows=$db->select_full_user('SELECT transaction_hash,first_seen_at FROM panel_subscription_observations WHERE request_id='.(int)$request['id']);
    if($rows===false)throw new RuntimeException('Cannot verify original transfer timing.');
    $seen=[];foreach($rows as $row)$seen[$row['transaction_hash']]=app_timestamp_from_utc_datetime($row['first_seen_at']);
    return $seen;
}
function app_panel_tiers_in_window(array $request, int $timestamp, string $hash, array $seen): bool
{
    $start=app_timestamp_from_utc_datetime($request['created_at']);$end=app_timestamp_from_utc_datetime($request['pay_before']);
    if($request['asset_code']!=='SOL')$hash=strtolower($hash);
    if($timestamp<$start||$timestamp>min(time(),app_timestamp_from_utc_datetime($request['check_until'])))return false;
    return $timestamp<=$end||(isset($seen[$hash])&&$seen[$hash]>=$start&&$seen[$hash]<=$end);
}

/** Avoid spending API receipt checks repeatedly on transfers already reconciled. */
function app_panel_tiers_unclaimed_rows(Mysql_ks $db, array $request, array $rows): array
{
    $hashes=[];
    foreach($rows as $row){
        $hash=trim((string)($row['hash']??''));
        if($request['asset_code']!=='SOL')$hash=strtolower($hash);
        if($hash!=='')$hashes[$hash]="'".$db->escape($hash)."'";
    }
    if(!$hashes)return [];
    $in=implode(',',array_values($hashes));$network=$db->escape($request['network_code']);
    $claimed=[];
    $panel=$db->select_full_user("SELECT transaction_hash FROM panel_subscription_transfers WHERE network_code='{$network}' AND transaction_hash IN ({$in})");
    $customer=$db->select_full_user("SELECT transaction_hash FROM crypto_deposit_transactions WHERE transaction_hash IN ({$in})");
    $reserved=$db->select_full_user("SELECT transaction_hash FROM panel_subscription_observations WHERE network_code='{$network}' AND transaction_hash IN ({$in}) AND request_id<>".(int)$request['id']);
    if($panel===false||$customer===false||$reserved===false)throw new RuntimeException('Cannot verify transfer ownership.');
    foreach(array_merge($panel,$customer,$reserved) as $row){$hash=(string)$row['transaction_hash'];if($request['asset_code']!=='SOL')$hash=strtolower($hash);$claimed[$hash]=true;}
    return array_values(array_filter($rows,static function(array $row)use($claimed,$request):bool{
        $hash=trim((string)($row['hash']??''));if($request['asset_code']!=='SOL')$hash=strtolower($hash);
        return !isset($claimed[$hash]);
    }));
}

/** Only verified incoming transfers on this dedicated receiver and inside its invoice window. */
function app_panel_tiers_candidates(array $request, array $rows, ?callable $verify = null, array $seen = [], bool $includeLateForReview = false): array
{
    $payment=app_panel_tiers_payment_row($request);$start=app_timestamp_from_utc_datetime($request['created_at']);$finality=min(time(),app_timestamp_from_utc_datetime($request['check_until']));$matches=[];
    foreach($rows as $row){
        $timestamp=(int)($row['timestamp']??0);$amount=(float)($row['amount_crypto']??0);
        if($timestamp<$start||$timestamp>$finality||(!$includeLateForReview&&!app_panel_tiers_in_window($request,$timestamp,(string)($row['hash']??''),$seen))||($row['direction']??'')!=='incoming'||!is_finite($amount)||$amount<=0)continue;
        $verified=$verify?$verify($payment,$row):admin_crypto_verified_incoming_row($payment,$row);if(!$verified)continue;
        $timestamp=(int)($verified['timestamp']??0);$amount=(float)($verified['amount_crypto']??0);$hash=(string)($verified['hash']??'');
        if($hash===''||$timestamp<$start||$timestamp>$finality||(!$includeLateForReview&&!app_panel_tiers_in_window($request,$timestamp,$hash,$seen))||!is_finite($amount)||$amount<=0)continue;
        $matches[$hash]=$verified;
    }
    return array_values($matches);
}

function app_panel_tiers_apply(Mysql_ks $db, array $request, array $candidates): bool
{
    $id=(int)$request['id'];$db->start();
    try{
        $config=$db->select_user('SELECT * FROM panel_subscription_config WHERE id=1 FOR UPDATE');
        $row=$db->select_user("SELECT * FROM panel_subscription_requests WHERE id={$id} FOR UPDATE");
        if(empty($config['enabled'])||!$row||app_timestamp_from_utc_datetime($row['check_until'])<time()||!in_array($row['status'],['pending','confirming','partial','cancelled','cancelled_review'],true)){$db->query('ROLLBACK');return false;}
        foreach(['amount_crypto','wallet_address','asset_code','network_code','exchange_rate','amount_fiat','created_at','months','tier','quoted_days'] as $key)if((string)$row[$key]!== (string)$request[$key])throw new RuntimeException('changed');
        $network=$db->escape($row['network_code']);$newHash='';
        $seen=app_panel_tiers_observations($db,$row);$timingReview=false;
        foreach($candidates as $candidate){
            // The network verifier runs before this transaction. Recheck bounds against the locked invoice.
            $timestamp=(int)($candidate['timestamp']??0);
            $decimals=app_panel_tiers_assets()[$row['asset_code']]['decimals'];
            $units=app_panel_tiers_amount_units($candidate['amount_crypto']??null,$decimals);
            $hash=trim((string)($candidate['hash']??''));
            if($row['asset_code']!=='SOL')$hash=strtolower($hash);
            if($hash===''||($candidate['direction']??'')!=='incoming'||$units===null||$units<=0
                ||$timestamp<app_timestamp_from_utc_datetime($row['created_at'])||$timestamp>min(time(),app_timestamp_from_utc_datetime($row['check_until'])))continue;
            $safeHash=$db->escape($hash);
            $used=$db->select_user("SELECT id FROM panel_subscription_transfers WHERE network_code='{$network}' AND transaction_hash='{$safeHash}' LIMIT 1");
            $customerUsed=$db->select_user("SELECT id FROM crypto_deposit_transactions WHERE transaction_hash='{$safeHash}' LIMIT 1");
            if($used||$customerUsed)continue;
            if(!app_panel_tiers_in_window($row,$timestamp,$hash,$seen))$timingReview=true;
            if(!$db->insert(['request_id','network_code','transaction_hash','amount_crypto','received_at'],[$id,$row['network_code'],$hash,app_panel_tiers_units_amount($units,$decimals),gmdate('Y-m-d H:i:s',$timestamp)],'panel_subscription_transfers'))throw new RuntimeException('storage');
            $newHash=$hash;
        }
        $sum=$db->select_user("SELECT COALESCE(SUM(amount_crypto),0) AS total FROM panel_subscription_transfers WHERE request_id={$id}");
        if(!is_array($sum))throw new RuntimeException('Cannot read received total.');
        $received=(float)$sum['total'];$days=app_panel_tiers_granted_days((int)$row['quoted_days'],$sum['total'],$row['amount_crypto'],app_panel_tiers_assets()[$row['asset_code']]['decimals']);
        if($received<=0){$db->query('COMMIT');return false;}
        if(!$db->update_using_id(['received_crypto'],[$sum['total']],'panel_subscription_requests',$id))throw new RuntimeException('storage');
        $row['received_crypto']=$sum['total'];
        if(in_array($row['status'],['cancelled','cancelled_review'],true)){
            if(!$db->query("UPDATE panel_subscription_requests SET status='cancelled_review',paid_at=UTC_TIMESTAMP(),open_slot=NULL WHERE id={$id}"))throw new RuntimeException('storage');
            $row['status']='cancelled_review';
            if($newHash!=='')app_panel_tiers_queue_email($db,$config,$row,$newHash,'',0);
            if(!$db->query('COMMIT'))throw new RuntimeException('storage');return false;
        }
        if($timingReview){
            if(!$db->query("UPDATE panel_subscription_requests SET status='review',paid_at=UTC_TIMESTAMP(),open_slot=1 WHERE id={$id}"))throw new RuntimeException('storage');
            $row['status']='review';$row['timing_review']=true;
            app_panel_tiers_queue_email($db,$config,$row,$newHash,'',0);
            if(!$db->query('COMMIT'))throw new RuntimeException('storage');return false;
        }
        if($days===0){
            if($row['status']!=='partial'){
                // Keep the original locked rate for one bounded top-up window, then allow finality.
                if(!$db->query("UPDATE panel_subscription_requests SET status='partial',pay_before=check_until,check_until=DATE_ADD(check_until,INTERVAL 24 HOUR) WHERE id={$id}"))throw new RuntimeException('storage');
            }
            if($newHash!=='')app_panel_tiers_queue_email($db,$config,$row,$newHash,'',0);
            if(!$db->query('COMMIT'))throw new RuntimeException('storage');return false;
        }
        $settings=$db->select_user('SELECT * FROM app_settings WHERE id=1 FOR UPDATE');
        app_panel_tiers_capture($db);$policy=app_panel_tiers_policy($config,app_panel_tiers_usage($db),$settings);
        if(!$policy['can_renew']||$policy['tier']>(int)$row['tier']||!in_array((int)$row['months'],$policy['allowed_months'],true)){
            if(!$db->query("UPDATE panel_subscription_requests SET status='review',paid_at=UTC_TIMESTAMP(),open_slot=1 WHERE id={$id}"))throw new RuntimeException('storage');
            $row['status']='review';
            app_panel_tiers_queue_email($db,$config,$row,$newHash,'',0);
            if(!$db->query('COMMIT'))throw new RuntimeException('storage');return false;
        }
        $date=app_panel_tiers_extended_date($settings['admin_panel_subscription_paid_until']??null,(int)$row['months'],null,$days);
        $previous=$settings['admin_panel_subscription_paid_until']??null;
        if(!$db->update_using_id(['admin_panel_subscription_paid_until'],[$date],'app_settings',1))throw new RuntimeException('storage');
        // Last transfer is informational. Deduplication is enforced for every partial payment separately.
        if(!$db->update_using_id(['status','open_slot','transaction_hash','paid_at','previous_paid_until','new_paid_until','granted_days'],['paid',null,$newHash!==''?$newHash:null,gmdate('Y-m-d H:i:s'),$previous,$date,$days],'panel_subscription_requests',$id))throw new RuntimeException('storage');
        app_panel_tiers_queue_email($db,$config,$row,$newHash,$date,$days);
        if(!$db->query('COMMIT'))throw new RuntimeException('storage');return true;
    }catch(Throwable $e){$db->query('ROLLBACK');throw $e;}
}

/** At most one invoice / 60 seconds per installation, shared by cron and the modal. */
function app_panel_tiers_process(Mysql_ks $db): array
{
    $result=['ok'=>true,'checked'=>0,'accepted'=>0];
    if(!app_panel_tiers_installed($db))return $result;
    $snapshot=app_panel_tiers_config($db);
    if(empty($snapshot['enabled'])||(!empty($snapshot['last_scan_at'])&&app_timestamp_from_utc_datetime($snapshot['last_scan_at'])>time()-60))return $result;
    if(!schema_column_exists($db,'panel_subscription_config','last_scan_at'))return ['ok'=>false,'checked'=>0,'accepted'=>0];
    $db->start();
    try {
        $config=$db->select_user('SELECT * FROM panel_subscription_config WHERE id=1 FOR UPDATE');
        if(empty($config['enabled'])||($config['last_scan_at']&&app_timestamp_from_utc_datetime($config['last_scan_at'])>time()-60)){$db->query('COMMIT');return $result;}
        // Expire abandoned cancelled invoices without a network call.
        if(!$db->query("UPDATE panel_subscription_requests SET status='cancelled_expired' WHERE status='cancelled' AND check_until<UTC_TIMESTAMP()"))throw new RuntimeException('storage');
        $request=$db->select_user("SELECT * FROM panel_subscription_requests WHERE status IN ('pending','confirming','partial','cancelled','cancelled_review') AND (check_until>=UTC_TIMESTAMP() OR open_slot=1) ORDER BY COALESCE(last_checked_at,'1000-01-01'),id LIMIT 1 FOR UPDATE");
        if(!$request){$db->query('COMMIT');return $result;}
        $id=(int)$request['id'];
        if(!$db->query('UPDATE panel_subscription_config SET last_scan_at=UTC_TIMESTAMP() WHERE id=1')||!$db->query("UPDATE panel_subscription_requests SET last_checked_at=UTC_TIMESTAMP() WHERE id={$id}"))throw new RuntimeException('storage');
        if(!$db->query('COMMIT'))throw new RuntimeException('storage');
    }catch(Throwable $e){$db->query('ROLLBACK');return ['ok'=>false,'checked'=>0,'accepted'=>0];}
    $result['checked']=1;
    if(app_timestamp_from_utc_datetime($request['check_until'])<time()){
        $db->query("UPDATE panel_subscription_requests SET status=IF(received_crypto>0,'review','expired'),open_slot=IF(received_crypto>0,1,NULL) WHERE id={$id} AND status IN ('pending','confirming','partial')");
        return $result;
    }
    app_crypto_network_budget(12,8);
    try{
        $rows=app_panel_tiers_remote_rows($request);
        $seen=app_panel_tiers_observe($db,$request,$rows);
        $unclaimed=app_panel_tiers_unclaimed_rows($db,$request,$rows);
        // Settle eligible payments before considering ambiguous late receipts for review.
        $candidates=app_panel_tiers_candidates($request,$unclaimed,null,$seen) ?: app_panel_tiers_candidates($request,$unclaimed,null,$seen,true);
        if($candidates){$result['accepted']=app_panel_tiers_apply($db,$request,$candidates)?1:0;if($result['accepted'])return $result;}
        $request=$db->select_user("SELECT * FROM panel_subscription_requests WHERE id={$id}");
        if(!$request||!in_array($request['status'],['pending','confirming','partial'],true))return $result;
        if(app_timestamp_from_utc_datetime($request['check_until'])<time()){
            // No auto-credit after the finality window. Old payments are never reused by a new quote.
            $db->query("UPDATE panel_subscription_requests SET status=IF(received_crypto>0,'review','expired'),open_slot=IF(received_crypto>0,1,NULL) WHERE id={$id} AND status IN ('pending','confirming','partial')");
        }elseif(app_timestamp_from_utc_datetime($request['pay_before'])<time()){
            $db->query("UPDATE panel_subscription_requests SET status='confirming' WHERE id={$id} AND status='pending'");
        }
    }catch(Throwable $e){$result['ok']=false;}
    return $result;
}

/** Queue once, in the same transaction as the access extension. SMTP runs in the existing worker. */
function app_panel_tiers_queue_email(Mysql_ks $db, array $config, array $request, string $hash, string $paidUntil, int $days): void
{
    if (empty($config['notify_admin'])) return;
    $admin=$db->select_user("SELECT email FROM admin_users WHERE login_name='admin' AND status='active' LIMIT 1");
    if (!is_array($admin) || !filter_var((string)($admin['email']??''),FILTER_VALIDATE_EMAIL)) return;
    $settings=$db->select_user('SELECT * FROM app_settings WHERE id=1');
    $locale=app_normalize_email_locale((string)($settings['default_locale_code']??'pl'));$pl=$locale==='pl';
    $e=static fn($value):string=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
    $review=in_array($request['status']??'',['review','cancelled_review'],true);
    $subject=($review?($pl?'Wpłata za panel — weryfikacja':'Panel payment — review required'):($days>0?($pl?'Opłacono dostęp do panelu':'Panel access payment confirmed'):($pl?'Wpłata za panel — potrzebna dopłata':'Panel payment — top-up required'))).' · Tier '.(int)$request['tier'];
    $decimals=app_panel_tiers_assets()[$request['asset_code']]['decimals'];
    $received=app_panel_tiers_units_amount(app_panel_tiers_amount_units($request['received_crypto'],$decimals)??0,$decimals);
    $requested=app_panel_tiers_units_amount(app_panel_tiers_amount_units($request['amount_crypto'],$decimals)??0,$decimals);
    $body='<h2>'.($review?($pl?'Wpłata wykryta — wymagana weryfikacja':'Payment received — review required'):($days>0?($pl?'Płatność potwierdzona':'Payment confirmed'):($pl?'Wpłata częściowa — potrzebna dopłata':'Partial payment — top-up required'))).'</h2>'
        .'<p>'.($pl?'Płatność':'Payment').' #'.(int)$request['id'].' · Tier '.(int)$request['tier'].' · '.(int)$request['months'].' '.($pl?'mies.':'month(s)').'</p>'
        .'<p>'.($pl?'Cena płatności':'Invoice price').': '.$e($request['amount_fiat']).' '.$e($request['currency_code'])
        .'<br>'.($pl?'Kwota do wpłaty':'Requested amount').': '.$e($requested).' '.$e($request['asset_code'])
        .'<br>'.($pl?'Otrzymano łącznie':'Total received').': '.$e($received).' '.$e($request['asset_code']).' ('.$e(number_format((float)$received*(float)$request['exchange_rate'],2,'.','')).' '.$e($request['currency_code']).')'
        .'<br>'.($pl?'Zablokowany kurs':'Locked rate').': 1 '.$e($request['asset_code']).' = '.$e($request['exchange_rate']).' '.$e($request['currency_code'])
        .'<br>'.($pl?'Sieć':'Network').': '.$e($request['network_code']).'</p>';
    $transfers=$db->select_full_user('SELECT transaction_hash,amount_crypto FROM panel_subscription_transfers WHERE request_id='.(int)$request['id'].' ORDER BY id');
    if($transfers===false)throw new RuntimeException('Cannot read payment transfers.');
    $body.='<p>'.($pl?'Transakcje uwzględnione w płatności':'Transfers included in this payment').':</p><ul>';
    foreach($transfers as $transfer){
        $amount=app_panel_tiers_units_amount(app_panel_tiers_amount_units($transfer['amount_crypto'],$decimals)??0,$decimals);
        $body.='<li>'.$e($transfer['transaction_hash']).' · '.$e($amount).' '.$e($request['asset_code']).'</li>';
    }
    $body.='</ul>';
    if(!empty($request['timing_review']))$body.='<p>'.($pl?'Wpłatę potwierdzono po terminie, ale brakuje dowodu nadania w czasie płatności. Zweryfikuj transakcję przed dodaniem dni.':'The transfer was confirmed after the deadline, but its on-time broadcast could not be established. Review the transaction before granting access.').'</p>';
    if(($request['status']??'')==='cancelled_review')$body.='<p>'.($pl?'Wpłata dotyczy anulowanej płatności. Nie została przeniesiona na nową płatność.':'This transfer belongs to a cancelled invoice and was not reassigned to a new payment.').'</p>';
    $body.='<p>'.($pl?'Dodane dni':'Days added').': <strong>'.$days.'</strong> / '.(int)$request['quoted_days'].'</p>';
    if($days>0)$body.='<p>'.($pl?'Dostęp przedłużony do':'Access renewed until').': <strong>'.$e($paidUntil).'</strong></p>';
    elseif($review)$body.='<p>'.($pl?'Data dostępu nie została zmieniona. Sprawdź wpłatę w Admin Mode.':'The access date has not changed. Review this payment in Admin Mode.').'</p>';
    else $body.='<p>'.($pl?'Do pełnej kwoty brakuje':'Remaining to pay in full').': <strong>'.$e(app_panel_tiers_remaining($request)).' '.$e($request['asset_code']).'</strong><br>'.($pl?'Dostęp nie został jeszcze przedłużony.':'Access has not yet been extended.').'</p>';
    $body=app_email_wrap_html($settings,$body,$locale);
    $key=$review?'panel-access-review:'.(int)$request['id'].(($request['status']??'')==='cancelled_review'?':'.substr(hash('sha256',$hash),0,32):''):($days>0?'panel-access-paid:'.(int)$request['id']:'panel-access-partial:'.(int)$request['id'].':'.substr(hash('sha256',$hash),0,32));
    if (!$db->query("INSERT INTO outbound_email_queue (to_email,subject,body_html,status,scheduled_at,dedupe_key) VALUES ('".$db->escape($admin['email'])."','".$db->escape($subject)."','".$db->escape($body)."','pending',NOW(),'{$key}') ON DUPLICATE KEY UPDATE dedupe_key=outbound_email_queue.dedupe_key")) throw new RuntimeException('Cannot queue payment receipt.');
}
