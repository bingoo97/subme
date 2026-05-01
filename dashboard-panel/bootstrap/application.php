<?php

if (!function_exists('app_runtime_timezone_name')) {
    function app_runtime_timezone_name() {
        static $timezone = null;

        if ($timezone !== null) {
            return $timezone;
        }

        $candidate = trim((string)getenv('APP_TIMEZONE'));
        if ($candidate === '') {
            $candidate = 'Europe/Warsaw';
        }

        try {
            new DateTimeZone($candidate);
            $timezone = $candidate;
        } catch (Throwable $exception) {
            $timezone = 'Europe/Warsaw';
        }

        return $timezone;
    }
}

if (!function_exists('app_bootstrap_runtime_timezone')) {
    function app_bootstrap_runtime_timezone() {
        static $bootstrapped = false;

        if ($bootstrapped) {
            return;
        }

        $bootstrapped = true;
        date_default_timezone_set(app_runtime_timezone_name());
    }
}

if (!function_exists('app_datetime_utc_from_unix_timestamp')) {
    function app_datetime_utc_from_unix_timestamp(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}

if (!function_exists('app_timestamp_from_utc_datetime')) {
    function app_timestamp_from_utc_datetime(?string $value): int
    {
        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            return 0;
        }

        return $date->getTimestamp();
    }
}

if (!function_exists('app_format_utc_datetime_local')) {
    function app_format_utc_datetime_local(?string $value, string $format = 'd.m.Y H:i'): string
    {
        $timestamp = app_timestamp_from_utc_datetime($value);
        if ($timestamp <= 0) {
            return '';
        }

        app_bootstrap_runtime_timezone();

        try {
            $timezone = new DateTimeZone(app_runtime_timezone_name());
        } catch (Throwable $exception) {
            $timezone = new DateTimeZone(date_default_timezone_get());
        }

        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone($timezone)
            ->format($format);
    }
}

app_bootstrap_runtime_timezone();

function app_format_crypto_rate($value): string
{
    if (!is_numeric($value)) {
        return '—';
    }

    $rate = (float)$value;
    if ($rate <= 0) {
        return '—';
    }

    $precision = abs($rate) < 1 ? 4 : 2;
    return number_format($rate, $precision, '.', '');
}

function app_format_money_value($amount, string $currencySymbol = '', string $currencyCode = ''): string
{
    $formattedAmount = number_format((float)$amount, 2, '.', '');
    $suffix = trim($currencySymbol) !== '' ? trim($currencySymbol) : trim($currencyCode);

    return $suffix !== '' ? trim($formattedAmount . ' ' . $suffix) : $formattedAmount;
}

function app_format_logo_path(string $path): string
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

function app_backend_root_path(): string
{
    $path = realpath(dirname(__DIR__));
    return $path !== false ? $path : dirname(__DIR__);
}

function app_public_root_path(): string
{
    static $resolvedRoot = null;
    if (is_string($resolvedRoot) && $resolvedRoot !== '') {
        return $resolvedRoot;
    }

    $backendRoot = app_backend_root_path();
    $candidates = [];

    $envOverride = trim((string)getenv('APP_PUBLIC_ROOT'));
    if ($envOverride !== '') {
        $candidates[] = $envOverride;
    }

    $pointerFile = $backendRoot . '/.public-root-path';
    if (is_file($pointerFile)) {
        $fileOverride = trim((string)file_get_contents($pointerFile));
        if ($fileOverride !== '') {
            $candidates[] = $fileOverride;
        }
    }

    $candidates[] = dirname($backendRoot) . '/public_html';

    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if ($resolved !== false && is_dir($resolved)) {
            $resolvedRoot = $resolved;
            return $resolvedRoot;
        }
    }

    $resolvedRoot = dirname($backendRoot) . '/public_html';
    return $resolvedRoot;
}

function app_public_path(string $relativePath = ''): string
{
    $relativePath = ltrim($relativePath, '/');
    $basePath = rtrim(app_public_root_path(), '/');

    if ($relativePath === '') {
        return $basePath;
    }

    return $basePath . '/' . $relativePath;
}

function app_chat_attachment_candidate_paths(string $attachmentPath): array
{
    $attachmentPath = trim($attachmentPath);
    if ($attachmentPath === '' || strpos($attachmentPath, '/uploads/chat/') !== 0) {
        return [];
    }

    $normalized = ltrim($attachmentPath, '/');
    $paths = [
        app_public_path($normalized),
        dirname(__DIR__, 2) . '/public_html/' . $normalized,
    ];

    $uniquePaths = [];
    foreach ($paths as $path) {
        $path = rtrim((string)$path);
        if ($path === '' || in_array($path, $uniquePaths, true)) {
            continue;
        }
        $uniquePaths[] = $path;
    }

    return $uniquePaths;
}

function app_chat_attachment_absolute_path(string $attachmentPath, bool $migrateLegacyToPublicRoot = false): string
{
    $candidatePaths = app_chat_attachment_candidate_paths($attachmentPath);
    if ($candidatePaths === []) {
        return '';
    }

    $activePath = $candidatePaths[0];
    foreach ($candidatePaths as $index => $candidatePath) {
        if (!is_file($candidatePath)) {
            continue;
        }

        if ($migrateLegacyToPublicRoot && $index > 0 && $candidatePath !== $activePath) {
            $activeDirectory = dirname($activePath);
            if ((!is_dir($activeDirectory) && @mkdir($activeDirectory, 0775, true)) || is_dir($activeDirectory)) {
                @copy($candidatePath, $activePath);
                if (is_file($activePath)) {
                    return $activePath;
                }
            }
        }

        return $candidatePath;
    }

    return $activePath;
}

function app_crypto_logo_by_code(string $assetCode, string $fallbackPath = ''): string
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

    return app_format_logo_path($fallbackPath);
}

function app_default_coingecko_id(string $assetCode): string
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

function app_http_json(string $url): ?array
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

function app_refresh_crypto_rates($db, string $vsCurrency = 'USD', int $cacheTtl = 900): array
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
            $coingeckoId = app_default_coingecko_id($code);
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
        $payload = app_http_json($apiUrl);

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
                $defaultCoingeckoId = app_default_coingecko_id((string)($row['code'] ?? ''));
                if ($defaultCoingeckoId !== '') {
                    $db->update_using_id(['coingecko_id'], [$defaultCoingeckoId], 'crypto_assets', $assetId);
                }
            }
        }
    }

    return $rows;
}

function app_crypto_network_label(string $networkCode): string
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

function app_sync_crypto_wallet_address_statuses($db, int $walletId = 0): void
{
    if (
        !schema_object_exists($db, 'crypto_wallet_addresses')
        || !schema_object_exists($db, 'crypto_wallet_assignments')
    ) {
        return;
    }

    $walletFilter = $walletId > 0 ? " AND wallet.id = {$walletId}" : '';

    $db->query(
        "UPDATE crypto_wallet_addresses AS wallet
         LEFT JOIN (
            SELECT wallet_address_id, COUNT(*) AS active_total
            FROM crypto_wallet_assignments
            WHERE status IN ('reserved', 'active')
            GROUP BY wallet_address_id
         ) AS active_assignments
           ON active_assignments.wallet_address_id = wallet.id
         SET wallet.status = CASE
            WHEN COALESCE(active_assignments.active_total, 0) > 0 THEN 'assigned'
            ELSE 'available'
         END
         WHERE wallet.disabled_at IS NULL{$walletFilter}"
    );
}

function app_find_available_crypto_wallet_for_asset($db, int $assetId, int $customerId = 0, array $settings = []): ?array
{
    if ($assetId <= 0 || !schema_object_exists($db, 'crypto_wallet_addresses')) {
        return null;
    }

    app_release_stale_auto_crypto_wallet_assignments($db);
    app_sync_crypto_wallet_address_statuses($db);

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

function app_assign_customer_crypto_wallet(
    $db,
    int $walletId,
    int $customerId,
    array $settings = [],
    int $orderId = 0,
    string $assignmentNote = 'Assigned from customer payment wizard',
    string $assignmentReason = 'deposit',
    string $assignmentStatus = 'active'
): int
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
                app_release_crypto_wallet_assignment_if_unused($db, (int)$assignment['id'], 'Moved automatically');
            }
        }
    }

    $assignmentReason = trim($assignmentReason) !== '' ? trim($assignmentReason) : 'deposit';
    $assignmentStatus = strtolower(trim($assignmentStatus));
    if ($assignmentStatus !== 'reserved') {
        $assignmentStatus = 'active';
    }

    $inserted = $db->insert(
        ['wallet_address_id', 'customer_id', 'order_id', 'assignment_reason', 'status', 'assigned_by_admin_user_id', 'assignment_note'],
        [$walletId, $customerId, $orderId > 0 ? $orderId : null, $assignmentReason, $assignmentStatus, null, $assignmentNote],
        'crypto_wallet_assignments'
    );
    if (!$inserted || (int)$db->id() <= 0) {
        return 0;
    }
    $assignmentId = (int)$db->id();

    $db->update_using_id(
        ['status', 'last_assigned_at', 'disabled_at'],
        ['assigned', $db->expr('NOW()'), null],
        'crypto_wallet_addresses',
        $walletId
    );

    return $assignmentId;
}

function app_release_crypto_wallet_assignment_if_unused($db, int $assignmentId, string $note = 'Released automatically'): bool
{
    if (
        $assignmentId <= 0
        || !schema_object_exists($db, 'crypto_wallet_assignments')
        || !schema_object_exists($db, 'crypto_wallet_addresses')
    ) {
        return false;
    }

    $assignment = $db->select_user(
        "SELECT id, wallet_address_id, customer_id, status
         FROM crypto_wallet_assignments
         WHERE id = {$assignmentId}
         LIMIT 1"
    );
    if (!is_array($assignment) || empty($assignment['id']) || empty($assignment['wallet_address_id'])) {
        return false;
    }

    $openRequest = schema_object_exists($db, 'crypto_deposit_requests')
        ? $db->select_user(
            "SELECT id
             FROM crypto_deposit_requests
             WHERE wallet_assignment_id = {$assignmentId}
               AND status IN ('pending', 'awaiting_confirmation', 'awaiting_review')
             ORDER BY id DESC
             LIMIT 1"
        )
        : null;
    if (is_array($openRequest) && !empty($openRequest['id'])) {
        return false;
    }

    $walletAddressId = (int)($assignment['wallet_address_id'] ?? 0);
    $customerId = (int)($assignment['customer_id'] ?? 0);
    if ($walletAddressId > 0 && $customerId > 0 && schema_object_exists($db, 'crypto_deposit_requests')) {
        $paidRequest = $db->select_user(
            "SELECT crypto_deposit_requests.id
             FROM crypto_deposit_requests
             LEFT JOIN crypto_wallet_assignments AS payment_assignment
               ON payment_assignment.id = crypto_deposit_requests.wallet_assignment_id
             WHERE crypto_deposit_requests.customer_id = {$customerId}
               AND (
                    crypto_deposit_requests.wallet_assignment_id = {$assignmentId}
                 OR crypto_deposit_requests.wallet_address_id = {$walletAddressId}
                 OR payment_assignment.wallet_address_id = {$walletAddressId}
               )
               AND crypto_deposit_requests.status NOT IN ('pending', 'pending_payment', 'cancelled')
             ORDER BY crypto_deposit_requests.id DESC
             LIMIT 1"
        );
        if (is_array($paidRequest) && !empty($paidRequest['id'])) {
            return false;
        }
    }

    $safeNote = $db->escape($note);
    $released = $db->query(
        "UPDATE crypto_wallet_assignments
         SET status = 'released',
             released_at = NOW(),
             assignment_note = CONCAT(COALESCE(assignment_note, ''), '\n{$safeNote}')
         WHERE id = {$assignmentId}
           AND status IN ('reserved', 'active')"
    );
    if (!$released) {
        return false;
    }

    app_sync_crypto_wallet_address_statuses($db, (int)$assignment['wallet_address_id']);

    return true;
}

function app_cancel_crypto_deposit_requests(
    Mysql_ks $db,
    string $whereSql,
    string $releaseNote = 'Released after crypto payment request cancellation',
    ?string $cancelledAt = null
): int {
    $whereSql = trim($whereSql);
    if (
        $whereSql === ''
        || !schema_object_exists($db, 'crypto_deposit_requests')
        || !schema_object_exists($db, 'crypto_wallet_assignments')
    ) {
        return 0;
    }

    $safeNow = trim((string)$cancelledAt);
    if ($safeNow === '') {
        $safeNow = date('Y-m-d H:i:s');
    }
    $safeNowSql = $db->escape($safeNow);

    $rows = $db->select_full_user(
        "SELECT id, wallet_assignment_id
         FROM crypto_deposit_requests
         WHERE {$whereSql}"
    );
    if (!$rows) {
        return 0;
    }

    $requestIds = [];
    foreach ($rows as $row) {
        $requestId = (int)($row['id'] ?? 0);
        if ($requestId > 0) {
            $requestIds[$requestId] = $requestId;
        }
    }

    if (!$requestIds) {
        return 0;
    }

    $requestIdList = implode(',', $requestIds);
    $updated = $db->query(
        "UPDATE crypto_deposit_requests
         SET status = 'cancelled',
             cancelled_at = CASE
                 WHEN cancelled_at IS NULL THEN '{$safeNowSql}'
                 ELSE cancelled_at
             END
         WHERE id IN ({$requestIdList})"
    );
    if (!$updated) {
        return 0;
    }

    foreach ($rows as $row) {
        $assignmentId = (int)($row['wallet_assignment_id'] ?? 0);
        if ($assignmentId > 0) {
            app_release_crypto_wallet_assignment_if_unused($db, $assignmentId, $releaseNote);
        }
    }

    return count($requestIds);
}

function app_release_expired_crypto_wallet_holds($db, ?string $now = null): int
{
    if (!schema_object_exists($db, 'crypto_wallet_assignments')) {
        return 0;
    }

    $safeNow = trim((string)$now);
    if ($safeNow === '') {
        $safeNow = date('Y-m-d H:i:s');
    }
    $safeNowSql = $db->escape($safeNow);

    $rows = $db->select_full_user(
        "SELECT crypto_wallet_assignments.id
         FROM crypto_wallet_assignments
         LEFT JOIN crypto_deposit_requests AS open_request
           ON open_request.wallet_assignment_id = crypto_wallet_assignments.id
          AND open_request.status IN ('pending', 'awaiting_confirmation', 'awaiting_review')
         WHERE crypto_wallet_assignments.status = 'reserved'
           AND crypto_wallet_assignments.assignment_reason IN ('topup_hold', 'order_payment_hold')
           AND crypto_wallet_assignments.assigned_at <= DATE_SUB('{$safeNowSql}', INTERVAL 1 HOUR)
           AND open_request.id IS NULL
         ORDER BY crypto_wallet_assignments.id ASC"
    );

    $releasedTotal = 0;
    foreach ($rows as $row) {
        if (app_release_crypto_wallet_assignment_if_unused($db, (int)($row['id'] ?? 0), 'Released after expired customer payment hold')) {
            $releasedTotal++;
        }
    }

    return $releasedTotal;
}

function app_release_stale_auto_crypto_wallet_assignments($db, ?string $now = null): int
{
    if (
        !schema_object_exists($db, 'crypto_wallet_assignments')
        || !schema_object_exists($db, 'crypto_wallet_addresses')
    ) {
        return 0;
    }

    $safeNow = trim((string)$now);
    if ($safeNow === '') {
        $safeNow = date('Y-m-d H:i:s');
    }
    $safeNowSql = $db->escape($safeNow);

    $rows = $db->select_full_user(
        "SELECT crypto_wallet_assignments.id
         FROM crypto_wallet_assignments
         LEFT JOIN crypto_deposit_requests AS open_request
           ON open_request.wallet_assignment_id = crypto_wallet_assignments.id
          AND open_request.status IN ('pending', 'awaiting_confirmation', 'awaiting_review')
         WHERE crypto_wallet_assignments.status = 'active'
           AND crypto_wallet_assignments.assigned_by_admin_user_id IS NULL
           AND crypto_wallet_assignments.assigned_at <= DATE_SUB('{$safeNowSql}', INTERVAL 1 HOUR)
           AND open_request.id IS NULL
           AND (
                crypto_wallet_assignments.assignment_note LIKE 'Assigned from balance top-up wizard%'
                OR crypto_wallet_assignments.assignment_note LIKE 'Assigned from customer payment wizard%'
           )
         ORDER BY crypto_wallet_assignments.id ASC"
    );

    $releasedTotal = 0;
    foreach ($rows as $row) {
        if (app_release_crypto_wallet_assignment_if_unused($db, (int)($row['id'] ?? 0), 'Released after stale auto payment assignment')) {
            $releasedTotal++;
        }
    }

    return $releasedTotal;
}

function app_create_crypto_deposit_request($db, array $payload): int
{
    if (!schema_object_exists($db, 'crypto_deposit_requests')) {
        return 0;
    }

    $fields = [
        'customer_id',
        'order_id',
        'crypto_asset_id',
        'wallet_address_id',
        'wallet_assignment_id',
        'requested_fiat_amount',
        'fiat_currency_id',
        'exchange_rate',
        'requested_crypto_amount',
    ];
    $values = [
        (int)($payload['customer_id'] ?? 0),
        !empty($payload['order_id']) ? (int)$payload['order_id'] : null,
        (int)($payload['crypto_asset_id'] ?? 0),
        (int)($payload['wallet_address_id'] ?? 0),
        !empty($payload['wallet_assignment_id']) ? (int)$payload['wallet_assignment_id'] : null,
        (float)($payload['requested_fiat_amount'] ?? 0),
        (int)($payload['fiat_currency_id'] ?? 0),
        (float)($payload['exchange_rate'] ?? 0),
        (float)($payload['requested_crypto_amount'] ?? 0),
    ];

    if (
        (int)$values[0] <= 0
        || (int)$values[2] <= 0
        || (int)$values[3] <= 0
        || (int)$values[6] <= 0
        || (float)$values[5] <= 0
        || (float)$values[7] <= 0
        || (float)$values[8] <= 0
    ) {
        return 0;
    }

    $optionalColumns = [
        'assignment_mode',
        'status',
        'created_by_admin_user_id',
        'requested_at',
        'expires_at',
        'confirmed_at',
        'cancelled_at',
        'legacy_source_table',
        'legacy_source_id',
        'request_note',
    ];

    foreach ($optionalColumns as $columnName) {
        if (!array_key_exists($columnName, $payload) || !schema_column_exists($db, 'crypto_deposit_requests', $columnName)) {
            continue;
        }

        $fields[] = $columnName;
        $value = $payload[$columnName];
        if (in_array($columnName, ['created_by_admin_user_id', 'legacy_source_id'], true)) {
            $value = $value !== null && $value !== '' ? (int)$value : null;
        }
        $values[] = $value;
    }

    $inserted = $db->insert($fields, $values, 'crypto_deposit_requests');
    if (!$inserted || (int)$db->id() <= 0) {
        return 0;
    }

    return (int)$db->id();
}

function app_effective_currency_id(array $settings, ...$candidates): int
{
    foreach ($candidates as $candidate) {
        $value = (int)$candidate;
        if ($value > 0) {
            return $value;
        }
    }

    $fallbacks = [
        $settings['default_currency_id'] ?? 0,
        $settings['currency'] ?? 0,
    ];

    foreach ($fallbacks as $fallback) {
        $value = (int)$fallback;
        if ($value > 0) {
            return $value;
        }
    }

    return 0;
}

function app_load_customer_bank_accounts($db, int $customerId, string $currencyCode = '', array $settings = []): array
{
    $currencyCode = strtoupper(trim($currencyCode));
    $sharedEnabled = !empty($settings['bank_account_shared_assignments_enabled']);
    $hasCustomerBankView = schema_object_exists($db, 'customer_bank_accounts');

    if (
        $customerId <= 0
        || (
            !$hasCustomerBankView
            && (
                !schema_object_exists($db, 'bank_account_assignments')
                || !schema_object_exists($db, 'bank_accounts')
            )
        )
    ) {
        return [];
    }

    if ($hasCustomerBankView) {
        $assignedBankAccounts = $db->select_full_user(
            "SELECT
                bank_account_assignment_id,
                bank_account_id,
                customer_id,
                customer_email,
                currency_code,
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
                transfer_instructions,
                status,
                assigned_at
             FROM customer_bank_accounts
             WHERE customer_id = {$customerId}
               AND status IN ('reserved', 'active')
             ORDER BY assigned_at DESC, bank_account_assignment_id DESC"
        );
    } else {
        $assignedBankAccounts = $db->select_full_user(
            "SELECT
                assignment.id AS bank_account_assignment_id,
                assignment.bank_account_id,
                assignment.customer_id,
                currency.code AS currency_code,
                account.label,
                account.account_holder_name,
                account.bank_name,
                account.bank_address,
                account.country_code,
                account.iban,
                account.account_number,
                account.routing_number,
                account.swift_bic,
                account.payment_reference_template,
                account.transfer_instructions,
                assignment.status,
                assignment.assigned_at
             FROM bank_account_assignments AS assignment
             INNER JOIN bank_accounts AS account
               ON account.id = assignment.bank_account_id
             LEFT JOIN currencies AS currency
               ON currency.id = account.currency_id
             WHERE assignment.customer_id = {$customerId}
               AND assignment.status IN ('reserved', 'active')
             ORDER BY assignment.assigned_at DESC, assignment.id DESC"
        );
    }

    if (!is_array($assignedBankAccounts)) {
        $assignedBankAccounts = [];
    }

    if ($assignedBankAccounts) {
        return $assignedBankAccounts;
    }

    $currencyFilter = $currencyCode !== ''
        ? " AND currency.code = '" . $db->escape($currencyCode) . "'"
        : '';

    $baseQuery = $sharedEnabled
        ? "SELECT
                account.id AS bank_account_id,
                currency.code AS currency_code,
                account.label,
                account.account_holder_name,
                account.bank_name,
                account.bank_address,
                account.country_code,
                account.iban,
                account.account_number,
                account.routing_number,
                account.swift_bic,
                account.payment_reference_template,
                account.transfer_instructions
             FROM bank_accounts AS account
             LEFT JOIN currencies AS currency
               ON currency.id = account.currency_id
             LEFT JOIN bank_transfer_requests AS open_request
               ON open_request.bank_account_id = account.id
              AND open_request.status IN ('pending_payment', 'awaiting_review')
             WHERE account.disabled_at IS NULL
               AND account.status IN ('available', 'assigned')
               AND open_request.id IS NULL"
        : "SELECT
                account.id AS bank_account_id,
                currency.code AS currency_code,
                account.label,
                account.account_holder_name,
                account.bank_name,
                account.bank_address,
                account.country_code,
                account.iban,
                account.account_number,
                account.routing_number,
                account.swift_bic,
                account.payment_reference_template,
                account.transfer_instructions
             FROM bank_accounts AS account
             LEFT JOIN currencies AS currency
               ON currency.id = account.currency_id
             LEFT JOIN bank_account_assignments AS assignment
               ON assignment.bank_account_id = account.id
              AND assignment.status IN ('reserved', 'active')
             LEFT JOIN bank_transfer_requests AS open_request
               ON open_request.bank_account_id = account.id
              AND open_request.status IN ('pending_payment', 'awaiting_review')
             WHERE account.disabled_at IS NULL
               AND account.status = 'available'
               AND assignment.id IS NULL
               AND open_request.id IS NULL";

    $availableAccounts = $db->select_full_user(
        $baseQuery . $currencyFilter . "
         ORDER BY account.label ASC, account.bank_name ASC, account.id ASC"
    );

    if ((!is_array($availableAccounts) || !$availableAccounts) && $currencyCode !== '') {
        $availableAccounts = $db->select_full_user(
            $baseQuery . "
             ORDER BY account.label ASC, account.bank_name ASC, account.id ASC"
        );
    }

    if (!is_array($availableAccounts)) {
        $availableAccounts = [];
    }

    $rows = [];
    foreach ($availableAccounts as $availableAccount) {
        $rows[] = [
            'bank_account_assignment_id' => 0 - (int)($availableAccount['bank_account_id'] ?? 0),
            'bank_account_id' => (int)($availableAccount['bank_account_id'] ?? 0),
            'customer_id' => $customerId,
            'customer_email' => '',
            'currency_code' => (string)($availableAccount['currency_code'] ?? ''),
            'label' => (string)($availableAccount['label'] ?? ''),
            'account_holder_name' => (string)($availableAccount['account_holder_name'] ?? ''),
            'bank_name' => (string)($availableAccount['bank_name'] ?? ''),
            'bank_address' => (string)($availableAccount['bank_address'] ?? ''),
            'country_code' => (string)($availableAccount['country_code'] ?? ''),
            'iban' => (string)($availableAccount['iban'] ?? ''),
            'account_number' => (string)($availableAccount['account_number'] ?? ''),
            'routing_number' => (string)($availableAccount['routing_number'] ?? ''),
            'swift_bic' => (string)($availableAccount['swift_bic'] ?? ''),
            'payment_reference_template' => (string)($availableAccount['payment_reference_template'] ?? ''),
            'transfer_instructions' => (string)($availableAccount['transfer_instructions'] ?? ''),
            'status' => 'available',
            'assigned_at' => '',
        ];
    }

    return $rows;
}

function app_load_customer_crypto_assets($db, int $customerId, string $vsCurrency = 'USD', array $settings = []): array
{
    $hasCustomerWalletView = schema_object_exists($db, 'customer_crypto_wallets');
    if (
        $customerId <= 0
        || (
            !$hasCustomerWalletView
            && (
                !schema_object_exists($db, 'crypto_wallet_assignments')
                || !schema_object_exists($db, 'crypto_wallet_addresses')
                || !schema_object_exists($db, 'crypto_assets')
            )
        )
    ) {
        return [];
    }

    app_release_stale_auto_crypto_wallet_assignments($db);
    app_sync_crypto_wallet_address_statuses($db);

    $activeAssets = app_refresh_crypto_rates($db, $vsCurrency);
    if ($hasCustomerWalletView) {
        $assignedCryptoWallets = $db->select_full_user(
            "SELECT *
             FROM customer_crypto_wallets
             WHERE customer_id = {$customerId}
               AND status = 'active'
             ORDER BY assigned_at DESC"
        );
    } else {
        $assignedCryptoWallets = $db->select_full_user(
            "SELECT
                assignment.id AS wallet_assignment_id,
                assignment.customer_id,
                asset.code AS crypto_asset_code,
                asset.name AS crypto_asset_name,
                wallet.id AS wallet_address_id,
                wallet.label,
                wallet.owner_full_name,
                wallet.address,
                wallet.network_code,
                wallet.memo_tag,
                wallet.wallet_provider,
                assignment.assignment_reason,
                assignment.status,
                assignment.assigned_at,
                assignment.released_at,
                assignment.assignment_note
             FROM crypto_wallet_assignments AS assignment
             INNER JOIN crypto_wallet_addresses AS wallet
               ON wallet.id = assignment.wallet_address_id
             INNER JOIN crypto_assets AS asset
               ON asset.id = wallet.crypto_asset_id
             WHERE assignment.customer_id = {$customerId}
               AND assignment.status = 'active'
             ORDER BY assignment.assigned_at DESC"
        );
    }
    if (!is_array($assignedCryptoWallets)) {
        $assignedCryptoWallets = [];
    }

    $activeAssetsByCode = [];
    foreach ($activeAssets as $asset) {
        $activeAssetsByCode[(string)($asset['code'] ?? '')] = $asset;
    }

    foreach ($assignedCryptoWallets as &$wallet) {
        if (!empty($wallet['network_code'])) {
            continue;
        }

        $assetCode = strtoupper(trim((string)($wallet['crypto_asset_code'] ?? '')));
        $walletAddress = trim((string)($wallet['address'] ?? ''));
        $inferredNetworkCode = '';

        if ($assetCode === 'BTC') {
            $inferredNetworkCode = 'bitcoin';
        } elseif ($assetCode === 'BCH') {
            $inferredNetworkCode = 'bitcoin-cash';
        } elseif ($assetCode === 'LTC') {
            $inferredNetworkCode = 'litecoin';
        } elseif ($assetCode === 'DOGE') {
            $inferredNetworkCode = 'dogecoin';
        } elseif ($assetCode === 'ETH') {
            $inferredNetworkCode = 'ethereum';
        } elseif ($assetCode === 'BNB') {
            $inferredNetworkCode = 'bnb';
        } elseif ($assetCode === 'CRO') {
            $inferredNetworkCode = 'cronos';
        } elseif ($assetCode === 'SOL') {
            $inferredNetworkCode = 'solana';
        } elseif ($assetCode === 'MATIC') {
            $inferredNetworkCode = 'polygon';
        } elseif ($assetCode === 'XRP') {
            $inferredNetworkCode = 'ripple';
        } elseif ($assetCode === 'USDT' || $assetCode === 'USDC') {
            if (strpos($walletAddress, 'T') === 0) {
                $inferredNetworkCode = 'tron';
            } elseif (stripos($walletAddress, '0x') === 0) {
                $inferredNetworkCode = 'ethereum';
            } elseif (stripos($walletAddress, 'cro') === 0) {
                $inferredNetworkCode = 'cronos';
            } elseif ($walletAddress !== '' && preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,}$/', $walletAddress)) {
                $inferredNetworkCode = 'solana';
            } else {
                $inferredNetworkCode = 'ethereum';
            }
        }

        if ($inferredNetworkCode !== '' && !empty($wallet['wallet_address_id'])) {
            $db->update_using_id(['network_code'], [$inferredNetworkCode], 'crypto_wallet_addresses', (int)$wallet['wallet_address_id']);
            $wallet['network_code'] = $inferredNetworkCode;
        }
    }
    unset($wallet);

    $cryptoAssets = [];
    foreach ($assignedCryptoWallets as $assignedWallet) {
        $assetCode = (string)($assignedWallet['crypto_asset_code'] ?? '');
        $asset = $activeAssetsByCode[$assetCode] ?? null;
        if (!is_array($asset)) {
            continue;
        }

        $networkCode = (string)($assignedWallet['network_code'] ?? '');
        $cryptoAssets[] = [
            'id' => (int)$assignedWallet['wallet_assignment_id'],
            'crypto_asset_id' => (int)$asset['id'],
            'code' => $assetCode,
            'name' => (string)$asset['name'],
            'network_code' => $networkCode,
            'network_label' => app_crypto_network_label($networkCode),
            'logo_path' => app_crypto_logo_by_code($assetCode, (string)($asset['logo_url'] ?? '')),
            'rate' => isset($asset['current_rate_fiat']) ? (float)$asset['current_rate_fiat'] : 0.0,
            'is_assigned' => true,
            'wallet_assignment_id' => (int)$assignedWallet['wallet_assignment_id'],
            'wallet_address_id' => (int)$assignedWallet['wallet_address_id'],
            'wallet_address' => (string)$assignedWallet['address'],
            'wallet_memo_tag' => (string)($assignedWallet['memo_tag'] ?? ''),
            'wallet_label' => (string)($assignedWallet['label'] ?? ''),
            'wallet_owner_full_name' => (string)($assignedWallet['owner_full_name'] ?? ''),
            'wallet_provider' => (string)($assignedWallet['wallet_provider'] ?? ''),
        ];
    }

    $existingWalletIds = [];
    $existingAssetIds = [];
    foreach ($cryptoAssets as $cryptoAsset) {
        $existingWalletIds[(int)($cryptoAsset['wallet_address_id'] ?? 0)] = true;
        $existingAssetIds[(int)($cryptoAsset['crypto_asset_id'] ?? 0)] = true;
    }

    foreach ($activeAssets as $asset) {
        $assetCode = strtoupper((string)($asset['code'] ?? ''));
        $cryptoAssetId = (int)($asset['id'] ?? 0);
        if ($assetCode === '' || $cryptoAssetId <= 0 || !empty($existingAssetIds[$cryptoAssetId])) {
            continue;
        }

        $availableWallet = app_find_available_crypto_wallet_for_asset(
            $db,
            $cryptoAssetId,
            $customerId,
            $settings
        );
        $availableWalletId = (int)($availableWallet['wallet_address_id'] ?? 0);
        if (!is_array($availableWallet) || $availableWalletId <= 0 || isset($existingWalletIds[$availableWalletId])) {
            continue;
        }

        $networkCode = (string)($availableWallet['network_code'] ?? '');
        $cryptoAssets[] = [
            'id' => 0 - $availableWalletId,
            'crypto_asset_id' => $cryptoAssetId,
            'code' => $assetCode,
            'name' => (string)($asset['name'] ?? $assetCode),
            'network_code' => $networkCode,
            'network_label' => app_crypto_network_label($networkCode),
            'logo_path' => app_crypto_logo_by_code($assetCode, (string)($asset['logo_url'] ?? '')),
            'rate' => isset($asset['current_rate_fiat']) ? (float)$asset['current_rate_fiat'] : 0.0,
            'is_assigned' => false,
            'wallet_assignment_id' => 0,
            'wallet_address_id' => $availableWalletId,
            'wallet_address' => (string)($availableWallet['address'] ?? ''),
            'wallet_memo_tag' => (string)($availableWallet['memo_tag'] ?? ''),
            'wallet_label' => (string)($availableWallet['label'] ?? ''),
            'wallet_owner_full_name' => (string)($availableWallet['owner_full_name'] ?? ''),
            'wallet_provider' => (string)($availableWallet['wallet_provider'] ?? ''),
        ];
        $existingWalletIds[$availableWalletId] = true;
        $existingAssetIds[$cryptoAssetId] = true;
    }

    return $cryptoAssets;
}

