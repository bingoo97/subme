<?php

function tenant_schema_object_exists(Mysql_ks $db, string $objectName): bool
{
    return schema_object_exists($db, $objectName);
}

function tenant_normalize_locale_code($value): string
{
    if (is_string($value)) {
        $localeCode = strtolower(trim($value));
        if (in_array($localeCode, ['en', 'pl', 'de'], true)) {
            return $localeCode;
        }
    }

    $legacyValue = (int)$value;
    if ($legacyValue === 1) {
        return 'pl';
    }

    if ($legacyValue === 2) {
        return 'de';
    }

    return 'en';
}

function tenant_normalize_active_flag($value): int
{
    if (is_string($value)) {
        $normalizedValue = strtolower(trim($value));
        if ($normalizedValue === 'active') {
            return 1;
        }
        if ($normalizedValue === 'blocked' || $normalizedValue === 'inactive') {
            return 0;
        }
    }

    return (int)$value === 1 ? 1 : 0;
}

function tenant_normalize_reseller_record(array $reseller): array
{
    if (!$reseller) {
        return [];
    }

    if (!isset($reseller['tenant_id'])) {
        $reseller['tenant_id'] = (int)($reseller['id'] ?? 0);
    } else {
        $reseller['tenant_id'] = (int)$reseller['tenant_id'];
    }

    if (!isset($reseller['currency_id'])) {
        $reseller['currency_id'] = (int)($reseller['currency'] ?? 0);
    } else {
        $reseller['currency_id'] = (int)$reseller['currency_id'];
    }

    if (!isset($reseller['registered_at'])) {
        $reseller['registered_at'] = $reseller['date_register'] ?? null;
    }

    if (!isset($reseller['last_login_at'])) {
        $reseller['last_login_at'] = $reseller['last_login'] ?? null;
    }

    if (!isset($reseller['ip_address'])) {
        $reseller['ip_address'] = $reseller['ip'] ?? '';
    }

    if (!isset($reseller['locale_code'])) {
        $reseller['locale_code'] = tenant_normalize_locale_code($reseller['lang'] ?? 'en');
    } else {
        $reseller['locale_code'] = tenant_normalize_locale_code($reseller['locale_code']);
    }

    if (!isset($reseller['is_active'])) {
        $reseller['is_active'] = tenant_normalize_active_flag($reseller['status'] ?? 0);
    } else {
        $reseller['is_active'] = tenant_normalize_active_flag($reseller['is_active']);
    }

    return $reseller;
}

function tenant_normalize_user_record(array $user): array
{
    if (!$user) {
        return [];
    }

    if (!isset($user['tenant_id'])) {
        $user['tenant_id'] = (int)($user['res_id'] ?? 0);
    } else {
        $user['tenant_id'] = (int)$user['tenant_id'];
    }

    if (!isset($user['registered_at'])) {
        $user['registered_at'] = $user['date_register'] ?? null;
    }

    if (!isset($user['last_login_at'])) {
        $user['last_login_at'] = $user['last_login'] ?? null;
    }

    if (!isset($user['ip_address'])) {
        $user['ip_address'] = $user['ip'] ?? '';
    }

    // Legacy template keys (v2 schema: registered_at, last_login_at, ip_address, country_code).
    if (!isset($user['date_register']) || $user['date_register'] === null || $user['date_register'] === '') {
        $user['date_register'] = $user['registered_at'] ?? '';
    }
    if (!isset($user['last_login']) || $user['last_login'] === null || $user['last_login'] === '') {
        $user['last_login'] = $user['last_login_at'] ?? '';
    }
    if (!isset($user['ip'])) {
        $user['ip'] = (string)($user['ip_address'] ?? '');
    }
    if (!isset($user['country']) || $user['country'] === '') {
        $user['country'] = (string)($user['country_code'] ?? '');
    }

    if (!isset($user['locale_code'])) {
        $user['locale_code'] = tenant_normalize_locale_code($user['lang'] ?? 'en');
    } else {
        $user['locale_code'] = tenant_normalize_locale_code($user['locale_code']);
    }

    if (!isset($user['balance_amount']) || !is_numeric($user['balance_amount'])) {
        $user['balance_amount'] = '0.00';
    } else {
        $user['balance_amount'] = number_format((float)$user['balance_amount'], 2, '.', '');
    }

    if (!isset($user['is_active'])) {
        $user['is_active'] = tenant_normalize_active_flag($user['status'] ?? 0);
    } else {
        $user['is_active'] = tenant_normalize_active_flag($user['is_active']);
    }

    if (!array_key_exists('email_notification', $user)) {
        if (array_key_exists('is_newsletter_subscribed', $user)) {
            if ($user['is_newsletter_subscribed'] === null || $user['is_newsletter_subscribed'] === '') {
                $user['email_notification'] = 1;
            } else {
                $user['email_notification'] = (int)$user['is_newsletter_subscribed'] !== 0 ? 1 : 0;
            }
        } else {
            $user['email_notification'] = 1;
        }
    } else {
        if ($user['email_notification'] === null || $user['email_notification'] === '') {
            $user['email_notification'] = 1;
        } else {
            $user['email_notification'] = (int)$user['email_notification'] !== 0 ? 1 : 0;
        }
    }

    $user['is_newsletter_subscribed'] = (int)$user['email_notification'];

    return $user;
}

function tenant_current_id(array $record): int
{
    if (isset($record['tenant_id'])) {
        return (int)$record['tenant_id'];
    }

    if (isset($record['res_id'])) {
        return (int)$record['res_id'];
    }

    if (isset($record['id'])) {
        return (int)$record['id'];
    }

    return 0;
}

function tenant_fetch_installation_profile(Mysql_ks $db): array
{
    if (tenant_schema_object_exists($db, 'installation_profile')) {
        $installationProfile = $db->select_user('SELECT * FROM installation_profile LIMIT 1');
        if (is_array($installationProfile) && $installationProfile) {
            return tenant_normalize_reseller_record($installationProfile);
        }
    }

    if (tenant_schema_object_exists($db, 'app_settings')) {
        $appSettingsProfile = $db->select_user(
            "SELECT
                1 AS id,
                1 AS tenant_id,
                app_settings.site_name AS name,
                app_settings.site_logo_url AS logo_url,
                app_settings.default_locale_code AS locale_code,
                app_settings.default_currency_id AS currency_id,
                currencies.code AS currency_short,
                currencies.symbol AS currency_symbol,
                1 AS status
             FROM app_settings
             LEFT JOIN currencies ON currencies.id = app_settings.default_currency_id
             LIMIT 1"
        );

        if (is_array($appSettingsProfile) && $appSettingsProfile) {
            return tenant_normalize_reseller_record($appSettingsProfile);
        }
    }

    $fallbackQuery = "
        SELECT
            resellers.*,
            currency.short_name AS currency_short,
            currency.symbol AS currency_symbol
        FROM resellers
        LEFT JOIN currency ON resellers.currency = currency.id
        WHERE resellers.status = 1
        ORDER BY resellers.id ASC
        LIMIT 1
    ";

    $fallbackProfile = $db->select_user($fallbackQuery);
    if (!is_array($fallbackProfile)) {
        return [];
    }

    return tenant_normalize_reseller_record($fallbackProfile);
}
