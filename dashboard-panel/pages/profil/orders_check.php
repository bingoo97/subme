<?php

$tenantId = tenant_current_id($user);

if (app_uses_v2_schema($db)) {
    $deliveryLinkVisibleSelect = schema_column_exists($db, 'orders', 'delivery_link_visible')
        ? 'orders.delivery_link_visible'
        : '0';
    $providerUrlReplacementFromSelect = schema_column_exists($db, 'product_providers', 'url_replacement_from')
        ? 'product_providers.url_replacement_from'
        : 'NULL';
    $providerUrlReplacementToSelect = schema_column_exists($db, 'product_providers', 'url_replacement_to')
        ? 'product_providers.url_replacement_to'
        : 'NULL';

    $zapytanie = "SELECT
              orders.id,
              orders.customer_id AS user_id,
              orders.product_id,
              orders.total_amount AS price,
              orders.created_at,
              orders.expires_at,
              orders.payment_status,
              orders.fulfillment_status,
              orders.payment_method,
              orders.customer_note AS note,
              orders.delivery_link AS delivery_link_raw,
              {$deliveryLinkVisibleSelect} AS delivery_link_visible,
              orders.created_at AS date_add,
              orders.expires_at AS date_end,
              CASE
                WHEN orders.status = 'expired' THEN 2
                WHEN orders.status = 'active' THEN 1
                ELSE 0
              END AS status,
              CASE
                WHEN orders.payment_status = 'paid' THEN 2
                WHEN orders.payment_status IN ('pending', 'manual_review', 'processing', 'awaiting_confirmation') THEN 1
                ELSE 0
              END AS payment,
              CASE
                WHEN orders.fulfillment_status IN ('delivered', 'fulfilled', 'completed') THEN 1
                ELSE 0
              END AS shipment,
              products.name AS name,
              products.product_type AS product_type,
              currencies.code AS currency_code,
              currencies.symbol AS currency_symbol,
              1 AS status_product,
              products.duration_hours AS duration,
              products.is_trial AS trial,
              products.provider_id AS provider_id,
              product_providers.name AS provider_name,
              product_providers.supports_manual_delivery,
              product_providers.supports_url_replacement,
              {$providerUrlReplacementFromSelect} AS url_replacement_from,
              {$providerUrlReplacementToSelect} AS url_replacement_to
              FROM orders
              LEFT JOIN products ON orders.product_id = products.id
              LEFT JOIN currencies ON currencies.id = orders.currency_id
              LEFT JOIN product_providers ON products.provider_id = product_providers.id
              WHERE orders.customer_id = '{$user["id"]}'
              ORDER BY orders.created_at DESC, orders.id DESC";
} else {
    $zapytanie = "SELECT products_users.*, products.name AS name, products.status AS status_product,
              products.duration AS duration,
              products.trial AS trial,
              products.provider_id AS provider_id,
              products_providers.name AS provider_name
              FROM products_users, products, products_providers
              WHERE products_users.product_id = products.id
              AND products.provider_id = products_providers.id
              AND products_users.res_id = '{$tenantId}'
              AND products_users.user_id = '{$user["id"]}'
              ORDER BY products_users.status DESC, products_users.date_end ASC, products_users.id DESC";
}

$strona = isset($_GET['strona']) ? (int)$_GET['strona'] : 1;

$str = new Stronicowanie(20);
$str->select_user($zapytanie);
$str->set_strona($strona);
$generator = $str->generator('orders-site_');
$wygrane = $str->get_wartosci();

if ($wygrane) {
    for ($i = 0; $i < count($wygrane); $i++) {
        if (app_uses_v2_schema($db)) {
            $wygrane[$i]['extend'] = [];
            $wygrane[$i]['test'] = 1;
            $deliveryPayload = app_order_delivery_payload($wygrane[$i]);
            $wygrane[$i]['link_url'] = (string)$deliveryPayload['url'];
            $wygrane[$i]['delivery_show_url'] = !empty($deliveryPayload['show_url']) ? 1 : 0;
            $wygrane[$i]['delivery_show_credentials'] = !empty($deliveryPayload['show_credentials']) ? 1 : 0;
            $wygrane[$i]['delivery_login'] = (string)$deliveryPayload['login'];
            $wygrane[$i]['delivery_password'] = (string)$deliveryPayload['password'];
        } else {
            $zapytanie = "SELECT products_extend.*
                 FROM products_extend LEFT JOIN products_users ON
                 products_extend.id_order = products_users.id
                 WHERE products_extend.id_order = {$wygrane[$i]["id"]} ORDER BY products_extend.id DESC LIMIT 3";
            $wygrane[$i]['extend'] = $db->select_full_user($zapytanie);
            $wygrane[$i]['delivery_show_url'] = 0;
            $wygrane[$i]['delivery_show_credentials'] = 0;
            $wygrane[$i]['delivery_login'] = '';
            $wygrane[$i]['delivery_password'] = '';
        }

        $wygrane[$i]['przedluzenia'] = $wygrane[$i]['extend'];

        $wygrane[$i]['date_end_s'] = strtotime((string)$wygrane[$i]['date_end']);
        $wygrane[$i]['date_e'] = $wygrane[$i]['date_end_s'] ? date('d.m.Y', $wygrane[$i]['date_end_s']) : '';

        $wygrane[$i]['date_add_s'] = strtotime((string)$wygrane[$i]['date_add']);
        $wygrane[$i]['date_a'] = $wygrane[$i]['date_add_s'] ? date('d.m.Y', $wygrane[$i]['date_add_s']) : '';

        $wygrane[$i]['days'] = ceil(abs($time_s - (int)$wygrane[$i]['date_end_s']) / 86400);
        $wygrane[$i]['expiry'] = $wygrane[$i]['date_end_s'] ? date('d.m.Y', $wygrane[$i]['date_end_s']) : '';
        $wygrane[$i]['created_display'] = $wygrane[$i]['date_add_s'] ? date('d.m.Y', $wygrane[$i]['date_add_s']) : '';
        $wygrane[$i]['price_label'] = app_format_money_value(
            (float)($wygrane[$i]['price'] ?? 0),
            (string)($wygrane[$i]['currency_symbol'] ?? ''),
            (string)($wygrane[$i]['currency_code'] ?? '')
        );
        $wygrane[$i]['progress'] = app_order_progress_data($wygrane[$i]);
        $wygrane[$i]['status_visual'] = app_order_status_visual($wygrane[$i]);
        $wygrane[$i]['payment_waiting_activation'] = (
            (int)($wygrane[$i]['status'] ?? 0) === 0
            && strtolower(trim((string)($wygrane[$i]['payment_status'] ?? ''))) === 'paid'
            && (int)($wygrane[$i]['shipment'] ?? 0) === 0
        ) ? 1 : 0;

        if ($wygrane[$i]['date_add_s'] < $wygrane[$i]['date_end_s']) {
            $startDate = $wygrane[$i]['date_add_s'];
            $endDate = $wygrane[$i]['date_end_s'];
            $currentDate = $time_s;
            $dateDivideBy = $endDate - $startDate;
            $dateDivide = $currentDate - $startDate;
            $divideProduct = $dateDivide / $dateDivideBy;
            $wygrane[$i]['czas_procent'] = round($divideProduct * 100);
        } else {
            $wygrane[$i]['czas_procent'] = 100;
        }
    }
}

$smarty->assign('time_s', $time_s);
$smarty->assign('generator', $generator);
$smarty->assign('wygrane', $wygrane);