function app_chat_card_prefix(): string
{
    return '[[APP_CHAT_CARD]]';
}

function app_chat_card_encode(array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || $json === '') {
        return '';
    }

    return app_chat_card_prefix() . base64_encode($json);
}

function app_chat_card_decode(string $messageBody): ?array
{
    $prefix = app_chat_card_prefix();
    if (strpos($messageBody, $prefix) !== 0) {
        return null;
    }

    $encoded = substr($messageBody, strlen($prefix));
    if ($encoded === '') {
        return null;
    }

    $json = base64_decode($encoded, true);
    if (!is_string($json) || $json === '') {
        return null;
    }

    $payload = json_decode($json, true);
    return is_array($payload) ? $payload : null;
}

function app_build_crypto_payment_chat_card_message(array $input, string $localeCode = 'en'): string
{
    $localeCode = app_normalize_email_locale($localeCode);
    $assetName = trim((string)($input['asset_name'] ?? $input['crypto_name'] ?? $input['asset_code'] ?? 'Crypto'));
    $assetCode = strtoupper(trim((string)($input['asset_code'] ?? $input['crypto_code'] ?? '')));
    $logoUrl = app_crypto_logo_by_code($assetCode, (string)($input['asset_logo_url'] ?? $input['logo_url'] ?? ''));
    $cryptoAmountRaw = trim((string)($input['requested_crypto_amount'] ?? ''));
    $cryptoAmount = is_numeric($cryptoAmountRaw) ? sprintf('%.8f', (float)$cryptoAmountRaw) : $cryptoAmountRaw;
    $fiatAmount = app_format_money_value(
        $input['requested_fiat_amount'] ?? 0,
        (string)($input['currency_symbol'] ?? ''),
        (string)($input['currency_code'] ?? '')
    );
    $walletAddress = trim((string)($input['wallet_address'] ?? ''));
    $walletOwner = trim((string)($input['wallet_owner_full_name'] ?? ''));
    $paymentUrl = trim((string)($input['payment_url'] ?? ''));

    $payload = [
        'kind' => 'payment_request',
        'payment_kind' => 'crypto',
        'locale_code' => $localeCode,
        'title' => $localeCode === 'pl' ? ('Zapłać przez ' . $assetName) : ('Pay with ' . $assetName),
        'logo_url' => $logoUrl,
        'button_url' => $paymentUrl,
        'button_label' => $localeCode === 'pl' ? 'Zapłać kryptowalutą' : 'Open crypto payment',
        'button_hint' => $localeCode === 'pl' ? '** kliknij aby przejść do płatności...' : '** click to open payment...',
        'step_text' => $localeCode === 'pl' ? 'Wybierz w Revolut' : 'Choose in Revolut',
        'step_arrow_text' => $localeCode === 'pl' ? 'Wyślij' : 'Send',
        'badges' => $localeCode === 'pl' ? ['Portfel innej osoby', 'TrustWallet'] : ['External wallet', 'TrustWallet'],
        'fields' => [],
        'note' => $localeCode === 'pl'
            ? 'W przypadku płatności przez Revolut lub z giełdy kryptowalutowej należy podać imię i nazwisko osoby do której jest wykonywany przelew. Do wyliczonej przez nas kwoty pamiętaj aby dodać kwotę prowizji, wyliczona kwota płatności musi w całości trafić na adres naszego portfela.'
            : 'If you pay from Revolut or a crypto exchange, provide the recipient full name exactly as shown. Add exchange or wallet fees on top so the full token amount reaches our wallet.',
    ];

    if ($walletOwner !== '') {
        $payload['fields'][] = [
            'label' => $localeCode === 'pl' ? 'Imię i nazwisko' : 'Full name',
            'value' => $walletOwner,
            'tone' => 'code',
        ];
    }

    $payload['fields'][] = [
        'label' => $localeCode === 'pl' ? 'Wartość transakcji' : 'Transaction value',
        'value' => $fiatAmount,
        'tone' => 'muted',
    ];

    $payload['fields'][] = [
        'label' => $localeCode === 'pl' ? 'Kwota do wysłania' : 'Amount to send',
        'value' => trim($cryptoAmount . ' ' . $assetCode),
        'tone' => 'code',
    ];

    $payload['fields'][] = [
        'label' => $localeCode === 'pl' ? 'Adres portfela' : 'Wallet address',
        'value' => $walletAddress !== '' ? $walletAddress : '-',
        'tone' => 'code',
    ];

    return app_chat_card_encode($payload);
}

function app_build_bank_payment_chat_card_message(array $input, string $localeCode = 'en'): string
{
    $localeCode = app_normalize_email_locale($localeCode);
    $amount = app_format_money_value(
        $input['requested_amount'] ?? 0,
        (string)($input['currency_symbol'] ?? ''),
        (string)($input['currency_code'] ?? '')
    );
    $bankName = trim((string)($input['bank_name'] ?? ''));
    $holder = trim((string)($input['account_holder_name'] ?? ''));
    $iban = trim((string)($input['iban'] ?? ''));
    $swift = trim((string)($input['swift_bic'] ?? ''));
    $reference = trim((string)($input['payment_reference'] ?? ''));
    $instructions = trim((string)($input['transfer_instructions'] ?? ''));

    $payload = [
        'kind' => 'payment_request',
        'payment_kind' => 'bank',
        'locale_code' => $localeCode,
        'title' => $localeCode === 'pl' ? 'Zapłać przelewem bankowym' : 'Pay by bank transfer',
        'logo_url' => '',
        'button_url' => '',
        'button_label' => '',
        'button_hint' => '',
        'fields' => [
            [
                'label' => $localeCode === 'pl' ? 'Kwota do przelewu' : 'Transfer amount',
                'value' => $amount,
                'tone' => 'danger',
            ],
        ],
        'note' => $instructions !== ''
            ? $instructions
            : ($localeCode === 'pl'
                ? 'Po wykonaniu przelewu zachowaj potwierdzenie i poczekaj na weryfikację po naszej stronie.'
                : 'Keep your transfer confirmation and wait for verification on our side.'),
    ];

    if ($holder !== '') {
        $payload['fields'][] = [
            'label' => $localeCode === 'pl' ? 'Odbiorca' : 'Beneficiary',
            'value' => $holder,
            'tone' => 'muted',
        ];
    }
    if ($bankName !== '') {
        $payload['fields'][] = [
            'label' => $localeCode === 'pl' ? 'Bank' : 'Bank',
            'value' => $bankName,
            'tone' => 'muted',
        ];
    }
    if ($iban !== '') {
        $payload['fields'][] = [
            'label' => 'IBAN',
            'value' => $iban,
            'tone' => 'code',
        ];
    }
    if ($swift !== '') {
        $payload['fields'][] = [
            'label' => 'SWIFT / BIC',
            'value' => $swift,
            'tone' => 'code',
        ];
    }
    if ($reference !== '') {
        $payload['fields'][] = [
            'label' => $localeCode === 'pl' ? 'Tytuł przelewu' : 'Payment reference',
            'value' => $reference,
            'tone' => 'code',
        ];
    }

    return app_chat_card_encode($payload);
}

function app_ensure_product_provider_runtime_columns(Mysql_ks $db): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;

    if (!schema_object_exists($db, 'product_providers')) {
        return;
    }

    if (!schema_column_exists($db, 'product_providers', 'logo_url')) {
        @$db->query(
            "ALTER TABLE product_providers
             ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL
             AFTER description"
        );
        schema_forget_column_cache('product_providers', 'logo_url');
    }

    if (schema_column_exists($db, 'product_providers', 'logo_url') && schema_column_exists($db, 'product_providers', 'icon_url')) {
        @$db->query(
            "UPDATE product_providers
             SET logo_url = NULLIF(TRIM(icon_url), '')
             WHERE (logo_url IS NULL OR TRIM(logo_url) = '')
               AND icon_url IS NOT NULL
               AND TRIM(icon_url) != ''"
        );
    }

    if (!schema_column_exists($db, 'product_providers', 'url_replacement_from')) {
        @$db->query(
            "ALTER TABLE product_providers
             ADD COLUMN url_replacement_from VARCHAR(255) DEFAULT NULL
             AFTER supports_url_replacement"
        );
        schema_forget_column_cache('product_providers', 'url_replacement_from');
    }

    if (!schema_column_exists($db, 'product_providers', 'url_replacement_to')) {
        @$db->query(
            "ALTER TABLE product_providers
             ADD COLUMN url_replacement_to VARCHAR(255) DEFAULT NULL
             AFTER url_replacement_from"
        );
        schema_forget_column_cache('product_providers', 'url_replacement_to');
    }

    if (!schema_column_exists($db, 'product_providers', 'supports_delivery_links')) {
        @$db->query(
            "ALTER TABLE product_providers
             ADD COLUMN supports_delivery_links TINYINT(1) NOT NULL DEFAULT 1
             AFTER supports_manual_delivery"
        );
        schema_forget_column_cache('product_providers', 'supports_delivery_links');
    }
}

function app_ensure_news_runtime_columns(Mysql_ks $db): void
{
    static $done = false;

    if ($done || !schema_object_exists($db, 'news_posts')) {
        return;
    }

    $done = true;

    if (!schema_column_exists($db, 'news_posts', 'created_by_admin_user_id')) {
        @$db->query(
            "ALTER TABLE news_posts
             ADD COLUMN created_by_admin_user_id INT UNSIGNED DEFAULT NULL
             AFTER legacy_source_id"
        );
        schema_forget_column_cache('news_posts', 'created_by_admin_user_id');
    }
}

function app_admin_display_name_sql(Mysql_ks $db, string $alias = 'admin_users'): string
{
    $alias = preg_replace('/[^a-zA-Z0-9_]+/', '', $alias) ?: 'admin_users';

    if (schema_object_exists($db, 'admin_users') && schema_column_exists($db, 'admin_users', 'public_handle')) {
        return "COALESCE(NULLIF(TRIM({$alias}.public_handle), ''), NULLIF(TRIM({$alias}.login_name), ''), NULLIF(TRIM({$alias}.email), ''), '')";
    }

    return "COALESCE(NULLIF(TRIM({$alias}.login_name), ''), NULLIF(TRIM({$alias}.email), ''), '')";
}

function app_url_starts_with(string $value, string $prefix): bool
{
    if ($prefix === '') {
        return false;
    }

    return substr($value, 0, strlen($prefix)) === $prefix;
}

function app_apply_provider_url_replacement(string $url, array $providerData): string
{
    $url = trim($url);
    if ($url === '' || empty($providerData['supports_url_replacement']) || empty($providerData['supports_delivery_links'])) {
        return $url;
    }

    $replaceFrom = trim((string)($providerData['url_replacement_from'] ?? ''));
    $replaceTo = trim((string)($providerData['url_replacement_to'] ?? ''));
    if ($replaceFrom === '' || $replaceTo === '') {
        return $url;
    }

    if (app_url_starts_with($url, $replaceFrom)) {
        return $replaceTo . substr($url, strlen($replaceFrom));
    }

    return $url;
}

function app_delivery_credentials_from_url(string $url): array
{
    $url = trim($url);
    if ($url === '') {
        return ['login' => '', 'password' => ''];
    }

    $login = '';
    $password = '';
    $parts = parse_url($url);
    if (is_array($parts)) {
        $login = trim((string)($parts['user'] ?? ''));
        $password = trim((string)($parts['pass'] ?? ''));

        $query = (string)($parts['query'] ?? '');
        if ($query !== '') {
            $queryValues = [];
            parse_str($query, $queryValues);
            if ($queryValues) {
                $normalized = [];
                foreach ($queryValues as $key => $value) {
                    $normalized[strtolower((string)$key)] = is_scalar($value) ? trim((string)$value) : '';
                }

                if ($login === '') {
                    $login = (string)($normalized['username'] ?? $normalized['user'] ?? $normalized['login'] ?? '');
                }

                if ($password === '') {
                    $password = (string)($normalized['password'] ?? $normalized['pass'] ?? $normalized['pwd'] ?? '');
                }
            }
        }
    }

    return [
        'login' => $login,
        'password' => $password,
    ];
}

function app_order_delivery_payload(array $order): array
{
    $rawUrl = trim((string)($order['delivery_link_raw'] ?? $order['delivery_link'] ?? ''));
    $manualDelivery = !empty($order['supports_manual_delivery']);
    $deliveryLinksEnabled = !array_key_exists('supports_delivery_links', $order) || !empty($order['supports_delivery_links']);
    $showUrl = $deliveryLinksEnabled && $rawUrl !== '' && !empty($order['delivery_link_visible']);
    $effectiveUrl = app_apply_provider_url_replacement($rawUrl, $order);
    $credentials = app_delivery_credentials_from_url($effectiveUrl !== '' ? $effectiveUrl : $rawUrl);
    $showCredentials = $deliveryLinksEnabled && $manualDelivery && ($credentials['login'] !== '' || $credentials['password'] !== '');

    return [
        'raw_url' => $rawUrl,
        'url' => $showUrl ? $effectiveUrl : '',
        'show_url' => $showUrl,
        'show_credentials' => $showCredentials,
        'login' => (string)$credentials['login'],
        'password' => (string)$credentials['password'],
    ];
}

function app_order_progress_data(array $order): array
{
    $createdAt = !empty($order['date_add']) ? strtotime((string)$order['date_add']) : (!empty($order['created_at']) ? strtotime((string)$order['created_at']) : 0);
    $expiresAt = !empty($order['date_end']) ? strtotime((string)$order['date_end']) : (!empty($order['expires_at']) ? strtotime((string)$order['expires_at']) : 0);
    $now = time();

    if ($expiresAt <= 0) {
        return [
            'has_expiry' => false,
            'remaining_seconds' => 0,
            'remaining_days' => 0,
            'percent' => 0,
            'color' => '#d1d5db',
            'tone' => 'neutral',
        ];
    }

    $totalSeconds = max(1, $expiresAt - ($createdAt > 0 ? $createdAt : $now));
    $remainingSeconds = max(0, $expiresAt - $now);
    $elapsedSeconds = max(0, $totalSeconds - $remainingSeconds);
    $percent = (int)round(min(1, $elapsedSeconds / $totalSeconds) * 100);
    $remainingDays = $remainingSeconds > 0 ? (int)ceil($remainingSeconds / 86400) : 0;

    if ($remainingSeconds <= 0) {
        $tone = 'expired';
        $color = '#ef4444';
        $percent = 100;
    } elseif ($remainingDays <= 7) {
        $tone = 'danger';
        $color = '#ef4444';
    } elseif ($remainingDays <= 30) {
        $tone = 'warning';
        $color = '#f59e0b';
    } else {
        $tone = 'success';
        $color = '#16a34a';
    }

    return [
        'has_expiry' => true,
        'remaining_seconds' => $remainingSeconds,
        'remaining_days' => $remainingDays,
        'percent' => $percent,
        'color' => $color,
        'tone' => $tone,
    ];
}

function app_order_status_visual(array $order): array
{
    $status = strtolower(trim((string)($order['status_name'] ?? $order['status'] ?? '')));
    $paymentStatus = strtolower(trim((string)($order['payment_status_name'] ?? $order['payment_status'] ?? '')));
    $fulfillmentStatus = strtolower(trim((string)($order['fulfillment_status_name'] ?? $order['fulfillment_status'] ?? '')));

    if (($status === 'pending_payment' || (isset($order['status']) && (string)$order['status'] === '0'))
        && $paymentStatus === 'paid'
        && !in_array($fulfillmentStatus, ['delivered', 'fulfilled', 'completed'], true)
    ) {
        return [
            'icon' => 'fa fa-check-circle',
            'class' => 'orders-status-awaiting-activation',
            'label' => 'Payment confirmed',
            'label_key' => 'orders_status_payment_confirmed',
        ];
    }

    if ($status === 'pending_payment' || $paymentStatus === 'unpaid' || $fulfillmentStatus === 'pending' || (isset($order['status']) && (string)$order['status'] === '0')) {
        return [
            'icon' => 'fa fa-refresh',
            'class' => 'orders-status-pending',
            'label' => 'Pending',
            'label_key' => 'orders_status_pending',
        ];
    }

    if ($status === 'expired' || (isset($order['status']) && (string)$order['status'] === '2')) {
        return [
            'icon' => 'fa fa-minus-circle',
            'class' => 'orders-status-expired',
            'label' => 'Expired',
            'label_key' => 'orders_status_expired',
        ];
    }

    if ($status === 'cancelled' || $fulfillmentStatus === 'cancelled') {
        return [
            'icon' => 'fa fa-times-circle',
            'class' => 'orders-status-expired',
            'label' => 'Cancelled',
            'label_key' => 'orders_status_cancelled',
        ];
    }

    if (($status === 'active' && $paymentStatus === 'paid') || (isset($order['status']) && (string)$order['status'] === '1')) {
        return [
            'icon' => 'fa fa-check-circle',
            'class' => 'orders-status-active',
            'label' => 'Active',
            'label_key' => 'orders_status_active',
        ];
    }

    return [
        'icon' => 'fa fa-circle',
        'class' => 'orders-status-neutral',
        'label' => 'Status',
        'label_key' => 'orders_status_default',
    ];
}

function app_history_badge(string $label, string $modifier = ''): string
{
    $className = 'history-entry__badge';
    if ($modifier !== '') {
        $className .= ' history-entry__badge--' . preg_replace('/[^a-z0-9_-]+/i', '', strtolower($modifier));
    }

    return '<span class="' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

function app_history_event_summary(
    string $badgeLabel,
    string $badgeModifier,
    string $title,
    array $metaItems = []
): string {
    $parts = [];
    foreach ($metaItems as $item) {
        $itemLabel = trim((string)($item['label'] ?? ''));
        if ($itemLabel === '') {
            continue;
        }

        $itemClass = 'history-entry__meta-item';
        if (!empty($item['strong'])) {
            $itemClass .= ' history-entry__meta-item--strong';
        }

        $parts[] = '<span class="' . htmlspecialchars($itemClass, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $metaHtml = '';
    if ($parts) {
        $metaHtml = '<div class="history-entry__meta">' . implode('<span class="history-entry__meta-dot"></span>', $parts) . '</div>';
    }

    return '<div class="history-entry">'
        . app_history_badge($badgeLabel, $badgeModifier)
        . '<div class="history-entry__body">'
        . '<div class="history-entry__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>'
        . $metaHtml
        . '</div>'
        . '</div>';
}

function app_history_payment_summary(
    array $messages,
    string $badgeLabel,
    string $badgeModifier,
    string $orderLabel,
    string $fiatLabel,
    string $secondaryLabel
): string {
    return '<div class="history-entry history-entry--payment">'
        . app_history_badge($badgeLabel, $badgeModifier)
        . '<div class="history-entry__body">'
        . '<div class="history-entry__title">'
        . htmlspecialchars(localization_translate($messages, 'history_payment_request_title'), ENT_QUOTES, 'UTF-8')
        . ' <strong>' . htmlspecialchars($orderLabel, ENT_QUOTES, 'UTF-8') . '</strong>'
        . '</div>'
        . '<div class="history-entry__meta">'
        . '<span class="history-entry__meta-item">' . htmlspecialchars($fiatLabel, ENT_QUOTES, 'UTF-8') . '</span>'
        . '<span class="history-entry__meta-dot"></span>'
        . '<span class="history-entry__meta-item history-entry__meta-item--strong">' . htmlspecialchars($secondaryLabel, ENT_QUOTES, 'UTF-8') . '</span>'
        . '</div>'
        . '</div>'
        . '</div>';
}

function app_history_is_visible(string $eventDate, int $visibleFromTimestamp): bool
{
    if ($eventDate === '') {
        return false;
    }

    if ($visibleFromTimestamp <= 0) {
        return true;
    }

    $eventTimestamp = strtotime($eventDate);
    if ($eventTimestamp === false) {
        return false;
    }

    return $eventTimestamp >= $visibleFromTimestamp;
}

function app_uses_v2_schema(Mysql_ks $db): bool
{
    return schema_object_exists($db, 'app_settings') && schema_object_exists($db, 'customers');
}

function app_ensure_settings_runtime_columns(Mysql_ks $db): void
{
    static $done = false;

    if ($done || !schema_object_exists($db, 'app_settings')) {
        return;
    }

    $done = true;

    if (!schema_column_exists($db, 'app_settings', 'contact_form_enabled')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN contact_form_enabled TINYINT(1) NOT NULL DEFAULT 1
             AFTER support_chat_enabled"
        );
        schema_forget_column_cache('app_settings', 'contact_form_enabled');
    }

    if (!schema_column_exists($db, 'app_settings', 'referrals_enabled')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN referrals_enabled TINYINT(1) NOT NULL DEFAULT 1
             AFTER contact_form_enabled"
        );
        schema_forget_column_cache('app_settings', 'referrals_enabled');
    }

    if (!schema_column_exists($db, 'app_settings', 'apps_page_enabled')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN apps_page_enabled TINYINT(1) NOT NULL DEFAULT 1
             AFTER referrals_enabled"
        );
        schema_forget_column_cache('app_settings', 'apps_page_enabled');
    }

    if (!schema_column_exists($db, 'app_settings', 'application_instructions_enabled')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN application_instructions_enabled TINYINT(1) NOT NULL DEFAULT 1
             AFTER apps_page_enabled"
        );
        schema_forget_column_cache('app_settings', 'application_instructions_enabled');
    }

    if (!schema_column_exists($db, 'app_settings', 'page_guidance_enabled')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN page_guidance_enabled TINYINT(1) NOT NULL DEFAULT 1
             AFTER application_instructions_enabled"
        );
        schema_forget_column_cache('app_settings', 'page_guidance_enabled');
    }

    if (!schema_column_exists($db, 'app_settings', 'payment_test_mode_notice_enabled')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN payment_test_mode_notice_enabled TINYINT(1) NOT NULL DEFAULT 0
             AFTER page_guidance_enabled"
        );
        schema_forget_column_cache('app_settings', 'payment_test_mode_notice_enabled');
    }

    if (!schema_column_exists($db, 'app_settings', 'crypto_daily_backup_enabled')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN crypto_daily_backup_enabled TINYINT(1) NOT NULL DEFAULT 0
             AFTER payment_test_mode_notice_enabled"
        );
        schema_forget_column_cache('app_settings', 'crypto_daily_backup_enabled');
    }

    if (!schema_column_exists($db, 'app_settings', 'crypto_daily_backup_email')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN crypto_daily_backup_email VARCHAR(191) DEFAULT NULL
             AFTER crypto_daily_backup_enabled"
        );
        schema_forget_column_cache('app_settings', 'crypto_daily_backup_email');
    }

    if (!schema_column_exists($db, 'app_settings', 'crypto_daily_backup_last_processed_date')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN crypto_daily_backup_last_processed_date DATE DEFAULT NULL
             AFTER crypto_daily_backup_email"
        );
        schema_forget_column_cache('app_settings', 'crypto_daily_backup_last_processed_date');
    }

    if (!schema_column_exists($db, 'app_settings', 'manual_database_backup_last_downloaded_at')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN manual_database_backup_last_downloaded_at DATETIME DEFAULT NULL
             AFTER crypto_daily_backup_last_processed_date"
        );
        schema_forget_column_cache('app_settings', 'manual_database_backup_last_downloaded_at');
    }

    if (!schema_column_exists($db, 'app_settings', 'history_cleanup_enabled')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN history_cleanup_enabled TINYINT(1) NOT NULL DEFAULT 0
             AFTER application_instructions_enabled"
        );
        schema_forget_column_cache('app_settings', 'history_cleanup_enabled');
    }

    if (!schema_column_exists($db, 'app_settings', 'payments_cleanup_enabled')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN payments_cleanup_enabled TINYINT(1) NOT NULL DEFAULT 0
             AFTER history_cleanup_enabled"
        );
        schema_forget_column_cache('app_settings', 'payments_cleanup_enabled');
    }

    if (!schema_column_exists($db, 'app_settings', 'expired_orders_cleanup_enabled')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN expired_orders_cleanup_enabled TINYINT(1) NOT NULL DEFAULT 0
             AFTER payments_cleanup_enabled"
        );
        schema_forget_column_cache('app_settings', 'expired_orders_cleanup_enabled');
    }

    if (!schema_column_exists($db, 'app_settings', 'support_chat_retention_hours')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN support_chat_retention_hours SMALLINT UNSIGNED NOT NULL DEFAULT 168
             AFTER support_chat_retention_days"
        );
        schema_forget_column_cache('app_settings', 'support_chat_retention_hours');
    }

    if (!schema_column_exists($db, 'app_settings', 'credits_sales_enabled')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN credits_sales_enabled TINYINT(1) NOT NULL DEFAULT 0
             AFTER sales_enabled"
        );
        schema_forget_column_cache('app_settings', 'credits_sales_enabled');
    }

    if (!schema_column_exists($db, 'app_settings', 'reseller_group_chat_limit')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN reseller_group_chat_limit TINYINT UNSIGNED NOT NULL DEFAULT 10
             AFTER support_chat_enabled"
        );
        schema_forget_column_cache('app_settings', 'reseller_group_chat_limit');
    }

    if (!schema_column_exists($db, 'app_settings', 'customer_type_switch_enabled')) {
        @$db->query(
            "ALTER TABLE app_settings
             ADD COLUMN customer_type_switch_enabled TINYINT(1) NOT NULL DEFAULT 0
             AFTER apps_page_enabled"
        );
        schema_forget_column_cache('app_settings', 'customer_type_switch_enabled');
    }
}

function app_ensure_customer_runtime_columns(Mysql_ks $db): void
{
    static $done = false;

    if ($done || !schema_object_exists($db, 'customers')) {
        return;
    }

    $done = true;

    if (!schema_column_exists($db, 'customers', 'customer_type')) {
        @$db->query(
            "ALTER TABLE customers
             ADD COLUMN customer_type VARCHAR(20) NOT NULL DEFAULT 'client'
             AFTER status"
        );
        schema_forget_column_cache('customers', 'customer_type');
    }

    if (!schema_column_exists($db, 'customers', 'public_handle')) {
        @$db->query(
            "ALTER TABLE customers
             ADD COLUMN public_handle VARCHAR(80) DEFAULT NULL
             AFTER email"
        );
        schema_forget_column_cache('customers', 'public_handle');
    }

    if (!schema_column_exists($db, 'customers', 'avatar_url')) {
        @$db->query(
            "ALTER TABLE customers
             ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL
             AFTER public_handle"
        );
        schema_forget_column_cache('customers', 'avatar_url');
    }

    if (schema_column_exists($db, 'customers', 'public_handle')) {
        app_backfill_missing_customer_public_handles($db);
    }
}

