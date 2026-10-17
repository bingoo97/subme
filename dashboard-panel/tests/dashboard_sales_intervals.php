<?php
/** Extended deterministic audit. The imported regression harness enforces a dedicated _test_ DB. */
declare(strict_types=1);
require __DIR__ . '/dashboard_sales_regression.php';

function auditReset(): void
{
    sql('DELETE FROM order_renewals');
    sql('DELETE FROM orders');
    sql('DELETE FROM customer_balance_runtime_events');
}
function auditOrder(int $id, string $date, float $amount = 1.0, int $product = 2, int $currency = 2): void
{
    $amount = number_format($amount, 2, '.', '');
    sql("INSERT INTO orders (id,customer_id,product_id,total_amount,currency_id,status,payment_status,paid_at,created_at,expires_at)
        VALUES ({$id},1,{$product},{$amount},{$currency},'active','paid','{$date}','{$date}','2030-12-31 23:59:59')");
}
function auditRenewal(int $id, int $order, string $date, string $status = 'applied', int $paymentId = 0, float $amount = 2.0, string $type = 'crypto'): void
{
    sql("INSERT INTO order_renewals (id,order_id,customer_id,product_id,price_amount,currency_id,status,requested_at,applied_at,paid_at,payment_request_type,payment_request_id)
        VALUES ({$id},{$order},1,2,{$amount},2,'{$status}','{$date}','{$date}','{$date}','{$type}',{$paymentId})");
}
function sumCounts(array $rows): int
{
    return (int)array_sum(array_column($rows, 'paid_orders'));
}
function approx(float $actual, float $expected, string $label): void
{
    check(abs($actual - $expected) < 0.00001, $label . " ({$actual} vs {$expected})");
}
$zone = new DateTimeZone(app_runtime_timezone_name());
$references = [
    '2024-02-29 12:00:00', // leap day
    '2024-03-01 00:00:00', // exact leap-month boundary
    '2024-12-30 12:00:00', // ISO week crosses year
    '2025-01-01 00:00:00', // exact year boundary
    '2025-01-01 12:00:00',
    '2026-03-29 12:00:00', // 23-hour day
    '2026-04-01 00:00:00',
    '2026-10-25 12:00:00', // 25-hour day
    '2026-12-31 23:59:59',
    '2027-01-01 00:00:00',
];
foreach ($references as $reference) {
    auditReset();
    $asOf = new DateTimeImmutable($reference, $zone);
    $today = $asOf->setTime(0, 0);
    $month = $today->modify('first day of this month');
    $year = $today->setDate((int)$today->format('Y'), 1, 1);
    $range = $month->modify('-11 months');
    $fixtures = [
        $today->modify('-1 second'), $today, $today->modify('+1 second'),
        $month->modify('-1 second'), $month, $month->modify('+1 second'),
        $year->modify('-1 second'), $year, $year->modify('+1 second'),
        $asOf, $asOf->modify('+1 second'),
        $range->modify('-1 second'), $range, $range->modify('+1 second'),
        $month->modify('last day of previous month')->setTime(23, 59, 59),
    ];
    // A separate paid renewal for every purchase; activation may take place in another month.
    $oracle = [];
    foreach ($fixtures as $i => $fixture) {
        $id = $i + 1;
        $date = $fixture->format('Y-m-d H:i:s');
        auditOrder($id, $date, 1.25, 1);
        auditRenewal($id, $id, $date, $id % 2 ? 'applied' : 'paid_pending_activation', 100+$id, 2.75);
        sql("UPDATE order_renewals SET applied_at='2028-02-01 12:00:00' WHERE id={$id}");
        if ($date <= $reference) {
            $oracle[] = ['date'=>$date,'amount'=>1.25];
            $oracle[] = ['date'=>$date,'amount'=>2.75];
        }
    }
    $periods = admin_dashboard_period_metrics($db, $asOf);
    foreach ($periods as $key => $period) {
        $expected = array_values(array_filter($oracle, static fn(array $r): bool => $r['date'] >= $period['from'] && $r['date'] <= $period['to']));
        check($period['paid_orders'] === count($expected), "{$reference}: {$key} exact interval count");
        approx((float)($period['revenue_by_currency']['EUR'] ?? 0), array_sum(array_column($expected,'amount')), "{$reference}: {$key} revenue");
    }
    $daily = admin_dashboard_sales_series($db, 30, $asOf);
    check(count($daily) === 30, "{$reference}: exactly 30 calendar days");
    check(end($daily)['paid_orders'] === $periods['daily']['paid_orders'], "{$reference}: card and daily chart agree");
    $monthly = admin_dashboard_monthly_sales_series($db, 12, $asOf);
    check(count($monthly) === 12 && $monthly[0]['month_key'] === $range->format('Y-m'), "{$reference}: exact rolling 12-month range");
    check(end($monthly)['paid_orders'] === $periods['monthly']['paid_orders'], "{$reference}: card and monthly chart agree");
    $rangeOracle = array_filter($oracle, static fn(array $r): bool => $r['date'] >= $range->format('Y-m-d H:i:s'));
    check(sumCounts($monthly) === count($rangeOracle), "{$reference}: 12m total respects both endpoints");
    foreach ($monthly as $bucket) {
        $expected = array_values(array_filter($oracle, static fn(array $r): bool => substr($r['date'],0,7) === $bucket['month_key']));
        check($bucket['paid_orders'] === count($expected), "{$reference}: month {$bucket['month_key']} count");
        approx((float)($bucket['revenue_by_currency']['EUR'] ?? 0), array_sum(array_column($expected,'amount')), "{$reference}: month {$bucket['month_key']} revenue");
    }
    $summary = admin_dashboard_product_sales_summary($db, 120, null, $asOf);
    check((int)array_sum(array_column($summary,'transactions_total')) === count($oracle), "{$reference}: all-time summary includes every eligible transaction");
    $years = admin_dashboard_yearly_sales_series($db, 10, $asOf);
    check(sumCounts($years) === count($oracle), "{$reference}: yearly chart agrees with all-time summary");
    foreach ($years as $yearBucket) {
        $filtered = admin_dashboard_product_sales_summary($db, 120, $yearBucket['year'], $asOf);
        check((int)array_sum(array_column($filtered,'transactions_total')) === $yearBucket['paid_orders'], "{$reference}: year filter reconciles with yearly chart");
        $revenues = admin_dashboard_revenue_sum($filtered);
        approx((float)($revenues['EUR'] ?? 0), (float)$yearBucket['revenue_by_currency']['EUR'], "{$reference}: filtered-year revenue reconciles");
    }
}
// Legacy approval in December, request in November and activation in January.
auditReset();
$asOf = new DateTimeImmutable('2026-02-01 12:00:00', $zone);
auditOrder(1, '2024-01-01 12:00:00', 10);
auditRenewal(1, 1, '2024-11-01 12:00:00', 'applied', 101, 30);
sql("UPDATE order_renewals SET paid_at=NULL, applied_at='2025-01-01 12:00:00' WHERE id=1");
sql("INSERT INTO customer_balance_runtime_events (customer_id,source_type,source_key,direction,amount,created_at)
    VALUES (1,'payment_approval','crypto:101','credit',30,'2024-12-31 23:59:59')");
$old = admin_dashboard_product_sales_summary($db, 120, 2024, $asOf);
$new = admin_dashboard_product_sales_summary($db, 120, 2025, $asOf);
check((int)array_sum(array_column($old,'transactions_total')) === 2, 'Legacy renewal belongs to approval year, not activation year.');
check((int)array_sum(array_column($old,'active_sales_total')) === 1, 'Active-year filter uses the same recovered approval date.');
check((int)array_sum(array_column($new,'transactions_total')) === 0 && (int)array_sum(array_column($new,'active_sales_total')) === 0, 'No phantom sales or active entries in activation-only year.');
// Dedupe must ignore earlier ineligible rows, and distinguish payment channels.
foreach (['future','zero','cancelled_order','refunded_order','cancelled_renewal'] as $invalid) {
    auditReset();
    auditOrder(1, '2024-01-01 12:00:00');
    auditOrder(2, '2024-01-01 12:00:00');
    auditRenewal(1, 1, $invalid === 'future' ? '2027-01-01 12:00:00' : '2025-01-01 12:00:00', $invalid === 'cancelled_renewal' ? 'cancelled' : 'applied', 101, $invalid === 'zero' ? 0 : 2);
    if ($invalid === 'cancelled_order') sql("UPDATE orders SET status='cancelled' WHERE id=1");
    if ($invalid === 'refunded_order') sql("UPDATE orders SET payment_status='refunded' WHERE id=1");
    auditRenewal(2, 2, '2025-02-01 12:00:00', 'applied', 101);
    $source = admin_dashboard_sales_source_sql($db, $asOf);
    $valid = $db->select_user("SELECT COUNT(*) AS n FROM ({$source}) s WHERE sale_type='renewal'");
    check((int)$valid['n'] === 1, "Earlier {$invalid} must not hide the valid paid renewal.");
}
// More than the previous 120/250 row cap, cents and mixed currencies.
auditReset();
sql('DELETE FROM products');
for ($i=1; $i<=300; $i++) {
    sql("INSERT INTO products (id,name,slug,price_amount,currency_id,duration_hours) VALUES ({$i},'Package {$i}','audit-{$i}',0.01,2,720)");
    auditOrder($i, '2025-05-01 12:00:00', 0.01, $i, $i % 2 ? 1 : 2);
}
$large = admin_dashboard_product_sales_summary($db, 120, 2025, $asOf);
check(count($large) === 300, 'Summary must not silently truncate products at 120 or 250.');
check((int)array_sum(array_column($large,'transactions_total')) === 300, 'All 300 sales must contribute to the summary.');
$revenues = admin_dashboard_revenue_sum($large);
approx($revenues['EUR'], 1.50, 'EUR cents reconcile across 150 products');
approx($revenues['USD'], 1.50, 'USD cents remain separate across 150 products');
// Repeated hour: separate records count separately, even with identical local timestamps.
auditReset();
foreach ([1,2] as $id) auditOrder($id, '2026-10-25 02:30:00');
$repeat = admin_dashboard_period_metrics($db, new DateTimeImmutable('2026-10-25 04:00:00', $zone));
check($repeat['daily']['paid_orders'] === 2, 'Two sales in the repeated DST hour remain separate.');
// Empty ranges keep valid zero-filled intervals.
auditReset();
check(sumCounts(admin_dashboard_monthly_sales_series($db,12,$asOf)) === 0, 'Empty 12-month range stays zero.');
check(admin_dashboard_product_sales_summary($db,120,2025,$asOf) === [], 'Empty summary stays empty without phantom historic rows.');
// Real lifecycle guard: cancelled records and an unpaid skip-debit attempt must not become sales.
auditOrder(1, '2025-01-01 12:00:00');
auditRenewal(1,1,'2025-02-01 12:00:00','cancelled');
check(empty(app_apply_pending_order_renewal($db,1,'system',0,'',true)['ok']), 'Cancelled renewal cannot be applied.');
sql("UPDATE order_renewals SET status='pending_payment',paid_at=NULL WHERE id=1");
check(empty(app_apply_pending_order_renewal($db,1,'system',0,'',true)['ok']), 'Skipping debit requires evidence of payment.');
echo "PASS: {$checks} total regression and interval assertions\n";
// Exercise the production activation code, including a deliberately failed DB update.
auditReset();
auditOrder(1, '2025-01-01 12:00:00', 10, 1);
auditRenewal(1,1,'2025-02-01 12:00:00','paid_pending_activation',101,30);
sql("UPDATE order_renewals SET target_expires_at='2031-01-01 12:00:00' WHERE id=1");
$before = admin_dashboard_product_sales_summary($db,120,2025,$asOf);
sql("ALTER TABLE order_renewals ADD CONSTRAINT audit_reject_activation CHECK (status <> 'applied')");
try {
    $failed = app_apply_pending_order_renewal($db,1,'system',0,'',true);
    check(empty($failed['ok']), 'A failed activation write must return a failure.');
    $order = $db->select_user('SELECT product_id,total_amount FROM orders WHERE id=1');
    check((int)$order['product_id'] === 1 && (float)$order['total_amount'] === 10.0, 'Failed renewal write must roll back the order package and price.');
    check($db->select_user('SELECT status FROM order_renewals WHERE id=1')['status'] === 'paid_pending_activation', 'Failed activation preserves the paid pending sale.');
} finally {
    sql('ALTER TABLE order_renewals DROP CHECK audit_reject_activation');
}
$activated = app_apply_pending_order_renewal($db,1,'system',0,'',true);
check(!empty($activated['ok']), 'Real paid renewal activation succeeds.');
$after = admin_dashboard_product_sales_summary($db,120,2025,$asOf);
check(array_sum(array_column($before,'transactions_total')) === array_sum(array_column($after,'transactions_total')), 'Real activation does not add a second sale.');
check(admin_dashboard_revenue_sum($before) === admin_dashboard_revenue_sum($after), 'Real activation does not change paid revenue.');
$repeat = app_apply_pending_order_renewal($db,1,'system',0,'',true);
check(!empty($repeat['already_applied']), 'Real repeated activation is idempotent.');
echo "PASS: {$checks} final assertions, including activation rollback\n";
// A payment ID is unique within its channel, not across bank and crypto.
auditReset();
auditOrder(1,'2025-01-01 12:00:00');
auditRenewal(1,1,'2025-02-01 12:00:00','applied',101,2,'crypto');
auditRenewal(2,1,'2025-02-01 12:00:00','applied',101,2,'bank');
$source = admin_dashboard_sales_source_sql($db,$asOf);
check((int)$db->select_user("SELECT COUNT(*) AS n FROM ({$source}) s WHERE sale_type='renewal'")['n'] === 2, 'Same numeric ID in bank and crypto represents two distinct payments.');
sql('UPDATE order_renewals SET customer_id=999 WHERE id=1');
$source = admin_dashboard_sales_source_sql($db,$asOf);
check((int)$db->select_user("SELECT COUNT(*) AS n FROM ({$source}) s WHERE sale_type='renewal'")['n'] === 1, 'Inconsistent customer/order links cannot inflate sales.');
// Report queries share one DB snapshot even while another connection records a sale.
auditReset();
auditOrder(1,'2025-01-01 12:00:00');
admin_dashboard_sales_currency($db);
sql('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
sql('START TRANSACTION WITH CONSISTENT SNAPSHOT');
$initial = admin_dashboard_product_sales_summary($db,120,null,$asOf);
$writer = new mysqli(getenv('DB_HOST'),getenv('DB_USER'),getenv('DB_PASS'),getenv('DB_NAME'));
check(!$writer->connect_errno, 'Second test connection opens.');
$writer->query('SET FOREIGN_KEY_CHECKS=0');
check((bool)$writer->query("INSERT INTO orders (id,customer_id,product_id,total_amount,currency_id,status,payment_status,paid_at,created_at)
    VALUES (2,1,2,1,2,'active','paid','2025-01-01 13:00:00','2025-01-01 13:00:00')"), 'Concurrent sale commits on second connection.');
$unchanged = admin_dashboard_product_sales_summary($db,120,null,$asOf);
check(array_sum(array_column($initial,'transactions_total')) === array_sum(array_column($unchanged,'transactions_total')), 'Concurrent approval cannot split a single report into inconsistent totals.');
sql('COMMIT');
$refreshed = admin_dashboard_product_sales_summary($db,120,null,$asOf);
check((int)array_sum(array_column($refreshed,'transactions_total')) === 2, 'Next report sees the newly committed sale.');
$writer->close();
echo "PASS: {$checks} complete audit assertions\n";
