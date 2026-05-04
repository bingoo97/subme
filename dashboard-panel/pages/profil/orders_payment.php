<?php

if (!function_exists('orders_payment_format_logo_path')) {
    function orders_payment_format_logo_path(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/img/no-image.png';
        }

        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, '/') === 0) {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('orders_payment_crypto_logo_by_code')) {
    function orders_payment_crypto_logo_by_code(string $assetCode, string $fallbackPath = ''): string
    {
        $code = strtoupper(trim($assetCode));
        $fileMap = [
            'BTC' => 'btc.png',
            'BCH' => 'btcash.png',
            'LTC' => 'ltc.png',
            'ETH' => 'eth.png',
            'DOGE' => 'doge.png',
            'BNB' => 'bnb.png',
            'BUSD' => 'busd.png',
            'USDT' => 'tether.png',
            'USDC' => 'tether.png',
            'CRO' => 'cro.png',
            'ATOM' => 'atom.png',
            'SOL' => 'sol.png',
            'MATIC' => 'matic.png',
            'XRP' => 'xrp.png',
        ];

        if (isset($fileMap[$code])) {
            return '/img/crypto/' . $fileMap[$code];
        }

        return orders_payment_format_logo_path($fallbackPath);
    }
}

if (!function_exists('orders_payment_product_label')) {
    function orders_payment_product_label(array $product, string $currencySymbol): string
    {
        $productType = strtolower(trim((string)($product['product_type'] ?? 'subscription')));
        $durationHours = isset($product['duration']) ? (int)$product['duration'] : 0;
        $price = isset($product['price']) ? number_format((float)$product['price'], 2, '.', '') : '0.00';
        $isTrial = !empty($product['trial']);

        if ($productType === 'credits') {
            return trim((string)$product['name']) . ' • ' . $price . ' ' . trim($currencySymbol);
        }

        if ($isTrial) {
            $durationLabel = $durationHours . 'h trial';
        } else {
            $days = $durationHours > 0 ? max(1, (int)round($durationHours / 24)) : 0;
            $durationLabel = $days . ' days';
            if ($days > 0 && ($days % 30) === 0) {
                $months = (int)($days / 30);
                $durationLabel = $months . ' month' . ($months === 1 ? '' : 's');
            } elseif ($days > 1) {
                $durationLabel = $days . ' days';
            } elseif ($days === 1) {
                $durationLabel = '1 day';
            }
        }

        $productName = trim((string)$product['name']);
        if (strcasecmp($productName, $durationLabel) === 0) {
            return $productName . ' • ' . $price . ' ' . trim($currencySymbol);
        }

        return $productName . ' • ' . $durationLabel . ' • ' . $price . ' ' . trim($currencySymbol);
    }
}

if (!function_exists('orders_payment_default_coingecko_id')) {
    function orders_payment_default_coingecko_id(string $assetCode): string
    {
        $assetCode = strtoupper(trim($assetCode));
        $map = [
            'BTC' => 'bitcoin',
            'BCH' => 'bitcoin-cash',
            'LTC' => 'litecoin',
            'ETH' => 'ethereum',
            'DOGE' => 'dogecoin',
            'BNB' => 'binancecoin',
            'USDT' => 'tether',
            'CRO' => 'crypto-com-chain',
            'SOL' => 'solana',
            'USDC' => 'usd-coin',
            'MATIC' => 'matic-network',
            'XRP' => 'ripple',
        ];

        return $map[$assetCode] ?? '';
    }
}

if (!function_exists('orders_payment_crypto_network_label')) {
    function orders_payment_crypto_network_label(string $networkCode): string
    {
        $networkCode = strtolower(trim($networkCode));
        $labels = [
            'bitcoin' => 'Bitcoin',
            'bitcoin-cash' => 'Bitcoin Cash',
            'litecoin' => 'Litecoin',
            'dogecoin' => 'Dogecoin',
            'ethereum' => 'Ethereum (ERC20)',
            'polygon' => 'Polygon',
            'bnb' => 'BNB Smart Chain',
            'tron' => 'Tron (TRC20)',
            'cronos' => 'Cronos',
            'solana' => 'Solana',
            'ripple' => 'XRP Ledger',
        ];

        return $labels[$networkCode] ?? ucfirst(str_replace('-', ' ', $networkCode));
    }
}

