<?php

require_once __DIR__ . '/../bootstrap/localization.php';
require_once __DIR__ . '/../bootstrap/schema.php';
require_once __DIR__ . '/../bootstrap/tenant.php';
require_once __DIR__ . '/../bootstrap/application.php';
require_once __DIR__ . '/../bootstrap/chat.php';
require_once __DIR__ . '/class/validator.php';

$translate = '';

$appRootDir = dirname(__DIR__);
$defaultPublicRoot = realpath($appRootDir . '/../public_html');
$deployedPublicRoot = null;
$publicRootPointer = $appRootDir . '/.public-root-path';
if (is_file($publicRootPointer) && is_readable($publicRootPointer)) {
    $pointerValue = trim((string)file_get_contents($publicRootPointer));
    if ($pointerValue !== '') {
        $resolvedPointerRoot = realpath($pointerValue);
        if ($resolvedPointerRoot !== false && is_dir($resolvedPointerRoot)) {
            $deployedPublicRoot = $resolvedPointerRoot;
        }
    }
}
$publicRootDir = $deployedPublicRoot ?: ($defaultPublicRoot !== false ? $defaultPublicRoot : null);

app_ensure_product_provider_runtime_columns($db);
chat_ensure_group_chat_runtime($db);

$settings = app_fetch_settings($db);
if (!is_array($settings)) {
    $settings = [];
}
$settings['results_per_page'] = isset($settings['results_per_page'])
    ? (int)$settings['results_per_page']
    : (isset($settings['liczba_wynikow']) ? (int)$settings['liczba_wynikow'] : 0);
$settings['smtp_password'] = isset($settings['smtp_password'])
    ? (string)$settings['smtp_password']
    : (isset($settings['smtp_haslo']) ? (string)$settings['smtp_haslo'] : '');
$settings['api_key'] = isset($settings['api_key'])
    ? (string)$settings['api_key']
    : (isset($settings['apikey']) ? (string)$settings['apikey'] : '');
$smarty->assign('settings', $settings);
$smarty->assign('csrf_token', app_csrf_token());

$chatCssPath = $publicRootDir ? realpath($publicRootDir . '/assets/css/messanger.css') : false;
$chatJsPath = $publicRootDir ? realpath($publicRootDir . '/assets/js/messanger.js') : false;
$mainCssPath = $publicRootDir ? realpath($publicRootDir . '/assets/css/style.css') : false;
$chatAssetVersion = 1;
$mainAssetVersion = 1;
if ($mainCssPath !== false && is_file($mainCssPath)) {
    $mainAssetVersion = max($mainAssetVersion, (int)filemtime($mainCssPath));
}
if ($chatCssPath !== false && is_file($chatCssPath)) {
    $chatAssetVersion = max($chatAssetVersion, (int)filemtime($chatCssPath));
}
if ($chatJsPath !== false && is_file($chatJsPath)) {
    $chatAssetVersion = max($chatAssetVersion, (int)filemtime($chatJsPath));
}
$smarty->assign('chat_asset_version', $chatAssetVersion);
$smarty->assign('main_asset_version', $mainAssetVersion);

$currentTime = date('Y-m-d H:i:s');
$currentTimestamp = time();
$currentDate = date('Y-m-d');
$clientIp = isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] !== ''
    ? $_SERVER['REMOTE_ADDR']
    : (getenv('REMOTE_ADDR') ?: '127.0.0.1');

$legacySiteAliases = [
    'kontakt' => 'contact',
    'logowanie' => 'login',
    'rejestracja' => 'register',
    'wyloguj' => 'logout',
    'moje_dane' => 'settings',
    'historia_transakcji' => 'history',
];

$site = isset($_GET['site']) ? trim((string)$_GET['site']) : '';
if (isset($legacySiteAliases[$site])) {
    $site = $legacySiteAliases[$site];
}

if (isset($_GET['ref'])) {
    if (app_referrals_enabled($settings)) {
        $_SESSION['ref'] = (int)$_GET['ref'];
    } else {
        unset($_SESSION['ref']);
    }
}

$requestedLocale = null;
if (isset($_POST['lang'])) {
    $requestedLocale = localization_normalize_locale($_POST['lang']);
} elseif (isset($_GET['lang'])) {
    $requestedLocale = localization_normalize_locale($_GET['lang']);
}

if ($requestedLocale !== null) {
    $_SESSION['lang'] = $requestedLocale;
}

$user = [
    'logged' => 0,
    'wiadomosci' => 0,
    'l_rezerwacji' => 0,
];
$smarty->assign('user', $user);

