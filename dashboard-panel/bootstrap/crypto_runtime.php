<?php
/** Shared, bounded public market/explorer transport. No database work or sleeping. */
declare(strict_types=1);

function app_crypto_api_policy(string $url): ?array
{
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) return null;
    $host = strtolower((string)($parts['host'] ?? ''));
    $policies = [
        'bitpay.com' => [60 => 6, 3600 => 100],
        'api.coingecko.com' => [60 => 10, 86400 => 500, 2592000 => 8000],
        'api.ethplorer.io' => [1 => 2, 60 => 20, 3600 => 100, 86400 => 1000, 604800 => 3000],
        'api.blockcypher.com' => [1 => 3, 3600 => 150, 86400 => 1500],
        'mempool.space' => [60 => 30, 3600 => 500],
        'blockchain.info' => [60 => 20, 3600 => 300],
        'opreturn.net' => [60 => 20, 3600 => 300],
        'api.trongrid.io' => [1 => 3, 60 => 30, 86400 => 2000],
        'api.mainnet-beta.solana.com' => [10 => 20, 60 => 60, 3600 => 1000],
    ];
    return isset($policies[$host]) ? ['host' => $host, 'limits' => $policies[$host], 'ttl' => $host === 'bitpay.com' ? 300 : ($host === 'api.coingecko.com' ? 60 : 30)] : null;
}

function app_crypto_runtime_dir(): string
{
    $override = trim((string)getenv('APP_CRYPTO_CACHE_DIR'));
    $root = function_exists('app_backend_root_path') ? app_backend_root_path() : dirname(__DIR__);
    $dir = $override !== '' ? $override : $root . '/storage/crypto-runtime';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
        if ($override === '' && function_exists('posix_geteuid') && posix_geteuid() === 0 && function_exists('posix_getpwnam')) {
            $webUser = posix_getpwnam('www-data');
            if (is_array($webUser)) @chown($dir, (int)$webUser['uid']);
        }
    }
    return $dir;
}

/** A root CLI worker must leave shared private cache files readable by the web owner. */
function app_crypto_runtime_inherit_owner(string $path): void
{
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        $owner = @fileowner(dirname($path));
        if ($owner !== false) @chown($path, $owner);
    }
}

function app_crypto_runtime_lock(string $path)
{
    $handle = @fopen($path, 'c');
    if ($handle) { @chmod($path, 0600); app_crypto_runtime_inherit_owner($path); }
    return $handle;
}

function app_crypto_runtime_read(string $path): array
{
    $size = @filesize($path);
    if (!$size || $size > 4500000) return [];
    $decoded = json_decode((string)@file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function app_crypto_runtime_write(string $path, array $value): bool
{
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json)) return false;
    $tmp = @tempnam(dirname($path), '.crypto-');
    if ($tmp === false) return false;
    @chmod($tmp, 0600);
    app_crypto_runtime_inherit_owner($tmp);
    $ok = @file_put_contents($tmp, $json) !== false && @rename($tmp, $path);
    if (!$ok) @unlink($tmp);
    clearstatcache(true, $path);
    return $ok;
}

function app_crypto_network_budget(float $seconds = 8.0, int $requests = 8): void
{
    $GLOBALS['app_crypto_network_budget'] = ['deadline' => microtime(true) + max(0, $seconds), 'remaining' => max(0, $requests)];
}

function app_crypto_transport(string $url, ?array $payload, int $timeoutMs): array
{
    if (!function_exists('curl_init')) return ['status' => 0, 'body' => '', 'retry_after' => 0];
    $body = ''; $retryAfter = 0; $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT_MS => min(2500, $timeoutMs),
        CURLOPT_TIMEOUT_MS => $timeoutMs,
        CURLOPT_HTTPHEADER => ['Accept: application/json, text/plain', 'Content-Type: application/json', 'User-Agent: Reseller-Crypto/1.0'],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 2097152) return 0;
            $body .= $chunk; return strlen($chunk);
        },
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$retryAfter): int {
            if (stripos($line, 'Retry-After:') === 0) {
                $value = trim(substr($line, 12));
                $retryAfter = ctype_digit($value) ? (int)$value : max(0, (strtotime($value) ?: time()) - time());
            }
            return strlen($line);
        },
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
    $ok = curl_exec($ch); $status = $ok === false ? 0 : (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $status === 200 ? $body : '', 'retry_after' => $retryAfter];
}

/** Tests may supply a transport/clock; HTTP entry points never accept these from requests. */
function app_crypto_observe(string $host, string $reason): void
{
    $GLOBALS['app_crypto_api_observations'][] = ['host' => $host, 'reason' => $reason];
}

