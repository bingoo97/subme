<?php

declare(strict_types=1);

/** Cache only within one read transaction, never across approvals or HTTP requests. */
final class AdminDashboardSalesQueryCache
{
    public static bool $enabled = false;
    public static array $rows = [];

    public static function read(Mysql_ks $db, string $sql): array
    {
        $key = hash('sha256', $sql);
        if (self::$enabled && array_key_exists($key, self::$rows)) {
            return self::$rows[$key];
        }
        $rows = $db->select_full_user($sql);
        if ($db->error) {
            throw new RuntimeException('Sales report query failed: ' . $db->error);
        }
        if (self::$enabled) self::$rows[$key] = $rows;
        return $rows;
    }
}

function admin_dashboard_sales_now(?DateTimeImmutable $asOf = null): DateTimeImmutable
{
    static $requestNow = null;
    $requestNow = $requestNow ?? new DateTimeImmutable('now');
    return ($asOf ?? $requestNow)->setTimezone(new DateTimeZone(app_runtime_timezone_name()));
}

function admin_dashboard_sales_currency(Mysql_ks $db): array
{
    static $currency = null;
    $currency = $currency ?? (admin_default_currency_row($db) ?? []);
    return $currency;
}

function admin_dashboard_renewal_sale_date_sql(Mysql_ks $db, string $alias): string
{
    $paymentDate = 'NULL';
    if (schema_object_exists($db, 'customer_balance_runtime_events')) {
        $paymentDate = "(SELECT MIN(e.created_at) FROM customer_balance_runtime_events e
            WHERE e.source_type = 'payment_approval' AND e.direction = 'credit'
              AND e.customer_id = {$alias}.customer_id
              AND {$alias}.payment_request_type IN ('bank', 'crypto')
              AND e.source_key = CONCAT(IF({$alias}.payment_request_type = 'bank', 'bank:', 'crypto:'), {$alias}.payment_request_id))";
    }
    return "COALESCE({$alias}.paid_at, {$paymentDate}, {$alias}.applied_at, {$alias}.requested_at)";
}

/** One row per purchase/paid renewal. Wallet credits and activation are not extra sales. */
function admin_dashboard_sales_source_sql(Mysql_ks $db, ?DateTimeImmutable $asOf = null): string
{
    app_ensure_sales_runtime_columns($db);
    app_ensure_order_renewal_runtime_columns($db);
    $safeNow = $db->escape(admin_dashboard_sales_now($asOf)->format('Y-m-d H:i:s'));
    $parts = [];
    if (schema_object_exists($db, 'orders')) {
        $parts[] = "SELECT orders.id AS sale_id, orders.id AS order_id, 'purchase' AS sale_type,
            COALESCE(orders.initial_sale_product_id, orders.product_id) AS product_id,
            COALESCE(orders.initial_sale_amount, orders.total_amount) AS amount,
            COALESCE(orders.initial_sale_currency_id, orders.currency_id) AS currency_id,
            COALESCE(orders.initial_sale_paid_at, orders.paid_at, orders.created_at) AS sale_at
            FROM orders WHERE orders.payment_status = 'paid' AND orders.status <> 'cancelled'";
    }
    if (schema_object_exists($db, 'order_renewals')) {
        $renewalDate = admin_dashboard_renewal_sale_date_sql($db, 'r');
        $earlierDate = admin_dashboard_renewal_sale_date_sql($db, 'earlier');
        $parts[] = "SELECT r.id AS sale_id, r.order_id, 'renewal' AS sale_type,
            r.product_id, r.price_amount AS amount, r.currency_id,
            {$renewalDate} AS sale_at
            FROM order_renewals r
            INNER JOIN orders ON orders.id = r.order_id
            WHERE r.customer_id = orders.customer_id
              AND r.status IN ('applied', 'paid_pending_activation')
              AND orders.status <> 'cancelled' AND orders.payment_status <> 'refunded'
              AND NOT EXISTS (
                SELECT 1 FROM order_renewals earlier
                INNER JOIN orders earlier_order ON earlier_order.id = earlier.order_id
                WHERE earlier.id < r.id AND earlier.status IN ('applied', 'paid_pending_activation')
                  AND earlier.price_amount > 0 AND {$earlierDate} <= '{$safeNow}'
                  AND earlier.customer_id = earlier_order.customer_id
                  AND earlier_order.status <> 'cancelled' AND earlier_order.payment_status <> 'refunded'
                  AND earlier.payment_request_type = r.payment_request_type
                  AND earlier.payment_request_id = r.payment_request_id
                  AND r.payment_request_id > 0 AND r.payment_request_type <> ''
              )";
    }
    if (!$parts) {
        return "SELECT 0 AS sale_id, 0 AS order_id, '' AS sale_type, NULL AS product_id,
            0 AS amount, 0 AS currency_id, NULL AS sale_at WHERE 1 = 0";
    }
    return 'SELECT sales.* FROM (' . implode(' UNION ALL ', $parts)
        . ") sales WHERE sales.amount > 0 AND sales.sale_at <= '{$safeNow}'";
}