$reseller = tenant_fetch_installation_profile($db);
$smarty->assign('reseller', $reseller);

if (isset($_SESSION['id'])) {
    $userId = (int)$_SESSION['id'];

    $loadedUser = app_load_customer_session_record($db, $userId);

    if (!is_array($loadedUser) || !isset($loadedUser['id'])) {
        unset($_SESSION['id']);
    } else {
        $user = tenant_normalize_user_record($loadedUser);
        $user['logged'] = 1;
        $user['wiadomosci'] = isset($user['wiadomosci']) ? (int)$user['wiadomosci'] : 0;
        $user['l_rezerwacji'] = isset($user['l_rezerwacji']) ? (int)$user['l_rezerwacji'] : 0;

        $tenantId = tenant_current_id($user);
        if ($tenantId > 0 && !app_uses_v2_schema($db)) {
            $userResellerQuery = "
                SELECT
                    resellers.*,
                    currency.short_name AS currency_short,
                    currency.symbol AS currency_symbol
                FROM resellers
                LEFT JOIN currency ON resellers.currency = currency.id
                WHERE resellers.id = {$tenantId}
                LIMIT 1
            ";
            $loadedReseller = $db->select_user($userResellerQuery);
            if (is_array($loadedReseller)) {
                $reseller = tenant_normalize_reseller_record($loadedReseller);
            }
        }

        $newsStats = app_recent_news_stats_for_customer($db, $tenantId, $user);
        $user['news'] = (int)($newsStats['count'] ?? 0);
        $user['news_count'] = (int)($newsStats['count'] ?? 0);
        $user['news_latest_at'] = (string)($newsStats['latest_at'] ?? '');

        if (schema_object_exists($db, 'user_online')) {
            $onlineEntry = $db->select('user_online', '*', "WHERE user={$userId} AND status=1");
            if (!$onlineEntry) {
                $offlineEntry = $db->select('user_online', '*', "WHERE user={$userId} AND status=0");
                if ($offlineEntry) {
                    $db->update_using_id(['status', 'ip'], [1, $clientIp], 'user_online', $offlineEntry['id']);
                } else {
                    $db->insert(['user', 'ip', 'status'], [$userId, $clientIp, 1], 'user_online');
                }
            }
        }

        app_update_customer_login_state($db, $userId, $currentTime, $clientIp);

        if (schema_object_exists($db, 'history')) {
            $loginLogText = 'Logged.';
            $dailyLogin = $db->select(
                'history',
                'COUNT(id) AS login_today',
                "WHERE user='{$userId}' AND history.date >= CURDATE() AND history.desc='{$loginLogText}'"
            );
            $smarty->assign('check_login', $dailyLogin);

            if (!isset($dailyLogin['login_today']) || !$dailyLogin['login_today']) {
                $db->insert(['user', 'date', 'desc'], [$userId, $currentTime, $loginLogText], 'history');
            }
        } elseif (schema_object_exists($db, 'customer_activity_logs')) {
            $dailyLogin = $db->select_user(
                "SELECT COUNT(id) AS login_today
                 FROM customer_activity_logs
                 WHERE customer_id = {$userId}
                   AND action_key = 'login'
                   AND created_at >= CURDATE()"
            );

            if (!isset($dailyLogin['login_today']) || !(int)$dailyLogin['login_today']) {
                $db->insert(
                    ['customer_id', 'actor_type', 'action_key', 'description', 'ip_address'],
                    [$userId, 'customer', 'login', 'Logged.', $clientIp],
                    'customer_activity_logs'
                );
            }
        }

        if (app_customer_is_blocked($user)) {
            if (schema_object_exists($db, 'user_online')) {
                $onlineEntry = $db->select('user_online', '*', "WHERE user={$userId}");
                if ($onlineEntry) {
                    $db->delete_using_id('user_online', $onlineEntry['id']);
                }
            }

            unset($_SESSION['id']);
            $user = [
                'logged' => 0,
                'wiadomosci' => 0,
                'l_rezerwacji' => 0,
            ];

            $smarty->assign('alert_error', 'This account is blocked.');
            $smarty->display('alert.tpl');
        }
    }
}

if ($site === 'logout' && !empty($user['logged']) && isset($_SESSION['id'])) {
    if (schema_object_exists($db, 'user_online')) {
        $onlineEntry = $db->select('user_online', '*', "WHERE user=" . (int)$_SESSION['id']);
        if ($onlineEntry) {
            $db->delete_using_id('user_online', $onlineEntry['id']);
        }
    }

    unset($_SESSION['id']);
    $user = [
        'logged' => 0,
        'wiadomosci' => 0,
        'l_rezerwacji' => 0,
    ];
}

