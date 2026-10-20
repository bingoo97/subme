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
            'USDC' => 'usdc.png',
            'CRO' => 'cro.png',
            'ATOM' => 'atom.png',
            'SOL' => 'sol.png',
            'MATIC' => 'matic.png',
            'XRP' => 'xrp.png',
            'TRX' => 'trx.png',
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
        $productType = app_normalize_product_type($product['product_type'] ?? 'subscription');
        $durationHours = isset($product['duration']) ? (int)$product['duration'] : 0;
        $price = isset($product['price']) ? number_format((float)$product['price'], 2, '.', '') : '0.00';
        $isTrial = !empty($product['trial']);

        if (!app_product_type_uses_expiry($productType)) {
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
            'TRX' => 'tron',
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
            'tron' => 'TRON',
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
        return app_http_json($url);
    }
}

if (!function_exists('orders_payment_refresh_crypto_rates')) {
    function orders_payment_refresh_crypto_rates($db, string $vsCurrency = 'USD', int $cacheTtl = 900): array
    {
        return app_refresh_crypto_rates($db, $vsCurrency, $cacheTtl);
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
        return function_exists('app_available_bank_account_rows')
            ? app_available_bank_account_rows($db, $currencyCode, app_fetch_settings($db))
            : [];
    }
}

if (!function_exists('orders_payment_available_crypto_wallet_for_asset')) {
    function orders_payment_available_crypto_wallet_for_asset($db, int $assetId, int $customerId = 0, array $settings = []): ?array
    {
        return function_exists('app_find_available_crypto_wallet_for_asset')
            ? app_find_available_crypto_wallet_for_asset($db, $assetId, $customerId, $settings)
            : null;
    }
}