function app_ensure_customer_provider_visibility_runtime_table(Mysql_ks $db): void
{
    static $done = false;

    if ($done || !schema_object_exists($db, 'customers') || !schema_object_exists($db, 'product_providers')) {
        return;
    }

    $done = true;

    if (!schema_object_exists($db, 'customer_provider_visibility')) {
        @$db->query(
            "CREATE TABLE IF NOT EXISTS customer_provider_visibility (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                customer_id INT UNSIGNED NOT NULL,
                provider_id INT UNSIGNED NOT NULL,
                is_visible TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_customer_provider_visibility_customer_provider (customer_id, provider_id),
                KEY idx_customer_provider_visibility_customer_visible (customer_id, is_visible),
                KEY idx_customer_provider_visibility_provider_visible (provider_id, is_visible)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function app_customer_provider_visibility_sql(Mysql_ks $db, int $customerId, string $providerColumn = 'products.provider_id'): string
{
    $customerId = (int)$customerId;
    $providerColumn = trim($providerColumn) !== '' ? trim($providerColumn) : 'products.provider_id';

    if ($customerId <= 0) {
        return '';
    }

    app_ensure_customer_provider_visibility_runtime_table($db);
    if (!schema_object_exists($db, 'customer_provider_visibility')) {
        return '';
    }

    return " AND NOT EXISTS (
        SELECT 1
        FROM customer_provider_visibility
        WHERE customer_provider_visibility.customer_id = {$customerId}
          AND customer_provider_visibility.provider_id = {$providerColumn}
          AND customer_provider_visibility.is_visible = 0
    )";
}

function app_customer_provider_is_visible(Mysql_ks $db, int $customerId, int $providerId): bool
{
    $customerId = (int)$customerId;
    $providerId = (int)$providerId;
    if ($customerId <= 0 || $providerId <= 0) {
        return true;
    }

    app_ensure_customer_provider_visibility_runtime_table($db);
    if (!schema_object_exists($db, 'customer_provider_visibility')) {
        return true;
    }

    $row = $db->select_user(
        "SELECT is_visible
         FROM customer_provider_visibility
         WHERE customer_id = {$customerId}
           AND provider_id = {$providerId}
         LIMIT 1"
    );

    if (!is_array($row) || !array_key_exists('is_visible', $row)) {
        return true;
    }

    return (int)($row['is_visible'] ?? 1) === 1;
}

function app_save_customer_provider_visibility(Mysql_ks $db, int $customerId, array $visibleProviderIds): bool
{
    $customerId = (int)$customerId;
    if ($customerId <= 0) {
        return false;
    }

    app_ensure_customer_provider_visibility_runtime_table($db);
    if (!schema_object_exists($db, 'customer_provider_visibility') || !schema_object_exists($db, 'product_providers')) {
        return false;
    }

    $providerRows = $db->select_full_user("SELECT id FROM product_providers");
    if (!$providerRows) {
        return true;
    }

    $allProviderIds = [];
    foreach ($providerRows as $providerRow) {
        $providerId = (int)($providerRow['id'] ?? 0);
        if ($providerId > 0) {
            $allProviderIds[] = $providerId;
        }
    }

    $visibleMap = [];
    foreach ($visibleProviderIds as $visibleProviderId) {
        $visibleProviderId = (int)$visibleProviderId;
        if ($visibleProviderId > 0) {
            $visibleMap[$visibleProviderId] = true;
        }
    }

    $db->query("DELETE FROM customer_provider_visibility WHERE customer_id = {$customerId}");

    foreach ($allProviderIds as $providerId) {
        if (!isset($visibleMap[$providerId])) {
            $db->insert(
                ['customer_id', 'provider_id', 'is_visible'],
                [$customerId, $providerId, 0],
                'customer_provider_visibility'
            );
        }
    }

    return true;
}

function app_normalize_customer_type($value): string
{
    $type = strtolower(trim((string)$value));
    if ($type === 'customer') {
        $type = 'client';
    }

    if (!in_array($type, ['client', 'reseller'], true)) {
        $type = 'client';
    }

    return $type;
}

function app_normalize_customer_public_handle(string $value): string
{
    if (function_exists('chat_normalize_public_handle')) {
        return chat_normalize_public_handle($value);
    }

    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? $value;
    return trim($value, '-._');
}

function app_customer_avatar_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strlen') && mb_strlen($value) > 255) {
        $value = mb_substr($value, 0, 255);
    } elseif (strlen($value) > 255) {
        $value = substr($value, 0, 255);
    }

    if (strpos($value, 'http://') === 0 || strpos($value, 'https://') === 0) {
        return $value;
    }

    $normalized = ltrim($value, '/');
    if ($normalized === '') {
        return '';
    }

    if (is_file(app_public_path($normalized))) {
        return '/' . $normalized;
    }

    return '';
}

function app_admin_avatar_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '/img/admin_avatar.png';
    }

    if (function_exists('mb_strlen') && mb_strlen($value) > 255) {
        $value = mb_substr($value, 0, 255);
    } elseif (strlen($value) > 255) {
        $value = substr($value, 0, 255);
    }

    if (strpos($value, 'http://') === 0 || strpos($value, 'https://') === 0) {
        return $value;
    }

    $normalized = ltrim($value, '/');
    $candidates = [
        'img/avatary/' . $normalized,
        'img/' . $normalized,
    ];

    foreach ($candidates as $candidate) {
        if (is_file(app_public_path($candidate))) {
            return '/' . $candidate;
        }
    }

    if (is_file(app_public_path($normalized))) {
        return '/' . $normalized;
    }

    return '/img/admin_avatar.png';
}

function app_customer_avatar_upload_directory(): string
{
    return app_public_path('uploads/avatars/customers');
}

function app_customer_avatar_create_image_resource(string $sourcePath, string $mimeType)
{
    switch (strtolower(trim($mimeType))) {
        case 'image/jpeg':
            return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false;
        case 'image/png':
            return function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false;
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false;
    }

    return false;
}

function app_store_customer_avatar_upload(array $file, int $customerId): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_OK);
    $tmpPath = (string)($file['tmp_name'] ?? '');
    $maxBytes = 5 * 1024 * 1024;

    if ($customerId <= 0 || $uploadError !== UPLOAD_ERR_OK || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'code' => 'upload_error'];
    }

    $fileSize = @filesize($tmpPath);
    if (!is_int($fileSize) || $fileSize <= 0) {
        $fileSize = isset($file['size']) ? (int)$file['size'] : 0;
    }

    if ($fileSize <= 0 || $fileSize > $maxBytes) {
        return ['ok' => false, 'code' => 'too_large'];
    }

    $imageInfo = @getimagesize($tmpPath);
    $mimeType = strtolower(trim((string)($imageInfo['mime'] ?? '')));
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        return ['ok' => false, 'code' => 'invalid_type'];
    }

    $uploadDirectory = app_customer_avatar_upload_directory();
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        return ['ok' => false, 'code' => 'upload_error'];
    }

    $extension = $allowedMimeTypes[$mimeType];
    $fileName = 'customer_avatar_' . $customerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destinationPath = $uploadDirectory . '/' . $fileName;
    $saved = false;

    $canResize = function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled')
        && function_exists('imagedestroy');

    if ($canResize) {
        $sourceImage = app_customer_avatar_create_image_resource($tmpPath, $mimeType);
        if ($sourceImage) {
            $width = imagesx($sourceImage);
            $height = imagesy($sourceImage);

            if ($width > 0 && $height > 0) {
                $maxDimension = 512;
                $scale = min($maxDimension / $width, $maxDimension / $height, 1);
                $targetWidth = max(1, (int)round($width * $scale));
                $targetHeight = max(1, (int)round($height * $scale));
                $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

                if ($targetImage) {
                    if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                        imagealphablending($targetImage, false);
                        imagesavealpha($targetImage, true);
                        $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
                        imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
                    } else {
                        $background = imagecolorallocate($targetImage, 255, 255, 255);
                        imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $background);
                    }

                    imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

                    if ($mimeType === 'image/png') {
                        $saved = function_exists('imagepng') ? imagepng($targetImage, $destinationPath, 6) : false;
                    } elseif ($mimeType === 'image/webp') {
                        $saved = function_exists('imagewebp') ? imagewebp($targetImage, $destinationPath, 82) : false;
                    } else {
                        $saved = function_exists('imagejpeg') ? imagejpeg($targetImage, $destinationPath, 82) : false;
                    }

                    imagedestroy($targetImage);
                }
            }

            imagedestroy($sourceImage);
        }
    }

    if (!$saved) {
        $saved = move_uploaded_file($tmpPath, $destinationPath);
    }

    if (!$saved) {
        return ['ok' => false, 'code' => 'upload_error'];
    }

    return [
        'ok' => true,
        'url' => '/uploads/avatars/customers/' . $fileName,
    ];
}

function app_delete_customer_avatar_file(string $publicPath): bool
{
    $publicPath = trim($publicPath);
    if ($publicPath === '' || strpos($publicPath, '/uploads/avatars/customers/') !== 0) {
        return false;
    }

    $publicRoot = realpath(app_public_path());
    if ($publicRoot === false) {
        return false;
    }

    $filePath = $publicRoot . '/' . ltrim($publicPath, '/');
    $realFilePath = realpath($filePath);
    if ($realFilePath === false || strpos($realFilePath, $publicRoot . '/uploads/avatars/customers/') !== 0 || !is_file($realFilePath)) {
        return false;
    }

    return @unlink($realFilePath);
}

function app_customer_handle_base_from_email(string $email): string
{
    $email = strtolower(trim($email));
    $localPart = $email;
    if (strpos($email, '@') !== false) {
        $localPart = (string)substr($email, 0, strpos($email, '@'));
    }

    $base = app_normalize_customer_public_handle($localPart);
    if ($base === '') {
        $base = 'user';
    }

    return $base;
}

function app_customer_public_handle_exists(Mysql_ks $db, string $handle, int $excludeCustomerId = 0): bool
{
    $handle = app_normalize_customer_public_handle($handle);
    if ($handle === '' || !schema_object_exists($db, 'customers') || !schema_column_exists($db, 'customers', 'public_handle')) {
        return false;
    }

    $safeHandle = $db->escape($handle);
    $excludeSql = $excludeCustomerId > 0 ? " AND id <> {$excludeCustomerId}" : '';
    $row = $db->select_user(
        "SELECT id
         FROM customers
         WHERE public_handle = '{$safeHandle}'{$excludeSql}
         LIMIT 1"
    );

    return is_array($row) && !empty($row['id']);
}

function app_generate_customer_public_handle(Mysql_ks $db, string $email, int $excludeCustomerId = 0): string
{
    $base = app_customer_handle_base_from_email($email);
    $candidate = $base;
    $suffix = 2;

    while (app_customer_public_handle_exists($db, $candidate, $excludeCustomerId)) {
        $candidate = $base . $suffix;
        $suffix++;
    }

    return $candidate;
}

function app_resolve_customer_public_handle(Mysql_ks $db, string $handleInput, string $email, int $excludeCustomerId = 0): array
{
    $handle = app_normalize_customer_public_handle($handleInput);
    if ($handle === '') {
        return [
            'ok' => true,
            'handle' => app_generate_customer_public_handle($db, $email, $excludeCustomerId),
            'generated' => true,
        ];
    }

    if (app_customer_public_handle_exists($db, $handle, $excludeCustomerId)) {
        return ['ok' => false, 'message' => 'This username is already taken.'];
    }

    return [
        'ok' => true,
        'handle' => $handle,
        'generated' => false,
    ];
}

function app_backfill_missing_customer_public_handles(Mysql_ks $db): void
{
    static $done = false;
    if ($done || !schema_object_exists($db, 'customers') || !schema_column_exists($db, 'customers', 'public_handle')) {
        return;
    }

    $done = true;
    $rows = $db->select_full_user(
        "SELECT id, email
         FROM customers
         WHERE public_handle IS NULL
            OR TRIM(public_handle) = ''
         ORDER BY id ASC
         LIMIT 500"
    );

    foreach ($rows as $row) {
        $customerId = (int)($row['id'] ?? 0);
        $email = trim((string)($row['email'] ?? ''));
        if ($customerId <= 0 || $email === '') {
            continue;
        }

        $handle = app_generate_customer_public_handle($db, $email, $customerId);
        if ($handle === '') {
            continue;
        }

        $db->update_using_id(['public_handle'], [$handle], 'customers', $customerId);
    }
}

function app_customer_display_label(array $customer): string
{
    $handle = app_normalize_customer_public_handle((string)($customer['public_handle'] ?? ''));
    if ($handle !== '') {
        return $handle;
    }

    $email = trim((string)($customer['email'] ?? ''));
    if ($email !== '') {
        if (strpos($email, '@') !== false) {
            $email = (string)substr($email, 0, strpos($email, '@'));
        }
        $email = trim($email);
    }

    return $email !== '' ? $email : 'user';
}

function app_customer_avatar_initial(array $customer): string
{
    $label = app_customer_display_label($customer);
    if ($label === '') {
        return 'U';
    }

    return strtoupper(function_exists('mb_substr') ? mb_substr($label, 0, 1) : substr($label, 0, 1));
}

function app_customer_avatar_theme(array $customer): string
{
    $seed = strtolower(trim((string)($customer['public_handle'] ?? $customer['email'] ?? '')));
    if ($seed === '') {
        return 'theme-1';
    }

    $index = abs((int)crc32($seed)) % 6;
    return 'theme-' . ($index + 1);
}

function app_customer_product_type(array $customer): string
{
    return app_normalize_customer_type($customer['customer_type'] ?? '') === 'reseller'
        ? 'credits'
        : 'subscription';
}

function app_credits_sales_enabled(array $settings): bool
{
    return !empty($settings['credits_sales_enabled']);
}

function app_customer_sales_enabled(array $customer, array $settings): bool
{
    $salesEnabled = !empty($settings['sales_enabled']) || !empty($settings['active_sale']);
    if (!$salesEnabled) {
        return false;
    }

    if (app_customer_product_type($customer) === 'credits') {
        return app_credits_sales_enabled($settings);
    }

    return true;
}

function app_customer_news_visibilities(array $customer): array
{
    if (app_normalize_customer_type($customer['customer_type'] ?? '') === 'reseller') {
        return ['public', 'reseller'];
    }

    return ['public', 'client', 'customer'];
}

function app_sql_string_list(Mysql_ks $db, array $values): string
{
    $safeValues = [];
    foreach ($values as $value) {
        $safeValues[] = "'" . $db->escape((string)$value) . "'";
    }

    return $safeValues ? implode(', ', $safeValues) : "''";
}

function app_product_type_sql(Mysql_ks $db, array $customer): string
{
    return "'" . $db->escape(app_customer_product_type($customer)) . "'";
}

function app_fetch_settings(Mysql_ks $db): array
{
    if (!app_uses_v2_schema($db)) {
        $settings = $db->select('settings');
        return is_array($settings) ? $settings : [];
    }

    app_ensure_settings_runtime_columns($db);

    $settings = $db->select_user(
        "SELECT
            app_settings.*,
            currencies.code AS currency_short,
            currencies.symbol AS currency_symbol
         FROM app_settings
         LEFT JOIN currencies ON currencies.id = app_settings.default_currency_id
         LIMIT 1"
    );

    if (!is_array($settings)) {
        return [];
    }

    $settings['technical_break'] = (int)($settings['maintenance_mode'] ?? 0);
    $settings['admin_email'] = $settings['support_email'] ?? '';
    $settings['page_title'] = $settings['site_title'] ?? '';
    $settings['page_name'] = $settings['site_name'] ?? '';
    $settings['page_url'] = $settings['site_url'] ?? '';
    $settings['page_logo'] = $settings['site_logo_url'] ?? '';
    $settings['page_desc'] = $settings['site_description'] ?? '';
    $settings['page_keywords'] = $settings['site_keywords'] ?? '';
    $settings['currency'] = (int)($settings['default_currency_id'] ?? 0);
    $settings['liczba_wynikow'] = (int)($settings['results_per_page'] ?? 20);
    $settings['smtp_login'] = $settings['smtp_username'] ?? '';
    $settings['smtp_haslo'] = $settings['smtp_password'] ?? '';
    $settings['active_register'] = (int)($settings['registration_enabled'] ?? 0);
    $settings['active_sale'] = (int)($settings['sales_enabled'] ?? 0);
    $settings['active_trials'] = (int)($settings['trials_enabled'] ?? 0);
    $settings['contact_form_enabled'] = (int)($settings['contact_form_enabled'] ?? 1);
    $settings['referrals_enabled'] = (int)($settings['referrals_enabled'] ?? 1);
    $settings['apps_page_enabled'] = (int)($settings['apps_page_enabled'] ?? 1);
    $settings['customer_type_switch_enabled'] = (int)($settings['customer_type_switch_enabled'] ?? 0);
    $settings['application_instructions_enabled'] = (int)($settings['application_instructions_enabled'] ?? 1);
    $settings['page_guidance_enabled'] = (int)($settings['page_guidance_enabled'] ?? 1);
    $settings['payment_test_mode_notice_enabled'] = (int)($settings['payment_test_mode_notice_enabled'] ?? 0);
    $settings['crypto_daily_backup_enabled'] = (int)($settings['crypto_daily_backup_enabled'] ?? 0);
    $settings['crypto_daily_backup_email'] = trim((string)($settings['crypto_daily_backup_email'] ?? ''));
    $settings['crypto_daily_backup_last_processed_date'] = trim((string)($settings['crypto_daily_backup_last_processed_date'] ?? ''));
    $settings['manual_database_backup_last_downloaded_at'] = trim((string)($settings['manual_database_backup_last_downloaded_at'] ?? ''));
    $settings['history_cleanup_enabled'] = (int)($settings['history_cleanup_enabled'] ?? 0);
    $settings['payments_cleanup_enabled'] = (int)($settings['payments_cleanup_enabled'] ?? 0);
    $settings['expired_orders_cleanup_enabled'] = (int)($settings['expired_orders_cleanup_enabled'] ?? 0);
    $settings['active_referrals'] = (int)($settings['referrals_enabled'] ?? 1);
    $settings['crypto_payments_enabled'] = (int)($settings['crypto_payments_enabled'] ?? 0);
    $settings['bank_transfers_enabled'] = (int)($settings['bank_transfers_enabled'] ?? 0);
    $settings['reseller_group_chat_limit'] = (int)($settings['reseller_group_chat_limit'] ?? 10);
    if ($settings['reseller_group_chat_limit'] < 0) {
        $settings['reseller_group_chat_limit'] = 0;
    } elseif ($settings['reseller_group_chat_limit'] > 10) {
        $settings['reseller_group_chat_limit'] = 10;
    }
    $legacyRetentionDays = max(1, min(30, (int)($settings['support_chat_retention_days'] ?? 7)));
    $settings['support_chat_retention_days'] = $legacyRetentionDays;
    $settings['support_chat_retention_hours'] = (int)($settings['support_chat_retention_hours'] ?? ($legacyRetentionDays * 24));
    if (!in_array($settings['support_chat_retention_hours'], [1, 24, 72, 168, 720], true)) {
        $settings['support_chat_retention_hours'] = 168;
    }
    $settings['count_news'] = (int)($settings['news_feed_limit'] ?? 10);
    $settings['homepage_verify'] = (int)($settings['homepage_verification_enabled'] ?? 0);
    $settings['apikey'] = $settings['api_key'] ?? '';

    return $settings;
}

function app_crypto_daily_backup_enabled(array $settings): bool
{
    return !empty($settings['crypto_daily_backup_enabled']);
}

function app_crypto_daily_backup_recipient(array $settings): string
{
    $recipient = strtolower(trim((string)($settings['crypto_daily_backup_email'] ?? '')));
    if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return $recipient;
    }

    return app_email_support_recipient($settings);
}

function app_crypto_daily_backup_filename(string $reportDate): string
{
    $normalizedDate = preg_replace('/[^0-9]/', '', $reportDate);
    if ($normalizedDate === null || $normalizedDate === '') {
        $normalizedDate = date('Ymd');
    }

    return 'crypto-payments-backup-' . $normalizedDate . '.csv';
}

function app_crypto_daily_backup_target_date(array $settings, string $safeNow): string
{
    $timestamp = strtotime($safeNow);
    if ($timestamp === false) {
        $timestamp = time();
    }

    $reportDate = date('Y-m-d', strtotime('-1 day', $timestamp));
    $lastProcessed = trim((string)($settings['crypto_daily_backup_last_processed_date'] ?? ''));

    if ($lastProcessed === $reportDate) {
        return '';
    }

    return $reportDate;
}

function app_crypto_daily_backup_update_last_processed_date(Mysql_ks $db, string $reportDate): void
{
    if ($reportDate === '' || !schema_object_exists($db, 'app_settings') || !schema_column_exists($db, 'app_settings', 'crypto_daily_backup_last_processed_date')) {
        return;
    }

    $db->update_using_id(
        ['crypto_daily_backup_last_processed_date'],
        [$reportDate],
        'app_settings',
        1
    );
}

function app_crypto_daily_backup_fetch_rows(Mysql_ks $db, string $reportDate): array
{
    if ($reportDate === ''
        || !schema_object_exists($db, 'crypto_deposit_requests')
        || !schema_object_exists($db, 'customers')
        || !schema_object_exists($db, 'crypto_assets')
        || !schema_object_exists($db, 'crypto_wallet_addresses')
    ) {
        return [];
    }

    $safeReportDate = $db->escape($reportDate);
    $rows = $db->select_full_user(
        "SELECT
            crypto_deposit_requests.id AS request_id,
            crypto_deposit_requests.order_id,
            crypto_deposit_requests.customer_id,
            customers.email AS customer_email,
            crypto_assets.code AS crypto_ticker,
            COALESCE(tx.total_amount_crypto, crypto_deposit_requests.requested_crypto_amount, 0) AS amount_crypto,
            crypto_deposit_requests.requested_fiat_amount AS fiat_amount,
            currencies.code AS fiat_currency_code,
            crypto_wallet_addresses.address AS wallet_address,
            COALESCE(tx.transaction_hashes, '') AS transaction_hashes,
            CASE
                WHEN crypto_deposit_requests.order_id IS NULL OR crypto_deposit_requests.order_id = 0 THEN 'balance_topup'
                ELSE 'order'
            END AS payment_target,
            COALESCE(crypto_deposit_requests.confirmed_at, crypto_deposit_requests.requested_at) AS completed_at
         FROM crypto_deposit_requests
         INNER JOIN customers
            ON customers.id = crypto_deposit_requests.customer_id
         INNER JOIN crypto_assets
            ON crypto_assets.id = crypto_deposit_requests.crypto_asset_id
         INNER JOIN crypto_wallet_addresses
            ON crypto_wallet_addresses.id = crypto_deposit_requests.wallet_address_id
         LEFT JOIN currencies
            ON currencies.id = crypto_deposit_requests.fiat_currency_id
         LEFT JOIN (
            SELECT
                deposit_request_id,
                SUM(amount_crypto) AS total_amount_crypto,
                GROUP_CONCAT(transaction_hash ORDER BY received_at SEPARATOR ' | ') AS transaction_hashes
            FROM crypto_deposit_transactions
            GROUP BY deposit_request_id
         ) AS tx
            ON tx.deposit_request_id = crypto_deposit_requests.id
         WHERE crypto_deposit_requests.status IN ('confirmed', 'approved', 'paid', 'completed')
           AND DATE(COALESCE(crypto_deposit_requests.confirmed_at, crypto_deposit_requests.requested_at)) = '{$safeReportDate}'
         ORDER BY COALESCE(crypto_deposit_requests.confirmed_at, crypto_deposit_requests.requested_at) ASC, crypto_deposit_requests.id ASC"
    );

    return is_array($rows) ? $rows : [];
}

function app_crypto_daily_backup_csv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        return '';
    }

    fputcsv($handle, [
        'completed_at',
        'payment_target',
        'request_id',
        'order_id',
        'user_id',
        'email',
        'crypto_ticker',
        'amount_crypto',
        'fiat_amount',
        'fiat_currency',
        'wallet_address',
        'transaction_hashes',
    ]);

    foreach ($rows as $row) {
        fputcsv($handle, [
            (string)($row['completed_at'] ?? ''),
            (string)($row['payment_target'] ?? ''),
            (string)($row['request_id'] ?? ''),
            (string)($row['order_id'] ?? ''),
            (string)($row['customer_id'] ?? ''),
            (string)($row['customer_email'] ?? ''),
            strtoupper((string)($row['crypto_ticker'] ?? '')),
            number_format((float)($row['amount_crypto'] ?? 0), 8, '.', ''),
            number_format((float)($row['fiat_amount'] ?? 0), 2, '.', ''),
            strtoupper((string)($row['fiat_currency_code'] ?? '')),
            (string)($row['wallet_address'] ?? ''),
            (string)($row['transaction_hashes'] ?? ''),
        ]);
    }

    rewind($handle);
    $csv = (string)stream_get_contents($handle);
    fclose($handle);

    return $csv;
}

function app_email_builtin_templates(): array
{
    return [
        'account-activation' => [
            'name' => 'Account created notification',
            'subject' => 'Your account is active',
            'body' => app_email_builtin_templates_localized('en')['account-activation']['body'],
            'placeholders' => ['customer_email', 'email', 'login_url', 'site_name', 'site_url', 'pagename', 'pageurl'],
        ],
        'password-reset' => [
            'name' => 'Password reset',
            'subject' => 'Your new password',
            'body' => app_email_builtin_templates_localized('en')['password-reset']['body'],
            'placeholders' => ['customer_email', 'email', 'password', 'nowehaslo', 'site_name', 'site_url', 'pagename', 'pageurl'],
        ],
        'payment-request-created' => [
            'name' => 'Payment request notification',
            'subject' => 'Payment is waiting',
            'body' => app_email_builtin_templates_localized('en')['payment-request-created']['body'],
            'placeholders' => ['order_id', 'id_zamowienia', 'payment_url', 'site_name', 'site_url', 'pagename', 'pageurl'],
        ],
        'order-paid' => [
            'name' => 'Order paid notification',
            'subject' => 'Payment confirmed',
            'body' => app_email_builtin_templates_localized('en')['order-paid']['body'],
            'placeholders' => ['order_id', 'id_zamowienia', 'order_url', 'site_name', 'site_url', 'pagename', 'pageurl'],
        ],
        'order-activated' => [
            'name' => 'Order activated notification',
            'subject' => 'Your order is active',
            'body' => app_email_builtin_templates_localized('en')['order-activated']['body'],
            'placeholders' => ['order_id', 'id_zamowienia', 'order_url', 'site_name', 'site_url', 'pagename', 'pageurl'],
        ],
        'order-expired' => [
            'name' => 'Order expired notification',
            'subject' => 'Your order expired',
            'body' => app_email_builtin_templates_localized('en')['order-expired']['body'],
            'placeholders' => ['order_id', 'id_zamowienia', 'order_url', 'site_name', 'site_url', 'pagename', 'pageurl'],
        ],
        'live-chat-admin-notify' => [
            'name' => 'Live chat admin notification',
            'subject' => 'You have a new message [Support]',
            'body' => app_email_builtin_templates_localized('en')['live-chat-admin-notify']['body'],
            'placeholders' => ['customer_email', 'chat_url', 'site_name', 'site_url', 'pagename', 'pageurl'],
        ],
        'live-chat-customer-notify' => [
            'name' => 'Live chat customer notification',
            'subject' => 'You have a new message [Support]',
            'body' => app_email_builtin_templates_localized('en')['live-chat-customer-notify']['body'],
            'placeholders' => ['chat_url', 'site_name', 'site_url', 'pagename', 'pageurl'],
        ],
        'news-broadcast' => [
            'name' => 'News notification',
            'subject' => 'New important information',
            'body' => app_email_builtin_templates_localized('en')['news-broadcast']['body'],
            'placeholders' => ['news_url', 'site_name', 'site_url', 'pagename', 'pageurl'],
        ],
        'account-blocked' => [
            'name' => 'Account blocked notification',
            'subject' => 'Your account was blocked',
            'body' => app_email_builtin_templates_localized('en')['account-blocked']['body'],
            'placeholders' => ['site_name', 'site_url', 'pagename', 'pageurl'],
        ],
        'support-payment-request-notify' => [
            'name' => 'Support payment request notification',
            'subject' => 'Customer started payment [Support]',
            'body' => app_email_builtin_templates_localized('en')['support-payment-request-notify']['body'],
            'placeholders' => ['customer_email', 'order_id', 'id_zamowienia', 'admin_url', 'site_name', 'site_url', 'pagename', 'pageurl'],
        ],
        'reseller-chat-customer-notify' => [
            'name' => 'Reseller messenger notification',
            'subject' => 'You have a new messenger message',
            'body' => app_email_builtin_templates_localized('en')['reseller-chat-customer-notify']['body'],
            'placeholders' => ['conversation_title', 'sender_label', 'message_preview', 'chat_url', 'site_name', 'site_url', 'pagename', 'pageurl'],
        ],
    ];
}

function app_supported_email_locales(): array
{
    return ['en', 'pl'];
}

function app_normalize_email_locale(?string $localeCode): string
{
    $localeCode = strtolower(trim((string)$localeCode));
    return in_array($localeCode, app_supported_email_locales(), true) ? $localeCode : 'en';
}

function app_email_active_template_keys(): array
{
    return [
        'account-activation',
        'password-reset',
        'payment-request-created',
        'order-paid',
        'order-activated',
        'order-expired',
        'live-chat-admin-notify',
        'live-chat-customer-notify',
        'news-broadcast',
        'account-blocked',
        'support-payment-request-notify',
        'reseller-chat-customer-notify',
    ];
}

