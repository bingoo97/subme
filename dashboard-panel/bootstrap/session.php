<?php

function app_is_https_request()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

function app_session_cookie_lifetime(): int
{
    return 60 * 60 * 24 * 180;
}

function app_session_storage_directory(): string
{
    static $resolvedDirectory = null;
    if (is_string($resolvedDirectory) && $resolvedDirectory !== '') {
        return $resolvedDirectory;
    }

    $backendRoot = realpath(dirname(__DIR__));
    if ($backendRoot === false) {
        $backendRoot = dirname(__DIR__);
    }

    $baseRoot = dirname($backendRoot);
    $publicRootPointer = $backendRoot . '/.public-root-path';
    if (is_file($publicRootPointer) && is_readable($publicRootPointer)) {
        $pointerValue = trim((string)file_get_contents($publicRootPointer));
        if ($pointerValue !== '') {
            $resolvedPublicRoot = realpath($pointerValue);
            if ($resolvedPublicRoot !== false && is_dir($resolvedPublicRoot)) {
                $baseRoot = dirname($resolvedPublicRoot);
            }
        }
    }

    $appKey = preg_replace('/[^a-z0-9._-]+/i', '-', basename($backendRoot)) ?: 'dashboard-panel';
    $sessionDirectory = rtrim($baseRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.subme-runtime' . DIRECTORY_SEPARATOR . $appKey . DIRECTORY_SEPARATOR . 'sessions' . DIRECTORY_SEPARATOR . 'customer';

    if (!is_dir($sessionDirectory)) {
        @mkdir($sessionDirectory, 0775, true);
    }

    $resolvedDirectory = $sessionDirectory;
    return $resolvedDirectory;
}

function app_start_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = app_is_https_request();
    $cookieLifetime = app_session_cookie_lifetime();
    $sessionDirectory = app_session_storage_directory();

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string)$cookieLifetime);
    ini_set('session.cookie_lifetime', (string)$cookieLifetime);

    if (is_dir($sessionDirectory) && is_writable($sessionDirectory)) {
        session_save_path($sessionDirectory);
    }

    session_name('reseller_sid');
    session_set_cookie_params([
        'lifetime' => $cookieLifetime,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
