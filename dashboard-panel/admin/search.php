<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

admin_send_security_headers();
admin_start_session();

header('Content-Type: application/json; charset=utf-8');

$db = Mysql_ks::get_instance();
$adminUser = admin_load_session_user($db);

if ($adminUser === null) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Unauthorized.',
    ]);
    exit;
}

$currentLocale = isset($_SESSION['admin_locale']) ? admin_normalize_locale((string)$_SESSION['admin_locale']) : 'pl';
$messages = admin_load_messages($currentLocale);
$rawAction = $_GET['action'] ?? $_POST['action'] ?? '';
$action = is_string($rawAction) ? trim($rawAction) : '';

if (str_starts_with($action, 'crypto_quick_create_')) {
    require_once __DIR__.'/crypto_quick.php';
    if (!admin_user_can_access_route($adminUser, 'payments')) { http_response_code(403); echo json_encode(['ok'=>false]); exit; }
    header('Cache-Control: no-store');
    foreach(['q','customer_id','asset_id','product_id','order_id','purpose','amount','_csrf'] as $field) {
        if(isset($_POST[$field])&&!is_scalar($_POST[$field]) || isset($_GET[$field])&&!is_scalar($_GET[$field])) {http_response_code(422);echo json_encode(['ok'=>false]);exit;}
    }
    $customerId = (int)($_POST['customer_id'] ?? $_GET['customer_id'] ?? 0);
    if ($action === 'crypto_quick_create_search') {
        $query = trim((string)($_GET['q'] ?? ''));
        if (strlen($query)>128) { http_response_code(422); echo json_encode(['ok'=>false]); exit; }
        $rows = strlen(ltrim($query,'@'))>=2 ? admin_search_customer_rows($db,$query) : [];
        echo json_encode(['ok'=>true,'html'=>admin_render_payment_customer_search_results_html($rows,$messages,$query)]); exit;
    }
    if ($action === 'crypto_quick_create_options') {
        $data = admin_quick_crypto_payment_data($db,$customerId,$messages);
        $data['can_order'] = admin_user_can_access_route($adminUser,'orders');
        $data['can_wallet'] = admin_user_can_access_route($adminUser,'crypto-wallets');
        $data['orders'] = [];
        foreach($data['product_options']??[] as $i=>$product) {
            $code=(string)($data['currency']['code']??'');
            $oldAmount=number_format((float)$product['amount'],2,'.','').' '.$code;
            $data['product_options'][$i]['label']=str_replace($oldAmount,admin_format_money_value($product['amount'],$code),$product['label']);
        }
        if (!empty($data['ok']) && $data['can_order']) {
            $currency=(int)admin_chat_payment_currency_context($db)['currency_id'];
            $orders=$db->select_full_user("SELECT o.id,o.total_amount,p.name,p.provider_id,c.code FROM orders o
                INNER JOIN products p ON p.id=o.product_id INNER JOIN currencies c ON c.id=o.currency_id
                WHERE o.customer_id={$customerId} AND o.status='pending_payment' AND o.payment_status='unpaid'
                  AND o.total_amount>0 AND o.currency_id={$currency} ORDER BY o.id DESC LIMIT 100");
            foreach($orders?:[] as $order) if((int)$order['provider_id']===0 || in_array((int)$order['provider_id'],$data['visible_provider_ids']??[],true))
                $data['orders'][]=['id'=>(int)$order['id'],'label'=>'#'.$order['id'].' · '.$order['name'].' · '.admin_format_money_value($order['total_amount'],$order['code'])];
        }
        echo json_encode($data); exit;
    }
    if ($action === 'crypto_quick_create_submit') {
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST' || !admin_csrf_is_valid($_POST['_csrf']??'')) { http_response_code(403); echo json_encode(['ok'=>false]); exit; }
        $purpose=(string)($_POST['purpose']??'');
        if ($purpose !== 'topup' && !admin_user_can_access_route($adminUser,'orders')) { http_response_code(403); echo json_encode(['ok'=>false]); exit; }
        $key='admin_quick_crypto_payment_'.$customerId;
        if(time()-(int)($_SESSION[$key]??0)<5) {http_response_code(429);echo json_encode(['ok'=>false,'message'=>'Poczekaj chwilę i sprawdź listę płatności.']);exit;}
        $_SESSION[$key]=time();
        $result=admin_quick_create_crypto_payment_request($db,$customerId,(int)($_POST['asset_id']??0),$_POST['amount']??'',(int)$adminUser['id'],$messages,(int)($_POST['product_id']??0),$purpose,(int)($_POST['order_id']??0));
        if(empty($result['ok'])) {
            http_response_code(422);
            if($currentLocale==='pl' && ($result['message']??'')!=='pending_crypto_payment') {
                $errors=[
                    'Selected order is unavailable or already paid.'=>'Zamówienie jest niedostępne albo zostało już opłacone.',
                    'Order changed. Select it again.'=>'Dane zamówienia zmieniły się. Wybierz je ponownie.',
                    'This order already has a payment request.'=>'To zamówienie ma już płatność. Sprawdź ją przed utworzeniem kolejnej.',
                    'Selected product is not available for this user.'=>'Ten produkt nie jest dostępny dla wybranego klienta.',
                    'Crypto asset not available.'=>'Ta kryptowaluta nie ma dostępnego portfela lub aktualnego kursu. Sprawdź portfele i kursy.',
                    'No wallet available for this asset.'=>'Brak wolnego portfela dla tej kryptowaluty. Dodaj portfel i spróbuj ponownie.',
                    'This wallet already has an active payment request.'=>'Ten portfel obsługuje już inną płatność. Poczekaj na jej zakończenie albo wybierz inną kryptowalutę.',
                    'Wallet is no longer available.'=>'Portfel został już zajęty lub wyłączony. Wybierz kryptowalutę ponownie.',
                ];
                $result['message']=$errors[$result['message']??'']??'Nie udało się utworzyć płatności. Sprawdź dane i spróbuj ponownie.';
            }
        }
        else $result['crypto_quick']=['filter'=>'new','counts'=>admin_crypto_quick_counts($db),'html'=>admin_crypto_quick_html(admin_crypto_quick_rows($db,'new'),'new',null,admin_csrf_token(),$messages)];
        echo json_encode($result);exit;
    }
    http_response_code(422);echo json_encode(['ok'=>false]);exit;
}

if ($action === 'create_customer') {
    if (!admin_csrf_is_valid($_POST['_csrf'] ?? '')) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'message' => 'Invalid request.',
        ]);
        exit;
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $settings = admin_app_settings($db);
    $clientIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $result = admin_create_customer_from_email($db, $email, $settings, $clientIp);

    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode($result);
        exit;
    }

    echo json_encode($result);
    exit;
}