$defaultLocale = 'en';
if (!isset($_SESSION['lang']) || $_SESSION['lang'] === '') {
    if (!empty($user['logged']) && isset($user['locale_code'])) {
        $defaultLocale = tenant_normalize_locale_code($user['locale_code']);
    } elseif (isset($user['lang'])) {
        $defaultLocale = localization_from_legacy_value($user['lang']);
    } elseif (isset($reseller['locale_code'])) {
        $defaultLocale = tenant_normalize_locale_code($reseller['locale_code']);
    } elseif (isset($reseller['lang'])) {
        $defaultLocale = localization_from_legacy_value($reseller['lang']);
    }

    $_SESSION['lang'] = localization_normalize_locale($defaultLocale);
}

$currentLocale = localization_normalize_locale($_SESSION['lang']);
$localization = localization_load($currentLocale, dirname(__DIR__));
$t = $localization['messages'];

$smarty->assign('t', $t);
$smarty->assign('current_locale', $currentLocale);
$smarty->assign('supported_locales', $localization['supported_locales']);
$smarty->assign('html_lang', $currentLocale);
$smarty->assign('site', $site);
$smarty->assign('today', $currentDate);
$smarty->assign('time_s', $currentTimestamp);

if (
    !empty($user['logged'])
    && app_uses_v2_schema($db)
    && !empty($settings['customer_type_switch_enabled'])
    && isset($_POST['customer_type_switch_submit'])
) {
    if (!app_csrf_is_valid($_POST['_csrf'] ?? '')) {
        $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
    } else {
        $newCustomerType = isset($_POST['customer_type_mode']) && (string)$_POST['customer_type_mode'] === 'reseller'
            ? 'reseller'
            : 'client';

        app_ensure_customer_runtime_columns($db);
        $updatedCustomerType = $db->update_using_id(
            ['customer_type'],
            [$newCustomerType],
            'customers',
            (int)($user['id'] ?? 0)
        );

        if ($updatedCustomerType) {
            $user['customer_type'] = $newCustomerType;
            $user['is_reseller'] = $newCustomerType === 'reseller' ? 1 : 0;
            $user['storefront_product_type'] = app_customer_product_type($user);

            $modeLabelKey = $newCustomerType === 'reseller'
                ? 'customer_type_switch_mode_reseller'
                : 'customer_type_switch_mode_client';

            $smarty->assign(
                'alert',
                localization_translate(
                    $t,
                    'customer_type_switch_saved',
                    ['mode' => localization_translate($t, $modeLabelKey)]
                )
            );
        } else {
            $smarty->assign('alert_error', localization_translate($t, 'customer_type_switch_save_error'));
        }
    }
}

$user['lang_code'] = $currentLocale;
$user = tenant_normalize_user_record($user);
$reseller = tenant_normalize_reseller_record($reseller);
$smarty->assign('user', $user);
$smarty->assign('reseller', $reseller);

$topbarPaymentBanner = null;
if (!empty($user['logged']) && app_uses_v2_schema($db) && isset($user['id'])) {
    $safeNow = date('Y-m-d H:i:s');
    $pendingCryptoRequest = $db->select_user(
        "SELECT
            crypto_deposit_requests.id,
            crypto_deposit_requests.order_id,
            crypto_deposit_requests.expires_at
         FROM crypto_deposit_requests
         WHERE crypto_deposit_requests.customer_id = " . (int)$user['id'] . "
           AND crypto_deposit_requests.status IN ('pending', 'awaiting_confirmation', 'awaiting_review')
           AND crypto_deposit_requests.expires_at IS NOT NULL
           AND crypto_deposit_requests.expires_at > '{$safeNow}'
         ORDER BY crypto_deposit_requests.id DESC
         LIMIT 1"
    );

    if (is_array($pendingCryptoRequest)) {
        $remainingSeconds = max(0, strtotime((string)$pendingCryptoRequest['expires_at']) - $currentTimestamp);
        $topbarPaymentBanner = [
            'mode' => 'pending_crypto',
            'url' => !empty($pendingCryptoRequest['order_id'])
                ? '/order-payment-' . (int)$pendingCryptoRequest['order_id']
                : '/cryptocurrency',
            'remaining_seconds' => $remainingSeconds,
        ];
    }
}
$smarty->assign('topbar_payment_banner', $topbarPaymentBanner);

$time = $currentTime;
$time_s = $currentTimestamp;
$today = $currentDate;
$ip = $clientIp;

?>
