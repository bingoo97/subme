<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
admin_send_security_headers();
admin_start_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$db = Mysql_ks::get_instance();
$adminUser = admin_load_session_user($db);
if ($adminUser === null) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
if (!admin_user_can_access_route($adminUser, 'settings')) { http_response_code(403); echo json_encode(['ok'=>false]); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); echo json_encode(['ok'=>false]); exit; }
if (!admin_csrf_is_valid($_POST['_csrf'] ?? '')) { http_response_code(419); echo json_encode(['ok'=>false]); exit; }
$messages = admin_load_messages(admin_current_locale());
session_write_close();
$rows = admin_crypto_preview_network_status_rows($messages, true);
echo json_encode(['ok'=>true, 'html'=>admin_render_crypto_network_status_grid($rows,$messages)],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