if (!function_exists('orders_payment_http_json')) {
    function orders_payment_http_json(string $url): ?array
    {
        if ($url === '') {
            return null;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: Reseller/1.0',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!is_string($response) || $response === '' || $httpCode >= 400) {
                return null;
            }

            $decoded = json_decode($response, true);
            return is_array($decoded) ? $decoded : null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'header' => "Accept: application/json\r\nUser-Agent: Reseller/1.0\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if (!is_string($response) || $response === '') {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('orders_payment_refresh_crypto_rates')) {
    function orders_payment_refresh_crypto_rates($db, string $vsCurrency = 'USD', int $cacheTtl = 900): array
    {
        if (!schema_object_exists($db, 'crypto_assets')) {
            return [];
        }

        $vsCurrency = strtoupper(trim($vsCurrency));
        if ($vsCurrency === '') {
            $vsCurrency = 'USD';
        }
        $vsCurrencyLower = strtolower($vsCurrency);
        $now = time();

        $rows = $db->select_full_user(
            "SELECT
                id,
                code,
                name,
                coingecko_id,
                logo_url,
                current_rate_fiat,
                rate_currency_code,
                rate_updated_at,
                is_active
             FROM crypto_assets
             WHERE is_active = 1
             ORDER BY id ASC"
        );

        if (!$rows) {
            return [];
        }

        $needsRefresh = false;
        $coingeckoIds = [];
        foreach ($rows as $index => $row) {
            $code = (string)($row['code'] ?? '');
            $coingeckoId = trim((string)($row['coingecko_id'] ?? ''));
            if ($coingeckoId === '') {
                $coingeckoId = orders_payment_default_coingecko_id($code);
                $rows[$index]['coingecko_id'] = $coingeckoId;
            }

            if ($coingeckoId !== '') {
                $coingeckoIds[$coingeckoId] = true;
            }

            $rate = isset($row['current_rate_fiat']) ? (float)$row['current_rate_fiat'] : 0.0;
            $rateCurrencyCode = strtoupper(trim((string)($row['rate_currency_code'] ?? '')));
            $rateUpdatedAt = !empty($row['rate_updated_at']) ? app_timestamp_from_utc_datetime((string)$row['rate_updated_at']) : 0;

            if ($coingeckoId === '' || $rate <= 0 || $rateCurrencyCode !== $vsCurrency || $rateUpdatedAt <= 0 || ($now - $rateUpdatedAt) >= $cacheTtl) {
                $needsRefresh = true;
            }
        }

        if ($needsRefresh && $coingeckoIds) {
            $apiUrl = 'https://api.coingecko.com/api/v3/simple/price?ids='
                . rawurlencode(implode(',', array_keys($coingeckoIds)))
                . '&vs_currencies=' . rawurlencode($vsCurrencyLower)
                . '&include_last_updated_at=true&precision=full';
            $payload = orders_payment_http_json($apiUrl);

            if (is_array($payload)) {
                foreach ($rows as $index => $row) {
                    $assetId = (int)($row['id'] ?? 0);
                    $coingeckoId = (string)($row['coingecko_id'] ?? '');
                    if ($assetId <= 0 || $coingeckoId === '' || empty($payload[$coingeckoId][$vsCurrencyLower])) {
                        continue;
                    }

                    $price = (float)$payload[$coingeckoId][$vsCurrencyLower];
                    $updatedAt = !empty($payload[$coingeckoId]['last_updated_at'])
                        ? app_datetime_utc_from_unix_timestamp((int)$payload[$coingeckoId]['last_updated_at'])
                        : app_datetime_utc_from_unix_timestamp($now);

                    $db->update_using_id(
                        ['coingecko_id', 'current_rate_fiat', 'rate_currency_code', 'rate_updated_at'],
                        [$coingeckoId, $price, $vsCurrency, $updatedAt],
                        'crypto_assets',
                        $assetId
                    );

                    $rows[$index]['current_rate_fiat'] = $price;
                    $rows[$index]['rate_currency_code'] = $vsCurrency;
                    $rows[$index]['rate_updated_at'] = $updatedAt;
                }
            }
        } else {
            foreach ($rows as $row) {
                $assetId = (int)($row['id'] ?? 0);
                $coingeckoId = trim((string)($row['coingecko_id'] ?? ''));
                if ($assetId > 0 && $coingeckoId === '') {
                    $defaultCoingeckoId = orders_payment_default_coingecko_id((string)($row['code'] ?? ''));
                    if ($defaultCoingeckoId !== '') {
                        $db->update_using_id(['coingecko_id'], [$defaultCoingeckoId], 'crypto_assets', $assetId);
                    }
                }
            }
        }

        return $rows;
    }
}

if (!function_exists('orders_payment_build_bank_reference')) {
    function orders_payment_build_bank_reference(string $template, int $customerId, int $requestId, int $orderId): string
    {
        $template = trim($template);
        if ($template === '') {
            return 'ORDER-' . $customerId . '-' . $orderId . '-' . $requestId;
        }

        return strtr($template, [
            '%customer_id%' => (string)$customerId,
            '%request_id%' => (string)$requestId,
            '%order_id%' => (string)$orderId,
        ]);
    }
}

if (!function_exists('orders_payment_available_bank_account_rows')) {
    function orders_payment_available_bank_account_rows($db, string $currencyCode = ''): array
    {
        if (!schema_object_exists($db, 'available_bank_account_pool')) {
            return [];
        }

        $currencyCode = strtoupper(trim($currencyCode));
        $currencyFilter = $currencyCode !== ''
            ? " WHERE currency_code = '" . $db->escape($currencyCode) . "'"
            : '';

        $rows = $db->select_full_user(
            "SELECT
                id,
                currency_code,
                currency_name,
                label,
                account_holder_name,
                bank_name,
                bank_address,
                country_code,
                iban,
                account_number,
                routing_number,
                swift_bic,
                payment_reference_template,
                transfer_instructions
             FROM available_bank_account_pool
             {$currencyFilter}
             ORDER BY label ASC, bank_name ASC, id ASC"
        );

        if ($rows || $currencyCode === '') {
            return $rows;
        }

        return $db->select_full_user(
            "SELECT
                id,
                currency_code,
                currency_name,
                label,
                account_holder_name,
                bank_name,
                bank_address,
                country_code,
                iban,
                account_number,
                routing_number,
                swift_bic,
                payment_reference_template,
                transfer_instructions
             FROM available_bank_account_pool
             ORDER BY label ASC, bank_name ASC, id ASC"
        );
    }
}

if (!function_exists('orders_payment_available_crypto_wallet_for_asset')) {
    function orders_payment_available_crypto_wallet_for_asset($db, int $assetId, int $customerId = 0, array $settings = []): ?array
    {
        if ($assetId <= 0 || !schema_object_exists($db, 'crypto_wallet_addresses')) {
            return null;
        }

        $sharedEnabled = !empty($settings['crypto_wallet_shared_assignments_enabled']);
        $currentCustomerFilter = $customerId > 0
            ? " AND current_customer_assignment.customer_id = {$customerId}"
            : '';

        if ($sharedEnabled) {
            $row = $db->select_user(
                "SELECT
                    crypto_wallet_addresses.id AS wallet_address_id,
                    crypto_wallet_addresses.crypto_asset_id,
                    crypto_wallet_addresses.label,
                    crypto_wallet_addresses.owner_full_name,
                    crypto_wallet_addresses.address,
                    crypto_wallet_addresses.network_code,
                    crypto_wallet_addresses.memo_tag,
                    crypto_wallet_addresses.wallet_provider,
                    crypto_assets.code AS crypto_asset_code,
                    crypto_assets.name AS crypto_asset_name
                 FROM crypto_wallet_addresses
                 INNER JOIN crypto_assets ON crypto_assets.id = crypto_wallet_addresses.crypto_asset_id
                 LEFT JOIN crypto_wallet_assignments AS current_customer_assignment
                   ON current_customer_assignment.wallet_address_id = crypto_wallet_addresses.id
                  AND current_customer_assignment.status IN ('reserved', 'active')
                  {$currentCustomerFilter}
                 LEFT JOIN crypto_deposit_requests AS open_request
                   ON open_request.wallet_address_id = crypto_wallet_addresses.id
                  AND open_request.status IN ('pending', 'awaiting_confirmation', 'awaiting_review')
                 WHERE crypto_wallet_addresses.crypto_asset_id = {$assetId}
                   AND crypto_wallet_addresses.disabled_at IS NULL
                   AND crypto_wallet_addresses.status IN ('available', 'assigned')
                   AND current_customer_assignment.id IS NULL
                   AND open_request.id IS NULL
                 ORDER BY
                    CASE WHEN crypto_wallet_addresses.status = 'available' THEN 0 ELSE 1 END ASC,
                    crypto_wallet_addresses.is_reusable DESC,
                    crypto_wallet_addresses.last_assigned_at ASC,
                    crypto_wallet_addresses.id ASC
                 LIMIT 1"
            );
        } else {
            $row = $db->select_user(
                "SELECT
                    crypto_wallet_addresses.id AS wallet_address_id,
                    crypto_wallet_addresses.crypto_asset_id,
                    crypto_wallet_addresses.label,
                    crypto_wallet_addresses.owner_full_name,
                    crypto_wallet_addresses.address,
                    crypto_wallet_addresses.network_code,
                    crypto_wallet_addresses.memo_tag,
                    crypto_wallet_addresses.wallet_provider,
                    crypto_assets.code AS crypto_asset_code,
                    crypto_assets.name AS crypto_asset_name
                 FROM crypto_wallet_addresses
                 INNER JOIN crypto_assets ON crypto_assets.id = crypto_wallet_addresses.crypto_asset_id
                 LEFT JOIN crypto_wallet_assignments
                   ON crypto_wallet_assignments.wallet_address_id = crypto_wallet_addresses.id
                  AND crypto_wallet_assignments.status IN ('reserved', 'active')
                 LEFT JOIN crypto_deposit_requests AS open_request
                   ON open_request.wallet_address_id = crypto_wallet_addresses.id
                  AND open_request.status IN ('pending', 'awaiting_confirmation', 'awaiting_review')
                 WHERE crypto_wallet_addresses.crypto_asset_id = {$assetId}
                   AND crypto_wallet_addresses.status = 'available'
                   AND crypto_wallet_addresses.disabled_at IS NULL
                   AND crypto_wallet_assignments.id IS NULL
                   AND open_request.id IS NULL
                 ORDER BY crypto_wallet_addresses.is_reusable DESC,
                          crypto_wallet_addresses.last_assigned_at ASC,
                          crypto_wallet_addresses.id ASC
                 LIMIT 1"
            );
        }

        return is_array($row) && !empty($row['wallet_address_id']) ? $row : null;
    }
}

if (!function_exists('orders_payment_assign_crypto_wallet_customer')) {
    function orders_payment_assign_crypto_wallet_customer($db, int $walletId, int $customerId, array $settings = [], int $orderId = 0): int
    {
        if (
            $walletId <= 0
            || $customerId <= 0
            || !schema_object_exists($db, 'crypto_wallet_assignments')
            || !schema_object_exists($db, 'crypto_wallet_addresses')
        ) {
            return 0;
        }

        $existing = $db->select_user(
            "SELECT id
             FROM crypto_wallet_assignments
             WHERE wallet_address_id = {$walletId}
               AND customer_id = {$customerId}
               AND status IN ('reserved', 'active')
             ORDER BY id DESC
             LIMIT 1"
        );
        if (is_array($existing) && !empty($existing['id'])) {
            return (int)$existing['id'];
        }

        $wallet = $db->select_user(
            "SELECT id, crypto_asset_id, status, disabled_at
             FROM crypto_wallet_addresses
             WHERE id = {$walletId}
             LIMIT 1"
        );
        if (!is_array($wallet) || empty($wallet['id']) || !empty($wallet['disabled_at']) || (string)($wallet['status'] ?? '') === 'disabled') {
            return 0;
        }

        $sharedEnabled = !empty($settings['crypto_wallet_shared_assignments_enabled']);
        if (!$sharedEnabled && !empty($wallet['crypto_asset_id'])) {
            $sameAssetAssignments = $db->select_full_user(
                "SELECT crypto_wallet_assignments.id
                 FROM crypto_wallet_assignments
                 INNER JOIN crypto_wallet_addresses
                    ON crypto_wallet_addresses.id = crypto_wallet_assignments.wallet_address_id
                 WHERE crypto_wallet_assignments.customer_id = {$customerId}
                   AND crypto_wallet_assignments.status IN ('reserved', 'active')
                   AND crypto_wallet_addresses.crypto_asset_id = " . (int)$wallet['crypto_asset_id'] . "
                   AND crypto_wallet_assignments.wallet_address_id <> {$walletId}"
            );

            foreach ($sameAssetAssignments as $assignment) {
                if (!empty($assignment['id'])) {
                    app_release_crypto_wallet_assignment_if_unused($db, (int)$assignment['id'], 'Moved from customer payment wizard');
                }
            }
        }

        $inserted = $db->insert(
            ['wallet_address_id', 'customer_id', 'order_id', 'assignment_reason', 'status', 'assigned_by_admin_user_id', 'assignment_note'],
            [$walletId, $customerId, $orderId > 0 ? $orderId : null, 'deposit', 'active', null, 'Assigned from customer payment wizard'],
            'crypto_wallet_assignments'
        );
        if (!$inserted || (int)$db->id() <= 0) {
            return 0;
        }

        $db->update_using_id(
            ['status', 'last_assigned_at', 'disabled_at'],
            ['assigned', $db->expr('NOW()'), null],
            'crypto_wallet_addresses',
            $walletId
        );

        return (int)$db->id();
    }
}

if (!function_exists('orders_payment_assign_bank_account_customer')) {
    function orders_payment_assign_bank_account_customer($db, int $bankAccountId, int $customerId, int $orderId = 0): int
    {
        if (
            $bankAccountId <= 0
            || $customerId <= 0
            || !schema_object_exists($db, 'bank_account_assignments')
            || !schema_object_exists($db, 'bank_accounts')
        ) {
            return 0;
        }

        $existing = $db->select_user(
            "SELECT id
             FROM bank_account_assignments
             WHERE bank_account_id = {$bankAccountId}
               AND customer_id = {$customerId}
               AND status IN ('reserved', 'active')
             ORDER BY id DESC
             LIMIT 1"
        );
        if (is_array($existing) && !empty($existing['id'])) {
            return (int)$existing['id'];
        }

        $account = $db->select_user(
            "SELECT id, status, disabled_at
             FROM bank_accounts
             WHERE id = {$bankAccountId}
             LIMIT 1"
        );
        if (!is_array($account) || empty($account['id']) || !empty($account['disabled_at']) || (string)($account['status'] ?? '') === 'disabled') {
            return 0;
        }

        $inserted = $db->insert(
            ['bank_account_id', 'customer_id', 'order_id', 'assignment_reason', 'status', 'assigned_by_admin_user_id', 'assignment_note'],
            [$bankAccountId, $customerId, $orderId > 0 ? $orderId : null, 'bank_transfer', 'active', null, 'Assigned from customer payment wizard'],
            'bank_account_assignments'
        );
        if (!$inserted || (int)$db->id() <= 0) {
            return 0;
        }
        $assignmentId = (int)$db->id();

        $db->update_using_id(
            ['status', 'last_assigned_at', 'disabled_at'],
            ['assigned', $db->expr('NOW()'), null],
            'bank_accounts',
            $bankAccountId
        );

        return $assignmentId;
    }
}

if (!function_exists('orders_payment_crypto_qr_url')) {
    function orders_payment_crypto_qr_url(array $request): string
    {
        $explicitQrUrl = trim((string)($request['wallet_qr_url'] ?? ''));
        if ($explicitQrUrl !== '') {
            return orders_payment_format_logo_path($explicitQrUrl);
        }

        $walletAddress = trim((string)($request['wallet_address'] ?? ''));
        if ($walletAddress === '') {
            return '';
        }

        return 'https://quickchart.io/qr?text=' . rawurlencode($walletAddress) . '&size=300';
    }
}

if (!function_exists('orders_payment_cancel_open_crypto_requests')) {
    function orders_payment_cancel_open_crypto_requests($db, int $customerId, int $orderId): void
    {
        $safeNow = date('Y-m-d H:i:s');
        app_cancel_crypto_deposit_requests(
            $db,
            "customer_id = {$customerId}
             AND order_id = {$orderId}
             AND status IN ('pending', 'awaiting_confirmation', 'awaiting_review')",
            'Released after customer cancelled order crypto payment request',
            $safeNow
        );
    }
}

if (!function_exists('orders_payment_cancel_open_bank_requests')) {
    function orders_payment_cancel_open_bank_requests($db, int $customerId, int $orderId): void
    {
        app_cancel_bank_transfer_requests(
            $db,
            "customer_id = {$customerId}
             AND order_id = {$orderId}
             AND status IN ('pending_payment', 'awaiting_review')",
            date('Y-m-d H:i:s')
        );
    }
}

if (!function_exists('orders_payment_archive_expired_crypto_requests')) {
    function orders_payment_archive_expired_crypto_requests($db, int $customerId, int $orderId): void
    {
        $safeNow = date('Y-m-d H:i:s');
        $expiredCount = app_expire_crypto_deposit_requests(
            $db,
            "customer_id = {$customerId}
             AND order_id = {$orderId}
             AND status IN ('pending', 'awaiting_confirmation', 'awaiting_review')
             AND expires_at IS NOT NULL
             AND expires_at <= '{$safeNow}'",
            $safeNow
        );

        if ($expiredCount > 0 && schema_object_exists($db, 'orders')) {
            @$db->query(
                "UPDATE orders
                 SET payment_method = NULL
                 WHERE id = {$orderId}
                   AND customer_id = {$customerId}
                   AND payment_status NOT IN ('paid', 'manual_review', 'processing', 'awaiting_confirmation', 'awaiting_review')"
            );
        }
    }
}

if (!function_exists('orders_payment_archive_expired_bank_requests')) {
    function orders_payment_archive_expired_bank_requests($db, int $customerId, int $orderId): void
    {
        $safeNow = date('Y-m-d H:i:s');
        $expiredCount = app_expire_bank_transfer_requests(
            $db,
            "customer_id = {$customerId}
             AND order_id = {$orderId}
             AND status IN ('pending_payment', 'awaiting_review')
             AND expires_at IS NOT NULL
             AND expires_at <= '{$safeNow}'",
            $safeNow
        );

        if ($expiredCount > 0 && schema_object_exists($db, 'orders')) {
            @$db->query(
                "UPDATE orders
                 SET payment_method = NULL
                 WHERE id = {$orderId}
                   AND customer_id = {$customerId}
                   AND payment_status NOT IN ('paid', 'manual_review', 'processing', 'awaiting_confirmation', 'awaiting_review')"
            );
        }
    }
}

if (!function_exists('orders_payment_load_v2_order')) {
    function orders_payment_load_v2_order($db, int $customerId, int $orderId): ?array
    {
        $query = "
            SELECT
                orders.id,
                orders.customer_id AS user_id,
                orders.product_id,
                orders.total_amount AS price,
                orders.customer_note AS note,
                orders.created_at AS date_add,
                orders.expires_at AS date_end,
                orders.payment_method AS payment_method_raw,
                orders.payment_status AS payment_status_raw,
                orders.fulfillment_status AS fulfillment_status_raw,
                orders.status AS status_raw,
                CASE
                    WHEN orders.status = 'expired' THEN 2
                    WHEN orders.status = 'active' THEN 1
                    ELSE 0
                END AS status,
                CASE
                    WHEN orders.payment_status = 'paid' THEN 2
                    WHEN orders.payment_status IN ('pending', 'manual_review', 'processing', 'awaiting_confirmation', 'awaiting_review') THEN 1
                    ELSE 0
                END AS payment,
                CASE
                    WHEN orders.fulfillment_status IN ('delivered', 'fulfilled', 'completed') THEN 1
                    ELSE 0
                END AS shipment,
                products.name AS name,
                products.product_type AS product_type,
                products.duration_hours AS duration,
                products.is_trial AS trial,
                products.provider_id AS provider_id,
                products.currency_id AS currency_id,
                product_providers.name AS provider_name,
                currencies.code AS currency_code,
                currencies.symbol AS currency_symbol
            FROM orders
            LEFT JOIN products ON orders.product_id = products.id
            LEFT JOIN product_providers ON products.provider_id = product_providers.id
            LEFT JOIN currencies ON currencies.id = orders.currency_id
            WHERE orders.customer_id = {$customerId}
              AND orders.id = {$orderId}
            LIMIT 1
        ";

        $order = $db->select_user($query);
        return is_array($order) ? $order : null;
    }
}

if (!function_exists('orders_payment_load_customer_balance_amount')) {
    function orders_payment_load_customer_balance_amount($db, int $customerId): float
    {
        if ($customerId <= 0 || !schema_object_exists($db, 'customers') || !schema_column_exists($db, 'customers', 'balance_amount')) {
            return 0.0;
        }

        $row = $db->select_user(
            "SELECT balance_amount
             FROM customers
             WHERE id = {$customerId}
             LIMIT 1"
        );

        return is_array($row) ? round((float)($row['balance_amount'] ?? 0), 2) : 0.0;
    }
}

$tenantId = tenant_current_id($user);
$orderId = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['payment']) ? (int)$_GET['payment'] : 0);
$salesEnabled = (int)($settings['active_sale'] ?? 0) === 1;
$trialsEnabled = (int)($settings['active_trials'] ?? 0) === 1;
$paymentRedirectUrl = '';

