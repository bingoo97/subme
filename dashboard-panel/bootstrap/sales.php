<?php

declare(strict_types=1);

/** Compatibility for existing installations; canonical DDL lives in database/v2. */
function app_ensure_sales_runtime_columns(Mysql_ks $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $definitions = [
        'orders' => [
            'initial_sale_product_id' => 'INT UNSIGNED DEFAULT NULL',
            'initial_sale_amount' => 'DECIMAL(10,2) DEFAULT NULL',
            'initial_sale_currency_id' => 'TINYINT UNSIGNED DEFAULT NULL',
            'initial_sale_paid_at' => 'DATETIME DEFAULT NULL',
        ],
        'order_renewals' => ['paid_at' => 'DATETIME DEFAULT NULL'],
    ];
    foreach ($definitions as $table => $columns) {
        if (!schema_object_exists($db, $table)) {
            continue;
        }
        foreach ($columns as $column => $definition) {
            if (!schema_column_exists($db, $table, $column)) {
                if (!$db->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}")) {
                    throw new RuntimeException('Unable to install sales reporting columns. Run database migration 019.');
                }
                schema_forget_column_cache($table, $column);
            }
        }
    }
    $done = true;
}

/** Freeze the first purchase before renewal replaces the order's current package. */
function app_preserve_initial_order_sale(Mysql_ks $db, int $orderId): void
{
    app_ensure_sales_runtime_columns($db);
    if ($orderId <= 0) {
        return;
    }
    if (!$db->query(
        "UPDATE orders SET
            initial_sale_product_id = COALESCE(product_id, 0),
            initial_sale_amount = total_amount,
            initial_sale_currency_id = currency_id,
            initial_sale_paid_at = COALESCE(paid_at, created_at)
         WHERE id = {$orderId} AND initial_sale_amount IS NULL AND payment_status = 'paid'"
    )) {
        throw new RuntimeException('Unable to preserve the original order sale.');
    }
}

/** A renewal pays for its own sale; it must never move the original purchase date. */
function app_mark_order_renewal_paid(Mysql_ks $db, int $orderId, int $renewalId, string $paidAt): bool
{
    app_preserve_initial_order_sale($db, $orderId);
    $paidAt = $db->escape($paidAt);
    $updated = $db->query(
        "UPDATE order_renewals
         SET status = 'paid_pending_activation', paid_at = COALESCE(paid_at, '{$paidAt}')
         WHERE id = {$renewalId} AND order_id = {$orderId}
           AND status IN ('pending_payment', 'pending', 'awaiting_payment', 'paid_pending_activation', 'cancelled')"
    );
    if (!$updated) {
        return false;
    }
    $row = $db->select_user("SELECT id FROM order_renewals WHERE id = {$renewalId} AND order_id = {$orderId}
        AND (status = 'applied' OR (status = 'paid_pending_activation' AND paid_at IS NOT NULL))");
    return !empty($row['id']);
}

function app_order_renewal_sale_paid_at(Mysql_ks $db, array $renewal): string
{
    if (!empty($renewal['paid_at'])) {
        return (string)$renewal['paid_at'];
    }
    if (!empty($renewal['payment_request_type']) && !empty($renewal['payment_request_id'])
        && schema_object_exists($db, 'customer_balance_runtime_events')) {
        $key = $db->escape($renewal['payment_request_type'] . ':' . $renewal['payment_request_id']);
        $row = $db->select_user("SELECT MIN(created_at) AS paid_at FROM customer_balance_runtime_events
            WHERE source_type = 'payment_approval' AND direction = 'credit' AND source_key = '{$key}'
              AND customer_id = " . (int)($renewal['customer_id'] ?? 0) . "");
        if (!empty($row['paid_at'])) {
            return (string)$row['paid_at'];
        }
    }
    return (string)($renewal['applied_at'] ?? date('Y-m-d H:i:s'));
}
