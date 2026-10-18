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
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit;
}
$appSettings = app_fetch_settings($db);
$messages = admin_load_messages(admin_current_locale());
$canViewOrders = admin_user_can_access_route($adminUser, 'orders');
$taskRows = $canViewOrders ? admin_topbar_notification_order_rows($db, 8) : [];
$taskTotal = $canViewOrders ? admin_topbar_notification_order_count($db) : 0;
$taskHtml = admin_topbar_order_tasks_html($taskRows, $taskTotal, $messages);
$badgeCount = admin_topbar_notification_count($db, $appSettings, $taskTotal);
if (admin_crypto_wallet_free_count($db) <= 0) $badgeCount++;
if (admin_bank_transfers_enabled($appSettings)) {
    $currency = admin_default_currency_row($db);
    if (!app_available_bank_account_rows($db, (string)($currency['code'] ?? 'USD'), $appSettings)) $badgeCount++;
}
// The refresh is read-only; leave the session available to other admin requests.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
echo json_encode([
    'ok' => true,
    'badge_count' => $badgeCount,
    'orders_html' => $taskHtml,
    'orders_revision' => hash('sha256', $taskHtml),
    'orders_count' => $taskTotal,
]);