function admin_dashboard_sales_buckets(Mysql_ks $db, string $unit, ?string $from = null, ?string $to = null, ?DateTimeImmutable $asOf = null): array
{
    $expressions = ['day' => 'DATE(sale_at)', 'month' => "DATE_FORMAT(sale_at, '%Y-%m')", 'year' => 'YEAR(sale_at)'];
    $expression = $expressions[$unit] ?? $expressions['day'];
    $source = admin_dashboard_sales_source_sql($db, $asOf);
    $where = '';
    if ($from !== null) {
        $where .= " AND sale_at >= '" . $db->escape($from) . "'";
    }
    if ($to !== null) {
        $where .= " AND sale_at < '" . $db->escape($to) . "'";
    }
    $calendarRange = ($from === null || preg_match('/^\d{4}-\d{2}-\d{2}( 00:00:00)?$/D', $from))
        && ($to === null || preg_match('/^\d{4}-\d{2}-\d{2}( 00:00:00)?$/D', $to));
    if ($calendarRange) {
        // One small daily aggregate serves cards, day/month/year charts and year options.
        $rows = AdminDashboardSalesQueryCache::read($db, "SELECT DATE(sale_at) AS bucket,
            currencies.code AS currency_code, COUNT(*) AS paid_orders, SUM(amount) AS paid_revenue,
            SUM(sale_type = 'purchase') AS purchases, SUM(sale_type = 'renewal') AS renewals
            FROM ({$source}) sales INNER JOIN currencies ON currencies.id = sales.currency_id
            GROUP BY DATE(sale_at), currencies.code ORDER BY bucket, currencies.code");
        $rows = array_values(array_filter($rows, static function (array $row) use ($from, $to): bool {
            return ($from === null || $row['bucket'] >= substr($from, 0, 10))
                && ($to === null || $row['bucket'] < substr($to, 0, 10));
        }));
        foreach ($rows as &$row) {
            $row['bucket'] = $unit === 'year' ? substr($row['bucket'], 0, 4)
                : ($unit === 'month' ? substr($row['bucket'], 0, 7) : $row['bucket']);
        }
        unset($row);
    } else {
        // Preserve exact timestamp ranges for callers that do not request whole calendar days.
        $rows = AdminDashboardSalesQueryCache::read($db, "SELECT {$expression} AS bucket, currencies.code AS currency_code,
            COUNT(*) AS paid_orders, SUM(amount) AS paid_revenue,
            SUM(sale_type = 'purchase') AS purchases, SUM(sale_type = 'renewal') AS renewals
            FROM ({$source}) sales INNER JOIN currencies ON currencies.id = sales.currency_id
            WHERE 1 = 1 {$where} GROUP BY bucket, currencies.code ORDER BY bucket");
    }
    $defaultCurrency = admin_dashboard_sales_currency($db);
    $defaultCode = (string)($defaultCurrency['code'] ?? '');
    $mapped = [];
    foreach ($rows as $row) {
        $key = (string)$row['bucket'];
        $mapped[$key] = $mapped[$key] ?? admin_dashboard_empty_sales_bucket();
        $mapped[$key]['paid_orders'] += (int)$row['paid_orders'];
        $mapped[$key]['purchases'] += (int)$row['purchases'];
        $mapped[$key]['renewals'] += (int)$row['renewals'];
        $code = (string)$row['currency_code'];
        $mapped[$key]['revenue_by_currency'][$code] = ($mapped[$key]['revenue_by_currency'][$code] ?? 0.0) + (float)$row['paid_revenue'];
        // Numeric chart scales use only the configured currency, never a sum of EUR/USD/etc.
        if ($code === $defaultCode) {
            $mapped[$key]['paid_revenue'] += (float)$row['paid_revenue'];
        }
    }
    return $mapped;
}

function admin_dashboard_empty_sales_bucket(): array
{
    return ['paid_orders' => 0, 'paid_revenue' => 0.0, 'revenue_by_currency' => [], 'purchases' => 0, 'renewals' => 0];
}

function admin_dashboard_revenue_sum(array $rows): array
{
    $totals = [];
    foreach ($rows as $row) {
        foreach ((array)($row['revenue_by_currency'] ?? []) as $code => $amount) {
            $totals[$code] = ($totals[$code] ?? 0.0) + (float)$amount;
        }
    }
    ksort($totals);
    return $totals;
}

function admin_dashboard_revenue_label(array $totals, string $defaultCode = '', float $divisor = 1): string
{
    $labels = [];
    foreach ($totals as $code => $amount) {
        $labels[] = admin_format_money_value_with_symbol($divisor > 0 ? $amount / $divisor : 0, $code, $code);
    }
    return $labels ? implode(' + ', $labels) : admin_format_money_value_with_symbol(0, $defaultCode, $defaultCode);
}

/** Keep a small positive average visibly different from no sales. */
function admin_dashboard_average_sales_label(float $value): string
{
    if($value<=0) return '0';
    if($value<0.01) return '<0.01';
    if($value<0.1) return number_format($value,2,'.','');
    return abs($value-round($value))<0.05 ? number_format(round($value),0,'.','') : number_format($value,1,'.','');
}

/** Determine only the display state; never add different currencies into a financial total. */
function admin_dashboard_revenue_state(array $totals): string
{
    foreach($totals as $amount) if((float)$amount>0) return 'is-positive';
    return 'is-zero';
}

function admin_dashboard_period_metrics(Mysql_ks $db, ?DateTimeImmutable $asOf = null): array
{
    $asOf = admin_dashboard_sales_now($asOf);
    $now = $asOf->setTime(0, 0);
    $periods = [
        'daily' => [$now, $now->modify('+1 day'), 'Today'],
        'weekly' => [$now->modify('-6 days'), $now->modify('+1 day'), 'Last 7 days'],
        'monthly' => [$now->modify('first day of this month'), $now->modify('first day of next month'), 'This month'],
        'yearly' => [$now->setDate((int)$now->format('Y'), 1, 1), $now->setDate((int)$now->format('Y') + 1, 1, 1), 'This year'],
    ];
    $result = [];
    foreach ($periods as $key => [$from, $to, $label]) {
        $rows = admin_dashboard_sales_buckets($db, 'day', $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s'), $asOf);
        $result[$key] = [
            'label' => $label, 'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->modify('-1 second')->format('Y-m-d H:i:s'),
            'paid_orders' => array_sum(array_column($rows, 'paid_orders')),
            'paid_revenue' => array_sum(array_column($rows, 'paid_revenue')),
            'revenue_by_currency' => admin_dashboard_revenue_sum($rows),
        ];
    }
    return $result;
}

/** Keep user-controlled chart ranges bounded, including malformed query parameters. */
function admin_dashboard_sales_chart_days($value = null): int
{
    return is_scalar($value) && in_array((string)$value, ['30', '90', '365'], true) ? (int)$value : 30;
}

function admin_dashboard_sales_series(Mysql_ks $db, int $days = 30, ?DateTimeImmutable $asOf = null): array
{
    $days = max(7, min(365, $days));
    $asOf = admin_dashboard_sales_now($asOf);
    $start = $asOf->setTime(0, 0)->modify('-' . ($days - 1) . ' days');
    $mapped = admin_dashboard_sales_buckets($db, 'day', $start->format('Y-m-d'), null, $asOf);
    $series = [];
    for ($i = 0; $i < $days; $i++) {
        $day = $start->modify("+{$i} days");
        $series[] = ['date' => $day->format('Y-m-d'), 'short_label' => $day->format('d.m')]
            + ($mapped[$day->format('Y-m-d')] ?? admin_dashboard_empty_sales_bucket());
    }
    return $series;
}

function admin_dashboard_monthly_sales_series(Mysql_ks $db, int $months = 12, ?DateTimeImmutable $asOf = null): array
{
    $months = max(6, min(24, $months));
    $asOf = admin_dashboard_sales_now($asOf);
    $start = $asOf->modify('first day of this month')->setTime(0, 0)->modify('-' . ($months - 1) . ' months');
    $mapped = admin_dashboard_sales_buckets($db, 'month', $start->format('Y-m-d'), null, $asOf);
    $series = [];
    for ($i = 0; $i < $months; $i++) {
        $month = $start->modify("+{$i} months");
        $series[] = ['month_key' => $month->format('Y-m'), 'year' => (int)$month->format('Y'), 'month' => (int)$month->format('m')]
            + ($mapped[$month->format('Y-m')] ?? admin_dashboard_empty_sales_bucket());
    }
    return $series;
}

function admin_dashboard_yearly_sales_series(Mysql_ks $db, int $limit = 6, ?DateTimeImmutable $asOf = null): array
{
    $mapped = admin_dashboard_sales_buckets($db, 'year', null, null, $asOf);
    $series = [];
    foreach (array_slice($mapped, -max(2, min(10, $limit)), null, true) as $year => $row) {
        $series[] = ['year' => (int)$year] + $row;
    }
    return $series;
}

function admin_dashboard_sales_activity_counts(Mysql_ks $db, ?string $fromDate = null, ?string $toDate = null): array
{
    $to = $toDate ? (new DateTimeImmutable($toDate))->modify('+1 day')->format('Y-m-d') : null;
    $days = admin_dashboard_sales_buckets($db, 'day', $fromDate ?: null, $to);
    $months = $weeks = [];
    foreach ($days as $day => $row) {
        $date = new DateTimeImmutable($day);
        $months[$date->format('Y-m')] = true;
        $weeks[$date->format('o-W')] = true;
    }
    return ['months_with_sales' => count($months), 'weeks_with_sales' => count($weeks), 'days_with_sales' => count($days)];
}

function admin_dashboard_sales_year_options(Mysql_ks $db): array
{
    $years = array_map('intval', array_keys(admin_dashboard_sales_buckets($db, 'year')));
    rsort($years);
    return $years;
}

function admin_dashboard_product_revenue_map(Mysql_ks $db, ?int $year, ?DateTimeImmutable $asOf = null): array
{
    $source = admin_dashboard_sales_source_sql($db, $asOf);
    $where = $year ? ' WHERE YEAR(sale_at) = ' . (int)$year : '';
    $rows = $db->select_full_user("SELECT COALESCE(products.id, 0) AS product_id, currencies.code, SUM(amount) AS amount
        FROM ({$source}) sales INNER JOIN currencies ON currencies.id = sales.currency_id
        LEFT JOIN products ON products.id = sales.product_id
        {$where} GROUP BY COALESCE(products.id, 0), currencies.code");
    if ($db->error) {
        throw new RuntimeException('Product revenue query failed: ' . $db->error);
    }
    $mapped = [];
    foreach ($rows as $row) {
        $mapped[(int)$row['product_id']][$row['code']] = (float)$row['amount'];
    }
    return $mapped;
}

function admin_dashboard_provider_revenue(Mysql_ks $db, int $providerId): array
{
    $source = admin_dashboard_sales_source_sql($db);
    $rows = $db->select_full_user("SELECT currencies.code, SUM(amount) AS amount FROM ({$source}) sales
        INNER JOIN products ON products.id = sales.product_id
        INNER JOIN currencies ON currencies.id = sales.currency_id
        WHERE products.provider_id = {$providerId} GROUP BY currencies.code");
    return array_map('floatval', array_column($rows, 'amount', 'code'));
}
