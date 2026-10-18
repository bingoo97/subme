<?php
/** Dedicated _test_ DB only. No live licence dates, customer data or external requests. */
declare(strict_types=1);
if (strpos((string)getenv('DB_NAME'), '_test_') === false || !getenv('APP_CRYPTO_CACHE_DIR')) throw new RuntimeException('Isolated test database/cache required.');
if (($argv[1] ?? '') === '--customer') {
    require_once dirname(__DIR__) . '/bootstrap/application.php';
    app_crypto_network_budget(0, 0);
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2) . '/public_html';
    $_GET = []; $_POST = [];
    ob_start();
    register_shutdown_function(static function (): void {
        $html = ob_get_clean();
        $status = http_response_code();
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        echo json_encode(['status' => $status, 'maintenance' => strpos($html, 'Technical Maintenance') !== false, 'error' => error_get_last()]);
    });
    require dirname(__DIR__, 2) . '/public_html/index.php';
    exit;
}
require_once dirname(__DIR__) . '/admin/bootstrap.php';
app_crypto_network_budget(0, 0);
$db = Mysql_ks::get_instance();
if (($argv[1] ?? '') === '--enforce') { echo json_encode(admin_app_settings($db)['maintenance_mode'] ?? 0); exit; }
$checks = 0;
function subscription_check(bool $value, string $label): void { global $checks; if (!$value) throw new RuntimeException($label); $checks++; }
function subscription_sql(string $query): void { global $db; if (!$db->query($query)) throw new RuntimeException('Fixture query failed'); }
function updates(): int { global $db; return (int)$db->select_user("SHOW SESSION STATUS LIKE 'Com_update'")['Value']; }
$zone = new DateTimeZone(app_runtime_timezone_name());
foreach (['2024-02-29', '2026-03-29', '2026-10-25', '2026-12-31'] as $date) {
    $settings = ['admin_panel_subscription_paid_until' => $date];
    $end = (new DateTimeImmutable($date . ' 23:59:59', $zone))->getTimestamp();
    subscription_check(app_panel_subscription_paid_until_timestamp($settings) === $end, 'Deadline respects leap/DST/year boundary: ' . $date);
    subscription_check(!app_panel_subscription_is_expired($settings, $end), 'Paid date includes its last second');
    subscription_check(app_panel_subscription_is_expired($settings, $end + 1), 'Next midnight expires access');
    $notice = admin_topbar_admin_panel_subscription_notice($settings, 7, $end + 1);
    subscription_check($notice['is_expired'] && $notice['show'], 'Dashboard agrees with customer expiry boundary');
}
foreach (['', '0000-00-00', '2026-02-31', '2026-13-01', 'not-a-date', '2026-10-18 12:00:00'] as $date) {
    $settings = ['admin_panel_subscription_paid_until' => $date];
    subscription_check(!app_panel_subscription_is_expired($settings), 'Unset/invalid date cannot suspend site: ' . $date);
    subscription_check(!admin_topbar_admin_panel_subscription_notice($settings)['show'], 'Unset/invalid date cannot raise a false reminder');
}
$validDate = '2026-12-31';
$end = app_panel_subscription_paid_until_timestamp(['admin_panel_subscription_paid_until' => $validDate]);
$settings = ['admin_panel_subscription_paid_until' => $validDate];
subscription_check(!admin_topbar_admin_panel_subscription_notice($settings, 7, $end - 7 * 86400)['show'], 'No urgent warning seven full days before expiry');
subscription_check(admin_topbar_admin_panel_subscription_notice($settings, 7, $end - 6 * 86400)['show'], 'Urgent warning below the existing seven-day threshold');

