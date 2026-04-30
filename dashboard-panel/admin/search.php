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
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

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
    $cooldownSeconds = 20;

    if ($lastRequestAt > 0 && ($now - $lastRequestAt) < $cooldownSeconds) {
        http_response_code(429);
        echo json_encode([
            'ok' => false,
            'message' => admin_t($messages, 'payment_quick_create_cooldown_error', 'Wait 20 seconds before creating another payment request for this user.'),
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