function app_email_builtin_templates_localized(string $localeCode): array
{
    $localeCode = app_normalize_email_locale($localeCode);
    $footer = app_email_standard_footer_html($localeCode);

    if ($localeCode === 'pl') {
        return [
            'account-activation' => ['subject' => 'Twoje konto jest aktywne', 'body' => "<h3>Witaj,</h3>\n<p>Twoje konto w <strong>%pagename%</strong> jest aktywne.</p>\n<p>Zaloguj się na swoje konto, aby rozpocząć korzystanie z panelu.</p>\n<p>Login: <strong>%email%</strong></p>\n<p>Panel logowania: <strong>%login_url%</strong></p>\n{$footer}"],
            'password-reset' => ['subject' => 'Twoje nowe hasło', 'body' => "<h3>Witaj,</h3>\n<p>Twoje konto w <strong>%pagename%</strong> jest aktywne.</p>\n<p>Zaloguj się na konto podając nowe hasło:<br /><br /></p>\n<p>Login: <strong>%email%</strong></p>\n<p>Password: <strong>%nowehaslo%</strong></p>\n{$footer}"],
            'payment-request-created' => ['subject' => 'Płatność oczekuje', 'body' => "<h3>Witaj,</h3>\n<p>Informujemy, że dla zamówienia nr <strong>#%order_id%</strong> oczekuje płatność.</p>\n<p>Zaloguj się na swoje konto, aby sprawdzić szczegóły i opłacić zamówienie.</p>\n{$footer}"],
            'order-paid' => ['subject' => 'Płatność została potwierdzona', 'body' => "<h3>Witaj,</h3>\n<p>Informujemy, że płatność do zamówienia nr <strong>#%order_id%</strong> została potwierdzona.</p>\n<p>Zaloguj się na swoje konto, aby sprawdzić szczegóły.</p>\n{$footer}"],
            'order-activated' => ['subject' => 'Twoje zamówienie jest aktywne', 'body' => "<h3>Witaj,</h3>\n<p>Informujemy, że zamówienie nr <strong>#%order_id%</strong> jest aktywne.</p>\n<p>Zaloguj się na swoje konto, aby sprawdzić szczegóły.</p>\n{$footer}"],
            'order-expired' => ['subject' => 'Twoje zamówienie wygasło', 'body' => "<h3>Witaj,</h3>\n<p>Informujemy, że Twoje zamówienie nr <strong>#%order_id%</strong> właśnie wygasło.</p>\n<p>Subskrypcję możesz odnowić w dowolnym czasie.</p>\n<p>Zaloguj się na swoje konto, aby sprawdzić ofertę.</p>\n{$footer}"],
            'live-chat-admin-notify' => ['subject' => 'Masz nową wiadomość [Support]', 'body' => "<h3>Witaj,</h3>\n<p>Klient wysłał nową wiadomość przez Live Chat.</p>\n<p>Login klienta: <strong>%customer_email%</strong></p>\n<p>Przejdź do panelu administracyjnego, aby odpowiedzieć.</p>\n{$footer}"],
            'live-chat-customer-notify' => ['subject' => 'Masz nową wiadomość [Support]', 'body' => "<h3>Witaj,</h3>\n<p>Otrzymałeś nową wiadomość od supportu.</p>\n<p>Zaloguj się na swoje konto, aby sprawdzić Live Chat.</p>\n{$footer}"],
            'news-broadcast' => ['subject' => 'Nowa ważna informacja', 'body' => "<h3>Witaj,</h3>\n<p>W Twoim panelu pojawiła się nowa ważna informacja.</p>\n<p>Zaloguj się na swoje konto, aby ją sprawdzić.</p>\n{$footer}"],
            'account-blocked' => ['subject' => 'Twoje konto zostało zablokowane', 'body' => "<h3>Witaj,</h3>\n<p>Informujemy, że Twoje konto w <strong>%pagename%</strong> zostało zablokowane.</p>\n<p>Jeśli potrzebujesz pomocy, skontaktuj się z nami przez stronę.</p>\n{$footer}"],
            'support-payment-request-notify' => ['subject' => 'Klient rozpoczął płatność [Support]', 'body' => "<h3>Witaj,</h3>\n<p>Klient rozpoczął proces płatności dla zamówienia nr <strong>#%order_id%</strong>.</p>\n<p>Login klienta: <strong>%customer_email%</strong></p>\n<p>Przejdź do panelu administracyjnego, aby sprawdzić szczegóły.</p>\n{$footer}"],
            'reseller-chat-customer-notify' => ['subject' => 'Masz nową wiadomość w messengerze', 'body' => "<h3>Witaj,</h3>\n<p>Otrzymałeś nową wiadomość od <strong>%sender_label%</strong>.</p>\n<p>Rozmowa: <strong>%conversation_title%</strong></p>\n<p>Podgląd wiadomości: <strong>%message_preview%</strong></p>\n<p>Zaloguj się do swojego konta, aby otworzyć messenger.</p>\n{$footer}"],
        ];
    }

    return [
        'account-activation' => ['subject' => 'Your account is active', 'body' => "<h3>Hello,</h3>\n<p>Your account in <strong>%pagename%</strong> is active.</p>\n<p>Log in to your account to start using the panel.</p>\n<p>Login: <strong>%email%</strong></p>\n<p>Login page: <strong>%login_url%</strong></p>\n{$footer}"],
        'password-reset' => ['subject' => 'Your new password', 'body' => "<h3>Hello,</h3>\n<p>Your account in <strong>%pagename%</strong> is active.</p>\n<p>Log in using your new password:<br /><br /></p>\n<p>Login: <strong>%email%</strong></p>\n<p>Password: <strong>%nowehaslo%</strong></p>\n{$footer}"],
        'payment-request-created' => ['subject' => 'Payment is waiting', 'body' => "<h3>Hello,</h3>\n<p>We would like to inform you that payment is waiting for order <strong>#%order_id%</strong>.</p>\n<p>Log in to your account to review the details and complete the payment.</p>\n{$footer}"],
        'order-paid' => ['subject' => 'Payment confirmed', 'body' => "<h3>Hello,</h3>\n<p>We would like to inform you that payment for order <strong>#%order_id%</strong> has been confirmed.</p>\n<p>Log in to your account to review the details.</p>\n{$footer}"],
        'order-activated' => ['subject' => 'Your order is active', 'body' => "<h3>Hello,</h3>\n<p>We would like to inform you that order <strong>#%order_id%</strong> is active.</p>\n<p>Log in to your account to review the details.</p>\n{$footer}"],
        'order-expired' => ['subject' => 'Your order expired', 'body' => "<h3>Hello,</h3>\n<p>We would like to inform you that your order <strong>#%order_id%</strong> has just expired.</p>\n<p>You can renew the subscription at any time.</p>\n<p>Log in to your account to review the available offer.</p>\n{$footer}"],
        'live-chat-admin-notify' => ['subject' => 'You have a new message [Support]', 'body' => "<h3>Hello,</h3>\n<p>A customer sent a new message through Live Chat.</p>\n<p>Customer login: <strong>%customer_email%</strong></p>\n<p>Open the admin panel to reply.</p>\n{$footer}"],
        'live-chat-customer-notify' => ['subject' => 'You have a new message [Support]', 'body' => "<h3>Hello,</h3>\n<p>You received a new message from support.</p>\n<p>Log in to your account to check Live Chat.</p>\n{$footer}"],
        'news-broadcast' => ['subject' => 'New important information', 'body' => "<h3>Hello,</h3>\n<p>A new important information is waiting in your panel.</p>\n<p>Log in to your account to review it.</p>\n{$footer}"],
        'account-blocked' => ['subject' => 'Your account was blocked', 'body' => "<h3>Hello,</h3>\n<p>We would like to inform you that your account in <strong>%pagename%</strong> has been blocked.</p>\n<p>If you need help, contact us through the website.</p>\n{$footer}"],
        'support-payment-request-notify' => ['subject' => 'Customer started payment [Support]', 'body' => "<h3>Hello,</h3>\n<p>A customer started the payment process for order <strong>#%order_id%</strong>.</p>\n<p>Customer login: <strong>%customer_email%</strong></p>\n<p>Open the admin panel to review the details.</p>\n{$footer}"],
        'reseller-chat-customer-notify' => ['subject' => 'You have a new messenger message', 'body' => "<h3>Hello,</h3>\n<p>You received a new message from <strong>%sender_label%</strong>.</p>\n<p>Conversation: <strong>%conversation_title%</strong></p>\n<p>Message preview: <strong>%message_preview%</strong></p>\n<p>Log in to your account to open messenger.</p>\n{$footer}"],
    ];
}

function app_email_standard_footer_html(string $localeCode): string
{
    if (app_normalize_email_locale($localeCode) === 'pl') {
        return "<br />\n<p>Pozdrawiamy</p>\n<p><strong>%pagename%<br /></strong></p>\n<p>%pageurl%</p>\n<p><br /><br />*** Wiadomość została wygenerowana automatycznie. W przypadku dodatkowych pytań zapraszamy do kontaktu na naszej stronie na Live Chat.</p>";
    }

    return "<br />\n<p>Regards</p>\n<p><strong>%pagename%<br /></strong></p>\n<p>%pageurl%</p>\n<p><br /><br />*** This message was generated automatically. If you have any additional questions, please contact us through Live Chat on our website.</p>";
}

function app_email_template_meta(string $templateKey): ?array
{
    $templates = app_email_builtin_templates();
    return isset($templates[$templateKey]) ? $templates[$templateKey] : null;
}

function app_email_template_placeholders(string $templateKey): array
{
    $meta = app_email_template_meta($templateKey);
    if (!is_array($meta) || empty($meta['placeholders']) || !is_array($meta['placeholders'])) {
        return [];
    }

    return array_values(array_unique(array_map(static function ($placeholder): string {
        return trim((string)$placeholder);
    }, $meta['placeholders'])));
}

function app_email_plain_text(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $normalized = str_ireplace(
        ['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>'],
        "\n",
        $value
    );
    $normalized = preg_replace('/<li[^>]*>/i', "- ", $normalized);
    $normalized = preg_replace('/<p[^>]*>/i', '', $normalized);
    $normalized = preg_replace('/<div[^>]*>/i', '', $normalized);
    $normalized = preg_replace('/<[^>]+>/', '', $normalized);
    $normalized = html_entity_decode((string)$normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);
    $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized);

    $lines = array_map(static function ($line): string {
        return rtrim((string)$line);
    }, explode("\n", $normalized));

    return trim(implode("\n", $lines));
}

function app_email_subject(string $value, string $fallback = 'Notification'): string
{
    $value = str_replace(["\r", "\n"], ' ', trim($value));
    $value = preg_replace('/\s+/', ' ', $value);
    $value = trim((string)$value);

    if ($value === '') {
        $value = $fallback;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 190);
    }

    return substr($value, 0, 190);
}

function app_email_site_host(array $settings): string
{
    $siteUrl = trim((string)($settings['site_url'] ?? $settings['page_url'] ?? ''));
    if ($siteUrl !== '') {
        $host = (string)parse_url($siteUrl, PHP_URL_HOST);
        if ($host !== '') {
            return $host;
        }
    }

    $smtpHost = trim((string)($settings['smtp_host'] ?? ''));
    if ($smtpHost !== '' && $smtpHost !== 'xxx.com') {
        return preg_replace('/:\d+$/', '', $smtpHost);
    }

    return isset($_SERVER['HTTP_HOST']) ? trim((string)$_SERVER['HTTP_HOST']) : 'localhost';
}

function app_email_primary_from(array $settings): string
{
    $smtpLogin = trim((string)($settings['smtp_username'] ?? $settings['smtp_login'] ?? ''));
    if ($smtpLogin !== '' && filter_var($smtpLogin, FILTER_VALIDATE_EMAIL)) {
        return strtolower($smtpLogin);
    }

    $supportEmail = trim((string)($settings['support_email'] ?? $settings['admin_email'] ?? ''));
    if ($supportEmail !== '' && filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
        return strtolower($supportEmail);
    }

    return 'no-reply@' . app_email_site_host($settings);
}

function app_email_domain_from_address(string $email): string
{
    $email = strtolower(trim($email));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return '';
    }

    $parts = explode('@', $email);
    $domain = strtolower(trim((string)end($parts)));
    if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
        return '';
    }

    return $domain;
}

function app_email_message_host(array $settings): string
{
    $fromDomain = app_email_domain_from_address(app_email_primary_from($settings));
    if ($fromDomain !== '') {
        return $fromDomain;
    }

    $siteHost = strtolower(trim(app_email_site_host($settings)));
    if ($siteHost !== '' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $siteHost)) {
        return $siteHost;
    }

    return 'localhost';
}

function app_email_support_recipient(array $settings): string
{
    $supportEmail = trim((string)($settings['support_email'] ?? ''));
    if ($supportEmail !== '' && filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
        return strtolower($supportEmail);
    }

    $smtpLogin = trim((string)($settings['smtp_username'] ?? $settings['smtp_login'] ?? ''));
    if ($smtpLogin !== '' && filter_var($smtpLogin, FILTER_VALIDATE_EMAIL)) {
        return strtolower($smtpLogin);
    }

    return '';
}

function app_email_smtp_is_configured(array $settings): bool
{
    $smtpHost = trim((string)($settings['smtp_host'] ?? ''));
    $smtpPort = (int)($settings['smtp_port'] ?? 0);
    $smtpLogin = trim((string)($settings['smtp_username'] ?? $settings['smtp_login'] ?? ''));
    $smtpPassword = trim((string)($settings['smtp_password'] ?? $settings['smtp_haslo'] ?? ''));

    return $smtpHost !== ''
        && $smtpHost !== 'xxx.com'
        && $smtpPort > 0
        && $smtpLogin !== ''
        && $smtpPassword !== '';
}

function app_email_require_phpmailer(): void
{
    if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        return;
    }

    require_once dirname(__DIR__) . '/PHPMailer/PHPMailer/src/Exception.php';
    require_once dirname(__DIR__) . '/PHPMailer/PHPMailer/src/PHPMailer.php';
    require_once dirname(__DIR__) . '/PHPMailer/PHPMailer/src/SMTP.php';
}

function app_email_mailer(array $settings): \PHPMailer\PHPMailer\PHPMailer
{
    app_email_require_phpmailer();

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_OFF;
    $mail->Host = trim((string)($settings['smtp_host'] ?? 'localhost'));
    $mail->Port = (int)($settings['smtp_port'] ?? 465);
    $mail->SMTPAuth = true;
    $mail->Username = trim((string)($settings['smtp_username'] ?? $settings['smtp_login'] ?? ''));
    $mail->Password = trim((string)($settings['smtp_password'] ?? $settings['smtp_haslo'] ?? ''));
    $mail->Timeout = 20;
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'quoted-printable';
    $mail->isHTML(true);
    $mail->ContentType = 'text/html; charset=UTF-8';
    $mail->SMTPAutoTLS = true;

    if ($mail->Port === 465) {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($mail->Port === 587) {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = '';
    }

    $siteName = trim((string)($settings['site_name'] ?? $settings['page_name'] ?? 'Subscription Panel'));
    $fromEmail = app_email_primary_from($settings);
    $messageHost = app_email_message_host($settings);
    $mail->Hostname = $messageHost;
    $mail->Helo = $messageHost;
    $mail->setFrom($fromEmail, $siteName);
    $mail->Sender = $fromEmail;
    $mail->addReplyTo(app_email_support_recipient($settings) ?: $fromEmail, $siteName);
    if ($messageHost !== 'localhost') {
        $mail->MessageID = sprintf('<%s@%s>', bin2hex(random_bytes(16)), $messageHost);
    }
    $mail->addCustomHeader('Auto-Submitted', 'auto-generated');
    $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');
    $mail->addCustomHeader('X-Entity-Ref-ID', bin2hex(random_bytes(10)));

    return $mail;
}

function app_email_placeholder_map(array $replacements): array
{
    $map = [];
    foreach ($replacements as $key => $value) {
        $normalizedKey = trim((string)$key);
        if ($normalizedKey === '') {
            continue;
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (!is_scalar($value) && $value !== null) {
            $value = '';
        }

        $stringValue = app_email_plain_text((string)($value ?? ''));
        $map['{' . $normalizedKey . '}'] = $stringValue;
        $map['{{' . $normalizedKey . '}}'] = $stringValue;
        $map['%' . $normalizedKey . '%'] = $stringValue;
    }

    return $map;
}

function app_email_render(string $templateBody, array $replacements): string
{
    return strtr($templateBody, app_email_placeholder_map($replacements));
}

function app_email_send_with_attachment(
    array $settings,
    string $recipientEmail,
    string $subject,
    string $htmlBody,
    string $attachmentBody,
    string $attachmentFilename,
    string $attachmentMime = 'text/csv; charset=UTF-8'
): array {
    $recipientEmail = strtolower(trim($recipientEmail));
    $subject = trim($subject);
    $htmlBody = trim($htmlBody);
    $attachmentFilename = trim($attachmentFilename);

    if (!app_email_smtp_is_configured($settings)) {
        return ['ok' => false, 'message' => 'SMTP is not configured.'];
    }

    if ($recipientEmail === '' || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'message' => 'Recipient email is invalid.'];
    }

    if ($subject === '' || $attachmentBody === '' || $attachmentFilename === '') {
        return ['ok' => false, 'message' => 'Attachment email payload is incomplete.'];
    }

    try {
        $mail = app_email_mailer($settings);
        $mail->clearAllRecipients();
        $mail->clearAttachments();
        $mail->addAddress($recipientEmail);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody !== '' ? $htmlBody : nl2br(app_email_plain_text($subject));
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $mail->Body)));
        $mail->addStringAttachment($attachmentBody, $attachmentFilename, 'base64', $attachmentMime);
        $mail->send();

        return ['ok' => true, 'message' => 'Attachment email sent.'];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => $exception->getMessage()];
    }
}

function app_send_daily_crypto_backup_report(Mysql_ks $db, string $safeNow): array
{
    $settings = app_fetch_settings($db);
    if (!app_crypto_daily_backup_enabled($settings)) {
        return ['ok' => true, 'sent' => 0, 'rows' => 0, 'message' => 'Daily crypto backup is disabled.'];
    }

    $reportDate = app_crypto_daily_backup_target_date($settings, $safeNow);
    if ($reportDate === '') {
        return ['ok' => true, 'sent' => 0, 'rows' => 0, 'message' => 'Daily crypto backup already processed for the previous day.'];
    }

    $rows = app_crypto_daily_backup_fetch_rows($db, $reportDate);
    if ($rows === []) {
        app_crypto_daily_backup_update_last_processed_date($db, $reportDate);
        return ['ok' => true, 'sent' => 0, 'rows' => 0, 'date' => $reportDate, 'message' => 'No completed crypto payments found for the previous day.'];
    }

    $recipient = app_crypto_daily_backup_recipient($settings);
    if ($recipient === '') {
        return ['ok' => false, 'sent' => 0, 'rows' => count($rows), 'date' => $reportDate, 'message' => 'Daily crypto backup recipient is not configured.'];
    }

    $csv = app_crypto_daily_backup_csv($rows);
    if ($csv === '') {
        return ['ok' => false, 'sent' => 0, 'rows' => count($rows), 'date' => $reportDate, 'message' => 'Unable to build crypto backup CSV.'];
    }

    $siteName = trim((string)($settings['site_name'] ?? $settings['page_name'] ?? 'Subscription Panel'));
    $subject = 'Backup crypto payments - ' . $reportDate . ' - ' . $siteName;
    $body = '<p>W załączniku znajduje się dzienny backup potwierdzonych płatności crypto z dnia <strong>'
        . htmlspecialchars($reportDate, ENT_QUOTES, 'UTF-8')
        . '</strong>.</p><p>Liczba rekordów: <strong>'
        . (int)count($rows)
        . '</strong>.</p>';

    $sendResult = app_email_send_with_attachment(
        $settings,
        $recipient,
        $subject,
        $body,
        $csv,
        app_crypto_daily_backup_filename($reportDate)
    );

    if (empty($sendResult['ok'])) {
        return [
            'ok' => false,
            'sent' => 0,
            'rows' => count($rows),
            'date' => $reportDate,
            'message' => 'Crypto backup email failed: ' . trim((string)($sendResult['message'] ?? 'Unknown error')),
        ];
    }

    app_crypto_daily_backup_update_last_processed_date($db, $reportDate);

    return [
        'ok' => true,
        'sent' => 1,
        'rows' => count($rows),
        'date' => $reportDate,
        'recipient' => $recipient,
        'message' => 'Crypto backup email sent successfully.',
    ];
}

function app_sql_dump_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function app_sql_dump_value(Mysql_ks $db, $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return "'" . $db->escape((string)$value) . "'";
}

function app_sql_dump_objects(Mysql_ks $db): array
{
    $rows = $db->select_full_user('SHOW FULL TABLES');
    $tables = [];
    $views = [];

    foreach ($rows as $row) {
        $values = array_values($row);
        $name = trim((string)($values[0] ?? ''));
        $type = strtoupper(trim((string)($values[1] ?? 'BASE TABLE')));
        if ($name === '') {
            continue;
        }

        if ($type === 'VIEW') {
            $views[] = $name;
        } else {
            $tables[] = $name;
        }
    }

    sort($tables);
    sort($views);

    return ['tables' => $tables, 'views' => $views];
}

function app_sql_dump_insertable_columns(Mysql_ks $db, string $tableName): array
{
    $columns = $db->select_full_user('SHOW FULL COLUMNS FROM ' . app_sql_dump_identifier($tableName));
    $insertable = [];

    foreach ($columns as $column) {
        $field = trim((string)($column['Field'] ?? ''));
        $extra = strtoupper(trim((string)($column['Extra'] ?? '')));
        if ($field === '' || strpos($extra, 'GENERATED') !== false) {
            continue;
        }
        $insertable[] = $field;
    }

    return $insertable;
}

function app_stream_database_sql_backup(Mysql_ks $db, string $downloadFilename): void
{
    @set_time_limit(0);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $objects = app_sql_dump_objects($db);
    $timestamp = date('Y-m-d H:i:s');

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadFilename) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    echo "-- Generated by Subme admin backup\n";
    echo '-- Export time: ' . $timestamp . "\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($objects['tables'] as $tableName) {
        $showCreateRow = $db->select_user('SHOW CREATE TABLE ' . app_sql_dump_identifier($tableName));
        $createSql = (string)($showCreateRow['Create Table'] ?? '');
        if ($createSql === '') {
            continue;
        }

        echo 'DROP TABLE IF EXISTS ' . app_sql_dump_identifier($tableName) . ";\n";
        echo $createSql . ";\n\n";

        $columns = app_sql_dump_insertable_columns($db, $tableName);
        if ($columns === []) {
            continue;
        }

        $selectSql = 'SELECT ' . implode(', ', array_map('app_sql_dump_identifier', $columns))
            . ' FROM ' . app_sql_dump_identifier($tableName);
        $result = $db->query($selectSql);
        if (!$result) {
            echo "\n";
            continue;
        }

        $columnSql = implode(', ', array_map('app_sql_dump_identifier', $columns));
        while ($row = $result->fetch_assoc()) {
            $values = [];
            foreach ($columns as $columnName) {
                $values[] = app_sql_dump_value($db, $row[$columnName] ?? null);
            }

            echo 'INSERT INTO ' . app_sql_dump_identifier($tableName)
                . ' (' . $columnSql . ') VALUES (' . implode(', ', $values) . ");\n";
        }

        $result->free();
        echo "\n";
    }

    foreach ($objects['views'] as $viewName) {
        $showCreateRow = $db->select_user('SHOW CREATE VIEW ' . app_sql_dump_identifier($viewName));
        $createSql = (string)($showCreateRow['Create View'] ?? '');
        if ($createSql === '') {
            continue;
        }

        $createSql = preg_replace('/\s+DEFINER=`[^`]+`@`[^`]+`\s+/i', ' ', $createSql);
        $createSql = preg_replace('/\s+SQL SECURITY DEFINER\s+/i', ' SQL SECURITY INVOKER ', (string)$createSql);

        echo 'DROP VIEW IF EXISTS ' . app_sql_dump_identifier($viewName) . ";\n";
        echo trim((string)$createSql) . ";\n\n";
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}

function app_update_manual_database_backup_timestamp(Mysql_ks $db, ?string $timestamp = null): void
{
    if (!schema_object_exists($db, 'app_settings') || !schema_column_exists($db, 'app_settings', 'manual_database_backup_last_downloaded_at')) {
        return;
    }

    $timestamp = trim((string)$timestamp);
    if ($timestamp === '') {
        $timestamp = date('Y-m-d H:i:s');
    }

    $db->update_using_id(
        ['manual_database_backup_last_downloaded_at'],
        [$timestamp],
        'app_settings',
        1
    );
}

function app_ensure_email_template_runtime_rows(Mysql_ks $db): void
{
    static $done = false;

    if ($done || !schema_object_exists($db, 'email_templates')) {
        return;
    }

    $done = true;
    app_sync_builtin_email_templates($db, false);
}

function app_ensure_email_template_runtime_translations(Mysql_ks $db): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;
    app_ensure_email_template_runtime_rows($db);

    if (!schema_object_exists($db, 'email_template_translations')) {
        @$db->query(
            "CREATE TABLE IF NOT EXISTS email_template_translations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                template_id INT UNSIGNED NOT NULL,
                locale_code VARCHAR(5) NOT NULL,
                subject VARCHAR(191) NOT NULL,
                body_text LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_email_template_translations_template_locale (template_id, locale_code),
                CONSTRAINT fk_email_template_translations_template FOREIGN KEY (template_id) REFERENCES email_templates (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        unset($GLOBALS['schema_object_exists_cache']['email_template_translations']);
    }

    if (!schema_object_exists($db, 'email_template_translations')) {
        return;
    }

    app_sync_builtin_email_templates_if_needed($db);
    app_sync_builtin_email_templates($db, false);
}

function app_email_template_sync_flag_path(): string
{
    return dirname(__DIR__) . '/templates_c/.email_templates_sync_20260425_notifications.flag';
}

function app_customer_notification_storage_column(Mysql_ks $db): string
{
    static $cache = [];

    $cacheKey = spl_object_hash($db);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    if (app_uses_v2_schema($db)) {
        if (schema_column_exists($db, 'customers', 'email_notification')) {
            return $cache[$cacheKey] = 'email_notification';
        }
        if (schema_column_exists($db, 'customers', 'is_newsletter_subscribed')) {
            return $cache[$cacheKey] = 'is_newsletter_subscribed';
        }

        return $cache[$cacheKey] = '';
    }

    if (schema_column_exists($db, 'users', 'email_notification')) {
        return $cache[$cacheKey] = 'email_notification';
    }
    if (schema_column_exists($db, 'users', 'is_newsletter_subscribed')) {
        return $cache[$cacheKey] = 'is_newsletter_subscribed';
    }

    return $cache[$cacheKey] = '';
}

function app_customer_notification_select_sql(Mysql_ks $db, string $tableAlias = ''): string
{
    $column = app_customer_notification_storage_column($db);
    if ($column === '') {
        return '1 AS email_notification, 1 AS is_newsletter_subscribed';
    }

    $prefix = trim($tableAlias) !== '' ? rtrim($tableAlias, '.') . '.' : '';
    return "{$prefix}{$column} AS email_notification, {$prefix}{$column} AS is_newsletter_subscribed";
}

function app_customer_email_notifications_enabled(array $customer): bool
{
    if (array_key_exists('email_notification', $customer)) {
        if ($customer['email_notification'] === null || $customer['email_notification'] === '') {
            return true;
        }

        return (int)$customer['email_notification'] !== 0;
    }

    if (array_key_exists('is_newsletter_subscribed', $customer)) {
        if ($customer['is_newsletter_subscribed'] === null || $customer['is_newsletter_subscribed'] === '') {
            return true;
        }

        return (int)$customer['is_newsletter_subscribed'] !== 0;
    }

    return true;
}

function app_normalize_customer_record(array $customer): array
{
    if (!$customer) {
        return [];
    }

    $emailNotification = app_customer_email_notifications_enabled($customer) ? 1 : 0;
    $customer['email_notification'] = $emailNotification;
    $customer['is_newsletter_subscribed'] = $emailNotification;
    $customer['customer_type'] = app_normalize_customer_type($customer['customer_type'] ?? '');
    $customer['is_reseller'] = $customer['customer_type'] === 'reseller' ? 1 : 0;
    $customer['storefront_product_type'] = app_customer_product_type($customer);

    return $customer;
}

function app_update_customer_email_notification(Mysql_ks $db, int $customerId, bool $enabled): bool
{
    if ($customerId <= 0) {
        return false;
    }

    $column = app_customer_notification_storage_column($db);
    if ($column === '') {
        return false;
    }

    $tableName = app_uses_v2_schema($db) ? 'customers' : 'users';
    return (bool)$db->update_using_id([$column], [$enabled ? 1 : 0], $tableName, $customerId);
}

function app_email_template_respects_customer_notification_preference(string $templateKey): bool
{
    return !in_array($templateKey, [
        'account-activation',
        'password-reset',
        'live-chat-admin-notify',
        'support-payment-request-notify',
    ], true);
}

function app_sync_builtin_email_templates_if_needed(Mysql_ks $db): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;
    $flagPath = app_email_template_sync_flag_path();
    if (is_file($flagPath)) {
        return;
    }

    app_sync_builtin_email_templates($db, true);
    @file_put_contents($flagPath, date('c'));
}