function app_crypto_api_request(string $url, ?array $payload = null, bool $text = false, int $timeoutSeconds = 8, ?callable $transport = null, ?float $clock = null): ?string
{
    $policy = app_crypto_api_policy($url);
    if ($policy === null) return null;
    $weight = $policy['host'] === 'api.mainnet-beta.solana.com' && is_array($payload) && array_is_list($payload) ? max(1, count($payload)) : 1;
    if ($weight > 20) return null;
    // Canonicalize equivalent CoinGecko batches so admin and customer share one cache.
    if ($policy['host'] === 'api.coingecko.com') {
        $parts = parse_url($url); parse_str((string)($parts['query'] ?? ''), $query);
        foreach (['ids', 'vs_currencies'] as $key) if (isset($query[$key])) { $items = array_unique(explode(',', $query[$key])); sort($items); $query[$key] = implode(',', $items); }
        ksort($query); $url = 'https://' . $policy['host'] . ($parts['path'] ?? '/') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
    $now = $clock ?? microtime(true); $dir = app_crypto_runtime_dir();
    $key = hash('sha256', $url . '|' . json_encode($payload) . '|' . (int)$text);
    $path = $dir . '/request-' . $key . '.json';
    $cached = app_crypto_runtime_read($path);
    if (($cached['expires'] ?? 0) > $now) { if (!isset($cached['body'])) app_crypto_observe($policy['host'], 'cooldown'); return isset($cached['body']) && is_string($cached['body']) ? $cached['body'] : null; }
    $requestLock = app_crypto_runtime_lock($dir . '/request-lock-' . substr($key, 0, 2) . '.lock');
    if (!$requestLock || !flock($requestLock, LOCK_EX | LOCK_NB)) { if ($requestLock) fclose($requestLock); app_crypto_observe($policy['host'], 'busy'); return null; }
    $providerSlot = null;
    try {
        clearstatcache(true, $path); $cached = app_crypto_runtime_read($path);
        if (($cached['expires'] ?? 0) > $now) { if (!isset($cached['body'])) app_crypto_observe($policy['host'], 'cooldown'); return isset($cached['body']) && is_string($cached['body']) ? $cached['body'] : null; }
        if (!isset($GLOBALS['app_crypto_network_budget'])) app_crypto_network_budget();
        $budget = &$GLOBALS['app_crypto_network_budget'];
        if ($budget['remaining'] < $weight || $budget['deadline'] <= microtime(true)) { app_crypto_observe($policy['host'], 'budget'); return null; }
        for ($slot = 0; $slot < 2; $slot++) {
            $handle = app_crypto_runtime_lock($dir . '/slot-' . $policy['host'] . '-' . $slot . '.lock');
            if ($handle && flock($handle, LOCK_EX | LOCK_NB)) { $providerSlot = $handle; break; }
            if ($handle) fclose($handle);
        }
        if (!$providerSlot) { app_crypto_observe($policy['host'], 'busy'); return null; }
        $hostPath = $dir . '/host-' . $policy['host'] . '.json';
        $hostLock = app_crypto_runtime_lock($dir . '/host-' . $policy['host'] . '.lock');
        if (!$hostLock || !flock($hostLock, LOCK_EX | LOCK_NB)) { if ($hostLock) fclose($hostLock); app_crypto_observe($policy['host'], 'busy'); return null; }
        try {
            $state = app_crypto_runtime_read($hostPath);
            if (($state['blocked_until'] ?? 0) > $now) { app_crypto_observe($policy['host'], 'cooldown'); return null; }
            $maxWindow = max(array_keys($policy['limits']));
            $events = array_values(array_filter($state['events'] ?? [], static fn($t): bool => is_numeric($t) && $t > $now - $maxWindow && $t <= $now));
            foreach ($policy['limits'] as $window => $limit) {
                $count = 0; foreach ($events as $t) if ($t > $now - $window) $count++;
                if ($count + $weight > $limit) { app_crypto_observe($policy['host'], 'rate_limit'); return null; }
            }
            for ($i = 0; $i < $weight; $i++) $events[] = $now;
            $state['events'] = $events;
            if (!app_crypto_runtime_write($hostPath, $state)) return null;
        } finally { flock($hostLock, LOCK_UN); fclose($hostLock); }
        $budget['remaining'] -= $weight;
        $timeoutMs = max(1, (int)min(max(1, $timeoutSeconds) * 1000, 6000, ($budget['deadline'] - microtime(true)) * 1000));
        $response = ($transport ?? 'app_crypto_transport')($url, $payload, $timeoutMs);
        $raw = $response['body'] ?? ''; $status = (int)($response['status'] ?? 0);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $valid = $status === 200 && is_string($raw) && $raw !== '' && strlen($raw) <= 2097152
            && ($text ? is_numeric(trim($raw)) : (is_array($decoded) && !isset($decoded['error'])));
        if ($lock = app_crypto_runtime_lock($dir . '/host-' . $policy['host'] . '.lock')) {
            if (flock($lock, LOCK_EX)) {
                $state = app_crypto_runtime_read($hostPath);
                $failures = $valid ? 0 : min(6, (int)($state['failures'] ?? 0) + 1);
                $state['failures'] = $failures;
                $state['blocked_until'] = $valid ? 0 : $now + min(3600, max($status === 429 ? 60 : 15, (int)($response['retry_after'] ?? 0), 15 * (2 ** max(0, $failures - 1))));
                app_crypto_runtime_write($hostPath, $state); flock($lock, LOCK_UN);
            }
            fclose($lock);
        }
        app_crypto_runtime_write($path, ['expires' => $now + ($valid ? $policy['ttl'] : 30), 'body' => $valid ? $raw : null]);
        return $valid ? $raw : null;
    } finally {
        if ($providerSlot) { flock($providerSlot, LOCK_UN); fclose($providerSlot); }
        flock($requestLock, LOCK_UN); fclose($requestLock);
    }
}

function app_crypto_rate_is_usable(array $row, string $currency, int $maxAge = 3600): bool
{
    $price = (float)($row['current_rate_fiat'] ?? 0);
    $timestamp = function_exists('app_timestamp_from_utc_datetime') ? app_timestamp_from_utc_datetime((string)($row['rate_updated_at'] ?? '')) : strtotime((string)($row['rate_updated_at'] ?? '') . ' UTC');
    return is_finite($price) && $price > 0 && $price < 1e15
        && strtoupper((string)($row['rate_currency_code'] ?? '')) === strtoupper($currency)
        && $timestamp > 0 && $timestamp <= time() + 60 && $timestamp >= time() - max(60, $maxAge);
}


function app_crypto_runtime_prune(int $limit = 256): void
{
    $dir = app_crypto_runtime_dir();
    if (!is_dir($dir)) return;
    $removed = 0;
    $deadline = microtime(true) + 0.05;
    foreach ([$dir, $dir . '/previews'] as $directory) {
        if (!is_dir($directory)) continue;
        foreach (new DirectoryIterator($directory) as $file) {
            if ($removed >= max(1, $limit) || microtime(true) >= $deadline) return;
            if ($file->isDot() || !$file->isFile()) continue;
            if (preg_match('/^(?:(?:request|rates|preview)-[a-f0-9]{64}|wallet-maintenance-[a-f0-9]{16}-[0-9]+)\.json$/D', $file->getFilename()) && $file->getMTime() < time() - 604800) {
                if (@unlink($file->getPathname())) $removed++;
            }
        }
    }
}

/** Currency-specific snapshots keep USD tools from rewriting the shop's EUR rates. */
function app_crypto_cached_prices(array $ids, string $currency, int $ttl): ?array
{
    $ids = array_values(array_unique(array_filter($ids))); sort($ids);
    $url = 'https://api.coingecko.com/api/v3/simple/price?ids=' . rawurlencode(implode(',', $ids))
        . '&vs_currencies=' . rawurlencode(strtolower($currency)) . '&include_last_updated_at=true&precision=full';
    $path = app_crypto_runtime_dir() . '/rates-' . hash('sha256', $url) . '.json';
    $cached = app_crypto_runtime_read($path);
    if (($cached['expires'] ?? 0) > time() && is_array($cached['prices'] ?? null)) return $cached['prices'];
    $raw = app_crypto_api_request($url);
    $prices = $raw !== null ? json_decode($raw, true) : null;
    if (!is_array($prices)) return null;
    $valid = false;
    foreach ($prices as $price) {
        if (is_array($price) && isset($price[strtolower($currency)]) && is_finite((float)$price[strtolower($currency)]) && (float)$price[strtolower($currency)] > 0) $valid = true;
    }
    if ($valid) app_crypto_runtime_write($path, ['expires' => time() + max(60, min(900, $ttl)), 'prices' => $prices]);
    return $prices;
}

function app_crypto_rate_should_persist($db, string $currency): bool
{
    static $primary = [];
    $key = spl_object_id($db);
    if (!isset($primary[$key])) {
        $row = $db->select_user('SELECT currencies.code FROM app_settings INNER JOIN currencies ON currencies.id = app_settings.default_currency_id WHERE app_settings.id = 1 LIMIT 1');
        $primary[$key] = strtoupper((string)($row['code'] ?? ''));
    }
    return $primary[$key] === strtoupper($currency);
}