admin_app_settings($db);
subscription_sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash,status) VALUES(1,1,'panel-one','panel-one@example.invalid','unused','active'),(2,1,'panel-two','panel-two@example.invalid','unused','active'),(3,1,'panel-inactive','panel-inactive@example.invalid','unused','inactive'),(4,1,'panel-invalid','invalid-recipient','unused','active')");
$future = date('Y-m-d', time() + 30 * 86400);
$past = date('Y-m-d', time() - 2 * 86400);
subscription_sql("INSERT INTO app_settings(id,site_name,site_title,site_url,support_email,default_currency_id,maintenance_mode,admin_panel_subscription_paid_until) VALUES(1,'Fixture','Fixture','http://localhost','test@example.invalid',2,0,'{$future}') ON DUPLICATE KEY UPDATE maintenance_mode=0,admin_panel_subscription_paid_until='{$future}'");
$before = updates();
$active = admin_app_settings($db);
subscription_check(empty($active['maintenance_mode']) && updates() === $before, 'Valid panel causes no maintenance write');
subscription_sql("UPDATE app_settings SET admin_panel_subscription_paid_until='{$past}',maintenance_mode=0 WHERE id=1");
$before = updates();
$expired = admin_app_settings($db);
subscription_check(!empty($expired['maintenance_mode']) && !empty($expired['technical_break']), 'Expired panel enables both admin and customer maintenance flags');
subscription_check(updates() === $before + 2, 'One maintenance update and one notification stamp are persisted');
$before = updates();
admin_app_settings($db); admin_app_settings($db);
$customer = app_fetch_settings($db);
subscription_check(!empty($customer['technical_break']) && updates() === $before, 'Repeated reads reuse ON without further updates');
$normalized = app_enforce_panel_subscription_maintenance($db, ['admin_panel_subscription_paid_until' => $past, 'maintenance_mode' => 1, 'technical_break' => 0, 'admin_panel_subscription_notified_until' => $past]);
subscription_check($normalized['technical_break'] === 1 && updates() === $before, 'Legacy customer flag stays ON even when already enabled');
subscription_check((int)$db->select_user('SELECT maintenance_mode FROM app_settings WHERE id=1')['maintenance_mode'] === 1, 'Admin settings switch is ON in the database');
$process = proc_open([PHP_BINARY, __FILE__, '--customer'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); foreach ($pipes as $pipe) fclose($pipe);
$exit = proc_close($process); $response = json_decode($out, true);
subscription_check($exit === 0 && is_array($response), 'Customer front controller renders with expired subscription: ' . substr($err . $out, 0, 300));
subscription_check($response['status'] === 503 && $response['maintenance'], 'Customer receives maintenance screen and HTTP 503');
subscription_check(!empty(admin_app_settings($db)['maintenance_mode']), 'Admin settings remain accessible during maintenance');
subscription_sql("UPDATE app_settings SET admin_panel_subscription_paid_until='{$future}' WHERE id=1");
subscription_check(!empty(app_fetch_settings($db)['technical_break']), 'Renewal never silently disables an existing maintenance switch');
subscription_sql('UPDATE app_settings SET maintenance_mode=0 WHERE id=1');
subscription_check(empty(app_fetch_settings($db)['technical_break']), 'Renewed panel can be reopened normally');
$before = updates();
subscription_sql("UPDATE app_settings SET maintenance_mode=1 WHERE id=1");
$active = app_fetch_settings($db);
subscription_check(!empty($active['technical_break']) && updates() === $before + 1, 'Manual maintenance remains enabled with a valid subscription');
// One queued message per active admin, no SMTP in a customer/admin page request.
$queue = $db->select_full_user("SELECT to_email,status,attempt_count,body_html FROM outbound_email_queue ORDER BY id");
subscription_check(count($queue) === 2, 'Only active administrators with valid emails are notified');
subscription_check(array_column($queue,'to_email') === ['panel-one@example.invalid','panel-two@example.invalid'], 'Recipients are administrator accounts, not customers or inactive accounts');
subscription_check($queue[0]['status'] === 'pending' && (int)$queue[0]['attempt_count'] === 0, 'Delivery is queued for the worker, never SMTP in a page request');
subscription_check(strpos($queue[0]['body_html'], 'maintenance') !== false && strpos($queue[0]['body_html'], 'admin panel remains accessible') !== false, 'Email explains maintenance and retained admin access');

$nextExpiry = date('Y-m-d', time() - 3 * 86400);
subscription_sql("UPDATE app_settings SET admin_panel_subscription_paid_until='{$nextExpiry}',maintenance_mode=0 WHERE id=1");
$children=[];
for ($i=0;$i<8;$i++) { $proc=proc_open([PHP_BINARY,__FILE__,'--enforce'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$children[]=[$proc,$pipes]; }
foreach($children as [$proc,$pipes]) { $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);subscription_check(proc_close($proc)===0 && json_decode($out,true)===1,'Parallel expiry read succeeds: '.substr($err,0,100)); }
subscription_check((int)$db->select_user('SELECT COUNT(*) n FROM outbound_email_queue')['n']===4,'Eight concurrent expiry reads enqueue each admin only once');

// No implicit COMMIT of an existing order/payment transaction.
$thirdExpiry = date('Y-m-d', time() - 4 * 86400);
subscription_sql('START TRANSACTION');
subscription_sql("UPDATE app_settings SET admin_panel_subscription_paid_until='{$thirdExpiry}',maintenance_mode=0 WHERE id=1");
app_fetch_settings($db);
subscription_check((int)$db->select_user('SELECT COUNT(*) n FROM outbound_email_queue')['n']===6,'Notification joins the existing transaction');
subscription_sql('ROLLBACK');
subscription_check((int)$db->select_user('SELECT COUNT(*) n FROM outbound_email_queue')['n']===4,'Outer rollback removes queued notifications');
subscription_check($db->select_user('SELECT admin_panel_subscription_paid_until d FROM app_settings WHERE id=1')['d']===$nextExpiry,'Notification never commits unrelated caller changes');

// A partial queue error is retryable without emailing the first recipient twice.
subscription_sql("UPDATE app_settings SET admin_panel_subscription_paid_until='{$thirdExpiry}',maintenance_mode=0 WHERE id=1");
subscription_sql("ALTER TABLE outbound_email_queue ADD CONSTRAINT panel_fixture_queue_error CHECK (to_email <> 'panel-two@example.invalid' OR dedupe_key NOT LIKE 'panel-expired:{$thirdExpiry}:%')");
app_fetch_settings($db);
subscription_check((int)$db->select_user('SELECT COUNT(*) n FROM outbound_email_queue')['n']===5,'First recipient survives a later queue failure');
subscription_check($db->select_user('SELECT admin_panel_subscription_notified_until d FROM app_settings WHERE id=1')['d']===$nextExpiry,'Queue error does not mark the expiry as notified');
subscription_sql('ALTER TABLE outbound_email_queue DROP CHECK panel_fixture_queue_error');
app_fetch_settings($db);
subscription_check((int)$db->select_user('SELECT COUNT(*) n FROM outbound_email_queue')['n']===6,'Retry adds only the missing administrator notification');
$before=updates();app_fetch_settings($db);app_fetch_settings($db);
subscription_check(updates()===$before,'Warm expired reads perform no notification writes');
echo "PASS: {$checks} subscription/date/maintenance/frontend assertions\n";