function app_sync_builtin_email_templates(Mysql_ks $db, bool $overwriteExisting = false): void
{
    if (!schema_object_exists($db, 'email_templates')) {
        return;
    }

    foreach (app_email_builtin_templates() as $templateKey => $meta) {
        if (!in_array($templateKey, app_email_active_template_keys(), true)) {
            continue;
        }

        $safeKey = $db->escape($templateKey);
        $existing = $db->select_user(
            "SELECT id
             FROM email_templates
             WHERE template_key = '{$safeKey}'
             LIMIT 1"
        );

        if (is_array($existing) && !empty($existing['id'])) {
            $templateId = (int)$existing['id'];
            if ($overwriteExisting) {
                $db->update_using_id(
                    ['name', 'subject', 'body_html', 'is_system'],
                    [
                        (string)($meta['name'] ?? $templateKey),
                        (string)($meta['subject'] ?? 'Notification'),
                        (string)($meta['body'] ?? ''),
                        1,
                    ],
                    'email_templates',
                    $templateId
                );
            }
        } else {
            $db->insert(
                ['template_key', 'name', 'subject', 'body_html', 'is_system', 'is_active'],
                [
                    $templateKey,
                    (string)($meta['name'] ?? $templateKey),
                    (string)($meta['subject'] ?? 'Notification'),
                    (string)($meta['body'] ?? ''),
                    1,
                    1,
                ],
                'email_templates'
            );
            $templateId = (int)$db->id();
        }

        if ($templateId <= 0 || !schema_object_exists($db, 'email_template_translations')) {
            continue;
        }

        foreach (app_supported_email_locales() as $localeCode) {
            $localized = app_email_builtin_templates_localized($localeCode);
            $localizedMeta = $localized[$templateKey] ?? null;
            if (!is_array($localizedMeta)) {
                continue;
            }

            $safeLocale = $db->escape($localeCode);
            $existingTranslation = $db->select_user(
                "SELECT id
                 FROM email_template_translations
                 WHERE template_id = {$templateId}
                   AND locale_code = '{$safeLocale}'
                 LIMIT 1"
            );

            if (is_array($existingTranslation) && !empty($existingTranslation['id'])) {
                if ($overwriteExisting) {
                    $db->update_using_id(
                        ['subject', 'body_text'],
                        [(string)($localizedMeta['subject'] ?? ''), (string)($localizedMeta['body'] ?? '')],
                        'email_template_translations',
                        (int)$existingTranslation['id']
                    );
                }
                continue;
            }

            $db->insert(
                ['template_id', 'locale_code', 'subject', 'body_text'],
                [$templateId, $localeCode, (string)($localizedMeta['subject'] ?? ''), (string)($localizedMeta['body'] ?? '')],
                'email_template_translations'
            );
        }
    }
}

function app_email_template_translation_row(Mysql_ks $db, int $templateId, string $localeCode): ?array
{
    if ($templateId <= 0) {
        return null;
    }

    app_ensure_email_template_runtime_translations($db);
    if (!schema_object_exists($db, 'email_template_translations')) {
        return null;
    }

    $localeCode = app_normalize_email_locale($localeCode);
    $safeLocale = $db->escape($localeCode);
    $row = $db->select_user(
        "SELECT id, template_id, locale_code, subject, body_text, updated_at
         FROM email_template_translations
         WHERE template_id = {$templateId}
           AND locale_code = '{$safeLocale}'
         LIMIT 1"
    );

    return is_array($row) && !empty($row['id']) ? $row : null;
}

function app_email_template_row(Mysql_ks $db, string $templateKey): ?array
{
    if ($templateKey === '' || !schema_object_exists($db, 'email_templates')) {
        return null;
    }

    app_ensure_email_template_runtime_rows($db);
    $safeKey = $db->escape($templateKey);
    $row = $db->select_user(
        "SELECT id, template_key, name, subject, body_html, is_system, is_active
         FROM email_templates
         WHERE template_key = '{$safeKey}'
         LIMIT 1"
    );

    if (!is_array($row) || empty($row['id'])) {
        return null;
    }

    return $row;
}

function app_email_recent_duplicate_exists(
    Mysql_ks $db,
    int $templateId,
    string $toEmail,
    ?int $customerId = null,
    ?int $orderId = null,
    int $windowSeconds = 180
): bool {
    if ($templateId <= 0 || $toEmail === '' || !schema_object_exists($db, 'outbound_email_queue')) {
        return false;
    }

    $safeEmail = $db->escape(strtolower($toEmail));
    $customerClause = $customerId !== null ? 'customer_id = ' . (int)$customerId : 'customer_id IS NULL';
    $orderClause = $orderId !== null ? 'order_id = ' . (int)$orderId : 'order_id IS NULL';
    $cutoff = $db->escape(date('Y-m-d H:i:s', time() - max(30, $windowSeconds)));

    $row = $db->select_user(
        "SELECT id
         FROM outbound_email_queue
         WHERE template_id = {$templateId}
           AND to_email = '{$safeEmail}'
           AND {$customerClause}
           AND {$orderClause}
           AND status IN ('pending', 'processing', 'sent')
           AND created_at >= '{$cutoff}'
         LIMIT 1"
    );

    return is_array($row) && !empty($row['id']);
}

function app_email_queue_template(
    Mysql_ks $db,
    string $templateKey,
    string $toEmail,
    array $replacements = [],
    ?int $customerId = null,
    ?int $orderId = null,
    int $dedupeWindowSeconds = 0,
    bool $deliverNow = true,
    ?string $localeCode = null
): array {
    $toEmail = strtolower(trim($toEmail));
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Recipient email is invalid.', 'queued' => false];
    }

    if (!schema_object_exists($db, 'outbound_email_queue')) {
        return ['ok' => false, 'message' => 'Outbound email queue table is missing.', 'queued' => false];
    }

    $template = app_email_template_row($db, $templateKey);
    if (!is_array($template) || empty($template['id']) || empty($template['is_active'])) {
        return ['ok' => false, 'message' => 'Email template is missing or disabled.', 'queued' => false];
    }

    $templateId = (int)$template['id'];

    $settings = app_fetch_settings($db);
    $customer = null;
    if ($customerId !== null && $customerId > 0) {
        $customer = app_find_customer_by_id($db, $customerId);
    }

    if (
        $customerId !== null
        && $customerId > 0
        && is_array($customer)
        && app_email_template_respects_customer_notification_preference($templateKey)
        && !app_customer_email_notifications_enabled($customer)
    ) {
        return ['ok' => true, 'message' => 'Customer disabled email notifications.', 'queued' => false, 'skipped' => true];
    }

    if ($localeCode === null || trim($localeCode) === '') {
        if (is_array($customer)) {
            $localeCode = (string)($customer['locale_code'] ?? '');
        }

        if ($localeCode === null || trim($localeCode) === '') {
            $localeCode = (string)($settings['default_locale_code'] ?? 'en');
        }
    }

    $localeCode = app_normalize_email_locale($localeCode);
    $siteName = trim((string)($settings['site_name'] ?? $settings['page_name'] ?? 'Subscription Panel'));
    $siteUrl = trim((string)($settings['site_url'] ?? $settings['page_url'] ?? ''));
    $baseReplacements = array_merge([
        'site_name' => $siteName,
        'site_url' => $siteUrl,
        'page_name' => $siteName,
        'page_url' => $siteUrl,
        'pagename' => $siteName,
        'pageurl' => $siteUrl,
        'support_email' => app_email_support_recipient($settings),
        'supportemail' => app_email_support_recipient($settings),
        'email_locale' => $localeCode,
        'email' => $toEmail,
    ], $replacements);

    if (!isset($baseReplacements['customer_email']) || trim((string)$baseReplacements['customer_email']) === '') {
        $baseReplacements['customer_email'] = $toEmail;
    }
    if (!isset($baseReplacements['password']) && isset($baseReplacements['nowehaslo'])) {
        $baseReplacements['password'] = (string)$baseReplacements['nowehaslo'];
    }
    if (!isset($baseReplacements['nowehaslo']) && isset($baseReplacements['password'])) {
        $baseReplacements['nowehaslo'] = (string)$baseReplacements['password'];
    }
    if ($orderId !== null && $orderId > 0) {
        if (!isset($baseReplacements['order_id'])) {
            $baseReplacements['order_id'] = $orderId;
        }
        if (!isset($baseReplacements['id_zamowienia'])) {
            $baseReplacements['id_zamowienia'] = $orderId;
        }
    }

    $translation = app_email_template_translation_row($db, $templateId, $localeCode);
    $subjectTemplate = is_array($translation) && trim((string)($translation['subject'] ?? '')) !== ''
        ? (string)$translation['subject']
        : (string)($template['subject'] ?? 'Notification');
    $bodyTemplate = is_array($translation) && trim((string)($translation['body_text'] ?? '')) !== ''
        ? (string)$translation['body_text']
        : (string)($template['body_html'] ?? '');

    $subject = app_email_subject(
        app_email_render($subjectTemplate, $baseReplacements),
        $subjectTemplate
    );
    $body = app_email_render($bodyTemplate, $baseReplacements);

    if (app_email_plain_text($body) === '') {
        return ['ok' => false, 'message' => 'Email body is empty.', 'queued' => false];
    }

    if ($dedupeWindowSeconds > 0 && app_email_recent_duplicate_exists($db, $templateId, $toEmail, $customerId, $orderId, $dedupeWindowSeconds)) {
        return ['ok' => true, 'message' => 'Skipped duplicate email notification.', 'queued' => false, 'skipped_duplicate' => true];
    }

    $smtpConfigured = app_email_smtp_is_configured($settings);
    $status = $smtpConfigured ? 'pending' : 'failed';
    $lastError = $smtpConfigured ? null : 'SMTP is not configured.';

    $queued = $db->insert(
        ['template_id', 'customer_id', 'order_id', 'to_email', 'subject', 'body_html', 'status', 'attempt_count', 'last_error', 'scheduled_at'],
        [$templateId, $customerId, $orderId, $toEmail, $subject, $body, $status, 0, $lastError, date('Y-m-d H:i:s')],
        'outbound_email_queue'
    );

    $queueId = $queued ? (int)$db->id() : 0;
    if (!$queued || $queueId <= 0) {
        return ['ok' => false, 'message' => 'Unable to queue email notification.', 'queued' => false];
    }

    if ($deliverNow && $smtpConfigured) {
        app_email_process_queue($db, 1, $queueId);
    }

    return ['ok' => true, 'message' => 'Email queued.', 'queued' => true, 'queue_id' => $queueId];
}

function app_email_process_queue(Mysql_ks $db, int $limit = 10, ?int $forcedQueueId = null): array
{
    $limit = max(1, min(50, $limit));
    if (!schema_object_exists($db, 'outbound_email_queue')) {
        return ['ok' => false, 'processed' => 0, 'sent' => 0, 'failed' => 0, 'message' => 'Outbound email queue table is missing.'];
    }

    $settings = app_fetch_settings($db);
    if (!app_email_smtp_is_configured($settings)) {
        return ['ok' => false, 'processed' => 0, 'sent' => 0, 'failed' => 0, 'message' => 'SMTP is not configured.'];
    }

    $safeNow = $db->escape(date('Y-m-d H:i:s'));
    $where = $forcedQueueId !== null
        ? 'outbound_email_queue.id = ' . (int)$forcedQueueId
        : "outbound_email_queue.status IN ('pending', 'failed')
           AND outbound_email_queue.attempt_count < 5
           AND (outbound_email_queue.scheduled_at IS NULL OR outbound_email_queue.scheduled_at <= '{$safeNow}')";

    $rows = $db->select_full_user(
        "SELECT id, to_email, subject, body_html, status, attempt_count
         FROM outbound_email_queue
         WHERE {$where}
         ORDER BY id ASC
         LIMIT {$limit}"
    );

    if (!$rows) {
        return ['ok' => true, 'processed' => 0, 'sent' => 0, 'failed' => 0, 'message' => 'No outbound emails waiting.'];
    }

    $processed = 0;
    $sent = 0;
    $failed = 0;

    foreach ($rows as $row) {
        $queueId = (int)($row['id'] ?? 0);
        if ($queueId <= 0) {
            continue;
        }

        $claimResult = $db->query(
            "UPDATE outbound_email_queue
             SET status = 'processing'
             WHERE id = {$queueId}
               AND status IN ('pending', 'failed')
             LIMIT 1"
        );

        if (!$claimResult || (int)$db->affected_rows <= 0) {
            continue;
        }

        $processed++;

        try {
            $mail = app_email_mailer($settings);
            $mail->addAddress((string)$row['to_email']);
            $mail->Subject = app_email_subject((string)($row['subject'] ?? 'Notification'));
            $mail->Body = (string)($row['body_html'] ?? '');
            $mail->AltBody = app_email_plain_text((string)($row['body_html'] ?? ''));
            $mail->send();

            $db->update_using_id(
                ['status', 'sent_at', 'last_error'],
                ['sent', date('Y-m-d H:i:s'), null],
                'outbound_email_queue',
                $queueId
            );
            $sent++;
        } catch (\Throwable $exception) {
            $attemptCount = (int)($row['attempt_count'] ?? 0) + 1;
            $nextStatus = $attemptCount >= 5 ? 'failed' : 'pending';
            $nextScheduledAt = $attemptCount >= 5
                ? date('Y-m-d H:i:s')
                : date('Y-m-d H:i:s', time() + 300);
            $db->update_using_id(
                ['status', 'attempt_count', 'last_error', 'scheduled_at'],
                [$nextStatus, $attemptCount, app_email_plain_text($exception->getMessage()), $nextScheduledAt],
                'outbound_email_queue',
                $queueId
            );
            $failed++;
        }
    }

    return [
        'ok' => true,
        'processed' => $processed,
        'sent' => $sent,
        'failed' => $failed,
        'message' => $processed > 0 ? 'Processed outbound email queue.' : 'No outbound emails waiting.',
    ];
}

function app_contact_form_enabled(array $settings): bool
{
    if (array_key_exists('contact_form_enabled', $settings)) {
        return !empty($settings['contact_form_enabled']);
    }

    return true;
}

function app_referrals_enabled(array $settings): bool
{
    if (array_key_exists('referrals_enabled', $settings)) {
        return !empty($settings['referrals_enabled']);
    }

    if (array_key_exists('active_referrals', $settings)) {
        return !empty($settings['active_referrals']);
    }

    return true;
}

function app_apps_page_enabled(array $settings): bool
{
    if (array_key_exists('apps_page_enabled', $settings)) {
        return !empty($settings['apps_page_enabled']);
    }

    return true;
}

function app_application_instructions_enabled(array $settings): bool
{
    if (array_key_exists('application_instructions_enabled', $settings)) {
        return !empty($settings['application_instructions_enabled']);
    }

    return true;
}

function app_page_guidance_enabled(array $settings): bool
{
    if (array_key_exists('page_guidance_enabled', $settings)) {
        return !empty($settings['page_guidance_enabled']);
    }

    return true;
}

function app_payment_test_mode_notice_enabled(array $settings): bool
{
    if (array_key_exists('payment_test_mode_notice_enabled', $settings)) {
        return !empty($settings['payment_test_mode_notice_enabled']);
    }

    return false;
}

function app_page_helper_content(string $site, bool $isLoggedIn = false): array
{
    $site = trim($site);
    if ($site === '') {
        $site = 'homepage';
    }

    $default = [
        'title' => 'Jak korzystać z tej podstrony?',
        'items' => [
            'Ta podstrona pokazuje najważniejsze informacje związane z bieżącym etapem pracy w panelu.',
            'Najlepiej przejdź od góry do dołu i wykonuj tylko te kroki, które pasują do Twojej sytuacji.',
            'Jeżeli widzisz przycisk akcji, zwykle prowadzi on do następnego logicznego etapu, a nie do dodatkowej konfiguracji technicznej.',
            'W razie wątpliwości sprawdź komunikaty o statusie, bo to one podpowiadają, czy coś jest aktywne, oczekujące albo wymaga Twojej reakcji.',
            'Jeśli nie masz pewności co zrobić dalej, wróć do strony głównej albo do zamówień, bo tam najłatwiej zobaczysz cały stan konta.',
        ],
    ];

    $map = [
        'homepage_logged' => [
            'title' => 'Co możesz zrobić ze strony głównej?',
            'items' => [
                'To jest główny pulpit klienta, z którego szybko przejdziesz do zamówień, płatności, historii i ustawień konta.',
                'W górnej części widzisz stan salda, więc od razu wiesz, czy możesz wykorzystać środki do dalszych zakupów lub odnowień.',
                'Każdy kafelek prowadzi do konkretnej funkcji, dlatego nie musisz znać technicznych nazw usług, żeby poruszać się po panelu.',
                'Jeżeli masz sprawę do supportu, z tego miejsca łatwo wejdziesz do wiadomości, instrukcji albo sekcji z aplikacjami.',
                'Najprościej traktować tę stronę jako centrum sterowania, z którego zaczynasz każdą ważniejszą czynność na koncie.',
            ],
        ],
        'homepage_guest' => [
            'title' => 'Co może zrobić nowy użytkownik na stronie głównej?',
            'items' => [
                'Ta strona pokazuje podstawowe wejścia do serwisu, więc nowa osoba może od razu wybrać logowanie, rejestrację albo kontakt.',
                'Jeśli nie masz jeszcze konta, najwygodniej zacząć od rejestracji, bo dopiero po zalogowaniu zobaczysz pełny panel klienta.',
                'Jeżeli chcesz najpierw zadać pytanie, przycisk kontaktu prowadzi do formularza, z którego możesz wysłać wiadomość do obsługi.',
                'Strona główna nie wymaga wiedzy technicznej, bo jej celem jest szybkie skierowanie Cię do właściwej akcji bez zbędnych kroków.',
                'W praktyce jest to wizytówka systemu i punkt startowy dla osoby, która chce ocenić, czy panel odpowiada jej potrzebom.',
            ],
        ],
        'news' => [
            'title' => 'Jak czytać aktualności?',
            'items' => [
                'Tutaj pojawiają się ważne komunikaty od operatora, dlatego warto zaglądać w to miejsce przed zgłoszeniem problemu do supportu.',
                'Możesz sprawdzić informacje o przerwach technicznych, zmianach w ofercie, nowych funkcjach albo planowanych pracach serwisowych.',
                'Jeśli coś w usłudze działa inaczej niż zwykle, aktualności często wyjaśniają, czy jest to chwilowa sytuacja, czy stała zmiana.',
                'Treści są podane w prostszej formie niż w systemach administracyjnych, więc mają być zrozumiałe także dla nietechnicznego użytkownika.',
                'Najlepiej traktować tę sekcję jako oficjalne źródło bieżących informacji dotyczących działania całego serwisu.',
            ],
        ],
        'apps' => [
            'title' => 'Do czego służy sekcja aplikacji?',
            'items' => [
                'Ta podstrona pomaga dobrać aplikację lub środowisko odtwarzania do urządzenia, z którego chcesz korzystać.',
                'Znajdziesz tu skróty i wskazówki, które ułatwiają przejście od aktywnej usługi do realnego uruchomienia jej na telewizorze, boxie albo telefonie.',
                'Jeśli nie wiesz, od czego zacząć po zakupie, właśnie tutaj zwykle powinieneś sprawdzić, jaka aplikacja będzie dla Ciebie najwygodniejsza.',
                'Sekcja nie musi wszystkiego konfigurować automatycznie, ale porządkuje kolejne kroki i ogranicza liczbę pytań do supportu.',
                'Dla laika to praktyczna mapa, która łączy zakupioną usługę z konkretnym sposobem użycia jej na własnym sprzęcie.',
            ],
        ],
        'settings' => [
            'title' => 'Co zmienisz w ustawieniach konta?',
            'items' => [
                'Tutaj zarządzasz danymi swojego profilu, więc możesz utrzymać konto w aktualnym i bezpiecznym stanie.',
                'To dobre miejsce do sprawdzenia podstawowych informacji, które wpływają na logowanie, kontakt i wygodę korzystania z panelu.',
                'Zmiany zapisane w tej sekcji dotyczą Twojego konta, dlatego nie wpływają na innych użytkowników ani na konfigurację całego serwisu.',
                'Jeżeli chcesz uporządkować sposób otrzymywania informacji albo przygotować konto do dalszych zakupów, zacznij właśnie od ustawień.',
                'Najprościej mówiąc, jest to panel osobisty, w którym dopasowujesz konto do własnych potrzeb bez ingerowania w ofertę usług.',
            ],
        ],
        'change-password' => [
            'title' => 'Po co jest zmiana hasła?',
            'items' => [
                'Ta podstrona służy do ręcznej zmiany hasła wtedy, gdy chcesz zwiększyć bezpieczeństwo swojego konta.',
                'Najlepiej ustawić hasło, które jest łatwe do zapamiętania dla Ciebie, ale trudne do odgadnięcia dla innej osoby.',
                'Po zmianie hasła nowe dane logowania zaczynają obowiązywać od razu, więc warto upewnić się, że zapisujesz je bez literówek.',
                'Jeżeli z konta korzystasz tylko Ty, regularna zmiana hasła zmniejsza ryzyko nieautoryzowanego dostępu do zamówień i płatności.',
                'To prosta czynność administracyjna, ale ma duży wpływ na bezpieczeństwo całej historii Twojego konta.',
            ],
        ],
        'orders' => [
            'title' => 'Jak działa sekcja zamówień?',
            'items' => [
                'To najważniejsza podstrona operacyjna, bo właśnie tutaj tworzysz nowe zamówienia, sprawdzasz aktywne usługi i wracasz do płatności.',
                'Każde zamówienie pokazuje swój status, dzięki czemu od razu widzisz, czy usługa czeka na płatność, jest aktywna, czy wymaga przedłużenia.',
                'Jeżeli chcesz kupić nową subskrypcję albo odnowić obecną, zwykle cały proces zaczyna się i kończy właśnie w tym miejscu.',
                'W tej sekcji możesz też wrócić do rozpoczętego zamówienia bez szukania linków w wiadomościach lub historii przeglądarki.',
                'Dla laika jest to po prostu lista wszystkich spraw zakupowych związanych z kontem, uporządkowana w jednym miejscu.',
            ],
        ],
        'cryptocurrency' => [
            'title' => 'Jak działa sekcja płatności krypto?',
            'items' => [
                'Tutaj tworzysz i śledzisz płatności kryptowalutowe, więc w jednym miejscu widzisz adres portfela, kwotę i czas ważności prośby o płatność.',
                'Jeśli masz otwarte żądanie płatności, ta strona pozwala wrócić do instrukcji bez generowania wszystkiego od początku.',
                'Dla osoby nietechnicznej najważniejsze jest to, aby wysłać dokładnie taką kwotę, jaka została pokazana przy aktywnej płatności.',
                'Sekcja porządkuje też historię rozpoczętych prób płatności, więc łatwiej ustalić, która prośba jest aktualna, a która wygasła.',
                'Najprościej traktować ją jako centrum obsługi płatności krypto, a nie tylko jednorazowy ekran z adresem portfela.',
            ],
        ],
        'payments_crypto' => [
            'title' => 'Jak działa sekcja płatności krypto?',
            'items' => [
                'Tutaj tworzysz i śledzisz płatności kryptowalutowe, więc w jednym miejscu widzisz adres portfela, kwotę i czas ważności prośby o płatność.',
                'Jeśli masz otwarte żądanie płatności, ta strona pozwala wrócić do instrukcji bez generowania wszystkiego od początku.',
                'Dla osoby nietechnicznej najważniejsze jest to, aby wysłać dokładnie taką kwotę, jaka została pokazana przy aktywnej płatności.',
                'Sekcja porządkuje też historię rozpoczętych prób płatności, więc łatwiej ustalić, która prośba jest aktualna, a która wygasła.',
                'Najprościej traktować ją jako centrum obsługi płatności krypto, a nie tylko jednorazowy ekran z adresem portfela.',
            ],
        ],
        'referrals' => [
            'title' => 'Do czego służą polecenia?',
            'items' => [
                'Ta podstrona pokazuje funkcję poleceń, czyli możliwość zapraszania nowych osób do serwisu przez własny link lub identyfikator.',
                'Jeżeli program poleceń jest aktywny, tutaj najłatwiej sprawdzisz, czy ktoś zarejestrował się z Twojego polecenia i jaki ma status.',
                'Sekcja jest przydatna szczególnie wtedy, gdy chcesz rozwijać bazę klientów albo po prostu polecać usługę znajomym w uporządkowany sposób.',
                'Dla laika najważniejsze jest to, że wszystko jest spięte z kontem, więc nie musisz ręcznie zapamiętywać, kogo zaprosiłeś.',
                'To narzędzie marketingowe w prostym wydaniu, które pozwala połączyć promocję serwisu z realnym śledzeniem efektów.',
            ],
        ],
        'history' => [
            'title' => 'Co sprawdzisz w historii?',
            'items' => [
                'Ta sekcja zbiera wcześniejsze działania na koncie, więc pomaga zrozumieć, co i kiedy zostało wykonane.',
                'Możesz tu wrócić do starszych zdarzeń związanych z zamówieniami, komunikacją albo zmianami statusów bez zgadywania dat.',
                'Jeżeli próbujesz wyjaśnić, dlaczego usługa ma określony stan, historia często pokazuje kolejność zdarzeń prowadzących do tego efektu.',
                'Dla laika jest to po prostu dziennik aktywności, który pozwala spokojnie odtworzyć przebieg wcześniejszych działań.',
                'To dobre miejsce do samodzielnego sprawdzenia kontekstu, zanim napiszesz do supportu z pytaniem o konkretną zmianę.',
            ],
        ],
        'how-to-pay' => [
            'title' => 'Jak korzystać z instrukcji płatności?',
            'items' => [
                'Ta podstrona wyjaśnia dostępne sposoby płatności i pomaga dobrać metodę, która będzie dla Ciebie najwygodniejsza.',
                'Jeżeli nie jesteś pewien, czym różni się przelew bankowy od płatności krypto, tutaj zobaczysz to w prostszej formie.',
                'Instrukcje są po to, aby ograniczyć błędy przy płatności, zwłaszcza w sytuacji, gdy liczy się dokładna kwota albo poprawny tytuł operacji.',
                'Dla początkującej osoby ta sekcja działa jak przewodnik krok po kroku przed wykonaniem realnej płatności.',
                'W praktyce najlepiej zajrzeć tutaj przed opłaceniem pierwszego zamówienia lub wtedy, gdy chcesz zmienić dotychczasową metodę.',
            ],
        ],
        'faq' => [
            'title' => 'Jak korzystać z FAQ?',
            'items' => [
                'FAQ zbiera najczęstsze pytania i odpowiedzi, więc często pozwala rozwiązać problem bez czekania na odpowiedź supportu.',
                'To dobre miejsce na szybkie wyjaśnienie podstaw działania serwisu, płatności, aktywacji i sposobu korzystania z usługi.',
                'Jeżeli dopiero poznajesz panel, ta sekcja oszczędza czas, bo odpowiada na pytania, które zwykle pojawiają się na początku.',
                'Dla laika FAQ działa jak uporządkowana instrukcja pytań i odpowiedzi, a nie jak techniczna dokumentacja.',
                'Najwygodniej zaglądać tutaj zawsze wtedy, gdy masz wątpliwość, ale nie chcesz od razu otwierać nowej rozmowy z supportem.',
            ],
        ],
        'instructions' => [
            'title' => 'Co znajdziesz w instrukcjach?',
            'items' => [
                'Ta sekcja porządkuje praktyczne poradniki związane z uruchomieniem usługi na konkretnych aplikacjach i urządzeniach.',
                'Możesz przejść do wybranego poradnika bez szukania informacji na zewnętrznych stronach lub w przypadkowych filmach.',
                'Instrukcje są napisane tak, aby osoba bez dużego doświadczenia mogła krok po kroku przejść przez konfigurację.',
                'Jeśli support prosi Cię o wykonanie określonych działań w aplikacji, zwykle właśnie tutaj znajdziesz potrzebny kontekst.',
                'Najprościej patrzeć na tę stronę jak na bibliotekę gotowych przewodników do najczęściej używanych scenariuszy.',
            ],
        ],
        'instruction-trust-wallet' => [
            'title' => 'Jak czytać ten poradnik aplikacji?',
            'items' => [
                'Ten ekran skupia się na jednej konkretnej aplikacji lub jednym scenariuszu, żeby nie mieszać wielu metod w jednym miejscu.',
                'Najlepiej wykonuj kroki dokładnie po kolei, bo poradnik jest ułożony od podstaw do efektu końcowego.',
                'Jeżeli na którymś etapie pojawi się inny widok niż w instrukcji, warto wrócić do początku i sprawdzić, czy wcześniejszy krok był poprawny.',
                'Dla laika najważniejsze jest to, że nie trzeba znać całej technologii, wystarczy odwzorować pokazany proces.',
                'Ta podstrona ma zmniejszyć liczbę pomyłek i skrócić czas potrzebny do samodzielnej konfiguracji usługi.',
            ],
        ],
        'instruction-revolut' => [
            'title' => 'Jak czytać ten poradnik aplikacji?',
            'items' => [
                'Ten ekran skupia się na jednej konkretnej aplikacji lub jednym scenariuszu, żeby nie mieszać wielu metod w jednym miejscu.',
                'Najlepiej wykonuj kroki dokładnie po kolei, bo poradnik jest ułożony od podstaw do efektu końcowego.',
                'Jeżeli na którymś etapie pojawi się inny widok niż w instrukcji, warto wrócić do początku i sprawdzić, czy wcześniejszy krok był poprawny.',
                'Dla laika najważniejsze jest to, że nie trzeba znać całej technologii, wystarczy odwzorować pokazany proces.',
                'Ta podstrona ma zmniejszyć liczbę pomyłek i skrócić czas potrzebny do samodzielnej konfiguracji usługi.',
            ],
        ],
        'instruction-crypto-exchange' => [
            'title' => 'Jak czytać ten poradnik aplikacji?',
            'items' => [
                'Ten ekran skupia się na jednej konkretnej aplikacji lub jednym scenariuszu, żeby nie mieszać wielu metod w jednym miejscu.',
                'Najlepiej wykonuj kroki dokładnie po kolei, bo poradnik jest ułożony od podstaw do efektu końcowego.',
                'Jeżeli na którymś etapie pojawi się inny widok niż w instrukcji, warto wrócić do początku i sprawdzić, czy wcześniejszy krok był poprawny.',
                'Dla laika najważniejsze jest to, że nie trzeba znać całej technologii, wystarczy odwzorować pokazany proces.',
                'Ta podstrona ma zmniejszyć liczbę pomyłek i skrócić czas potrzebny do samodzielnej konfiguracji usługi.',
            ],
        ],
        'instruction-smart-iptv' => [
            'title' => 'Jak czytać ten poradnik aplikacji?',
            'items' => [
                'Ten ekran skupia się na jednej konkretnej aplikacji lub jednym scenariuszu, żeby nie mieszać wielu metod w jednym miejscu.',
                'Najlepiej wykonuj kroki dokładnie po kolei, bo poradnik jest ułożony od podstaw do efektu końcowego.',
                'Jeżeli na którymś etapie pojawi się inny widok niż w instrukcji, warto wrócić do początku i sprawdzić, czy wcześniejszy krok był poprawny.',
                'Dla laika najważniejsze jest to, że nie trzeba znać całej technologii, wystarczy odwzorować pokazany proces.',
                'Ta podstrona ma zmniejszyć liczbę pomyłek i skrócić czas potrzebny do samodzielnej konfiguracji usługi.',
            ],
        ],
        'instruction-ott-player' => [
            'title' => 'Jak czytać ten poradnik aplikacji?',
            'items' => [
                'Ten ekran skupia się na jednej konkretnej aplikacji lub jednym scenariuszu, żeby nie mieszać wielu metod w jednym miejscu.',
                'Najlepiej wykonuj kroki dokładnie po kolei, bo poradnik jest ułożony od podstaw do efektu końcowego.',
                'Jeżeli na którymś etapie pojawi się inny widok niż w instrukcji, warto wrócić do początku i sprawdzić, czy wcześniejszy krok był poprawny.',
                'Dla laika najważniejsze jest to, że nie trzeba znać całej technologii, wystarczy odwzorować pokazany proces.',
                'Ta podstrona ma zmniejszyć liczbę pomyłek i skrócić czas potrzebny do samodzielnej konfiguracji usługi.',
            ],
        ],
        'instruction-newlook' => [
            'title' => 'Jak czytać ten poradnik aplikacji?',
            'items' => [
                'Ten ekran skupia się na jednej konkretnej aplikacji lub jednym scenariuszu, żeby nie mieszać wielu metod w jednym miejscu.',
                'Najlepiej wykonuj kroki dokładnie po kolei, bo poradnik jest ułożony od podstaw do efektu końcowego.',
                'Jeżeli na którymś etapie pojawi się inny widok niż w instrukcji, warto wrócić do początku i sprawdzić, czy wcześniejszy krok był poprawny.',
                'Dla laika najważniejsze jest to, że nie trzeba znać całej technologii, wystarczy odwzorować pokazany proces.',
                'Ta podstrona ma zmniejszyć liczbę pomyłek i skrócić czas potrzebny do samodzielnej konfiguracji usługi.',
            ],
        ],
        'contact' => [
            'title' => 'Jak używać formularza kontaktowego?',
            'items' => [
                'Ta podstrona pozwala wysłać wiadomość do obsługi wtedy, gdy potrzebujesz pomocy przed lub po zalogowaniu.',
                'Najlepiej opisać problem prostymi słowami i dodać konkrety, takie jak adres e-mail, numer zamówienia albo nazwa urządzenia.',
                'Im dokładniejsza wiadomość, tym szybciej support zrozumie sytuację i ograniczy liczbę pytań zwrotnych.',
                'Dla laika to zwykły kanał kontaktu, ale dobrze przygotowana treść bardzo przyspiesza otrzymanie właściwej odpowiedzi.',
                'Jeżeli nie wiesz, od czego zacząć rozmowę z obsługą, ten formularz jest najprostszym i najbardziej uporządkowanym wejściem.',
            ],
        ],
        'register' => [
            'title' => 'Co zrobić na stronie rejestracji?',
            'items' => [
                'Tutaj zakładasz nowe konto klienta, które będzie później używane do zamówień, płatności i kontaktu z supportem.',
                'Wystarczy podać podstawowe dane i poprawny adres e-mail, bo to on zwykle staje się głównym identyfikatorem konta.',
                'Po rejestracji możesz zostać poproszony o aktywację albo po prostu przejść do logowania, zależnie od ustawień serwisu.',
                'Dla osoby początkującej to pierwszy krok do wejścia w pełną funkcjonalność panelu i rozpoczęcia korzystania z oferty.',
                'Najważniejsze jest, aby używać prawdziwego adresu e-mail, bo późniejsze powiadomienia i odzyskiwanie dostępu będą od niego zależeć.',
            ],
        ],
        'password' => [
            'title' => 'Do czego służy ta strona hasła?',
            'items' => [
                'Ta podstrona pomaga odzyskać dostęp wtedy, gdy nie pamiętasz hasła albo nie możesz zalogować się na konto.',
                'Proces zwykle opiera się na adresie e-mail, dlatego trzeba podać dokładnie ten sam adres, który był użyty przy rejestracji.',
                'Po poprawnym przejściu procedury serwis może wysłać nowe hasło lub link do dalszych kroków, zależnie od konfiguracji systemu.',
                'Dla laika to bezpieczna ścieżka awaryjna, która pozwala wrócić do konta bez zakładania nowego profilu od zera.',
                'Jeżeli odzyskiwanie nie działa, warto sprawdzić skrzynkę spam albo skontaktować się z supportem z poziomu formularza kontaktowego.',
            ],
        ],
        'login' => [
            'title' => 'Jak korzystać z logowania?',
            'items' => [
                'Ta strona służy do wejścia do panelu klienta, w którym znajdują się zamówienia, płatności, historia i ustawienia konta.',
                'Do zalogowania potrzebujesz poprawnego adresu e-mail oraz hasła ustawionego przy rejestracji albo wygenerowanego przez system.',
                'Jeśli dane są poprawne, po zalogowaniu trafisz do własnego panelu i od razu zobaczysz najważniejsze funkcje konta.',
                'Dla laika to po prostu brama do całego serwisu, więc wszystkie dalsze czynności zaczynają się od poprawnego logowania.',
                'Jeżeli nie pamiętasz hasła albo nie możesz wejść na konto, skorzystaj z odzyskiwania hasła zamiast próbować tworzyć nowe konto.',
            ],
        ],
    ];

    if ($site === 'homepage') {
        return $isLoggedIn ? $map['homepage_logged'] : $map['homepage_guest'];
    }

    return $map[$site] ?? $default;
}

