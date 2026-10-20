<?php
/** Run only against an isolated MySQL database whose name contains _test_. */
declare(strict_types=1);
if (strpos((string)getenv('DB_NAME'), '_test_') === false) {
    fwrite(STDERR, "Refusing to modify a non-test database.\n");
    exit(1);
}
require_once dirname(__DIR__) . '/admin/bootstrap.php';
$db = Mysql_ks::get_instance();
$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function sql(string $query): void
{
    global $db;
    if (!$db->query($query)) {
        throw new RuntimeException((string)$db->error . ': ' . $query);
    }
}
function todaySales(): array
{
    global $db;
    return admin_dashboard_period_metrics($db)['daily'];
}
app_ensure_sales_runtime_columns($db);
app_ensure_order_renewal_runtime_columns($db);
sql('SET FOREIGN_KEY_CHECKS = 0');
foreach (['orders', 'order_renewals', 'products', 'currencies', 'customer_balance_runtime_events'] as $table) {
    if (schema_object_exists($db, $table)) {
        sql("DELETE FROM {$table}");
    }
}
app_ensure_customer_balance_runtime_table($db);
sql("INSERT INTO currencies (id,code,name,symbol) VALUES (1,'USD','US Dollar','$'),(2,'EUR','Euro','EUR')");
sql("INSERT INTO products (id,name,slug,price_amount,currency_id,duration_hours,is_active) VALUES
    (1,'Original package','sales-test-original',10,2,720,0),
    (2,'Renewal package','sales-test-renewal',30,2,2160,1)");
$yesterday = date('Y-m-d 12:00:00', strtotime('-1 day'));
$now = date('Y-m-d H:i:s');
// Keep MySQL fixtures in the same second as the report snapshot across slow CI runs.
sql('SET timestamp = ' . strtotime($now));
sql("INSERT INTO orders (id,customer_id,product_id,total_amount,currency_id,status,payment_status,paid_at,created_at)
    VALUES (1,1,1,10,2,'active','paid','{$yesterday}','{$yesterday}')");
check(todaySales()['paid_orders'] === 0, 'Old purchase must not count today.');
sql("INSERT INTO order_renewals (id,order_id,customer_id,product_id,price_amount,currency_id,status,requested_at,payment_request_type,payment_request_id)
    VALUES (1,1,1,2,30,2,'pending_payment','{$now}','crypto',101)");
check(todaySales()['paid_orders'] === 0, 'Unpaid renewal must not count.');
check(app_mark_order_renewal_paid($db, 1, 1, $now), 'Approval must succeed.');
check(todaySales()['paid_orders'] === 1, 'One approval must add exactly one sale.');
check(todaySales()['revenue_by_currency']['EUR'] === 30.0, 'Renewal amount must be its own price.');
$order = $db->select_user('SELECT * FROM orders WHERE id=1');
check($order['paid_at'] === $yesterday, 'Renewal approval must preserve original payment date.');
check((float)$order['initial_sale_amount'] === 10.0, 'Original amount must be frozen.');
check(app_mark_order_renewal_paid($db, 1, 1, date('Y-m-d H:i:s', time()+10)), 'Repeated approval must be idempotent.');
check(todaySales()['paid_orders'] === 1, 'Repeated approval must not create an extra sale.');
check($db->select_user('SELECT paid_at FROM order_renewals WHERE id=1')['paid_at'] === $now, 'Repeated approval must not move the payment date.');
// The same updates as activation, followed by a second snapshot attempt.
sql("UPDATE order_renewals SET status='applied',applied_at=NOW() WHERE id=1");
sql('UPDATE orders SET product_id=2,total_amount=30 WHERE id=1');
app_preserve_initial_order_sale($db, 1);
check(todaySales()['paid_orders'] === 1, 'Activation must not count a second sale.');
check(app_mark_order_renewal_paid($db, 1, 1, date('Y-m-d H:i:s', time()+20)), 'Repeated approval after activation must succeed without changing the applied state.');
check($db->select_user('SELECT status FROM order_renewals WHERE id=1')['status'] === 'applied', 'Repeated approval must not reactivate an applied renewal.');
$source = admin_dashboard_sales_source_sql($db);
$purchase = $db->select_user("SELECT * FROM ({$source}) s WHERE sale_type='purchase'");
check((float)$purchase['amount'] === 10.0 && (int)$purchase['product_id'] === 1, 'Renewal must not rewrite original price/product.');
// A duplicate renewal row linked to the exact same payment is still one sale.
sql("INSERT INTO order_renewals (id,order_id,customer_id,product_id,price_amount,currency_id,status,requested_at,applied_at,payment_request_type,payment_request_id)
    VALUES (2,1,1,2,30,2,'applied','{$now}','{$now}','crypto',101)");
check(todaySales()['paid_orders'] === 1, 'A duplicated payment link must not multiply sales.');
// Unlinked balance credit is money in the wallet, not a product sale.
sql("INSERT INTO customer_balance_runtime_events (customer_id,source_type,source_key,direction,amount) VALUES (1,'payment_approval','crypto:999','credit',100)");
check(todaySales()['paid_orders'] === 1, 'A wallet top-up must not count as a sale.');
sql("INSERT INTO orders (id,customer_id,product_id,total_amount,currency_id,status,payment_status,paid_at) VALUES
    (2,1,2,50,2,'pending_payment','unpaid',NOW()),
    (3,1,2,50,2,'cancelled','paid',NOW()),
    (4,1,2,50,2,'active','refunded',NOW()),
    (5,1,2,0,2,'active','paid',NOW()),
    (6,1,2,50,2,'active','paid',DATE_ADD(NOW(),INTERVAL 1 DAY))");
check(todaySales()['paid_orders'] === 1, 'Unpaid/cancelled/refunded/free/future orders must not inflate sales.');
// Multiple currencies must be reported as separate amounts.
sql("INSERT INTO orders (id,customer_id,product_id,total_amount,currency_id,status,payment_status,paid_at)
    VALUES (7,1,2,7,1,'active','paid',NOW())");
$daily = todaySales();
check($daily['paid_orders'] === 2, 'Two real sales must count as two.');
check($daily['revenue_by_currency'] === ['EUR'=>30.0,'USD'=>7.0], 'Currencies must not be added together.');
$series = admin_dashboard_sales_series($db);
check(end($series)['paid_orders'] === $daily['paid_orders'], 'Daily card and daily chart must agree.');
check(end($series)['revenue_by_currency'] === $daily['revenue_by_currency'], 'Chart revenue must match daily card.');
$monthly = admin_dashboard_monthly_sales_series($db);
$yearly = admin_dashboard_yearly_sales_series($db);
check(end($monthly)['paid_orders'] === 3, 'Monthly chart includes original purchase plus two sales.');
check(end($yearly)['paid_orders'] === 3, 'Yearly chart must agree with monthly totals.');
$summary = admin_dashboard_product_sales_summary($db);
check(array_sum(array_column($summary,'transactions_total')) === 3, 'Product totals must agree, including inactive original products.');
check(array_sum(array_column($summary,'purchases_total')) === 2, 'Purchases and renewals must stay distinct.');
check(array_sum(array_column($summary,'renewals_total')) === 1, 'Duplicate renewal must also be excluded from product summary.');
sql('UPDATE orders SET initial_sale_product_id=0 WHERE id=1');
$unknownSummary = admin_dashboard_product_sales_summary($db);
$unknownRows = array_values(array_filter($unknownSummary, static fn(array $r): bool => (int)$r['id'] === 0));
check(count($unknownRows) === 1 && $unknownRows[0]['transactions_total'] === 1, 'Unknown historical product must keep its own explicit row.');
check($unknownRows[0]['revenue_by_currency'] === ['EUR'=>10.0], 'Unknown historic revenue must not disappear.');
check(array_sum(array_column($unknownSummary,'transactions_total')) === 3, 'Unknown products must not change summary totals.');
check(!app_mark_order_renewal_paid($db, 1, 9999, $now), 'Missing renewal must fail instead of reporting successful approval.');
// A renewal approved just before midnight and activated today belongs to yesterday.
sql("UPDATE order_renewals SET paid_at='{$yesterday}' WHERE id=1");
check(todaySales()['paid_orders'] === 1, 'Activation after midnight must not move a paid renewal to today.');
// Verify original dates and geometry with zero data as well.
$points = admin_dashboard_chart_points([['paid_orders'=>0],['paid_orders'=>1],['paid_orders'=>3]]);
check(abs($points[0]['y'] - 222.0) < 0.001 && abs($points[2]['y'] - 18.0) < 0.001, 'Chart geometry must match the data scale.');
check(admin_dashboard_chart_scale_max([['paid_orders'=>4]]) === 6, 'Integer grid ticks must use the same scale as chart points.');
echo "PASS: {$checks} sales regression checks\n";
