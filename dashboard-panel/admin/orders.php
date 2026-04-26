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

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

if ($action === 'search_customers') {
    $query = trim((string)($_GET['q'] ?? ''));
    if ($query === '' || (!ctype_digit($query) && strlen($query) < 2)) {
        echo json_encode([
            'ok' => true,
            'customers' => [],
        ]);
        exit;
    }

    $rows = admin_search_customer_rows($db, $query, 8);
    $customers = [];
    foreach ($rows as $row) {
        $customers[] = [
            'id' => (int)($row['id'] ?? 0),
            'email' => (string)($row['email'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'locale_code' => (string)($row['locale_code'] ?? ''),
        ];
    }

    echo json_encode([
        'ok' => true,
        'customers' => $customers,
    ]);
    exit;
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

http_response_code(400);
echo json_encode([
    'ok' => false,
    'message' => 'Unsupported action.',
]);