function app_referral_link(array $settings, int $customerId): string
{
    if ($customerId <= 0) {
        return '';
    }

    $siteUrl = rtrim(trim((string)($settings['site_url'] ?? $settings['page_url'] ?? '')), '/');
    if ($siteUrl === '') {
        return '/ref-' . $customerId;
    }

    return $siteUrl . '/ref-' . $customerId;
}

function app_attach_referral_to_customer(Mysql_ks $db, int $referrerCustomerId, int $referredCustomerId): bool
{
    if ($referrerCustomerId <= 0 || $referredCustomerId <= 0 || $referrerCustomerId === $referredCustomerId) {
        return false;
    }

    if (schema_object_exists($db, 'referrals')) {
        $existing = $db->select_user(
            "SELECT id
             FROM referrals
             WHERE referred_customer_id = " . (int)$referredCustomerId . "
             LIMIT 1"
        );
        if (is_array($existing) && !empty($existing['id'])) {
            return false;
        }

        return (bool)$db->insert(
            ['referrer_customer_id', 'referred_customer_id', 'status'],
            [$referrerCustomerId, $referredCustomerId, 'pending'],
            'referrals'
        );
    }

    if (!schema_object_exists($db, 'refy')) {
        return false;
    }

    $existing = $db->select_user(
        "SELECT id
         FROM refy
         WHERE (" .
            (schema_column_exists($db, 'refy', 'referred_user_id') ? 'referred_user_id' : 'user2') .
         ") = " . (int)$referredCustomerId . "
         LIMIT 1"
    );
    if (is_array($existing) && !empty($existing['id'])) {
        return false;
    }

    $referralInsertFields = ['serwis', 'user1', 'user2', 'status'];
    $referralInsertValues = [0, $referrerCustomerId, $referredCustomerId, 1];

    if (schema_column_exists($db, 'refy', 'service_id')) {
        $referralInsertFields[] = 'service_id';
        $referralInsertValues[] = 0;
    }
    if (schema_column_exists($db, 'refy', 'referrer_user_id')) {
        $referralInsertFields[] = 'referrer_user_id';
        $referralInsertValues[] = $referrerCustomerId;
    }
    if (schema_column_exists($db, 'refy', 'referred_user_id')) {
        $referralInsertFields[] = 'referred_user_id';
        $referralInsertValues[] = $referredCustomerId;
    }
    if (schema_column_exists($db, 'refy', 'is_active')) {
        $referralInsertFields[] = 'is_active';
        $referralInsertValues[] = 1;
    }

    return (bool)$db->insert($referralInsertFields, $referralInsertValues, 'refy');
}

function app_referral_rows(Mysql_ks $db, int $customerId): array
{
    if ($customerId <= 0) {
        return [];
    }

    if (schema_object_exists($db, 'referrals') && schema_object_exists($db, 'customers')) {
        $rows = $db->select_full_user(
            "SELECT
                referrals.id,
                referrals.status AS referral_status,
                customers.id AS customer_id,
                customers.email,
                customers.registered_at,
                customers.status AS customer_status,
                (
                    SELECT COUNT(*)
                    FROM orders
                    WHERE orders.customer_id = referrals.referred_customer_id
                      AND orders.payment_status = 'paid'
                ) AS paid_orders_count
             FROM referrals
             INNER JOIN customers ON customers.id = referrals.referred_customer_id
             WHERE referrals.referrer_customer_id = " . (int)$customerId . "
             ORDER BY customers.id DESC"
        );

        return is_array($rows) ? $rows : [];
    }

    $referralsTable = schema_read_target($db, 'referrals');
    if (!schema_object_exists($db, $referralsTable) || !schema_object_exists($db, 'users')) {
        return [];
    }

    $serviceIdColumn = schema_read_column($db, 'referrals', 'service_id', 'serwis');
    $referrerUserIdColumn = schema_read_column($db, 'referrals', 'referrer_user_id', 'user1');
    $referredUserIdColumn = schema_read_column($db, 'referrals', 'referred_user_id', 'user2');
    $referralStatusColumn = schema_read_column($db, 'referrals', 'is_active', 'status');

    $rows = $db->select_full_user(
        "SELECT {$referralsTable}.id,
                {$referralsTable}.{$referralStatusColumn} AS referral_status,
                users.id AS customer_id,
                users.email AS email,
                users.date_register AS registered_at,
                users.status AS customer_status,
                (
                    SELECT COUNT(id)
                    FROM produkty_user
                    WHERE (aukcja <> 1 AND aukcja <> 5 AND aukcja <> 8 AND aukcja <> 9)
                      AND platnosc = 1
                      AND produkty_user.user = {$referralsTable}.{$referredUserIdColumn}
                ) AS paid_orders_count
         FROM {$referralsTable}
         INNER JOIN users ON {$referralsTable}.{$referredUserIdColumn} = users.id
         WHERE {$referralsTable}.{$serviceIdColumn} = 0
           AND {$referralsTable}.{$referrerUserIdColumn} = " . (int)$customerId . "
         ORDER BY users.id DESC"
    );

    return is_array($rows) ? $rows : [];
}

function app_contact_cooldown_seconds(): int
{
    return 120;
}

function app_contact_rate_limit_remaining(): int
{
    $lastSentAt = isset($_SESSION['contact_form_last_sent_at']) ? (int)$_SESSION['contact_form_last_sent_at'] : 0;
    $cooldown = app_contact_cooldown_seconds();
    if ($lastSentAt <= 0) {
        return 0;
    }

    $remaining = ($lastSentAt + $cooldown) - time();
    return $remaining > 0 ? $remaining : 0;
}

function app_contact_mark_sent(): void
{
    $_SESSION['contact_form_last_sent_at'] = time();
}

function app_contact_subject_prefix(array $settings, string $subjectLabel): string
{
    $siteName = trim((string)($settings['site_name'] ?? $settings['page_name'] ?? 'Subscription Panel'));
    $subjectLabel = trim($subjectLabel);
    $prefix = $siteName !== '' ? '[' . $siteName . ' Contact]' : '[Contact]';

    return trim($prefix . ' ' . $subjectLabel);
}

function app_contact_support_body(array $settings, string $email, string $subjectLabel, string $message, string $clientIp = ''): string
{
    $siteName = trim((string)($settings['site_name'] ?? $settings['page_name'] ?? 'Subscription Panel'));
    $siteUrl = trim((string)($settings['site_url'] ?? $settings['page_url'] ?? ''));
    $bodyLines = [
        'New message from the contact form.',
        '',
        'Site: ' . ($siteName !== '' ? $siteName : 'Subscription Panel'),
        'Email: ' . trim($email),
        'Subject: ' . trim($subjectLabel),
    ];

    if ($clientIp !== '') {
        $bodyLines[] = 'IP: ' . $clientIp;
    }

    if ($siteUrl !== '') {
        $bodyLines[] = 'URL: ' . $siteUrl;
    }

    $bodyLines[] = '';
    $bodyLines[] = 'Message:';
    $bodyLines[] = app_email_plain_text($message);

    return implode("\n", $bodyLines);
}

function app_contact_copy_body(array $settings, string $subjectLabel, string $message): string
{
    $siteName = trim((string)($settings['site_name'] ?? $settings['page_name'] ?? 'Subscription Panel'));
    $siteUrl = trim((string)($settings['site_url'] ?? $settings['page_url'] ?? ''));

    return implode("\n", [
        'Hello,',
        '',
        'We received your contact form message.',
        '',
        'Subject: ' . trim($subjectLabel),
        '',
        'Your message:',
        app_email_plain_text($message),
        '',
        'Regards,',
        $siteName !== '' ? $siteName : 'Subscription Panel',
        $siteUrl,
    ]);
}

function app_customer_presence_key(Mysql_ks $db, int $customerId, string $lastSeenAt = '', ?int $currentTime = null): string
{
    $currentTime = $currentTime ?? time();
    $lastSeenAt = trim($lastSeenAt);
    $lastSeenTimestamp = $lastSeenAt !== '' ? strtotime($lastSeenAt) : false;
    $secondsSinceLastSeen = $lastSeenTimestamp !== false ? max(0, $currentTime - $lastSeenTimestamp) : null;

    $hasActiveOnlineEntry = false;
    if ($customerId > 0 && schema_object_exists($db, 'user_online')) {
        $onlineEntry = $db->select_user(
            "SELECT id
             FROM user_online
             WHERE user = {$customerId}
               AND status = 1
             LIMIT 1"
        );
        $hasActiveOnlineEntry = is_array($onlineEntry) && !empty($onlineEntry['id']);
    }

    if ($hasActiveOnlineEntry && ($secondsSinceLastSeen === null || $secondsSinceLastSeen <= 180)) {
        return 'online';
    }

    if ($secondsSinceLastSeen !== null && $secondsSinceLastSeen <= 180) {
        return 'online';
    }

    if ($secondsSinceLastSeen !== null && $secondsSinceLastSeen <= 600) {
        return 'away';
    }

    return 'offline';
}

function app_customer_is_currently_online(Mysql_ks $db, int $customerId, string $lastSeenAt = ''): bool
{
    return app_customer_presence_key($db, $customerId, $lastSeenAt) === 'online';
}

function app_order_email_context(Mysql_ks $db, int $orderId): ?array
{
    if ($orderId <= 0 || !schema_object_exists($db, 'orders')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT
            orders.id,
            orders.customer_id,
            orders.order_reference,
            orders.status,
            orders.payment_status,
            orders.fulfillment_status,
            orders.total_amount,
            orders.expires_at,
            orders.paid_at,
            customers.email AS customer_email,
            customers.locale_code AS customer_locale_code,
            products.name AS product_name,
            product_providers.name AS provider_name,
            currencies.code AS currency_code,
            currencies.symbol AS currency_symbol
         FROM orders
         LEFT JOIN customers ON customers.id = orders.customer_id
         LEFT JOIN products ON products.id = orders.product_id
         LEFT JOIN product_providers ON product_providers.id = products.provider_id
         LEFT JOIN currencies ON currencies.id = orders.currency_id
         WHERE orders.id = {$orderId}
         LIMIT 1"
    );

    return is_array($row) && !empty($row['id']) ? $row : null;
}

function app_queue_order_email(Mysql_ks $db, string $templateKey, int $orderId, array $extraReplacements = [], int $dedupeWindowSeconds = 0): array
{
    $order = app_order_email_context($db, $orderId);
    if (!is_array($order) || empty($order['customer_email'])) {
        return ['ok' => false, 'message' => 'Order or customer email is missing.'];
    }

    $settings = app_fetch_settings($db);
    $replacements = array_merge([
        'customer_email' => (string)($order['customer_email'] ?? ''),
        'order_id' => (int)($order['id'] ?? 0),
        'id_zamowienia' => (int)($order['id'] ?? 0),
        'order_reference' => (string)($order['order_reference'] ?? ''),
        'product_name' => (string)($order['product_name'] ?? ''),
        'provider_name' => (string)($order['provider_name'] ?? ''),
        'amount' => app_format_money_value($order['total_amount'] ?? 0, (string)($order['currency_symbol'] ?? ''), (string)($order['currency_code'] ?? '')),
        'status' => (string)($order['status'] ?? ''),
        'payment_status' => (string)($order['payment_status'] ?? ''),
        'fulfillment_status' => (string)($order['fulfillment_status'] ?? ''),
        'expires_at' => (string)($order['expires_at'] ?? ''),
        'paid_at' => (string)($order['paid_at'] ?? ''),
        'order_url' => rtrim((string)($settings['site_url'] ?? ''), '/') . '/orders',
    ], $extraReplacements);

    return app_email_queue_template(
        $db,
        $templateKey,
        (string)$order['customer_email'],
        $replacements,
        (int)($order['customer_id'] ?? 0),
        $orderId,
        $dedupeWindowSeconds,
        true,
        (string)($order['customer_locale_code'] ?? '')
    );
}

function app_queue_order_created_notification(Mysql_ks $db, int $orderId): array
{
    return ['ok' => true, 'message' => 'Order created email is disabled.'];
}

function app_queue_order_extended_notification(Mysql_ks $db, int $orderId): array
{
    return ['ok' => true, 'message' => 'Order extended email is disabled.'];
}

function app_queue_order_transition_notifications(Mysql_ks $db, array $beforeOrder, array $afterOrder): array
{
    $orderId = (int)($afterOrder['id'] ?? $beforeOrder['id'] ?? 0);
    if ($orderId <= 0) {
        return [];
    }

    $beforeStatus = strtolower(trim((string)($beforeOrder['status'] ?? '')));
    $afterStatus = strtolower(trim((string)($afterOrder['status'] ?? '')));
    $beforePaymentStatus = strtolower(trim((string)($beforeOrder['payment_status'] ?? '')));
    $afterPaymentStatus = strtolower(trim((string)($afterOrder['payment_status'] ?? '')));

    $results = [];
    if ($beforePaymentStatus !== 'paid' && $afterPaymentStatus === 'paid') {
        $results[] = app_queue_order_email($db, 'order-paid', $orderId, [], 60);
    }

    if ($beforeStatus !== 'active' && $afterStatus === 'active') {
        $results[] = app_queue_order_email($db, 'order-activated', $orderId, [], 60);
    }

    return $results;
}

function app_queue_live_chat_admin_notification(
    Mysql_ks $db,
    int $conversationId,
    int $customerId,
    string $messageBody,
    ?string $attachmentPath = null
): array {
    if (app_live_chat_admins_are_currently_online($db)) {
        return ['ok' => true, 'message' => 'An administrator is online. Email skipped.', 'skipped' => true];
    }

    $settings = app_fetch_settings($db);
    $recipientEmail = app_email_support_recipient($settings);
    if ($recipientEmail === '') {
        return ['ok' => false, 'message' => 'Support email is not configured.'];
    }

    $customer = app_find_customer_by_id($db, $customerId);
    $messagePreview = trim($messageBody);
    if ($messagePreview === '' && trim((string)$attachmentPath) !== '') {
        $messagePreview = 'Customer sent an image attachment.';
    }

    return app_email_queue_template(
        $db,
        'live-chat-admin-notify',
        $recipientEmail,
        [
            'customer_email' => (string)($customer['email'] ?? ''),
            'conversation_id' => $conversationId,
            'chat_url' => rtrim((string)($settings['site_url'] ?? ''), '/') . '/admin/?page=live-chat',
        ],
        $customerId,
        null,
        app_live_chat_email_cooldown_seconds()
    );
}

function app_queue_live_chat_customer_notification_if_offline(
    Mysql_ks $db,
    int $conversationId,
    int $customerId,
    string $messageBody,
    ?string $attachmentPath = null
): array {
    $customer = app_find_customer_by_id($db, $customerId);
    if (!is_array($customer) || empty($customer['email'])) {
        return ['ok' => false, 'message' => 'Customer email is missing.'];
    }

    if (app_customer_is_currently_online($db, $customerId, (string)($customer['last_login_at'] ?? ''))) {
        return ['ok' => true, 'message' => 'Customer is online. Email skipped.', 'skipped' => true];
    }

    $settings = app_fetch_settings($db);
    $messagePreview = trim($messageBody);
    if ($messagePreview === '' && trim((string)$attachmentPath) !== '') {
        $messagePreview = 'Support sent an image attachment.';
    }

    return app_email_queue_template(
        $db,
        'live-chat-customer-notify',
        (string)$customer['email'],
        [
            'conversation_id' => $conversationId,
            'customer_email' => (string)$customer['email'],
            'chat_url' => rtrim((string)($settings['site_url'] ?? ''), '/') . '/',
        ],
        $customerId,
        null,
        app_live_chat_email_cooldown_seconds(),
        true,
        (string)($customer['locale_code'] ?? '')
    );
}

function app_live_chat_email_cooldown_seconds(): int
{
    return 900;
}

function app_reseller_chat_email_cooldown_seconds(): int
{
    return 3600;
}

function app_admin_last_seen_is_online(string $lastSeenAt = '', ?int $currentTime = null): bool
{
    $currentTime = $currentTime ?? time();
    $lastSeenAt = trim($lastSeenAt);
    if ($lastSeenAt === '') {
        return false;
    }

    $lastSeenTimestamp = strtotime($lastSeenAt);
    if ($lastSeenTimestamp === false) {
        return false;
    }

    return max(0, $currentTime - $lastSeenTimestamp) <= 180;
}

function app_live_chat_admins_are_currently_online(Mysql_ks $db): bool
{
    if (!schema_object_exists($db, 'admin_users')) {
        return false;
    }

    $rows = $db->select_full_user(
        "SELECT last_login_at
         FROM admin_users
         WHERE status = 'active'
         ORDER BY COALESCE(last_login_at, '1970-01-01 00:00:00') DESC, id ASC
         LIMIT 10"
    );

    $currentTime = time();
    foreach ($rows as $row) {
        if (app_admin_last_seen_is_online((string)($row['last_login_at'] ?? ''), $currentTime)) {
            return true;
        }
    }

    return false;
}

function app_chat_primary_admin_id(Mysql_ks $db): int
{
    if (schema_object_exists($db, 'admin_users')) {
        $admin = $db->select_user("SELECT id FROM admin_users ORDER BY id ASC LIMIT 1");
        if (is_array($admin) && !empty($admin['id'])) {
            return (int)$admin['id'];
        }
    }

    return 1;
}

function app_find_or_create_live_chat_conversation(Mysql_ks $db, int $customerId): int
{
    if ($customerId <= 0 || !schema_object_exists($db, 'support_conversations')) {
        return 0;
    }

    $conversation = $db->select_user(
        "SELECT id
         FROM support_conversations
         WHERE conversation_type = 'live_chat'
           AND customer_id = {$customerId}
         ORDER BY id ASC
         LIMIT 1"
    );

    if (is_array($conversation) && !empty($conversation['id'])) {
        return (int)$conversation['id'];
    }

    $adminId = app_chat_primary_admin_id($db);
    $subject = 'Customer live chat #' . $customerId;

    $db->insert(
        ['conversation_type', 'customer_id', 'assigned_admin_id', 'subject', 'status', 'priority'],
        ['live_chat', $customerId, $adminId, $subject, 'open', 'normal'],
        'support_conversations'
    );

    return (int)$db->id();
}

function app_insert_live_chat_admin_message(Mysql_ks $db, int $customerId, string $messageBody, ?string $createdAt = null): bool
{
    $messageBody = trim($messageBody);
    if ($customerId <= 0 || $messageBody === '' || !schema_object_exists($db, 'support_messages')) {
        return false;
    }

    $conversationId = app_find_or_create_live_chat_conversation($db, $customerId);
    if ($conversationId <= 0) {
        return false;
    }

    $createdAt = trim((string)$createdAt);
    if ($createdAt === '') {
        $createdAt = app_current_datetime_string();
    }

    $adminId = app_chat_primary_admin_id($db);

    $inserted = $db->insert(
        ['conversation_id', 'sender_type', 'customer_id', 'admin_user_id', 'message_body', 'attachment_path', 'is_read', 'created_at'],
        [$conversationId, 'admin', $customerId, $adminId, $messageBody, null, 0, $createdAt],
        'support_messages'
    );

    if (!$inserted) {
        return false;
    }

    $db->update_using_id(
        ['status', 'last_admin_message_at', 'updated_at'],
        ['open', $createdAt, $createdAt],
        'support_conversations',
        $conversationId
    );

    return true;
}

function app_queue_crypto_payment_live_chat_message(
    Mysql_ks $db,
    int $customerId,
    int $orderId,
    array $assetData
): array {
    if ($customerId <= 0 || $orderId <= 0) {
        return ['ok' => false, 'message' => 'Invalid payment chat payload.'];
    }

    $customer = app_find_customer_by_id($db, $customerId);
    $localeCode = app_normalize_email_locale((string)($customer['locale_code'] ?? 'en'));
    $settings = app_fetch_settings($db);
    $siteUrl = rtrim((string)($settings['site_url'] ?? ''), '/');
    $paymentUrl = $siteUrl !== '' ? $siteUrl . '/order-payment-' . $orderId : '/order-payment-' . $orderId;

    $assetName = trim((string)($assetData['asset_name'] ?? $assetData['crypto_name'] ?? $assetData['crypto_code'] ?? 'Crypto'));
    $assetCode = strtoupper(trim((string)($assetData['asset_code'] ?? $assetData['crypto_code'] ?? '')));
    $amount = trim((string)($assetData['requested_crypto_amount'] ?? ''));
    $walletAddress = trim((string)($assetData['wallet_address'] ?? ''));
    $walletOwner = trim((string)($assetData['wallet_owner_full_name'] ?? ''));
    $requestedFiatAmount = (float)($assetData['requested_fiat_amount'] ?? 0);
    $currencySymbol = (string)($assetData['currency_symbol'] ?? '');
    $currencyCode = (string)($assetData['currency_code'] ?? '');

    if ($orderId > 0 && ($requestedFiatAmount <= 0 || ($currencySymbol === '' && $currencyCode === '')) && schema_object_exists($db, 'orders')) {
        $orderRow = $db->select_user(
            "SELECT
                orders.total_amount,
                currencies.symbol AS currency_symbol,
                currencies.code AS currency_code
             FROM orders
             LEFT JOIN currencies ON currencies.id = orders.currency_id
             WHERE orders.id = {$orderId}
             LIMIT 1"
        );
        if (is_array($orderRow)) {
            if ($requestedFiatAmount <= 0) {
                $requestedFiatAmount = (float)($orderRow['total_amount'] ?? 0);
            }
            if ($currencySymbol === '') {
                $currencySymbol = (string)($orderRow['currency_symbol'] ?? '');
            }
            if ($currencyCode === '') {
                $currencyCode = (string)($orderRow['currency_code'] ?? '');
            }
        }
    }

    $messageBody = app_build_crypto_payment_chat_card_message([
        'asset_name' => $assetName,
        'asset_code' => $assetCode,
        'asset_logo_url' => (string)($assetData['asset_logo_url'] ?? $assetData['logo_url'] ?? ''),
        'requested_crypto_amount' => $amount,
        'requested_fiat_amount' => $requestedFiatAmount,
        'currency_symbol' => $currencySymbol,
        'currency_code' => $currencyCode,
        'wallet_address' => $walletAddress,
        'wallet_owner_full_name' => $walletOwner,
        'payment_url' => $paymentUrl,
    ], $localeCode);
    if ($messageBody === '') {
        return ['ok' => false, 'message' => 'Unable to build payment instruction message.'];
    }
    $inserted = app_insert_live_chat_admin_message($db, $customerId, $messageBody);

    return [
        'ok' => $inserted,
        'message' => $inserted ? 'Payment instruction message created.' : 'Unable to create payment instruction message.',
    ];
}

function app_queue_account_created_notification(Mysql_ks $db, int $customerId): array
{
    $customer = app_find_customer_by_id($db, $customerId);
    if (!is_array($customer) || empty($customer['email'])) {
        return ['ok' => false, 'message' => 'Customer email is missing.'];
    }

    $settings = app_fetch_settings($db);

    return app_email_queue_template(
        $db,
        'account-activation',
        (string)$customer['email'],
        [
            'customer_email' => (string)$customer['email'],
            'login_url' => rtrim((string)($settings['site_url'] ?? ''), '/') . '/login',
        ],
        $customerId,
        null,
        300,
        true,
        (string)($customer['locale_code'] ?? '')
    );
}

function app_queue_account_blocked_notification(Mysql_ks $db, int $customerId): array
{
    $customer = app_find_customer_by_id($db, $customerId);
    if (!is_array($customer) || empty($customer['email'])) {
        return ['ok' => false, 'message' => 'Customer email is missing.'];
    }

    return app_email_queue_template(
        $db,
        'account-blocked',
        (string)$customer['email'],
        [
            'customer_email' => (string)$customer['email'],
        ],
        $customerId,
        null,
        900,
        true,
        (string)($customer['locale_code'] ?? '')
    );
}

function app_queue_payment_request_notification(Mysql_ks $db, int $orderId, int $customerId): array
{
    $customer = app_find_customer_by_id($db, $customerId);
    if (!is_array($customer) || empty($customer['email'])) {
        return ['ok' => false, 'message' => 'Customer email is missing.'];
    }

    $settings = app_fetch_settings($db);

    return app_email_queue_template(
        $db,
        'payment-request-created',
        (string)$customer['email'],
        [
            'payment_url' => rtrim((string)($settings['site_url'] ?? ''), '/') . '/orders',
        ],
        $customerId,
        $orderId,
        300,
        true,
        (string)($customer['locale_code'] ?? '')
    );
}

function app_queue_support_payment_request_notification(Mysql_ks $db, int $orderId, int $customerId, string $paymentType): array
{
    $settings = app_fetch_settings($db);
    $recipientEmail = app_email_support_recipient($settings);
    if ($recipientEmail === '') {
        return ['ok' => false, 'message' => 'Support email is not configured.'];
    }

    $customer = app_find_customer_by_id($db, $customerId);

    return app_email_queue_template(
        $db,
        'support-payment-request-notify',
        $recipientEmail,
        [
            'customer_email' => (string)($customer['email'] ?? ''),
            'payment_type' => trim($paymentType) !== '' ? $paymentType : 'payment',
            'admin_url' => rtrim((string)($settings['site_url'] ?? ''), '/') . '/admin/?page=payments',
        ],
        $customerId,
        $orderId,
        300
    );
}

function app_queue_news_broadcast(Mysql_ks $db, int $newsId): array
{
    if ($newsId <= 0 || !schema_object_exists($db, 'news_posts')) {
        return ['ok' => false, 'message' => 'News post not found.'];
    }

    $safeNow = $db->escape(app_current_datetime_string());
    $news = $db->select_user(
        "SELECT id, visibility, is_active, published_at
         FROM news_posts
         WHERE id = {$newsId}
         LIMIT 1"
    );

    if (!is_array($news) || empty($news['id'])) {
        return ['ok' => false, 'message' => 'News post not found.'];
    }

    $visibility = strtolower(trim((string)($news['visibility'] ?? '')));
    if (empty($news['is_active']) || !in_array($visibility, ['customer', 'client', 'reseller', 'public'], true) || (string)($news['published_at'] ?? '') > $safeNow) {
        return ['ok' => true, 'message' => 'News email skipped. Post is not public yet.', 'queued' => 0];
    }

    app_ensure_customer_runtime_columns($db);
    $customerTypeWhere = '';
    if ($visibility === 'customer' || $visibility === 'client') {
        $customerTypeWhere = " AND customer_type = 'client'";
    } elseif ($visibility === 'reseller') {
        $customerTypeWhere = " AND customer_type = 'reseller'";
    }

    $rows = $db->select_full_user(
        "SELECT id, email
         FROM customers
         WHERE status = 'active'
         {$customerTypeWhere}
         ORDER BY id ASC"
    );

    if (!$rows) {
        return ['ok' => true, 'message' => 'No active customers found.', 'queued' => 0];
    }

    $settings = app_fetch_settings($db);
    $queued = 0;
    foreach ($rows as $row) {
        $customerId = (int)($row['id'] ?? 0);
        $email = trim((string)($row['email'] ?? ''));
        if ($customerId <= 0 || $email === '') {
            continue;
        }

        $result = app_email_queue_template(
            $db,
            'news-broadcast',
            $email,
            [
                'news_url' => rtrim((string)($settings['site_url'] ?? ''), '/') . '/news',
            ],
            $customerId,
            null,
            300
        );

        if (!empty($result['queued'])) {
            $queued++;
        }
    }

    return ['ok' => true, 'message' => 'News notification queued.', 'queued' => $queued];
}

function app_expire_overdue_orders(Mysql_ks $db, ?string $now = null): array
{
    if (!schema_object_exists($db, 'orders')) {
        return [
            'ok' => false,
            'time' => '',
            'expired_orders' => 0,
            'logged_events' => 0,
            'message' => 'Orders table is not available.',
        ];
    }

    $safeNow = trim((string)$now);
    if ($safeNow === '') {
        $safeNow = date('Y-m-d H:i:s');
    }
    $safeNowSql = $db->escape($safeNow);

    $rows = $db->select_full_user(
        "SELECT id
         FROM orders
         WHERE status = 'active'
           AND expires_at IS NOT NULL
           AND expires_at <= '{$safeNowSql}'
         ORDER BY id ASC"
    );

    if (!$rows) {
        return [
            'ok' => true,
            'time' => $safeNow,
            'expired_orders' => 0,
            'logged_events' => 0,
            'message' => 'No overdue active subscriptions found.',
        ];
    }

    $orderIds = [];
    foreach ($rows as $row) {
        $orderId = (int)($row['id'] ?? 0);
        if ($orderId > 0) {
            $orderIds[] = $orderId;
        }
    }

    if (!$orderIds) {
        return [
            'ok' => true,
            'time' => $safeNow,
            'expired_orders' => 0,
            'logged_events' => 0,
            'message' => 'No overdue active subscriptions found.',
        ];
    }

    $idList = implode(',', $orderIds);
    $loggedEvents = 0;

    $db->start();

    if (schema_object_exists($db, 'order_status_events')) {
        $insertEvents = $db->query(
            "INSERT INTO order_status_events (order_id, admin_user_id, old_status, new_status, event_note, created_at)
             SELECT orders.id,
                    NULL,
                    'active',
                    'expired',
                    'Subscription expired automatically by scheduler',
                    '{$safeNowSql}'
             FROM orders
             WHERE orders.id IN ({$idList})
               AND orders.status = 'active'"
        );

        if (!$insertEvents) {
            $db->query('ROLLBACK');
            return [
                'ok' => false,
                'time' => $safeNow,
                'expired_orders' => 0,
                'logged_events' => 0,
                'message' => 'Unable to write order status events.',
            ];
        }

        $loggedEvents = (int)$db->affected_rows;
    }

    $updated = $db->query(
        "UPDATE orders
         SET status = 'expired'
         WHERE id IN ({$idList})
           AND status = 'active'"
    );

    if (!$updated) {
        $db->query('ROLLBACK');
        return [
            'ok' => false,
            'time' => $safeNow,
            'expired_orders' => 0,
            'logged_events' => 0,
            'message' => 'Unable to expire overdue subscriptions.',
        ];
    }

    $expiredOrders = (int)$db->affected_rows;
    $db->commit();

    $emailQueued = 0;
    foreach ($orderIds as $orderId) {
        $emailResult = app_queue_order_email($db, 'order-expired', (int)$orderId, [], 60);
        if (!empty($emailResult['queued'])) {
            $emailQueued++;
        }
    }

    return [
        'ok' => true,
        'time' => $safeNow,
        'expired_orders' => $expiredOrders,
        'logged_events' => $loggedEvents,
        'email_notifications_queued' => $emailQueued,
        'message' => $expiredOrders > 0
            ? 'Overdue subscriptions expired successfully.'
            : 'No overdue active subscriptions found.',
    ];
}

function app_archive_expired_payment_requests(Mysql_ks $db, ?string $now = null): array
{
    $safeNow = trim((string)$now);
    if ($safeNow === '') {
        $safeNow = date('Y-m-d H:i:s');
    }
    $safeNowSql = $db->escape($safeNow);

    $cancelledCrypto = 0;
    $cancelledBank = 0;
    $resetOrders = 0;

    if (schema_object_exists($db, 'crypto_deposit_requests')) {
        $cancelledCrypto = app_cancel_crypto_deposit_requests(
            $db,
            "status IN ('pending', 'awaiting_confirmation', 'awaiting_review')
             AND expires_at IS NOT NULL
             AND expires_at <= '{$safeNowSql}'",
            'Released after expired crypto payment request',
            $safeNow
        );
    }

    if (schema_object_exists($db, 'bank_transfer_requests')) {
        $bankResult = $db->query(
            "UPDATE bank_transfer_requests
             SET status = 'cancelled'
             WHERE status IN ('pending_payment', 'awaiting_review')
               AND expires_at IS NOT NULL
               AND expires_at <= '{$safeNowSql}'"
        );
        if ($bankResult) {
            $cancelledBank = (int)$db->affected_rows;
        }
    }

    if (schema_object_exists($db, 'orders')) {
        $orderResult = $db->query(
            "UPDATE orders
             SET payment_method = NULL
             WHERE payment_status NOT IN ('paid', 'manual_review', 'processing', 'awaiting_confirmation', 'awaiting_review')
               AND id IN (
                   SELECT expired_requests.order_id
                   FROM (
                       SELECT order_id
                       FROM crypto_deposit_requests
                       WHERE status = 'cancelled'
                         AND expires_at IS NOT NULL
                         AND expires_at <= '{$safeNowSql}'
                       UNION
                       SELECT order_id
                       FROM bank_transfer_requests
                       WHERE status = 'cancelled'
                         AND expires_at IS NOT NULL
                         AND expires_at <= '{$safeNowSql}'
                   ) AS expired_requests
                   WHERE expired_requests.order_id IS NOT NULL
               )"
        );
        if ($orderResult) {
            $resetOrders = (int)$db->affected_rows;
        }
    }

    return [
        'ok' => true,
        'time' => $safeNow,
        'archived_crypto_requests' => $cancelledCrypto,
        'archived_bank_requests' => $cancelledBank,
        'cancelled_crypto_requests' => $cancelledCrypto,
        'cancelled_bank_requests' => $cancelledBank,
        'reset_order_payment_method' => $resetOrders,
        'message' => ($cancelledCrypto + $cancelledBank + $resetOrders) > 0
            ? 'Expired payment requests cancelled.'
            : 'No expired payment requests found.',
    ];
}

function app_delete_stale_unpaid_orders(Mysql_ks $db, ?string $now = null): array
{
    $safeNow = trim((string)$now);
    if ($safeNow === '') {
        $safeNow = date('Y-m-d H:i:s');
    }

    if (!schema_object_exists($db, 'orders')) {
        return [
            'ok' => true,
            'time' => $safeNow,
            'deleted_orders' => 0,
            'cancelled_crypto_requests' => 0,
            'cancelled_bank_requests' => 0,
            'message' => 'Orders table is not available.',
        ];
    }

    $safeNowSql = $db->escape($safeNow);
    $extraWhere = '';
    if (schema_object_exists($db, 'crypto_deposit_requests')) {
        $extraWhere .= "
           AND NOT EXISTS (
               SELECT 1
               FROM crypto_deposit_requests
               WHERE crypto_deposit_requests.order_id = orders.id
                 AND crypto_deposit_requests.status IN ('approved', 'confirmed', 'paid', 'completed', 'archived')
           )";
    }
    if (schema_object_exists($db, 'bank_transfer_requests')) {
        $extraWhere .= "
           AND NOT EXISTS (
               SELECT 1
               FROM bank_transfer_requests
               WHERE bank_transfer_requests.order_id = orders.id
                 AND bank_transfer_requests.status IN ('approved', 'confirmed', 'paid', 'completed', 'archived')
           )";
    }

    $rows = $db->select_full_user(
        "SELECT orders.id, orders.customer_id
         FROM orders
         WHERE orders.created_at IS NOT NULL
           AND orders.created_at <= DATE_SUB('{$safeNowSql}', INTERVAL 24 HOUR)
           AND LOWER(COALESCE(orders.status, '')) IN ('pending', 'pending_payment')
           AND LOWER(COALESCE(orders.payment_status, '')) IN ('', 'unpaid', 'pending', 'pending_payment', 'awaiting_confirmation', 'awaiting_review')
           AND LOWER(COALESCE(orders.fulfillment_status, '')) IN ('', 'pending')
           {$extraWhere}
         ORDER BY orders.id ASC"
    );

    if (!$rows) {
        return [
            'ok' => true,
            'time' => $safeNow,
            'deleted_orders' => 0,
            'cancelled_crypto_requests' => 0,
            'cancelled_bank_requests' => 0,
            'message' => 'No stale unpaid orders found.',
        ];
    }

    $orderIds = [];
    foreach ($rows as $row) {
        $orderId = (int)($row['id'] ?? 0);
        if ($orderId > 0) {
            $orderIds[$orderId] = $orderId;
        }
    }

    if (!$orderIds) {
        return [
            'ok' => true,
            'time' => $safeNow,
            'deleted_orders' => 0,
            'cancelled_crypto_requests' => 0,
            'cancelled_bank_requests' => 0,
            'message' => 'No stale unpaid orders found.',
        ];
    }

    $idList = implode(',', $orderIds);
    $cancelledCrypto = 0;
    $cancelledBank = 0;

    if (schema_object_exists($db, 'crypto_deposit_requests')) {
        $cancelledCrypto = app_cancel_crypto_deposit_requests(
            $db,
            "order_id IN ({$idList})
             AND status IN ('pending', 'awaiting_confirmation', 'awaiting_review')",
            'Released after stale unpaid order cleanup',
            $safeNow
        );

        if (schema_column_exists($db, 'crypto_deposit_requests', 'order_id')) {
            $db->query(
                "UPDATE crypto_deposit_requests
                 SET order_id = NULL
                 WHERE order_id IN ({$idList})
                   AND status NOT IN ('approved', 'confirmed', 'paid', 'completed', 'archived')"
            );
        }
    }

    if (schema_object_exists($db, 'bank_transfer_requests')) {
        $bankResult = $db->query(
            "UPDATE bank_transfer_requests
             SET status = 'cancelled'
             WHERE order_id IN ({$idList})
               AND status IN ('pending_payment', 'awaiting_review')"
        );
        if ($bankResult) {
            $cancelledBank = (int)$db->affected_rows;
        }

        if (schema_column_exists($db, 'bank_transfer_requests', 'order_id')) {
            $db->query(
                "UPDATE bank_transfer_requests
                 SET order_id = NULL
                 WHERE order_id IN ({$idList})
                   AND status NOT IN ('approved', 'confirmed', 'paid', 'completed', 'archived')"
            );
        }
    }

    if (schema_object_exists($db, 'order_status_events')) {
        app_delete_records_by_ids($db, 'order_status_events', 'order_id', array_values($orderIds));
    }

    $deletedOrders = app_delete_records_by_ids($db, 'orders', 'id', array_values($orderIds));
    if ($deletedOrders === null) {
        return [
            'ok' => false,
            'time' => $safeNow,
            'deleted_orders' => 0,
            'cancelled_crypto_requests' => $cancelledCrypto,
            'cancelled_bank_requests' => $cancelledBank,
            'message' => 'Unable to delete stale unpaid orders.',
        ];
    }

    return [
        'ok' => true,
        'time' => $safeNow,
        'deleted_orders' => (int)$deletedOrders,
        'cancelled_crypto_requests' => $cancelledCrypto,
        'cancelled_bank_requests' => $cancelledBank,
        'message' => $deletedOrders > 0
            ? 'Stale unpaid orders deleted successfully.'
            : 'No stale unpaid orders found.',
    ];
}

function app_prune_retained_data(Mysql_ks $db, ?string $now = null): array
{
    $settings = app_fetch_settings($db);
    $safeNow = trim((string)$now);
    if ($safeNow === '') {
        $safeNow = date('Y-m-d H:i:s');
    }

    $cutoffTimestamp = strtotime('-12 months', strtotime($safeNow));
    if ($cutoffTimestamp === false) {
        $cutoffTimestamp = strtotime('-365 days');
    }
    $cutoff = date('Y-m-d H:i:s', (int)$cutoffTimestamp);
    $safeCutoff = $db->escape($cutoff);

    $deletedHistoryLogs = 0;
    $deletedCryptoTransactions = 0;
    $deletedCryptoRequests = 0;
    $deletedBankRequests = 0;
    $deletedExpiredOrders = 0;
    $errors = [];

    if (!empty($settings['history_cleanup_enabled']) && schema_object_exists($db, 'customer_activity_logs')) {
        $result = $db->query(
            "DELETE FROM customer_activity_logs
             WHERE created_at < '{$safeCutoff}'"
        );
        if ($result) {
            $deletedHistoryLogs = (int)$db->affected_rows;
        } else {
            $errors[] = 'history_logs';
        }
    }

    if (!empty($settings['payments_cleanup_enabled'])) {
        if (schema_object_exists($db, 'crypto_deposit_transactions')) {
            $result = $db->query(
                "DELETE FROM crypto_deposit_transactions
                 WHERE received_at < '{$safeCutoff}'"
            );
            if ($result) {
                $deletedCryptoTransactions = (int)$db->affected_rows;
            } else {
                $errors[] = 'crypto_transactions';
            }
        }

        if (schema_object_exists($db, 'crypto_deposit_requests')) {
            $result = $db->query(
                "DELETE FROM crypto_deposit_requests
                 WHERE requested_at < '{$safeCutoff}'
                   AND status NOT IN ('pending', 'awaiting_confirmation', 'awaiting_review')"
            );
            if ($result) {
                $deletedCryptoRequests = (int)$db->affected_rows;
            } else {
                $errors[] = 'crypto_requests';
            }
        }

        if (schema_object_exists($db, 'bank_transfer_requests')) {
            $result = $db->query(
                "DELETE FROM bank_transfer_requests
                 WHERE requested_at < '{$safeCutoff}'
                   AND status NOT IN ('pending_payment', 'awaiting_review')"
            );
            if ($result) {
                $deletedBankRequests = (int)$db->affected_rows;
            } else {
                $errors[] = 'bank_requests';
            }
        }
    }

    if (!empty($settings['expired_orders_cleanup_enabled']) && schema_object_exists($db, 'orders')) {
        $result = $db->query(
            "DELETE FROM orders
             WHERE status = 'expired'
               AND COALESCE(expires_at, updated_at, created_at) < '{$safeCutoff}'"
        );
        if ($result) {
            $deletedExpiredOrders = (int)$db->affected_rows;
        } else {
            $errors[] = 'expired_orders';
        }
    }

    return [
        'ok' => $errors === [],
        'time' => $safeNow,
        'cutoff' => $cutoff,
        'deleted_history_logs' => $deletedHistoryLogs,
        'deleted_crypto_transactions' => $deletedCryptoTransactions,
        'deleted_crypto_requests' => $deletedCryptoRequests,
        'deleted_bank_requests' => $deletedBankRequests,
        'deleted_expired_orders' => $deletedExpiredOrders,
        'message' => $errors === []
            ? 'Retention cleanup finished.'
            : 'Retention cleanup finished with errors: ' . implode(', ', $errors),
    ];
}

function app_prune_support_chat_messages(Mysql_ks $db, ?string $now = null): array
{
    $settings = app_fetch_settings($db);
    $safeNow = trim((string)$now);
    if ($safeNow === '') {
        $safeNow = date('Y-m-d H:i:s');
    }

    if (!schema_object_exists($db, 'support_messages')) {
        return [
            'ok' => false,
            'time' => $safeNow,
            'deleted_messages' => 0,
            'deleted_empty_conversations' => 0,
            'deleted_files' => 0,
            'message' => 'Support messages table is not available.',
        ];
    }

    $hours = (int)($settings['support_chat_retention_hours'] ?? 168);
    if (!in_array($hours, [1, 24, 72, 168, 720], true)) {
        $hours = 168;
    }

    $cutoffTimestamp = strtotime('-' . $hours . ' hours', strtotime($safeNow));
    if ($cutoffTimestamp === false) {
        $cutoffTimestamp = strtotime('-7 days');
    }
    $cutoff = date('Y-m-d H:i:s', (int)$cutoffTimestamp);
    $safeCutoff = $db->escape($cutoff);

    if (schema_object_exists($db, 'support_conversations') && schema_column_exists($db, 'support_conversations', 'conversation_type')) {
        $rows = $db->select_full_user(
            "SELECT support_messages.id, support_messages.attachment_path
             FROM support_messages
             INNER JOIN support_conversations
                     ON support_conversations.id = support_messages.conversation_id
             WHERE support_conversations.conversation_type = 'live_chat'
               AND support_messages.created_at < '{$safeCutoff}'
             ORDER BY support_messages.id ASC"
        );
    } else {
        $rows = $db->select_full_user(
            "SELECT id, attachment_path
             FROM support_messages
             WHERE created_at < '{$safeCutoff}'
             ORDER BY id ASC"
        );
    }

    if (!$rows) {
        return [
            'ok' => true,
            'time' => $safeNow,
            'cutoff' => $cutoff,
            'deleted_messages' => 0,
            'deleted_empty_conversations' => 0,
            'deleted_files' => 0,
            'message' => 'No old live chat messages found.',
        ];
    }

    $messageIds = [];
    $deletedFiles = 0;
    foreach ($rows as $row) {
        $messageId = (int)($row['id'] ?? 0);
        if ($messageId > 0) {
            $messageIds[] = $messageId;
        }

        $attachmentPath = trim((string)($row['attachment_path'] ?? ''));
        if ($attachmentPath !== '' && strpos($attachmentPath, '/uploads/chat/') === 0) {
            $absolutePath = app_chat_attachment_absolute_path($attachmentPath);
            if (is_file($absolutePath) && @unlink($absolutePath)) {
                $deletedFiles++;
            }
        }
    }

    if (!$messageIds) {
        return [
            'ok' => true,
            'time' => $safeNow,
            'cutoff' => $cutoff,
            'deleted_messages' => 0,
            'deleted_empty_conversations' => 0,
            'deleted_files' => $deletedFiles,
            'message' => 'No old live chat messages found.',
        ];
    }

    $idList = implode(',', $messageIds);
    $db->start();

    $deleteMessages = $db->query("DELETE FROM support_messages WHERE id IN ({$idList})");
    if (!$deleteMessages) {
        $db->query('ROLLBACK');
        return [
            'ok' => false,
            'time' => $safeNow,
            'cutoff' => $cutoff,
            'deleted_messages' => 0,
            'deleted_empty_conversations' => 0,
            'deleted_files' => $deletedFiles,
            'message' => 'Unable to delete old live chat messages.',
        ];
    }
    $deletedMessages = (int)$db->affected_rows;

    $deletedEmptyConversations = 0;
    if (schema_object_exists($db, 'support_conversations')) {
        $deleteEmptyConversations = $db->query(
            "DELETE FROM support_conversations
             WHERE conversation_type = 'live_chat'
               AND id NOT IN (
                    SELECT DISTINCT conversation_id
                    FROM support_messages
               )"
        );
        if ($deleteEmptyConversations) {
            $deletedEmptyConversations = (int)$db->affected_rows;
        }

        $db->query(
            "UPDATE support_conversations AS conversation
             LEFT JOIN (
                SELECT
                    conversation_id,
                    MAX(created_at) AS latest_message_at,
                    MAX(CASE WHEN sender_type = 'customer' THEN created_at ELSE NULL END) AS latest_customer_message_at,
                    MAX(CASE WHEN sender_type = 'admin' THEN created_at ELSE NULL END) AS latest_admin_message_at
                FROM support_messages
                GROUP BY conversation_id
             ) AS message_state
               ON message_state.conversation_id = conversation.id
             SET conversation.updated_at = COALESCE(message_state.latest_message_at, conversation.updated_at),
                 conversation.last_customer_message_at = message_state.latest_customer_message_at,
                 conversation.last_admin_message_at = message_state.latest_admin_message_at
             WHERE conversation.conversation_type = 'live_chat'"
        );
    }

    $db->commit();

    return [
        'ok' => true,
        'time' => $safeNow,
        'cutoff' => $cutoff,
        'deleted_messages' => $deletedMessages,
        'deleted_empty_conversations' => $deletedEmptyConversations,
        'deleted_files' => $deletedFiles,
        'message' => $deletedMessages > 0
            ? 'Old live chat messages deleted.'
            : 'No old live chat messages found.',
    ];
}

function app_run_maintenance_cycle(Mysql_ks $db, array $options = []): array
{
    $safeNow = trim((string)($options['now'] ?? ''));
    if ($safeNow === '') {
        $safeNow = date('Y-m-d H:i:s');
    }

    $emailLimit = (int)($options['email_limit'] ?? 20);
    if ($emailLimit <= 0) {
        $emailLimit = 20;
    }

    $email = app_email_process_queue($db, $emailLimit);
    $cryptoBackup = app_send_daily_crypto_backup_report($db, $safeNow);
    $archive = app_archive_expired_payment_requests($db, $safeNow);
    $staleOrders = app_delete_stale_unpaid_orders($db, $safeNow);
    $expire = app_expire_overdue_orders($db, $safeNow);
    $chat = app_prune_support_chat_messages($db, $safeNow);
    $messenger = function_exists('chat_prune_group_chat_messages')
        ? chat_prune_group_chat_messages($db, $safeNow)
        : ['ok' => true, 'deleted_messages' => 0, 'deleted_files' => 0, 'message' => 'Group messenger cleanup skipped.'];
    $prune = app_prune_retained_data($db, $safeNow);

    $steps = [
        'email_queue' => $email,
        'crypto_daily_backup' => $cryptoBackup,
        'archive_requests' => $archive,
        'stale_unpaid_orders' => $staleOrders,
        'expire_orders' => $expire,
        'live_chat_cleanup' => $chat,
        'group_chat_cleanup' => $messenger,
        'retention_cleanup' => $prune,
    ];

    $warnings = [];
    foreach ($steps as $stepKey => $stepResult) {
        if (!is_array($stepResult) || !array_key_exists('ok', $stepResult) || !empty($stepResult['ok'])) {
            continue;
        }
        $warnings[] = $stepKey . ': ' . trim((string)($stepResult['message'] ?? 'Unknown error'));
    }

    return [
        'ok' => $warnings === [],
        'time' => $safeNow,
        'steps' => $steps,
        'summary' => [
            'emails_processed' => (int)($email['processed'] ?? 0),
            'emails_sent' => (int)($email['sent'] ?? 0),
            'emails_failed' => (int)($email['failed'] ?? 0),
            'crypto_backup_sent' => (int)($cryptoBackup['sent'] ?? 0),
            'crypto_backup_rows' => (int)($cryptoBackup['rows'] ?? 0),
            'archived_crypto_requests' => (int)($archive['archived_crypto_requests'] ?? 0),
            'archived_bank_requests' => (int)($archive['archived_bank_requests'] ?? 0),
            'reset_order_payment_method' => (int)($archive['reset_order_payment_method'] ?? 0),
            'deleted_stale_unpaid_orders' => (int)($staleOrders['deleted_orders'] ?? 0),
            'expired_orders' => (int)($expire['expired_orders'] ?? 0),
            'deleted_chat_messages' => (int)($chat['deleted_messages'] ?? 0),
            'deleted_chat_conversations' => (int)($chat['deleted_empty_conversations'] ?? 0),
            'deleted_chat_files' => (int)($chat['deleted_files'] ?? 0),
            'deleted_group_chat_messages' => (int)($messenger['deleted_messages'] ?? 0),
            'deleted_group_chat_files' => (int)($messenger['deleted_files'] ?? 0),
            'deleted_history_logs' => (int)($prune['deleted_history_logs'] ?? 0),
            'deleted_crypto_transactions' => (int)($prune['deleted_crypto_transactions'] ?? 0),
            'deleted_crypto_requests' => (int)($prune['deleted_crypto_requests'] ?? 0),
            'deleted_bank_requests' => (int)($prune['deleted_bank_requests'] ?? 0),
            'deleted_expired_orders' => (int)($prune['deleted_expired_orders'] ?? 0),
        ],
        'message' => $warnings === []
            ? 'Maintenance cycle finished successfully.'
            : 'Maintenance cycle finished with warnings: ' . implode(' | ', $warnings),
    ];
}

function app_delete_records_by_ids(Mysql_ks $db, string $table, string $column, array $ids): ?int
{
    if (!$ids || !schema_object_exists($db, $table) || !schema_column_exists($db, $table, $column)) {
        return 0;
    }

    $safeIds = [];
    foreach ($ids as $id) {
        $value = (int)$id;
        if ($value > 0) {
            $safeIds[] = $value;
        }
    }

    if (!$safeIds) {
        return 0;
    }

    $idList = implode(',', array_unique($safeIds));
    $result = $db->query("DELETE FROM {$table} WHERE {$column} IN ({$idList})");
    return $result ? (int)$db->affected_rows : null;
}

function app_clear_user_history(Mysql_ks $db, ?string $now = null): array
{
    $safeNow = trim((string)$now);
    if ($safeNow === '') {
        $safeNow = date('Y-m-d H:i:s');
    }

    $deletedLogs = 0;
    if (schema_object_exists($db, 'customer_activity_logs')) {
        $result = $db->query("DELETE FROM customer_activity_logs");
        if ($result) {
            $deletedLogs = (int)$db->affected_rows;
        }
    }

    $updatedVisibility = false;
    if (schema_object_exists($db, 'app_settings') && schema_column_exists($db, 'app_settings', 'history_visible_from')) {
        $updatedVisibility = (bool)$db->update_using_id(
            ['history_visible_from'],
            [$safeNow],
            'app_settings',
            1
        );
    }

    return [
        'ok' => true,
        'time' => $safeNow,
        'deleted_activity_logs' => $deletedLogs,
        'history_visible_from' => $safeNow,
        'updated_visibility_cutoff' => $updatedVisibility ? 1 : 0,
        'message' => 'User history has been cleared.',
    ];
}

function app_customer_activity_log(
    Mysql_ks $db,
    int $customerId,
    string $actionKey,
    string $description,
    string $actorType = 'system',
    int $adminUserId = 0,
    string $ipAddress = ''
): void
{
    if ($customerId <= 0 || !schema_object_exists($db, 'customer_activity_logs')) {
        return;
    }

    $actorType = strtolower(trim($actorType));
    if (!in_array($actorType, ['system', 'customer', 'admin'], true)) {
        $actorType = 'system';
    }

    $db->insert(
        ['customer_id', 'admin_user_id', 'actor_type', 'action_key', 'description', 'ip_address'],
        [$customerId, $adminUserId > 0 ? $adminUserId : null, $actorType, $actionKey, $description, $ipAddress !== '' ? $ipAddress : null],
        'customer_activity_logs'
    );
}

function app_ensure_customer_balance_runtime_table(Mysql_ks $db): void
{
    if (schema_object_exists($db, 'customer_balance_runtime_events')) {
        return;
    }

    @$db->query(
        "CREATE TABLE IF NOT EXISTS customer_balance_runtime_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id INT UNSIGNED NOT NULL,
            source_type VARCHAR(40) NOT NULL,
            source_key VARCHAR(120) NOT NULL,
            direction VARCHAR(10) NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            note VARCHAR(255) DEFAULT NULL,
            created_by_admin_user_id INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_customer_balance_runtime_event (source_type, source_key, direction),
            KEY idx_customer_balance_runtime_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function app_apply_customer_balance_runtime_event(
    Mysql_ks $db,
    int $customerId,
    float $amount,
    string $direction,
    string $sourceType,
    string $sourceKey,
    string $note,
    string $actorType = 'system',
    int $adminUserId = 0,
    string $ipAddress = ''
): array
{
    if ($customerId <= 0 || $amount <= 0) {
        return ['ok' => false, 'message' => 'Customer balance event is invalid.'];
    }

    if (!schema_object_exists($db, 'customers') || !schema_column_exists($db, 'customers', 'balance_amount')) {
        return ['ok' => false, 'message' => 'Balance is not available in this database schema yet.'];
    }

    $direction = strtolower(trim($direction));
    if (!in_array($direction, ['credit', 'debit'], true)) {
        return ['ok' => false, 'message' => 'Customer balance event direction is invalid.'];
    }

    app_ensure_customer_balance_runtime_table($db);

    $sourceType = substr(trim($sourceType), 0, 40);
    $sourceKey = substr(trim($sourceKey), 0, 120);
    $note = trim($note);
    if ($sourceType === '' || $sourceKey === '') {
        return ['ok' => false, 'message' => 'Customer balance event source is invalid.'];
    }

    $safeSourceType = $db->escape($sourceType);
    $safeSourceKey = $db->escape($sourceKey);

    $db->query('START TRANSACTION');

    $existingEvent = $db->select_user(
        "SELECT id
         FROM customer_balance_runtime_events
         WHERE source_type = '" . $safeSourceType . "'
           AND source_key = '" . $safeSourceKey . "'
           AND direction = '" . $db->escape($direction) . "'
         LIMIT 1"
    );

    if (is_array($existingEvent) && !empty($existingEvent['id'])) {
        $db->query('COMMIT');
        return [
            'ok' => true,
            'already_applied' => true,
            'message' => 'Customer balance event was already applied.',
        ];
    }

    $customerRow = $db->select_user(
        "SELECT id, balance_amount
         FROM customers
         WHERE id = {$customerId}
         LIMIT 1"
    );

    if (!is_array($customerRow) || empty($customerRow['id'])) {
        $db->query('ROLLBACK');
        return ['ok' => false, 'message' => 'Customer not found.'];
    }

    $currentBalance = round((float)($customerRow['balance_amount'] ?? 0), 2);
    $delta = round($amount, 2);
    if ($direction === 'debit' && $currentBalance + 0.00001 < $delta) {
        $db->query('ROLLBACK');
        return ['ok' => false, 'message' => 'Customer balance is too low for this action.'];
    }

    $newBalance = $direction === 'credit'
        ? round($currentBalance + $delta, 2)
        : round($currentBalance - $delta, 2);

    $updated = $db->update_using_id(
        ['balance_amount'],
        [number_format($newBalance, 2, '.', '')],
        'customers',
        $customerId
    );

    if (!$updated) {
        $db->query('ROLLBACK');
        return ['ok' => false, 'message' => 'Unable to update customer balance.'];
    }

    $inserted = $db->insert(
        ['customer_id', 'source_type', 'source_key', 'direction', 'amount', 'note', 'created_by_admin_user_id'],
        [$customerId, $sourceType, $sourceKey, $direction, number_format($delta, 2, '.', ''), $note !== '' ? $note : null, $adminUserId > 0 ? $adminUserId : null],
        'customer_balance_runtime_events'
    );

    if (!$inserted) {
        $db->query('ROLLBACK');
        return ['ok' => false, 'message' => 'Unable to save customer balance event.'];
    }

    $db->query('COMMIT');

    $description = ($direction === 'debit' ? 'Balance debited automatically: ' : 'Balance credited automatically: ')
        . number_format($delta, 2, '.', '');
    if ($note !== '') {
        $description .= ' (' . $note . ')';
    }

    app_customer_activity_log(
        $db,
        $customerId,
        $direction === 'debit' ? 'balance_debit' : 'balance_credit',
        $description,
        $actorType,
        $adminUserId,
        $ipAddress
    );

    return [
        'ok' => true,
        'already_applied' => false,
        'new_balance' => number_format($newBalance, 2, '.', ''),
        'message' => 'Customer balance updated.',
    ];
}

function app_reset_dashboard_sample_data(Mysql_ks $db, ?string $now = null): array
{
    $safeNow = trim((string)$now);
    if ($safeNow === '') {
        $safeNow = date('Y-m-d H:i:s');
    }

    if (!schema_object_exists($db, 'customers')) {
        return ['ok' => false, 'time' => $safeNow, 'message' => 'Customers table is not available.'];
    }

    $sampleCustomers = $db->select_full_user(
        "SELECT id
         FROM customers
         WHERE legacy_source_table = 'dashboard_sample_customer'
            OR email LIKE 'dashboard-sample-%@example.test'"
    );
    $sampleOrders = schema_object_exists($db, 'orders')
        ? $db->select_full_user(
            "SELECT id
             FROM orders
             WHERE legacy_source_table = 'dashboard_sample_order'"
        )
        : [];

    $customerIds = array_values(array_filter(array_map(static function (array $row): int {
        return (int)($row['id'] ?? 0);
    }, $sampleCustomers)));
    $orderIds = array_values(array_filter(array_map(static function (array $row): int {
        return (int)($row['id'] ?? 0);
    }, $sampleOrders)));

    if (!$customerIds && !$orderIds) {
        return ['ok' => true, 'time' => $safeNow, 'message' => 'No sample data found.', 'deleted' => []];
    }

    $deleted = [
        'support_messages' => 0,
        'support_conversations' => 0,
        'outbound_email_queue' => 0,
        'crypto_deposit_transactions' => 0,
        'crypto_deposit_requests' => 0,
        'bank_transfer_requests' => 0,
        'order_status_events' => 0,
        'customer_activity_logs' => 0,
        'crypto_wallet_assignments' => 0,
        'bank_account_assignments' => 0,
        'orders' => 0,
        'customers' => 0,
    ];

    $db->start();

    $safeDelete = static function (string $table, string $column, array $ids) use ($db): ?int {
        $result = app_delete_records_by_ids($db, $table, $column, $ids);
        return $result;
    };

    $conversationIds = [];
    if ($customerIds && schema_object_exists($db, 'support_conversations') && schema_column_exists($db, 'support_conversations', 'customer_id')) {
        $rows = $db->select_full_user(
            "SELECT id
             FROM support_conversations
             WHERE customer_id IN (" . implode(',', $customerIds) . ")"
        );
        $conversationIds = array_values(array_filter(array_map(static function (array $row): int {
            return (int)($row['id'] ?? 0);
        }, $rows)));
    }

    $cryptoRequestIds = [];
    if (schema_object_exists($db, 'crypto_deposit_requests')) {
        $where = [];
        if ($customerIds && schema_column_exists($db, 'crypto_deposit_requests', 'customer_id')) {
            $where[] = 'customer_id IN (' . implode(',', $customerIds) . ')';
        }
        if ($orderIds && schema_column_exists($db, 'crypto_deposit_requests', 'order_id')) {
            $where[] = 'order_id IN (' . implode(',', $orderIds) . ')';
        }
        if ($where) {
            $rows = $db->select_full_user(
                "SELECT id
                 FROM crypto_deposit_requests
                 WHERE " . implode(' OR ', $where)
            );
            $cryptoRequestIds = array_values(array_filter(array_map(static function (array $row): int {
                return (int)($row['id'] ?? 0);
            }, $rows)));
        }
    }

    if ($conversationIds) {
        $deletedMessages = $safeDelete('support_messages', 'conversation_id', $conversationIds);
        $deletedConversations = $safeDelete('support_conversations', 'id', $conversationIds);
        if ($deletedMessages === null || $deletedConversations === null) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'time' => $safeNow, 'message' => 'Unable to remove sample support conversations.'];
        }
        $deleted['support_messages'] = $deletedMessages;
        $deleted['support_conversations'] = $deletedConversations;
    }

    if ($cryptoRequestIds) {
        $deletedCryptoTransactions = $safeDelete('crypto_deposit_transactions', 'request_id', $cryptoRequestIds);
        if ($deletedCryptoTransactions === null) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'time' => $safeNow, 'message' => 'Unable to remove sample crypto transactions.'];
        }
        $deleted['crypto_deposit_transactions'] = $deletedCryptoTransactions;
    }

    if ($customerIds) {
        $deletedQueueByCustomer = $safeDelete('outbound_email_queue', 'customer_id', $customerIds);
        $deletedHistoryLogs = $safeDelete('customer_activity_logs', 'customer_id', $customerIds);
        $deletedWalletAssignments = $safeDelete('crypto_wallet_assignments', 'customer_id', $customerIds);
        $deletedBankAssignments = $safeDelete('bank_account_assignments', 'customer_id', $customerIds);
        if ($deletedQueueByCustomer === null || $deletedHistoryLogs === null || $deletedWalletAssignments === null || $deletedBankAssignments === null) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'time' => $safeNow, 'message' => 'Unable to remove sample customer-related records.'];
        }
        $deleted['outbound_email_queue'] += $deletedQueueByCustomer;
        $deleted['customer_activity_logs'] = $deletedHistoryLogs;
        $deleted['crypto_wallet_assignments'] = $deletedWalletAssignments;
        $deleted['bank_account_assignments'] = $deletedBankAssignments;
    }

    if ($orderIds) {
        $deletedQueueByOrder = $safeDelete('outbound_email_queue', 'order_id', $orderIds);
        $deletedStatusEvents = $safeDelete('order_status_events', 'order_id', $orderIds);
        if ($deletedQueueByOrder === null || $deletedStatusEvents === null) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'time' => $safeNow, 'message' => 'Unable to remove sample order-related records.'];
        }
        $deleted['outbound_email_queue'] += $deletedQueueByOrder;
        $deleted['order_status_events'] = $deletedStatusEvents;
    }

    if ($cryptoRequestIds) {
        $deletedCryptoRequests = $safeDelete('crypto_deposit_requests', 'id', $cryptoRequestIds);
        if ($deletedCryptoRequests === null) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'time' => $safeNow, 'message' => 'Unable to remove sample crypto requests.'];
        }
        $deleted['crypto_deposit_requests'] = $deletedCryptoRequests;
    }

    if ($customerIds || $orderIds) {
        $bankWhere = [];
        if ($customerIds && schema_object_exists($db, 'bank_transfer_requests') && schema_column_exists($db, 'bank_transfer_requests', 'customer_id')) {
            $bankWhere[] = 'customer_id IN (' . implode(',', $customerIds) . ')';
        }
        if ($orderIds && schema_object_exists($db, 'bank_transfer_requests') && schema_column_exists($db, 'bank_transfer_requests', 'order_id')) {
            $bankWhere[] = 'order_id IN (' . implode(',', $orderIds) . ')';
        }
        if ($bankWhere) {
            $result = $db->query("DELETE FROM bank_transfer_requests WHERE " . implode(' OR ', $bankWhere));
            if (!$result) {
                $db->query('ROLLBACK');
                return ['ok' => false, 'time' => $safeNow, 'message' => 'Unable to remove sample bank transfer requests.'];
            }
            $deleted['bank_transfer_requests'] = (int)$db->affected_rows;
        }
    }

    if ($orderIds) {
        $deletedOrders = $safeDelete('orders', 'id', $orderIds);
        if ($deletedOrders === null) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'time' => $safeNow, 'message' => 'Unable to remove sample orders.'];
        }
        $deleted['orders'] = $deletedOrders;
    }

    if ($customerIds) {
        $deletedCustomers = $safeDelete('customers', 'id', $customerIds);
        if ($deletedCustomers === null) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'time' => $safeNow, 'message' => 'Unable to remove sample customers.'];
        }
        $deleted['customers'] = $deletedCustomers;
    }

    if (schema_object_exists($db, 'crypto_wallet_addresses')) {
        $db->query(
            "UPDATE crypto_wallet_addresses
             SET status = 'available'
             WHERE disabled_at IS NULL
               AND id NOT IN (
                    SELECT wallet_address_id
                    FROM crypto_wallet_assignments
                    WHERE status IN ('reserved', 'active')
               )"
        );
    }

    $db->commit();

    return [
        'ok' => true,
        'time' => $safeNow,
        'deleted' => $deleted,
        'message' => 'Sample/test data has been removed.',
    ];
}