if ($action === 'payment_customer_search') {
    $query = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));

    if ($query === '' || (!ctype_digit($query) && strlen(ltrim($query, '@')) < 2)) {
        echo json_encode([
            'ok' => true,
            'html' => '',
            'count' => 0,
        ]);
        exit;
    }

    $customers = admin_search_customer_rows($db, $query);

    echo json_encode([
        'ok' => true,
        'html' => admin_render_payment_customer_search_results_html($customers, $messages, $query),
        'count' => count($customers),
    ]);
    exit;
}

if ($action === 'payment_customer_crypto_data') {
    $customerId = (int)($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
    $payload = admin_quick_crypto_payment_data($db, $customerId, $messages);

    if (empty($payload['ok'])) {
        http_response_code(422);
        echo json_encode($payload);
        exit;
    }

    echo json_encode($payload);
    exit;
}

if ($action === 'create_quick_crypto_payment_request') {
    if (!admin_csrf_is_valid($_POST['_csrf'] ?? '')) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'message' => 'Invalid request.',
        ]);
        exit;
    }

    $customerId = (int)($_POST['customer_id'] ?? 0);
    $assetId = (int)($_POST['asset_id'] ?? 0);
    $amount = $_POST['amount'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);
    $cooldownKey = 'admin_quick_crypto_payment_' . $customerId;
    $lastRequestAt = (int)($_SESSION[$cooldownKey] ?? 0);
    $now = time();
    $cooldownSeconds = 5;

    if ($lastRequestAt > 0 && ($now - $lastRequestAt) < $cooldownSeconds) {
        http_response_code(429);
        echo json_encode([
            'ok' => false,
            'message' => admin_t($messages, 'payment_quick_create_cooldown_error', 'Wait 5 seconds before creating another payment request for this user.'),
            'cooldown_remaining' => $cooldownSeconds - ($now - $lastRequestAt),
        ]);
        exit;
    }

    $result = admin_quick_create_crypto_payment_request($db, $customerId, $assetId, $amount, (int)($adminUser['id'] ?? 0), $messages, $productId);
    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode($result);
        exit;
    }

    $_SESSION[$cooldownKey] = $now;
    $result['cooldown_seconds'] = $cooldownSeconds;
    echo json_encode($result);
    exit;
}

$query = trim((string)($_GET['q'] ?? ''));
if (strlen($query) > 512) { http_response_code(422); echo json_encode(['ok'=>false]); exit; }
session_write_close();

if ($query === '' || (!ctype_digit($query) && strlen($query) < 2)) {
    echo json_encode([
        'ok' => true,
        'html' => '',
        'count' => 0,
    ]);
    exit;
}

$resultSets = [
    'customers' => admin_search_customer_rows($db, $query),
    'orders' => admin_search_order_rows($db, $query),
    'wallets' => admin_search_wallet_rows($db, $query),
];

$count = count($resultSets['customers']) + count($resultSets['orders']) + count($resultSets['wallets']);

echo json_encode([
    'ok' => true,
    'html' => admin_render_search_results_html($resultSets, $messages, $query),
    'count' => $count,
]);