if (app_uses_v2_schema($db)) {
    if (!$salesEnabled) {
        $smarty->assign('alert_error', localization_translate($t, 'sales_disabled_notice', 'Sales are currently unavailable.'));
        $selected = null;
        $smarty->assign('selected', null);
        return;
    }

    $selected = orders_payment_load_v2_order($db, (int)$user['id'], $orderId);
    $pendingRenewalRow = null;
    $isSameOrderRenewalFlow = false;

    if ($selected && !empty($selected['trial']) && !$trialsEnabled) {
        $smarty->assign('alert_error', localization_translate($t, 'trials_disabled_notice', 'Trial subscriptions are currently disabled.'));
        $smarty->assign('selected', null);
        return;
    }

    if ($selected) {
        $pendingRenewalRow = app_order_find_pending_renewal($db, (int)$selected['id']);
        $isSameOrderRenewalFlow = app_order_can_self_extend($selected);
        $pendingBalanceTopupPayment = app_find_customer_pending_balance_topup_payment($db, (int)$user['id']);
        $selectedProductType = strtolower(trim((string)($selected['product_type'] ?? 'subscription')));
        $selected['date_end_s'] = !empty($selected['date_end']) ? strtotime($selected['date_end']) : 0;
        $canRequestPayment = (
            (($selected['status'] == 0) && ($selected['payment'] == 0))
            || ($selected['status'] == 2)
            || (($selected['status'] == 1) && ($selected['date_end_s'] > 0) && ($selected['date_end_s'] < ($time_s + (3600 * 24 * 7))))
        );

        $availableProducts = [];
        if (!empty($selected['provider_id'])) {
            $availableProducts = $db->select_full_user(
                "SELECT
                    id,
                    name,
                    provider_id,
                    is_active,
                    product_type,
                    duration_hours,
                    duration_hours AS duration,
                    price_amount,
                    price_amount AS price,
                    currency_id,
                    is_trial AS trial,
                    currencies.code AS currency_code,
                    currencies.symbol AS currency_symbol
                 FROM products
                 LEFT JOIN currencies ON currencies.id = products.currency_id
                 WHERE provider_id = " . (int)$selected['provider_id'] . "
                   AND is_active = 1
                   AND product_type = '" . $db->escape($selectedProductType) . "'
                   " . ($trialsEnabled ? '' : "AND is_trial = 0") . "
                 ORDER BY duration_hours ASC, price_amount ASC, id ASC"
            );
        }

        if (!$availableProducts && !empty($selected['product_id'])) {
            $availableProducts = [[
                'id' => $selected['product_id'],
                'name' => $selected['name'],
                'provider_id' => $selected['provider_id'],
                'is_active' => 1,
                'product_type' => $selectedProductType,
                'duration_hours' => $selected['duration'],
                'duration' => $selected['duration'],
                'price_amount' => $selected['price'],
                'price' => $selected['price'],
                'currency_id' => $selected['currency_id'],
                'currency_code' => $selected['currency_code'] ?? '',
                'currency_symbol' => $selected['currency_symbol'] ?? '',
                'trial' => $selected['trial'],
            ]];
        }

        foreach ($availableProducts as $index => $product) {
            $productCurrencySymbol = trim((string)($product['currency_symbol'] ?? ''));
            if ($productCurrencySymbol === '') {
                $productCurrencySymbol = trim((string)($product['currency_code'] ?? 'USD'));
            }
            $availableProducts[$index]['payment_label'] = orders_payment_product_label($product, $productCurrencySymbol);
            $availableProducts[$index]['is_selected'] = ((int)$product['id'] === (int)$selected['product_id']);
        }

        $selectedProductId = isset($_POST['payment_product_id'])
            ? (int)$_POST['payment_product_id']
            : (int)($pendingRenewalRow['product_id'] ?? $selected['product_id']);
        $selectedProduct = null;
        foreach ($availableProducts as $product) {
            if ((int)$product['id'] === $selectedProductId) {
                $selectedProduct = $product;
                break;
            }
        }
        if (!$selectedProduct && !empty($availableProducts)) {
            $selectedProduct = $availableProducts[0];
            $selectedProductId = (int)$selectedProduct['id'];
        }
        $selectedProductCurrencyId = app_effective_currency_id(
            is_array($settings ?? null) ? $settings : [],
            (int)($selectedProduct['currency_id'] ?? 0),
            (int)($selected['currency_id'] ?? 0)
        );

        $selectedCurrencyCode = strtoupper(trim((string)($selectedProduct['currency_code'] ?? $selected['currency_code'] ?? 'USD')));
        if ($selectedCurrencyCode === '') {
            $selectedCurrencyCode = 'USD';
        }
        $cryptoAssets = app_load_customer_crypto_assets(
            $db,
            (int)$user['id'],
            $selectedCurrencyCode,
            is_array($settings ?? null) ? $settings : []
        );

        $bankAccounts = app_load_customer_bank_accounts(
            $db,
            (int)$user['id'],
            (string)($selectedProduct['currency_code'] ?? $selected['currency_code'] ?? ''),
            is_array($settings ?? null) ? $settings : []
        );

        $bankEnabled = !empty($settings['bank_transfers_enabled']);
        $cryptoEnabled = !empty($settings['crypto_payments_enabled']);
        $hasAssignedCryptoWallets = !empty($cryptoAssets);
        $canUseBank = $bankEnabled && !empty($bankAccounts);
        $canUseCrypto = $cryptoEnabled && $hasAssignedCryptoWallets;
        $blockingPendingActivationOrder = app_find_customer_paid_pending_activation_order(
            $db,
            (int)$user['id'],
            (int)($selected['id'] ?? 0)
        );
        $customerBalanceAmount = orders_payment_load_customer_balance_amount($db, (int)$user['id']);
        $selectedProductPrice = round((float)($selectedProduct['price'] ?? $selected['price'] ?? 0), 2);
        $canUseBalance = !$blockingPendingActivationOrder
            && $selectedProduct
            && $selectedProductPrice > 0
            && $customerBalanceAmount + 0.00001 >= $selectedProductPrice;

        $selectedMethod = isset($_POST['payment_method']) ? trim((string)$_POST['payment_method']) : '';
        if ($selectedMethod !== 'crypto' && $selectedMethod !== 'bank_transfer' && $selectedMethod !== 'balance') {
            $selectedMethod = '';
        }
        if (!$bankEnabled && $selectedMethod === 'bank_transfer') {
            $selectedMethod = '';
        }
        if (!$canUseBalance && $selectedMethod === 'balance') {
            $selectedMethod = '';
        }

        orders_payment_archive_expired_crypto_requests($db, (int)$user['id'], (int)$selected['id']);
        orders_payment_archive_expired_bank_requests($db, (int)$user['id'], (int)$selected['id']);

        if (isset($_POST['cancel_crypto_payment'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
            } else {
            $openCryptoRequest = $db->select_user(
                "SELECT id
                 FROM crypto_deposit_requests
                 WHERE customer_id = " . (int)$user['id'] . "
                   AND order_id = " . (int)$selected['id'] . "
                   AND status IN ('pending', 'awaiting_confirmation', 'awaiting_review')
                 ORDER BY id DESC
                 LIMIT 1"
            );

            if ($openCryptoRequest) {
                orders_payment_cancel_open_crypto_requests($db, (int)$user['id'], (int)$selected['id']);
                $db->update_using_id(['payment_method'], [null], 'orders', (int)$selected['id']);
                if ($isSameOrderRenewalFlow) {
                    app_order_cancel_pending_renewal(
                        $db,
                        (int)$selected['id'],
                        'Cancelled from customer crypto renewal flow',
                        'crypto',
                        (int)$openCryptoRequest['id']
                    );
                }
                $smarty->assign('alert', localization_translate($t, 'payment_request_cancelled'));
                $selectedMethod = '';
            } else {
                $smarty->assign('alert_error', localization_translate($t, 'payment_request_not_found'));
            }

            $selected = orders_payment_load_v2_order($db, (int)$user['id'], $orderId);
            if ($selected) {
                $selected['date_end_s'] = !empty($selected['date_end']) ? strtotime($selected['date_end']) : 0;
            }
            }
        }

        if (isset($_POST['cancel_bank_payment'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
            } else {
            $openBankRequest = $db->select_user(
                "SELECT id
                 FROM bank_transfer_requests
                 WHERE customer_id = " . (int)$user['id'] . "
                   AND order_id = " . (int)$selected['id'] . "
                   AND status IN ('pending_payment', 'awaiting_review')
                 ORDER BY id DESC
                 LIMIT 1"
            );

            if ($openBankRequest) {
                orders_payment_cancel_open_bank_requests($db, (int)$user['id'], (int)$selected['id']);
                $db->update_using_id(['payment_method'], [null], 'orders', (int)$selected['id']);
                if ($isSameOrderRenewalFlow) {
                    app_order_cancel_pending_renewal(
                        $db,
                        (int)$selected['id'],
                        'Cancelled from customer bank renewal flow',
                        'bank',
                        (int)$openBankRequest['id']
                    );
                }
                $smarty->assign('alert', localization_translate($t, 'payment_request_cancelled'));
                $selectedMethod = '';
            } else {
                $smarty->assign('alert_error', localization_translate($t, 'payment_request_not_found'));
            }

            $selected = orders_payment_load_v2_order($db, (int)$user['id'], $orderId);
            if ($selected) {
                $selected['date_end_s'] = !empty($selected['date_end']) ? strtotime($selected['date_end']) : 0;
            }
            }
        }

        if ($canRequestPayment && isset($_POST['create_crypto_payment'])) {
            $selectedMethod = 'crypto';
            if ($pendingBalanceTopupPayment) {
                $paymentRedirectUrl = (string)($pendingBalanceTopupPayment['payment_url'] ?? '/cryptocurrency');
            } elseif (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
            } elseif (!$cryptoEnabled) {
                $smarty->assign('alert_error', 'Crypto payments are disabled.');
            } elseif (!$selectedProduct) {
                $smarty->assign('alert_error', 'Choose a package first.');
            } else {
                $createdWalletAssignmentNow = false;
                $renewalState = null;
                $selectedAssetId = isset($_POST['crypto_wallet_assignment_id']) ? (int)$_POST['crypto_wallet_assignment_id'] : 0;
                $selectedAsset = null;
                foreach ($cryptoAssets as $asset) {
                    if ((int)$asset['id'] === $selectedAssetId) {
                        $selectedAsset = $asset;
                        break;
                    }
                }

                if (!$selectedAsset) {
                    $smarty->assign('alert_error', 'Choose a cryptocurrency.');
                } elseif ($selectedAsset['rate'] <= 0) {
                    $smarty->assign('alert_error', 'Exchange rate is unavailable for the selected cryptocurrency.');
                } else {
                    if ((int)($selectedAsset['wallet_assignment_id'] ?? 0) <= 0) {
                        $assignedWalletId = app_assign_customer_crypto_wallet(
                            $db,
                            (int)($selectedAsset['wallet_address_id'] ?? 0),
                            (int)$user['id'],
                            is_array($settings ?? null) ? $settings : [],
                            (int)$selected['id'],
                            'Assigned from customer payment wizard',
                            'deposit',
                            'active'
                        );
                        if ($assignedWalletId <= 0) {
                            $smarty->assign('alert_error', 'No wallet could be assigned to your account for this cryptocurrency right now.');
                            $selectedAsset = null;
                        } else {
                            $createdWalletAssignmentNow = true;
                            $selectedAsset['wallet_assignment_id'] = $assignedWalletId;
                            $selectedAsset['is_assigned'] = true;
                        }
                    }

                    if (!$selectedAsset) {
                        // error already assigned above
                    } else {
                    if ($isSameOrderRenewalFlow) {
                        $renewalState = app_order_upsert_pending_renewal(
                            $db,
                            $selected,
                            $selectedProduct,
                            'Created from customer crypto renewal flow'
                        );
                        if (empty($renewalState['ok'])) {
                            if ($createdWalletAssignmentNow && !empty($selectedAsset['wallet_assignment_id'])) {
                                app_release_crypto_wallet_assignment_if_unused($db, (int)$selectedAsset['wallet_assignment_id'], 'Released after failed customer renewal request creation');
                            }
                            $smarty->assign('alert_error', (string)($renewalState['message'] ?? 'Unable to prepare renewal request right now.'));
                            $selectedAsset = null;
                        }
                    }

                    if (!$selectedAsset) {
                        // error already assigned above
                    } else {
                    orders_payment_cancel_open_bank_requests($db, (int)$user['id'], (int)$selected['id']);

                    if (!$isSameOrderRenewalFlow) {
                        $newExpiry = date('Y-m-d H:i:s', $time_s + (3600 * max(1, (int)$selectedProduct['duration'])));
                        $db->update_using_id(
                            ['product_id', 'total_amount', 'currency_id', 'expires_at', 'payment_method'],
                            [
                                (int)$selectedProduct['id'],
                                (float)$selectedProduct['price'],
                                (int)$selectedProduct['currency_id'],
                                $newExpiry,
                                'crypto',
                            ],
                            'orders',
                            (int)$selected['id']
                        );
                    }

                    $existingCryptoRequestWhere = (int)($selectedAsset['wallet_assignment_id'] ?? 0) > 0
                        ? "wallet_assignment_id = " . (int)$selectedAsset['wallet_assignment_id']
                        : "wallet_address_id = " . (int)($selectedAsset['wallet_address_id'] ?? 0);
                    $existingCryptoRequest = $db->select_user(
                        "SELECT id
                         FROM crypto_deposit_requests
                         WHERE customer_id = " . (int)$user['id'] . "
                           AND order_id = " . (int)$selected['id'] . "
                           AND {$existingCryptoRequestWhere}
                           AND status IN ('pending', 'awaiting_confirmation', 'awaiting_review')
                         ORDER BY id DESC
                         LIMIT 1"
                    );

                    if ($existingCryptoRequest) {
                        $requestedCryptoAmount = round(((float)$selectedProduct['price']) / (float)$selectedAsset['rate'], 8);
                        $db->update_using_id(
                            ['requested_fiat_amount', 'fiat_currency_id', 'exchange_rate', 'requested_crypto_amount', 'requested_at', 'expires_at', 'request_note'],
                            [
                                (float)$selectedProduct['price'],
                                $selectedProductCurrencyId,
                                (float)$selectedAsset['rate'],
                                $requestedCryptoAmount,
                                date('Y-m-d H:i:s', $time_s),
                                date('Y-m-d H:i:s', $time_s + 3600),
                                $isSameOrderRenewalFlow ? 'Updated from customer crypto renewal flow' : 'Updated from customer payment wizard',
                            ],
                            'crypto_deposit_requests',
                            (int)$existingCryptoRequest['id']
                        );
                        if ($isSameOrderRenewalFlow && !empty($renewalState['renewal_id'])) {
                            app_order_attach_renewal_payment_request($db, (int)$renewalState['renewal_id'], 'crypto', (int)$existingCryptoRequest['id']);
                        }
                        $paymentRedirectUrl = '/order-payment-' . (int)$selected['id'];
                    } else {
                        $requestedCryptoAmount = round(((float)$selectedProduct['price']) / (float)$selectedAsset['rate'], 8);
                        $createdCryptoRequestId = app_create_crypto_deposit_request($db, [
                            'customer_id' => (int)$user['id'],
                            'order_id' => (int)$selected['id'],
                            'crypto_asset_id' => (int)$selectedAsset['crypto_asset_id'],
                            'wallet_address_id' => (int)$selectedAsset['wallet_address_id'],
                            'wallet_assignment_id' => (int)$selectedAsset['wallet_assignment_id'],
                            'requested_fiat_amount' => (float)$selectedProduct['price'],
                            'fiat_currency_id' => $selectedProductCurrencyId,
                            'exchange_rate' => (float)$selectedAsset['rate'],
                            'requested_crypto_amount' => $requestedCryptoAmount,
                            'assignment_mode' => 'manual',
                            'status' => 'pending',
                            'requested_at' => date('Y-m-d H:i:s', $time_s),
                            'expires_at' => date('Y-m-d H:i:s', $time_s + 3600),
                            'request_note' => 'Created from customer payment wizard',
                        ]);
                        if ($createdCryptoRequestId > 0) {
                            if ($isSameOrderRenewalFlow && !empty($renewalState['renewal_id'])) {
                                app_order_attach_renewal_payment_request($db, (int)$renewalState['renewal_id'], 'crypto', $createdCryptoRequestId);
                            }
                            app_delete_cancelled_crypto_requests_for_asset(
                                $db,
                                (int)$user['id'],
                                (int)$selectedAsset['crypto_asset_id']
                            );
                            app_queue_payment_request_notification($db, (int)$selected['id'], (int)$user['id']);
                            app_queue_support_payment_request_notification($db, (int)$selected['id'], (int)$user['id'], 'crypto');
                            app_queue_crypto_payment_live_chat_message(
                                $db,
                                (int)$user['id'],
                                (int)$selected['id'],
                                [
                                    'asset_name' => (string)($selectedAsset['name'] ?? ''),
                                    'asset_code' => (string)($selectedAsset['code'] ?? ''),
                                    'requested_fiat_amount' => (float)$selectedProduct['price'],
                                    'currency_symbol' => (string)($selectedProduct['currency_symbol'] ?? $selected['currency_symbol'] ?? ''),
                                    'currency_code' => (string)($selectedProduct['currency_code'] ?? $selected['currency_code'] ?? ''),
                                    'requested_crypto_amount' => (string)$requestedCryptoAmount,
                                    'wallet_address' => (string)($selectedAsset['wallet_address'] ?? ''),
                                    'wallet_owner_full_name' => (string)($selectedAsset['wallet_owner_full_name'] ?? ''),
                                ]
                            );
                            $paymentRedirectUrl = '/order-payment-' . (int)$selected['id'];
                        } else {
                            if ($createdWalletAssignmentNow && !empty($selectedAsset['wallet_assignment_id'])) {
                                app_release_crypto_wallet_assignment_if_unused($db, (int)$selectedAsset['wallet_assignment_id'], 'Released after failed customer payment request creation');
                            }
                            $smarty->assign('alert_error', 'Unable to create crypto payment request right now.');
                        }
                    }

                    $selected = orders_payment_load_v2_order($db, (int)$user['id'], $orderId);
                    $selected['date_end_s'] = !empty($selected['date_end']) ? strtotime($selected['date_end']) : 0;
                    }
                    }
                }
            }
        }

        if ($canRequestPayment && isset($_POST['create_bank_payment'])) {
            $selectedMethod = 'bank_transfer';
            if ($pendingBalanceTopupPayment) {
                $paymentRedirectUrl = (string)($pendingBalanceTopupPayment['payment_url'] ?? '/cryptocurrency');
            } elseif (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
            } elseif (!$bankEnabled) {
                $smarty->assign('alert_error', 'Bank transfers are disabled.');
            } elseif (!$selectedProduct) {
                $smarty->assign('alert_error', 'Choose a package first.');
            } elseif (!$bankAccounts) {
                $smarty->assign('alert_error', 'No bank account has been assigned to your account yet.');
            } else {
                $renewalState = null;
                orders_payment_cancel_open_crypto_requests($db, (int)$user['id'], (int)$selected['id']);

                $selectedBankAssignmentId = isset($_POST['bank_account_assignment_id']) ? (int)$_POST['bank_account_assignment_id'] : (int)$bankAccounts[0]['bank_account_assignment_id'];
                $selectedBankAccount = null;
                foreach ($bankAccounts as $bankAccount) {
                    if ((int)$bankAccount['bank_account_assignment_id'] === $selectedBankAssignmentId) {
                        $selectedBankAccount = $bankAccount;
                        break;
                    }
                }
                if (!$selectedBankAccount) {
                    $selectedBankAccount = $bankAccounts[0];
                }

                if ((int)($selectedBankAccount['bank_account_assignment_id'] ?? 0) <= 0) {
                    $assignedBankAccountId = orders_payment_assign_bank_account_customer(
                        $db,
                        (int)($selectedBankAccount['bank_account_id'] ?? 0),
                        (int)$user['id'],
                        (int)$selected['id']
                    );
                    if ($assignedBankAccountId <= 0) {
                        $smarty->assign('alert_error', 'No bank account could be assigned to your account right now.');
                        $selectedBankAccount = null;
                    } else {
                        $selectedBankAccount['bank_account_assignment_id'] = $assignedBankAccountId;
                    }
                }

                if (!$selectedBankAccount) {
                    // error already assigned above
                } else {
                if ($isSameOrderRenewalFlow) {
                    $renewalState = app_order_upsert_pending_renewal(
                        $db,
                        $selected,
                        $selectedProduct,
                        'Created from customer bank renewal flow'
                    );
                    if (empty($renewalState['ok'])) {
                        $smarty->assign('alert_error', (string)($renewalState['message'] ?? 'Unable to prepare renewal request right now.'));
                        $selectedBankAccount = null;
                    }
                }

                if (!$selectedBankAccount) {
                    // error already assigned above
                } else {

                if (!$isSameOrderRenewalFlow) {
                    $newExpiry = date('Y-m-d H:i:s', $time_s + (3600 * max(1, (int)$selectedProduct['duration'])));
                    $db->update_using_id(
                        ['product_id', 'total_amount', 'currency_id', 'expires_at', 'payment_method'],
                        [
                            (int)$selectedProduct['id'],
                            (float)$selectedProduct['price'],
                            (int)$selectedProduct['currency_id'],
                            $newExpiry,
                            'bank_transfer',
                        ],
                        'orders',
                        (int)$selected['id']
                    );
                }

                $existingBankRequest = $db->select_user(
                    "SELECT id
                     FROM bank_transfer_requests
                     WHERE customer_id = " . (int)$user['id'] . "
                       AND order_id = " . (int)$selected['id'] . "
                       AND status IN ('pending_payment', 'awaiting_review')
                     ORDER BY id DESC
                     LIMIT 1"
                );

                if ($existingBankRequest) {
                    $db->update_using_id(
                        ['requested_amount', 'currency_id', 'requested_at', 'expires_at', 'customer_transfer_note'],
                        [
                            (float)$selectedProduct['price'],
                            $selectedProductCurrencyId,
                            date('Y-m-d H:i:s', $time_s),
                            date('Y-m-d H:i:s', $time_s + 3600),
                            $isSameOrderRenewalFlow ? 'Updated from customer bank renewal flow' : 'Updated from customer payment wizard',
                        ],
                        'bank_transfer_requests',
                        (int)$existingBankRequest['id']
                    );
                    if ($isSameOrderRenewalFlow && !empty($renewalState['renewal_id'])) {
                        app_order_attach_renewal_payment_request($db, (int)$renewalState['renewal_id'], 'bank', (int)$existingBankRequest['id']);
                    }
                    $paymentRedirectUrl = '/order-payment-' . (int)$selected['id'];
                } else {
                    $createdBankRequest = $db->insert(
                        [
                            'customer_id',
                            'order_id',
                            'bank_account_id',
                            'bank_account_assignment_id',
                            'requested_amount',
                            'currency_id',
                            'payment_reference',
                            'status',
                            'requested_at',
                            'expires_at',
                            'customer_transfer_note',
                        ],
                        [
                            (int)$user['id'],
                            (int)$selected['id'],
                            (int)$selectedBankAccount['bank_account_id'],
                            (int)$selectedBankAccount['bank_account_assignment_id'],
                            (float)$selectedProduct['price'],
                            $selectedProductCurrencyId,
                            '',
                            'pending_payment',
                            date('Y-m-d H:i:s', $time_s),
                            date('Y-m-d H:i:s', $time_s + 3600),
                            'Created from customer payment wizard',
                        ],
                        'bank_transfer_requests'
                    );

                    $bankRequestId = (int)$db->id();
                    if ($createdBankRequest && $bankRequestId > 0) {
                        if ($isSameOrderRenewalFlow && !empty($renewalState['renewal_id'])) {
                            app_order_attach_renewal_payment_request($db, (int)$renewalState['renewal_id'], 'bank', $bankRequestId);
                        }
                        $paymentReference = orders_payment_build_bank_reference(
                            (string)($selectedBankAccount['payment_reference_template'] ?? ''),
                            (int)$user['id'],
                            $bankRequestId,
                            (int)$selected['id']
                        );
                        $db->update_using_id(['payment_reference'], [$paymentReference], 'bank_transfer_requests', $bankRequestId);
                        app_queue_payment_request_notification($db, (int)$selected['id'], (int)$user['id']);
                        app_queue_support_payment_request_notification($db, (int)$selected['id'], (int)$user['id'], 'bank transfer');
                        $paymentRedirectUrl = '/order-payment-' . (int)$selected['id'];
                    } else {
                        $smarty->assign('alert_error', 'Unable to create bank transfer request right now.');
                    }
                }

                $selected = orders_payment_load_v2_order($db, (int)$user['id'], $orderId);
                $selected['date_end_s'] = !empty($selected['date_end']) ? strtotime($selected['date_end']) : 0;
                }
                }
            }
        }

        if ($canRequestPayment && isset($_POST['create_balance_payment'])) {
            $selectedMethod = 'balance';
            if ($pendingBalanceTopupPayment) {
                $paymentRedirectUrl = (string)($pendingBalanceTopupPayment['payment_url'] ?? '/cryptocurrency');
            } elseif (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
            } elseif (!$selectedProduct) {
                $smarty->assign('alert_error', localization_translate($t, 'payment_choose_package_error', 'Choose a package first.'));
            } elseif ($blockingPendingActivationOrder) {
                $smarty->assign(
                    'alert_error',
                    localization_translate(
                        $t,
                        'payment_balance_blocked_pending_activation',
                        'Balance payment is unavailable while another paid order is still waiting for activation.'
                    )
                );
            } else {
                $selectedProductPrice = round((float)($selectedProduct['price'] ?? 0), 2);
                $customerBalanceAmount = orders_payment_load_customer_balance_amount($db, (int)$user['id']);
                if ($selectedProductPrice <= 0) {
                    $smarty->assign('alert_error', localization_translate($t, 'payment_balance_unavailable', 'Balance payment is unavailable for this order.'));
                } elseif ($customerBalanceAmount + 0.00001 < $selectedProductPrice) {
                    $smarty->assign('alert_error', localization_translate($t, 'payment_balance_too_low', 'Your account balance is too low for this payment.'));
                } else {
                    orders_payment_cancel_open_crypto_requests($db, (int)$user['id'], (int)$selected['id']);
                    orders_payment_cancel_open_bank_requests($db, (int)$user['id'], (int)$selected['id']);
                    $balanceDebitResult = app_apply_customer_balance_runtime_event(
                        $db,
                        (int)$user['id'],
                        $selectedProductPrice,
                        'debit',
                        'order_activation',
                        (string)((int)$selected['id']),
                        'Paid from customer balance for order #' . (int)$selected['id'],
                        'customer',
                        0,
                        (string)($_SERVER['REMOTE_ADDR'] ?? '')
                    );

                    if (empty($balanceDebitResult['ok'])) {
                        $smarty->assign('alert_error', localization_translate($t, 'payment_balance_debit_error', 'Unable to pay from account balance right now.'));
                    } else {
                        $updated = false;
                        if ($isSameOrderRenewalFlow) {
                            $renewalState = app_order_upsert_pending_renewal(
                                $db,
                                $selected,
                                $selectedProduct,
                                'Paid from customer balance'
                            );

                            if (empty($renewalState['ok'])) {
                                $updated = false;
                                $smarty->assign('alert_error', (string)($renewalState['message'] ?? localization_translate($t, 'payment_balance_update_error', 'Payment was not saved for this order.')));
                            } else {
                                $applyRenewalResult = app_apply_pending_order_renewal(
                                    $db,
                                    (int)$renewalState['renewal_id'],
                                    'customer',
                                    0,
                                    (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                                    true
                                );
                                $updated = !empty($applyRenewalResult['ok']);
                                if (!$updated) {
                                    app_order_cancel_pending_renewal(
                                        $db,
                                        (int)$selected['id'],
                                        'Cancelled after failed balance renewal apply'
                                    );
                                }
                            }
                        } else {
                            $newExpiry = date('Y-m-d H:i:s', $time_s + (3600 * max(1, (int)$selectedProduct['duration'])));
                            $updated = $db->update_using_id(
                                ['product_id', 'total_amount', 'currency_id', 'expires_at', 'payment_method', 'payment_status', 'fulfillment_status', 'paid_at', 'status'],
                                [
                                    (int)$selectedProduct['id'],
                                    $selectedProductPrice,
                                    (int)$selectedProduct['currency_id'],
                                    $newExpiry,
                                    'balance',
                                    'paid',
                                    'pending',
                                    date('Y-m-d H:i:s', $time_s),
                                    'pending_payment',
                                ],
                                'orders',
                                (int)$selected['id']
                            );
                        }

                        if (!$updated) {
                            if (empty($balanceDebitResult['already_applied'])) {
                                app_apply_customer_balance_runtime_event(
                                    $db,
                                    (int)$user['id'],
                                    $selectedProductPrice,
                                    'credit',
                                    'order_balance_payment_rollback',
                                    (string)((int)$selected['id']) . ':' . $time_s,
                                    'Rollback after failed balance payment save for order #' . (int)$selected['id'],
                                    'system',
                                    0,
                                    (string)($_SERVER['REMOTE_ADDR'] ?? '')
                                );
                            }
                            if (!$smarty->getTemplateVars('alert_error')) {
                                $smarty->assign('alert_error', localization_translate($t, 'payment_balance_update_error', 'Payment was not saved for this order.'));
                            }
                        } else {
                            if (!$isSameOrderRenewalFlow && schema_object_exists($db, 'order_status_events')) {
                                $db->insert(
                                    ['order_id', 'admin_user_id', 'old_status', 'new_status', 'event_note'],
                                    [(int)$selected['id'], null, (string)($selected['status_raw'] ?? 'pending_payment'), 'pending_payment', 'Payment confirmed from customer balance'],
                                    'order_status_events'
                                );
                            }
                            if (!$isSameOrderRenewalFlow) {
                                app_customer_activity_log(
                                    $db,
                                    (int)$user['id'],
                                    'order_payment_approved',
                                    'Order #' . (int)$selected['id'] . ' was paid from account balance.',
                                    'customer',
                                    0,
                                    (string)($_SERVER['REMOTE_ADDR'] ?? '')
                                );
                                $paymentRedirectUrl = '/order-payment-' . (int)$selected['id'];
                            } else {
                                $paymentRedirectUrl = '/orders';
                            }
                        }
                    }
                    $selected = orders_payment_load_v2_order($db, (int)$user['id'], $orderId);
                    if ($selected) {
                        $selected['date_end_s'] = !empty($selected['date_end']) ? strtotime($selected['date_end']) : 0;
                    }
                }
            }
        }

        $cryptoRequest = $db->select_user(
            "SELECT
                crypto_deposit_requests.*,
                crypto_assets.code AS crypto_code,
                crypto_assets.name AS crypto_name,
                crypto_assets.logo_url AS crypto_logo_url,
                crypto_wallet_addresses.label AS wallet_label,
                crypto_wallet_addresses.owner_full_name AS wallet_owner_full_name,
                crypto_wallet_addresses.network_code AS wallet_network_code,
                crypto_wallet_addresses.wallet_provider AS wallet_provider,
                crypto_wallet_addresses.address AS wallet_address,
                crypto_wallet_addresses.memo_tag AS wallet_memo_tag,
                crypto_wallet_addresses.qr_url AS wallet_qr_url
             FROM crypto_deposit_requests
             LEFT JOIN crypto_assets ON crypto_assets.id = crypto_deposit_requests.crypto_asset_id
             LEFT JOIN crypto_wallet_addresses ON crypto_wallet_addresses.id = crypto_deposit_requests.wallet_address_id
             WHERE crypto_deposit_requests.customer_id = " . (int)$user['id'] . "
               AND crypto_deposit_requests.order_id = " . (int)$selected['id'] . "
             ORDER BY crypto_deposit_requests.id DESC
             LIMIT 1"
        );
        if ($cryptoRequest) {
            $cryptoRequest['crypto_logo_path'] = orders_payment_crypto_logo_by_code((string)($cryptoRequest['crypto_code'] ?? ''), (string)($cryptoRequest['crypto_logo_url'] ?? ''));
            $cryptoRequest['qr_code_url'] = orders_payment_crypto_qr_url($cryptoRequest);
            $cryptoRequest['wallet_network_label'] = orders_payment_crypto_network_label((string)($cryptoRequest['wallet_network_code'] ?? ''));
        }

        $bankRequest = $db->select_user(
            "SELECT
                bank_transfer_requests.*,
                currencies.code AS currency_code,
                bank_accounts.label,
                bank_accounts.account_holder_name,
                bank_accounts.bank_name,
                bank_accounts.bank_address,
                bank_accounts.country_code,
                bank_accounts.iban,
                bank_accounts.account_number,
                bank_accounts.routing_number,
                bank_accounts.swift_bic,
                bank_accounts.transfer_instructions
             FROM bank_transfer_requests
             LEFT JOIN bank_accounts
                ON bank_accounts.id = bank_transfer_requests.bank_account_id
             LEFT JOIN currencies
                ON currencies.id = bank_transfer_requests.currency_id
             WHERE bank_transfer_requests.customer_id = " . (int)$user['id'] . "
               AND bank_transfer_requests.order_id = " . (int)$selected['id'] . "
             ORDER BY bank_transfer_requests.id DESC
             LIMIT 1"
        );

        if (!$bankEnabled) {
            $bankRequest = null;
        }

        $hasActiveCryptoRequest = $cryptoRequest && in_array((string)($cryptoRequest['status'] ?? ''), ['pending', 'awaiting_confirmation', 'awaiting_review'], true);
        $hasActiveBankRequest = $bankRequest && in_array((string)($bankRequest['status'] ?? ''), ['pending_payment', 'awaiting_review'], true);
        $activeRequestMethod = '';

        if ($hasActiveCryptoRequest && ((string)($selected['payment_method_raw'] ?? '') === 'crypto' || !$hasActiveBankRequest)) {
            $activeRequestMethod = 'crypto';
        } elseif ($hasActiveBankRequest) {
            $activeRequestMethod = 'bank_transfer';
        }

        if ($activeRequestMethod !== '') {
            $selectedMethod = $activeRequestMethod;
        }

        $activeRequestExpiresAt = '';
        $activeRequestRemainingSeconds = 0;
        if ($activeRequestMethod === 'crypto' && !empty($cryptoRequest['expires_at'])) {
            $activeRequestExpiresAt = (string)$cryptoRequest['expires_at'];
            $activeRequestRemainingSeconds = max(0, strtotime($activeRequestExpiresAt) - $time_s);
        } elseif ($activeRequestMethod === 'bank_transfer' && !empty($bankRequest['expires_at'])) {
            $activeRequestExpiresAt = (string)$bankRequest['expires_at'];
            $activeRequestRemainingSeconds = max(0, strtotime($activeRequestExpiresAt) - $time_s);
        }

        $statusRaw = strtolower(trim((string)($selected['status_raw'] ?? '')));
        $paymentStatusRaw = strtolower(trim((string)($selected['payment_status_raw'] ?? '')));
        $fulfillmentStatusRaw = strtolower(trim((string)($selected['fulfillment_status_raw'] ?? '')));
        $paymentStateNotice = '';

        if ($activeRequestMethod === '' && !$canRequestPayment) {
            if ($paymentStatusRaw === 'paid' && $statusRaw !== 'active' && !in_array($fulfillmentStatusRaw, ['delivered', 'fulfilled', 'completed'], true)) {
                $paymentStateNotice = 'paid_pending_activation';
            } elseif ($paymentStatusRaw === 'paid' || $statusRaw === 'active') {
                $paymentStateNotice = 'already_paid';
            } else {
                $paymentStateNotice = 'closed';
            }
        }

        $customerBalanceAmount = orders_payment_load_customer_balance_amount($db, (int)$user['id']);
        $selectedProductPrice = round((float)($selectedProduct['price'] ?? $selected['price'] ?? 0), 2);
        $canUseBalance = !$blockingPendingActivationOrder
            && $selectedProduct
            && $selectedProductPrice > 0
            && $customerBalanceAmount + 0.00001 >= $selectedProductPrice;

        $smarty->assign('selected', $selected);
        $smarty->assign('payment_can_request', $canRequestPayment);
        $smarty->assign('payment_selected_method', $selectedMethod);
        $smarty->assign('payment_active_request_method', $activeRequestMethod);
        $smarty->assign('payment_active_request_expires_at', $activeRequestExpiresAt);
        $smarty->assign('payment_active_request_remaining_seconds', $activeRequestRemainingSeconds);
        $smarty->assign('payment_products', $availableProducts);
        $smarty->assign('payment_selected_product_id', $selectedProductId);
        $smarty->assign('payment_crypto_assets', $cryptoAssets);
        $smarty->assign('payment_has_crypto_assignments', $hasAssignedCryptoWallets);
        $smarty->assign('payment_can_use_crypto', $canUseCrypto);
        $smarty->assign('payment_can_use_bank', $canUseBank);
        $smarty->assign('payment_can_use_balance', $canUseBalance);
        $smarty->assign('payment_customer_balance_amount', number_format($customerBalanceAmount, 2, '.', ''));
        $smarty->assign('payment_balance_blocking_order', $blockingPendingActivationOrder ?: null);
        $smarty->assign('payment_bank_accounts', $bankAccounts);
        $smarty->assign('payment_crypto_request', $cryptoRequest ?: null);
        $smarty->assign('payment_bank_request', $bankRequest ?: null);
        $smarty->assign('payment_state_notice', $paymentStateNotice);
        $smarty->assign('payment_redirect_url', $paymentRedirectUrl);
        $smarty->assign('payment_pending_topup_request', $pendingBalanceTopupPayment ?: null);
    }
} else {
    $ask = "SELECT products_users.*, products.name AS name, products.status AS status_product, 
                  products.duration AS duration,  
                  products.trial AS trial, 
                  products.provider_id AS provider_id,
                  products_providers.name AS provider_name
                  FROM products_users, products, products_providers
                  WHERE products_users.product_id = products.id
                  AND products.provider_id = products_providers.id
                  AND products_users.res_id = '{$tenantId}'
                  AND products_users.user_id = '{$user["id"]}'
                  AND products_users.id = '{$orderId}'";
    $selected = $db->select_user($ask);
    if ($selected) {
        $selected['date_end_s'] = strtotime($selected['date_end']);
        $smarty->assign('selected', $selected);
    }
}