function app_find_customer_by_email(Mysql_ks $db, string $email): ?array
{
    $safeEmail = $db->escape($email);
    $tableName = app_uses_v2_schema($db) ? 'customers' : 'users';
    if ($tableName === 'customers') {
        app_ensure_customer_runtime_columns($db);
    }
    $customer = $db->select($tableName, '*', "WHERE email = '{$safeEmail}' LIMIT 1");
    return is_array($customer) ? app_normalize_customer_record($customer) : null;
}

function app_find_customer_by_id(Mysql_ks $db, int $customerId): ?array
{
    if ($customerId <= 0) {
        return null;
    }

    $tableName = app_uses_v2_schema($db) ? 'customers' : 'users';
    if ($tableName === 'customers') {
        app_ensure_customer_runtime_columns($db);
    }
    $customer = $db->select_using_id($tableName, '*', $customerId);
    return is_array($customer) ? app_normalize_customer_record($customer) : null;
}

function app_csrf_token(string $scope = 'frontend'): string
{
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        return '';
    }

    $sessionKey = '__csrf_' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower($scope));
    $token = isset($_SESSION[$sessionKey]) ? (string)$_SESSION[$sessionKey] : '';

    if ($token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION[$sessionKey] = $token;
    }

    return $token;
}

function app_csrf_is_valid(?string $token, string $scope = 'frontend'): bool
{
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        return false;
    }

    $sessionKey = '__csrf_' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower($scope));
    $sessionToken = isset($_SESSION[$sessionKey]) ? (string)$_SESSION[$sessionKey] : '';
    $token = (string)$token;

    return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
}