if (!function_exists('orders_payment_assign_crypto_wallet_customer')) {
    function orders_payment_assign_crypto_wallet_customer($db, int $walletId, int $customerId, array $settings = [], int $orderId = 0): int
    {
        return function_exists('app_assign_customer_crypto_wallet')
            ? app_assign_customer_crypto_wallet(
                $db,
                $walletId,
                $customerId,
                $settings,
                $orderId,
                'Assigned from customer payment wizard',
                'deposit',
                'active'
            )
            : 0;
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
             AND status IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')",
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
             AND status IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')
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

if (!function_exists('orders_payment_can_cancel_order_from_selection')) {
    function orders_payment_can_cancel_order_from_selection(array $selected, string $activeRequestMethod, bool $isSameOrderRenewalFlow, bool $hasGeneratedPaymentRequest): bool
    {
        $statusRaw = strtolower(trim((string)($selected['status_raw'] ?? '')));
        $paymentStatusRaw = strtolower(trim((string)($selected['payment_status_raw'] ?? '')));
        $fulfillmentStatusRaw = strtolower(trim((string)($selected['fulfillment_status_raw'] ?? '')));

        return !$isSameOrderRenewalFlow
            && $activeRequestMethod === ''
            && !$hasGeneratedPaymentRequest
            && $statusRaw === 'pending_payment'
            && $paymentStatusRaw === 'unpaid'
            && !in_array($fulfillmentStatusRaw, ['delivered', 'fulfilled', 'completed'], true);
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

// Serialize mutating customer requests with admin payment approval for this customer.
$customerPaymentLock = '';
$customerPaymentActions = ['create_crypto_payment','create_bank_payment','create_balance_payment','cancel_crypto_payment','cancel_bank_payment','cancel_order_from_payment_selection'];
if (app_uses_v2_schema($db) && array_intersect($customerPaymentActions, array_keys($_POST))) {
    if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
        $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
        $smarty->assign('selected', null);
        return;
    }
    $customerPaymentLock = 'payment:' . substr(hash('sha256', (string)getenv('DB_NAME')), 0, 16) . ':' . (int)$user['id'];
    $lock = $db->select_user("SELECT GET_LOCK('" . $db->escape($customerPaymentLock) . "', 0) acquired");
    if ((int)($lock['acquired'] ?? 0) !== 1) {
        $smarty->assign('alert_error', localization_translate($t, 'payment_operation_busy', 'Another payment operation is in progress. Please retry shortly.'));
        $smarty->assign('selected', null);
        return;
    }
}
try {
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
        $canOpenRenewalPayment = $isSameOrderRenewalFlow && app_order_can_open_self_extension_payment($selected);
        $pendingRenewalStatus = strtolower(trim((string)($pendingRenewalRow['status'] ?? '')));
        $hasBlockingPendingRenewalState = $isSameOrderRenewalFlow
            && in_array($pendingRenewalStatus, ['pending_payment', 'paid_pending_activation'], true);
        $pendingBalanceTopupPayment = app_find_customer_pending_balance_topup_payment($db, (int)$user['id']);
        $selectedProductType = strtolower(trim((string)($selected['product_type'] ?? 'subscription')));
        $selected['date_end_s'] = !empty($selected['date_end']) ? strtotime($selected['date_end']) : 0;
        $canRequestPayment = (
            (($selected['status_raw'] ?? '') === 'pending_payment' && ($selected['payment_status_raw'] ?? '') === 'unpaid')
            || $canOpenRenewalPayment
        ) && !$hasBlockingPendingRenewalState;

        $availableProducts = [];
        if (!empty($selected['provider_id'])) {
            $catalogTypeSql = app_customer_order_catalog_product_type_sql($db, $user, $settings);
            $availableProducts = $db->select_full_user(
                "SELECT products.id, products.name, products.provider_id, products.is_active, products.product_type,
                    products.duration_hours, products.duration_hours AS duration, products.price_amount,
                    products.price_amount AS price, products.currency_id, products.is_trial AS trial,
                    currencies.code AS currency_code, currencies.symbol AS currency_symbol
                 FROM products
                 JOIN product_providers ON product_providers.id = products.provider_id
                 LEFT JOIN currencies ON currencies.id = products.currency_id
                 WHERE products.provider_id = " . (int)$selected['provider_id'] . "
                   AND products.is_active = 1 AND product_providers.is_active = 1
                   AND products.product_type = '" . $db->escape($selectedProductType) . "'
                   AND products.product_type IN ({$catalogTypeSql})
                   " . app_customer_provider_visibility_sql($db, (int)$user['id'], 'products.provider_id') . "
                   " . ($trialsEnabled ? '' : "AND products.is_trial = 0") . "
                   " . ($isSameOrderRenewalFlow ? 'AND products.is_trial = 0 AND products.duration_hours > 24' : '') . "
                 ORDER BY products.duration_hours ASC, products.price_amount ASC, products.id ASC"
            );
        }
        $packagePaymentUnavailable = $canRequestPayment && !$availableProducts;
        if ($packagePaymentUnavailable) $canRequestPayment = false;

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
        if (!$selectedProduct && isset($_POST['payment_product_id'])) {
            $canRequestPayment = false;
            $smarty->assign('alert_error', localization_translate($t, 'payment_choose_package_error', 'Choose an available package first.'));
        } elseif (!$selectedProduct && !empty($availableProducts)) {
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
        app_cancel_invalid_open_crypto_deposit_requests(
            $db,
            "customer_id = " . (int)$user['id'] . "
             AND order_id = " . (int)$selected['id'] . "
             AND (expires_at IS NULL OR expires_at > '" . date('Y-m-d H:i:s') . "')",
            is_array($settings ?? null) ? $settings : [],
            'Cancelled invalid order crypto wallet assignment'
        );

        if (isset($_POST['cancel_crypto_payment'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
            } else {
            $openCryptoRequest = $db->select_user(
                "SELECT id
                 FROM crypto_deposit_requests
                 WHERE customer_id = " . (int)$user['id'] . "
                   AND order_id = " . (int)$selected['id'] . "
                   AND status IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')
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
                    if (
                        (int)($selectedAsset['wallet_assignment_id'] ?? 0) > 0
                        && !app_crypto_deposit_wallet_payload_is_valid(
                            $db,
                            (int)$user['id'],
                            (int)($selectedAsset['crypto_asset_id'] ?? 0),
                            (int)($selectedAsset['wallet_address_id'] ?? 0),
                            (int)($selectedAsset['wallet_assignment_id'] ?? 0),
                            is_array($settings ?? null) ? $settings : []
                        )
                    ) {
                        $selectedAsset['wallet_assignment_id'] = 0;
                    }

                    if ((int)($selectedAsset['wallet_assignment_id'] ?? 0) <= 0) {
                        $assignedWallet = app_assign_customer_crypto_wallet_with_fallback(
                            $db,
                            (int)($selectedAsset['wallet_address_id'] ?? 0),
                            (int)($selectedAsset['crypto_asset_id'] ?? 0),
                            (int)$user['id'],
                            is_array($settings ?? null) ? $settings : [],
                            (int)$selected['id'],
                            'Assigned from customer payment wizard',
                            'deposit',
                            'active'
                        );
                        $assignedWalletId = (int)($assignedWallet['wallet_assignment_id'] ?? 0);
                        if ($assignedWalletId <= 0) {
                            $smarty->assign('alert_error', 'No wallet could be assigned to your account for this cryptocurrency right now.');
                            $selectedAsset = null;
                        } else {
                            $createdWalletAssignmentNow = true;
                            $selectedAsset['wallet_assignment_id'] = $assignedWalletId;
                            $selectedAsset['wallet_address_id'] = (int)($assignedWallet['wallet_address_id'] ?? $selectedAsset['wallet_address_id']);
                            $selectedAsset['wallet_address'] = (string)($assignedWallet['address'] ?? $selectedAsset['wallet_address'] ?? '');
                            $selectedAsset['wallet_memo_tag'] = (string)($assignedWallet['memo_tag'] ?? $selectedAsset['wallet_memo_tag'] ?? '');
                            $selectedAsset['wallet_label'] = (string)($assignedWallet['label'] ?? $selectedAsset['wallet_label'] ?? '');
                            $selectedAsset['wallet_owner_full_name'] = (string)($assignedWallet['owner_full_name'] ?? $selectedAsset['wallet_owner_full_name'] ?? '');
                            $selectedAsset['wallet_notes'] = (string)($assignedWallet['notes'] ?? $selectedAsset['wallet_notes'] ?? '');
                            $selectedAsset['wallet_provider'] = (string)($assignedWallet['wallet_provider'] ?? $selectedAsset['wallet_provider'] ?? '');
                            $selectedAsset['network_code'] = (string)($assignedWallet['network_code'] ?? $selectedAsset['network_code'] ?? '');
                            $selectedAsset['network_label'] = app_crypto_network_label((string)($selectedAsset['network_code'] ?? ''));
                            $selectedAsset['wallet_owner_location'] = app_wallet_owner_location_resolve(
                                (string)($selectedAsset['wallet_owner_full_name'] ?? ''),
                                (string)($selectedAsset['wallet_notes'] ?? '')
                            );
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
                        $newExpiry = app_product_type_uses_expiry($selectedProduct['product_type'] ?? 'subscription')
                            ? app_subscription_expiry_from_base_timestamp($time_s, max(1, (int)$selectedProduct['duration']))
                            : null;
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
                           AND status IN ('pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')
                         ORDER BY id DESC
                         LIMIT 1"
                    );

                    if ($existingCryptoRequest) {
                        $requestedCryptoAmount = round(((float)$selectedProduct['price']) / (float)$selectedAsset['rate'], 8);
                        $db->update_using_id(
                            ['crypto_asset_id', 'wallet_address_id', 'wallet_assignment_id', 'requested_fiat_amount', 'fiat_currency_id', 'exchange_rate', 'requested_crypto_amount', 'requested_at', 'expires_at', 'request_note'],
                            [
                                (int)$selectedAsset['crypto_asset_id'],
                                (int)$selectedAsset['wallet_address_id'],
                                (int)$selectedAsset['wallet_assignment_id'],
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
                                    'wallet_owner_location' => (string)($selectedAsset['wallet_owner_location'] ?? ''),
                                    'wallet_notes' => (string)($selectedAsset['wallet_notes'] ?? ''),
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
                    $newExpiry = app_product_type_uses_expiry($selectedProduct['product_type'] ?? 'subscription')
                        ? app_subscription_expiry_from_base_timestamp($time_s, max(1, (int)$selectedProduct['duration']))
                        : null;
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
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
            } elseif (!array_key_exists('payment_expected_expires_at', $_POST)) {
                $smarty->assign('alert_error', localization_translate($t, 'payment_order_changed', 'The order changed. Refresh the page before retrying.'));
            } else {
                $balanceResult = app_pay_order_from_customer_balance($db, (int)$user['id'], (int)$selected['id'],
                    (int)$selectedProductId, $settings, (string)$_POST['payment_expected_expires_at'], (string)($_SERVER['REMOTE_ADDR'] ?? ''));
                if (empty($balanceResult['ok'])) {
                    $smarty->assign('alert_error', localization_translate($t, (string)($balanceResult['message_key'] ?? 'payment_balance_update_error'), 'Unable to save payment. Refresh the page and check the balance before retrying.'));
                } else {
                    if (empty($balanceResult['already_applied'])) {
                        orders_payment_cancel_open_crypto_requests($db, (int)$user['id'], (int)$selected['id']);
                        orders_payment_cancel_open_bank_requests($db, (int)$user['id'], (int)$selected['id']);
                    }
                    $paymentRedirectUrl = !empty($balanceResult['renewal']) ? '/orders' : '/order-payment-' . (int)$selected['id'];
                }
                $selected = orders_payment_load_v2_order($db, (int)$user['id'], $orderId);
                if ($selected) $selected['date_end_s'] = !empty($selected['date_end']) ? strtotime($selected['date_end']) : 0;
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
                crypto_wallet_addresses.notes AS wallet_notes,
                crypto_wallet_addresses.network_code AS wallet_network_code,
                crypto_wallet_addresses.wallet_provider AS wallet_provider,
                crypto_wallet_addresses.address AS wallet_address,
                crypto_wallet_addresses.memo_tag AS wallet_memo_tag,
                crypto_wallet_addresses.qr_url AS wallet_qr_url,
                currencies.symbol AS currency_symbol,
                currencies.code AS currency_code
             FROM crypto_deposit_requests
             LEFT JOIN crypto_assets ON crypto_assets.id = crypto_deposit_requests.crypto_asset_id
             LEFT JOIN crypto_wallet_addresses ON crypto_wallet_addresses.id = crypto_deposit_requests.wallet_address_id
             LEFT JOIN currencies ON currencies.id = crypto_deposit_requests.fiat_currency_id
             WHERE crypto_deposit_requests.customer_id = " . (int)$user['id'] . "
               AND crypto_deposit_requests.order_id = " . (int)$selected['id'] . "
             ORDER BY crypto_deposit_requests.id DESC
             LIMIT 1"
        );
        if ($cryptoRequest) {
                $cryptoRequest=array_merge($cryptoRequest,app_crypto_request_receipt_labels($cryptoRequest));
                $cryptoRequest['payment_detected'] = (string)($cryptoRequest['status'] ?? '') === 'awaiting_review' && (float)($cryptoRequest['received_crypto_amount'] ?? 0) > 0;
            $cryptoRequest['crypto_logo_path'] = orders_payment_crypto_logo_by_code((string)($cryptoRequest['crypto_code'] ?? ''), (string)($cryptoRequest['crypto_logo_url'] ?? ''));
            $cryptoRequest['qr_code_url'] = orders_payment_crypto_qr_url($cryptoRequest);
            $cryptoRequest['wallet_network_label'] = orders_payment_crypto_network_label((string)($cryptoRequest['wallet_network_code'] ?? ''));
            $cryptoRequest['wallet_owner_location'] = app_wallet_owner_location_resolve(
                (string)($cryptoRequest['wallet_owner_full_name'] ?? ''),
                (string)($cryptoRequest['wallet_notes'] ?? '')
            );
        }

        $bankRequest = $db->select_user(
            "SELECT
                bank_transfer_requests.*,
                currencies.symbol AS currency_symbol,
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

        $hasGeneratedPaymentRequest = app_order_has_generated_payment_request(
            $db,
            (int)($selected['id'] ?? 0),
            (int)$user['id']
        );
        $canCancelOrderFromSelection = orders_payment_can_cancel_order_from_selection(
            (array)$selected,
            $activeRequestMethod,
            $isSameOrderRenewalFlow,
            $hasGeneratedPaymentRequest
        );

        if (isset($_POST['cancel_order_from_payment_selection'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
            } elseif (!$canCancelOrderFromSelection) {
                $smarty->assign(
                    'alert_error',
                    localization_translate(
                        $t,
                        'orders_delete_blocked_payment_generated',
                        'You cannot remove this order because a payment request has already been generated for it.'
                    )
                );
            } else {
                app_delete_unpaid_order_payment_requests($db, (int)$selected['id'], (int)$user['id']);
                if (schema_object_exists($db, 'order_status_events')) {
                    app_delete_records_by_ids($db, 'order_status_events', 'order_id', [(int)$selected['id']]);
                }
                if (schema_object_exists($db, 'order_renewals')) {
                    app_delete_records_by_ids($db, 'order_renewals', 'order_id', [(int)$selected['id']]);
                }
                $db->delete_using_id('orders', (int)$selected['id']);

                if (!headers_sent()) {
                    header('Location: /orders?cancelled_order=1');
                    exit;
                }

                // orders_payment.tpl renders this notice once, also when headers were already sent.
                $smarty->assign('alert', localization_translate($t, 'orders_cancelled', 'Order removed.'));
                $smarty->assign('selected', null);
                return;
            }
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
            if ($packagePaymentUnavailable) {
                $paymentStateNotice = 'packages_unavailable';
            } elseif ($hasBlockingPendingRenewalState) {
                if ($pendingRenewalStatus === 'paid_pending_activation') {
                    $paymentStateNotice = 'renewal_approved_waiting_extension';
                } else {
                    $paymentStateNotice = 'renewal_pending_activation';
                }
            } elseif ($paymentStatusRaw === 'paid' && $statusRaw !== 'active' && !in_array($fulfillmentStatusRaw, ['delivered', 'fulfilled', 'completed'], true)) {
                $paymentStateNotice = 'paid_pending_activation';
            } elseif (!empty($_GET['renewal']) && $isSameOrderRenewalFlow && !$canOpenRenewalPayment) {
                $paymentStateNotice = 'renewal_not_ready';
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

        $smarty->assign('payment_summary_amount_display', app_format_money_value($selected['price'] ?? 0, (string)($selected['currency_symbol'] ?? ''), (string)($selected['currency_code'] ?? '')));
        $smarty->assign('selected', $selected);
        $smarty->assign('payment_can_request', $canRequestPayment);
        $smarty->assign('payment_selected_method', $selectedMethod);
        $smarty->assign('payment_active_request_method', $activeRequestMethod);
        $smarty->assign('payment_active_request_expires_at', $activeRequestExpiresAt);
        $smarty->assign('payment_active_request_remaining_seconds', $activeRequestRemainingSeconds);
        $smarty->assign('payment_can_cancel_order', $canCancelOrderFromSelection ? 1 : 0);
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
        $smarty->assign('payment_summary_amount_display', app_format_money_value($selected['price'] ?? 0, (string)($selected['currency_symbol'] ?? $reseller['currency_symbol'] ?? ''), (string)($selected['currency_code'] ?? $reseller['currency_short'] ?? '')));
        $smarty->assign('selected', $selected);
    }
}

} finally {
    if ($customerPaymentLock !== '') $db->select_user("SELECT RELEASE_LOCK('" . $db->escape($customerPaymentLock) . "') released");
}
