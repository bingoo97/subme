<?php
// The v2 payment flow uses authenticated requests and server-side maintenance.
require_once __DIR__ . '/config/mysql.php';
require_once __DIR__ . '/bootstrap/session.php';
require_once __DIR__ . '/bootstrap/application.php';
app_start_session();
header('Cache-Control: no-store');
// A bounded, read-only status check. Blockchain scanning stays in maintenance.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['ticket_status'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    $customerId=(int)($_SESSION['id']??0);
    if($customerId<=0){http_response_code(401);exit;}
    if(!app_uses_v2_schema($db)){http_response_code(410);exit;}
    $ticket=filter_var($_GET['ticket_status'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$ticket){http_response_code(422);exit;}
    $now=microtime(true);
    if($now-(float)($_SESSION['crypto_ticket_status_checked']??0)<15){http_response_code(429);header('Retry-After: 15');exit;}
    $_SESSION['crypto_ticket_status_checked']=$now;
    session_write_close();
    $row=$db->select_user("SELECT r.status,r.received_crypto_amount FROM crypto_deposit_requests r
        INNER JOIN customers c ON c.id=r.customer_id AND c.status='active'
        WHERE r.id=".(int)$ticket." AND r.customer_id={$customerId} LIMIT 1");
    if(!$row){http_response_code(404);exit;}
    echo json_encode(['ok'=>true,'status'=>(string)$row['status'],'detected'=>$row['status']==='awaiting_review'&&(float)$row['received_crypto_amount']>0]);exit;
}

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
