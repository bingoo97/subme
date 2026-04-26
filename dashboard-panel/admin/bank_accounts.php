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
    $accountId = (int)($_GET['account_id'] ?? 0);

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
        $state = $accountId > 0
            ? admin_bank_assignment_search_state($db, $accountId, (int)($row['id'] ?? 0))
            : ['disabled' => false, 'hint' => ''];

        $customers[] = [
            'id' => (int)($row['id'] ?? 0),
            'email' => (string)($row['email'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'locale_code' => (string)($row['locale_code'] ?? ''),
            'disabled' => !empty($state['disabled']),
            'hint' => (string)($state['hint'] ?? ''),
        ];
    }

    echo json_encode([
        'ok' => true,
        'customers' => $customers,
    ]);
    exit;
}

http_response_code(400);
echo json_encode([
    'ok' => false,
    'message' => 'Unsupported action.',
]);