function app_customer_status(array $customer): string
{
    if (isset($customer['status']) && is_string($customer['status'])) {
        return strtolower(trim($customer['status']));
    }

    $legacyStatus = (int)($customer['status'] ?? 0);
    if ($legacyStatus === 1) {
        return 'active';
    }
    if ($legacyStatus === 2) {
        return 'blocked';
    }

    return 'inactive';
}

function app_customer_is_active(array $customer): bool
{
    return app_customer_status($customer) === 'active';
}

function app_customer_is_blocked(array $customer): bool
{
    return app_customer_status($customer) === 'blocked';
}

function app_verify_customer_password(array $customer, string $plainPassword): bool
{
    if (isset($customer['password_hash'])) {
        $algorithm = (string)($customer['password_hash_algorithm'] ?? 'legacy_sha1');
        $storedHash = (string)$customer['password_hash'];

        if ($algorithm === 'password_hash') {
            return password_verify($plainPassword, $storedHash);
        }

        return sha1('SOOOL' . $plainPassword) === $storedHash;
    }

    if (isset($customer['password'])) {
        return sha1('SOOOL' . $plainPassword) === (string)$customer['password'];
    }

    return false;
}

function app_generate_customer_password(int $length = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $alphabetLength = strlen($alphabet);
    $length = max(10, min(32, $length));
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $alphabetLength - 1)];
    }

    return $password;
}

function app_store_customer_password(Mysql_ks $db, int $customerId, string $plainPassword): bool
{
    if (app_uses_v2_schema($db)) {
        return (bool)$db->update_using_id(
            ['password_hash', 'password_hash_algorithm'],
            [password_hash($plainPassword, PASSWORD_DEFAULT), 'password_hash'],
            'customers',
            $customerId
        );
    }

    return (bool)$db->update_using_id(['password'], [sha1('SOOOL' . $plainPassword)], 'users', $customerId);
}

function app_restore_customer_password_snapshot(Mysql_ks $db, array $customer): bool
{
    $customerId = (int)($customer['id'] ?? 0);
    if ($customerId <= 0) {
        return false;
    }

    if (app_uses_v2_schema($db)) {
        if (!isset($customer['password_hash'])) {
            return false;
        }

        $algorithm = trim((string)($customer['password_hash_algorithm'] ?? 'password_hash'));
        if ($algorithm === '') {
            $algorithm = 'password_hash';
        }

        return (bool)$db->update_using_id(
            ['password_hash', 'password_hash_algorithm'],
            [(string)$customer['password_hash'], $algorithm],
            'customers',
            $customerId
        );
    }

    if (!isset($customer['password'])) {
        return false;
    }

    return (bool)$db->update_using_id(['password'], [(string)$customer['password']], 'users', $customerId);
}

function app_activate_customer(Mysql_ks $db, int $customerId): void
{
    if (app_uses_v2_schema($db)) {
        $db->update_using_id(
            ['status', 'email_verified_at'],
            ['active', date('Y-m-d H:i:s')],
            'customers',
            $customerId
        );
        return;
    }

    $db->update_using_id(['status'], [1], 'users', $customerId);
}

function app_update_customer_locale(Mysql_ks $db, int $customerId, string $localeCode): void
{
    if (app_uses_v2_schema($db)) {
        $db->update_using_id(['locale_code'], [$localeCode], 'customers', $customerId);
        return;
    }

    $db->update_using_id(['lang'], [localization_to_legacy_value($localeCode)], 'users', $customerId);
}

function app_update_customer_login_state(Mysql_ks $db, int $customerId, string $currentTime, string $clientIp): void
{
    if (app_uses_v2_schema($db)) {
        $db->update_using_id(['last_login_at', 'ip_address'], [$currentTime, $clientIp], 'customers', $customerId);
        return;
    }

    $db->update_using_id(['last_login', 'ip'], [$currentTime, $clientIp], 'users', $customerId);
}

function app_load_customer_session_record(Mysql_ks $db, int $customerId): ?array
{
    if ($customerId <= 0) {
        return null;
    }

    if (app_uses_v2_schema($db)) {
        app_ensure_customer_runtime_columns($db);
        $query = "
            SELECT
                customers.*,
                1 AS res_id,
                (
                    SELECT COUNT(id)
                    FROM orders
                    WHERE orders.customer_id = customers.id
                      AND orders.payment_status = 'paid'
                ) AS count_paid
            FROM customers
            WHERE customers.id = {$customerId}
            LIMIT 1
        ";
    } else {
        $query = "
            SELECT
                users.*,
                (
                    SELECT COUNT(id)
                    FROM products_users
                    WHERE products_users.user_id = users.id
                      AND payment = 1
                      AND shipment = 1
                ) AS count_paid
            FROM users
            WHERE id = {$customerId}
            LIMIT 1
        ";
    }

    $customer = $db->select_user($query);
    return is_array($customer) ? app_normalize_customer_record($customer) : null;
}

function app_recent_news_count(Mysql_ks $db, int $tenantId): int
{
    $stats = app_recent_news_stats($db, $tenantId);
    return (int)($stats['count'] ?? 0);
}

function app_current_datetime_string(): string
{
    return date('Y-m-d H:i:s');
}

function app_recent_news_window_seconds(): int
{
    return 86400;
}

function app_recent_news_stats(Mysql_ks $db, int $tenantId): array
{
    return app_recent_news_stats_for_customer($db, $tenantId, []);
}

function app_recent_news_stats_for_customer(Mysql_ks $db, int $tenantId, array $customer = []): array
{
    $visibleNow = $db->escape(app_current_datetime_string());
    $recentCutoff = $db->escape(date('Y-m-d H:i:s', time() - app_recent_news_window_seconds()));

    if (app_uses_v2_schema($db)) {
        $visibilityList = app_sql_string_list($db, app_customer_news_visibilities($customer));
        $row = $db->select_user(
            "SELECT COUNT(id) AS total, MAX(published_at) AS latest_at
             FROM news_posts
             WHERE is_active = 1
               AND visibility IN ({$visibilityList})
               AND published_at >= '{$recentCutoff}'
               AND published_at <= '{$visibleNow}'"
        );

        return [
            'count' => isset($row['total']) ? (int)$row['total'] : 0,
            'latest_at' => isset($row['latest_at']) ? trim((string)$row['latest_at']) : '',
        ];
    }

    $row = $db->select_user(
        "SELECT COUNT(id) AS total, MAX(date) AS latest_at
         FROM resellers_news
         WHERE status = 1
           AND res_id = {$tenantId}
           AND date >= '{$recentCutoff}'
           AND date <= '{$visibleNow}'"
    );

    return [
        'count' => isset($row['total']) ? (int)$row['total'] : 0,
        'latest_at' => isset($row['latest_at']) ? trim((string)$row['latest_at']) : '',
    ];
}

function app_history_activity_description(array $entry, array $messages): string
{
    $actionKey = trim((string)($entry['action_key'] ?? ''));
    $description = trim((string)($entry['description'] ?? ''));

    switch ($actionKey) {
        case 'login':
            return localization_translate($messages, 'history_event_login');

        case 'profile_updated':
            return localization_translate($messages, 'history_event_profile_updated');

        case 'balance_credit':
            if (preg_match('/:\s*(.+)$/', $description, $matches)) {
                return localization_translate($messages, 'history_event_balance_credit', ['details' => $matches[1]]);
            }
            return localization_translate($messages, 'history_event_balance_credit_simple');

        case 'balance_debit':
            if (preg_match('/:\s*(.+)$/', $description, $matches)) {
                return localization_translate($messages, 'history_event_balance_debit', ['details' => $matches[1]]);
            }
            return localization_translate($messages, 'history_event_balance_debit_simple');
    }

    return $description !== ''
        ? $description
        : localization_translate($messages, 'history_event_account_update');
}

function app_customer_history_rows(Mysql_ks $db, int $customerId, array $messages, array $settings = []): array
{
    if ($customerId <= 0) {
        return [];
    }

    if (!app_uses_v2_schema($db)) {
        return [];
    }

    $history = [];
    $visibleFromRaw = trim((string)($settings['history_visible_from'] ?? ''));
    $visibleFromTimestamp = $visibleFromRaw !== '' ? (strtotime($visibleFromRaw) ?: 0) : 0;

    if (schema_object_exists($db, 'customer_activity_logs')) {
        $activityRows = $db->select_full_user(
            "SELECT action_key, description, created_at
             FROM customer_activity_logs
             WHERE customer_id = {$customerId}
             ORDER BY id DESC"
        );

        foreach ($activityRows as $row) {
            $eventDate = trim((string)($row['created_at'] ?? ''));
            if (!app_history_is_visible($eventDate, $visibleFromTimestamp)) {
                continue;
            }

            $actionKey = trim((string)($row['action_key'] ?? ''));
            $badgeLabel = localization_translate($messages, 'history_badge_activity');
            $badgeModifier = 'activity';
            if ($actionKey === 'login') {
                $badgeLabel = localization_translate($messages, 'history_badge_login');
                $badgeModifier = 'login';
            } elseif ($actionKey === 'balance_credit' || $actionKey === 'balance_debit') {
                $badgeLabel = localization_translate($messages, 'history_badge_balance');
                $badgeModifier = 'balance';
            } elseif ($actionKey === 'profile_updated') {
                $badgeLabel = localization_translate($messages, 'history_badge_profile');
                $badgeModifier = 'profile';
            }

            $history[] = [
                'desc' => app_history_event_summary(
                    $badgeLabel,
                    $badgeModifier,
                    app_history_activity_description($row, $messages)
                ),
                'is_html' => true,
                'date' => $eventDate,
                'sort_time' => strtotime($eventDate) ?: 0,
            ];
        }
    }

    if (schema_object_exists($db, 'orders')) {
        $orderRows = $db->select_full_user(
            "SELECT orders.id, orders.created_at, orders.started_at, orders.paid_at, products.name AS product_name
             FROM orders
             LEFT JOIN products ON products.id = orders.product_id
             WHERE orders.customer_id = {$customerId}
             ORDER BY orders.id DESC"
        );

        foreach ($orderRows as $row) {
            $orderId = (int)($row['id'] ?? 0);
            $productName = trim((string)($row['product_name'] ?? ''));
            $orderLabel = '#' . $orderId . ($productName !== '' ? ' · ' . $productName : '');

            $createdAt = trim((string)($row['created_at'] ?? ''));
            if (app_history_is_visible($createdAt, $visibleFromTimestamp)) {
                $history[] = [
                    'desc' => app_history_event_summary(
                        localization_translate($messages, 'history_badge_order'),
                        'order',
                        localization_translate($messages, 'history_event_order_created', ['order' => $orderLabel])
                    ),
                    'is_html' => true,
                    'date' => $createdAt,
                    'sort_time' => strtotime($createdAt) ?: 0,
                ];
            }

            $startedAt = trim((string)($row['started_at'] ?? ''));
            if ($startedAt !== '' && $startedAt !== $createdAt && app_history_is_visible($startedAt, $visibleFromTimestamp)) {
                $history[] = [
                    'desc' => app_history_event_summary(
                        localization_translate($messages, 'history_badge_subscription'),
                        'subscription',
                        localization_translate($messages, 'history_event_subscription_started', ['order' => $orderLabel])
                    ),
                    'is_html' => true,
                    'date' => $startedAt,
                    'sort_time' => strtotime($startedAt) ?: 0,
                ];
            }

            $paidAt = trim((string)($row['paid_at'] ?? ''));
            if (app_history_is_visible($paidAt, $visibleFromTimestamp)) {
                $history[] = [
                    'desc' => app_history_event_summary(
                        localization_translate($messages, 'history_badge_payment'),
                        'payment',
                        localization_translate($messages, 'history_event_order_paid', ['order' => $orderLabel])
                    ),
                    'is_html' => true,
                    'date' => $paidAt,
                    'sort_time' => strtotime($paidAt) ?: 0,
                ];
            }
        }
    }

    if (schema_object_exists($db, 'crypto_deposit_requests')) {
        $cryptoRows = $db->select_full_user(
            "SELECT
                crypto_deposit_requests.order_id,
                crypto_deposit_requests.requested_fiat_amount,
                crypto_deposit_requests.requested_crypto_amount,
                crypto_deposit_requests.requested_at,
                crypto_assets.code AS asset_code,
                currencies.symbol AS currency_symbol,
                currencies.code AS currency_code
             FROM crypto_deposit_requests
             LEFT JOIN crypto_assets ON crypto_assets.id = crypto_deposit_requests.crypto_asset_id
             LEFT JOIN currencies ON currencies.id = crypto_deposit_requests.fiat_currency_id
             WHERE crypto_deposit_requests.customer_id = {$customerId}
             ORDER BY crypto_deposit_requests.id DESC"
        );

        foreach ($cryptoRows as $row) {
            $requestedAt = trim((string)($row['requested_at'] ?? ''));
            if (!app_history_is_visible($requestedAt, $visibleFromTimestamp)) {
                continue;
            }

            $history[] = [
                'desc' => app_history_payment_summary(
                    $messages,
                    localization_translate($messages, 'history_badge_crypto'),
                    'crypto',
                    '#' . (int)($row['order_id'] ?? 0),
                    app_format_money_value(
                        (float)($row['requested_fiat_amount'] ?? 0),
                        (string)($row['currency_symbol'] ?? ''),
                        (string)($row['currency_code'] ?? '')
                    ),
                    trim(
                        rtrim(rtrim(number_format((float)($row['requested_crypto_amount'] ?? 0), 8, '.', ''), '0'), '.')
                        . ' '
                        . (string)($row['asset_code'] ?? '')
                    )
                ),
                'is_html' => true,
                'date' => $requestedAt,
                'sort_time' => strtotime($requestedAt) ?: 0,
            ];
        }
    }

    if (schema_object_exists($db, 'bank_transfer_requests')) {
        $bankRows = $db->select_full_user(
            "SELECT bank_transfer_requests.order_id, bank_transfer_requests.requested_amount, bank_transfer_requests.requested_at,
                    currencies.symbol AS currency_symbol, currencies.code AS currency_code
             FROM bank_transfer_requests
             LEFT JOIN currencies ON currencies.id = bank_transfer_requests.currency_id
             WHERE customer_id = {$customerId}
             ORDER BY id DESC"
        );

        foreach ($bankRows as $row) {
            $requestedAt = trim((string)($row['requested_at'] ?? ''));
            if (!app_history_is_visible($requestedAt, $visibleFromTimestamp)) {
                continue;
            }

            $history[] = [
                'desc' => app_history_payment_summary(
                    $messages,
                    localization_translate($messages, 'history_badge_bank'),
                    'bank',
                    '#' . (int)($row['order_id'] ?? 0),
                    app_format_money_value(
                        (float)($row['requested_amount'] ?? 0),
                        (string)($row['currency_symbol'] ?? ''),
                        (string)($row['currency_code'] ?? '')
                    ),
                    localization_translate($messages, 'history_bank_transfer_label')
                ),
                'is_html' => true,
                'date' => $requestedAt,
                'sort_time' => strtotime($requestedAt) ?: 0,
            ];
        }
    }

    usort($history, static function (array $left, array $right): int {
        return (int)($right['sort_time'] ?? 0) <=> (int)($left['sort_time'] ?? 0);
    });

    foreach ($history as &$entry) {
        unset($entry['sort_time']);
    }
    unset($entry);

    return $history;
}

function app_insert_customer_registration(
    Mysql_ks $db,
    string $email,
    string $plainPassword,
    string $localeCode,
    string $currentTime,
    string $clientIp,
    int $tenantId = 1,
    string $customerType = 'client'
): int {
    if (app_uses_v2_schema($db)) {
        app_ensure_customer_runtime_columns($db);
        $customerType = app_normalize_customer_type($customerType);
        $handle = app_generate_customer_public_handle($db, $email);
        $db->insert(
            [
                'email',
                'public_handle',
                'password_hash',
                'password_hash_algorithm',
                'locale_code',
                'ip_address',
                'status',
                'customer_type',
                'email_verified_at',
                'registered_at',
                'last_login_at',
            ],
            [
                $email,
                $handle,
                password_hash($plainPassword, PASSWORD_DEFAULT),
                'password_hash',
                $localeCode,
                $clientIp,
                'active',
                $customerType,
                $currentTime,
                $currentTime,
                $currentTime,
            ],
            'customers'
        );

        return (int)$db->id();
    }

    $db->insert(
        ['res_id', 'email', 'password', 'last_login', 'ip', 'country', 'lang', 'status'],
        [$tenantId, $email, sha1('SOOOL' . $plainPassword), $currentTime, $clientIp, '', localization_to_legacy_value($localeCode), 1],
        'users'
    );

    return (int)$db->id();
}
