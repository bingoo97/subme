<?php
// The v2 payment flow uses authenticated requests and server-side maintenance.
require_once __DIR__ . '/config/mysql.php';
require_once __DIR__ . '/bootstrap/session.php';
require_once __DIR__ . '/bootstrap/application.php';
app_start_session();
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
if ((int)($_SESSION['id'] ?? 0) <= 0) { http_response_code(401); exit; }
if (app_uses_v2_schema($db)) { http_response_code(410); exit; }
require __DIR__ . '/vendor/autoload.php';
$smarty = new \Smarty\Smarty;
require __DIR__ . '/config/config.php';
if (empty($user['logged'])) { http_response_code(401); exit; }
$code = trim((string)($_POST['code'] ?? ''));
if ($code === '' || strlen($code) > 128) { http_response_code(422); exit; }
require __DIR__ . '/config/check_crypto.php';
$smarty->display('profil/payment_content.tpl');
