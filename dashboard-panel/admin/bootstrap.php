<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/mysql.php';
require_once dirname(__DIR__) . '/bootstrap/schema.php';
require_once dirname(__DIR__) . '/bootstrap/application.php';
require_once dirname(__DIR__) . '/bootstrap/chat.php';

$__adminBootstrapChatRuntimeDb = Mysql_ks::get_instance();
chat_ensure_group_chat_runtime($__adminBootstrapChatRuntimeDb);
unset($__adminBootstrapChatRuntimeDb);

function admin_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');

    session_name('reseller_admin_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function admin_send_security_headers(): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

function admin_supported_locales(): array
{
    return [
        'en' => ['label' => 'English'],
        'pl' => ['label' => 'Polski'],
    ];
}

function admin_normalize_locale(?string $locale): string
{
    $locale = strtolower(trim((string)$locale));
    $supported = admin_supported_locales();
    return isset($supported[$locale]) ? $locale : 'en';
}

function admin_load_messages(string $locale): array
{
    $locale = admin_normalize_locale($locale);
    $baseFile = __DIR__ . '/locales/en.php';
    $localeFile = __DIR__ . '/locales/' . $locale . '.php';

    $baseMessages = is_file($baseFile) ? require $baseFile : [];
    $localeMessages = is_file($localeFile) ? require $localeFile : [];

    return array_replace(is_array($baseMessages) ? $baseMessages : [], is_array($localeMessages) ? $localeMessages : []);
}

function admin_t(array $messages, string $key, string $default = '', array $replacements = []): string
{
    $text = isset($messages[$key]) ? (string)$messages[$key] : $default;
    if ($text === '' || !$replacements) {
        return $text;
    }

    foreach ($replacements as $replacementKey => $replacementValue) {
        $text = str_replace('{' . $replacementKey . '}', (string)$replacementValue, $text);
    }

    return $text;
}

function admin_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['admin_csrf_token'];
}

function admin_csrf_is_valid(?string $token): bool
{
    $sessionToken = isset($_SESSION['admin_csrf_token']) ? (string)$_SESSION['admin_csrf_token'] : '';
    $token = (string)$token;

    return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
}

function admin_app_settings(Mysql_ks $db): array
{
    if (!schema_object_exists($db, 'app_settings')) {
        return [];
    }

    app_ensure_settings_runtime_columns($db);

    $settings = $db->select_user("SELECT * FROM app_settings WHERE id = 1 LIMIT 1");
    return is_array($settings) ? $settings : [];
}

function admin_setting_is_enabled(array $settings, string $key, bool $default = false): bool
{
    if (!array_key_exists($key, $settings)) {
        return $default;
    }

    return !empty($settings[$key]);
}

function admin_bank_transfers_enabled(array $settings): bool
{
    return admin_setting_is_enabled($settings, 'bank_transfers_enabled', true);
}

function admin_contact_form_enabled(array $settings): bool
{
    return admin_setting_is_enabled($settings, 'contact_form_enabled', true);
}

function admin_referrals_enabled(array $settings): bool
{
    return admin_setting_is_enabled($settings, 'referrals_enabled', true);
}

function admin_apps_page_enabled(array $settings): bool
{
    return admin_setting_is_enabled($settings, 'apps_page_enabled', true);
}

function admin_customer_type_switch_enabled(array $settings): bool
{
    return admin_setting_is_enabled($settings, 'customer_type_switch_enabled', false);
}

function admin_application_instructions_enabled(array $settings): bool
{
    return admin_setting_is_enabled($settings, 'application_instructions_enabled', true);
}

function admin_crypto_wallet_shared_assignments_enabled(array $settings): bool
{
    return admin_setting_is_enabled($settings, 'crypto_wallet_shared_assignments_enabled', false);
}

function admin_bank_account_shared_assignments_enabled(array $settings): bool
{
    return admin_setting_is_enabled($settings, 'bank_account_shared_assignments_enabled', true);
}

function admin_credits_sales_enabled(array $settings): bool
{
    return admin_setting_is_enabled($settings, 'credits_sales_enabled', false);
}

function admin_normalize_product_type($value): string
{
    return strtolower(trim((string)$value)) === 'credits' ? 'credits' : 'subscription';
}

function admin_customer_type_options(string $current = ''): array
{
    $options = ['client', 'reseller'];
    $current = app_normalize_customer_type($current);
    if ($current !== '' && !in_array($current, $options, true)) {
        $options[] = $current;
    }

    return $options;
}

function admin_service_currency_rows(Mysql_ks $db): array
{
    if (!schema_object_exists($db, 'currencies')) {
        return [];
    }

    return $db->select_full_user(
        "SELECT id, code, name, symbol
         FROM currencies
         WHERE is_active = 1
           AND code IN ('USD', 'EUR')
         ORDER BY FIELD(code, 'USD', 'EUR'), id ASC"
    );
}

function admin_currency_row(Mysql_ks $db, int $currencyId): ?array
{
    if ($currencyId <= 0 || !schema_object_exists($db, 'currencies')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT id, code, name, symbol
         FROM currencies
         WHERE id = {$currencyId}
         LIMIT 1"
    );

    return is_array($row) && !empty($row['id']) ? $row : null;
}

function admin_default_currency_row(Mysql_ks $db): ?array
{
    $settings = admin_app_settings($db);
    $currencyId = (int)($settings['default_currency_id'] ?? 0);
    $currencyRow = admin_currency_row($db, $currencyId);
    if ($currencyRow !== null) {
        return $currencyRow;
    }

    if (!schema_object_exists($db, 'currencies')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT id, code, name, symbol
         FROM currencies
         WHERE is_active = 1
         ORDER BY id ASC
         LIMIT 1"
    );

    return is_array($row) && !empty($row['id']) ? $row : null;
}

function admin_default_currency_id(Mysql_ks $db): int
{
    $currencyRow = admin_default_currency_row($db);
    return (int)($currencyRow['id'] ?? 0);
}

function admin_sync_products_currency(Mysql_ks $db, int $currencyId): bool
{
    if ($currencyId <= 0 || !schema_object_exists($db, 'products')) {
        return false;
    }

    $db->query(
        "UPDATE products
         SET currency_id = {$currencyId}
         WHERE currency_id <> {$currencyId}"
    );

    return true;
}

function admin_find_user_by_identity(Mysql_ks $db, string $identity): ?array
{
    admin_ensure_user_runtime_columns($db);

    $identity = trim($identity);
    if ($identity === '' || !schema_object_exists($db, 'admin_users')) {
        return null;
    }

    $safeIdentity = $db->escape($identity);
    $row = $db->select_user(
        "SELECT
            admin_users.*,
            admin_roles.name AS role_name,
            admin_roles.slug AS role_slug,
            admin_roles.access_level AS role_access_level
         FROM admin_users
         INNER JOIN admin_roles ON admin_roles.id = admin_users.role_id
         WHERE admin_users.login_name = '{$safeIdentity}'
            OR admin_users.email = '{$safeIdentity}'
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_load_session_user(Mysql_ks $db): ?array
{
    admin_ensure_user_runtime_columns($db);

    $adminId = isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : 0;
    if ($adminId <= 0 || !schema_object_exists($db, 'admin_users')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT
            admin_users.*,
            admin_roles.name AS role_name,
            admin_roles.slug AS role_slug,
            admin_roles.access_level AS role_access_level
         FROM admin_users
         INNER JOIN admin_roles ON admin_roles.id = admin_users.role_id
         WHERE admin_users.id = {$adminId}
         LIMIT 1"
    );

    if (!is_array($row) || empty($row['id']) || (string)($row['status'] ?? '') !== 'active') {
        return null;
    }

    admin_touch_session_activity($db, (int)$row['id']);
    $row['last_login_at'] = date('Y-m-d H:i:s');

    return $row;
}

function admin_touch_session_activity(Mysql_ks $db, int $adminUserId): void
{
    if ($adminUserId <= 0 || !schema_object_exists($db, 'admin_users')) {
        return;
    }

    $nowTs = time();
    $lastTouchTs = isset($_SESSION['admin_last_seen_touch_ts']) ? (int)$_SESSION['admin_last_seen_touch_ts'] : 0;
    if ($lastTouchTs > 0 && ($nowTs - $lastTouchTs) < 60) {
        return;
    }

    $now = date('Y-m-d H:i:s', $nowTs);
    $db->update_using_id(['last_login_at'], [$now], 'admin_users', $adminUserId);
    $_SESSION['admin_last_seen_touch_ts'] = $nowTs;
}

function admin_request_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        $value = trim((string)$candidate);
        if ($value === '') {
            continue;
        }

        if (strpos($value, ',') !== false) {
            $parts = array_map('trim', explode(',', $value));
            $value = (string)($parts[0] ?? '');
        }

        if ($value !== '') {
            return substr($value, 0, 64);
        }
    }

    return '';
}

function admin_ensure_user_runtime_columns(Mysql_ks $db): void
{
    static $done = false;

    if ($done || !schema_object_exists($db, 'admin_users')) {
        return;
    }

    $done = true;

    if (!schema_column_exists($db, 'admin_users', 'public_handle')) {
        @$db->query(
            "ALTER TABLE admin_users
             ADD COLUMN public_handle VARCHAR(80) DEFAULT NULL
             AFTER email"
        );
        schema_forget_column_cache('admin_users', 'public_handle');
    }

    if (schema_column_exists($db, 'admin_users', 'public_handle')) {
        @$db->query(
            "UPDATE admin_users
             SET public_handle = CONCAT('support-', id)
             WHERE public_handle IS NULL
                OR TRIM(public_handle) = ''"
        );
    }
}

function admin_normalize_public_handle(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? $value;
    $value = trim($value, '-._');

    if (strlen($value) > 40) {
        $value = substr($value, 0, 40);
        $value = rtrim($value, '-._');
    }

    return $value;
}

function admin_public_handle_exists(Mysql_ks $db, string $handle, int $excludeAdminId = 0): bool
{
    admin_ensure_user_runtime_columns($db);

    $handle = admin_normalize_public_handle($handle);
    if ($handle === '' || !schema_object_exists($db, 'admin_users') || !schema_column_exists($db, 'admin_users', 'public_handle')) {
        return false;
    }

    $safeHandle = $db->escape($handle);
    $excludeSql = $excludeAdminId > 0 ? " AND id != {$excludeAdminId}" : '';
    $row = $db->select_user(
        "SELECT id
         FROM admin_users
         WHERE public_handle = '{$safeHandle}'{$excludeSql}
         LIMIT 1"
    );

    return is_array($row) && !empty($row['id']);
}

function admin_generate_public_handle(Mysql_ks $db, string $base = 'support', int $excludeAdminId = 0): string
{
    $base = admin_normalize_public_handle($base);
    if ($base === '') {
        $base = 'support';
    }

    $candidate = $base;
    $suffix = 2;

    while (admin_public_handle_exists($db, $candidate, $excludeAdminId)) {
        $candidate = $base . '-' . $suffix;
        $suffix++;
    }

    return $candidate;
}

function admin_resolve_public_handle(Mysql_ks $db, string $handleInput, int $excludeAdminId = 0): array
{
    $handle = admin_normalize_public_handle($handleInput);
    if ($handle === '') {
        return ['ok' => false, 'message' => 'Live chat handle is required.'];
    }

    if (strlen($handle) < 3) {
        return ['ok' => false, 'message' => 'Live chat handle must contain at least 3 characters.'];
    }

    if (admin_public_handle_exists($db, $handle, $excludeAdminId)) {
        return ['ok' => false, 'message' => 'This live chat handle is already used by another administrator.'];
    }

    return ['ok' => true, 'handle' => $handle];
}

function admin_public_handle_label(array $adminUser): string
{
    $handle = admin_normalize_public_handle((string)($adminUser['public_handle'] ?? ''));
    return $handle !== '' ? $handle : 'Support';
}

function admin_user_access_level(array $adminUser): int
{
    return (int)($adminUser['role_access_level'] ?? 0);
}

function admin_route_minimum_access_level(string $route): int
{
    $restrictedRoutes = ['settings', 'email-templates', 'faq'];
    return in_array($route, $restrictedRoutes, true) ? 1000 : 500;
}

function admin_user_can_access_route(array $adminUser, string $route): bool
{
    return admin_user_access_level($adminUser) >= admin_route_minimum_access_level($route);
}

function admin_route_label_key(string $route): string
{
    $map = [
        'dashboard' => 'nav_dashboard',
        'orders' => 'nav_orders',
        'products' => 'nav_products',
        'users' => 'nav_users',
        'payments' => 'nav_payments',
        'bank-accounts' => 'nav_bank_accounts',
        'crypto-wallets' => 'nav_crypto_wallets',
        'cryptocurrencies' => 'nav_cryptocurrencies',
        'news' => 'nav_news',
        'live-chat' => 'nav_live_chat',
        'email-templates' => 'nav_email_templates',
        'faq' => 'nav_faq',
        'settings' => 'nav_settings',
    ];

    return $map[$route] ?? 'brand';
}

function admin_route_label(array $messages, string $route): string
{
    return admin_t($messages, admin_route_label_key($route), ucfirst(str_replace('-', ' ', $route)));
}

function admin_role_rows(Mysql_ks $db): array
{
    if (!schema_object_exists($db, 'admin_roles')) {
        return [];
    }

    return $db->select_full_user(
        "SELECT id, name, slug, access_level
         FROM admin_roles
         ORDER BY access_level DESC, id ASC"
    );
}

function admin_role_find(Mysql_ks $db, int $roleId): ?array
{
    if ($roleId <= 0 || !schema_object_exists($db, 'admin_roles')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT id, name, slug, access_level
         FROM admin_roles
         WHERE id = {$roleId}
         LIMIT 1"
    );

    return is_array($row) && !empty($row['id']) ? $row : null;
}

function admin_user_rows(Mysql_ks $db): array
{
    admin_ensure_user_runtime_columns($db);

    if (!schema_object_exists($db, 'admin_users')) {
        return [];
    }

    return $db->select_full_user(
        "SELECT
            admin_users.id,
            admin_users.role_id,
            admin_users.login_name,
            admin_users.email,
            admin_users.public_handle,
            admin_users.status,
            admin_users.last_login_at,
            admin_users.created_at,
            admin_roles.name AS role_name,
            admin_roles.access_level AS role_access_level
         FROM admin_users
         INNER JOIN admin_roles ON admin_roles.id = admin_users.role_id
         ORDER BY admin_roles.access_level DESC, admin_users.id ASC"
    );
}

function admin_admin_status_options(): array
{
    return ['active', 'inactive'];
}

function admin_admin_user_find(Mysql_ks $db, int $adminUserId): ?array
{
    admin_ensure_user_runtime_columns($db);

    if ($adminUserId <= 0 || !schema_object_exists($db, 'admin_users')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT
            admin_users.id,
            admin_users.role_id,
            admin_users.login_name,
            admin_users.email,
            admin_users.public_handle,
            admin_users.status,
            admin_users.last_login_at,
            admin_users.created_at,
            admin_roles.name AS role_name,
            admin_roles.access_level AS role_access_level
         FROM admin_users
         INNER JOIN admin_roles ON admin_roles.id = admin_users.role_id
         WHERE admin_users.id = {$adminUserId}
         LIMIT 1"
    );

    return is_array($row) && !empty($row['id']) ? $row : null;
}

function admin_active_full_admin_count(Mysql_ks $db, int $excludeAdminUserId = 0): int
{
    if (!schema_object_exists($db, 'admin_users') || !schema_object_exists($db, 'admin_roles')) {
        return 0;
    }

    $excludeSql = $excludeAdminUserId > 0 ? ' AND admin_users.id <> ' . $excludeAdminUserId : '';
    $row = $db->select_user(
        "SELECT COUNT(*) AS total
         FROM admin_users
         INNER JOIN admin_roles ON admin_roles.id = admin_users.role_id
         WHERE admin_users.status = 'active'
           AND admin_roles.access_level >= 1000{$excludeSql}"
    );

    return (int)($row['total'] ?? 0);
}

function admin_create_admin_user(Mysql_ks $db, array $input, int $createdByAdminUserId = 0, string $ipAddress = ''): array
{
    admin_ensure_user_runtime_columns($db);

    if (!schema_object_exists($db, 'admin_users')) {
        return ['ok' => false, 'message' => 'Administrator storage is not available.'];
    }

    $roleId = (int)($input['role_id'] ?? 0);
    $role = admin_role_find($db, $roleId);
    if (!$role) {
        return ['ok' => false, 'message' => 'Select a valid administrator role.'];
    }

    $loginName = trim((string)($input['login_name'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $password = (string)($input['password'] ?? '');
    $repeatPassword = (string)($input['repeat_password'] ?? '');
    $handleResult = admin_resolve_public_handle($db, (string)($input['public_handle'] ?? ''));

    if ($loginName === '') {
        return ['ok' => false, 'message' => 'Login name is required.'];
    }

    if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $loginName)) {
        return ['ok' => false, 'message' => 'Login name must contain 3-80 characters and use only letters, numbers, dots, dashes or underscores.'];
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Enter a valid email address.'];
    }

    if (strlen($password) < 8) {
        return ['ok' => false, 'message' => 'Password must contain at least 8 characters.'];
    }

    if ($password !== $repeatPassword) {
        return ['ok' => false, 'message' => 'Passwords do not match.'];
    }

    if (empty($handleResult['ok'])) {
        return $handleResult;
    }

    $safeLoginName = $db->escape($loginName);
    $safeEmail = $db->escape($email);
    $existing = $db->select_user(
        "SELECT id
         FROM admin_users
         WHERE login_name = '{$safeLoginName}'
            OR email = '{$safeEmail}'
         LIMIT 1"
    );
    if (is_array($existing) && !empty($existing['id'])) {
        return ['ok' => false, 'message' => 'This login or email is already assigned to another administrator.'];
    }

    $inserted = $db->insert(
        ['role_id', 'login_name', 'email', 'public_handle', 'password_hash', 'password_hash_algorithm', 'status'],
        [
            $roleId,
            $loginName,
            $email,
            (string)$handleResult['handle'],
            password_hash($password, PASSWORD_DEFAULT),
            'password_hash',
            'active',
        ],
        'admin_users'
    );

    if (!$inserted || (int)$db->id() <= 0) {
        return ['ok' => false, 'message' => 'Unable to create administrator account.'];
    }

    $newAdminUserId = (int)$db->id();
    if ($createdByAdminUserId > 0) {
        admin_activity_log(
            $db,
            0,
            $createdByAdminUserId,
            'admin_created',
            'Administrator account created: ' . $loginName . ' (' . $email . '), role ' . (string)($role['name'] ?? 'unknown') . '.',
            $ipAddress
        );
    }

    return [
        'ok' => true,
        'message' => 'Administrator account created successfully.',
        'admin_user_id' => $newAdminUserId,
    ];
}

function admin_update_admin_user(
    Mysql_ks $db,
    int $targetAdminUserId,
    array $input,
    array $currentAdminUser,
    string $ipAddress = ''
): array {
    if (admin_user_access_level($currentAdminUser) < 1000) {
        return ['ok' => false, 'message' => 'Only the main administrator can edit administrator accounts.'];
    }

    $targetAdmin = admin_admin_user_find($db, $targetAdminUserId);
    if (!$targetAdmin) {
        return ['ok' => false, 'message' => 'Administrator account not found.'];
    }

    $roleId = (int)($input['role_id'] ?? 0);
    $role = admin_role_find($db, $roleId);
    if (!$role) {
        return ['ok' => false, 'message' => 'Select a valid administrator role.'];
    }

    $loginName = trim((string)($input['login_name'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $status = strtolower(trim((string)($input['status'] ?? (string)($targetAdmin['status'] ?? 'active'))));
    $password = (string)($input['password'] ?? '');
    $repeatPassword = (string)($input['repeat_password'] ?? '');
    $handleResult = admin_resolve_public_handle($db, (string)($input['public_handle'] ?? ''), $targetAdminUserId);

    if ($loginName === '') {
        return ['ok' => false, 'message' => 'Login name is required.'];
    }

    if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $loginName)) {
        return ['ok' => false, 'message' => 'Login name must contain 3-80 characters and use only letters, numbers, dots, dashes or underscores.'];
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Enter a valid email address.'];
    }

    if (!in_array($status, admin_admin_status_options(), true)) {
        $status = 'active';
    }

    if (empty($handleResult['ok'])) {
        return $handleResult;
    }

    if ($password !== '' || $repeatPassword !== '') {
        if (strlen($password) < 8) {
            return ['ok' => false, 'message' => 'Password must contain at least 8 characters.'];
        }

        if ($password !== $repeatPassword) {
            return ['ok' => false, 'message' => 'Passwords do not match.'];
        }
    }

    $safeLoginName = $db->escape($loginName);
    $safeEmail = $db->escape($email);
    $existing = $db->select_user(
        "SELECT id
         FROM admin_users
         WHERE (login_name = '{$safeLoginName}'
            OR email = '{$safeEmail}')
           AND id <> {$targetAdminUserId}
         LIMIT 1"
    );
    if (is_array($existing) && !empty($existing['id'])) {
        return ['ok' => false, 'message' => 'This login or email is already assigned to another administrator.'];
    }

    $currentAdminId = (int)($currentAdminUser['id'] ?? 0);
    if ($currentAdminId === $targetAdminUserId) {
        $currentRoleId = (int)($targetAdmin['role_id'] ?? 0);
        $currentStatus = (string)($targetAdmin['status'] ?? 'active');
        if ($currentRoleId !== $roleId || $currentStatus !== $status) {
            return ['ok' => false, 'message' => 'Use another boss account to change your own role or status.'];
        }
    }

    $targetWasFullAdmin = admin_user_access_level($targetAdmin) >= 1000;
    $targetWillRemainFullAdmin = (int)($role['access_level'] ?? 0) >= 1000 && $status === 'active';
    if ($targetWasFullAdmin && !$targetWillRemainFullAdmin && admin_active_full_admin_count($db, $targetAdminUserId) < 1) {
        return ['ok' => false, 'message' => 'At least one active full administrator must remain in the panel.'];
    }

    $updateFields = ['role_id', 'login_name', 'email', 'public_handle', 'status'];
    $updateValues = [$roleId, $loginName, $email, (string)$handleResult['handle'], $status];

    if ($password !== '') {
        $updateFields[] = 'password_hash';
        $updateFields[] = 'password_hash_algorithm';
        $updateValues[] = password_hash($password, PASSWORD_DEFAULT);
        $updateValues[] = 'password_hash';
    }

    $updated = $db->update_using_id($updateFields, $updateValues, 'admin_users', $targetAdminUserId);
    if (!$updated) {
        return ['ok' => false, 'message' => 'Unable to update administrator account.'];
    }

    $changes = [];
    if ((string)($targetAdmin['login_name'] ?? '') !== $loginName) {
        $changes[] = 'login';
    }
    if ((string)($targetAdmin['email'] ?? '') !== $email) {
        $changes[] = 'email';
    }
    if ((string)($targetAdmin['public_handle'] ?? '') !== (string)$handleResult['handle']) {
        $changes[] = 'live chat handle';
    }
    if ((int)($targetAdmin['role_id'] ?? 0) !== $roleId) {
        $changes[] = 'role';
    }
    if ((string)($targetAdmin['status'] ?? '') !== $status) {
        $changes[] = 'status';
    }
    if ($password !== '') {
        $changes[] = 'password';
    }

    admin_activity_log(
        $db,
        0,
        $currentAdminId,
        'admin_updated',
        'Administrator account updated: ' . $loginName . '. Changed: ' . ($changes ? implode(', ', $changes) : 'profile') . '.',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Administrator account updated successfully.'];
}

function admin_delete_admin_user(
    Mysql_ks $db,
    int $targetAdminUserId,
    array $currentAdminUser,
    string $ipAddress = ''
): array {
    if (admin_user_access_level($currentAdminUser) < 1000) {
        return ['ok' => false, 'message' => 'Only the main administrator can delete administrator accounts.'];
    }

    $targetAdmin = admin_admin_user_find($db, $targetAdminUserId);
    if (!$targetAdmin) {
        return ['ok' => false, 'message' => 'Administrator account not found.'];
    }

    $currentAdminId = (int)($currentAdminUser['id'] ?? 0);
    if ($currentAdminId === $targetAdminUserId) {
        return ['ok' => false, 'message' => 'You cannot delete the administrator account currently used in this session.'];
    }

    if (admin_user_access_level($targetAdmin) >= 1000 && admin_active_full_admin_count($db, $targetAdminUserId) < 1) {
        return ['ok' => false, 'message' => 'At least one active full administrator must remain in the panel.'];
    }

    $deleted = $db->delete_using_id('admin_users', $targetAdminUserId);
    if (!$deleted) {
        return ['ok' => false, 'message' => 'Unable to delete administrator account.'];
    }

    admin_activity_log(
        $db,
        0,
        $currentAdminId,
        'admin_deleted',
        'Administrator account deleted: ' . (string)($targetAdmin['login_name'] ?? 'unknown') . ' (' . (string)($targetAdmin['email'] ?? '') . ').',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Administrator account deleted successfully.'];
}

function admin_verify_password(array $adminUser, string $plainPassword): bool
{
    $plainPassword = (string)$plainPassword;
    $storedHash = isset($adminUser['password_hash']) ? (string)$adminUser['password_hash'] : '';
    $algorithm = isset($adminUser['password_hash_algorithm']) ? (string)$adminUser['password_hash_algorithm'] : 'password_hash';

    if ($storedHash === '') {
        return false;
    }

    if ($algorithm === 'password_hash') {
        return password_verify($plainPassword, $storedHash);
    }

    if ($algorithm === 'legacy_sha1') {
        return sha1('SOOOL' . $plainPassword) === $storedHash;
    }

    return false;
}

function admin_refresh_password_hash_if_needed(Mysql_ks $db, array $adminUser, string $plainPassword): void
{
    $adminId = isset($adminUser['id']) ? (int)$adminUser['id'] : 0;
    if ($adminId <= 0) {
        return;
    }

    $storedHash = isset($adminUser['password_hash']) ? (string)$adminUser['password_hash'] : '';
    $algorithm = isset($adminUser['password_hash_algorithm']) ? (string)$adminUser['password_hash_algorithm'] : 'password_hash';
    $needsUpgrade = $algorithm !== 'password_hash';

    if (!$needsUpgrade && $storedHash !== '') {
        $needsUpgrade = password_needs_rehash($storedHash, PASSWORD_DEFAULT);
    }

    if (!$needsUpgrade) {
        return;
    }

    $db->update_using_id(
        ['password_hash', 'password_hash_algorithm'],
        [password_hash($plainPassword, PASSWORD_DEFAULT), 'password_hash'],
        'admin_users',
        $adminId
    );
}

function admin_login(array $adminUser, string $locale): void
{
    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = (int)$adminUser['id'];
    $_SESSION['admin_locale'] = admin_normalize_locale($locale);
    $_SESSION['admin_last_seen_touch_ts'] = time();
}

function admin_logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }

    session_destroy();
}

function admin_navigation_config(array $settings = [], ?array $adminUser = null): array
{
    $navigation = [
        'general' => [
            ['route' => 'dashboard', 'icon' => 'bi-grid-1x2', 'label_key' => 'nav_dashboard'],
            ['route' => 'orders', 'icon' => 'bi-card-checklist', 'label_key' => 'nav_orders'],
            ['route' => 'products', 'icon' => 'bi-box-seam', 'label_key' => 'nav_products'],
            ['route' => 'users', 'icon' => 'bi-people', 'label_key' => 'nav_users'],
        ],
        'finance' => [
            ['route' => 'payments', 'icon' => 'bi-credit-card-2-front', 'label_key' => 'nav_payments'],
            ['route' => 'bank-accounts', 'icon' => 'bi-bank', 'label_key' => 'nav_bank_accounts'],
            ['route' => 'crypto-wallets', 'icon' => 'bi-currency-bitcoin', 'label_key' => 'nav_crypto_wallets'],
            ['route' => 'cryptocurrencies', 'icon' => 'bi-currency-exchange', 'label_key' => 'nav_cryptocurrencies'],
        ],
        'content' => [
            ['route' => 'news', 'icon' => 'bi-megaphone', 'label_key' => 'nav_news'],
            ['route' => 'live-chat', 'icon' => 'bi-chat-dots', 'label_key' => 'nav_live_chat'],
            ['route' => 'email-templates', 'icon' => 'bi-envelope-paper', 'label_key' => 'nav_email_templates'],
            ['route' => 'faq', 'icon' => 'bi-question-circle', 'label_key' => 'nav_faq'],
        ],
        'system' => [
            ['route' => 'settings', 'icon' => 'bi-gear', 'label_key' => 'nav_settings'],
        ],
    ];

    if (!admin_bank_transfers_enabled($settings)) {
        foreach ($navigation as $groupKey => $items) {
            $navigation[$groupKey] = array_values(array_filter($items, static function (array $item): bool {
                return (string)($item['route'] ?? '') !== 'bank-accounts';
            }));
        }
    }

    if (is_array($adminUser) && !empty($adminUser)) {
        foreach ($navigation as $groupKey => $items) {
            $navigation[$groupKey] = array_values(array_filter($items, static function (array $item) use ($adminUser): bool {
                return admin_user_can_access_route($adminUser, (string)($item['route'] ?? ''));
            }));
        }
    }

    return $navigation;
}

function admin_allowed_routes(): array
{
    $routes = [];
    foreach (admin_navigation_config() as $items) {
        foreach ($items as $item) {
            $routes[] = $item['route'];
        }
    }

    return $routes;
}

function admin_normalize_route(?string $route): string
{
    $route = trim((string)$route);
    if ($route === '' || !in_array($route, admin_allowed_routes(), true)) {
        return 'dashboard';
    }

    return $route;
}

function admin_asset_version(string $absolutePath): int
{
    return is_file($absolutePath) ? (int)filemtime($absolutePath) : 1;
}

function admin_dashboard_metrics(Mysql_ks $db): array
{
    $metrics = [
        'customers' => 0,
        'orders' => 0,
        'crypto_open' => 0,
        'bank_open' => 0,
        'news_posts' => 0,
        'email_templates' => 0,
        'faq_pages' => 0,
        'chat_open' => 0,
    ];

    if (schema_object_exists($db, 'customers')) {
        $row = $db->select_user("SELECT COUNT(*) AS total FROM customers");
        $metrics['customers'] = (int)($row['total'] ?? 0);
    }

    if (schema_object_exists($db, 'orders')) {
        $row = $db->select_user("SELECT COUNT(*) AS total FROM orders");
        $metrics['orders'] = (int)($row['total'] ?? 0);
    }

    if (schema_object_exists($db, 'crypto_deposit_requests')) {
        $row = $db->select_user("SELECT COUNT(*) AS total FROM crypto_deposit_requests WHERE status IN ('pending', 'awaiting_confirmation')");
        $metrics['crypto_open'] = (int)($row['total'] ?? 0);
    }

    if (schema_object_exists($db, 'bank_transfer_requests')) {
        $row = $db->select_user("SELECT COUNT(*) AS total FROM bank_transfer_requests WHERE status IN ('pending_payment', 'awaiting_review')");
        $metrics['bank_open'] = (int)($row['total'] ?? 0);
    }

    if (schema_object_exists($db, 'news_posts')) {
        $row = $db->select_user("SELECT COUNT(*) AS total FROM news_posts");
        $metrics['news_posts'] = (int)($row['total'] ?? 0);
    }

    if (schema_object_exists($db, 'email_templates')) {
        $row = $db->select_user("SELECT COUNT(*) AS total FROM email_templates");
        $metrics['email_templates'] = (int)($row['total'] ?? 0);
    }

    if (schema_object_exists($db, 'static_pages')) {
        $row = $db->select_user("SELECT COUNT(*) AS total FROM static_pages WHERE slug LIKE 'faq-%'");
        $metrics['faq_pages'] = (int)($row['total'] ?? 0);
    }

    if (schema_object_exists($db, 'support_conversations')) {
        $row = $db->select_user("SELECT COUNT(*) AS total FROM support_conversations WHERE conversation_type = 'live_chat'");
        $metrics['chat_open'] = (int)($row['total'] ?? 0);
    }

    return $metrics;
}

function admin_dashboard_period_metrics(Mysql_ks $db): array
{
    if (!schema_object_exists($db, 'orders')) {
        return [];
    }

    $now = time();
    $dateColumn = "COALESCE(orders.paid_at, orders.created_at)";
    $periods = [
        'daily' => [
            'label' => 'Today',
            'from' => date('Y-m-d 00:00:00', $now),
            'to' => date('Y-m-d 23:59:59', $now),
        ],
        'weekly' => [
            'label' => 'This week',
            'from' => date('Y-m-d 00:00:00', strtotime('monday this week', $now)),
            'to' => date('Y-m-d 23:59:59', strtotime('sunday this week', $now)),
        ],
        'monthly' => [
            'label' => 'This month',
            'from' => date('Y-m-01 00:00:00', $now),
            'to' => date('Y-m-t 23:59:59', $now),
        ],
        'yearly' => [
            'label' => 'This year',
            'from' => date('Y-01-01 00:00:00', $now),
            'to' => date('Y-12-31 23:59:59', $now),
        ],
    ];

    $results = [];
    foreach ($periods as $key => $period) {
        $safeFrom = $db->escape((string)$period['from']);
        $safeTo = $db->escape((string)$period['to']);
        $row = $db->select_user(
            "SELECT
                COUNT(*) AS paid_orders,
                COALESCE(SUM(orders.total_amount), 0) AS paid_revenue
             FROM orders
             WHERE orders.payment_status = 'paid'
               AND {$dateColumn} BETWEEN '{$safeFrom}' AND '{$safeTo}'"
        );

        $results[$key] = [
            'label' => (string)$period['label'],
            'from' => (string)$period['from'],
            'to' => (string)$period['to'],
            'paid_orders' => (int)($row['paid_orders'] ?? 0),
            'paid_revenue' => (float)($row['paid_revenue'] ?? 0),
        ];
    }

    return $results;
}

function admin_dashboard_sales_series(Mysql_ks $db, int $days = 30): array
{
    if (!schema_object_exists($db, 'orders')) {
        return [];
    }

    $days = max(7, min(90, $days));
    $startTimestamp = strtotime('-' . ($days - 1) . ' days midnight');
    $startDate = date('Y-m-d', $startTimestamp);
    $safeStartDate = $db->escape($startDate);
    $dateColumn = "DATE(COALESCE(orders.paid_at, orders.created_at))";

    $rows = $db->select_full_user(
        "SELECT
            {$dateColumn} AS metric_date,
            COUNT(*) AS paid_orders,
            COALESCE(SUM(orders.total_amount), 0) AS paid_revenue
         FROM orders
         WHERE orders.payment_status = 'paid'
           AND {$dateColumn} >= '{$safeStartDate}'
         GROUP BY {$dateColumn}
         ORDER BY {$dateColumn} ASC"
    );

    $mapped = [];
    foreach ($rows as $row) {
        $mapped[(string)($row['metric_date'] ?? '')] = [
            'paid_orders' => (int)($row['paid_orders'] ?? 0),
            'paid_revenue' => (float)($row['paid_revenue'] ?? 0),
        ];
    }

    $series = [];
    for ($offset = 0; $offset < $days; $offset++) {
        $timestamp = strtotime('+' . $offset . ' days', $startTimestamp);
        $dateKey = date('Y-m-d', $timestamp);
        $dayData = $mapped[$dateKey] ?? ['paid_orders' => 0, 'paid_revenue' => 0.0];
        $series[] = [
            'date' => $dateKey,
            'short_label' => date('d.m', $timestamp),
            'paid_orders' => (int)$dayData['paid_orders'],
            'paid_revenue' => (float)$dayData['paid_revenue'],
        ];
    }

    return $series;
}

function admin_dashboard_provider_breakdowns(Mysql_ks $db, int $providerLimit = 6, int $productLimit = 5): array
{
    if (!schema_object_exists($db, 'orders') || !schema_object_exists($db, 'products') || !schema_object_exists($db, 'product_providers')) {
        return [];
    }

    $providerLimit = max(1, min(12, $providerLimit));
    $productLimit = max(2, min(8, $productLimit));

    $rows = $db->select_full_user(
        "SELECT
            product_providers.id AS provider_id,
            product_providers.name AS provider_name,
            products.id AS product_id,
            products.name AS product_name,
            COUNT(*) AS sold_count,
            COALESCE(SUM(orders.total_amount), 0) AS revenue_total
         FROM orders
         INNER JOIN products ON products.id = orders.product_id
         INNER JOIN product_providers ON product_providers.id = products.provider_id
         WHERE orders.payment_status = 'paid'
         GROUP BY product_providers.id, product_providers.name, products.id, products.name
         ORDER BY product_providers.name ASC, sold_count DESC, products.name ASC"
    );

    if (!$rows) {
        return [];
    }

    $palette = ['#111827', '#2563eb', '#14b8a6', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b', '#22c55e'];
    $providers = [];

    foreach ($rows as $row) {
        $providerId = (int)($row['provider_id'] ?? 0);
        if ($providerId <= 0) {
            continue;
        }

        if (!isset($providers[$providerId])) {
            $providers[$providerId] = [
                'provider_id' => $providerId,
                'provider_name' => (string)($row['provider_name'] ?? 'Provider'),
                'total_sold' => 0,
                'total_revenue' => 0.0,
                'products' => [],
            ];
        }

        $soldCount = (int)($row['sold_count'] ?? 0);
        $providers[$providerId]['total_sold'] += $soldCount;
        $providers[$providerId]['total_revenue'] += (float)($row['revenue_total'] ?? 0);
        $providers[$providerId]['products'][] = [
            'product_id' => (int)($row['product_id'] ?? 0),
            'product_name' => (string)($row['product_name'] ?? 'Product'),
            'sold_count' => $soldCount,
        ];
    }

    usort($providers, static function (array $left, array $right): int {
        return ($right['total_sold'] <=> $left['total_sold']);
    });

    $providers = array_slice($providers, 0, $providerLimit);

    foreach ($providers as $providerIndex => $provider) {
        $products = $provider['products'];
        usort($products, static function (array $left, array $right): int {
            return ($right['sold_count'] <=> $left['sold_count']);
        });

        $visibleProducts = array_slice($products, 0, $productLimit);
        $otherCount = 0;
        if (count($products) > $productLimit) {
            foreach (array_slice($products, $productLimit) as $extraProduct) {
                $otherCount += (int)($extraProduct['sold_count'] ?? 0);
            }
            if ($otherCount > 0) {
                $visibleProducts[] = [
                    'product_id' => 0,
                    'product_name' => 'Other',
                    'sold_count' => $otherCount,
                ];
            }
        }

        $startAngle = -90.0;
        $preparedProducts = [];
        foreach ($visibleProducts as $productIndex => $product) {
            $count = (int)($product['sold_count'] ?? 0);
            $percent = $provider['total_sold'] > 0 ? ($count / $provider['total_sold']) * 100 : 0;
            $sliceAngle = max(0.0, min(360.0, 360.0 * ($percent / 100)));
            $preparedProducts[] = [
                'product_id' => (int)($product['product_id'] ?? 0),
                'product_name' => (string)($product['product_name'] ?? 'Product'),
                'sold_count' => $count,
                'percent' => $percent,
                'color' => $palette[$productIndex % count($palette)],
                'start_angle' => $startAngle,
                'end_angle' => $startAngle + $sliceAngle,
            ];
            $startAngle += $sliceAngle;
        }

        $providers[$providerIndex]['products'] = $preparedProducts;
    }

    return $providers;
}

function admin_dashboard_chart_path(array $series, int $width = 640, int $height = 240, int $paddingX = 20, int $paddingY = 18): string
{
    if (!$series) {
        return '';
    }

    $maxValue = 0;
    foreach ($series as $point) {
        $maxValue = max($maxValue, (int)($point['paid_orders'] ?? 0));
    }
    if ($maxValue <= 0) {
        $maxValue = 1;
    }

    $count = count($series);
    if ($count === 1) {
        $x = (float)($width / 2);
        $y = (float)($height - $paddingY);
        return 'M ' . number_format($x, 2, '.', '') . ' ' . number_format($y, 2, '.', '');
    }

    $plotWidth = max(1, $width - ($paddingX * 2));
    $plotHeight = max(1, $height - ($paddingY * 2));
    $stepX = $plotWidth / max(1, $count - 1);
    $commands = [];

    foreach ($series as $index => $point) {
        $value = (int)($point['paid_orders'] ?? 0);
        $x = $paddingX + ($index * $stepX);
        $y = $height - $paddingY - (($value / $maxValue) * $plotHeight);
        $commands[] = ($index === 0 ? 'M ' : 'L ')
            . number_format($x, 2, '.', '') . ' '
            . number_format($y, 2, '.', '');
    }

    return implode(' ', $commands);
}

function admin_dashboard_chart_points(array $series, int $width = 640, int $height = 240, int $paddingX = 20, int $paddingY = 18): array
{
    if (!$series) {
        return [];
    }

    $maxValue = 0;
    foreach ($series as $point) {
        $maxValue = max($maxValue, (int)($point['paid_orders'] ?? 0));
    }
    if ($maxValue <= 0) {
        $maxValue = 1;
    }

    $count = count($series);
    $plotWidth = max(1, $width - ($paddingX * 2));
    $plotHeight = max(1, $height - ($paddingY * 2));
    $stepX = $count > 1 ? ($plotWidth / max(1, $count - 1)) : 0;
    $points = [];

    foreach ($series as $index => $point) {
        $value = (int)($point['paid_orders'] ?? 0);
        $points[] = [
            'x' => $count > 1 ? ($paddingX + ($index * $stepX)) : ($width / 2),
            'y' => $height - $paddingY - (($value / $maxValue) * $plotHeight),
            'value' => $value,
            'label' => (string)($point['short_label'] ?? ''),
        ];
    }

    return $points;
}

function admin_dashboard_donut_segments(array $products, float $radius = 42.0, float $circumference = 263.8937829): array
{
    $segments = [];
    $offset = 0.0;

    foreach ($products as $product) {
        $percent = (float)($product['percent'] ?? 0);
        $dash = $circumference * ($percent / 100);
        $segments[] = [
            'color' => (string)($product['color'] ?? '#111827'),
            'dash' => $dash,
            'gap' => max(0.0, $circumference - $dash),
            'offset' => $offset,
            'label' => (string)($product['product_name'] ?? 'Product'),
            'count' => (int)($product['sold_count'] ?? 0),
            'percent' => $percent,
            'radius' => $radius,
        ];
        $offset -= $dash;
    }

    return $segments;
}

function admin_recent_orders(Mysql_ks $db, int $limit = 8): array
{
    if (!schema_object_exists($db, 'orders')) {
        return [];
    }

    $limit = max(1, min(20, $limit));
    return $db->select_full_user(
        "SELECT
            orders.id,
            orders.customer_id,
            orders.status,
            orders.payment_status,
            orders.fulfillment_status,
            orders.payment_method,
            orders.order_reference,
            orders.total_amount,
            orders.created_at,
            currencies.code AS currency_code,
            currencies.symbol AS currency_symbol,
            customers.email AS customer_email,
            products.name AS product_name,
            product_providers.name AS provider_name
         FROM orders
         LEFT JOIN customers ON customers.id = orders.customer_id
         LEFT JOIN products ON products.id = orders.product_id
         LEFT JOIN product_providers ON product_providers.id = products.provider_id
         LEFT JOIN currencies ON currencies.id = orders.currency_id
         ORDER BY orders.id DESC
         LIMIT {$limit}"
    );
}

function admin_order_count(Mysql_ks $db, int $customerId = 0): int
{
    if (!schema_object_exists($db, 'orders')) {
        return 0;
    }

    $where = $customerId > 0 ? ' WHERE customer_id = ' . $customerId : '';
    $row = $db->select_user("SELECT COUNT(*) AS total FROM orders{$where}");
    return (int)($row['total'] ?? 0);
}

function admin_order_rows(Mysql_ks $db, int $limit = 20, int $offset = 0, int $customerId = 0): array
{
    if (!schema_object_exists($db, 'orders')) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $deliveryLinkVisibleSelect = schema_column_exists($db, 'orders', 'delivery_link_visible')
        ? 'orders.delivery_link_visible'
        : '0 AS delivery_link_visible';
    $providerUrlReplacementFromSelect = schema_column_exists($db, 'product_providers', 'url_replacement_from')
        ? 'product_providers.url_replacement_from'
        : 'NULL';
    $providerUrlReplacementToSelect = schema_column_exists($db, 'product_providers', 'url_replacement_to')
        ? 'product_providers.url_replacement_to'
        : 'NULL';
    $where = $customerId > 0 ? 'WHERE orders.customer_id = ' . $customerId : '';

    return $db->select_full_user(
        "SELECT
            orders.id,
            orders.customer_id,
            orders.product_id,
            orders.payment_method,
            orders.status,
            orders.payment_status,
            orders.fulfillment_status,
            orders.total_amount,
            orders.order_reference,
            orders.support_note,
            orders.transaction_reference,
            orders.customer_note,
            orders.delivery_link,
            {$deliveryLinkVisibleSelect},
            orders.started_at,
            orders.created_at,
            orders.expires_at,
            orders.paid_at,
            customers.email AS customer_email,
            products.provider_id,
            products.name AS product_name,
            products.duration_hours,
            product_providers.name AS provider_name,
            product_providers.dashboard_url,
            product_providers.supports_manual_delivery,
            product_providers.supports_url_replacement,
            {$providerUrlReplacementFromSelect} AS url_replacement_from,
            {$providerUrlReplacementToSelect} AS url_replacement_to,
            currencies.code AS currency_code
         FROM orders
         LEFT JOIN customers ON customers.id = orders.customer_id
         LEFT JOIN products ON products.id = orders.product_id
         LEFT JOIN product_providers ON product_providers.id = products.provider_id
         LEFT JOIN currencies ON currencies.id = orders.currency_id
         {$where}
         ORDER BY orders.id DESC
         LIMIT {$offset}, {$limit}"
    );
}

function admin_order_find(Mysql_ks $db, int $orderId): ?array
{
    if ($orderId <= 0 || !schema_object_exists($db, 'orders')) {
        return null;
    }

    $deliveryLinkVisibleSelect = schema_column_exists($db, 'orders', 'delivery_link_visible')
        ? 'orders.delivery_link_visible'
        : '0 AS delivery_link_visible';
    $providerUrlReplacementFromSelect = schema_column_exists($db, 'product_providers', 'url_replacement_from')
        ? 'product_providers.url_replacement_from'
        : 'NULL';
    $providerUrlReplacementToSelect = schema_column_exists($db, 'product_providers', 'url_replacement_to')
        ? 'product_providers.url_replacement_to'
        : 'NULL';

    $row = $db->select_user(
        "SELECT
            orders.id,
            orders.customer_id,
            orders.product_id,
            orders.order_reference,
            orders.source_system,
            orders.payment_method,
            orders.status,
            orders.payment_status,
            orders.fulfillment_status,
            orders.total_amount,
            orders.customer_note,
            orders.support_note,
            orders.delivery_link,
            {$deliveryLinkVisibleSelect},
            orders.transaction_reference,
            orders.started_at,
            orders.expires_at,
            orders.paid_at,
            orders.created_at,
            customers.email AS customer_email,
            products.provider_id,
            products.name AS product_name,
            products.duration_hours,
            products.currency_id AS product_currency_id,
            product_providers.name AS provider_name,
            product_providers.dashboard_url,
            product_providers.supports_manual_delivery,
            product_providers.supports_url_replacement,
            {$providerUrlReplacementFromSelect} AS url_replacement_from,
            {$providerUrlReplacementToSelect} AS url_replacement_to,
            currencies.code AS currency_code
         FROM orders
         LEFT JOIN customers ON customers.id = orders.customer_id
         LEFT JOIN products ON products.id = orders.product_id
         LEFT JOIN product_providers ON product_providers.id = products.provider_id
         LEFT JOIN currencies ON currencies.id = orders.currency_id
         WHERE orders.id = {$orderId}
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_normalize_datetime_input(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $normalized = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
        $normalized .= ':00';
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function admin_order_status_options(string $current = ''): array
{
    $options = ['pending_payment', 'active', 'expired', 'cancelled'];
    $current = trim($current);
    if ($current !== '' && !in_array($current, $options, true)) {
        $options[] = $current;
    }

    return $options;
}

function admin_order_payment_status_options(string $current = ''): array
{
    $options = ['unpaid', 'paid', 'refunded', 'archived'];
    $current = trim($current);
    if ($current !== '' && !in_array($current, $options, true)) {
        $options[] = $current;
    }

    return $options;
}

function admin_order_fulfillment_status_options(string $current = ''): array
{
    $options = ['pending', 'delivered', 'cancelled'];
    $current = trim($current);
    if ($current !== '' && !in_array($current, $options, true)) {
        $options[] = $current;
    }

    return $options;
}

function admin_order_payment_method_options(?string $current = ''): array
{
    $options = ['', 'crypto', 'bank_transfer'];
    $current = trim((string)$current);
    if ($current !== '' && !in_array($current, $options, true)) {
        $options[] = $current;
    }

    return $options;
}

function admin_order_progress_data(array $order): array
{
    $createdAt = !empty($order['created_at']) ? strtotime((string)$order['created_at']) : 0;
    $expiresAt = !empty($order['expires_at']) ? strtotime((string)$order['expires_at']) : 0;
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

function admin_remaining_time_label(int $seconds): string
{
    $seconds = max(0, $seconds);
    if ($seconds === 0) {
        return 'Expired';
    }

    $days = (int)floor($seconds / 86400);
    $hours = (int)floor(($seconds % 86400) / 3600);
    $minutes = (int)floor(($seconds % 3600) / 60);

    if ($days > 0) {
        return $days . 'd ' . $hours . 'h left';
    }
    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm left';
    }

    return max(1, $minutes) . 'm left';
}

function admin_order_status_visual(array $order): array
{
    $status = strtolower(trim((string)($order['status'] ?? '')));
    $paymentStatus = strtolower(trim((string)($order['payment_status'] ?? '')));
    $fulfillmentStatus = strtolower(trim((string)($order['fulfillment_status'] ?? '')));

    if ($status === 'pending_payment' && $paymentStatus === 'paid' && !in_array($fulfillmentStatus, ['delivered', 'fulfilled', 'completed'], true)) {
        return [
            'icon' => 'bi bi-check-circle-fill',
            'class' => 'admin-order-status-icon--awaiting-activation',
            'label' => 'Payment confirmed',
        ];
    }

    if ($status === 'pending_payment' || $paymentStatus === 'unpaid' || $fulfillmentStatus === 'pending') {
        return [
            'icon' => 'bi bi-arrow-repeat',
            'class' => 'admin-order-status-icon--pending',
            'label' => 'Pending',
        ];
    }

    if ($status === 'expired') {
        return [
            'icon' => 'bi bi-x-circle-fill',
            'class' => 'admin-order-status-icon--expired',
            'label' => 'Expired',
        ];
    }

    if ($status === 'cancelled' || $fulfillmentStatus === 'cancelled') {
        return [
            'icon' => 'bi bi-slash-circle-fill',
            'class' => 'admin-order-status-icon--cancelled',
            'label' => 'Cancelled',
        ];
    }

    if ($status === 'active' && $paymentStatus === 'paid') {
        return [
            'icon' => 'bi bi-check-circle-fill',
            'class' => 'admin-order-status-icon--active',
            'label' => 'Active',
        ];
    }

    return [
        'icon' => 'bi bi-circle-fill',
        'class' => 'admin-order-status-icon--neutral',
        'label' => ucfirst($status !== '' ? $status : 'status'),
    ];
}

function admin_format_datetime_local(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp);
}

function admin_format_money_value($amount, string $currencyCode = ''): string
{
    $currencyCode = trim($currencyCode);
    if (!is_numeric($amount)) {
        return trim('— ' . $currencyCode);
    }

    return trim(number_format((float)$amount, 2, '.', '') . ' ' . $currencyCode);
}

function admin_format_money_value_with_symbol($amount, string $currencyCode = '', string $currencySymbol = ''): string
{
    $currencyCode = trim($currencyCode);
    $currencySymbol = trim($currencySymbol);
    $currencyLabel = $currencySymbol !== '' ? $currencySymbol : $currencyCode;

    if (!is_numeric($amount)) {
        return trim('— ' . $currencyLabel);
    }

    return trim(number_format((float)$amount, 2, '.', '') . ' ' . $currencyLabel);
}

function admin_compact_datetime_label(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    $today = date('Y-m-d');
    if (date('Y-m-d', $timestamp) === $today) {
        return date('H:i', $timestamp);
    }

    if (date('Y', $timestamp) === date('Y')) {
        return date('d.m', $timestamp);
    }

    return date('d.m.y', $timestamp);
}

function admin_country_flag_url(string $countryCode): string
{
    $countryCode = strtoupper(trim($countryCode));
    if ($countryCode === '') {
        return '';
    }

    $flagPath = dirname(__DIR__, 2) . '/public_html/img/flags/' . $countryCode . '.gif';
    if (!is_file($flagPath)) {
        return '';
    }

    return '/img/flags/' . $countryCode . '.gif';
}

function admin_locale_flag_url(string $localeCode): string
{
    $localeCode = strtolower(trim($localeCode));
    if ($localeCode === 'pl') {
        return admin_country_flag_url('PL');
    }

    if ($localeCode === 'de') {
        return admin_country_flag_url('DE');
    }

    return admin_country_flag_url('GB');
}

function admin_customer_rows(Mysql_ks $db, int $limit = 20): array
{
    if (!schema_object_exists($db, 'customers')) {
        return [];
    }

    app_ensure_customer_runtime_columns($db);

    $limit = max(1, min(50, $limit));
    $balanceSelect = schema_column_exists($db, 'customers', 'balance_amount')
        ? 'customers.balance_amount'
        : '0.00 AS balance_amount';
    $ordersTotalSelect = schema_object_exists($db, 'orders')
        ? "(SELECT COUNT(*) FROM orders WHERE orders.customer_id = customers.id)"
        : '0';
    $activeOrdersSelect = schema_object_exists($db, 'orders')
        ? "(SELECT COUNT(*) FROM orders WHERE orders.customer_id = customers.id AND orders.status = 'active')"
        : '0';
    $pendingOrdersSelect = schema_object_exists($db, 'orders')
        ? "(SELECT COUNT(*) FROM orders WHERE orders.customer_id = customers.id AND orders.status = 'pending_payment')"
        : '0';
    $openCryptoPaymentsSelect = schema_object_exists($db, 'crypto_deposit_requests')
        ? "(SELECT COUNT(*) FROM crypto_deposit_requests WHERE crypto_deposit_requests.customer_id = customers.id AND crypto_deposit_requests.status IN ('pending', 'awaiting_confirmation'))"
        : '0';
    $openBankPaymentsSelect = schema_object_exists($db, 'bank_transfer_requests')
        ? "(SELECT COUNT(*) FROM bank_transfer_requests WHERE bank_transfer_requests.customer_id = customers.id AND bank_transfer_requests.status IN ('pending_payment', 'awaiting_review'))"
        : '0';

    return $db->select_full_user(
        "SELECT
            customers.id,
            customers.email,
            customers.locale_code,
            customers.country_code,
            customers.status,
            customers.customer_type,
            customers.email_verified_at,
            customers.registered_at,
            customers.last_login_at,
            {$balanceSelect},
            {$ordersTotalSelect} AS orders_total,
            {$activeOrdersSelect} AS orders_active_total,
            {$pendingOrdersSelect} AS orders_pending_total,
            ({$openCryptoPaymentsSelect} + {$openBankPaymentsSelect}) AS open_payments_total
         FROM customers
         ORDER BY customers.id DESC
         LIMIT {$limit}"
    );
}

function admin_customer_management_summary(Mysql_ks $db, int $customerId): array
{
    if ($customerId <= 0 || !schema_object_exists($db, 'customers')) {
        return [
            'orders_total' => 0,
            'orders_active_total' => 0,
            'orders_pending_total' => 0,
            'open_payments_total' => 0,
            'wallets_total' => 0,
            'bank_accounts_total' => 0,
        ];
    }

    $ordersTotal = 0;
    $ordersActiveTotal = 0;
    $ordersPendingTotal = 0;
    $openPaymentsTotal = 0;
    $walletsTotal = 0;
    $bankAccountsTotal = 0;

    if (schema_object_exists($db, 'orders')) {
        $row = $db->select_user(
            "SELECT
                COUNT(*) AS orders_total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS orders_active_total,
                SUM(CASE WHEN status = 'pending_payment' THEN 1 ELSE 0 END) AS orders_pending_total
             FROM orders
             WHERE customer_id = {$customerId}"
        );
        $ordersTotal = (int)($row['orders_total'] ?? 0);
        $ordersActiveTotal = (int)($row['orders_active_total'] ?? 0);
        $ordersPendingTotal = (int)($row['orders_pending_total'] ?? 0);
    }

    if (schema_object_exists($db, 'crypto_deposit_requests')) {
        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM crypto_deposit_requests
             WHERE customer_id = {$customerId}
               AND status IN ('pending', 'awaiting_confirmation')"
        );
        $openPaymentsTotal += (int)($row['total'] ?? 0);
    }

    if (schema_object_exists($db, 'bank_transfer_requests')) {
        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM bank_transfer_requests
             WHERE customer_id = {$customerId}
               AND status IN ('pending_payment', 'awaiting_review')"
        );
        $openPaymentsTotal += (int)($row['total'] ?? 0);
    }

    if (schema_object_exists($db, 'customer_crypto_wallets')) {
        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM customer_crypto_wallets
             WHERE customer_id = {$customerId}"
        );
        $walletsTotal = (int)($row['total'] ?? 0);
    }

    if (schema_object_exists($db, 'customer_bank_accounts')) {
        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM customer_bank_accounts
             WHERE customer_id = {$customerId}"
        );
        $bankAccountsTotal = (int)($row['total'] ?? 0);
    }

    return [
        'orders_total' => $ordersTotal,
        'orders_active_total' => $ordersActiveTotal,
        'orders_pending_total' => $ordersPendingTotal,
        'open_payments_total' => $openPaymentsTotal,
        'wallets_total' => $walletsTotal,
        'bank_accounts_total' => $bankAccountsTotal,
    ];
}

function admin_customer_detail_row(Mysql_ks $db, int $customerId): ?array
{
    if ($customerId <= 0 || !schema_object_exists($db, 'customers')) {
        return null;
    }

    app_ensure_customer_runtime_columns($db);

    $balanceSelect = schema_column_exists($db, 'customers', 'balance_amount')
        ? 'customers.balance_amount'
        : '0.00 AS balance_amount';
    $notificationSelect = app_customer_notification_select_sql($db, 'customers');

    $row = $db->select_user(
        "SELECT
            customers.id,
            customers.email,
            customers.locale_code,
            customers.country_code,
            customers.ip_address,
            customers.status,
            customers.customer_type,
            customers.email_verified_at,
            customers.registered_at,
            customers.last_login_at,
            {$notificationSelect},
            {$balanceSelect}
         FROM customers
         WHERE customers.id = {$customerId}
         LIMIT 1"
    );

    return is_array($row) ? app_normalize_customer_record($row) : null;
}

function admin_customer_order_rows(Mysql_ks $db, int $customerId, int $limit = 12): array
{
    if ($customerId <= 0 || !schema_object_exists($db, 'orders')) {
        return [];
    }

    $limit = max(1, min(30, $limit));

    return $db->select_full_user(
        "SELECT
            orders.id,
            orders.status,
            orders.payment_status,
            orders.fulfillment_status,
            orders.payment_method,
            orders.total_amount,
            orders.created_at,
            orders.expires_at,
            currencies.code AS currency_code,
            products.name AS product_name,
            product_providers.name AS provider_name,
            product_providers.logo_url AS provider_logo_url
         FROM orders
         LEFT JOIN currencies ON currencies.id = orders.currency_id
         LEFT JOIN products ON products.id = orders.product_id
         LEFT JOIN product_providers ON product_providers.id = products.provider_id
         WHERE orders.customer_id = {$customerId}
         ORDER BY orders.id DESC
         LIMIT {$limit}"
    );
}

function admin_customer_payment_activity(Mysql_ks $db, int $customerId, int $limit = 12): array
{
    if ($customerId <= 0) {
        return [];
    }

    $rows = [];
    $limit = max(1, min(30, $limit));

    if (schema_object_exists($db, 'crypto_deposit_requests')) {
        $rows = array_merge($rows, $db->select_full_user(
            "SELECT
                'crypto' AS payment_type,
                crypto_deposit_requests.id,
                crypto_deposit_requests.order_id,
                crypto_deposit_requests.status,
                crypto_deposit_requests.requested_fiat_amount AS amount_value,
                crypto_deposit_requests.requested_crypto_amount AS crypto_value,
                crypto_deposit_requests.requested_at,
                crypto_assets.code AS asset_code,
                crypto_assets.name AS asset_name,
                crypto_assets.logo_url AS asset_logo_url,
                crypto_wallet_addresses.address AS wallet_address,
                crypto_wallet_addresses.network_code AS network_code,
                currencies.code AS currency_code,
                currencies.symbol AS currency_symbol
             FROM crypto_deposit_requests
             LEFT JOIN crypto_assets ON crypto_assets.id = crypto_deposit_requests.crypto_asset_id
             LEFT JOIN crypto_wallet_addresses ON crypto_wallet_addresses.id = crypto_deposit_requests.wallet_address_id
             LEFT JOIN currencies ON currencies.id = crypto_deposit_requests.fiat_currency_id
             WHERE crypto_deposit_requests.customer_id = {$customerId}
             ORDER BY crypto_deposit_requests.id DESC
             LIMIT {$limit}"
        ));
    }

    if (schema_object_exists($db, 'bank_transfer_requests')) {
        $rows = array_merge($rows, $db->select_full_user(
            "SELECT
                'bank' AS payment_type,
                bank_transfer_requests.id,
                bank_transfer_requests.order_id,
                bank_transfer_requests.status,
                bank_transfer_requests.requested_amount AS amount_value,
                '' AS crypto_value,
                bank_transfer_requests.requested_at,
                customer_bank_accounts.currency_code AS asset_code,
                '' AS asset_name,
                '' AS asset_logo_url,
                customer_bank_accounts.iban AS wallet_address,
                '' AS network_code,
                currencies.code AS currency_code,
                currencies.symbol AS currency_symbol,
                bank_transfer_requests.payment_reference,
                customer_bank_accounts.bank_name,
                customer_bank_accounts.label AS bank_label
             FROM bank_transfer_requests
             LEFT JOIN customer_bank_accounts
               ON customer_bank_accounts.bank_account_assignment_id = bank_transfer_requests.bank_account_assignment_id
             LEFT JOIN currencies ON currencies.id = bank_transfer_requests.currency_id
             WHERE bank_transfer_requests.customer_id = {$customerId}
             ORDER BY bank_transfer_requests.id DESC
             LIMIT {$limit}"
        ));
    }

    usort($rows, static function (array $left, array $right): int {
        return strcmp((string)($right['requested_at'] ?? ''), (string)($left['requested_at'] ?? ''));
    });

    return array_slice($rows, 0, $limit);
}

function admin_customer_wallet_rows(Mysql_ks $db, int $customerId, int $limit = 12): array
{
    if ($customerId <= 0 || !schema_object_exists($db, 'customer_crypto_wallets')) {
        return [];
    }

    $limit = max(1, min(30, $limit));

    return $db->select_full_user(
        "SELECT
            wallet_assignment_id,
            crypto_asset_code,
            crypto_asset_name,
            label,
            owner_full_name,
            address,
            network_code,
            wallet_provider,
            status,
            assigned_at
         FROM customer_crypto_wallets
         WHERE customer_id = {$customerId}
           AND status IN ('active', 'reserved')
         ORDER BY assigned_at DESC
         LIMIT {$limit}"
    );
}

function admin_customer_bank_rows(Mysql_ks $db, int $customerId, int $limit = 12): array
{
    if ($customerId <= 0 || !schema_object_exists($db, 'customer_bank_accounts')) {
        return [];
    }

    $limit = max(1, min(30, $limit));

    return $db->select_full_user(
        "SELECT
            bank_account_id,
            bank_account_assignment_id,
            currency_code,
            label,
            account_holder_name,
            bank_name,
            iban,
            status,
            assigned_at
         FROM customer_bank_accounts
         WHERE customer_id = {$customerId}
           AND status IN ('active', 'reserved')
         ORDER BY assigned_at DESC
         LIMIT {$limit}"
    );
}

function admin_bank_account_delete_summary(Mysql_ks $db, int $accountId): array
{
    $summary = [
        'active_assignments_total' => 0,
        'assignments_total' => 0,
        'payments_total' => 0,
        'can_delete' => false,
    ];

    if ($accountId <= 0 || !schema_object_exists($db, 'bank_accounts')) {
        return $summary;
    }

    if (schema_object_exists($db, 'bank_account_assignments')) {
        $row = $db->select_user(
            "SELECT
                SUM(CASE WHEN status IN ('reserved', 'active') THEN 1 ELSE 0 END) AS active_total,
                COUNT(*) AS assignments_total
             FROM bank_account_assignments
             WHERE bank_account_id = {$accountId}"
        );
        $summary['active_assignments_total'] = (int)($row['active_total'] ?? 0);
        $summary['assignments_total'] = (int)($row['assignments_total'] ?? 0);
    }

    if (schema_object_exists($db, 'bank_transfer_requests')) {
        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM bank_transfer_requests
             WHERE bank_account_id = {$accountId}"
        );
        $summary['payments_total'] = (int)($row['total'] ?? 0);
    }

    $summary['can_delete'] = $summary['active_assignments_total'] === 0
        && $summary['assignments_total'] === 0
        && $summary['payments_total'] === 0;

    return $summary;
}

function admin_crypto_wallet_delete_summary(Mysql_ks $db, int $walletId): array
{
    $summary = [
        'active_assignments_total' => 0,
        'assignments_total' => 0,
        'payments_total' => 0,
        'transactions_total' => 0,
        'can_delete' => false,
    ];

    if ($walletId <= 0 || !schema_object_exists($db, 'crypto_wallet_addresses')) {
        return $summary;
    }

    if (schema_object_exists($db, 'crypto_wallet_assignments')) {
        $row = $db->select_user(
            "SELECT
                SUM(CASE WHEN status IN ('reserved', 'active') THEN 1 ELSE 0 END) AS active_total,
                COUNT(*) AS assignments_total
             FROM crypto_wallet_assignments
             WHERE wallet_address_id = {$walletId}"
        );
        $summary['active_assignments_total'] = (int)($row['active_total'] ?? 0);
        $summary['assignments_total'] = (int)($row['assignments_total'] ?? 0);
    }

    if (schema_object_exists($db, 'crypto_deposit_requests')) {
        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM crypto_deposit_requests
             WHERE wallet_address_id = {$walletId}"
        );
        $summary['payments_total'] = (int)($row['total'] ?? 0);
    }

    if (schema_object_exists($db, 'crypto_deposit_transactions')) {
        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM crypto_deposit_transactions
             WHERE wallet_address_id = {$walletId}"
        );
        $summary['transactions_total'] = (int)($row['total'] ?? 0);
    }

    $summary['can_delete'] = $summary['active_assignments_total'] === 0
        && $summary['assignments_total'] === 0
        && $summary['payments_total'] === 0
        && $summary['transactions_total'] === 0;

    return $summary;
}

function admin_customer_activity_rows(Mysql_ks $db, int $customerId, int $limit = 30): array
{
    if ($customerId <= 0 || !schema_object_exists($db, 'customer_activity_logs')) {
        return [];
    }

    $limit = max(1, min(100, $limit));

    return $db->select_full_user(
        "SELECT
            customer_activity_logs.id,
            customer_activity_logs.actor_type,
            customer_activity_logs.action_key,
            customer_activity_logs.description,
            customer_activity_logs.ip_address,
            customer_activity_logs.created_at,
            admin_users.login_name AS admin_login
         FROM customer_activity_logs
         LEFT JOIN admin_users ON admin_users.id = customer_activity_logs.admin_user_id
         WHERE customer_activity_logs.customer_id = {$customerId}
         ORDER BY customer_activity_logs.id DESC
         LIMIT {$limit}"
    );
}

function admin_delete_customer_wallet_assignment(
    Mysql_ks $db,
    int $customerId,
    int $walletAssignmentId,
    int $adminUserId,
    string $ipAddress = ''
): array
{
    if (
        $customerId <= 0
        || $walletAssignmentId <= 0
        || !schema_object_exists($db, 'customer_crypto_wallets')
        || !schema_object_exists($db, 'crypto_wallet_assignments')
    ) {
        return ['ok' => false, 'message' => 'Wallet assignment not found.'];
    }

    $assignment = $db->select_user(
        "SELECT
            wallet_assignment_id,
            crypto_asset_code,
            crypto_asset_name,
            address
         FROM customer_crypto_wallets
         WHERE customer_id = {$customerId}
           AND wallet_assignment_id = {$walletAssignmentId}
         LIMIT 1"
    );

    if (!$assignment) {
        return ['ok' => false, 'message' => 'Wallet assignment not found.'];
    }

    $releasedAt = date('Y-m-d H:i:s');
    $deleted = $db->query(
        "UPDATE crypto_wallet_assignments
         SET status = 'released',
             released_at = '{$releasedAt}'
         WHERE customer_id = {$customerId}
           AND id = {$walletAssignmentId}
         LIMIT 1"
    );

    if ($deleted) {
        $assetLabel = trim((string)($assignment['crypto_asset_name'] ?? ''));
        if ($assetLabel === '') {
            $assetLabel = strtoupper(trim((string)($assignment['crypto_asset_code'] ?? '')));
        }
        $addressLabel = admin_compact_wallet_address((string)($assignment['address'] ?? ''), 6, 5);
        admin_log_customer_and_admin(
            $db,
            $customerId,
            $adminUserId,
            'customer_wallet_assignment_deleted',
            'Removed crypto wallet assignment' . ($assetLabel !== '' ? ' [' . $assetLabel . ']' : '') . ($addressLabel !== '' ? ' ' . $addressLabel : '') . '.',
            $ipAddress
        );
    }

    return [
        'ok' => (bool)$deleted,
        'message' => $deleted ? 'Wallet assignment removed successfully.' : 'Unable to remove wallet assignment.',
    ];
}

function admin_delete_customer_bank_assignment(
    Mysql_ks $db,
    int $customerId,
    int $bankAssignmentId,
    int $adminUserId,
    string $ipAddress = ''
): array
{
    if (
        $customerId <= 0
        || $bankAssignmentId <= 0
        || !schema_object_exists($db, 'customer_bank_accounts')
        || !schema_object_exists($db, 'bank_account_assignments')
    ) {
        return ['ok' => false, 'message' => 'Bank account assignment not found.'];
    }

    $assignment = $db->select_user(
        "SELECT
            bank_account_assignment_id,
            bank_name,
            label,
            iban
         FROM customer_bank_accounts
         WHERE customer_id = {$customerId}
           AND bank_account_assignment_id = {$bankAssignmentId}
         LIMIT 1"
    );

    if (!$assignment) {
        return ['ok' => false, 'message' => 'Bank account assignment not found.'];
    }

    $releasedAt = date('Y-m-d H:i:s');
    $deleted = $db->query(
        "UPDATE bank_account_assignments
         SET status = 'released',
             released_at = '{$releasedAt}'
         WHERE customer_id = {$customerId}
           AND id = {$bankAssignmentId}
         LIMIT 1"
    );

    if ($deleted) {
        $bankLabel = trim((string)($assignment['label'] ?? ''));
        if ($bankLabel === '') {
            $bankLabel = trim((string)($assignment['bank_name'] ?? ''));
        }
        $ibanLabel = admin_compact_wallet_address((string)($assignment['iban'] ?? ''), 6, 5);
        admin_log_customer_and_admin(
            $db,
            $customerId,
            $adminUserId,
            'customer_bank_assignment_deleted',
            'Removed bank account assignment' . ($bankLabel !== '' ? ' [' . $bankLabel . ']' : '') . ($ibanLabel !== '' ? ' ' . $ibanLabel : '') . '.',
            $ipAddress
        );
    }

    return [
        'ok' => (bool)$deleted,
        'message' => $deleted ? 'Bank account assignment removed successfully.' : 'Unable to remove bank account assignment.',
    ];
}

function admin_delete_customer_activity_entry(
    Mysql_ks $db,
    int $customerId,
    int $activityId,
    int $adminUserId,
    string $ipAddress = ''
): array
{
    if ($customerId <= 0 || $activityId <= 0 || !schema_object_exists($db, 'customer_activity_logs')) {
        return ['ok' => false, 'message' => 'History entry not found.'];
    }

    $activityRow = $db->select_user(
        "SELECT
            id,
            action_key,
            description
         FROM customer_activity_logs
         WHERE customer_id = {$customerId}
           AND id = {$activityId}
         LIMIT 1"
    );

    if (!$activityRow) {
        return ['ok' => false, 'message' => 'History entry not found.'];
    }

    $deleted = $db->query(
        "DELETE FROM customer_activity_logs
         WHERE customer_id = {$customerId}
           AND id = {$activityId}
         LIMIT 1"
    );

    if ($deleted && $adminUserId > 0) {
        admin_activity_log(
            $db,
            0,
            $adminUserId,
            'customer_activity_entry_deleted',
            'Deleted customer history entry #' . $activityId . ' for customer #' . $customerId . ': ' . trim((string)($activityRow['action_key'] ?? 'entry')) . '.',
            $ipAddress
        );
    }

    return [
        'ok' => (bool)$deleted,
        'message' => $deleted ? 'History entry removed successfully.' : 'Unable to remove history entry.',
    ];
}

function admin_customer_log(
    Mysql_ks $db,
    int $customerId,
    int $adminUserId,
    string $actionKey,
    string $description,
    string $ipAddress = ''
): void {
    if ($customerId <= 0) {
        return;
    }

    admin_activity_log($db, $customerId, $adminUserId, $actionKey, $description, $ipAddress);
}

function admin_activity_log(
    Mysql_ks $db,
    int $customerId,
    int $adminUserId,
    string $actionKey,
    string $description,
    string $ipAddress = ''
): void {
    if (!schema_object_exists($db, 'customer_activity_logs')) {
        return;
    }

    $db->insert(
        ['customer_id', 'admin_user_id', 'actor_type', 'action_key', 'description', 'ip_address'],
        [$customerId > 0 ? $customerId : null, $adminUserId > 0 ? $adminUserId : null, 'admin', $actionKey, $description, $ipAddress !== '' ? $ipAddress : null],
        'customer_activity_logs'
    );
}

function admin_log_customer_and_admin(
    Mysql_ks $db,
    int $customerId,
    int $adminUserId,
    string $actionKey,
    string $description,
    string $ipAddress = ''
): void {
    admin_customer_log($db, $customerId, $adminUserId, $actionKey, $description, $ipAddress);

    if ($adminUserId > 0) {
        admin_activity_log($db, 0, $adminUserId, $actionKey, $description, $ipAddress);
    }
}

function admin_save_customer_profile(Mysql_ks $db, int $customerId, array $payload, int $adminUserId, string $ipAddress = ''): array
{
    if ($customerId <= 0 || !schema_object_exists($db, 'customers')) {
        return ['ok' => false, 'message' => 'Customer not found.'];
    }

    $current = admin_customer_detail_row($db, $customerId);
    if (!$current) {
        return ['ok' => false, 'message' => 'Customer not found.'];
    }

    $email = strtolower(trim((string)($payload['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Enter a valid email address.'];
    }

    $existing = app_find_customer_by_email($db, $email);
    if ($existing && (int)($existing['id'] ?? 0) !== $customerId) {
        return ['ok' => false, 'message' => 'This email address is already assigned to another user.'];
    }

    $localeCode = admin_normalize_locale((string)($payload['locale_code'] ?? 'en'));
    $countryCode = strtoupper(substr(trim((string)($payload['country_code'] ?? '')), 0, 2));
    $ipValue = trim((string)($payload['ip_address'] ?? ''));
    $status = strtolower(trim((string)($payload['status'] ?? 'active')));
    if (!in_array($status, ['active', 'blocked', 'inactive'], true)) {
        $status = 'active';
    }
    $customerType = app_normalize_customer_type((string)($payload['customer_type'] ?? ($current['customer_type'] ?? 'client')));

    $emailVerifiedAt = admin_normalize_datetime_input((string)($payload['email_verified_at'] ?? ''));
    $registeredAt = admin_normalize_datetime_input((string)($payload['registered_at'] ?? ''));
    $lastLoginAt = admin_normalize_datetime_input((string)($payload['last_login_at'] ?? ''));
    if ($registeredAt === null) {
        $registeredAt = (string)($current['registered_at'] ?? date('Y-m-d H:i:s'));
    }
    $emailNotification = isset($payload['email_notification']) ? 1 : 0;
    $updateColumns = [
        'email',
        'locale_code',
        'country_code',
        'ip_address',
        'status',
        'customer_type',
        'email_verified_at',
        'registered_at',
        'last_login_at',
    ];
    $updateValues = [
        $email,
        $localeCode,
        $countryCode !== '' ? $countryCode : null,
        $ipValue !== '' ? $ipValue : null,
        $status,
        $customerType,
        $emailVerifiedAt,
        $registeredAt,
        $lastLoginAt,
    ];

    $notificationColumn = app_customer_notification_storage_column($db);
    if ($notificationColumn !== '') {
        $updateColumns[] = $notificationColumn;
        $updateValues[] = $emailNotification;
    }

    $updated = $db->update_using_id($updateColumns, $updateValues, 'customers', $customerId);

    if (!$updated) {
        return ['ok' => false, 'message' => 'Unable to save customer profile.'];
    }

    $actionKey = 'profile_updated';
    $description = 'Customer profile updated by admin.';
    if ((string)($current['status'] ?? '') !== $status) {
        $actionKey = $status === 'blocked' ? 'customer_blocked' : 'customer_status_changed';
        $description = 'Customer status changed from ' . (string)($current['status'] ?? 'unknown') . ' to ' . $status . '.';
    }

    admin_log_customer_and_admin($db, $customerId, $adminUserId, $actionKey, $description, $ipAddress);

    if ((string)($current['status'] ?? '') !== 'blocked' && $status === 'blocked') {
        app_queue_account_blocked_notification($db, $customerId);
    }

    return ['ok' => true, 'message' => 'Customer profile saved.'];
}

function admin_adjust_customer_balance(Mysql_ks $db, int $customerId, array $payload, int $adminUserId, string $ipAddress = ''): array
{
    if ($customerId <= 0 || !schema_object_exists($db, 'customers')) {
        return ['ok' => false, 'message' => 'Customer not found.'];
    }

    if (!schema_column_exists($db, 'customers', 'balance_amount')) {
        return ['ok' => false, 'message' => 'Balance is not available in this database schema yet.'];
    }

    $customer = admin_customer_detail_row($db, $customerId);
    if (!$customer) {
        return ['ok' => false, 'message' => 'Customer not found.'];
    }

    $amount = str_replace(',', '.', trim((string)($payload['adjustment_amount'] ?? '')));
    if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
        return ['ok' => false, 'message' => 'Enter a valid adjustment amount.'];
    }

    $direction = strtolower(trim((string)($payload['adjustment_direction'] ?? 'credit')));
    if (!in_array($direction, ['credit', 'debit'], true)) {
        $direction = 'credit';
    }

    $note = trim((string)($payload['adjustment_note'] ?? ''));
    $currentBalance = (float)($customer['balance_amount'] ?? 0);
    $delta = round((float)$amount, 2);
    $newBalance = $direction === 'debit'
        ? max(0, round($currentBalance - $delta, 2))
        : round($currentBalance + $delta, 2);

    $updated = $db->update_using_id(
        ['balance_amount'],
        [number_format($newBalance, 2, '.', '')],
        'customers',
        $customerId
    );

    if (!$updated) {
        return ['ok' => false, 'message' => 'Unable to update customer balance.'];
    }

    $description = ($direction === 'debit' ? 'Balance debited by admin: ' : 'Balance credited by admin: ')
        . number_format($delta, 2, '.', '');
    if ($note !== '') {
        $description .= ' (' . $note . ')';
    }

    admin_log_customer_and_admin(
        $db,
        $customerId,
        $adminUserId,
        $direction === 'debit' ? 'balance_debit' : 'balance_credit',
        $description,
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Customer balance updated.'];
}

function admin_payment_default_status(string $paymentType): string
{
    $paymentType = strtolower(trim($paymentType));
    return ($paymentType === 'crypto' || $paymentType === 'crypto_topup') ? 'pending' : 'pending_payment';
}

function admin_payment_review_status(string $paymentType): string
{
    $paymentType = strtolower(trim($paymentType));
    return $paymentType === 'crypto' ? 'awaiting_confirmation' : 'awaiting_review';
}

function admin_payment_runtime_statuses(Mysql_ks $db, string $paymentType): array
{
    $paymentType = strtolower(trim($paymentType));
    if ($paymentType === 'crypto_topup') {
        return ['pending', 'archived'];
    }

    $tableName = $paymentType === 'crypto' ? 'crypto_deposit_requests' : 'bank_transfer_requests';
    $knownStatuses = $paymentType === 'crypto'
        ? ['pending', 'awaiting_confirmation', 'confirmed', 'approved', 'paid', 'completed', 'cancelled', 'rejected', 'failed', 'archived']
        : ['pending_payment', 'awaiting_review', 'confirmed', 'approved', 'paid', 'completed', 'cancelled', 'rejected', 'failed', 'archived'];

    if (!schema_object_exists($db, $tableName)) {
        return $knownStatuses;
    }

    $rows = $db->select_full_user("SELECT DISTINCT status FROM {$tableName}");
    if (!is_array($rows)) {
        return $knownStatuses;
    }

    $foundStatuses = [];
    foreach ($rows as $row) {
        $status = strtolower(trim((string)($row['status'] ?? '')));
        if ($status !== '') {
            $foundStatuses[] = $status;
        }
    }

    return array_values(array_unique(array_merge($knownStatuses, $foundStatuses)));
}

function admin_payment_statuses_for_scope(Mysql_ks $db, string $paymentType, string $scope): array
{
    $paymentType = strtolower(trim($paymentType));
    $scope = strtolower(trim($scope));

    if ($paymentType === 'crypto' || $paymentType === 'crypto_topup') {
        $openStatuses = $paymentType === 'crypto_topup' ? ['pending'] : ['pending', 'awaiting_confirmation'];
        $allStatuses = admin_payment_runtime_statuses($db, $paymentType);
        if ($scope === 'new') {
            return ['pending'];
        }
        if ($scope === 'review' && $paymentType !== 'crypto_topup') {
            return ['awaiting_confirmation'];
        }
    } else {
        $openStatuses = ['pending_payment', 'awaiting_review'];
        $allStatuses = admin_payment_runtime_statuses($db, $paymentType);
        if ($scope === 'new') {
            return ['pending_payment'];
        }
        if ($scope === 'review') {
            return ['awaiting_review'];
        }
    }

    if ($scope === 'archived') {
        return array_values(array_filter($allStatuses, static function (string $status) use ($openStatuses): bool {
            return !in_array($status, $openStatuses, true);
        }));
    }

    if ($scope === 'open') {
        return array_values(array_filter($allStatuses, static function (string $status) use ($openStatuses): bool {
            return in_array($status, $openStatuses, true);
        }));
    }

    return $allStatuses;
}

function admin_payment_rows(Mysql_ks $db, int $limit = 10, int $customerId = 0, string $scope = 'open', string $paymentTypeFilter = '', bool $includeBankTransfers = true): array
{
    $rows = [];
    $limit = max(1, min(100, $limit));
    $customerWhere = $customerId > 0 ? ' AND %s.customer_id = ' . $customerId : '';
    $paymentTypeFilter = strtolower(trim($paymentTypeFilter));

    if (($paymentTypeFilter === '' || $paymentTypeFilter === 'crypto') && schema_object_exists($db, 'crypto_deposit_requests')) {
        $cryptoStatuses = admin_payment_statuses_for_scope($db, 'crypto', $scope);
        $cryptoStatusSql = "'" . implode("','", array_map([$db, 'escape'], $cryptoStatuses)) . "'";
        $cryptoRows = $db->select_full_user(
            "SELECT
                'crypto' AS payment_type,
                crypto_deposit_requests.id,
                crypto_deposit_requests.order_id,
                crypto_deposit_requests.customer_id,
                crypto_deposit_requests.status,
                crypto_deposit_requests.requested_fiat_amount AS amount_value,
                crypto_deposit_requests.requested_crypto_amount AS amount_crypto,
                COALESCE(crypto_deposit_requests.requested_at, crypto_deposit_requests.expires_at) AS requested_at,
                crypto_deposit_requests.expires_at,
                crypto_wallet_addresses.address AS payment_reference,
                crypto_wallet_addresses.address AS wallet_address,
                crypto_wallet_addresses.network_code AS network_code,
                customers.email AS customer_email,
                crypto_assets.code AS asset_code,
                crypto_assets.name AS asset_name,
                crypto_assets.logo_url AS asset_logo_url,
                currencies.code AS currency_code,
                currencies.symbol AS currency_symbol
             FROM crypto_deposit_requests
             LEFT JOIN customers ON customers.id = crypto_deposit_requests.customer_id
             LEFT JOIN currencies ON currencies.id = crypto_deposit_requests.fiat_currency_id
             LEFT JOIN crypto_assets ON crypto_assets.id = crypto_deposit_requests.crypto_asset_id
             LEFT JOIN crypto_wallet_addresses ON crypto_wallet_addresses.id = crypto_deposit_requests.wallet_address_id
             WHERE crypto_deposit_requests.status IN ({$cryptoStatusSql})
             " . sprintf($customerWhere, 'crypto_deposit_requests') . "
             ORDER BY crypto_deposit_requests.id DESC
             LIMIT {$limit}"
        );
        $rows = array_merge($rows, $cryptoRows);
    }

    if (($paymentTypeFilter === '' || $paymentTypeFilter === 'crypto') && schema_object_exists($db, 'crypto_topups')) {
        $legacyTopupsTable = schema_read_target($db, 'crypto_topups');
        $legacyTopupProductPriceColumn = schema_read_column($db, 'crypto_topups', 'product_price', 'package_price');
        $legacyTopupCreatedAtColumn = schema_read_column($db, 'crypto_topups', 'created_at', 'date');
        $legacyTopupStatusColumn = schema_read_column($db, 'crypto_topups', 'is_active', 'status');
        $legacyWhere = [];
        if ($scope === 'new' || $scope === 'open') {
            $legacyWhere[] = $legacyTopupsTable . '.' . $legacyTopupStatusColumn . ' = 0';
        } elseif ($scope === 'review') {
            $legacyWhere[] = '1 = 0';
        } elseif ($scope === 'archived') {
            $legacyWhere[] = $legacyTopupsTable . '.' . $legacyTopupStatusColumn . ' <> 0';
        }
        if ($customerId > 0) {
            $legacyWhere[] = $legacyTopupsTable . '.user_id = ' . $customerId;
        }

        $legacySqlWhere = $legacyWhere ? 'WHERE ' . implode(' AND ', $legacyWhere) : '';
        $legacyRows = $db->select_full_user(
            "SELECT
                'crypto_topup' AS payment_type,
                {$legacyTopupsTable}.id,
                NULL AS order_id,
                {$legacyTopupsTable}.user_id AS customer_id,
                CASE WHEN {$legacyTopupsTable}.{$legacyTopupStatusColumn} = 0 THEN 'pending' ELSE 'archived' END AS status,
                {$legacyTopupsTable}.{$legacyTopupProductPriceColumn} AS amount_value,
                {$legacyTopupsTable}.crypto_amount AS amount_crypto,
                {$legacyTopupsTable}.{$legacyTopupCreatedAtColumn} AS requested_at,
                NULL AS expires_at,
                cryptocurrency.adress_code AS payment_reference,
                cryptocurrency.adress_code AS wallet_address,
                '' AS network_code,
                customers.email AS customer_email,
                UPPER(COALESCE(cryptocurrency.symbol, '')) AS asset_code,
                cryptocurrency.name AS asset_name,
                cryptocurrency.logo_url AS asset_logo_url,
                '' AS currency_code,
                '' AS currency_symbol
             FROM {$legacyTopupsTable}
             LEFT JOIN customers ON customers.id = {$legacyTopupsTable}.user_id
             LEFT JOIN cryptocurrency ON cryptocurrency.id = {$legacyTopupsTable}.crypto_id
             {$legacySqlWhere}
             ORDER BY {$legacyTopupsTable}.id DESC
             LIMIT {$limit}"
        );
        $rows = array_merge($rows, $legacyRows);
    }

    if ($includeBankTransfers && ($paymentTypeFilter === '' || $paymentTypeFilter === 'bank') && schema_object_exists($db, 'bank_transfer_requests')) {
        $bankStatuses = admin_payment_statuses_for_scope($db, 'bank', $scope);
        $bankStatusSql = "'" . implode("','", array_map([$db, 'escape'], $bankStatuses)) . "'";
        $bankRows = $db->select_full_user(
            "SELECT
                'bank' AS payment_type,
                bank_transfer_requests.id,
                bank_transfer_requests.order_id,
                bank_transfer_requests.customer_id,
                bank_transfer_requests.status,
                bank_transfer_requests.requested_amount AS amount_value,
                NULL AS amount_crypto,
                COALESCE(bank_transfer_requests.requested_at, bank_transfer_requests.expires_at) AS requested_at,
                bank_transfer_requests.expires_at,
                bank_transfer_requests.payment_reference AS payment_reference,
                '' AS wallet_address,
                '' AS network_code,
                customers.email AS customer_email,
                '' AS asset_code,
                '' AS asset_name,
                '' AS asset_logo_url,
                currencies.code AS currency_code,
                currencies.symbol AS currency_symbol
             FROM bank_transfer_requests
             LEFT JOIN customers ON customers.id = bank_transfer_requests.customer_id
             LEFT JOIN currencies ON currencies.id = bank_transfer_requests.currency_id
             WHERE bank_transfer_requests.status IN ({$bankStatusSql})
             " . sprintf($customerWhere, 'bank_transfer_requests') . "
             ORDER BY bank_transfer_requests.id DESC
             LIMIT {$limit}"
        );
        $rows = array_merge($rows, $bankRows);
    }

    usort($rows, static function (array $left, array $right): int {
        return strcmp((string)($right['requested_at'] ?? ''), (string)($left['requested_at'] ?? ''));
    });

    return array_slice($rows, 0, $limit);
}

function admin_payment_summary(Mysql_ks $db, int $customerId = 0, bool $includeBankTransfers = true): array
{
    $summary = [
        'total' => 0,
        'open_total' => 0,
        'new_total' => 0,
        'review_total' => 0,
        'archived_total' => 0,
        'crypto_total' => 0,
        'bank_total' => 0,
    ];

    $customerWhereCrypto = $customerId > 0 ? ' WHERE customer_id = ' . $customerId : '';
    $customerWhereBank = $customerId > 0 ? ' WHERE customer_id = ' . $customerId : '';

    if (schema_object_exists($db, 'crypto_deposit_requests')) {
        $row = $db->select_user(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_total,
                SUM(CASE WHEN status = 'awaiting_confirmation' THEN 1 ELSE 0 END) AS review_total,
                SUM(CASE WHEN status IN ('cancelled', 'rejected', 'failed', 'archived', 'confirmed', 'approved', 'paid', 'completed') THEN 1 ELSE 0 END) AS archived_total
             FROM crypto_deposit_requests" . $customerWhereCrypto
        );

        $summary['crypto_total'] += (int)($row['total'] ?? 0);
        $summary['new_total'] += (int)($row['pending_total'] ?? 0);
        $summary['review_total'] += (int)($row['review_total'] ?? 0);
        $summary['archived_total'] += (int)($row['archived_total'] ?? 0);
    }

    if (schema_object_exists($db, 'crypto_topups')) {
        $legacyTopupsTable = schema_read_target($db, 'crypto_topups');
        $legacyTopupStatusColumn = schema_read_column($db, 'crypto_topups', 'is_active', 'status');
        $legacyCustomerWhere = $customerId > 0 ? ' WHERE user_id = ' . $customerId : '';
        $row = $db->select_user(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN {$legacyTopupStatusColumn} = 0 THEN 1 ELSE 0 END) AS pending_total,
                SUM(CASE WHEN {$legacyTopupStatusColumn} <> 0 THEN 1 ELSE 0 END) AS archived_total
             FROM {$legacyTopupsTable}{$legacyCustomerWhere}"
        );

        $summary['crypto_total'] += (int)($row['total'] ?? 0);
        $summary['new_total'] += (int)($row['pending_total'] ?? 0);
        $summary['archived_total'] += (int)($row['archived_total'] ?? 0);
    }

    if ($includeBankTransfers && schema_object_exists($db, 'bank_transfer_requests')) {
        $row = $db->select_user(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending_payment' THEN 1 ELSE 0 END) AS pending_total,
                SUM(CASE WHEN status = 'awaiting_review' THEN 1 ELSE 0 END) AS review_total,
                SUM(CASE WHEN status IN ('cancelled', 'rejected', 'failed', 'archived', 'confirmed', 'approved', 'paid', 'completed') THEN 1 ELSE 0 END) AS archived_total
             FROM bank_transfer_requests" . $customerWhereBank
        );

        $summary['bank_total'] += (int)($row['total'] ?? 0);
        $summary['new_total'] += (int)($row['pending_total'] ?? 0);
        $summary['review_total'] += (int)($row['review_total'] ?? 0);
        $summary['archived_total'] += (int)($row['archived_total'] ?? 0);
    }

    $summary['total'] = $summary['crypto_total'] + $summary['bank_total'];
    $summary['open_total'] = $summary['new_total'] + $summary['review_total'];

    return $summary;
}

function admin_payment_status_options(string $paymentType, string $current = ''): array
{
    $paymentType = strtolower(trim($paymentType));
    $current = strtolower(trim($current));

    if ($paymentType === 'crypto') {
        $options = ['pending', 'awaiting_confirmation', 'cancelled', 'archived'];
    } elseif ($paymentType === 'crypto_topup') {
        $options = ['pending', 'archived'];
    } else {
        $options = ['pending_payment', 'awaiting_review', 'cancelled', 'archived'];
    }

    if ($current !== '' && !in_array($current, $options, true)) {
        $options[] = $current;
    }

    return $options;
}

function admin_payment_find(Mysql_ks $db, string $paymentType, int $paymentId): ?array
{
    $paymentType = strtolower(trim($paymentType));
    if ($paymentId <= 0) {
        return null;
    }

    if ($paymentType === 'crypto' && schema_object_exists($db, 'crypto_deposit_requests')) {
        $row = $db->select_user(
            "SELECT
                'crypto' AS payment_type,
                crypto_deposit_requests.id,
                crypto_deposit_requests.order_id,
                crypto_deposit_requests.customer_id,
                crypto_deposit_requests.status,
                crypto_deposit_requests.requested_fiat_amount AS amount_value,
                crypto_deposit_requests.requested_crypto_amount AS amount_crypto,
                COALESCE(crypto_deposit_requests.requested_at, crypto_deposit_requests.expires_at) AS requested_at,
                crypto_deposit_requests.expires_at,
                crypto_deposit_requests.request_note,
                crypto_deposit_requests.created_by_admin_user_id,
                customers.email AS customer_email,
                currencies.code AS currency_code,
                currencies.symbol AS currency_symbol,
                crypto_assets.code AS asset_code,
                crypto_assets.name AS asset_name,
                crypto_wallet_addresses.address AS wallet_address,
                orders.status AS order_status
             FROM crypto_deposit_requests
             LEFT JOIN customers ON customers.id = crypto_deposit_requests.customer_id
             LEFT JOIN currencies ON currencies.id = crypto_deposit_requests.fiat_currency_id
             LEFT JOIN crypto_assets ON crypto_assets.id = crypto_deposit_requests.crypto_asset_id
             LEFT JOIN crypto_wallet_addresses ON crypto_wallet_addresses.id = crypto_deposit_requests.wallet_address_id
             LEFT JOIN orders ON orders.id = crypto_deposit_requests.order_id
             WHERE crypto_deposit_requests.id = {$paymentId}
             LIMIT 1"
        );

        return is_array($row) ? $row : null;
    }

    if ($paymentType === 'crypto_topup' && schema_object_exists($db, 'crypto_topups')) {
        $legacyTopupsTable = schema_read_target($db, 'crypto_topups');
        $legacyTopupProductPriceColumn = schema_read_column($db, 'crypto_topups', 'product_price', 'package_price');
        $legacyTopupCreatedAtColumn = schema_read_column($db, 'crypto_topups', 'created_at', 'date');
        $legacyTopupStatusColumn = schema_read_column($db, 'crypto_topups', 'is_active', 'status');
        $row = $db->select_user(
            "SELECT
                'crypto_topup' AS payment_type,
                {$legacyTopupsTable}.id,
                NULL AS order_id,
                {$legacyTopupsTable}.user_id AS customer_id,
                CASE WHEN {$legacyTopupsTable}.{$legacyTopupStatusColumn} = 0 THEN 'pending' ELSE 'archived' END AS status,
                {$legacyTopupsTable}.{$legacyTopupProductPriceColumn} AS amount_value,
                {$legacyTopupsTable}.crypto_amount AS amount_crypto,
                {$legacyTopupsTable}.{$legacyTopupCreatedAtColumn} AS requested_at,
                NULL AS expires_at,
                NULL AS request_note,
                NULL AS created_by_admin_user_id,
                customers.email AS customer_email,
                '' AS currency_code,
                '' AS currency_symbol,
                UPPER(COALESCE(cryptocurrency.symbol, '')) AS asset_code,
                cryptocurrency.name AS asset_name,
                cryptocurrency.logo_url AS asset_logo_url,
                cryptocurrency.adress_code AS wallet_address,
                NULL AS order_status
             FROM {$legacyTopupsTable}
             LEFT JOIN customers ON customers.id = {$legacyTopupsTable}.user_id
             LEFT JOIN cryptocurrency ON cryptocurrency.id = {$legacyTopupsTable}.crypto_id
             WHERE {$legacyTopupsTable}.id = {$paymentId}
             LIMIT 1"
        );

        return is_array($row) ? $row : null;
    }

    if ($paymentType === 'bank' && schema_object_exists($db, 'bank_transfer_requests')) {
        $row = $db->select_user(
            "SELECT
                'bank' AS payment_type,
                bank_transfer_requests.id,
                bank_transfer_requests.order_id,
                bank_transfer_requests.customer_id,
                bank_transfer_requests.status,
                bank_transfer_requests.requested_amount AS amount_value,
                COALESCE(bank_transfer_requests.requested_at, bank_transfer_requests.expires_at) AS requested_at,
                bank_transfer_requests.expires_at,
                bank_transfer_requests.submitted_at,
                bank_transfer_requests.approved_at,
                bank_transfer_requests.rejected_at,
                bank_transfer_requests.payment_reference,
                bank_transfer_requests.payer_name,
                bank_transfer_requests.payer_bank_name,
                bank_transfer_requests.customer_transfer_note,
                bank_transfer_requests.admin_review_note,
                bank_transfer_requests.created_by_admin_user_id,
                bank_transfer_requests.approved_by_admin_user_id,
                customers.email AS customer_email,
                currencies.code AS currency_code,
                currencies.symbol AS currency_symbol,
                bank_accounts.label AS bank_label,
                bank_accounts.bank_name,
                bank_accounts.iban,
                orders.status AS order_status
             FROM bank_transfer_requests
             LEFT JOIN customers ON customers.id = bank_transfer_requests.customer_id
             LEFT JOIN currencies ON currencies.id = bank_transfer_requests.currency_id
             LEFT JOIN bank_accounts ON bank_accounts.id = bank_transfer_requests.bank_account_id
             LEFT JOIN orders ON orders.id = bank_transfer_requests.order_id
             WHERE bank_transfer_requests.id = {$paymentId}
             LIMIT 1"
        );

        return is_array($row) ? $row : null;
    }

    return null;
}

function admin_save_payment(
    Mysql_ks $db,
    string $paymentType,
    int $paymentId,
    array $input,
    int $adminUserId = 0,
    string $ipAddress = ''
): array
{
    $payment = admin_payment_find($db, $paymentType, $paymentId);
    if (!is_array($payment) || empty($payment['id'])) {
        return ['ok' => false, 'message' => 'Payment request not found.'];
    }

    $paymentType = strtolower((string)$payment['payment_type']);
    $status = strtolower(trim((string)($input['status'] ?? (string)($payment['status'] ?? ''))));
    if (!in_array($status, admin_payment_status_options($paymentType, (string)($payment['status'] ?? '')), true)) {
        $status = (string)($payment['status'] ?? '');
    }

    if ($paymentType === 'crypto') {
        $requestedAt = admin_normalize_datetime_input($input['requested_at'] ?? null) ?? (string)($payment['requested_at'] ?? date('Y-m-d H:i:s'));
        $expiresAt = admin_normalize_datetime_input($input['expires_at'] ?? null);
        $requestNote = trim((string)($input['request_note'] ?? ''));

        $updated = $db->update_using_id(
            ['status', 'requested_at', 'expires_at', 'request_note'],
            [$status, $requestedAt, $expiresAt, $requestNote !== '' ? $requestNote : null],
            'crypto_deposit_requests',
            $paymentId
        );

        if ($updated) {
            $description = 'Crypto payment request #' . $paymentId . ' updated';
            if ((string)($payment['status'] ?? '') !== $status) {
                $description .= ': status ' . (string)($payment['status'] ?? 'unknown') . ' -> ' . $status;
            }
            if (!empty($payment['order_id'])) {
                $description .= ' for order #' . (int)$payment['order_id'];
            }

            admin_log_customer_and_admin(
                $db,
                (int)($payment['customer_id'] ?? 0),
                $adminUserId,
                'payment_updated',
                $description . '.',
                $ipAddress
            );
        }

        return [
            'ok' => (bool)$updated,
            'message' => $updated ? 'Payment request saved successfully.' : 'Unable to save payment request.',
        ];
    }

    if ($paymentType === 'crypto_topup') {
        $requestedAt = admin_normalize_datetime_input($input['requested_at'] ?? null) ?? (string)($payment['requested_at'] ?? date('Y-m-d H:i:s'));
        $writeTable = schema_write_target('crypto_topups');
        $updated = $db->update_using_id(
            ['status', 'date'],
            [$status === 'archived' ? 1 : 0, $requestedAt],
            $writeTable,
            $paymentId
        );

        if ($updated) {
            admin_log_customer_and_admin(
                $db,
                (int)($payment['customer_id'] ?? 0),
                $adminUserId,
                'payment_updated',
                'Legacy crypto payment request #' . $paymentId . ' updated: status ' . (string)($payment['status'] ?? 'unknown') . ' -> ' . $status . '.',
                $ipAddress
            );
        }

        return [
            'ok' => (bool)$updated,
            'message' => $updated ? 'Payment request saved successfully.' : 'Unable to save payment request.',
        ];
    }

    $requestedAt = admin_normalize_datetime_input($input['requested_at'] ?? null) ?? (string)($payment['requested_at'] ?? date('Y-m-d H:i:s'));
    $expiresAt = admin_normalize_datetime_input($input['expires_at'] ?? null);
    $submittedAt = admin_normalize_datetime_input($input['submitted_at'] ?? null);
    $approvedAt = admin_normalize_datetime_input($input['approved_at'] ?? null);
    $rejectedAt = admin_normalize_datetime_input($input['rejected_at'] ?? null);
    $paymentReference = trim((string)($input['payment_reference'] ?? ''));
    $payerName = trim((string)($input['payer_name'] ?? ''));
    $payerBankName = trim((string)($input['payer_bank_name'] ?? ''));
    $customerTransferNote = trim((string)($input['customer_transfer_note'] ?? ''));
    $adminReviewNote = trim((string)($input['admin_review_note'] ?? ''));

    $updated = $db->update_using_id(
        ['status', 'requested_at', 'expires_at', 'submitted_at', 'approved_at', 'rejected_at', 'payment_reference', 'payer_name', 'payer_bank_name', 'customer_transfer_note', 'admin_review_note'],
        [
            $status,
            $requestedAt,
            $expiresAt,
            $submittedAt,
            $approvedAt,
            $rejectedAt,
            $paymentReference !== '' ? $paymentReference : null,
            $payerName !== '' ? $payerName : null,
            $payerBankName !== '' ? $payerBankName : null,
            $customerTransferNote !== '' ? $customerTransferNote : null,
            $adminReviewNote !== '' ? $adminReviewNote : null,
        ],
        'bank_transfer_requests',
        $paymentId
    );

    if ($updated) {
        $description = 'Bank payment request #' . $paymentId . ' updated';
        if ((string)($payment['status'] ?? '') !== $status) {
            $description .= ': status ' . (string)($payment['status'] ?? 'unknown') . ' -> ' . $status;
        }
        if (!empty($payment['order_id'])) {
            $description .= ' for order #' . (int)$payment['order_id'];
        }

        admin_log_customer_and_admin(
            $db,
            (int)($payment['customer_id'] ?? 0),
            $adminUserId,
            'payment_updated',
            $description . '.',
            $ipAddress
        );
    }

    return [
        'ok' => (bool)$updated,
        'message' => $updated ? 'Payment request saved successfully.' : 'Unable to save payment request.',
    ];
}

function admin_delete_payment(
    Mysql_ks $db,
    string $paymentType,
    int $paymentId,
    int $adminUserId = 0,
    string $ipAddress = ''
): array
{
    $payment = admin_payment_find($db, $paymentType, $paymentId);
    if (!is_array($payment) || empty($payment['id'])) {
        return ['ok' => false, 'message' => 'Payment request not found.'];
    }

    $paymentType = strtolower((string)$payment['payment_type']);
    if ($paymentType === 'crypto_topup') {
        $tableName = schema_write_target('crypto_topups');
    } else {
        $tableName = $paymentType === 'crypto' ? 'crypto_deposit_requests' : 'bank_transfer_requests';
    }

    $deleted = $db->delete_using_id($tableName, $paymentId);

    if ($deleted) {
        $description = ucfirst(str_replace('_', ' ', $paymentType)) . ' payment request #' . $paymentId . ' deleted';
        if (!empty($payment['order_id'])) {
            $description .= ' for order #' . (int)$payment['order_id'];
        }

        admin_log_customer_and_admin(
            $db,
            (int)($payment['customer_id'] ?? 0),
            $adminUserId,
            'payment_deleted',
            $description . '.',
            $ipAddress
        );
    }

    return [
        'ok' => (bool)$deleted,
        'message' => $deleted ? 'Payment request deleted successfully.' : 'Unable to delete payment request.',
    ];
}

function admin_payment_asset_logo_url(string $assetCode, string $fallbackPath = ''): string
{
    $assetCode = strtolower(trim($assetCode));
    $fallbackPath = trim($fallbackPath);

    if ($assetCode !== '') {
        $builtIn = admin_crypto_asset_logo_url($assetCode);
        if ($builtIn !== '/img/crypto/blockchain.png') {
            return $builtIn;
        }
    }

    if ($fallbackPath !== '') {
        if (strpos($fallbackPath, 'http://') === 0 || strpos($fallbackPath, 'https://') === 0 || strpos($fallbackPath, '/') === 0) {
            return $fallbackPath;
        }
        return '/' . ltrim($fallbackPath, '/');
    }

    return '/img/crypto/blockchain.png';
}

function admin_optional_media_url(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, '/') === 0) {
        return $path;
    }

    return '/' . ltrim($path, '/');
}

function admin_payment_apply_quick_action(
    Mysql_ks $db,
    string $paymentType,
    int $paymentId,
    string $action,
    int $adminUserId = 0,
    string $ipAddress = ''
): array
{
    $paymentType = strtolower(trim($paymentType));
    $action = strtolower(trim($action));

    if (!in_array($paymentType, ['crypto', 'crypto_topup', 'bank'], true) || $paymentId <= 0) {
        return ['ok' => false, 'message' => 'Payment request not found.'];
    }

    if ($action === 'delete') {
        return admin_delete_payment($db, $paymentType, $paymentId, $adminUserId, $ipAddress);
    }

    $payment = admin_payment_find($db, $paymentType, $paymentId);
    if (!is_array($payment) || empty($payment['id'])) {
        return ['ok' => false, 'message' => 'Payment request not found.'];
    }

    if ($action === 'review') {
        return admin_save_payment($db, $paymentType, $paymentId, [
            'status' => admin_payment_review_status($paymentType),
        ], $adminUserId, $ipAddress);
    }

    if ($action === 'archive') {
        return admin_save_payment($db, $paymentType, $paymentId, [
            'status' => 'archived',
        ], $adminUserId, $ipAddress);
    }

    if ($action === 'reopen') {
        return admin_save_payment($db, $paymentType, $paymentId, [
            'status' => admin_payment_default_status($paymentType),
        ], $adminUserId, $ipAddress);
    }

    return ['ok' => false, 'message' => 'Payment action is invalid.'];
}

function admin_payment_type_label(string $paymentType, array $messages): string
{
    $paymentType = strtolower(trim($paymentType));
    if ($paymentType === 'crypto') {
        return admin_t($messages, 'payment_type_crypto', 'Crypto');
    }

    if ($paymentType === 'crypto_topup') {
        return admin_t($messages, 'payment_type_crypto', 'Crypto');
    }

    if ($paymentType === 'bank') {
        return admin_t($messages, 'payment_type_bank', 'Bank transfer');
    }

    return $paymentType !== '' ? ucfirst(str_replace('_', ' ', $paymentType)) : admin_t($messages, 'col_type', 'Type');
}

function admin_payment_type_badge_class(string $paymentType): string
{
    $paymentType = strtolower(trim($paymentType));
    if ($paymentType === 'crypto' || $paymentType === 'crypto_topup') {
        return 'admin-status-pill--assigned';
    }

    if ($paymentType === 'bank') {
        return 'admin-status-pill--available';
    }

    return 'admin-status-pill--muted';
}

function admin_payment_status_badge_class(string $status): string
{
    $status = strtolower(trim($status));

    if ($status === 'pending' || $status === 'pending_payment') {
        return 'admin-status-pill--warning';
    }

    if ($status === 'awaiting_confirmation' || $status === 'awaiting_review') {
        return 'admin-status-pill--assigned';
    }

    if ($status === 'archived') {
        return 'admin-status-pill--muted';
    }

    if (in_array($status, ['cancelled', 'rejected', 'failed'], true)) {
        return 'admin-status-pill--danger';
    }

    return 'admin-status-pill--available';
}

function admin_compact_wallet_address(string $value, int $prefixLength = 4, int $suffixLength = 5): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $minimumVisibleLength = $prefixLength + $suffixLength + 3;
    if (strlen($value) <= $minimumVisibleLength) {
        return $value;
    }

    return substr($value, 0, $prefixLength) . '...' . substr($value, -$suffixLength);
}

function admin_payment_progress_state(string $status): array
{
    $status = strtolower(trim($status));

    if ($status === 'archived') {
        return [
            'show_bar' => false,
            'tone' => 'neutral',
            'percent' => 0,
            'status_label_key' => 'payment_stage_archived',
            'status_fallback' => 'Archived',
        ];
    }

    if ($status === 'awaiting_confirmation' || $status === 'awaiting_review') {
        return [
            'show_bar' => true,
            'tone' => 'warning',
            'percent' => 56,
            'status_label_key' => 'payment_stage_review',
            'status_fallback' => 'Verification',
        ];
    }

    if (in_array($status, ['cancelled', 'rejected', 'failed'], true)) {
        return [
            'show_bar' => false,
            'tone' => 'danger',
            'percent' => 0,
            'status_label_key' => 'enum_cancelled',
            'status_fallback' => 'Cancelled',
        ];
    }

    if (in_array($status, ['success', 'confirmed', 'approved', 'paid', 'completed'], true)) {
        return [
            'show_bar' => true,
            'tone' => 'success',
            'percent' => 100,
            'status_label_key' => 'payment_stage_success',
            'status_fallback' => 'Success',
        ];
    }

    return [
        'show_bar' => true,
        'tone' => 'neutral',
        'percent' => 16,
        'status_label_key' => 'payment_stage_new',
        'status_fallback' => 'Pending',
    ];
}

function admin_product_count(Mysql_ks $db): int
{
    if (!schema_object_exists($db, 'products')) {
        return 0;
    }

    $row = $db->select_user("SELECT COUNT(*) AS total FROM products");
    return (int)($row['total'] ?? 0);
}

function admin_product_rows_paginated(Mysql_ks $db, int $limit = 20, int $offset = 0): array
{
    if (!schema_object_exists($db, 'products')) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);

    return $db->select_full_user(
        "SELECT
            products.id,
            products.name,
            products.slug,
            products.duration_hours,
            products.price_amount,
            products.description,
            products.product_type,
            products.provisioning_mode,
            products.is_trial,
            products.is_active,
            products.updated_at,
            product_providers.name AS provider_name,
            product_providers.logo_url AS provider_logo_url,
            currencies.code AS currency_code
         FROM products
         LEFT JOIN product_providers ON product_providers.id = products.provider_id
         LEFT JOIN currencies ON currencies.id = products.currency_id
         ORDER BY product_providers.name ASC, products.duration_hours ASC, products.id ASC
         LIMIT {$offset}, {$limit}"
    );
}

function admin_active_currency_rows(Mysql_ks $db): array
{
    if (!schema_object_exists($db, 'currencies')) {
        return [];
    }

    return $db->select_full_user(
        "SELECT id, code, name, symbol
         FROM currencies
         WHERE is_active = 1
         ORDER BY name ASC, id ASC"
    );
}

function admin_product_provider_rows(Mysql_ks $db): array
{
    if (!schema_object_exists($db, 'product_providers')) {
        return [];
    }

    return $db->select_full_user(
        "SELECT id, name
         FROM product_providers
         WHERE is_active = 1
         ORDER BY name ASC"
    );
}

function admin_product_provider_count(Mysql_ks $db): int
{
    if (!schema_object_exists($db, 'product_providers')) {
        return 0;
    }

    $row = $db->select_user("SELECT COUNT(*) AS total FROM product_providers");
    return (int)($row['total'] ?? 0);
}

function admin_product_provider_rows_paginated(Mysql_ks $db, int $limit = 20, int $offset = 0): array
{
    if (!schema_object_exists($db, 'product_providers')) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $providerUrlReplacementFromValue = schema_column_exists($db, 'product_providers', 'url_replacement_from')
        ? 'product_providers.url_replacement_from'
        : 'NULL';
    $providerUrlReplacementToValue = schema_column_exists($db, 'product_providers', 'url_replacement_to')
        ? 'product_providers.url_replacement_to'
        : 'NULL';

    if (!schema_object_exists($db, 'products')) {
        return $db->select_full_user(
            "SELECT
                product_providers.id,
                product_providers.name,
                product_providers.slug,
                product_providers.description,
                product_providers.dashboard_url,
                product_providers.logo_url,
                product_providers.supports_manual_delivery,
                product_providers.supports_url_replacement,
                {$providerUrlReplacementFromValue} AS url_replacement_from,
                {$providerUrlReplacementToValue} AS url_replacement_to,
                product_providers.is_active,
                product_providers.created_at,
                product_providers.updated_at,
                0 AS product_count,
                0 AS active_product_count
             FROM product_providers
             ORDER BY product_providers.name ASC, product_providers.id ASC
             LIMIT {$offset}, {$limit}"
        );
    }

    return $db->select_full_user(
        "SELECT
            product_providers.id,
            product_providers.name,
            product_providers.slug,
            product_providers.description,
            product_providers.dashboard_url,
            product_providers.logo_url,
            product_providers.supports_manual_delivery,
            product_providers.supports_url_replacement,
            MAX({$providerUrlReplacementFromValue}) AS url_replacement_from,
            MAX({$providerUrlReplacementToValue}) AS url_replacement_to,
            product_providers.is_active,
            product_providers.created_at,
            product_providers.updated_at,
            COUNT(products.id) AS product_count,
            SUM(CASE WHEN products.is_active = 1 THEN 1 ELSE 0 END) AS active_product_count
         FROM product_providers
         LEFT JOIN products ON products.provider_id = product_providers.id
         GROUP BY
            product_providers.id,
            product_providers.name,
            product_providers.slug,
            product_providers.description,
            product_providers.dashboard_url,
            product_providers.logo_url,
            product_providers.supports_manual_delivery,
            product_providers.supports_url_replacement,
            product_providers.is_active,
            product_providers.created_at,
            product_providers.updated_at
         ORDER BY product_providers.name ASC, product_providers.id ASC
         LIMIT {$offset}, {$limit}"
    );
}

function admin_product_provider_find(Mysql_ks $db, int $providerId): ?array
{
    if ($providerId <= 0 || !schema_object_exists($db, 'product_providers')) {
        return null;
    }

    $providerUrlReplacementFromValue = schema_column_exists($db, 'product_providers', 'url_replacement_from')
        ? 'product_providers.url_replacement_from'
        : 'NULL';
    $providerUrlReplacementToValue = schema_column_exists($db, 'product_providers', 'url_replacement_to')
        ? 'product_providers.url_replacement_to'
        : 'NULL';

    $row = $db->select_user(
        "SELECT
            product_providers.id,
            product_providers.name,
            product_providers.slug,
            product_providers.description,
            product_providers.dashboard_url,
            product_providers.logo_url,
            product_providers.supports_manual_delivery,
            product_providers.supports_url_replacement,
            MAX({$providerUrlReplacementFromValue}) AS url_replacement_from,
            MAX({$providerUrlReplacementToValue}) AS url_replacement_to,
            product_providers.is_active,
            product_providers.created_at,
            product_providers.updated_at,
            COUNT(products.id) AS product_count,
            SUM(CASE WHEN products.is_active = 1 THEN 1 ELSE 0 END) AS active_product_count
         FROM product_providers
         LEFT JOIN products ON products.provider_id = product_providers.id
         WHERE product_providers.id = {$providerId}
         GROUP BY
            product_providers.id,
            product_providers.name,
            product_providers.slug,
            product_providers.description,
            product_providers.dashboard_url,
            product_providers.logo_url,
            product_providers.supports_manual_delivery,
            product_providers.supports_url_replacement,
            product_providers.is_active,
            product_providers.created_at,
            product_providers.updated_at
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_product_provider_unique_slug(Mysql_ks $db, string $name, string $requestedSlug = '', int $excludeId = 0): string
{
    $baseSlug = admin_product_slugify($requestedSlug !== '' ? $requestedSlug : $name);
    $candidate = $baseSlug;
    $suffix = 2;

    while (true) {
        $safeSlug = $db->escape($candidate);
        $excludeSql = $excludeId > 0 ? " AND id != {$excludeId}" : '';
        $row = $db->select_user(
            "SELECT id
             FROM product_providers
             WHERE slug = '{$safeSlug}'{$excludeSql}
             LIMIT 1"
        );

        if (!is_array($row) || empty($row['id'])) {
            return $candidate;
        }

        $candidate = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function admin_create_product_provider(Mysql_ks $db, array $input): array
{
    if (!schema_object_exists($db, 'product_providers')) {
        return ['ok' => false, 'message' => 'Provider storage is not available.'];
    }

    $name = trim((string)($input['name'] ?? ''));
    $slugInput = trim((string)($input['slug'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $dashboardUrl = trim((string)($input['dashboard_url'] ?? ''));
    $urlReplacementFrom = trim((string)($input['url_replacement_from'] ?? ''));
    $urlReplacementTo = trim((string)($input['url_replacement_to'] ?? ''));
    $supportsManualDelivery = isset($input['supports_manual_delivery']) && (string)$input['supports_manual_delivery'] === '1' ? 1 : 0;
    $supportsUrlReplacement = isset($input['supports_url_replacement']) && (string)$input['supports_url_replacement'] === '1' ? 1 : 0;
    $isActive = isset($input['is_active']) && (string)$input['is_active'] === '1' ? 1 : 0;

    if ($name === '') {
        return ['ok' => false, 'message' => 'Provider name is required.'];
    }

    if ($dashboardUrl !== '' && filter_var($dashboardUrl, FILTER_VALIDATE_URL) === false) {
        return ['ok' => false, 'message' => 'Dashboard URL must be a valid link.'];
    }

    if (($urlReplacementFrom !== '' || $urlReplacementTo !== '') && ($urlReplacementFrom === '' || $urlReplacementTo === '')) {
        return ['ok' => false, 'message' => 'Both URL replacement values are required.'];
    }

    if ($urlReplacementFrom !== '' && filter_var($urlReplacementFrom, FILTER_VALIDATE_URL) === false) {
        return ['ok' => false, 'message' => 'Replacement source URL must be a valid link.'];
    }

    if ($urlReplacementTo !== '' && filter_var($urlReplacementTo, FILTER_VALIDATE_URL) === false) {
        return ['ok' => false, 'message' => 'Replacement target URL must be a valid link.'];
    }

    $slug = admin_product_provider_unique_slug($db, $name, $slugInput);
    $insertFields = ['name', 'slug', 'description', 'dashboard_url', 'supports_manual_delivery', 'supports_url_replacement'];
    $insertValues = [$name, $slug, $description !== '' ? $description : null, $dashboardUrl !== '' ? $dashboardUrl : null, $supportsManualDelivery, $supportsUrlReplacement];

    if (schema_column_exists($db, 'product_providers', 'url_replacement_from')) {
        $insertFields[] = 'url_replacement_from';
        $insertValues[] = $urlReplacementFrom !== '' ? $urlReplacementFrom : null;
    }

    if (schema_column_exists($db, 'product_providers', 'url_replacement_to')) {
        $insertFields[] = 'url_replacement_to';
        $insertValues[] = $urlReplacementTo !== '' ? $urlReplacementTo : null;
    }

    $insertFields[] = 'is_active';
    $insertValues[] = $isActive;

    $inserted = $db->insert($insertFields, $insertValues, 'product_providers');

    if (!$inserted) {
        return ['ok' => false, 'message' => 'Unable to create provider.'];
    }

    return [
        'ok' => true,
        'message' => 'Provider created successfully.',
        'provider_id' => (int)$db->id(),
    ];
}

function admin_save_product_provider(Mysql_ks $db, int $providerId, array $input): array
{
    $provider = admin_product_provider_find($db, $providerId);
    if (!is_array($provider) || empty($provider['id'])) {
        return ['ok' => false, 'message' => 'Provider not found.'];
    }

    $name = trim((string)($input['name'] ?? ''));
    $slugInput = trim((string)($input['slug'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $dashboardUrl = trim((string)($input['dashboard_url'] ?? ''));
    $urlReplacementFrom = trim((string)($input['url_replacement_from'] ?? ''));
    $urlReplacementTo = trim((string)($input['url_replacement_to'] ?? ''));
    $supportsManualDelivery = isset($input['supports_manual_delivery']) && (string)$input['supports_manual_delivery'] === '1' ? 1 : 0;
    $supportsUrlReplacement = isset($input['supports_url_replacement']) && (string)$input['supports_url_replacement'] === '1' ? 1 : 0;
    $isActive = isset($input['is_active']) && (string)$input['is_active'] === '1' ? 1 : 0;

    if ($name === '') {
        return ['ok' => false, 'message' => 'Provider name is required.'];
    }

    if ($dashboardUrl !== '' && filter_var($dashboardUrl, FILTER_VALIDATE_URL) === false) {
        return ['ok' => false, 'message' => 'Dashboard URL must be a valid link.'];
    }

    if (($urlReplacementFrom !== '' || $urlReplacementTo !== '') && ($urlReplacementFrom === '' || $urlReplacementTo === '')) {
        return ['ok' => false, 'message' => 'Both URL replacement values are required.'];
    }

    if ($urlReplacementFrom !== '' && filter_var($urlReplacementFrom, FILTER_VALIDATE_URL) === false) {
        return ['ok' => false, 'message' => 'Replacement source URL must be a valid link.'];
    }

    if ($urlReplacementTo !== '' && filter_var($urlReplacementTo, FILTER_VALIDATE_URL) === false) {
        return ['ok' => false, 'message' => 'Replacement target URL must be a valid link.'];
    }

    $slug = admin_product_provider_unique_slug($db, $name, $slugInput, $providerId);
    $updateFields = ['name', 'slug', 'description', 'dashboard_url', 'supports_manual_delivery', 'supports_url_replacement'];
    $updateValues = [$name, $slug, $description !== '' ? $description : null, $dashboardUrl !== '' ? $dashboardUrl : null, $supportsManualDelivery, $supportsUrlReplacement];

    if (schema_column_exists($db, 'product_providers', 'url_replacement_from')) {
        $updateFields[] = 'url_replacement_from';
        $updateValues[] = $urlReplacementFrom !== '' ? $urlReplacementFrom : null;
    }

    if (schema_column_exists($db, 'product_providers', 'url_replacement_to')) {
        $updateFields[] = 'url_replacement_to';
        $updateValues[] = $urlReplacementTo !== '' ? $urlReplacementTo : null;
    }

    $updateFields[] = 'is_active';
    $updateValues[] = $isActive;

    $updated = $db->update_using_id($updateFields, $updateValues, 'product_providers', $providerId);

    return [
        'ok' => (bool)$updated,
        'message' => $updated ? 'Provider saved successfully.' : 'Unable to save provider.',
    ];
}

function admin_delete_product_provider(Mysql_ks $db, int $providerId): array
{
    $provider = admin_product_provider_find($db, $providerId);
    if (!is_array($provider) || empty($provider['id'])) {
        return ['ok' => false, 'message' => 'Provider not found.'];
    }

    $productCount = (int)($provider['product_count'] ?? 0);
    if ($productCount > 0) {
        return [
            'ok' => false,
            'message' => 'This provider still has attached products. Reassign, archive or remove those products first.',
        ];
    }

    $deleted = $db->delete_using_id('product_providers', $providerId);
    return [
        'ok' => (bool)$deleted,
        'message' => $deleted ? 'Provider deleted successfully.' : 'Unable to delete provider.',
    ];
}

function admin_product_active_rows(Mysql_ks $db): array
{
    if (!schema_object_exists($db, 'products')) {
        return [];
    }

    return $db->select_full_user(
        "SELECT
            products.id,
            products.provider_id,
            products.name,
            products.duration_hours,
            products.price_amount,
            products.is_active,
            product_providers.name AS provider_name,
            currencies.code AS currency_code
         FROM products
         LEFT JOIN product_providers ON product_providers.id = products.provider_id
         LEFT JOIN currencies ON currencies.id = products.currency_id
         ORDER BY product_providers.name ASC, products.duration_hours ASC, products.id ASC"
    );
}

function admin_chat_payment_product_presets(Mysql_ks $db, array $currencyContext): array
{
    if (!schema_object_exists($db, 'products')) {
        return [];
    }

    $currencyId = (int)($currencyContext['currency_id'] ?? 0);
    $safeCurrencyFilter = $currencyId > 0 ? " AND products.currency_id = {$currencyId}" : '';
    $query = "
        SELECT
            products.id,
            products.name,
            products.duration_hours,
            products.price_amount,
            products.is_trial,
            product_providers.name AS provider_name,
            currencies.code AS currency_code,
            currencies.symbol AS currency_symbol
         FROM products
         LEFT JOIN product_providers ON product_providers.id = products.provider_id
         LEFT JOIN currencies ON currencies.id = products.currency_id
         WHERE products.is_active = 1
           AND products.price_amount > 0
           AND products.is_trial = 0
           %s
         ORDER BY products.price_amount ASC, products.duration_hours ASC, products.id ASC";
    $rows = $db->select_full_user(sprintf($query, $safeCurrencyFilter));
    if (!$rows) {
        $rows = $db->select_full_user(sprintf($query, ''));
    }

    $presets = [];
    foreach ($rows as $row) {
        $amount = number_format((float)($row['price_amount'] ?? 0), 2, '.', '');
        $label = admin_format_money_value_with_symbol(
            $row['price_amount'] ?? 0,
            (string)($row['currency_code'] ?? $currencyContext['currency_code'] ?? ''),
            (string)($row['currency_symbol'] ?? $currencyContext['currency_symbol'] ?? '')
        ) . ' - ' . trim((string)($row['name'] ?? 'Product'));
        $providerName = trim((string)($row['provider_name'] ?? ''));
        if ($providerName !== '') {
            $label .= ' (' . $providerName . ')';
        }
        $presets[] = [
            'product_id' => (int)($row['id'] ?? 0),
            'amount' => $amount,
            'label' => $label,
        ];
    }

    return $presets;
}

function admin_product_find(Mysql_ks $db, int $productId): ?array
{
    if ($productId <= 0 || !schema_object_exists($db, 'products')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT
            products.id,
            products.provider_id,
            products.name,
            products.slug,
            products.description,
            products.duration_hours,
            products.price_amount,
            products.currency_id,
            products.product_type,
            products.provisioning_mode,
            products.is_trial,
            products.is_active,
            products.created_at,
            products.updated_at,
            product_providers.name AS provider_name,
            currencies.code AS currency_code
         FROM products
         LEFT JOIN product_providers ON product_providers.id = products.provider_id
         LEFT JOIN currencies ON currencies.id = products.currency_id
         WHERE products.id = {$productId}
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_product_type_options(string $current = ''): array
{
    $options = ['subscription', 'credits'];
    $current = admin_normalize_product_type($current);
    if ($current !== '' && !in_array($current, $options, true)) {
        $options[] = $current;
    }

    return $options;
}

function admin_product_type_form_options(array $settings, string $current = ''): array
{
    $current = admin_normalize_product_type($current);
    if ($current === 'credits') {
        return ['credits'];
    }

    return admin_credits_sales_enabled($settings) ? ['subscription', 'credits'] : ['subscription'];
}

function admin_product_provisioning_mode_options(string $current = ''): array
{
    $options = ['manual'];
    $current = trim($current);
    if ($current !== '' && !in_array($current, $options, true)) {
        $options[] = $current;
    }

    return $options;
}

function admin_product_slugify(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'product';
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
    $value = trim($value, '-');

    return $value !== '' ? $value : 'product';
}

function admin_product_unique_slug(Mysql_ks $db, string $name, string $requestedSlug = '', int $excludeId = 0): string
{
    $baseSlug = admin_product_slugify($requestedSlug !== '' ? $requestedSlug : $name);
    $candidate = $baseSlug;
    $suffix = 2;

    while (true) {
        $safeSlug = $db->escape($candidate);
        $excludeSql = $excludeId > 0 ? " AND id != {$excludeId}" : '';
        $row = $db->select_user(
            "SELECT id
             FROM products
             WHERE slug = '{$safeSlug}'{$excludeSql}
             LIMIT 1"
        );

        if (!is_array($row) || empty($row['id'])) {
            return $candidate;
        }

        $candidate = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function admin_create_product(Mysql_ks $db, array $input): array
{
    if (!schema_object_exists($db, 'products')) {
        return ['ok' => false, 'message' => 'Product storage is not available.'];
    }

    $providerId = (int)($input['provider_id'] ?? 0);
    $name = trim((string)($input['name'] ?? ''));
    $slugInput = trim((string)($input['slug'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $durationHoursRaw = trim((string)($input['duration_hours'] ?? '0'));
    $priceAmountRaw = trim((string)($input['price_amount'] ?? '0'));
    $currencyId = admin_default_currency_id($db);
    $productType = admin_normalize_product_type($input['product_type'] ?? 'subscription');
    $provisioningMode = trim((string)($input['provisioning_mode'] ?? 'manual'));
    $isTrial = isset($input['is_trial']) && (string)$input['is_trial'] === '1' ? 1 : 0;
    $isActive = isset($input['is_active']) && (string)$input['is_active'] === '1' ? 1 : 0;
    $creditsSalesEnabled = admin_credits_sales_enabled(admin_app_settings($db));

    $provider = $providerId > 0 ? $db->select_user("SELECT id FROM product_providers WHERE id = {$providerId} LIMIT 1") : null;
    $currency = $currencyId > 0 ? $db->select_user("SELECT id FROM currencies WHERE id = {$currencyId} LIMIT 1") : null;

    if (!is_array($provider) || empty($provider['id'])) {
        return ['ok' => false, 'message' => 'Product provider is required.'];
    }

    if ($name === '') {
        return ['ok' => false, 'message' => 'Product name is required.'];
    }

    if ($productType === 'credits' && !$creditsSalesEnabled) {
        return ['ok' => false, 'message' => 'Enable credits sales in Settings before creating credits products.'];
    }

    if ($productType !== 'credits' && ($durationHoursRaw === '' || !ctype_digit($durationHoursRaw))) {
        return ['ok' => false, 'message' => 'Duration must be a whole number of hours.'];
    }

    if ($priceAmountRaw === '' || !is_numeric($priceAmountRaw)) {
        return ['ok' => false, 'message' => 'Price must be a valid number.'];
    }

    if (!is_array($currency) || empty($currency['id'])) {
        return ['ok' => false, 'message' => 'Currency is required.'];
    }

    if (!in_array($provisioningMode, admin_product_provisioning_mode_options(), true)) {
        $provisioningMode = 'manual';
    }

    $slug = admin_product_unique_slug($db, $name, $slugInput);
    $durationHours = $productType === 'credits' ? 0 : (int)$durationHoursRaw;
    if ($productType === 'credits') {
        $isTrial = 0;
    }
    $priceAmount = number_format((float)$priceAmountRaw, 2, '.', '');

    $inserted = $db->insert(
        ['provider_id', 'name', 'slug', 'description', 'duration_hours', 'price_amount', 'currency_id', 'product_type', 'provisioning_mode', 'is_trial', 'is_active', 'source_system'],
        [$providerId, $name, $slug, $description !== '' ? $description : null, $durationHours, $priceAmount, $currencyId, $productType, $provisioningMode, $isTrial, $isActive, 'native'],
        'products'
    );

    if (!$inserted) {
        return ['ok' => false, 'message' => 'Unable to create product.'];
    }

    return [
        'ok' => true,
        'message' => 'Product created successfully.',
        'product_id' => (int)$db->id(),
    ];
}

function admin_save_product(Mysql_ks $db, int $productId, array $input): array
{
    $product = admin_product_find($db, $productId);
    if (!is_array($product) || empty($product['id'])) {
        return ['ok' => false, 'message' => 'Product not found.'];
    }

    $providerId = (int)($input['provider_id'] ?? 0);
    $name = trim((string)($input['name'] ?? ''));
    $slugInput = trim((string)($input['slug'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $durationHoursRaw = trim((string)($input['duration_hours'] ?? '0'));
    $priceAmountRaw = trim((string)($input['price_amount'] ?? '0'));
    $currencyId = admin_default_currency_id($db);
    $productType = admin_normalize_product_type($input['product_type'] ?? 'subscription');
    $provisioningMode = trim((string)($input['provisioning_mode'] ?? 'manual'));
    $isTrial = isset($input['is_trial']) && (string)$input['is_trial'] === '1' ? 1 : 0;
    $isActive = isset($input['is_active']) && (string)$input['is_active'] === '1' ? 1 : 0;
    $creditsSalesEnabled = admin_credits_sales_enabled(admin_app_settings($db));
    $currentProductType = admin_normalize_product_type((string)($product['product_type'] ?? 'subscription'));

    $provider = $providerId > 0 ? $db->select_user("SELECT id FROM product_providers WHERE id = {$providerId} LIMIT 1") : null;
    $currency = $currencyId > 0 ? $db->select_user("SELECT id FROM currencies WHERE id = {$currencyId} LIMIT 1") : null;

    if (!is_array($provider) || empty($provider['id'])) {
        return ['ok' => false, 'message' => 'Product provider is required.'];
    }

    if ($name === '') {
        return ['ok' => false, 'message' => 'Product name is required.'];
    }

    if ($productType === 'credits' && !$creditsSalesEnabled && $currentProductType !== 'credits') {
        return ['ok' => false, 'message' => 'Enable credits sales in Settings before switching products to credits.'];
    }

    if ($productType !== 'credits' && ($durationHoursRaw === '' || !ctype_digit($durationHoursRaw))) {
        return ['ok' => false, 'message' => 'Duration must be a whole number of hours.'];
    }

    if ($priceAmountRaw === '' || !is_numeric($priceAmountRaw)) {
        return ['ok' => false, 'message' => 'Price must be a valid number.'];
    }

    if (!is_array($currency) || empty($currency['id'])) {
        return ['ok' => false, 'message' => 'Currency is required.'];
    }

    if (!in_array($provisioningMode, admin_product_provisioning_mode_options((string)($product['provisioning_mode'] ?? '')), true)) {
        $provisioningMode = 'manual';
    }

    $slug = admin_product_unique_slug($db, $name, $slugInput, $productId);
    $durationHours = $productType === 'credits' ? 0 : (int)$durationHoursRaw;
    if ($productType === 'credits') {
        $isTrial = 0;
    }
    $priceAmount = number_format((float)$priceAmountRaw, 2, '.', '');

    $updated = $db->update_using_id(
        ['provider_id', 'name', 'slug', 'description', 'duration_hours', 'price_amount', 'currency_id', 'product_type', 'provisioning_mode', 'is_trial', 'is_active'],
        [$providerId, $name, $slug, $description !== '' ? $description : null, $durationHours, $priceAmount, $currencyId, $productType, $provisioningMode, $isTrial, $isActive],
        'products',
        $productId
    );

    return [
        'ok' => (bool)$updated,
        'message' => $updated ? 'Product saved successfully.' : 'Unable to save product.',
    ];
}

function admin_product_delete_summary(Mysql_ks $db, int $productId): array
{
    $summary = [
        'orders_total' => 0,
        'can_delete' => false,
    ];

    if ($productId <= 0 || !schema_object_exists($db, 'products')) {
        return $summary;
    }

    if (schema_object_exists($db, 'orders')) {
        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM orders
             WHERE product_id = {$productId}"
        );
        $summary['orders_total'] = (int)($row['total'] ?? 0);
    }

    $summary['can_delete'] = $summary['orders_total'] === 0;
    return $summary;
}

function admin_delete_product(Mysql_ks $db, int $productId): array
{
    $product = admin_product_find($db, $productId);
    if (!is_array($product) || empty($product['id'])) {
        return ['ok' => false, 'message' => 'Product not found.'];
    }

    $deleteSummary = admin_product_delete_summary($db, $productId);
    if ((int)($deleteSummary['orders_total'] ?? 0) > 0) {
        return [
            'ok' => false,
            'message' => 'This product already has orders. Delete the orders first or archive the product instead.',
        ];
    }

    $deleted = $db->delete_using_id('products', $productId);
    return [
        'ok' => (bool)$deleted,
        'message' => $deleted ? 'Product deleted successfully.' : 'Unable to delete product.',
    ];
}

function admin_bank_account_count(Mysql_ks $db): int
{
    if (!schema_object_exists($db, 'bank_accounts')) {
        return 0;
    }

    $row = $db->select_user("SELECT COUNT(*) AS total FROM bank_accounts");
    return (int)($row['total'] ?? 0);
}

function admin_bank_account_rows(Mysql_ks $db, int $limit = 20, int $offset = 0): array
{
    if (!schema_object_exists($db, 'bank_accounts')) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $offset = max(0, $offset);
    return $db->select_full_user(
        "SELECT
            bank_accounts.id,
            bank_accounts.currency_id,
            bank_accounts.label,
            bank_accounts.account_holder_name,
            bank_accounts.bank_name,
            bank_accounts.bank_address,
            bank_accounts.country_code,
            bank_accounts.iban,
            bank_accounts.account_number,
            bank_accounts.routing_number,
            bank_accounts.swift_bic,
            bank_accounts.payment_reference_template,
            bank_accounts.transfer_instructions,
            bank_accounts.status,
            bank_accounts.notes,
            currencies.code AS currency_code,
            (
                SELECT customer_bank_accounts.customer_email
                FROM customer_bank_accounts
                WHERE customer_bank_accounts.bank_account_id = bank_accounts.id
                  AND customer_bank_accounts.status IN ('reserved', 'active')
                ORDER BY customer_bank_accounts.assigned_at DESC, customer_bank_accounts.bank_account_assignment_id DESC
                LIMIT 1
            ) AS assigned_customer_email,
            (
                SELECT customer_bank_accounts.customer_id
                FROM customer_bank_accounts
                WHERE customer_bank_accounts.bank_account_id = bank_accounts.id
                  AND customer_bank_accounts.status IN ('reserved', 'active')
                ORDER BY customer_bank_accounts.assigned_at DESC, customer_bank_accounts.bank_account_assignment_id DESC
                LIMIT 1
            ) AS assigned_customer_id,
            (
                SELECT COUNT(*)
                FROM bank_account_assignments
                WHERE bank_account_assignments.bank_account_id = bank_accounts.id
                  AND bank_account_assignments.status IN ('reserved', 'active')
            ) AS active_assignment_count,
            bank_accounts.updated_at
         FROM bank_accounts
         LEFT JOIN currencies ON currencies.id = bank_accounts.currency_id
         ORDER BY bank_accounts.id DESC
         LIMIT {$offset}, {$limit}"
    );
}

function admin_crypto_wallet_count(Mysql_ks $db): int
{
    if (!schema_object_exists($db, 'crypto_wallet_addresses')) {
        return 0;
    }

    $row = $db->select_user("SELECT COUNT(*) AS total FROM crypto_wallet_addresses");
    return (int)($row['total'] ?? 0);
}

function admin_crypto_wallet_free_count(Mysql_ks $db): int
{
    if (!schema_object_exists($db, 'crypto_wallet_addresses')) {
        return 0;
    }

    $row = $db->select_user(
        "SELECT COUNT(*) AS total
         FROM crypto_wallet_addresses
         LEFT JOIN crypto_wallet_assignments
           ON crypto_wallet_assignments.wallet_address_id = crypto_wallet_addresses.id
          AND crypto_wallet_assignments.status IN ('reserved', 'active')
         WHERE crypto_wallet_addresses.status = 'available'
           AND crypto_wallet_addresses.disabled_at IS NULL
           AND crypto_wallet_assignments.id IS NULL"
    );

    return (int)($row['total'] ?? 0);
}

function admin_crypto_wallet_rows(Mysql_ks $db, int $limit = 20, int $offset = 0): array
{
    if (!schema_object_exists($db, 'crypto_wallet_addresses')) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $offset = max(0, $offset);
    return $db->select_full_user(
        "SELECT
            crypto_wallet_addresses.id,
            crypto_wallet_addresses.crypto_asset_id,
            crypto_wallet_addresses.label,
            crypto_wallet_addresses.owner_full_name,
            crypto_wallet_addresses.address,
            crypto_wallet_addresses.network_code,
            crypto_wallet_addresses.memo_tag,
            crypto_wallet_addresses.wallet_provider,
            crypto_wallet_addresses.status,
            crypto_wallet_addresses.notes,
            crypto_assets.code AS asset_code,
            crypto_assets.name AS asset_name,
            crypto_assets.logo_url AS asset_logo_url,
            (
                SELECT customer_crypto_wallets.customer_email
                FROM customer_crypto_wallets
                WHERE customer_crypto_wallets.wallet_address_id = crypto_wallet_addresses.id
                  AND customer_crypto_wallets.status IN ('reserved', 'active')
                ORDER BY customer_crypto_wallets.assigned_at DESC, customer_crypto_wallets.wallet_assignment_id DESC
                LIMIT 1
            ) AS assigned_customer_email,
            (
                SELECT customer_crypto_wallets.customer_id
                FROM customer_crypto_wallets
                WHERE customer_crypto_wallets.wallet_address_id = crypto_wallet_addresses.id
                  AND customer_crypto_wallets.status IN ('reserved', 'active')
                ORDER BY customer_crypto_wallets.assigned_at DESC, customer_crypto_wallets.wallet_assignment_id DESC
                LIMIT 1
            ) AS assigned_customer_id,
            (
                SELECT COUNT(*)
                FROM crypto_wallet_assignments
                WHERE crypto_wallet_assignments.wallet_address_id = crypto_wallet_addresses.id
                  AND crypto_wallet_assignments.status IN ('reserved', 'active')
            ) AS active_assignment_count,
            crypto_wallet_addresses.updated_at
         FROM crypto_wallet_addresses
         LEFT JOIN crypto_assets ON crypto_assets.id = crypto_wallet_addresses.crypto_asset_id
         ORDER BY crypto_wallet_addresses.id DESC
         LIMIT {$offset}, {$limit}"
    );
}

function admin_crypto_asset_rows(Mysql_ks $db, bool $onlyActive = false): array
{
    if (!schema_object_exists($db, 'crypto_assets')) {
        return [];
    }

    $whereSql = $onlyActive ? 'WHERE is_active = 1' : '';

    return $db->select_full_user(
        "SELECT id, code, name, coingecko_id, logo_url, current_rate_fiat, rate_updated_at, is_active
         FROM crypto_assets
         {$whereSql}
         ORDER BY is_active DESC, name ASC, id ASC"
    );
}

function admin_default_coingecko_id(string $assetCode): string
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

function admin_http_json(string $url): ?array
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
                'User-Agent: Reseller-Admin/1.0',
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
            'header' => "Accept: application/json\r\nUser-Agent: Reseller-Admin/1.0\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if (!is_string($response) || $response === '') {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function admin_refresh_crypto_asset_rates(Mysql_ks $db, string $vsCurrency = 'USD', int $cacheTtl = 900): array
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
            updated_at,
            is_active
         FROM crypto_assets
         WHERE is_active = 1
         ORDER BY name ASC, id ASC"
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
            $coingeckoId = admin_default_coingecko_id($code);
            $rows[$index]['coingecko_id'] = $coingeckoId;
        }

            if ($coingeckoId !== '') {
                $coingeckoIds[$coingeckoId] = true;
            }

            $rows[$index]['rate_refreshed_now'] = 0;
            $rows[$index]['rate_refresh_label'] = '';
            $rate = isset($row['current_rate_fiat']) ? (float)$row['current_rate_fiat'] : 0.0;
            $rateCurrencyCode = strtoupper(trim((string)($row['rate_currency_code'] ?? '')));
            $rowUpdatedAt = !empty($row['updated_at']) ? strtotime((string)$row['updated_at']) : 0;
            if ($coingeckoId === '' || $rate <= 0 || $rateCurrencyCode !== $vsCurrency || $rowUpdatedAt <= 0 || ($now - $rowUpdatedAt) >= $cacheTtl) {
                $needsRefresh = true;
            }
        }

    if ($needsRefresh && $coingeckoIds) {
        $apiUrl = 'https://api.coingecko.com/api/v3/simple/price?ids='
            . rawurlencode(implode(',', array_keys($coingeckoIds)))
            . '&vs_currencies=' . rawurlencode($vsCurrencyLower)
            . '&include_last_updated_at=true&precision=full';
        $payload = admin_http_json($apiUrl);

        if (is_array($payload)) {
            foreach ($rows as $index => $row) {
                $assetId = (int)($row['id'] ?? 0);
                $coingeckoId = (string)($row['coingecko_id'] ?? '');
                if ($assetId <= 0 || $coingeckoId === '' || empty($payload[$coingeckoId][$vsCurrencyLower])) {
                    continue;
                }

                $price = (float)$payload[$coingeckoId][$vsCurrencyLower];
                    $updatedAt = !empty($payload[$coingeckoId]['last_updated_at'])
                        ? date('Y-m-d H:i:s', (int)$payload[$coingeckoId]['last_updated_at'])
                        : date('Y-m-d H:i:s', $now);
                    $refreshRecordedAt = date('Y-m-d H:i:s', $now);

                    $db->update_using_id(
                        ['coingecko_id', 'current_rate_fiat', 'rate_currency_code', 'rate_updated_at'],
                        [$coingeckoId, $price, $vsCurrency, $updatedAt],
                    'crypto_assets',
                    $assetId
                );

                    $rows[$index]['current_rate_fiat'] = $price;
                    $rows[$index]['rate_currency_code'] = $vsCurrency;
                    $rows[$index]['rate_updated_at'] = $updatedAt;
                    $rows[$index]['updated_at'] = $refreshRecordedAt;
                    $rows[$index]['rate_refreshed_now'] = 1;
                    $rows[$index]['rate_refresh_label'] = date('d.m.Y H:i', strtotime($refreshRecordedAt));
                }
            }
        }

    return $rows;
}

function admin_crypto_asset_count(Mysql_ks $db): int
{
    if (!schema_object_exists($db, 'crypto_assets')) {
        return 0;
    }

    $row = $db->select_user("SELECT COUNT(*) AS total FROM crypto_assets");
    return (int)($row['total'] ?? 0);
}

function admin_crypto_asset_find(Mysql_ks $db, int $assetId): ?array
{
    if ($assetId <= 0 || !schema_object_exists($db, 'crypto_assets')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT id, code, name, coingecko_id, logo_url, current_rate_fiat, rate_updated_at, is_active
         FROM crypto_assets
         WHERE id = {$assetId}
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_save_crypto_asset(Mysql_ks $db, int $assetId, array $input): array
{
    $asset = admin_crypto_asset_find($db, $assetId);
    if (!$asset) {
        return ['ok' => false, 'message' => 'Asset not found.'];
    }

    $name = trim((string)($input['name'] ?? ''));
    $coingeckoId = trim((string)($input['coingecko_id'] ?? ''));
    $logoUrl = trim((string)($input['logo_url'] ?? ''));
    $isActive = isset($input['is_active']) && (string)$input['is_active'] === '1' ? 1 : 0;

    if ($name === '') {
        return ['ok' => false, 'message' => 'Asset name is required.'];
    }

    if ($coingeckoId === '') {
        $coingeckoId = admin_default_coingecko_id((string)($asset['code'] ?? ''));
    }

    $updated = $db->update_using_id(
        ['name', 'coingecko_id', 'logo_url', 'is_active'],
        [$name, $coingeckoId !== '' ? $coingeckoId : null, $logoUrl !== '' ? $logoUrl : null, $isActive],
        'crypto_assets',
        $assetId
    );

    return [
        'ok' => (bool)$updated,
        'message' => $updated ? 'Asset saved successfully.' : 'Unable to save asset.',
    ];
}

function admin_crypto_asset_network_options(string $assetCode): array
{
    $assetCode = strtoupper(trim($assetCode));
    $fixed = [
        'BTC' => ['bitcoin' => 'Bitcoin'],
        'BCH' => ['bitcoin-cash' => 'Bitcoin Cash'],
        'LTC' => ['litecoin' => 'Litecoin'],
        'DOGE' => ['dogecoin' => 'Dogecoin'],
        'ETH' => ['ethereum' => 'Ethereum'],
        'BNB' => ['bnb' => 'BNB Smart Chain'],
        'CRO' => ['cronos' => 'Cronos'],
        'SOL' => ['solana' => 'Solana'],
        'MATIC' => ['polygon' => 'Polygon'],
        'XRP' => ['ripple' => 'XRP Ledger'],
    ];

    if (isset($fixed[$assetCode])) {
        return $fixed[$assetCode];
    }

    if ($assetCode === 'USDT' || $assetCode === 'USDC') {
        return [
            'ethereum' => 'Ethereum (ERC20)',
            'polygon' => 'Polygon',
            'bnb' => 'BNB Smart Chain',
            'tron' => 'Tron (TRC20)',
            'solana' => 'Solana',
        ];
    }

    return [];
}

function admin_crypto_asset_allows_network_choice(string $assetCode): bool
{
    return count(admin_crypto_asset_network_options($assetCode)) > 1;
}

function admin_crypto_network_label(string $networkCode): string
{
    $labels = [
        'bitcoin' => 'Bitcoin',
        'bitcoin-cash' => 'Bitcoin Cash',
        'litecoin' => 'Litecoin',
        'dogecoin' => 'Dogecoin',
        'ethereum' => 'Ethereum',
        'polygon' => 'Polygon',
        'bnb' => 'BNB Smart Chain',
        'tron' => 'Tron (TRC20)',
        'cronos' => 'Cronos',
        'solana' => 'Solana',
        'ripple' => 'XRP Ledger',
    ];

    $networkCode = strtolower(trim($networkCode));
    return $labels[$networkCode] ?? ucfirst(str_replace('-', ' ', $networkCode));
}

function admin_crypto_wallet_provider_options(?string $currentProvider = null): array
{
    $options = [
        '' => 'Choose provider',
        'TrustWallet' => 'TrustWallet',
        'MetaMask' => 'MetaMask',
        'KrakenWallet' => 'KrakenWallet',
    ];

    $currentProvider = trim((string)$currentProvider);
    if ($currentProvider !== '' && !isset($options[$currentProvider])) {
        $options[$currentProvider] = $currentProvider;
    }

    return $options;
}

function admin_infer_stablecoin_network_by_address(string $address): string
{
    $address = trim($address);
    if ($address === '') {
        return 'ethereum';
    }

    if (strpos($address, 'T') === 0) {
        return 'tron';
    }

    if (strpos($address, '0x') === 0 || strpos($address, '0X') === 0) {
        return 'ethereum';
    }

    if (strpos($address, 'cro') === 0) {
        return 'cronos';
    }

    if (strlen($address) >= 32 && preg_match('/^[1-9A-HJ-NP-Za-km-z]+$/', $address)) {
        return 'solana';
    }

    return 'ethereum';
}

function admin_normalize_crypto_wallet_network(string $assetCode, string $requestedNetworkCode, string $address = ''): string
{
    $assetCode = strtoupper(trim($assetCode));
    $options = admin_crypto_asset_network_options($assetCode);
    if (!$options) {
        return '';
    }

    if (!admin_crypto_asset_allows_network_choice($assetCode)) {
        return (string)array_key_first($options);
    }

    $requestedNetworkCode = strtolower(trim($requestedNetworkCode));
    if ($requestedNetworkCode !== '' && isset($options[$requestedNetworkCode])) {
        return $requestedNetworkCode;
    }

    if ($assetCode === 'USDT' || $assetCode === 'USDC') {
        $inferred = admin_infer_stablecoin_network_by_address($address);
        if (isset($options[$inferred])) {
            return $inferred;
        }
    }

    return (string)array_key_first($options);
}

function admin_toggle_crypto_asset(Mysql_ks $db, int $assetId): array
{
    $asset = admin_crypto_asset_find($db, $assetId);
    if (!$asset) {
        return ['ok' => false, 'message' => 'Asset not found.'];
    }

    $nextState = !empty($asset['is_active']) ? 0 : 1;
    $updated = $db->update_using_id(['is_active'], [$nextState], 'crypto_assets', $assetId);

    return [
        'ok' => (bool)$updated,
        'message' => $updated ? 'Asset status updated.' : 'Unable to update asset status.',
    ];
}

function admin_duration_label_from_hours(int $hours): string
{
    $hours = max(0, $hours);
    if ($hours === 0) {
        return '0h';
    }

    if ($hours % 720 === 0) {
        $months = (int)($hours / 720);
        return $months . ' ' . ($months === 1 ? 'Month' : 'Months');
    }

    if ($hours % 24 === 0) {
        $days = (int)($hours / 24);
        return $days . ' ' . ($days === 1 ? 'Day' : 'Days');
    }

    return $hours . 'h';
}

function admin_format_product_option_label(array $productRow): string
{
    $provider = trim((string)($productRow['provider_name'] ?? ''));
    $name = trim((string)($productRow['name'] ?? ''));
    $productType = admin_normalize_product_type($productRow['product_type'] ?? 'subscription');
    $duration = $productType === 'credits' ? 'Credits' : admin_duration_label_from_hours((int)($productRow['duration_hours'] ?? 0));
    $amount = trim((string)($productRow['price_amount'] ?? '0.00'));
    $currency = trim((string)($productRow['currency_code'] ?? ''));

    $parts = [];
    if ($provider !== '') {
        $parts[] = $provider;
    }
    if ($name !== '') {
        $parts[] = $name;
    }
    if ($duration !== '') {
        $parts[] = $duration;
    }

    $label = implode(' - ', $parts);
    if ($amount !== '' || $currency !== '') {
        $label .= ' · ' . trim($amount . ' ' . $currency);
    }

    return trim($label);
}

function admin_product_basic_row(Mysql_ks $db, int $productId): ?array
{
    if ($productId <= 0 || !schema_object_exists($db, 'products')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT
            products.id,
            products.provider_id,
            products.name,
            products.duration_hours,
            products.price_amount,
            products.currency_id,
            products.is_active,
            product_providers.name AS provider_name,
            product_providers.supports_manual_delivery,
            currencies.code AS currency_code
         FROM products
         LEFT JOIN product_providers ON product_providers.id = products.provider_id
         LEFT JOIN currencies ON currencies.id = products.currency_id
         WHERE products.id = {$productId}
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_create_order(Mysql_ks $db, array $input): array
{
    $customerId = (int)($input['customer_id'] ?? 0);
    $productId = (int)($input['product_id'] ?? 0);
    $customer = admin_customer_basic_row($db, $customerId);
    $product = admin_product_basic_row($db, $productId);

    if (!$customer) {
        return ['ok' => false, 'message' => 'Customer not found.'];
    }

    if (!$product || empty($product['is_active'])) {
        return ['ok' => false, 'message' => 'Product is not available.'];
    }

    $note = trim((string)($input['customer_note'] ?? ''));
    $hasDeliveryLink = isset($input['has_delivery_link']) && (string)$input['has_delivery_link'] === '1';
    $deliveryLink = $hasDeliveryLink ? trim((string)($input['delivery_link'] ?? '')) : '';
    $deliveryLinkVisible = $hasDeliveryLink && $deliveryLink !== '' && empty($product['supports_manual_delivery']) ? 1 : 0;

    if ($hasDeliveryLink && $deliveryLink !== '' && filter_var($deliveryLink, FILTER_VALIDATE_URL) === false) {
        return ['ok' => false, 'message' => 'Delivery URL must be a valid link.'];
    }

    $durationHours = (int)($product['duration_hours'] ?? 0);
    $expiresAt = $durationHours > 0 ? date('Y-m-d H:i:s', time() + ($durationHours * 3600)) : null;
    $orderReference = 'ADM-' . date('YmdHis') . '-' . $customerId;
    $insertFields = [
        'customer_id',
        'product_id',
        'order_reference',
        'source_system',
        'payment_method',
        'total_amount',
        'currency_id',
        'status',
        'payment_status',
        'fulfillment_status',
        'customer_note',
        'delivery_link',
        'expires_at',
    ];
    $insertValues = [
        $customerId,
        $productId,
        $orderReference,
        'native',
        null,
        (string)($product['price_amount'] ?? '0.00'),
        (int)($product['currency_id'] ?? 1),
        'pending_payment',
        'unpaid',
        'pending',
        $note !== '' ? $note : null,
        ($hasDeliveryLink && $deliveryLink !== '') ? $deliveryLink : null,
        $expiresAt,
    ];

    if (schema_column_exists($db, 'orders', 'delivery_link_visible')) {
        array_splice($insertFields, 12, 0, ['delivery_link_visible']);
        array_splice($insertValues, 12, 0, [$deliveryLinkVisible]);
    }

    $inserted = $db->insert($insertFields, $insertValues, 'orders');

    if (!$inserted) {
        return ['ok' => false, 'message' => 'Unable to create order.'];
    }

    $orderId = (int)$db->id();
    if (schema_object_exists($db, 'order_status_events')) {
        $db->insert(
            ['order_id', 'admin_user_id', 'old_status', 'new_status', 'event_note'],
            [$orderId, isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : null, null, 'pending_payment', 'Order created from admin panel'],
            'order_status_events'
        );
    }

    app_queue_order_created_notification($db, $orderId);

    return ['ok' => true, 'message' => 'Order created successfully.', 'order_id' => $orderId];
}

function admin_save_order_info(
    Mysql_ks $db,
    int $orderId,
    array $input,
    int $adminUserId = 0,
    string $ipAddress = ''
): array
{
    $order = admin_order_find($db, $orderId);
    if (!$order) {
        return ['ok' => false, 'message' => 'Order not found.'];
    }

    $totalAmountRaw = trim((string)($input['total_amount'] ?? ''));
    if ($totalAmountRaw === '' || !is_numeric($totalAmountRaw)) {
        return ['ok' => false, 'message' => 'Amount must be a valid number.'];
    }

    $deliveryLink = trim((string)($input['delivery_link'] ?? ''));
    $deliveryLinkVisible = isset($input['delivery_link_visible']) && (string)($input['delivery_link_visible']) === '1' ? 1 : 0;
    if ($deliveryLink !== '' && filter_var($deliveryLink, FILTER_VALIDATE_URL) === false) {
        return ['ok' => false, 'message' => 'Delivery URL must be a valid link.'];
    }
    if ($deliveryLink === '') {
        $deliveryLinkVisible = 0;
    }

    $paymentMethod = trim((string)($input['payment_method'] ?? ''));
    if (!in_array($paymentMethod, admin_order_payment_method_options((string)($order['payment_method'] ?? '')), true)) {
        $paymentMethod = '';
    }

    $status = trim((string)($input['status'] ?? ''));
    $paymentStatus = trim((string)($input['payment_status'] ?? ''));
    $fulfillmentStatus = trim((string)($input['fulfillment_status'] ?? ''));

    if ($status === 'active') {
        $paymentStatus = 'paid';
        $fulfillmentStatus = 'delivered';
    } elseif ($status === 'pending_payment') {
        $paymentStatus = 'unpaid';
        $fulfillmentStatus = 'pending';
    }

    if (!in_array($status, admin_order_status_options((string)($order['status'] ?? '')), true)) {
        return ['ok' => false, 'message' => 'Order status is invalid.'];
    }
    if (!in_array($paymentStatus, admin_order_payment_status_options((string)($order['payment_status'] ?? '')), true)) {
        return ['ok' => false, 'message' => 'Payment status is invalid.'];
    }
    if (!in_array($fulfillmentStatus, admin_order_fulfillment_status_options((string)($order['fulfillment_status'] ?? '')), true)) {
        return ['ok' => false, 'message' => 'Fulfillment status is invalid.'];
    }

    $startedAt = admin_normalize_datetime_input($input['started_at'] ?? null);
    $expiresAt = admin_normalize_datetime_input($input['expires_at'] ?? null);
    $paidAt = admin_normalize_datetime_input($input['paid_at'] ?? null);

    if ($status === 'active' && $startedAt === null) {
        $startedAt = date('Y-m-d H:i:s');
    }

    if ($paymentStatus === 'paid' && $paidAt === null) {
        $paidAt = date('Y-m-d H:i:s');
    }

    $updateFields = [
        'order_reference',
        'payment_method',
        'total_amount',
        'customer_note',
        'support_note',
        'delivery_link',
        'transaction_reference',
        'started_at',
        'expires_at',
        'paid_at',
        'status',
        'payment_status',
        'fulfillment_status',
    ];
    $updateValues = [
        trim((string)($input['order_reference'] ?? '')) ?: null,
        $paymentMethod !== '' ? $paymentMethod : null,
        number_format((float)$totalAmountRaw, 2, '.', ''),
        trim((string)($input['customer_note'] ?? '')) ?: null,
        trim((string)($input['support_note'] ?? '')) ?: null,
        $deliveryLink !== '' ? $deliveryLink : null,
        trim((string)($input['transaction_reference'] ?? '')) ?: null,
        $startedAt,
        $expiresAt,
        $paidAt,
        $status,
        $paymentStatus,
        $fulfillmentStatus,
    ];

    if (schema_column_exists($db, 'orders', 'delivery_link_visible')) {
        array_splice($updateFields, 6, 0, ['delivery_link_visible']);
        array_splice($updateValues, 6, 0, [$deliveryLinkVisible]);
    }

    $updated = $db->update_using_id($updateFields, $updateValues, 'orders', $orderId);

    if ($updated && schema_object_exists($db, 'order_status_events') && $status !== (string)($order['status'] ?? '')) {
        $db->insert(
            ['order_id', 'admin_user_id', 'old_status', 'new_status', 'event_note'],
            [$orderId, isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : null, (string)($order['status'] ?? ''), $status, 'Order updated from admin modal'],
            'order_status_events'
        );
    }

    if ($updated) {
        $freshOrder = admin_order_find($db, $orderId);
        if (is_array($freshOrder)) {
            app_queue_order_transition_notifications($db, $order, $freshOrder);
        }

        $changes = [];
        if ((string)($order['status'] ?? '') !== $status) {
            $changes[] = 'status ' . (string)($order['status'] ?? 'unknown') . ' -> ' . $status;
        }
        if ((string)($order['payment_status'] ?? '') !== $paymentStatus) {
            $changes[] = 'payment ' . (string)($order['payment_status'] ?? 'unknown') . ' -> ' . $paymentStatus;
        }
        if ((string)($order['fulfillment_status'] ?? '') !== $fulfillmentStatus) {
            $changes[] = 'fulfillment ' . (string)($order['fulfillment_status'] ?? 'unknown') . ' -> ' . $fulfillmentStatus;
        }

        $actionKey = ((string)($order['payment_status'] ?? '') !== 'paid' && $paymentStatus === 'paid')
            ? 'order_payment_approved'
            : 'order_updated';

        admin_log_customer_and_admin(
            $db,
            (int)($order['customer_id'] ?? 0),
            $adminUserId,
            $actionKey,
            'Order #' . $orderId . ' updated' . ($changes ? ': ' . implode(', ', $changes) . '.' : '.'),
            $ipAddress
        );
    }

    return [
        'ok' => (bool)$updated,
        'message' => $updated ? 'Order updated successfully.' : 'Unable to update order.',
    ];
}

function admin_order_product_can_extend(array $product): bool
{
    if (empty($product['is_active'])) {
        return false;
    }

    if (strtolower(trim((string)($product['product_type'] ?? 'subscription'))) !== 'subscription') {
        return false;
    }

    if (!empty($product['is_trial'])) {
        return false;
    }

    return (int)($product['duration_hours'] ?? 0) > 24;
}

function admin_delete_order(
    Mysql_ks $db,
    int $orderId,
    int $adminUserId = 0,
    string $ipAddress = ''
): array
{
    $order = admin_order_find($db, $orderId);
    if (!$order) {
        return ['ok' => false, 'message' => 'Order not found.'];
    }

    if (schema_object_exists($db, 'order_status_events')) {
        @$db->query("DELETE FROM order_status_events WHERE order_id = {$orderId}");
    }

    $deleted = $db->delete_using_id('orders', $orderId);
    if (!$deleted) {
        return ['ok' => false, 'message' => 'Unable to delete order.'];
    }

    admin_log_customer_and_admin(
        $db,
        (int)($order['customer_id'] ?? 0),
        $adminUserId,
        'order_deleted',
        'Order #' . $orderId . ' deleted from admin panel.',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Order deleted successfully.'];
}

function admin_extend_order(Mysql_ks $db, int $orderId, int $productId): array
{
    $order = admin_order_find($db, $orderId);
    $product = admin_product_basic_row($db, $productId);

    if (!$order) {
        return ['ok' => false, 'message' => 'Order not found.'];
    }

    if (!$product || empty($product['is_active'])) {
        return ['ok' => false, 'message' => 'Extension package is not available.'];
    }

    if ((int)($product['provider_id'] ?? 0) <= 0 || (int)($product['provider_id'] ?? 0) !== (int)($order['provider_id'] ?? 0)) {
        return ['ok' => false, 'message' => 'You can only extend the order with a package from the same provider.'];
    }

    if (!admin_order_product_can_extend($product)) {
        return ['ok' => false, 'message' => 'Selected package cannot be used for subscription extension.'];
    }

    $durationHours = (int)($product['duration_hours'] ?? 0);
    if ($durationHours <= 0) {
        return ['ok' => false, 'message' => 'Selected package has no duration.'];
    }

    $now = time();
    $currentExpiry = !empty($order['expires_at']) ? strtotime((string)$order['expires_at']) : 0;
    $baseTimestamp = $currentExpiry > $now ? $currentExpiry : $now;
    $newExpiry = date('Y-m-d H:i:s', $baseTimestamp + ($durationHours * 3600));

    $updated = $db->update_using_id(
        ['product_id', 'total_amount', 'currency_id', 'expires_at'],
        [
            $productId,
            number_format((float)($product['price_amount'] ?? 0), 2, '.', ''),
            (int)($product['currency_id'] ?? ($order['product_currency_id'] ?? 1)),
            $newExpiry,
        ],
        'orders',
        $orderId
    );

    if ($updated && schema_object_exists($db, 'order_status_events')) {
        $db->insert(
            ['order_id', 'admin_user_id', 'old_status', 'new_status', 'event_note'],
            [$orderId, isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : null, (string)($order['status'] ?? ''), (string)($order['status'] ?? ''), 'Order extended by ' . admin_duration_label_from_hours($durationHours)],
            'order_status_events'
        );
    }

    if ($updated) {
        app_queue_order_extended_notification($db, $orderId);
    }

    return [
        'ok' => (bool)$updated,
        'message' => $updated ? 'Subscription extended successfully.' : 'Unable to extend subscription.',
    ];
}

function admin_customer_basic_row(Mysql_ks $db, int $customerId): ?array
{
    if ($customerId <= 0 || !schema_object_exists($db, 'customers')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT id, email, status, locale_code
         FROM customers
         WHERE id = {$customerId}
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_create_customer_from_email(Mysql_ks $db, string $email, array $settings, string $clientIp = ''): array
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.'];
    }

    $existingCustomer = app_find_customer_by_email($db, $email);
    if ($existingCustomer) {
        return [
            'ok' => true,
            'customer' => [
                'id' => (int)($existingCustomer['id'] ?? 0),
                'email' => (string)($existingCustomer['email'] ?? ''),
                'status' => (string)($existingCustomer['status'] ?? ''),
                'locale_code' => (string)($existingCustomer['locale_code'] ?? ''),
                'created' => false,
            ],
        ];
    }

    $localeCode = trim((string)($settings['default_locale_code'] ?? 'en'));
    if ($localeCode === '') {
        $localeCode = 'en';
    }

    $currentTime = date('Y-m-d H:i:s');
    $temporaryPassword = bin2hex(random_bytes(12));
    $customerId = app_insert_customer_registration(
        $db,
        $email,
        $temporaryPassword,
        $localeCode,
        $currentTime,
        $clientIp,
        1
    );

    if ($customerId <= 0) {
        return ['ok' => false, 'message' => 'Unable to create customer.'];
    }

    $customer = app_find_customer_by_id($db, $customerId);
    if (!$customer) {
        return ['ok' => false, 'message' => 'Customer was created, but could not be loaded.'];
    }

    app_queue_account_created_notification($db, $customerId);

    return [
        'ok' => true,
        'customer' => [
            'id' => (int)($customer['id'] ?? 0),
            'email' => (string)($customer['email'] ?? ''),
            'status' => (string)($customer['status'] ?? ''),
            'locale_code' => (string)($customer['locale_code'] ?? ''),
            'created' => true,
        ],
    ];
}

function admin_customer_status_options(string $current = ''): array
{
    $options = ['active', 'inactive', 'blocked'];
    $current = strtolower(trim($current));
    if ($current !== '' && !in_array($current, $options, true)) {
        $options[] = $current;
    }

    return $options;
}

function admin_generate_customer_password(int $length = 12): string
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

function admin_queue_customer_password_email(
    Mysql_ks $db,
    int $customerId,
    string $email,
    string $plainPassword,
    string $localeCode
): array {
    return app_email_queue_template(
        $db,
        'password-reset',
        $email,
        [
            'customer_email' => $email,
            'password' => $plainPassword,
        ],
        $customerId,
        null,
        0,
        true,
        $localeCode
    );
}

function admin_create_customer_account(
    Mysql_ks $db,
    string $email,
    string $plainPassword,
    array $options,
    int $adminUserId,
    string $ipAddress = ''
): array {
    if (!schema_object_exists($db, 'customers')) {
        return ['ok' => false, 'message' => 'Customers table is missing.'];
    }

    app_ensure_customer_runtime_columns($db);

    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Enter a valid email address.'];
    }

    $existing = app_find_customer_by_email($db, $email);
    if ($existing) {
        return ['ok' => false, 'message' => 'This email address is already assigned to another user.'];
    }

    $plainPassword = trim($plainPassword);
    if ($plainPassword === '') {
        $plainPassword = admin_generate_customer_password();
    }

    $localeCode = admin_normalize_locale((string)($options['locale_code'] ?? 'en'));
    $status = strtolower(trim((string)($options['status'] ?? 'active')));
    if (!in_array($status, admin_customer_status_options($status), true)) {
        $status = 'active';
    }

    $sendPasswordEmail = !empty($options['send_password_email']);
    $emailNotification = array_key_exists('email_notification', $options)
        ? (!empty($options['email_notification']) ? 1 : 0)
        : (array_key_exists('is_newsletter_subscribed', $options) ? (!empty($options['is_newsletter_subscribed']) ? 1 : 0) : 1);
    $currentTime = date('Y-m-d H:i:s');

    $insertFields = [
        'email',
        'password_hash',
        'password_hash_algorithm',
        'locale_code',
        'ip_address',
        'status',
        'customer_type',
        'email_verified_at',
        'registered_at',
        'last_login_at',
    ];
    $insertValues = [
        $email,
        password_hash($plainPassword, PASSWORD_DEFAULT),
        'password_hash',
        $localeCode,
        $ipAddress !== '' ? $ipAddress : null,
        $status,
        'client',
        $currentTime,
        $currentTime,
        null,
    ];

    $notificationColumn = app_customer_notification_storage_column($db);
    if ($notificationColumn !== '') {
        $insertFields[] = $notificationColumn;
        $insertValues[] = $emailNotification;
    }

    $inserted = $db->insert($insertFields, $insertValues, 'customers');

    if (!$inserted) {
        return ['ok' => false, 'message' => 'Unable to create customer.'];
    }

    $customerId = (int)$db->id();
    if ($customerId <= 0) {
        return ['ok' => false, 'message' => 'Customer was created, but could not be loaded.'];
    }

    admin_log_customer_and_admin(
        $db,
        $customerId,
        $adminUserId,
        'account_created',
        'Customer account created by admin.',
        $ipAddress
    );

    $emailResult = ['ok' => false, 'queued' => false];
    if ($sendPasswordEmail) {
        $emailResult = admin_queue_customer_password_email($db, $customerId, $email, $plainPassword, $localeCode);
    }

    return [
        'ok' => true,
        'message' => 'Customer created successfully.',
        'customer_id' => $customerId,
        'email' => $email,
        'password' => $plainPassword,
        'locale_code' => $localeCode,
        'status' => $status,
        'email_notification' => $emailResult,
    ];
}

function admin_delete_customer_account(Mysql_ks $db, int $customerId, int $adminUserId, string $ipAddress = ''): array
{
    if ($customerId <= 0 || !schema_object_exists($db, 'customers')) {
        return ['ok' => false, 'message' => 'Customer not found.'];
    }

    $customer = admin_customer_detail_row($db, $customerId);
    if (!$customer) {
        return ['ok' => false, 'message' => 'Customer not found.'];
    }

    $customerEmail = (string)($customer['email'] ?? '');

    $db->start();

    if (schema_object_exists($db, 'support_messages')) {
        $db->query(
            "UPDATE support_messages
             SET customer_id = NULL
             WHERE customer_id = {$customerId}"
        );
    }

    if (schema_object_exists($db, 'support_conversations')) {
        $db->query(
            "UPDATE support_conversations
             SET customer_id = NULL
             WHERE customer_id = {$customerId}"
        );
    }

    if (schema_object_exists($db, 'customer_activity_logs')) {
        $db->query(
            "UPDATE customer_activity_logs
             SET customer_id = NULL
             WHERE customer_id = {$customerId}"
        );
    }

    if (schema_object_exists($db, 'crypto_deposit_transactions')) {
        $db->query(
            "UPDATE crypto_deposit_transactions
             SET customer_id = NULL
             WHERE customer_id = {$customerId}"
        );
    }

    if (schema_object_exists($db, 'bank_transfer_requests')) {
        $db->query("DELETE FROM bank_transfer_requests WHERE customer_id = {$customerId}");
    }

    if (schema_object_exists($db, 'crypto_deposit_requests')) {
        $db->query("DELETE FROM crypto_deposit_requests WHERE customer_id = {$customerId}");
    }

    if (schema_object_exists($db, 'bank_account_assignments')) {
        $db->query("DELETE FROM bank_account_assignments WHERE customer_id = {$customerId}");
    }

    if (schema_object_exists($db, 'crypto_wallet_assignments')) {
        $db->query("DELETE FROM crypto_wallet_assignments WHERE customer_id = {$customerId}");
    }

    if (schema_object_exists($db, 'referrals')) {
        $db->query(
            "DELETE FROM referrals
             WHERE referrer_customer_id = {$customerId}
                OR referred_customer_id = {$customerId}"
        );
    }

    if (schema_object_exists($db, 'orders')) {
        $db->query("DELETE FROM orders WHERE customer_id = {$customerId}");
    }

    $deleted = $db->delete_using_id('customers', $customerId);
    if (!$deleted) {
        $db->query('ROLLBACK');
        return ['ok' => false, 'message' => 'Unable to delete customer account.'];
    }

    admin_activity_log(
        $db,
        0,
        $adminUserId,
        'customer_deleted',
        'Customer account deleted: ' . $customerEmail . '. Related operational data was removed.',
        $ipAddress
    );

    $db->commit();

    return ['ok' => true, 'message' => 'Customer account deleted successfully.'];
}

function admin_import_detect_delimiter(array $lines): string
{
    $candidates = [",", ";", "\t", "|"];
    $bestDelimiter = ',';
    $bestScore = -1;
    $sample = array_slice($lines, 0, 5);

    foreach ($candidates as $delimiter) {
        $score = 0;
        foreach ($sample as $line) {
            $score += substr_count($line, $delimiter);
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestDelimiter = $delimiter;
        }
    }

    return $bestDelimiter;
}

function admin_import_normalize_header_cell(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
    return trim($value, '_');
}

function admin_import_extract_email_from_cell(string $value): string
{
    $value = trim($value);
    if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return strtolower($value);
    }

    if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches)) {
        return strtolower((string)($matches[0] ?? ''));
    }

    return '';
}

function admin_parse_customer_import_source(string $rawInput): array
{
    $rawInput = str_replace(["\r\n", "\r"], "\n", trim($rawInput));
    $rawInput = preg_replace('/^\xEF\xBB\xBF/', '', $rawInput ?? '') ?? $rawInput;
    if ($rawInput === '') {
        return ['ok' => false, 'message' => 'Import source is empty.'];
    }

    $lines = array_values(array_filter(array_map('trim', explode("\n", $rawInput)), static function (string $line): bool {
        return $line !== '';
    }));
    if (!$lines) {
        return ['ok' => false, 'message' => 'Import source is empty.'];
    }

    $delimiter = admin_import_detect_delimiter($lines);
    $parsedRows = [];
    $maxColumns = 0;

    foreach ($lines as $lineIndex => $line) {
        $row = str_getcsv($line, $delimiter);
        if (count($row) === 1) {
            $fallbackEmail = admin_import_extract_email_from_cell((string)$row[0]);
            if ($fallbackEmail !== '' && trim((string)$row[0]) !== $fallbackEmail) {
                $row = [$fallbackEmail];
            }
        }

        $maxColumns = max($maxColumns, count($row));
        $parsedRows[] = [
            'line_number' => $lineIndex + 1,
            'cells' => $row,
        ];
    }

    if ($maxColumns <= 0) {
        return ['ok' => false, 'message' => 'Unable to detect import columns.'];
    }

    $headerMap = [];
    $emailColumn = -1;
    $hasHeader = false;
    $firstRowCells = isset($parsedRows[0]['cells']) && is_array($parsedRows[0]['cells']) ? $parsedRows[0]['cells'] : [];
    foreach ($firstRowCells as $cellIndex => $cellValue) {
        $normalized = admin_import_normalize_header_cell((string)$cellValue);
        $headerMap[$cellIndex] = $normalized;
        if (in_array($normalized, ['email', 'e_mail', 'mail', 'email_address', 'adres_email'], true)) {
            $emailColumn = $cellIndex;
            $hasHeader = true;
        }
    }

    if ($emailColumn < 0) {
        $columnScores = array_fill(0, $maxColumns, 0);
        foreach ($parsedRows as $rowIndex => $row) {
            foreach (($row['cells'] ?? []) as $cellIndex => $cellValue) {
                if (admin_import_extract_email_from_cell((string)$cellValue) !== '') {
                    $columnScores[$cellIndex] = (int)($columnScores[$cellIndex] ?? 0) + 1;
                }
            }
            if ($rowIndex >= 15) {
                break;
            }
        }

        $bestScore = 0;
        foreach ($columnScores as $cellIndex => $score) {
            if ($score > $bestScore) {
                $bestScore = $score;
                $emailColumn = (int)$cellIndex;
            }
        }
    }

    if ($emailColumn < 0) {
        return ['ok' => false, 'message' => 'Unable to detect the email column automatically.'];
    }

    $items = [];
    $seenEmails = [];
    foreach ($parsedRows as $rowIndex => $row) {
        if ($hasHeader && $rowIndex === 0) {
            continue;
        }

        $cells = is_array($row['cells'] ?? null) ? $row['cells'] : [];
        $emailCell = isset($cells[$emailColumn]) ? (string)$cells[$emailColumn] : '';
        $email = admin_import_extract_email_from_cell($emailCell);
        if ($email === '') {
            if (count($cells) === 1) {
                $email = admin_import_extract_email_from_cell((string)$cells[0]);
            }
            if ($email === '') {
                continue;
            }
        }

        if (isset($seenEmails[$email])) {
            continue;
        }

        $seenEmails[$email] = true;
        $items[] = [
            'email' => $email,
            'line_number' => (int)($row['line_number'] ?? ($rowIndex + 1)),
        ];
    }

    if (!$items) {
        return ['ok' => false, 'message' => 'No valid email addresses were found in the import source.'];
    }

    $detectedColumnLabel = $hasHeader && isset($firstRowCells[$emailColumn]) && trim((string)$firstRowCells[$emailColumn]) !== ''
        ? trim((string)$firstRowCells[$emailColumn])
        : '#' . ($emailColumn + 1);

    return [
        'ok' => true,
        'items' => $items,
        'detected_email_column' => $emailColumn,
        'detected_email_column_label' => $detectedColumnLabel,
        'has_header' => $hasHeader,
        'delimiter' => $delimiter,
    ];
}

function admin_import_customer_accounts(
    Mysql_ks $db,
    string $rawInput,
    array $options,
    int $adminUserId,
    string $ipAddress = ''
): array {
    $parsed = admin_parse_customer_import_source($rawInput);
    if (empty($parsed['ok'])) {
        return $parsed;
    }

    $localeCode = admin_normalize_locale((string)($options['locale_code'] ?? 'en'));
    $status = strtolower(trim((string)($options['status'] ?? 'active')));
    if (!in_array($status, admin_customer_status_options($status), true)) {
        $status = 'active';
    }

    $createdCount = 0;
    $skippedCount = 0;
    $emailQueuedCount = 0;
    $errors = [];

    foreach ((array)($parsed['items'] ?? []) as $item) {
        $email = strtolower(trim((string)($item['email'] ?? '')));
        if ($email === '') {
            continue;
        }

        if (app_find_customer_by_email($db, $email)) {
            $skippedCount++;
            continue;
        }

        $result = admin_create_customer_account(
            $db,
            $email,
            admin_generate_customer_password(),
            [
                'locale_code' => $localeCode,
                'status' => $status,
                'send_password_email' => true,
            ],
            $adminUserId,
            $ipAddress
        );

        if (!empty($result['ok'])) {
            $createdCount++;
            if (!empty($result['email_notification']['ok']) || !empty($result['email_notification']['queued'])) {
                $emailQueuedCount++;
            }
            continue;
        }

        $errors[] = $email . ': ' . (string)($result['message'] ?? 'Unable to create customer.');
    }

    $message = $createdCount > 0
        ? 'Imported ' . $createdCount . ' user(s).'
        : 'No new users were imported.';
    if ($skippedCount > 0) {
        $message .= ' Skipped existing: ' . $skippedCount . '.';
    }
    if ($emailQueuedCount > 0) {
        $message .= ' Password emails queued: ' . $emailQueuedCount . '.';
    }
    if (!empty($parsed['detected_email_column_label'])) {
        $message .= ' Email column: ' . (string)$parsed['detected_email_column_label'] . '.';
    }
    if ($errors) {
        $message .= ' Errors: ' . implode(' | ', array_slice($errors, 0, 3));
    }

    return [
        'ok' => $createdCount > 0 || $skippedCount > 0,
        'message' => $message,
        'created_count' => $createdCount,
        'skipped_count' => $skippedCount,
        'email_queued_count' => $emailQueuedCount,
        'errors' => $errors,
        'detected_email_column_label' => (string)($parsed['detected_email_column_label'] ?? ''),
    ];
}

function admin_customer_active_bank_assignments(Mysql_ks $db, int $customerId): array
{
    if ($customerId <= 0 || !schema_object_exists($db, 'customer_bank_accounts')) {
        return [];
    }

    return $db->select_full_user(
        "SELECT
            bank_account_assignment_id,
            bank_account_id,
            customer_id,
            customer_email,
            currency_code,
            label,
            account_holder_name,
            bank_name,
            iban,
            account_number,
            status,
            assigned_at
         FROM customer_bank_accounts
         WHERE customer_id = {$customerId}
           AND status IN ('reserved', 'active')
         ORDER BY assigned_at DESC, bank_account_assignment_id DESC"
    );
}

function admin_customer_has_pending_crypto_payment(Mysql_ks $db, int $customerId): bool
{
    if ($customerId <= 0 || !schema_object_exists($db, 'crypto_deposit_requests')) {
        return false;
    }

    $row = $db->select_user(
        "SELECT COUNT(*) AS total
         FROM crypto_deposit_requests
         WHERE customer_id = {$customerId}
           AND status IN ('pending', 'awaiting_confirmation')
           AND expires_at > NOW()"
    );
    return (int)($row['total'] ?? 0) > 0;
}

function admin_customer_active_crypto_assignments(Mysql_ks $db, int $customerId, string $assetCode = ''): array
{
    if ($customerId <= 0 || !schema_object_exists($db, 'customer_crypto_wallets')) {
        return [];
    }

    $assetCode = strtoupper(trim($assetCode));
    $assetFilter = '';
    if ($assetCode !== '') {
        $assetFilter = " AND crypto_asset_code = '" . $db->escape($assetCode) . "'";
    }

    return $db->select_full_user(
        "SELECT
            wallet_assignment_id,
            wallet_address_id,
            customer_id,
            customer_email,
            crypto_asset_code,
            crypto_asset_name,
            label,
            address,
            network_code,
            wallet_provider,
            owner_full_name,
            status,
            assigned_at
         FROM customer_crypto_wallets
         WHERE customer_id = {$customerId}
           AND status IN ('reserved', 'active')
           {$assetFilter}
         ORDER BY assigned_at DESC, wallet_assignment_id DESC"
    );
}

function admin_chat_payment_amount_options(float $min = 1.0, float $max = 200.0, float $step = 1.0): array
{
    $options = [];
    for ($value = $min; $value <= $max; $value += $step) {
        $options[] = number_format($value, 2, '.', '');
    }

    return $options;
}

function admin_chat_payment_amount_normalize($amount): float
{
    $numeric = round((float)$amount, 2);
    if ($numeric < 1.0) {
        return 0.0;
    }

    if ($numeric > 200.0) {
        $numeric = 200.0;
    }

    return $numeric;
}

function admin_chat_payment_currency_context(Mysql_ks $db): array
{
    $currencyRow = admin_default_currency_row($db);
    return [
        'currency_id' => (int)($currencyRow['id'] ?? 0),
        'currency_code' => (string)($currencyRow['code'] ?? ''),
        'currency_symbol' => (string)($currencyRow['symbol'] ?? ''),
        'currency_name' => (string)($currencyRow['name'] ?? ''),
    ];
}

function admin_chat_bank_reference_build(string $template, int $customerId, int $requestId, int $orderId = 0): string
{
    $template = trim($template);
    if ($template === '') {
        return 'PAY-' . $customerId . '-' . $requestId;
    }

    return strtr($template, [
        '%customer_id%' => (string)$customerId,
        '%request_id%' => (string)$requestId,
        '%order_id%' => (string)$orderId,
    ]);
}

function admin_chat_available_crypto_wallet_for_asset(Mysql_ks $db, int $assetId, int $customerId = 0, array $settings = []): ?array
{
    if ($assetId <= 0 || !schema_object_exists($db, 'crypto_wallet_addresses')) {
        return null;
    }

    $sharedEnabled = admin_crypto_wallet_shared_assignments_enabled($settings);
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
              AND open_request.status IN ('pending', 'awaiting_confirmation')
             WHERE crypto_wallet_addresses.crypto_asset_id = {$assetId}
               AND crypto_wallet_addresses.disabled_at IS NULL
               AND crypto_wallet_addresses.status IN ('available', 'assigned')
               AND current_customer_assignment.id IS NULL
               AND open_request.id IS NULL
               AND (
                    crypto_wallet_addresses.status = 'available'
                    OR crypto_wallet_addresses.is_reusable = 1
               )
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
              AND open_request.status IN ('pending', 'awaiting_confirmation')
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

function admin_chat_crypto_wallet_pool_help_html(array $messages, array $settings, string $assetName = ''): string
{
    $walletsUrl = '/admin/?page=crypto-wallets';
    $title = $assetName !== ''
        ? admin_t($messages, 'chat_payment_wallet_pool_missing_title_asset', 'No wallet address is available for {asset}.', ['asset' => $assetName])
        : admin_t($messages, 'chat_payment_wallet_pool_missing_title', 'No wallet address is available for this cryptocurrency.');
    $body = admin_crypto_wallet_shared_assignments_enabled($settings)
        ? admin_t($messages, 'chat_payment_wallet_pool_missing_shared_on', 'There is no assigned, free or reusable wallet without an active payment request. Add another address to the wallet pool.')
        : admin_t($messages, 'chat_payment_wallet_pool_missing_shared_off', 'Crypto wallet sharing is OFF, so this customer needs a free wallet address. Add a new address to the wallet pool.')
            ;
    $button = admin_t($messages, 'chat_payment_wallet_pool_manage_button', 'Open crypto wallets');

    return '<div class="admin-chat-payment-empty">'
        . '<strong>' . admin_e($title) . '</strong>'
        . '<span>' . admin_e($body) . '</span>'
        . '<a href="' . admin_e($walletsUrl) . '" class="btn btn-dark btn-sm">' . admin_e($button) . '</a>'
        . '</div>';
}

function admin_chat_available_bank_account_rows(Mysql_ks $db, string $currencyCode = ''): array
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
            swift_bic,
            payment_reference_template,
            transfer_instructions
         FROM available_bank_account_pool
         ORDER BY label ASC, bank_name ASC, id ASC"
    );
}

function admin_chat_payment_crypto_assets(Mysql_ks $db, int $customerId, array $currencyContext): array
{
    $settings = admin_app_settings($db);
    $assets = admin_refresh_crypto_asset_rates($db, (string)($currencyContext['currency_code'] ?? 'USD'));
    if (!$assets) {
        return [];
    }

    $activeAssignments = admin_customer_active_crypto_assignments($db, $customerId);
    $assignmentMap = [];
    foreach ($activeAssignments as $assignment) {
        $assignmentMap[strtoupper((string)($assignment['crypto_asset_code'] ?? ''))] = $assignment;
    }

    $rows = [];
    foreach ($assets as $asset) {
        $assetId = (int)($asset['id'] ?? 0);
        $assetCode = strtoupper((string)($asset['code'] ?? ''));
        $existingAssignment = $assignmentMap[$assetCode] ?? null;
        $availableWallet = $existingAssignment ? null : admin_chat_available_crypto_wallet_for_asset($db, $assetId, $customerId, $settings);
        if ((float)($asset['current_rate_fiat'] ?? 0) <= 0 || (!$existingAssignment && !$availableWallet)) {
            continue;
        }

        $rows[] = [
            'id' => $assetId,
            'code' => $assetCode,
            'name' => (string)($asset['name'] ?? $assetCode),
            'logo_url' => app_crypto_logo_by_code($assetCode, (string)($asset['logo_url'] ?? '')),
            'rate' => isset($asset['current_rate_fiat']) ? (float)$asset['current_rate_fiat'] : 0.0,
            'rate_label' => app_format_crypto_rate($asset['current_rate_fiat'] ?? null),
            'rate_refreshed_now' => !empty($asset['rate_refreshed_now']) ? 1 : 0,
            'rate_refresh_label' => (string)($asset['rate_refresh_label'] ?? ''),
            'has_existing_assignment' => $existingAssignment ? 1 : 0,
            'has_available_wallet' => $availableWallet ? 1 : 0,
        ];
    }

    return $rows;
}

function admin_chat_payment_crypto_preview_html(array $preview, array $messages): string
{
    $assetName = admin_e((string)($preview['asset_name'] ?? 'Crypto'));
    $assetCode = admin_e((string)($preview['asset_code'] ?? ''));
    $logoUrl = admin_e((string)($preview['asset_logo_url'] ?? ''));
    $fiatAmount = admin_e((string)($preview['fiat_amount_label'] ?? ''));
    $cryptoAmount = admin_e((string)($preview['crypto_amount_label'] ?? ''));
    $walletAddress = admin_e((string)($preview['wallet_address'] ?? ''));
    $walletOwner = admin_e((string)($preview['wallet_owner_full_name'] ?? ''));
    $assignmentLabel = !empty($preview['uses_existing_assignment'])
        ? admin_t($messages, 'chat_payment_preview_assigned_wallet', 'Assigned wallet')
        : admin_t($messages, 'chat_payment_preview_new_wallet', 'New wallet assignment');

    ob_start();
    ?>
    <div class="admin-chat-payment-preview">
        <div class="admin-chat-payment-preview__top">
            <div class="admin-chat-payment-preview__asset">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?php echo $logoUrl; ?>" alt="" class="admin-chat-payment-preview__logo">
                <?php endif; ?>
                <div>
                    <strong><?php echo $assetName; ?></strong>
                    <span><?php echo $assetCode; ?></span>
                </div>
            </div>
            <span class="admin-status-pill admin-status-pill--neutral"><?php echo admin_e($assignmentLabel); ?></span>
        </div>
        <div class="admin-chat-payment-preview__grid">
            <div><span><?php echo admin_e(admin_t($messages, 'chat_payment_field_value', 'Transaction value')); ?></span><strong><?php echo $fiatAmount; ?></strong></div>
            <div><span><?php echo admin_e(admin_t($messages, 'chat_payment_field_amount', 'Amount to send')); ?></span><strong><?php echo $cryptoAmount; ?></strong></div>
            <div><span><?php echo admin_e(admin_t($messages, 'chat_payment_field_wallet', 'Wallet address')); ?></span><strong class="admin-chat-payment-preview__code"><?php echo $walletAddress; ?></strong></div>
            <?php if ($walletOwner !== ''): ?>
                <div><span><?php echo admin_e(admin_t($messages, 'chat_payment_field_full_name', 'Full name')); ?></span><strong><?php echo $walletOwner; ?></strong></div>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return trim((string)ob_get_clean());
}

function admin_chat_payment_bank_preview_html(array $preview, array $messages): string
{
    $amount = admin_e((string)($preview['amount_label'] ?? ''));
    $holder = admin_e((string)($preview['account_holder_name'] ?? ''));
    $bankName = admin_e((string)($preview['bank_name'] ?? ''));
    $iban = admin_e((string)($preview['iban'] ?? ''));
    $reference = admin_e((string)($preview['payment_reference'] ?? ''));
    $assignmentLabel = !empty($preview['uses_existing_assignment'])
        ? admin_t($messages, 'chat_payment_preview_assigned_account', 'Assigned account')
        : admin_t($messages, 'chat_payment_preview_new_account', 'New account assignment');

    ob_start();
    ?>
    <div class="admin-chat-payment-preview">
        <div class="admin-chat-payment-preview__top">
            <div class="admin-chat-payment-preview__asset admin-chat-payment-preview__asset--bank">
                <div>
                    <strong><?php echo admin_e(admin_t($messages, 'chat_bank_transfer_title', 'Bank transfer')); ?></strong>
                    <span><?php echo $bankName; ?></span>
                </div>
            </div>
            <span class="admin-status-pill admin-status-pill--neutral"><?php echo admin_e($assignmentLabel); ?></span>
        </div>
        <div class="admin-chat-payment-preview__grid">
            <div><span><?php echo admin_e(admin_t($messages, 'chat_payment_field_amount', 'Amount to send')); ?></span><strong><?php echo $amount; ?></strong></div>
            <div><span><?php echo admin_e(admin_t($messages, 'chat_payment_field_full_name', 'Full name')); ?></span><strong><?php echo $holder; ?></strong></div>
            <div><span>IBAN</span><strong class="admin-chat-payment-preview__code"><?php echo $iban; ?></strong></div>
            <?php if ($reference !== ''): ?>
                <div><span><?php echo admin_e(admin_t($messages, 'chat_payment_field_reference', 'Payment reference')); ?></span><strong class="admin-chat-payment-preview__code"><?php echo $reference; ?></strong></div>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return trim((string)ob_get_clean());
}

function admin_chat_crypto_payment_preview(Mysql_ks $db, int $customerId, int $assetId, $amount, array $messages): array
{
    $settings = admin_app_settings($db);
    $currencyContext = admin_chat_payment_currency_context($db);
    $amountValue = admin_chat_payment_amount_normalize($amount);
    if ($customerId <= 0 || $assetId <= 0 || $amountValue <= 0) {
        return ['ok' => false, 'message' => 'Invalid crypto payment preview payload.'];
    }

    $assetRows = admin_chat_payment_crypto_assets($db, $customerId, $currencyContext);
    $selectedAsset = null;
    foreach ($assetRows as $assetRow) {
        if ((int)$assetRow['id'] === $assetId) {
            $selectedAsset = $assetRow;
            break;
        }
    }

    if (!$selectedAsset) {
        return ['ok' => false, 'message' => 'Crypto asset not available.'];
    }

    $rate = (float)($selectedAsset['rate'] ?? 0.0);
    if ($rate <= 0) {
        return ['ok' => false, 'message' => 'Crypto exchange rate is unavailable.'];
    }

    $assignments = admin_customer_active_crypto_assignments($db, $customerId, (string)$selectedAsset['code']);
    $assignment = $assignments ? $assignments[0] : null;
    $availableWallet = null;
    if (!$assignment) {
        $availableWallet = admin_chat_available_crypto_wallet_for_asset($db, (int)$selectedAsset['id'], $customerId, $settings);
        if (!$availableWallet) {
            return [
                'ok' => false,
                'message' => 'No wallet available for this asset.',
                'preview_html' => admin_chat_crypto_wallet_pool_help_html($messages, $settings, (string)($selectedAsset['name'] ?? $selectedAsset['code'] ?? '')),
            ];
        }
    }

    $walletAddress = trim((string)($assignment['address'] ?? $availableWallet['address'] ?? ''));
    $walletOwner = trim((string)($assignment['owner_full_name'] ?? $availableWallet['owner_full_name'] ?? ''));
    $requestedCryptoAmount = sprintf('%.8f', $amountValue / $rate);

    $preview = [
        'ok' => true,
        'asset_id' => (int)$selectedAsset['id'],
        'asset_name' => (string)$selectedAsset['name'],
        'asset_code' => (string)$selectedAsset['code'],
        'asset_logo_url' => (string)$selectedAsset['logo_url'],
        'rate' => $rate,
        'fiat_amount' => $amountValue,
        'fiat_amount_label' => app_format_money_value($amountValue, (string)$currencyContext['currency_symbol'], (string)$currencyContext['currency_code']),
        'crypto_amount' => $requestedCryptoAmount,
        'crypto_amount_label' => rtrim(rtrim($requestedCryptoAmount, '0'), '.') . ' ' . (string)$selectedAsset['code'],
        'currency_id' => (int)$currencyContext['currency_id'],
        'currency_code' => (string)$currencyContext['currency_code'],
        'currency_symbol' => (string)$currencyContext['currency_symbol'],
        'wallet_address_id' => (int)($assignment['wallet_address_id'] ?? $availableWallet['wallet_address_id'] ?? 0),
        'wallet_assignment_id' => (int)($assignment['wallet_assignment_id'] ?? 0),
        'wallet_address' => $walletAddress,
        'wallet_owner_full_name' => $walletOwner,
        'uses_existing_assignment' => $assignment ? 1 : 0,
    ];
    $preview['preview_html'] = admin_chat_payment_crypto_preview_html($preview, $messages);

    return $preview;
}

function admin_chat_bank_payment_preview(Mysql_ks $db, int $customerId, $amount, int $selectedBankAccountId, array $messages): array
{
    $currencyContext = admin_chat_payment_currency_context($db);
    $amountValue = admin_chat_payment_amount_normalize($amount);
    if ($customerId <= 0 || $amountValue <= 0) {
        return ['ok' => false, 'message' => 'Invalid bank payment preview payload.'];
    }

    $assignments = admin_customer_active_bank_assignments($db, $customerId);
    $assignment = $assignments ? $assignments[0] : null;
    $bankAccount = null;

    if ($assignment) {
        $bankAccount = $assignment;
    } else {
        $availableAccounts = admin_chat_available_bank_account_rows($db, (string)($currencyContext['currency_code'] ?? ''));
        foreach ($availableAccounts as $availableAccount) {
            if ((int)($availableAccount['id'] ?? 0) === $selectedBankAccountId) {
                $bankAccount = $availableAccount;
                break;
            }
        }
        if (!$bankAccount && $availableAccounts) {
            $bankAccount = $availableAccounts[0];
        }
    }

    if (!$bankAccount) {
        return ['ok' => false, 'message' => 'No bank account available.'];
    }

    $preview = [
        'ok' => true,
        'amount' => $amountValue,
        'amount_label' => app_format_money_value($amountValue, (string)$currencyContext['currency_symbol'], (string)$currencyContext['currency_code']),
        'currency_id' => (int)$currencyContext['currency_id'],
        'currency_code' => (string)$currencyContext['currency_code'],
        'currency_symbol' => (string)$currencyContext['currency_symbol'],
        'bank_account_id' => (int)($bankAccount['bank_account_id'] ?? $bankAccount['id'] ?? 0),
        'bank_account_assignment_id' => (int)($bankAccount['bank_account_assignment_id'] ?? 0),
        'account_holder_name' => (string)($bankAccount['account_holder_name'] ?? ''),
        'bank_name' => (string)($bankAccount['bank_name'] ?? ''),
        'iban' => (string)($bankAccount['iban'] ?? ''),
        'swift_bic' => (string)($bankAccount['swift_bic'] ?? ''),
        'transfer_instructions' => (string)($bankAccount['transfer_instructions'] ?? ''),
        'payment_reference_template' => (string)($bankAccount['payment_reference_template'] ?? ''),
        'uses_existing_assignment' => $assignment ? 1 : 0,
    ];
    $preview['preview_html'] = admin_chat_payment_bank_preview_html($preview, $messages);

    return $preview;
}

function admin_bank_assignment_search_state(Mysql_ks $db, int $accountId, int $customerId): array
{
    $assignments = admin_customer_active_bank_assignments($db, $customerId);
    foreach ($assignments as $assignment) {
        if ((int)$assignment['bank_account_id'] === $accountId) {
            return [
                'disabled' => true,
                'hint' => 'Already assigned to this bank account.',
            ];
        }
    }

    if ($assignments) {
        $current = $assignments[0];
        $label = trim((string)($current['label'] ?? ''));
        if ($label === '') {
            $label = trim((string)($current['bank_name'] ?? ''));
        }

        return [
            'disabled' => false,
            'hint' => $label !== ''
                ? 'Current: ' . $label . '. Click to move.'
                : 'Already assigned to another bank account. Click to move.',
        ];
    }

    return [
        'disabled' => false,
        'hint' => 'Click to assign this user.',
    ];
}

function admin_crypto_assignment_search_state(Mysql_ks $db, int $walletId, int $customerId, array $settings): array
{
    $wallet = admin_crypto_wallet_find($db, $walletId);
    if (!is_array($wallet) || empty($wallet['id'])) {
        return [
            'disabled' => true,
            'hint' => 'Wallet not found.',
        ];
    }

    $assetCode = strtoupper(trim((string)($wallet['asset_code'] ?? '')));
    $sharedEnabled = admin_crypto_wallet_shared_assignments_enabled($settings);
    $assignments = admin_customer_active_crypto_assignments($db, $customerId, $assetCode);

    foreach ($assignments as $assignment) {
        if ((int)$assignment['wallet_address_id'] === $walletId) {
            return [
                'disabled' => true,
                'hint' => 'Already assigned to this wallet.',
            ];
        }
    }

    if ($assignments) {
        $current = $assignments[0];
        $label = trim((string)($current['label'] ?? ''));
        if ($label === '') {
            $label = admin_string_truncate((string)($current['address'] ?? ''), 18);
        }

        return [
            'disabled' => false,
            'hint' => $sharedEnabled
                ? ($label !== '' ? 'Also assigned: ' . $label : 'User already has another wallet for this coin.')
                : ($label !== '' ? 'Current: ' . $label . '. Click to move.' : 'User already has another wallet for this coin. Click to move.'),
        ];
    }

    return [
        'disabled' => false,
        'hint' => 'Click to assign this user.',
    ];
}

function admin_crypto_wallet_find(Mysql_ks $db, int $walletId): ?array
{
    if ($walletId <= 0 || !schema_object_exists($db, 'crypto_wallet_addresses')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT
            wallet.id,
            wallet.crypto_asset_id,
            wallet.label,
            wallet.owner_full_name,
            wallet.address,
            wallet.network_code,
            wallet.memo_tag,
            wallet.wallet_provider,
            wallet.status,
            wallet.notes,
            wallet.qr_url,
            wallet.is_reusable,
            wallet.disabled_at,
            wallet.updated_at,
            asset.code AS asset_code,
            asset.name AS asset_name,
            assignment.id AS active_assignment_id,
            assignment.customer_id AS assigned_customer_id,
            assignment.status AS assignment_status,
            assignment.assigned_at,
            customer.email AS assigned_customer_email
         FROM crypto_wallet_addresses AS wallet
         LEFT JOIN crypto_assets AS asset
           ON asset.id = wallet.crypto_asset_id
         LEFT JOIN crypto_wallet_assignments AS assignment
           ON assignment.id = (
                SELECT inner_assignment.id
                FROM crypto_wallet_assignments AS inner_assignment
                WHERE inner_assignment.wallet_address_id = wallet.id
                  AND inner_assignment.status IN ('reserved', 'active')
                ORDER BY inner_assignment.assigned_at DESC, inner_assignment.id DESC
                LIMIT 1
           )
         LEFT JOIN customers AS customer
           ON customer.id = assignment.customer_id
         WHERE wallet.id = {$walletId}
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_crypto_wallet_active_assignments(Mysql_ks $db, int $walletId): array
{
    if ($walletId <= 0 || !schema_object_exists($db, 'crypto_wallet_assignments')) {
        return [];
    }

    return $db->select_full_user(
        "SELECT
            crypto_wallet_assignments.id,
            crypto_wallet_assignments.customer_id,
            crypto_wallet_assignments.status,
            crypto_wallet_assignments.assigned_at,
            crypto_wallet_assignments.assignment_note,
            customers.email AS customer_email
         FROM crypto_wallet_assignments
         INNER JOIN customers ON customers.id = crypto_wallet_assignments.customer_id
         WHERE crypto_wallet_assignments.wallet_address_id = {$walletId}
           AND crypto_wallet_assignments.status IN ('reserved', 'active')
         ORDER BY crypto_wallet_assignments.assigned_at DESC, crypto_wallet_assignments.id DESC"
    );
}

function admin_crypto_wallet_status_options(): array
{
    return [
        'available' => 'available',
        'disabled' => 'disabled',
    ];
}

function admin_bank_account_status_options(): array
{
    return [
        'available' => 'available',
        'disabled' => 'disabled',
    ];
}

function admin_random_wallet_owner_name(): string
{
    $firstNames = [
        'Oliver', 'Liam', 'Noah', 'Ethan', 'Lucas', 'Mason', 'Henry', 'Leo',
        'Mia', 'Emma', 'Sofia', 'Luna', 'Amelia', 'Chloe', 'Ava', 'Nina',
    ];
    $lastNames = [
        'Bennett', 'Foster', 'Hayes', 'Carter', 'Walker', 'Coleman', 'Meyer', 'Fischer',
        'Nowak', 'Kowalski', 'Wagner', 'Hoffmann', 'Mercer', 'Abbott', 'Quinn', 'Brooks',
    ];

    return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
}

function admin_crypto_wallet_explorer_url(?string $assetCode, ?string $networkName, ?string $address): string
{
    $address = trim((string)$address);
    if ($address === '') {
        return '';
    }

    $assetCode = strtoupper(trim((string)$assetCode));
    $networkName = strtolower(trim((string)$networkName));

    if ($networkName === '') {
        $networkName = admin_normalize_crypto_wallet_network($assetCode, '', $address);
    }

    if ($networkName === 'bitcoin' || $assetCode === 'BTC') {
        return 'https://mempool.space/address/' . rawurlencode($address);
    }

    if ($networkName === 'bitcoin-cash' || $assetCode === 'BCH') {
        return 'https://blockchair.com/bitcoin-cash/address/' . rawurlencode($address);
    }

    if ($networkName === 'litecoin' || $assetCode === 'LTC') {
        return 'https://blockchair.com/litecoin/address/' . rawurlencode($address);
    }

    if ($networkName === 'dogecoin' || $assetCode === 'DOGE') {
        return 'https://blockchair.com/dogecoin/address/' . rawurlencode($address);
    }

    if ($networkName === 'ethereum') {
        return 'https://etherscan.io/address/' . rawurlencode($address);
    }

    if ($networkName === 'polygon') {
        return 'https://polygonscan.com/address/' . rawurlencode($address);
    }

    if ($networkName === 'bnb' || $assetCode === 'BNB') {
        return 'https://bscscan.com/address/' . rawurlencode($address);
    }

    if ($networkName === 'tron') {
        return 'https://tronscan.org/#/address/' . rawurlencode($address);
    }

    if ($networkName === 'cronos' || $assetCode === 'CRO') {
        return 'https://cronoscan.com/address/' . rawurlencode($address);
    }

    if ($networkName === 'solana' || $assetCode === 'SOL') {
        return 'https://solscan.io/account/' . rawurlencode($address);
    }

    if ($networkName === 'ripple') {
        return 'https://xrpscan.com/account/' . rawurlencode($address);
    }

    return 'https://blockchair.com/search?q=' . rawurlencode($address);
}

function admin_save_crypto_wallet(Mysql_ks $db, int $walletId, array $payload, int $adminUserId, string $ipAddress = ''): array
{
    $isCreate = $walletId <= 0;
    $wallet = $isCreate ? null : admin_crypto_wallet_find($db, $walletId);
    if (!$isCreate && (!is_array($wallet) || empty($wallet['id']))) {
        return ['ok' => false, 'message' => 'Wallet not found.'];
    }

    $cryptoAssetId = (int)($payload['crypto_asset_id'] ?? 0);
    $address = trim((string)($payload['address'] ?? ''));
    $label = trim((string)($payload['label'] ?? ''));
    $ownerFullName = trim((string)($payload['owner_full_name'] ?? ''));
    $memoTag = trim((string)($payload['memo_tag'] ?? ''));
    $walletProvider = trim((string)($payload['wallet_provider'] ?? ''));
    $networkCode = trim((string)($payload['network_code'] ?? ''));
    $notes = trim((string)($payload['notes'] ?? ''));
    $statusChoice = strtolower(trim((string)($payload['wallet_status'] ?? 'available')));

    $asset = $cryptoAssetId > 0 ? $db->select_user("SELECT id, code, is_active FROM crypto_assets WHERE id = {$cryptoAssetId} LIMIT 1") : null;
    if ($cryptoAssetId <= 0 || !is_array($asset)) {
        return ['ok' => false, 'message' => 'Invalid crypto asset.'];
    }
    if ($isCreate && empty($asset['is_active'])) {
        return ['ok' => false, 'message' => 'Choose an active cryptocurrency first.'];
    }

    if ($address === '') {
        return ['ok' => false, 'message' => 'Wallet address is required.'];
    }

    $existingAddress = $db->select_user(
        "SELECT id
         FROM crypto_wallet_addresses
         WHERE address = '" . $db->escape($address) . "'
           AND id <> {$walletId}
         LIMIT 1"
    );
    if (is_array($existingAddress) && !empty($existingAddress['id'])) {
        return ['ok' => false, 'message' => 'This wallet address already exists in the database.'];
    }

    $networkCode = admin_normalize_crypto_wallet_network((string)($asset['code'] ?? ''), $networkCode, $address);
    if ($networkCode === '') {
        return ['ok' => false, 'message' => 'Network is required for this wallet.'];
    }

    if ($ownerFullName === '') {
        $ownerFullName = admin_random_wallet_owner_name();
    }

    if (!in_array($statusChoice, array_keys(admin_crypto_wallet_status_options()), true)) {
        $statusChoice = 'available';
    }

    $activeAssignmentCount = $isCreate ? 0 : count(admin_crypto_wallet_active_assignments($db, $walletId));
    $finalWalletStatus = $activeAssignmentCount > 0 ? 'assigned' : ($statusChoice === 'disabled' ? 'disabled' : 'available');
    $disabledAtValue = $finalWalletStatus === 'disabled' ? $db->expr('NOW()') : null;

    $db->start();

    if ($isCreate) {
        $insertOk = $db->insert(
            ['crypto_asset_id', 'label', 'owner_full_name', 'address', 'network_code', 'memo_tag', 'wallet_provider', 'status', 'notes', 'disabled_at', 'created_by_admin_user_id'],
            [$cryptoAssetId, $label !== '' ? $label : null, $ownerFullName, $address, $networkCode, $memoTag !== '' ? $memoTag : null, $walletProvider !== '' ? $walletProvider : null, $finalWalletStatus, $notes !== '' ? $notes : null, $disabledAtValue, $adminUserId],
            'crypto_wallet_addresses'
        );

        if (!$insertOk || (int)$db->id() <= 0) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Unable to create wallet.'];
        }

        $walletId = (int)$db->id();
    } else {
        $updateOk = $db->update_using_id(
            ['crypto_asset_id', 'label', 'owner_full_name', 'address', 'network_code', 'memo_tag', 'wallet_provider', 'status', 'notes', 'disabled_at'],
            [$cryptoAssetId, $label !== '' ? $label : null, $ownerFullName, $address, $networkCode, $memoTag !== '' ? $memoTag : null, $walletProvider !== '' ? $walletProvider : null, $finalWalletStatus, $notes !== '' ? $notes : null, $disabledAtValue],
            'crypto_wallet_addresses',
            $walletId
        );

        if (!$updateOk) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Unable to update wallet.'];
        }
    }

    $db->commit();

    admin_activity_log(
        $db,
        0,
        $adminUserId,
        $isCreate ? 'crypto_wallet_created' : 'crypto_wallet_updated',
        'Crypto wallet #' . $walletId . ($isCreate ? ' created: ' : ' saved: ') . ($label !== '' ? $label : $address) . '.',
        $ipAddress
    );

    return ['ok' => true, 'message' => $isCreate ? 'Wallet created successfully.' : 'Wallet saved successfully.', 'wallet_id' => $walletId];
}

function admin_assign_crypto_wallet_customer(Mysql_ks $db, int $walletId, int $customerId, int $adminUserId, array $settings, string $ipAddress = ''): array
{
    $wallet = admin_crypto_wallet_find($db, $walletId);
    if (!is_array($wallet) || empty($wallet['id'])) {
        return ['ok' => false, 'message' => 'Wallet not found.'];
    }

    $customer = admin_customer_basic_row($db, $customerId);
    if (!is_array($customer) || empty($customer['id'])) {
        return ['ok' => false, 'message' => 'Customer not found.'];
    }

    if ((string)($wallet['status'] ?? '') === 'disabled') {
        return ['ok' => false, 'message' => 'Disabled wallet cannot be assigned.'];
    }

    $sharedEnabled = admin_crypto_wallet_shared_assignments_enabled($settings);
    $activeAssignments = admin_crypto_wallet_active_assignments($db, $walletId);
    $customerAssignments = admin_customer_active_crypto_assignments($db, $customerId, strtoupper(trim((string)($wallet['asset_code'] ?? ''))));
    $existingCustomerAssignmentId = 0;
    foreach ($customerAssignments as $assignment) {
        if ((int)$assignment['customer_id'] === $customerId) {
            if ((int)$assignment['wallet_address_id'] === $walletId) {
                $existingCustomerAssignmentId = (int)($assignment['wallet_assignment_id'] ?? 0);
                break;
            }
        }
    }

    if ($existingCustomerAssignmentId > 0 && ($sharedEnabled || count($customerAssignments) <= 1)) {
        return ['ok' => false, 'message' => 'This wallet is already assigned to the selected customer.'];
    }

    $db->start();

    if (!$sharedEnabled && $activeAssignments) {
        foreach ($activeAssignments as $assignment) {
            if ((int)$assignment['customer_id'] === $customerId) {
                continue;
            }

            $releaseOk = admin_release_crypto_wallet_assignment($db, (int)$assignment['id'], $adminUserId, 'Released from admin wallet editor');

            if (!$releaseOk) {
                $db->query('ROLLBACK');
                return ['ok' => false, 'message' => 'Unable to release current wallet assignment.'];
            }
        }
    }

    if (!$sharedEnabled && $customerAssignments) {
        foreach ($customerAssignments as $assignment) {
            $assignmentWalletId = (int)($assignment['wallet_address_id'] ?? 0);
            $assignmentId = (int)($assignment['wallet_assignment_id'] ?? 0);
            if ($assignmentWalletId === $walletId || $assignmentId <= 0) {
                continue;
            }

            $releaseOk = admin_release_crypto_wallet_assignment($db, $assignmentId, $adminUserId, 'Moved to a new wallet from admin wallet editor');
            if (!$releaseOk) {
                $db->query('ROLLBACK');
                return ['ok' => false, 'message' => 'Unable to release previous wallet assignment.'];
            }
        }
    }

    if ($existingCustomerAssignmentId <= 0) {
        $insertOk = $db->insert(
            ['wallet_address_id', 'customer_id', 'assignment_reason', 'status', 'assigned_by_admin_user_id', 'assignment_note'],
            [$walletId, $customerId, 'manual_customer_wallet', 'active', $adminUserId, 'Assigned from admin wallet editor'],
            'crypto_wallet_assignments'
        );

        if (!$insertOk) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Unable to assign wallet to the selected customer.'];
        }
    }

    $db->update_using_id(
        ['status', 'last_assigned_at', 'disabled_at'],
        ['assigned', $db->expr('NOW()'), null],
        'crypto_wallet_addresses',
        $walletId
    );

    $db->commit();

    admin_log_customer_and_admin(
        $db,
        $customerId,
        $adminUserId,
        'crypto_wallet_assignment_updated',
        'Crypto wallet #' . $walletId . ' assigned to customer #' . $customerId . '.',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Wallet assignment updated.'];
}

function admin_remove_crypto_wallet_assignment(Mysql_ks $db, int $walletId, int $assignmentId, int $adminUserId, string $ipAddress = ''): array
{
    $assignment = $db->select_user(
        "SELECT id, customer_id
         FROM crypto_wallet_assignments
         WHERE id = {$assignmentId}
           AND wallet_address_id = {$walletId}
           AND status IN ('reserved', 'active')
         LIMIT 1"
    );

    if (!is_array($assignment) || empty($assignment['id'])) {
        return ['ok' => false, 'message' => 'Assignment not found.'];
    }

    $db->start();

    $releaseOk = admin_release_crypto_wallet_assignment(
        $db,
        $assignmentId,
        $adminUserId,
        'Removed from admin wallet editor'
    );

    if (!$releaseOk) {
        $db->query('ROLLBACK');
        return ['ok' => false, 'message' => 'Unable to remove wallet assignment.'];
    }

    $remainingAssignments = admin_crypto_wallet_active_assignments($db, $walletId);
    $walletStatus = $remainingAssignments ? 'assigned' : 'available';
    $db->update_using_id(
        ['status', 'disabled_at'],
        [$walletStatus, $walletStatus === 'available' ? null : null],
        'crypto_wallet_addresses',
        $walletId
    );

    $db->commit();

    admin_log_customer_and_admin(
        $db,
        (int)($assignment['customer_id'] ?? 0),
        $adminUserId,
        'crypto_wallet_assignment_removed',
        'Crypto wallet assignment #' . $assignmentId . ' removed from wallet #' . $walletId . '.',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Wallet assignment removed.'];
}

function admin_delete_crypto_wallet(Mysql_ks $db, int $walletId, int $adminUserId, string $ipAddress = ''): array
{
    $wallet = admin_crypto_wallet_find($db, $walletId);
    if (!is_array($wallet) || empty($wallet['id'])) {
        return ['ok' => false, 'message' => 'Wallet not found.'];
    }

    $summary = admin_crypto_wallet_delete_summary($db, $walletId);
    if (empty($summary['can_delete'])) {
        return ['ok' => false, 'message' => 'This wallet has assignment or payment history and cannot be deleted.'];
    }

    $deleted = $db->delete_using_id('crypto_wallet_addresses', $walletId);
    if (!$deleted) {
        return ['ok' => false, 'message' => 'Unable to delete wallet.'];
    }

    $walletLabel = trim((string)($wallet['label'] ?? ''));
    $walletAddress = trim((string)($wallet['address'] ?? ''));
    admin_activity_log(
        $db,
        0,
        $adminUserId,
        'crypto_wallet_deleted',
        'Crypto wallet #' . $walletId . ' deleted: ' . ($walletLabel !== '' ? $walletLabel : $walletAddress) . '.',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Wallet deleted successfully.'];
}

function admin_release_crypto_wallet_assignment(Mysql_ks $db, int $assignmentId, int $adminUserId, string $note): bool
{
    $safeNote = $db->escape($note);

    return (bool)$db->query(
        "UPDATE crypto_wallet_assignments
         SET status = 'released',
             released_at = NOW(),
             released_by_admin_user_id = {$adminUserId},
             assignment_note = CONCAT(COALESCE(assignment_note, ''), '\n{$safeNote}')
         WHERE id = {$assignmentId}"
    );
}

function admin_bank_account_find(Mysql_ks $db, int $accountId): ?array
{
    if ($accountId <= 0 || !schema_object_exists($db, 'bank_accounts')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT
            bank_accounts.*,
            currencies.code AS currency_code
         FROM bank_accounts
         LEFT JOIN currencies ON currencies.id = bank_accounts.currency_id
         WHERE bank_accounts.id = {$accountId}
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_bank_account_active_assignments(Mysql_ks $db, int $accountId): array
{
    if ($accountId <= 0 || !schema_object_exists($db, 'bank_account_assignments')) {
        return [];
    }

    return $db->select_full_user(
        "SELECT
            bank_account_assignments.id,
            bank_account_assignments.customer_id,
            bank_account_assignments.status,
            bank_account_assignments.assigned_at,
            bank_account_assignments.assignment_note,
            customers.email AS customer_email
         FROM bank_account_assignments
         INNER JOIN customers ON customers.id = bank_account_assignments.customer_id
         WHERE bank_account_assignments.bank_account_id = {$accountId}
           AND bank_account_assignments.status IN ('reserved', 'active')
         ORDER BY bank_account_assignments.assigned_at DESC, bank_account_assignments.id DESC"
    );
}

function admin_save_bank_account(Mysql_ks $db, int $accountId, array $payload, int $adminUserId, string $ipAddress = ''): array
{
    $account = admin_bank_account_find($db, $accountId);
    if (!is_array($account) || empty($account['id'])) {
        return ['ok' => false, 'message' => 'Bank account not found.'];
    }

    $label = trim((string)($payload['label'] ?? ''));
    $accountHolderName = trim((string)($payload['account_holder_name'] ?? ''));
    $bankName = trim((string)($payload['bank_name'] ?? ''));
    $bankAddress = trim((string)($payload['bank_address'] ?? ''));
    $countryCode = strtoupper(trim((string)($payload['country_code'] ?? '')));
    $iban = trim((string)($payload['iban'] ?? ''));
    $accountNumber = trim((string)($payload['account_number'] ?? ''));
    $routingNumber = trim((string)($payload['routing_number'] ?? ''));
    $swiftBic = strtoupper(trim((string)($payload['swift_bic'] ?? '')));
    $transferInstructions = trim((string)($payload['transfer_instructions'] ?? ''));
    $paymentReferenceTemplate = trim((string)($payload['payment_reference_template'] ?? ''));
    $notes = trim((string)($payload['notes'] ?? ''));
    $statusChoice = strtolower(trim((string)($payload['bank_account_status'] ?? 'available')));

    if ($accountHolderName === '') {
        return ['ok' => false, 'message' => 'Account holder name is required.'];
    }

    if ($bankName === '') {
        return ['ok' => false, 'message' => 'Bank name is required.'];
    }

    if ($iban === '' && $accountNumber === '') {
        return ['ok' => false, 'message' => 'IBAN or account number is required.'];
    }

    if (!in_array($statusChoice, array_keys(admin_bank_account_status_options()), true)) {
        $statusChoice = 'available';
    }

    $activeAssignmentCount = count(admin_bank_account_active_assignments($db, $accountId));
    $finalStatus = $activeAssignmentCount > 0 ? 'assigned' : ($statusChoice === 'disabled' ? 'disabled' : 'available');
    $disabledAtValue = $finalStatus === 'disabled' ? $db->expr('NOW()') : null;

    $updateOk = $db->update_using_id(
        [
            'label',
            'account_holder_name',
            'bank_name',
            'bank_address',
            'country_code',
            'iban',
            'account_number',
            'routing_number',
            'swift_bic',
            'payment_reference_template',
            'transfer_instructions',
            'status',
            'notes',
            'disabled_at',
        ],
        [
            $label !== '' ? $label : null,
            $accountHolderName,
            $bankName,
            $bankAddress !== '' ? $bankAddress : null,
            $countryCode !== '' ? $countryCode : null,
            $iban !== '' ? $iban : null,
            $accountNumber !== '' ? $accountNumber : null,
            $routingNumber !== '' ? $routingNumber : null,
            $swiftBic !== '' ? $swiftBic : null,
            $paymentReferenceTemplate !== '' ? $paymentReferenceTemplate : null,
            $transferInstructions !== '' ? $transferInstructions : null,
            $finalStatus,
            $notes !== '' ? $notes : null,
            $disabledAtValue,
        ],
        'bank_accounts',
        $accountId
    );

    if (!$updateOk) {
        return ['ok' => false, 'message' => 'Unable to save bank account.'];
    }

    admin_activity_log(
        $db,
        0,
        $adminUserId,
        'bank_account_updated',
        'Bank account #' . $accountId . ' saved: ' . ($label !== '' ? $label : $bankName) . '.',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Bank account saved successfully.'];
}

function admin_assign_bank_account_customer(Mysql_ks $db, int $accountId, int $customerId, int $adminUserId, array $settings, string $ipAddress = ''): array
{
    $account = admin_bank_account_find($db, $accountId);
    if (!is_array($account) || empty($account['id'])) {
        return ['ok' => false, 'message' => 'Bank account not found.'];
    }

    $customer = admin_customer_basic_row($db, $customerId);
    if (!is_array($customer) || empty($customer['id'])) {
        return ['ok' => false, 'message' => 'Customer not found.'];
    }

    if ((string)($account['status'] ?? '') === 'disabled') {
        return ['ok' => false, 'message' => 'Disabled bank account cannot be assigned.'];
    }

    $sharedEnabled = admin_bank_account_shared_assignments_enabled($settings);
    $activeAssignments = admin_bank_account_active_assignments($db, $accountId);
    $customerAssignments = admin_customer_active_bank_assignments($db, $customerId);
    $existingCustomerAssignmentId = 0;
    foreach ($customerAssignments as $assignment) {
        if ((int)$assignment['customer_id'] === $customerId) {
            if ((int)$assignment['bank_account_id'] === $accountId) {
                $existingCustomerAssignmentId = (int)($assignment['bank_account_assignment_id'] ?? 0);
                break;
            }
        }
    }

    if ($existingCustomerAssignmentId > 0 && ($sharedEnabled || count($customerAssignments) <= 1)) {
        return ['ok' => false, 'message' => 'This bank account is already assigned to the selected customer.'];
    }

    $db->start();

    if (!$sharedEnabled && $activeAssignments) {
        foreach ($activeAssignments as $assignment) {
            if ((int)$assignment['customer_id'] === $customerId) {
                continue;
            }

            $releaseOk = admin_release_bank_account_assignment($db, (int)$assignment['id'], $adminUserId, 'Released from admin bank account editor');

            if (!$releaseOk) {
                $db->query('ROLLBACK');
                return ['ok' => false, 'message' => 'Unable to release current bank account assignment.'];
            }
        }
    }

    if ($customerAssignments) {
        foreach ($customerAssignments as $assignment) {
            $assignmentAccountId = (int)($assignment['bank_account_id'] ?? 0);
            $assignmentId = (int)($assignment['bank_account_assignment_id'] ?? 0);
            if ($assignmentAccountId === $accountId || $assignmentId <= 0) {
                continue;
            }

            $releaseOk = admin_release_bank_account_assignment($db, $assignmentId, $adminUserId, 'Moved to a new bank account from admin editor');
            if (!$releaseOk) {
                $db->query('ROLLBACK');
                return ['ok' => false, 'message' => 'Unable to release previous bank account assignment.'];
            }
        }
    }

    if ($existingCustomerAssignmentId <= 0) {
        $insertOk = $db->insert(
            ['bank_account_id', 'customer_id', 'assignment_reason', 'status', 'assigned_by_admin_user_id', 'assignment_note'],
            [$accountId, $customerId, 'manual_bank_account', 'active', $adminUserId, 'Assigned from admin bank account editor'],
            'bank_account_assignments'
        );

        if (!$insertOk) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Unable to assign bank account to the selected customer.'];
        }
    }

    $db->update_using_id(
        ['status', 'last_assigned_at', 'disabled_at'],
        ['assigned', $db->expr('NOW()'), null],
        'bank_accounts',
        $accountId
    );

    $db->commit();

    admin_log_customer_and_admin(
        $db,
        $customerId,
        $adminUserId,
        'bank_account_assignment_updated',
        'Bank account #' . $accountId . ' assigned to customer #' . $customerId . '.',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Bank account assignment updated.'];
}

function admin_remove_bank_account_assignment(Mysql_ks $db, int $accountId, int $assignmentId, int $adminUserId, string $ipAddress = ''): array
{
    $assignment = $db->select_user(
        "SELECT id, customer_id
         FROM bank_account_assignments
         WHERE id = {$assignmentId}
           AND bank_account_id = {$accountId}
           AND status IN ('reserved', 'active')
         LIMIT 1"
    );

    if (!is_array($assignment) || empty($assignment['id'])) {
        return ['ok' => false, 'message' => 'Assignment not found.'];
    }

    $db->start();

    $releaseOk = admin_release_bank_account_assignment(
        $db,
        $assignmentId,
        $adminUserId,
        'Removed from admin bank account editor'
    );

    if (!$releaseOk) {
        $db->query('ROLLBACK');
        return ['ok' => false, 'message' => 'Unable to remove bank account assignment.'];
    }

    $remainingAssignments = admin_bank_account_active_assignments($db, $accountId);
    $accountStatus = $remainingAssignments ? 'assigned' : 'available';
    $db->update_using_id(
        ['status', 'disabled_at'],
        [$accountStatus, null],
        'bank_accounts',
        $accountId
    );

    $db->commit();

    admin_log_customer_and_admin(
        $db,
        (int)($assignment['customer_id'] ?? 0),
        $adminUserId,
        'bank_account_assignment_removed',
        'Bank account assignment #' . $assignmentId . ' removed from account #' . $accountId . '.',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Bank account assignment removed.'];
}

function admin_delete_bank_account(Mysql_ks $db, int $accountId, int $adminUserId, string $ipAddress = ''): array
{
    $account = admin_bank_account_find($db, $accountId);
    if (!is_array($account) || empty($account['id'])) {
        return ['ok' => false, 'message' => 'Bank account not found.'];
    }

    $activeAssignments = admin_bank_account_active_assignments($db, $accountId);
    if ($activeAssignments) {
        return ['ok' => false, 'message' => 'Release assigned users before deleting this bank account.'];
    }

    if (schema_object_exists($db, 'bank_account_assignments')) {
        $assignmentRow = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM bank_account_assignments
             WHERE bank_account_id = {$accountId}"
        );
        if ((int)($assignmentRow['total'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'This bank account has assignment history and cannot be deleted.'];
        }
    }

    if (schema_object_exists($db, 'bank_transfer_requests')) {
        $paymentRow = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM bank_transfer_requests
             WHERE bank_account_id = {$accountId}"
        );
        if ((int)($paymentRow['total'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'This bank account has payment history and cannot be deleted.'];
        }
    }

    $deleted = $db->delete_using_id('bank_accounts', $accountId);
    if (!$deleted) {
        return ['ok' => false, 'message' => 'Unable to delete bank account.'];
    }

    $bankLabel = trim((string)($account['label'] ?? ''));
    $bankName = trim((string)($account['bank_name'] ?? ''));
    admin_activity_log(
        $db,
        0,
        $adminUserId,
        'bank_account_deleted',
        'Bank account #' . $accountId . ' deleted: ' . ($bankLabel !== '' ? $bankLabel : $bankName) . '.',
        $ipAddress
    );

    return ['ok' => true, 'message' => 'Bank account deleted successfully.'];
}

function admin_release_bank_account_assignment(Mysql_ks $db, int $assignmentId, int $adminUserId, string $note): bool
{
    $safeNote = $db->escape($note);

    return (bool)$db->query(
        "UPDATE bank_account_assignments
         SET status = 'released',
             released_at = NOW(),
             released_by_admin_user_id = {$adminUserId},
             assignment_note = CONCAT(COALESCE(assignment_note, ''), '\n{$safeNote}')
         WHERE id = {$assignmentId}"
    );
}

function admin_news_count(Mysql_ks $db): int
{
    if (!schema_object_exists($db, 'news_posts')) {
        return 0;
    }

    $row = $db->select_user("SELECT COUNT(*) AS total FROM news_posts");
    return (int)($row['total'] ?? 0);
}

function admin_news_rows(Mysql_ks $db, int $limit = 12, int $offset = 0): array
{
    if (!schema_object_exists($db, 'news_posts')) {
        return [];
    }

    app_ensure_news_runtime_columns($db);
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $authorSelect = schema_column_exists($db, 'news_posts', 'created_by_admin_user_id') && schema_object_exists($db, 'admin_users')
        ? app_admin_display_name_sql($db, 'admin_users') . ' AS author_handle'
        : "'' AS author_handle";
    $authorJoin = schema_column_exists($db, 'news_posts', 'created_by_admin_user_id') && schema_object_exists($db, 'admin_users')
        ? ' LEFT JOIN admin_users ON admin_users.id = news_posts.created_by_admin_user_id'
        : '';

    return $db->select_full_user(
        "SELECT news_posts.id, news_posts.title, news_posts.slug, news_posts.body, news_posts.visibility, news_posts.is_active, news_posts.published_at, news_posts.created_at, news_posts.updated_at, {$authorSelect}
         FROM news_posts{$authorJoin}
         ORDER BY news_posts.published_at DESC, news_posts.id DESC
         LIMIT {$offset}, {$limit}"
    );
}

function admin_news_find(Mysql_ks $db, int $newsId): ?array
{
    if ($newsId <= 0 || !schema_object_exists($db, 'news_posts')) {
        return null;
    }

    app_ensure_news_runtime_columns($db);
    $authorSelect = schema_column_exists($db, 'news_posts', 'created_by_admin_user_id') && schema_object_exists($db, 'admin_users')
        ? app_admin_display_name_sql($db, 'admin_users') . ' AS author_handle'
        : "'' AS author_handle";
    $authorJoin = schema_column_exists($db, 'news_posts', 'created_by_admin_user_id') && schema_object_exists($db, 'admin_users')
        ? ' LEFT JOIN admin_users ON admin_users.id = news_posts.created_by_admin_user_id'
        : '';

    $row = $db->select_user(
        "SELECT news_posts.id, news_posts.title, news_posts.slug, news_posts.body, news_posts.visibility, news_posts.is_active, news_posts.published_at, news_posts.created_at, news_posts.updated_at, {$authorSelect}
         FROM news_posts{$authorJoin}
         WHERE news_posts.id = {$newsId}
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_news_visibility_options(string $current = ''): array
{
    $options = ['client', 'reseller', 'public', 'admin'];
    $current = strtolower(trim($current));
    if ($current === 'customer') {
        $current = 'client';
    }
    if ($current !== '' && !in_array($current, $options, true)) {
        $options[] = $current;
    }

    return $options;
}

function admin_news_visibility_label(string $visibility, array $messages): string
{
    $visibility = strtolower(trim($visibility));
    if ($visibility === '') {
        return admin_t($messages, 'news_visibility_client', 'Client');
    }

    if ($visibility === 'customer') {
        $visibility = 'client';
    }

    return admin_t(
        $messages,
        'news_visibility_' . preg_replace('/[^a-z0-9_]+/', '_', $visibility),
        ucfirst(str_replace('_', ' ', $visibility))
    );
}

function admin_news_body_preview(string $body, int $limit = 160): string
{
    $body = trim(strip_tags($body));
    if ($body === '') {
        return '';
    }

    $body = preg_replace('/\s+/u', ' ', $body) ?? $body;
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($body) <= $limit) {
            return $body;
        }

        return rtrim(mb_substr($body, 0, max(1, $limit - 3))) . '...';
    }

    if (strlen($body) <= $limit) {
        return $body;
    }

    return rtrim(substr($body, 0, max(1, $limit - 3))) . '...';
}

function admin_news_slugify(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'news';
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
    $value = trim($value, '-');

    return $value !== '' ? $value : 'news';
}

function admin_news_unique_slug(Mysql_ks $db, string $title, string $requestedSlug = '', int $excludeId = 0): string
{
    $baseSlug = admin_news_slugify($requestedSlug !== '' ? $requestedSlug : $title);
    $candidate = $baseSlug;
    $suffix = 2;

    while (true) {
        $safeSlug = $db->escape($candidate);
        $excludeSql = $excludeId > 0 ? " AND id != {$excludeId}" : '';
        $row = $db->select_user(
            "SELECT id
             FROM news_posts
             WHERE slug = '{$safeSlug}'{$excludeSql}
             LIMIT 1"
        );

        if (!is_array($row) || empty($row['id'])) {
            return $candidate;
        }

        $candidate = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function admin_create_news(Mysql_ks $db, array $input): array
{
    if (!schema_object_exists($db, 'news_posts')) {
        return ['ok' => false, 'message' => 'News storage is not available.'];
    }

    app_ensure_news_runtime_columns($db);
    $title = trim((string)($input['title'] ?? ''));
    $slugInput = trim((string)($input['slug'] ?? ''));
    $body = trim((string)($input['body'] ?? ''));
    $visibility = strtolower(trim((string)($input['visibility'] ?? 'client')));
    $publishedAt = admin_normalize_datetime_input($input['published_at'] ?? null) ?? date('Y-m-d H:i:s');
    $isActive = isset($input['is_active']) && (string)$input['is_active'] === '1' ? 1 : 0;

    if ($title === '') {
        return ['ok' => false, 'message' => 'News title is required.'];
    }

    if ($body === '') {
        return ['ok' => false, 'message' => 'News content is required.'];
    }

    if ($visibility === 'customer') {
        $visibility = 'client';
    }

    if (!in_array($visibility, admin_news_visibility_options(), true)) {
        $visibility = 'client';
    }

    $slug = admin_news_unique_slug($db, $title, $slugInput);

    $insertFields = ['title', 'slug', 'body', 'visibility', 'is_active', 'published_at'];
    $insertValues = [$title, $slug, $body, $visibility, $isActive, $publishedAt];

    if (schema_column_exists($db, 'news_posts', 'created_by_admin_user_id')) {
        $insertFields[] = 'created_by_admin_user_id';
        $insertValues[] = isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : null;
    }

    $inserted = $db->insert($insertFields, $insertValues, 'news_posts');

    if (!$inserted) {
        return ['ok' => false, 'message' => 'Unable to create news post.'];
    }

    $newsId = (int)$db->id();
    if ($newsId > 0) {
        app_queue_news_broadcast($db, $newsId);
    }

    return [
        'ok' => true,
        'message' => 'News post created successfully.',
        'news_id' => $newsId,
    ];
}

function admin_save_news(Mysql_ks $db, int $newsId, array $input): array
{
    app_ensure_news_runtime_columns($db);
    $news = admin_news_find($db, $newsId);
    if (!is_array($news) || empty($news['id'])) {
        return ['ok' => false, 'message' => 'News post not found.'];
    }

    $title = trim((string)($input['title'] ?? ''));
    $slugInput = trim((string)($input['slug'] ?? ''));
    $body = trim((string)($input['body'] ?? ''));
    $visibility = strtolower(trim((string)($input['visibility'] ?? 'client')));
    $publishedAt = admin_normalize_datetime_input($input['published_at'] ?? null) ?? (string)($news['published_at'] ?? date('Y-m-d H:i:s'));
    $isActive = isset($input['is_active']) && (string)$input['is_active'] === '1' ? 1 : 0;

    if ($title === '') {
        return ['ok' => false, 'message' => 'News title is required.'];
    }

    if ($body === '') {
        return ['ok' => false, 'message' => 'News content is required.'];
    }

    if ($visibility === 'customer') {
        $visibility = 'client';
    }

    if (!in_array($visibility, admin_news_visibility_options((string)($news['visibility'] ?? '')), true)) {
        $visibility = 'client';
    }

    $slug = admin_news_unique_slug($db, $title, $slugInput, $newsId);

    $updated = $db->update_using_id(
        ['title', 'slug', 'body', 'visibility', 'is_active', 'published_at'],
        [$title, $slug, $body, $visibility, $isActive, $publishedAt],
        'news_posts',
        $newsId
    );

    return [
        'ok' => (bool)$updated,
        'message' => $updated ? 'News post saved successfully.' : 'Unable to save news post.',
    ];
}

function admin_delete_news(Mysql_ks $db, int $newsId): array
{
    $news = admin_news_find($db, $newsId);
    if (!is_array($news) || empty($news['id'])) {
        return ['ok' => false, 'message' => 'News post not found.'];
    }

    $deleted = $db->delete_using_id('news_posts', $newsId);

    return [
        'ok' => (bool)$deleted,
        'message' => $deleted ? 'News post deleted successfully.' : 'Unable to delete news post.',
    ];
}

function admin_live_chat_rows(Mysql_ks $db, int $limit = 12): array
{
    if (!schema_object_exists($db, 'support_conversations')) {
        return [];
    }

    $limit = max(1, min(30, $limit));
    return $db->select_full_user(
        "SELECT
            support_conversations.id,
            support_conversations.subject,
            support_conversations.status,
            support_conversations.created_at,
            customers.email AS customer_email
         FROM support_conversations
         LEFT JOIN customers ON customers.id = support_conversations.customer_id
         WHERE support_conversations.conversation_type = 'live_chat'
         ORDER BY support_conversations.id DESC
         LIMIT {$limit}"
    );
}

function admin_chat_inbox_rows(Mysql_ks $db, int $limit = 12, int $adminUserId = 0): array
{
    if (!schema_object_exists($db, 'support_conversations') || !schema_object_exists($db, 'support_messages')) {
        return [];
    }

    $limit = max(1, min(20, $limit));

    $rows = $db->select_full_user(
        "SELECT
            support_conversations.id,
            support_conversations.customer_id,
            support_conversations.subject,
            support_conversations.status,
            support_conversations.created_at,
            support_conversations.updated_at,
            support_conversations.last_customer_message_at,
            support_conversations.last_admin_message_at,
            customers.email AS customer_email,
            customers.last_login_at AS customer_last_login_at,
            (
                SELECT support_messages.message_body
                FROM support_messages
                WHERE support_messages.conversation_id = support_conversations.id
                ORDER BY support_messages.id DESC
                LIMIT 1
            ) AS last_message_body,
            (
                SELECT support_messages.attachment_path
                FROM support_messages
                WHERE support_messages.conversation_id = support_conversations.id
                ORDER BY support_messages.id DESC
                LIMIT 1
            ) AS last_attachment_path,
            (
                SELECT COUNT(*)
                FROM support_messages
                WHERE support_messages.conversation_id = support_conversations.id
                  AND support_messages.sender_type = 'customer'
                  AND support_messages.is_read = 0
            ) AS unread_count
         FROM support_conversations
         LEFT JOIN customers ON customers.id = support_conversations.customer_id
         WHERE support_conversations.conversation_type = 'live_chat'
         ORDER BY COALESCE(
            support_conversations.last_customer_message_at,
            support_conversations.last_admin_message_at,
            support_conversations.updated_at,
            support_conversations.created_at
         ) DESC, support_conversations.id DESC
         LIMIT {$limit}"
    );

    if ($adminUserId > 0) {
        $rows = array_merge($rows, chat_admin_group_conversation_rows($db, $adminUserId));
    }

    usort($rows, static function (array $left, array $right): int {
        $leftTime = strtotime((string)($left['updated_at'] ?? $left['created_at'] ?? '')) ?: 0;
        $rightTime = strtotime((string)($right['updated_at'] ?? $right['created_at'] ?? '')) ?: 0;
        if ($leftTime === $rightTime) {
            return (int)($right['id'] ?? 0) <=> (int)($left['id'] ?? 0);
        }
        return $rightTime <=> $leftTime;
    });

    if (count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
    }

    return $rows;
}

function admin_chat_inbox_unread_count(array $rows): int
{
    $total = 0;
    foreach ($rows as $row) {
        $total += (int)($row['unread_count'] ?? 0);
    }

    return $total;
}

function admin_ensure_chat_quick_replies_runtime_table(Mysql_ks $db): void
{
    static $done = false;
    if ($done) {
        return;
    }

    if (!schema_object_exists($db, 'support_quick_replies')) {
        $db->query(
            "CREATE TABLE IF NOT EXISTS support_quick_replies (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                locale_code VARCHAR(8) NOT NULL DEFAULT 'en',
                title VARCHAR(191) NOT NULL,
                message_body TEXT NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 100,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_support_quick_replies_locale_title (locale_code, title),
                KEY idx_support_quick_replies_active_sort (is_active, locale_code, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        unset($GLOBALS['schema_object_exists_cache']['support_quick_replies']);
    }

    if (schema_object_exists($db, 'support_quick_replies') && !schema_column_exists($db, 'support_quick_replies', 'locale_code')) {
        $db->query("ALTER TABLE support_quick_replies ADD COLUMN locale_code VARCHAR(8) NOT NULL DEFAULT 'en' AFTER id");
        schema_forget_column_cache('support_quick_replies', 'locale_code');
    }

    if (schema_object_exists($db, 'support_quick_replies')) {
        $seedRows = [
            ['locale_code' => 'en', 'title' => 'Payment instructions', 'message_body' => "Please open the payment page in your account and follow the instructions shown there.\n\nIf you send a bank transfer or crypto payment from an external wallet or app, make sure the full amount reaches our account or wallet.", 'sort_order' => 10],
            ['locale_code' => 'pl', 'title' => 'Instrukcja płatności', 'message_body' => "Otwórz proszę stronę płatności w swoim koncie i postępuj zgodnie z widoczną instrukcją.\n\nJeśli wysyłasz przelew lub krypto z zewnętrznego portfela albo aplikacji, upewnij się, że pełna kwota dotrze do naszego konta lub portfela.", 'sort_order' => 10],
            ['locale_code' => 'en', 'title' => 'Restart app / device', 'message_body' => "Please restart the app and your device first.\n\nThen check if the subscription is active in your account and try again.", 'sort_order' => 20],
            ['locale_code' => 'pl', 'title' => 'Restart aplikacji / urządzenia', 'message_body' => "Najpierw zrestartuj proszę aplikację i urządzenie.\n\nNastępnie sprawdź, czy subskrypcja jest aktywna na koncie, i spróbuj ponownie.", 'sort_order' => 20],
            ['locale_code' => 'en', 'title' => 'Send payment confirmation', 'message_body' => "Please send the payment confirmation in this chat.\n\nAfter verification we will update the order status in your account.", 'sort_order' => 30],
            ['locale_code' => 'pl', 'title' => 'Wyślij potwierdzenie płatności', 'message_body' => "Wyślij proszę potwierdzenie płatności w tym czacie.\n\nPo weryfikacji zaktualizujemy status zamówienia na Twoim koncie.", 'sort_order' => 30],
            ['locale_code' => 'en', 'title' => 'Need app help', 'message_body' => "If you need help with setup, open the Apps page in your account and choose the app for your device.\n\nIf you still need support, write which app and device you use.", 'sort_order' => 40],
            ['locale_code' => 'pl', 'title' => 'Pomoc z aplikacją', 'message_body' => "Jeśli potrzebujesz pomocy z konfiguracją, otwórz podstronę Apps na swoim koncie i wybierz aplikację dla swojego urządzenia.\n\nJeśli nadal potrzebujesz wsparcia, napisz z jakiej aplikacji i urządzenia korzystasz.", 'sort_order' => 40],
        ];

        foreach ($seedRows as $seedRow) {
            $safeTitle = $db->escape((string)$seedRow['title']);
            $safeLocaleCode = $db->escape((string)$seedRow['locale_code']);
            $existing = $db->select_user(
                "SELECT id
                 FROM support_quick_replies
                 WHERE title = '{$safeTitle}'
                   AND locale_code = '{$safeLocaleCode}'
                 LIMIT 1"
            );
            if (is_array($existing) && !empty($existing['id'])) {
                continue;
            }

            $db->insert(
                ['locale_code', 'title', 'message_body', 'is_active', 'sort_order'],
                [(string)$seedRow['locale_code'], (string)$seedRow['title'], (string)$seedRow['message_body'], 1, (int)$seedRow['sort_order']],
                'support_quick_replies'
            );
        }
    }

    $done = true;
}

function admin_chat_quick_reply_rows(Mysql_ks $db, bool $onlyActive = false): array
{
    admin_ensure_chat_quick_replies_runtime_table($db);
    if (!schema_object_exists($db, 'support_quick_replies')) {
        return [];
    }

    $where = $onlyActive ? 'WHERE is_active = 1' : '';
    return $db->select_full_user(
        "SELECT
            id,
            locale_code,
            title,
            message_body,
            is_active,
            sort_order,
            created_at,
            updated_at
         FROM support_quick_replies
         {$where}
         ORDER BY is_active DESC, locale_code ASC, sort_order ASC, id ASC"
    );
}

function admin_chat_quick_reply_rows_for_locale(Mysql_ks $db, string $localeCode = '', bool $onlyActive = true): array
{
    $rows = admin_chat_quick_reply_rows($db, $onlyActive);
    if ($localeCode === '') {
        return $rows;
    }

    $localeCode = admin_normalize_locale($localeCode);
    $exactRows = array_values(array_filter($rows, static function (array $row) use ($localeCode): bool {
        return admin_normalize_locale((string)($row['locale_code'] ?? '')) === $localeCode;
    }));
    if ($exactRows !== []) {
        return $exactRows;
    }

    if ($localeCode !== 'en') {
        $fallbackRows = array_values(array_filter($rows, static function (array $row): bool {
            return admin_normalize_locale((string)($row['locale_code'] ?? '')) === 'en';
        }));
        if ($fallbackRows !== []) {
            return $fallbackRows;
        }
    }

    return $rows;
}

function admin_chat_quick_reply_find(Mysql_ks $db, int $replyId): ?array
{
    admin_ensure_chat_quick_replies_runtime_table($db);
    if ($replyId <= 0 || !schema_object_exists($db, 'support_quick_replies')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT
            id,
            locale_code,
            title,
            message_body,
            is_active,
            sort_order,
            created_at,
            updated_at
         FROM support_quick_replies
         WHERE id = {$replyId}
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_chat_quick_reply_locale_options(string $current = ''): array
{
    $options = ['pl', 'en'];
    $current = admin_normalize_locale($current !== '' ? $current : 'en');
    if (!in_array($current, $options, true)) {
        $options[] = $current;
    }

    return $options;
}

function admin_create_chat_quick_reply(Mysql_ks $db, array $input): array
{
    admin_ensure_chat_quick_replies_runtime_table($db);
    if (!schema_object_exists($db, 'support_quick_replies')) {
        return ['ok' => false, 'message' => 'Quick reply storage is unavailable.'];
    }

    $title = trim((string)($input['title'] ?? ''));
    $messageBody = trim((string)($input['message_body'] ?? ''));
    $localeCode = admin_normalize_locale((string)($input['locale_code'] ?? 'en'));
    $sortOrder = (int)($input['sort_order'] ?? 100);
    $isActive = isset($input['is_active']) && (string)($input['is_active'] ?? '') === '1' ? 1 : 0;

    if ($title === '') {
        return ['ok' => false, 'message' => 'Quick reply title is required.'];
    }

    if ($messageBody === '') {
        return ['ok' => false, 'message' => 'Quick reply message is required.'];
    }

    $inserted = $db->insert(
        ['locale_code', 'title', 'message_body', 'is_active', 'sort_order'],
        [$localeCode, $title, $messageBody, $isActive, $sortOrder],
        'support_quick_replies'
    );

    return [
        'ok' => (bool)$inserted,
        'message' => $inserted ? 'Quick reply created successfully.' : 'Unable to create quick reply.',
        'reply_id' => (int)$db->id(),
    ];
}

function admin_save_chat_quick_reply(Mysql_ks $db, int $replyId, array $input): array
{
    $reply = admin_chat_quick_reply_find($db, $replyId);
    if (!is_array($reply) || empty($reply['id'])) {
        return ['ok' => false, 'message' => 'Quick reply not found.'];
    }

    $title = trim((string)($input['title'] ?? ''));
    $messageBody = trim((string)($input['message_body'] ?? ''));
    $localeCode = admin_normalize_locale((string)($input['locale_code'] ?? (string)($reply['locale_code'] ?? 'en')));
    $sortOrder = (int)($input['sort_order'] ?? 100);
    $isActive = isset($input['is_active']) && (string)($input['is_active'] ?? '') === '1' ? 1 : 0;

    if ($title === '') {
        return ['ok' => false, 'message' => 'Quick reply title is required.'];
    }

    if ($messageBody === '') {
        return ['ok' => false, 'message' => 'Quick reply message is required.'];
    }

    $updated = $db->update_using_id(
        ['locale_code', 'title', 'message_body', 'is_active', 'sort_order'],
        [$localeCode, $title, $messageBody, $isActive, $sortOrder],
        'support_quick_replies',
        $replyId
    );

    return [
        'ok' => (bool)$updated,
        'message' => $updated ? 'Quick reply saved successfully.' : 'Unable to save quick reply.',
    ];
}

function admin_delete_chat_quick_reply(Mysql_ks $db, int $replyId): array
{
    $reply = admin_chat_quick_reply_find($db, $replyId);
    if (!is_array($reply) || empty($reply['id'])) {
        return ['ok' => false, 'message' => 'Quick reply not found.'];
    }

    $deleted = $db->delete_using_id('support_quick_replies', $replyId);
    return [
        'ok' => (bool)$deleted,
        'message' => $deleted ? 'Quick reply deleted successfully.' : 'Unable to delete quick reply.',
    ];
}

function admin_chat_message_preview(array $row, array $messages): string
{
    $rawBody = (string)($row['last_message_body'] ?? '');
    $cardPayload = function_exists('app_chat_card_decode') ? app_chat_card_decode($rawBody) : null;
    if (is_array($cardPayload) && !empty($cardPayload['title'])) {
        $body = trim((string)$cardPayload['title']);
    } else {
        $body = trim(html_entity_decode(strip_tags($rawBody), ENT_QUOTES, 'UTF-8'));
    }
    if ($body === '' && trim((string)($row['last_attachment_path'] ?? '')) !== '') {
        $body = admin_t($messages, 'chat_preview_image', 'Image attachment');
    }

    $body = preg_replace('/\s+/u', ' ', $body) ?? $body;
    if (mb_strlen($body) > 30) {
        $body = mb_substr($body, 0, 27) . '...';
    }

    return $body;
}

function admin_chat_list_timestamp(?string $timestamp): string
{
    $timestamp = trim((string)$timestamp);
    if ($timestamp === '') {
        return '';
    }

    $time = strtotime($timestamp);
    if ($time === false) {
        return $timestamp;
    }

    return date('Y-m-d', $time) === date('Y-m-d') ? date('H:i', $time) : date('d.m', $time);
}

function admin_format_last_login_date(?string $timestamp): string
{
    $timestamp = trim((string)$timestamp);
    if ($timestamp === '') {
        return '';
    }

    $time = strtotime($timestamp);
    if ($time === false) {
        return $timestamp;
    }

    $today = date('Y-m-d');
    $loginDate = date('Y-m-d', $time);
    $loginYear = date('Y', $time);
    $currentYear = date('Y');

    if ($loginDate === $today) {
        return date('H:i', $time);
    } elseif ($loginYear === $currentYear) {
        return date('d.m', $time);
    } else {
        return date('d.m.Y', $time);
    }
}

function admin_chat_status_badge_class(string $status): string
{
    $status = strtolower(trim($status));

    if (in_array($status, ['open', 'active', 'new'], true)) {
        return 'admin-status-pill--available';
    }

    if (in_array($status, ['pending', 'waiting'], true)) {
        return 'admin-status-pill--warning';
    }

    if (in_array($status, ['closed', 'resolved', 'archived'], true)) {
        return 'admin-status-pill--muted';
    }

    return 'admin-status-pill--neutral';
}

function admin_chat_customer_presence(
    Mysql_ks $db,
    int $customerId,
    string $lastSeenAt = '',
    array $messages = [],
    ?int $currentTime = null
): array {
    $currentTime = $currentTime ?? time();
    $lastSeenAt = trim($lastSeenAt);
    $lastSeenTimestamp = $lastSeenAt !== '' ? strtotime($lastSeenAt) : false;
    $secondsSinceLastSeen = $lastSeenTimestamp !== false ? max(0, $currentTime - $lastSeenTimestamp) : null;

    static $supportsUserOnline = null;
    if ($supportsUserOnline === null) {
        $supportsUserOnline = schema_object_exists($db, 'user_online');
    }

    $hasActiveOnlineEntry = false;
    if ($supportsUserOnline && $customerId > 0) {
        $onlineEntry = $db->select_user(
            "SELECT id
             FROM user_online
             WHERE user = {$customerId}
               AND status = 1
             LIMIT 1"
        );
        $hasActiveOnlineEntry = is_array($onlineEntry) && !empty($onlineEntry['id']);
    }

    $statusKey = 'offline';
    if ($hasActiveOnlineEntry && ($secondsSinceLastSeen === null || $secondsSinceLastSeen <= 180)) {
        $statusKey = 'online';
    } elseif ($secondsSinceLastSeen !== null && $secondsSinceLastSeen <= 180) {
        $statusKey = 'online';
    } elseif ($secondsSinceLastSeen !== null && $secondsSinceLastSeen <= 600) {
        $statusKey = 'away';
    }

    $statusLabel = admin_t($messages, 'chat_presence_' . $statusKey, ucfirst($statusKey));

    if ($lastSeenTimestamp !== false && $statusKey !== 'online') {
        $statusLabel .= ' · ' . admin_chat_list_timestamp($lastSeenAt);
    }

    return [
        'key' => $statusKey,
        'label' => $statusLabel,
        'class_name' => 'admin-chat-presence admin-chat-presence--' . $statusKey,
    ];
}

function admin_chat_presence_dot_html(array $presence): string
{
    $className = trim((string)($presence['class_name'] ?? 'admin-chat-presence admin-chat-presence--offline'));
    $label = trim((string)($presence['label'] ?? 'Offline'));

    return '<span class="' . admin_e($className) . '" title="' . admin_e($label) . '" aria-label="' . admin_e($label) . '"></span>';
}

function admin_chat_search_customers(Mysql_ks $db, string $query, int $limit = 8): array
{
    $query = trim($query);
    if ($query === '' || !schema_object_exists($db, 'customers')) {
        return [];
    }

    $limit = max(1, min(15, $limit));
    $safeLike = $db->escape('%' . $query . '%');

    return $db->select_full_user(
        "SELECT
            customers.id,
            customers.email,
            customers.status,
            (
                SELECT support_conversations.id
                FROM support_conversations
                WHERE support_conversations.customer_id = customers.id
                  AND support_conversations.conversation_type = 'live_chat'
                ORDER BY support_conversations.id DESC
                LIMIT 1
            ) AS conversation_id
         FROM customers
         WHERE customers.email LIKE '{$safeLike}'
         ORDER BY customers.email ASC
         LIMIT {$limit}"
    );
}

function admin_string_truncate(string $value, int $maxLength = 20): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(5, $maxLength - 4))) . '...';
    }

    if (strlen($value) <= $maxLength) {
        return $value;
    }

    return rtrim(substr($value, 0, max(5, $maxLength - 4))) . '...';
}

function admin_chat_display_name(array $row, array $messages, int $maxLength = 20): string
{
    if (trim((string)($row['conversation_type'] ?? '')) === 'group_chat') {
        return admin_string_truncate(chat_group_conversation_title($row, admin_t($messages, 'group_chat_badge', 'Group chat')), $maxLength);
    }

    $email = trim((string)($row['customer_email'] ?? ''));
    if ($email === '') {
        $email = admin_t($messages, 'chat_unknown_customer', 'Customer');
    }

    return admin_string_truncate($email, $maxLength);
}

function admin_chat_avatar_text(array $row, array $messages): string
{
    if (trim((string)($row['conversation_type'] ?? '')) === 'group_chat') {
        return 'G';
    }

    $email = trim((string)($row['customer_email'] ?? ''));
    if ($email === '') {
        return 'C';
    }

    return strtoupper(substr($email, 0, 1));
}

function admin_chat_avatar_theme(array $row): string
{
    if (trim((string)($row['conversation_type'] ?? '')) === 'group_chat') {
        return 'theme-6';
    }

    $seed = trim((string)($row['customer_email'] ?? ''));
    if ($seed === '') {
        return 'theme-1';
    }

    $index = abs((int)crc32(strtolower($seed))) % 6;
    return 'theme-' . ($index + 1);
}

function admin_chat_conversation_messages(Mysql_ks $db, int $conversationId): array
{
    if ($conversationId <= 0 || !schema_object_exists($db, 'support_messages')) {
        return [];
    }

    return $db->select_full_user(
        "SELECT
            support_messages.id,
            support_messages.sender_type,
            support_messages.customer_id,
            support_messages.admin_user_id,
            support_messages.message_body,
            support_messages.attachment_path,
            support_messages.is_read,
            support_messages.created_at,
            NULLIF(TRIM(admin_users.public_handle), '') AS admin_public_handle,
            admin_users.login_name AS admin_login_name,
            customers.email AS customer_email
         FROM support_messages
         LEFT JOIN admin_users ON admin_users.id = support_messages.admin_user_id
         LEFT JOIN customers ON customers.id = support_messages.customer_id
         WHERE support_messages.conversation_id = {$conversationId}
         ORDER BY support_messages.id ASC"
    );
}

function admin_chat_conversation_row(Mysql_ks $db, int $conversationId): ?array
{
    if ($conversationId <= 0 || !schema_object_exists($db, 'support_conversations')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT
            support_conversations.id,
            support_conversations.customer_id,
            support_conversations.conversation_type,
            support_conversations.status,
            support_conversations.subject,
            support_conversations.group_name,
            support_conversations.is_group_read_only,
            support_conversations.created_at,
            support_conversations.updated_at,
            customers.email AS customer_email,
            customers.last_login_at AS customer_last_login_at,
            customers.locale_code AS customer_locale_code
         FROM support_conversations
         LEFT JOIN customers ON customers.id = support_conversations.customer_id
         WHERE support_conversations.id = {$conversationId}
           AND support_conversations.conversation_type = 'live_chat'
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_mark_chat_conversation_read(Mysql_ks $db, int $conversationId): void
{
    if ($conversationId <= 0 || !schema_object_exists($db, 'support_messages')) {
        return;
    }

    $db->query(
        "UPDATE support_messages
         SET is_read = 1
         WHERE conversation_id = {$conversationId}
           AND sender_type = 'customer'
           AND is_read = 0"
    );
}

function admin_chat_refresh_conversation_timestamps(Mysql_ks $db, int $conversationId): void
{
    if ($conversationId <= 0 || !schema_object_exists($db, 'support_conversations') || !schema_object_exists($db, 'support_messages')) {
        return;
    }

    $latestMessageAt = $db->select_user(
        "SELECT created_at
         FROM support_messages
         WHERE conversation_id = {$conversationId}
         ORDER BY id DESC
         LIMIT 1"
    );
    $latestCustomerMessageAt = $db->select_user(
        "SELECT created_at
         FROM support_messages
         WHERE conversation_id = {$conversationId}
           AND sender_type = 'customer'
         ORDER BY id DESC
         LIMIT 1"
    );
    $latestAdminMessageAt = $db->select_user(
        "SELECT created_at
         FROM support_messages
         WHERE conversation_id = {$conversationId}
           AND sender_type = 'admin'
         ORDER BY id DESC
         LIMIT 1"
    );

    $safeLatestMessageAt = isset($latestMessageAt['created_at']) ? "'" . $db->escape((string)$latestMessageAt['created_at']) . "'" : 'created_at';
    $safeLatestCustomerMessageAt = isset($latestCustomerMessageAt['created_at']) ? "'" . $db->escape((string)$latestCustomerMessageAt['created_at']) . "'" : 'NULL';
    $safeLatestAdminMessageAt = isset($latestAdminMessageAt['created_at']) ? "'" . $db->escape((string)$latestAdminMessageAt['created_at']) . "'" : 'NULL';

    $db->query(
        "UPDATE support_conversations
         SET updated_at = {$safeLatestMessageAt},
             last_customer_message_at = {$safeLatestCustomerMessageAt},
             last_admin_message_at = {$safeLatestAdminMessageAt}
         WHERE id = {$conversationId}
         LIMIT 1"
    );
}

function admin_find_or_create_chat_conversation(Mysql_ks $db, int $customerId, int $adminUserId): int
{
    if ($customerId <= 0 || !schema_object_exists($db, 'support_conversations')) {
        return 0;
    }

    $row = $db->select_user(
        "SELECT id
         FROM support_conversations
         WHERE customer_id = {$customerId}
           AND conversation_type = 'live_chat'
         ORDER BY id DESC
         LIMIT 1"
    );

    if (is_array($row) && !empty($row['id'])) {
        return (int)$row['id'];
    }

    $subject = 'Customer live chat #' . $customerId;
    $db->insert(
        ['conversation_type', 'customer_id', 'assigned_admin_id', 'subject', 'status', 'priority'],
        ['live_chat', $customerId, $adminUserId > 0 ? $adminUserId : null, $subject, 'open', 'normal'],
        'support_conversations'
    );

    return (int)$db->id();
}

function admin_chat_group_pending_invites(Mysql_ks $db, int $adminUserId): array
{
    return chat_admin_group_pending_invites($db, $adminUserId);
}

function admin_chat_create_group_conversation(Mysql_ks $db, int $adminUserId, string $groupName, array $emails, bool $readOnly = false): array
{
    return chat_create_group_conversation(
        $db,
        ['participant_type' => 'admin', 'customer_id' => 0, 'admin_user_id' => $adminUserId],
        $groupName,
        $emails,
        $readOnly
    );
}

function admin_chat_toggle_group_read_only(Mysql_ks $db, int $conversationId, int $adminUserId, bool $readOnly): array
{
    $conversation = chat_group_accessible_for_admin($db, $adminUserId, $conversationId);
    if (!$conversation) {
        return ['ok' => false, 'message' => 'Group conversation not found.'];
    }

    $db->update_using_id(['is_group_read_only'], [$readOnly ? 1 : 0], 'support_conversations', $conversationId);
    return ['ok' => true];
}

function admin_chat_payment_modal_data(Mysql_ks $db, string $type, int $customerId, array $messages = []): array
{
    $settings = admin_app_settings($db);
    $currencyContext = admin_chat_payment_currency_context($db);
    $payload = [
        'ok' => true,
        'type' => $type,
        'currency' => [
            'id' => (int)($currencyContext['currency_id'] ?? 0),
            'code' => (string)($currencyContext['currency_code'] ?? ''),
            'symbol' => (string)($currencyContext['currency_symbol'] ?? ''),
            'name' => (string)($currencyContext['currency_name'] ?? ''),
        ],
        'amount_options' => admin_chat_payment_amount_options(),
    ];

    if ($type === 'crypto') {
        if (empty($settings['crypto_payments_enabled'])) {
            return ['ok' => false, 'message' => 'Crypto payments are disabled.'];
        }

        $payload['items'] = admin_chat_payment_crypto_assets($db, $customerId, $currencyContext);
        $payload['product_presets'] = admin_chat_payment_product_presets($db, $currencyContext);
        if (!$payload['items']) {
            $payload['empty_state_html'] = admin_chat_crypto_wallet_pool_help_html($messages, $settings);
        }
        $refreshedRows = array_values(array_filter($payload['items'], static function (array $item): bool {
            return !empty($item['rate_refreshed_now']);
        }));
        if ($refreshedRows) {
            $latestRefreshLabel = '';
            foreach ($refreshedRows as $row) {
                $candidate = trim((string)($row['rate_refresh_label'] ?? ''));
                if ($candidate !== '') {
                    $latestRefreshLabel = $candidate;
                }
            }
            $payload['rate_notice'] = [
                'refreshed' => true,
                'label' => $latestRefreshLabel,
            ];
        }
        return $payload;
    }

    if ($type === 'bank') {
        if (!admin_bank_transfers_enabled($settings)) {
            return ['ok' => false, 'message' => 'Bank transfers are disabled.'];
        }

        $assignedAccounts = admin_customer_active_bank_assignments($db, $customerId);
        $availableAccounts = $assignedAccounts ? [] : admin_chat_available_bank_account_rows($db, (string)($currencyContext['currency_code'] ?? ''));

        $payload['assigned_accounts'] = array_map(static function (array $row): array {
            return [
                'bank_account_id' => (int)($row['bank_account_id'] ?? 0),
                'bank_account_assignment_id' => (int)($row['bank_account_assignment_id'] ?? 0),
                'label' => (string)($row['label'] ?? ''),
                'bank_name' => (string)($row['bank_name'] ?? ''),
                'account_holder_name' => (string)($row['account_holder_name'] ?? ''),
                'iban' => (string)($row['iban'] ?? ''),
            ];
        }, $assignedAccounts);
        $payload['available_accounts'] = array_map(static function (array $row): array {
            return [
                'bank_account_id' => (int)($row['id'] ?? 0),
                'label' => (string)($row['label'] ?? ''),
                'bank_name' => (string)($row['bank_name'] ?? ''),
                'account_holder_name' => (string)($row['account_holder_name'] ?? ''),
                'iban' => (string)($row['iban'] ?? ''),
            ];
        }, $availableAccounts);

        return $payload;
    }

    return ['ok' => false, 'message' => 'Unsupported payment modal type.'];
}

function admin_chat_create_crypto_payment_request(
    Mysql_ks $db,
    int $conversationId,
    int $customerId,
    int $assetId,
    $amount,
    int $adminUserId,
    array $messages,
    int $productId = 0
): array {
    $settings = admin_app_settings($db);
    if (empty($settings['crypto_payments_enabled'])) {
        return ['ok' => false, 'message' => 'Crypto payments are disabled.'];
    }

    if (admin_customer_has_pending_crypto_payment($db, $customerId)) {
        return ['ok' => false, 'message' => 'pending_crypto_payment'];
    }

    $preview = admin_chat_crypto_payment_preview($db, $customerId, $assetId, $amount, $messages);
    if (empty($preview['ok'])) {
        return $preview;
    }

    $walletAddressId = (int)($preview['wallet_address_id'] ?? 0);
    if ($walletAddressId <= 0) {
        return ['ok' => false, 'message' => 'Wallet address is unavailable.'];
    }

    $db->start();

    $walletAssignmentId = (int)($preview['wallet_assignment_id'] ?? 0);
    if ($walletAssignmentId <= 0) {
        $assignResult = admin_assign_crypto_wallet_customer($db, $walletAddressId, $customerId, $adminUserId, $settings);
        if (empty($assignResult['ok'])) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => (string)($assignResult['message'] ?? 'Unable to assign wallet.')];
        }

        $assignments = admin_customer_active_crypto_assignments($db, $customerId, (string)($preview['asset_code'] ?? ''));
        $assignment = $assignments ? $assignments[0] : null;
        $walletAssignmentId = (int)($assignment['wallet_assignment_id'] ?? 0);
        if ($walletAssignmentId <= 0) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Wallet assignment could not be confirmed.'];
        }
    }

    $requestId = 0;
    $now = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $db->query(
        "UPDATE crypto_deposit_requests
         SET status = 'archived'
         WHERE customer_id = {$customerId}
           AND status IN ('pending', 'awaiting_confirmation')
           AND expires_at IS NOT NULL
           AND expires_at < '{$now}'"
    );
    $openWalletRequest = $db->select_user(
        "SELECT id, customer_id, order_id
         FROM crypto_deposit_requests
         WHERE wallet_address_id = {$walletAddressId}
           AND status IN ('pending', 'awaiting_confirmation')
         ORDER BY id DESC
         LIMIT 1"
    );

    if (is_array($openWalletRequest) && !empty($openWalletRequest['id'])) {
        if ((int)($openWalletRequest['customer_id'] ?? 0) !== $customerId || !empty($openWalletRequest['order_id'])) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'This wallet already has an active payment request.'];
        }

        $updated = $db->update_using_id(
            [
                'crypto_asset_id',
                'wallet_assignment_id',
                'requested_fiat_amount',
                'fiat_currency_id',
                'exchange_rate',
                'requested_crypto_amount',
                'created_by_admin_user_id',
                'requested_at',
                'expires_at',
                'request_note',
                'status',
            ],
            [
                (int)$preview['asset_id'],
                $walletAssignmentId,
                (float)$preview['fiat_amount'],
                (int)$preview['currency_id'],
                (float)$preview['rate'],
                (float)$preview['crypto_amount'],
                $adminUserId,
                $now,
                $expiresAt,
                'Updated from admin live chat crypto request',
                'pending',
            ],
            'crypto_deposit_requests',
            (int)$openWalletRequest['id']
        );
        if (!$updated) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Unable to update crypto payment request.'];
        }
        $requestId = (int)$openWalletRequest['id'];
    } else {
        $created = $db->insert(
            [
                'customer_id',
                'order_id',
                'crypto_asset_id',
                'wallet_address_id',
                'wallet_assignment_id',
                'requested_fiat_amount',
                'fiat_currency_id',
                'exchange_rate',
                'requested_crypto_amount',
                'assignment_mode',
                'status',
                'created_by_admin_user_id',
                'requested_at',
                'expires_at',
                'request_note',
            ],
            [
                $customerId,
                null,
                (int)$preview['asset_id'],
                $walletAddressId,
                $walletAssignmentId,
                (float)$preview['fiat_amount'],
                (int)$preview['currency_id'],
                (float)$preview['rate'],
                (float)$preview['crypto_amount'],
                'manual',
                'pending',
                $adminUserId,
                $now,
                $expiresAt,
                'Created from admin live chat crypto request',
            ],
            'crypto_deposit_requests'
        );

        if (!$created || (int)$db->id() <= 0) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Unable to create crypto payment request.'];
        }

        $requestId = (int)$db->id();
    }

    $customer = app_find_customer_by_id($db, $customerId);
    $localeCode = app_normalize_email_locale((string)($customer['locale_code'] ?? 'en'));
    $appSettings = app_fetch_settings($db);
    $siteUrl = rtrim((string)($appSettings['site_url'] ?? ''), '/');
    $paymentUrl = $siteUrl !== '' ? $siteUrl . '/payments_crypto#tickets' : '/payments_crypto#tickets';

    $messageBody = app_build_crypto_payment_chat_card_message([
        'asset_name' => (string)$preview['asset_name'],
        'asset_code' => (string)$preview['asset_code'],
        'asset_logo_url' => (string)$preview['asset_logo_url'],
        'requested_crypto_amount' => (string)$preview['crypto_amount'],
        'requested_fiat_amount' => (float)$preview['fiat_amount'],
        'currency_symbol' => (string)$preview['currency_symbol'],
        'currency_code' => (string)$preview['currency_code'],
        'wallet_address' => (string)$preview['wallet_address'],
        'wallet_owner_full_name' => (string)$preview['wallet_owner_full_name'],
        'payment_url' => $paymentUrl,
    ], $localeCode);

    if ($messageBody === '' || !admin_chat_insert_message($db, $conversationId, $adminUserId, $messageBody)) {
        $db->query('ROLLBACK');
        return ['ok' => false, 'message' => 'Unable to send crypto payment message to chat.'];
    }

    $orderId = 0;
    if ($productId > 0) {
        $product = admin_product_find($db, $productId);
        if ($product && !empty($product['is_active'])) {
            $orderResult = admin_create_order($db, [
                'customer_id' => $customerId,
                'product_id' => $productId,
                'customer_note' => 'created from admin',
                'has_delivery_link' => '0',
            ]);
            if (!empty($orderResult['ok']) && !empty($orderResult['order_id'])) {
                $orderId = (int)$orderResult['order_id'];
                $updated = $db->update_using_id(
                    ['order_id'],
                    [$orderId],
                    'crypto_deposit_requests',
                    $requestId
                );
            }
        }
    }

    $db->commit();

    return ['ok' => true, 'request_id' => $requestId, 'order_id' => $orderId];
}

function admin_chat_create_bank_payment_request(
    Mysql_ks $db,
    int $conversationId,
    int $customerId,
    $amount,
    int $bankAccountId,
    int $adminUserId,
    array $messages
): array {
    $settings = admin_app_settings($db);
    if (!admin_bank_transfers_enabled($settings)) {
        return ['ok' => false, 'message' => 'Bank transfers are disabled.'];
    }

    $preview = admin_chat_bank_payment_preview($db, $customerId, $amount, $bankAccountId, $messages);
    if (empty($preview['ok'])) {
        return $preview;
    }

    $resolvedBankAccountId = (int)($preview['bank_account_id'] ?? 0);
    if ($resolvedBankAccountId <= 0) {
        return ['ok' => false, 'message' => 'Bank account is unavailable.'];
    }

    $db->start();

    $bankAccountAssignmentId = (int)($preview['bank_account_assignment_id'] ?? 0);
    if ($bankAccountAssignmentId <= 0) {
        $assignResult = admin_assign_bank_account_customer($db, $resolvedBankAccountId, $customerId, $adminUserId, $settings);
        if (empty($assignResult['ok'])) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => (string)($assignResult['message'] ?? 'Unable to assign bank account.')];
        }

        $assignments = admin_customer_active_bank_assignments($db, $customerId);
        foreach ($assignments as $assignment) {
            if ((int)($assignment['bank_account_id'] ?? 0) === $resolvedBankAccountId) {
                $preview = array_merge($preview, [
                    'bank_account_assignment_id' => (int)($assignment['bank_account_assignment_id'] ?? 0),
                    'account_holder_name' => (string)($assignment['account_holder_name'] ?? $preview['account_holder_name']),
                    'bank_name' => (string)($assignment['bank_name'] ?? $preview['bank_name']),
                    'iban' => (string)($assignment['iban'] ?? $preview['iban']),
                    'swift_bic' => (string)($assignment['swift_bic'] ?? $preview['swift_bic']),
                    'transfer_instructions' => (string)($assignment['transfer_instructions'] ?? $preview['transfer_instructions']),
                    'payment_reference_template' => (string)($assignment['payment_reference_template'] ?? $preview['payment_reference_template']),
                ]);
                break;
            }
        }
        $bankAccountAssignmentId = (int)($preview['bank_account_assignment_id'] ?? 0);
        if ($bankAccountAssignmentId <= 0) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Bank account assignment could not be confirmed.'];
        }
    }

    $requestId = 0;
    $now = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', strtotime('+72 hours'));
    $db->query(
        "UPDATE bank_transfer_requests
         SET status = 'archived'
         WHERE customer_id = {$customerId}
           AND status IN ('pending_payment', 'awaiting_review')
           AND expires_at IS NOT NULL
           AND expires_at < '{$now}'"
    );
    $openBankRequest = $db->select_user(
        "SELECT id, customer_id, order_id
         FROM bank_transfer_requests
         WHERE bank_account_id = {$resolvedBankAccountId}
           AND status IN ('pending_payment', 'awaiting_review')
         ORDER BY id DESC
         LIMIT 1"
    );

    if (is_array($openBankRequest) && !empty($openBankRequest['id'])) {
        if ((int)($openBankRequest['customer_id'] ?? 0) !== $customerId || !empty($openBankRequest['order_id'])) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'This bank account already has an active payment request.'];
        }

        $updated = $db->update_using_id(
            [
                'bank_account_assignment_id',
                'requested_amount',
                'currency_id',
                'created_by_admin_user_id',
                'requested_at',
                'expires_at',
                'customer_transfer_note',
                'status',
            ],
            [
                $bankAccountAssignmentId,
                (float)$preview['amount'],
                (int)$preview['currency_id'],
                $adminUserId,
                $now,
                $expiresAt,
                'Updated from admin live chat bank request',
                'pending_payment',
            ],
            'bank_transfer_requests',
            (int)$openBankRequest['id']
        );
        if (!$updated) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Unable to update bank payment request.'];
        }
        $requestId = (int)$openBankRequest['id'];
    } else {
        $created = $db->insert(
            [
                'customer_id',
                'order_id',
                'bank_account_id',
                'bank_account_assignment_id',
                'requested_amount',
                'currency_id',
                'payment_reference',
                'status',
                'created_by_admin_user_id',
                'requested_at',
                'expires_at',
                'customer_transfer_note',
            ],
            [
                $customerId,
                null,
                $resolvedBankAccountId,
                $bankAccountAssignmentId,
                (float)$preview['amount'],
                (int)$preview['currency_id'],
                '',
                'pending_payment',
                $adminUserId,
                $now,
                $expiresAt,
                'Created from admin live chat bank request',
            ],
            'bank_transfer_requests'
        );

        if (!$created || (int)$db->id() <= 0) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Unable to create bank payment request.'];
        }

        $requestId = (int)$db->id();
    }

    $paymentReference = admin_chat_bank_reference_build(
        (string)($preview['payment_reference_template'] ?? ''),
        $customerId,
        $requestId,
        0
    );
    $db->update_using_id(['payment_reference'], [$paymentReference], 'bank_transfer_requests', $requestId);

    $customer = app_find_customer_by_id($db, $customerId);
    $localeCode = app_normalize_email_locale((string)($customer['locale_code'] ?? 'en'));
    $messageBody = app_build_bank_payment_chat_card_message([
        'requested_amount' => (float)$preview['amount'],
        'currency_symbol' => (string)$preview['currency_symbol'],
        'currency_code' => (string)$preview['currency_code'],
        'account_holder_name' => (string)$preview['account_holder_name'],
        'bank_name' => (string)$preview['bank_name'],
        'iban' => (string)$preview['iban'],
        'swift_bic' => (string)$preview['swift_bic'],
        'payment_reference' => $paymentReference,
        'transfer_instructions' => (string)$preview['transfer_instructions'],
    ], $localeCode);

    if ($messageBody === '' || !admin_chat_insert_message($db, $conversationId, $adminUserId, $messageBody)) {
        $db->query('ROLLBACK');
        return ['ok' => false, 'message' => 'Unable to send bank payment message to chat.'];
    }

    $db->commit();

    return ['ok' => true, 'request_id' => $requestId];
}

function admin_delete_chat_conversation(Mysql_ks $db, int $conversationId): bool
{
    if ($conversationId <= 0 || !schema_object_exists($db, 'support_conversations')) {
        return false;
    }

    $db->query("DELETE FROM support_conversations WHERE id = {$conversationId} LIMIT 1");
    return true;
}

function admin_delete_chat_message(Mysql_ks $db, int $conversationId, int $messageId): bool
{
    if ($conversationId <= 0 || $messageId <= 0 || !schema_object_exists($db, 'support_messages')) {
        return false;
    }

    $messageRow = $db->select_user(
        "SELECT id, attachment_path
         FROM support_messages
         WHERE id = {$messageId}
           AND conversation_id = {$conversationId}
         LIMIT 1"
    );
    if (!is_array($messageRow) || empty($messageRow['id'])) {
        return false;
    }

    $deleted = $db->delete_using_id('support_messages', $messageId);
    if (!$deleted) {
        return false;
    }

    $attachmentPath = trim((string)($messageRow['attachment_path'] ?? ''));
    if ($attachmentPath !== '' && strpos($attachmentPath, '/uploads/chat/') === 0) {
        $absoluteAttachmentPath = dirname(__DIR__, 2) . '/public_html' . $attachmentPath;
        if (is_file($absoluteAttachmentPath)) {
            @unlink($absoluteAttachmentPath);
        }
    }

    admin_chat_refresh_conversation_timestamps($db, $conversationId);
    return true;
}

function admin_render_chat_conversation_html(array $conversationRow, array $messageRows, array $messages): string
{
    ob_start();
    ?>
    <div class="admin-chat-conversation" data-admin-chat-conversation data-conversation-id="<?php echo admin_e((string)($conversationRow['id'] ?? 0)); ?>">
        <div class="admin-chat-conversation__messages">
            <?php if (!$messageRows): ?>
                <div class="admin-chat-conversation__empty">
                    <?php echo admin_e(admin_t($messages, 'chat_conversation_empty', 'No messages in this conversation yet.')); ?>
                </div>
            <?php else: ?>
                <?php foreach ($messageRows as $messageRow): ?>
                    <?php
                    $isCustomer = (string)($messageRow['sender_type'] ?? '') === 'customer';
                    $bubbleClass = $isCustomer ? 'is-customer' : 'is-admin';
                    $attachmentPath = trim((string)($messageRow['attachment_path'] ?? ''));
                    $messageHtml = chat_format_message_html((string)($messageRow['message_body'] ?? ''));
                    $senderLabel = chat_sender_display_name(
                        [
                            'sender_type' => (string)($messageRow['sender_type'] ?? ''),
                            'admin_public_handle' => (string)($messageRow['admin_public_handle'] ?? ''),
                            'admin_login_name' => (string)($messageRow['admin_login_name'] ?? ''),
                            'customer_email' => (string)($messageRow['customer_email'] ?? ''),
                        ],
                        [],
                        admin_t($messages, 'chat_inbox_title', 'Live chat inbox')
                    );
                    $isRead = (int)($messageRow['is_read'] ?? 0) === 1;
                    $receiptClass = $isRead ? 'is-read' : 'is-pending';
                    $receiptIcon = $isRead ? 'bi bi-check2-all' : 'bi bi-check2';
                    $receiptLabel = $isRead
                        ? admin_t($messages, 'chat_read_receipt_read', 'Read by customer')
                        : admin_t($messages, 'chat_read_receipt_pending', 'Waiting for customer to read');
                    $deleteLabel = admin_t($messages, 'chat_message_delete_button', 'Delete message');
                    ?>
                    <div class="admin-chat-conversation__message <?php echo admin_e($bubbleClass); ?>" data-admin-chat-message data-message-id="<?php echo admin_e((string)($messageRow['id'] ?? 0)); ?>">
                        <div class="admin-chat-conversation__bubble">
                            <div class="admin-chat-conversation__sender"><?php echo admin_e($senderLabel); ?></div>
                            <?php if ($attachmentPath !== ''): ?>
                                <a href="<?php echo admin_e($attachmentPath); ?>" target="_blank" rel="noopener noreferrer" class="admin-chat-conversation__image-link">
                                    <img src="<?php echo admin_e($attachmentPath); ?>" alt="attachment" class="admin-chat-conversation__image">
                                </a>
                            <?php endif; ?>
                            <?php if ($messageHtml !== ''): ?>
                                <div class="admin-chat-conversation__text"><?php echo $messageHtml; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="admin-chat-conversation__meta">
                            <?php if (!$isCustomer): ?>
                                <span class="admin-chat-read-receipt <?php echo admin_e($receiptClass); ?>" title="<?php echo admin_e($receiptLabel); ?>" aria-label="<?php echo admin_e($receiptLabel); ?>">
                                    <i class="<?php echo admin_e($receiptIcon); ?>" aria-hidden="true"></i>
                                </span>
                            <?php endif; ?>
                            <div class="admin-chat-conversation__time"><?php echo admin_e(admin_chat_list_timestamp((string)($messageRow['created_at'] ?? ''))); ?></div>
                            <button
                                type="button"
                                class="admin-chat-conversation__delete"
                                data-admin-chat-delete-message
                                data-conversation-id="<?php echo admin_e((string)($conversationRow['id'] ?? 0)); ?>"
                                data-message-id="<?php echo admin_e((string)($messageRow['id'] ?? 0)); ?>"
                                title="<?php echo admin_e($deleteLabel); ?>"
                                aria-label="<?php echo admin_e($deleteLabel); ?>">
                                <i class="bi bi-trash3" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return trim((string)ob_get_clean());
}

function admin_chat_insert_message(Mysql_ks $db, int $conversationId, int $adminUserId, string $messageBody, ?string $attachmentPath = null): bool
{
    $messageBody = trim($messageBody);
    $attachmentPath = trim((string)$attachmentPath);
    $currentTime = function_exists('app_current_datetime_string') ? app_current_datetime_string() : date('Y-m-d H:i:s');

    if ($conversationId <= 0 || $adminUserId <= 0 || ($messageBody === '' && $attachmentPath === '')) {
        return false;
    }

    $conversationRow = $db->select_user(
        "SELECT customer_id
         FROM support_conversations
         WHERE id = {$conversationId}
         LIMIT 1"
    );

    $customerId = isset($conversationRow['customer_id']) ? (int)$conversationRow['customer_id'] : 0;

    $inserted = $db->insert(
        ['conversation_id', 'sender_type', 'customer_id', 'admin_user_id', 'message_body', 'attachment_path', 'is_read', 'created_at'],
        [$conversationId, 'admin', $customerId > 0 ? $customerId : null, $adminUserId, $messageBody, $attachmentPath !== '' ? $attachmentPath : null, 0, $currentTime],
        'support_messages'
    );

    if (!$inserted) {
        return false;
    }

    $db->update_using_id(
        ['last_admin_message_at', 'updated_at'],
        [$currentTime, $currentTime],
        'support_conversations',
        $conversationId
    );

    if ($customerId > 0) {
        app_queue_live_chat_customer_notification_if_offline($db, $conversationId, $customerId, $messageBody, $attachmentPath);
    }

    return true;
}

function admin_chat_resize_image(string $sourcePath, string $destinationPath, string $mimeType): bool
{
    switch ($mimeType) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if (!$image) {
        return false;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    if ($width <= 0 || $height <= 0) {
        imagedestroy($image);
        return false;
    }

    $maxDimension = 1280;
    $scale = min($maxDimension / $width, $maxDimension / $height, 1);
    $targetWidth = max(1, (int)round($width * $scale));
    $targetHeight = max(1, (int)round($height * $scale));

    $target = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$target) {
        imagedestroy($image);
        return false;
    }

    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
    }

    imagecopyresampled($target, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

    $saved = false;
    if ($mimeType === 'image/jpeg') {
        $saved = imagejpeg($target, $destinationPath, 80);
    } elseif ($mimeType === 'image/png') {
        $saved = imagepng($target, $destinationPath, 7);
    } elseif ($mimeType === 'image/gif') {
        $saved = imagegif($target, $destinationPath);
    }

    imagedestroy($target);
    imagedestroy($image);

    return (bool)$saved;
}

function admin_chat_store_uploaded_image(array $file, int $adminUserId): ?string
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_OK);
    $originalName = (string)($file['name'] ?? '');
    $tmpPath = (string)($file['tmp_name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $mimeType = function_exists('mime_content_type') ? (string)mime_content_type($tmpPath) : '';
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

    if (
        $uploadError !== UPLOAD_ERR_OK
        || !is_uploaded_file($tmpPath)
        || !in_array($extension, $allowedExtensions, true)
        || ($mimeType !== '' && !in_array($mimeType, $allowedMimeTypes, true))
    ) {
        return null;
    }

    $uploadDirectory = dirname(__DIR__, 2) . '/public_html/uploads/chat';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        return null;
    }

    $safeExtension = $extension === 'jpeg' ? 'jpg' : $extension;
    $fileName = 'admin_chat_' . $adminUserId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safeExtension;
    $destinationPath = $uploadDirectory . '/' . $fileName;

    $saved = false;
    if ($mimeType !== '' && function_exists('imagecreatetruecolor')) {
        $saved = admin_chat_resize_image($tmpPath, $destinationPath, $mimeType);
    }

    if (!$saved) {
        $saved = move_uploaded_file($tmpPath, $destinationPath);
    }

    if (!$saved) {
        return null;
    }

    return '/uploads/chat/' . $fileName;
}

function admin_site_logo_upload_directory(): string
{
    return dirname(__DIR__, 2) . '/public_html/uploads/branding';
}

function admin_site_logo_is_valid_svg(string $tmpPath): bool
{
    if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath)) {
        return false;
    }

    $contents = @file_get_contents($tmpPath, false, null, 0, 65536);
    if (!is_string($contents) || $contents === '') {
        return false;
    }

    return stripos($contents, '<svg') !== false;
}

function admin_site_logo_public_path(array $file, int $adminUserId): ?string
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_OK);
    $originalName = (string)($file['name'] ?? '');
    $tmpPath = (string)($file['tmp_name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
    $mimeType = function_exists('mime_content_type') ? (string)mime_content_type($tmpPath) : '';
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'text/plain', 'text/xml', 'application/xml'];
    $isSvg = $extension === 'svg';

    if (
        $uploadError !== UPLOAD_ERR_OK
        || !is_uploaded_file($tmpPath)
        || !in_array($extension, $allowedExtensions, true)
        || (!$isSvg && $mimeType !== '' && !in_array($mimeType, $allowedMimeTypes, true))
        || ($isSvg && !admin_site_logo_is_valid_svg($tmpPath))
    ) {
        return null;
    }

    $uploadDirectory = admin_site_logo_upload_directory();
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        return null;
    }

    $safeExtension = $extension === 'jpeg' ? 'jpg' : $extension;
    $fileName = 'site_logo_' . $adminUserId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safeExtension;
    $destinationPath = $uploadDirectory . '/' . $fileName;

    $saved = false;
    if (!$isSvg && $mimeType !== '' && function_exists('imagecreatetruecolor')) {
        $saved = admin_chat_resize_image($tmpPath, $destinationPath, $mimeType);
    }

    if (!$saved) {
        $saved = move_uploaded_file($tmpPath, $destinationPath);
    }

    if (!$saved) {
        return null;
    }

    return '/uploads/branding/' . $fileName;
}

function admin_delete_site_logo_file(string $publicPath): bool
{
    $publicPath = trim($publicPath);
    if ($publicPath === '' || strpos($publicPath, '/uploads/branding/') !== 0) {
        return false;
    }

    $publicRoot = realpath(dirname(__DIR__, 2) . '/public_html');
    if ($publicRoot === false) {
        return false;
    }

    $filePath = $publicRoot . '/' . ltrim($publicPath, '/');
    $realFilePath = realpath($filePath);
    if ($realFilePath === false || strpos($realFilePath, $publicRoot . '/uploads/branding/') !== 0 || !is_file($realFilePath)) {
        return false;
    }

    return @unlink($realFilePath);
}

function admin_email_template_rows(Mysql_ks $db, int $limit = 20): array
{
    if (!schema_object_exists($db, 'email_templates')) {
        return [];
    }

    app_ensure_email_template_runtime_rows($db);
    app_ensure_email_template_runtime_translations($db);
    $limit = max(1, min(50, $limit));
    return $db->select_full_user(
        "SELECT id, template_key, name, subject, body_html, is_system, is_active, updated_at
         FROM email_templates
         WHERE template_key IN ('" . implode("','", array_map([$db, 'escape'], app_email_active_template_keys())) . "')
         ORDER BY is_system DESC, template_key ASC, id ASC
         LIMIT {$limit}"
    );
}

function admin_email_template_count(Mysql_ks $db): int
{
    if (!schema_object_exists($db, 'email_templates')) {
        return 0;
    }

    app_ensure_email_template_runtime_rows($db);
    app_ensure_email_template_runtime_translations($db);
    $row = $db->select_user(
        "SELECT COUNT(*) AS total
         FROM email_templates
         WHERE template_key IN ('" . implode("','", array_map([$db, 'escape'], app_email_active_template_keys())) . "')"
    );
    return (int)($row['total'] ?? 0);
}

function admin_email_template_rows_paginated(Mysql_ks $db, int $limit = 20, int $offset = 0): array
{
    if (!schema_object_exists($db, 'email_templates')) {
        return [];
    }

    app_ensure_email_template_runtime_rows($db);
    app_ensure_email_template_runtime_translations($db);
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);

    return $db->select_full_user(
        "SELECT id, template_key, name, subject, body_html, is_system, is_active, updated_at
         FROM email_templates
         WHERE template_key IN ('" . implode("','", array_map([$db, 'escape'], app_email_active_template_keys())) . "')
         ORDER BY is_system DESC, template_key ASC, id ASC
         LIMIT {$offset}, {$limit}"
    );
}

function admin_email_template_translation_map(Mysql_ks $db, int $templateId): array
{
    if ($templateId <= 0) {
        return [];
    }

    app_ensure_email_template_runtime_translations($db);
    if (!schema_object_exists($db, 'email_template_translations')) {
        return [];
    }

    $rows = $db->select_full_user(
        "SELECT locale_code, subject, body_text
         FROM email_template_translations
         WHERE template_id = {$templateId}
         ORDER BY locale_code ASC"
    );

    $map = [];
    foreach ($rows as $row) {
        $localeCode = app_normalize_email_locale((string)($row['locale_code'] ?? ''));
        $map[$localeCode] = [
            'subject' => (string)($row['subject'] ?? ''),
            'body' => (string)($row['body_text'] ?? ''),
        ];
    }

    return $map;
}

function admin_email_template_find(Mysql_ks $db, int $templateId): ?array
{
    if ($templateId <= 0 || !schema_object_exists($db, 'email_templates')) {
        return null;
    }

    app_ensure_email_template_runtime_rows($db);
    $row = $db->select_user(
        "SELECT id, template_key, name, subject, body_html, is_system, is_active, updated_at
         FROM email_templates
         WHERE id = {$templateId}
         LIMIT 1"
    );

    if (!is_array($row) || empty($row['id'])) {
        return null;
    }

    $row['body_text'] = app_email_plain_text((string)($row['body_html'] ?? ''));
    $row['translations'] = admin_email_template_translation_map($db, (int)$row['id']);
    return $row;
}

function admin_save_email_template(Mysql_ks $db, int $templateId, array $input): array
{
    $template = admin_email_template_find($db, $templateId);
    if (!$template) {
        return ['ok' => false, 'message' => 'Email template not found.'];
    }

    $name = trim((string)($input['name'] ?? ''));
    $isActive = isset($input['is_active']) && (string)($input['is_active'] ?? '') === '1' ? 1 : 0;

    if ($name === '') {
        return ['ok' => false, 'message' => 'Template name is required.'];
    }

    $translationPayloads = [];
    foreach (app_supported_email_locales() as $localeCode) {
        $subjectInput = app_email_subject(
            (string)($input['subject_' . $localeCode] ?? ''),
            (string)($template['subject'] ?? 'Notification')
        );
        $bodyInput = trim((string)($input['body_' . $localeCode] ?? ''));

        if ($bodyInput === '' || app_email_plain_text($bodyInput) === '') {
            return ['ok' => false, 'message' => 'Template body cannot be empty.'];
        }

        $translationPayloads[$localeCode] = [
            'subject' => $subjectInput,
            'body' => $bodyInput,
        ];
    }

    $updated = $db->update_using_id(
        ['name', 'is_active'],
        [$name, $isActive],
        'email_templates',
        $templateId
    );

    if (!$updated) {
        return [
            'ok' => false,
            'message' => 'Unable to save email template.',
        ];
    }

    foreach ($translationPayloads as $localeCode => $payload) {
        $existingTranslation = app_email_template_translation_row($db, $templateId, $localeCode);
        if (is_array($existingTranslation) && !empty($existingTranslation['id'])) {
            $db->update_using_id(
                ['subject', 'body_text'],
                [$payload['subject'], $payload['body']],
                'email_template_translations',
                (int)$existingTranslation['id']
            );
        } else {
            $db->insert(
                ['template_id', 'locale_code', 'subject', 'body_text'],
                [$templateId, $localeCode, $payload['subject'], $payload['body']],
                'email_template_translations'
            );
        }
    }

    return [
        'ok' => true,
        'message' => 'Email template saved successfully.',
    ];
}

function admin_email_template_placeholder_badges(string $templateKey): array
{
    $badges = [];
    foreach (app_email_template_placeholders($templateKey) as $placeholder) {
        if ($placeholder === '') {
            continue;
        }
        $badges[] = '{' . $placeholder . '}';
    }

    return $badges;
}

function admin_faq_count(Mysql_ks $db): int
{
    if (!schema_object_exists($db, 'static_pages')) {
        return 0;
    }

    $row = $db->select_user("SELECT COUNT(*) AS total FROM static_pages WHERE page_type = 'faq' OR slug LIKE 'faq-%'");
    return (int)($row['total'] ?? 0);
}

function admin_faq_rows(Mysql_ks $db, int $limit = 20, int $offset = 0): array
{
    if (!schema_object_exists($db, 'static_pages')) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    return $db->select_full_user(
        "SELECT id, slug, title, body, page_type, is_system, is_active, created_at, updated_at
         FROM static_pages
         WHERE page_type = 'faq' OR slug LIKE 'faq-%'
         ORDER BY slug ASC, id ASC
         LIMIT {$offset}, {$limit}"
    );
}

function admin_faq_find(Mysql_ks $db, int $faqId): ?array
{
    if ($faqId <= 0 || !schema_object_exists($db, 'static_pages')) {
        return null;
    }

    $row = $db->select_user(
        "SELECT id, slug, title, body, page_type, is_system, is_active, created_at, updated_at
         FROM static_pages
         WHERE id = {$faqId}
           AND (page_type = 'faq' OR slug LIKE 'faq-%')
         LIMIT 1"
    );

    return is_array($row) ? $row : null;
}

function admin_faq_slugify(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'faq';
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
    $value = trim($value, '-');

    return $value !== '' ? $value : 'faq';
}

function admin_faq_unique_slug(Mysql_ks $db, string $title, string $requestedSlug = '', int $excludeId = 0): string
{
    $baseSlug = admin_faq_slugify($requestedSlug !== '' ? $requestedSlug : $title);
    $candidate = $baseSlug;
    $suffix = 2;

    while (true) {
        $safeSlug = $db->escape($candidate);
        $excludeSql = $excludeId > 0 ? " AND id != {$excludeId}" : '';
        $row = $db->select_user(
            "SELECT id
             FROM static_pages
             WHERE slug = '{$safeSlug}'{$excludeSql}
             LIMIT 1"
        );

        if (!is_array($row) || empty($row['id'])) {
            return $candidate;
        }

        $candidate = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function admin_create_faq(Mysql_ks $db, array $input): array
{
    if (!schema_object_exists($db, 'static_pages')) {
        return ['ok' => false, 'message' => 'FAQ storage is not available.'];
    }

    $title = trim((string)($input['title'] ?? ''));
    $slugInput = trim((string)($input['slug'] ?? ''));
    $body = trim((string)($input['body'] ?? ''));
    $isActive = isset($input['is_active']) && (string)$input['is_active'] === '1' ? 1 : 0;

    if ($title === '') {
        return ['ok' => false, 'message' => 'FAQ title is required.'];
    }

    if ($body === '') {
        return ['ok' => false, 'message' => 'FAQ content is required.'];
    }

    $slug = admin_faq_unique_slug($db, $title, $slugInput);
    $inserted = $db->insert(
        ['slug', 'title', 'body', 'page_type', 'is_system', 'is_active'],
        [$slug, $title, $body, 'faq', 0, $isActive],
        'static_pages'
    );

    if (!$inserted) {
        return ['ok' => false, 'message' => 'Unable to create FAQ entry.'];
    }

    return [
        'ok' => true,
        'message' => 'FAQ entry created successfully.',
        'faq_id' => (int)$db->id(),
    ];
}

function admin_save_faq(Mysql_ks $db, int $faqId, array $input): array
{
    $faq = admin_faq_find($db, $faqId);
    if (!is_array($faq) || empty($faq['id'])) {
        return ['ok' => false, 'message' => 'FAQ entry not found.'];
    }

    $title = trim((string)($input['title'] ?? ''));
    $slugInput = trim((string)($input['slug'] ?? ''));
    $body = trim((string)($input['body'] ?? ''));
    $isActive = isset($input['is_active']) && (string)$input['is_active'] === '1' ? 1 : 0;

    if ($title === '') {
        return ['ok' => false, 'message' => 'FAQ title is required.'];
    }

    if ($body === '') {
        return ['ok' => false, 'message' => 'FAQ content is required.'];
    }

    $slug = admin_faq_unique_slug($db, $title, $slugInput, $faqId);
    $updated = $db->update_using_id(
        ['slug', 'title', 'body', 'page_type', 'is_system', 'is_active'],
        [$slug, $title, $body, 'faq', (int)($faq['is_system'] ?? 0), $isActive],
        'static_pages',
        $faqId
    );

    return [
        'ok' => (bool)$updated,
        'message' => $updated ? 'FAQ entry saved successfully.' : 'Unable to save FAQ entry.',
    ];
}

function admin_delete_faq(Mysql_ks $db, int $faqId): array
{
    $faq = admin_faq_find($db, $faqId);
    if (!is_array($faq) || empty($faq['id'])) {
        return ['ok' => false, 'message' => 'FAQ entry not found.'];
    }

    $deleted = $db->delete_using_id('static_pages', $faqId);

    return [
        'ok' => (bool)$deleted,
        'message' => $deleted ? 'FAQ entry deleted successfully.' : 'Unable to delete FAQ entry.',
    ];
}

function admin_settings_rows(Mysql_ks $db): array
{
    $settings = admin_app_settings($db);
    if (!$settings) {
        return [];
    }

    return [
        ['label' => 'site_name', 'value' => $settings['site_name'] ?? ''],
        ['label' => 'site_title', 'value' => $settings['site_title'] ?? ''],
        ['label' => 'site_url', 'value' => $settings['site_url'] ?? ''],
        ['label' => 'support_email', 'value' => $settings['support_email'] ?? ''],
        ['label' => 'default_locale_code', 'value' => $settings['default_locale_code'] ?? ''],
        ['label' => 'support_chat_enabled', 'value' => !empty($settings['support_chat_enabled']) ? '1' : '0'],
        ['label' => 'contact_form_enabled', 'value' => !empty($settings['contact_form_enabled']) ? '1' : '0'],
        ['label' => 'crypto_payments_enabled', 'value' => !empty($settings['crypto_payments_enabled']) ? '1' : '0'],
        ['label' => 'bank_transfers_enabled', 'value' => !empty($settings['bank_transfers_enabled']) ? '1' : '0'],
        ['label' => 'crypto_wallet_shared_assignments_enabled', 'value' => !empty($settings['crypto_wallet_shared_assignments_enabled']) ? '1' : '0'],
        ['label' => 'bank_account_shared_assignments_enabled', 'value' => !empty($settings['bank_account_shared_assignments_enabled']) ? '1' : '0'],
    ];
}

function admin_search_order_rows(Mysql_ks $db, string $query, int $limit = 20): array
{
    $query = trim($query);
    if ($query === '' || !schema_object_exists($db, 'orders') || !schema_object_exists($db, 'customers')) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $safeLike = $db->escape('%' . $query . '%');

    $whereParts = [
        "customers.email LIKE '{$safeLike}'",
    ];

    if (ctype_digit($query)) {
        $whereParts[] = 'orders.id = ' . (int)$query;
    }

    $hasCustomerCryptoWallets = schema_object_exists($db, 'customer_crypto_wallets');
    $hasCryptoDepositRequests = schema_object_exists($db, 'crypto_deposit_requests') && schema_object_exists($db, 'crypto_wallet_addresses');

    if ($hasCryptoDepositRequests) {
        $whereParts[] = "EXISTS (
            SELECT 1
            FROM crypto_deposit_requests
            INNER JOIN crypto_wallet_addresses ON crypto_wallet_addresses.id = crypto_deposit_requests.wallet_address_id
            WHERE crypto_deposit_requests.order_id = orders.id
              AND crypto_wallet_addresses.address LIKE '{$safeLike}'
        )";
    }

    if ($hasCustomerCryptoWallets) {
        $whereParts[] = "EXISTS (
            SELECT 1
            FROM customer_crypto_wallets
            WHERE customer_crypto_wallets.customer_id = orders.customer_id
              AND customer_crypto_wallets.address LIKE '{$safeLike}'
        )";
    }

    $walletAddressSelect = "''";
    $walletAssetSelect = "''";

    if ($hasCryptoDepositRequests && $hasCustomerCryptoWallets) {
        $walletAddressSelect = "COALESCE(
            (
                SELECT crypto_wallet_addresses.address
                FROM crypto_deposit_requests
                INNER JOIN crypto_wallet_addresses ON crypto_wallet_addresses.id = crypto_deposit_requests.wallet_address_id
                WHERE crypto_deposit_requests.order_id = orders.id
                ORDER BY crypto_deposit_requests.id DESC
                LIMIT 1
            ),
            (
                SELECT customer_crypto_wallets.address
                FROM customer_crypto_wallets
                WHERE customer_crypto_wallets.customer_id = orders.customer_id
                ORDER BY customer_crypto_wallets.assigned_at DESC
                LIMIT 1
            )
        )";

        $walletAssetSelect = "COALESCE(
            (
                SELECT crypto_assets.code
                FROM crypto_deposit_requests
                INNER JOIN crypto_assets ON crypto_assets.id = crypto_deposit_requests.crypto_asset_id
                WHERE crypto_deposit_requests.order_id = orders.id
                ORDER BY crypto_deposit_requests.id DESC
                LIMIT 1
            ),
            (
                SELECT customer_crypto_wallets.crypto_asset_code
                FROM customer_crypto_wallets
                WHERE customer_crypto_wallets.customer_id = orders.customer_id
                ORDER BY customer_crypto_wallets.assigned_at DESC
                LIMIT 1
            )
        )";
    } elseif ($hasCryptoDepositRequests) {
        $walletAddressSelect = "(
            SELECT crypto_wallet_addresses.address
            FROM crypto_deposit_requests
            INNER JOIN crypto_wallet_addresses ON crypto_wallet_addresses.id = crypto_deposit_requests.wallet_address_id
            WHERE crypto_deposit_requests.order_id = orders.id
            ORDER BY crypto_deposit_requests.id DESC
            LIMIT 1
        )";

        $walletAssetSelect = "(
            SELECT crypto_assets.code
            FROM crypto_deposit_requests
            INNER JOIN crypto_assets ON crypto_assets.id = crypto_deposit_requests.crypto_asset_id
            WHERE crypto_deposit_requests.order_id = orders.id
            ORDER BY crypto_deposit_requests.id DESC
            LIMIT 1
        )";
    } elseif ($hasCustomerCryptoWallets) {
        $walletAddressSelect = "(
            SELECT customer_crypto_wallets.address
            FROM customer_crypto_wallets
            WHERE customer_crypto_wallets.customer_id = orders.customer_id
            ORDER BY customer_crypto_wallets.assigned_at DESC
            LIMIT 1
        )";

        $walletAssetSelect = "(
            SELECT customer_crypto_wallets.crypto_asset_code
            FROM customer_crypto_wallets
            WHERE customer_crypto_wallets.customer_id = orders.customer_id
            ORDER BY customer_crypto_wallets.assigned_at DESC
            LIMIT 1
        )";
    }

    $currencyJoin = schema_object_exists($db, 'currencies')
        ? 'LEFT JOIN currencies ON currencies.id = orders.currency_id'
        : '';
    $currencySelect = schema_object_exists($db, 'currencies')
        ? 'currencies.code AS currency_code'
        : "'' AS currency_code";

    return $db->select_full_user(
        "SELECT
            orders.id,
            orders.payment_method,
            orders.payment_status,
            orders.status AS order_status,
            orders.fulfillment_status,
            orders.total_amount,
            orders.created_at,
            orders.expires_at,
            customers.email AS customer_email,
            products.name AS product_name,
            product_providers.name AS provider_name,
            {$currencySelect},
            {$walletAddressSelect} AS wallet_address,
            {$walletAssetSelect} AS wallet_asset_code
         FROM orders
         INNER JOIN customers ON customers.id = orders.customer_id
         LEFT JOIN products ON products.id = orders.product_id
         LEFT JOIN product_providers ON product_providers.id = products.provider_id
         {$currencyJoin}
         WHERE (" . implode(' OR ', $whereParts) . ")
         ORDER BY orders.id DESC
         LIMIT {$limit}"
    );
}

function admin_search_customer_rows(Mysql_ks $db, string $query, int $limit = 20): array
{
    $query = trim($query);
    if ($query === '' || !schema_object_exists($db, 'customers')) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $safeLike = $db->escape('%' . $query . '%');
    $whereParts = [
        "customers.email LIKE '{$safeLike}'",
    ];

    if (ctype_digit($query)) {
        $whereParts[] = 'customers.id = ' . (int)$query;
    }

    return $db->select_full_user(
        "SELECT
            customers.id,
            customers.email,
            customers.status,
            customers.locale_code,
            customers.registered_at,
            customers.last_login_at
         FROM customers
         WHERE (" . implode(' OR ', $whereParts) . ")
         ORDER BY customers.id DESC
         LIMIT {$limit}"
    );
}

function admin_search_wallet_rows(Mysql_ks $db, string $query, int $limit = 20): array
{
    $query = trim($query);
    if ($query === '' || !schema_object_exists($db, 'customer_crypto_wallets')) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $safeLike = $db->escape('%' . $query . '%');

    return $db->select_full_user(
        "SELECT
            customer_crypto_wallets.wallet_address_id,
            customer_crypto_wallets.customer_id,
            customer_crypto_wallets.customer_email,
            customer_crypto_wallets.crypto_asset_code,
            customer_crypto_wallets.crypto_asset_name,
            customer_crypto_wallets.label,
            customer_crypto_wallets.address,
            customer_crypto_wallets.wallet_provider,
            customer_crypto_wallets.status,
            customer_crypto_wallets.assigned_at,
            customer_crypto_wallets.released_at
         FROM customer_crypto_wallets
         WHERE customer_crypto_wallets.address LIKE '{$safeLike}'
            OR customer_crypto_wallets.customer_email LIKE '{$safeLike}'
            OR customer_crypto_wallets.crypto_asset_code LIKE '{$safeLike}'
            OR customer_crypto_wallets.crypto_asset_name LIKE '{$safeLike}'
         ORDER BY customer_crypto_wallets.assigned_at DESC
         LIMIT {$limit}"
    );
}

function admin_crypto_asset_logo_url(string $assetCode): string
{
    $assetCode = strtolower(trim($assetCode));
    $baseDir = rtrim(function_exists('app_public_root_path') ? app_public_root_path() : (dirname(__DIR__, 2) . '/public_html'), '/') . '/img/crypto/';
    $direct = $assetCode !== '' ? $baseDir . $assetCode . '.png' : '';

    if ($direct !== '' && is_file($direct)) {
        return '/img/crypto/' . $assetCode . '.png';
    }

    $map = [
        'usdt' => 'tether.png',
    ];

    if ($assetCode !== '' && isset($map[$assetCode]) && is_file($baseDir . $map[$assetCode])) {
        return '/img/crypto/' . $map[$assetCode];
    }

    return '/img/crypto/blockchain.png';
}

function admin_render_search_results_html(array $resultSets, array $messages, string $query): string
{
    $title = admin_e(admin_t($messages, 'search_results_title', 'Search results'));
    $emptyTitle = admin_e(admin_t($messages, 'search_empty_title', 'No matching orders'));
    $emptyText = admin_e(admin_t($messages, 'search_empty_text', 'Try a different order ID, customer email or wallet address.'));
    $orders = isset($resultSets['orders']) && is_array($resultSets['orders']) ? $resultSets['orders'] : [];
    $customers = isset($resultSets['customers']) && is_array($resultSets['customers']) ? $resultSets['customers'] : [];
    $wallets = isset($resultSets['wallets']) && is_array($resultSets['wallets']) ? $resultSets['wallets'] : [];
    $hasResults = $orders || $customers || $wallets;

    ob_start();
    ?>
    <article class="admin-panel-card admin-search-card">
        <div class="admin-search-card__title">
            <h2><?php echo $title; ?></h2>
        </div>

        <?php if (!$hasResults): ?>
            <div class="admin-search-empty">
                <strong><?php echo $emptyTitle; ?></strong>
                <p><?php echo $emptyText; ?></p>
            </div>
        <?php else: ?>
            <?php if ($customers): ?>
                <section class="admin-search-section">
                    <div class="admin-search-section__label"><?php echo admin_e(admin_t($messages, 'search_section_users', 'Users')); ?></div>
                    <div class="table-responsive">
                        <table class="table admin-table admin-search-table align-middle">
                            <thead>
                            <tr>
                                <th><?php echo admin_e(admin_t($messages, 'col_customer', 'Customer')); ?></th>
                                <th><?php echo admin_e(admin_t($messages, 'col_status', 'Status')); ?></th>
                                <th><?php echo admin_e(admin_t($messages, 'col_locale', 'Locale')); ?></th>
                                <th><?php echo admin_e(admin_t($messages, 'col_registered', 'Registered')); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($customers as $row): ?>
                                <tr>
                                    <td>
                                        <div class="admin-search-table__primary">#<?php echo admin_e((string)$row['id']); ?></div>
                                        <div class="admin-search-table__muted"><?php echo admin_e((string)($row['email'] ?? '')); ?></div>
                                    </td>
                                    <td><div class="admin-search-table__primary"><?php echo admin_e((string)($row['status'] ?? '-')); ?></div></td>
                                    <td><div class="admin-search-table__primary"><?php echo admin_e((string)($row['locale_code'] ?? '-')); ?></div></td>
                                    <td>
                                        <div class="admin-search-table__primary"><?php echo admin_e(substr((string)($row['registered_at'] ?? ''), 0, 16)); ?></div>
                                        <div class="admin-search-table__muted"><?php echo admin_e(substr((string)($row['last_login_at'] ?? ''), 0, 16)); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($orders): ?>
                <section class="admin-search-section">
                    <div class="admin-search-section__label"><?php echo admin_e(admin_t($messages, 'search_section_orders', 'Orders')); ?></div>
                    <div class="table-responsive">
                        <table class="table admin-table admin-search-table align-middle">
                            <thead>
                            <tr>
                                <th><?php echo admin_e(admin_t($messages, 'col_order', 'Order')); ?></th>
                                <th><?php echo admin_e(admin_t($messages, 'col_customer', 'Customer')); ?></th>
                                <th><?php echo admin_e(admin_t($messages, 'col_product', 'Product')); ?></th>
                                <th><?php echo admin_e(admin_t($messages, 'col_payment', 'Payment')); ?></th>
                                <th><?php echo admin_e(admin_t($messages, 'col_wallet', 'Wallet')); ?></th>
                                <th><?php echo admin_e(admin_t($messages, 'col_status', 'Status')); ?></th>
                                <th><?php echo admin_e(admin_t($messages, 'col_created', 'Created')); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($orders as $row): ?>
                                <?php
                                $providerName = trim((string)($row['provider_name'] ?? ''));
                                $productName = trim((string)($row['product_name'] ?? ''));
                                $productLabel = trim($providerName . ' ' . $productName);
                                $paymentLabel = trim((string)($row['payment_method'] ?? ''));
                                $paymentStatus = trim((string)($row['payment_status'] ?? ''));
                                $walletAddress = trim((string)($row['wallet_address'] ?? ''));
                                $walletAsset = trim((string)($row['wallet_asset_code'] ?? ''));
                                $currencyCode = trim((string)($row['currency_code'] ?? ''));
                                ?>
                                <tr>
                                    <td>
                                        <div class="admin-search-table__primary">#<?php echo admin_e((string)$row['id']); ?></div>
                                        <div class="admin-search-table__muted">
                                            <?php echo admin_e(number_format((float)($row['total_amount'] ?? 0), 2, '.', '')); ?>
                                            <?php echo admin_e($currencyCode); ?>
                                        </div>
                                    </td>
                                    <td><div class="admin-search-table__primary"><?php echo admin_e((string)($row['customer_email'] ?? '')); ?></div></td>
                                    <td><div class="admin-search-table__primary"><?php echo admin_e($productLabel !== '' ? $productLabel : '-'); ?></div></td>
                                    <td>
                                        <div class="admin-search-table__primary"><?php echo admin_e($paymentLabel !== '' ? $paymentLabel : '-'); ?></div>
                                        <div class="admin-search-table__muted"><?php echo admin_e($paymentStatus !== '' ? $paymentStatus : '-'); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($walletAddress !== ''): ?>
                                            <div class="admin-search-table__wallet">
                                                <code><?php echo admin_e($walletAddress); ?></code>
                                                <?php if ($walletAsset !== ''): ?>
                                                    <span><?php echo admin_e($walletAsset); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="admin-search-table__muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="admin-search-table__primary"><?php echo admin_e((string)($row['order_status'] ?? '-')); ?></div>
                                        <div class="admin-search-table__muted"><?php echo admin_e((string)($row['fulfillment_status'] ?? '-')); ?></div>
                                    </td>
                                    <td>
                                        <div class="admin-search-table__primary"><?php echo admin_e(substr((string)($row['created_at'] ?? ''), 0, 16)); ?></div>
                                        <div class="admin-search-table__muted"><?php echo admin_e(substr((string)($row['expires_at'] ?? ''), 0, 16)); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($wallets): ?>
                <section class="admin-search-section">
                    <div class="admin-search-section__label"><?php echo admin_e(admin_t($messages, 'search_section_wallets', 'Crypto wallets')); ?></div>
                    <div class="admin-search-wallet-grid">
                        <?php foreach ($wallets as $row): ?>
                            <?php
                            $assetCode = trim((string)($row['crypto_asset_code'] ?? ''));
                            $assetName = trim((string)($row['crypto_asset_name'] ?? ''));
                            $logoUrl = admin_crypto_asset_logo_url($assetCode);
                            ?>
                            <article class="admin-search-wallet-card">
                                <div class="admin-search-wallet-card__asset">
                                    <img src="<?php echo admin_e($logoUrl); ?>" alt="<?php echo admin_e($assetName !== '' ? $assetName : $assetCode); ?>">
                                    <div>
                                        <strong><?php echo admin_e($assetName !== '' ? $assetName : $assetCode); ?></strong>
                                        <span><?php echo admin_e($assetCode !== '' ? $assetCode : '-'); ?></span>
                                    </div>
                                </div>
                                <div class="admin-search-wallet-card__body">
                                    <code><?php echo admin_e((string)($row['address'] ?? '')); ?></code>
                                    <div class="admin-search-wallet-card__meta">
                                        <span><?php echo admin_e(admin_t($messages, 'search_wallet_user_label', 'User')); ?>: <?php echo admin_e((string)($row['customer_email'] ?? '')); ?></span>
                                        <span><?php echo admin_e(admin_t($messages, 'col_status', 'Status')); ?>: <?php echo admin_e((string)($row['status'] ?? '-')); ?></span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </article>
    <?php

    return trim((string)ob_get_clean());
}

function admin_page_cards(string $route, array $messages): array
{
    $cards = [
        'orders' => ['title' => admin_t($messages, 'page_orders_card_title', 'Orders'), 'text' => admin_t($messages, 'page_orders_card_text', 'Create new subscriptions and review all customer orders.')],
        'products' => ['title' => admin_t($messages, 'page_products_card_title', 'Products'), 'text' => admin_t($messages, 'page_products_card_text', 'Review active packages and subscription times from the new database.')],
        'users' => ['title' => admin_t($messages, 'page_users_card_title', 'Users'), 'text' => admin_t($messages, 'page_users_card_text', 'Create, edit and secure customer accounts.')],
        'payments' => ['title' => admin_t($messages, 'page_payments_card_title', 'Payments'), 'text' => admin_t($messages, 'page_payments_card_text', 'Manage crypto and bank transfer requests.')],
        'bank-accounts' => ['title' => admin_t($messages, 'page_bank_accounts_card_title', 'Bank accounts'), 'text' => admin_t($messages, 'page_bank_accounts_card_text', 'Assign and activate bank accounts manually.')],
        'crypto-wallets' => ['title' => admin_t($messages, 'page_crypto_wallets_card_title', 'Crypto wallets'), 'text' => admin_t($messages, 'page_crypto_wallets_card_text', 'Manage wallet pools and customer assignments.')],
        'cryptocurrencies' => ['title' => admin_t($messages, 'page_cryptocurrencies_card_title', 'Cryptocurrencies'), 'text' => admin_t($messages, 'page_cryptocurrencies_card_text', 'Enable or disable crypto assets and edit their labels, networks and notes.')],
        'news' => ['title' => admin_t($messages, 'page_news_card_title', 'News'), 'text' => admin_t($messages, 'page_news_card_text', 'Publish announcements visible to customers.')],
        'email-templates' => ['title' => admin_t($messages, 'page_email_templates_card_title', 'Email templates'), 'text' => admin_t($messages, 'page_email_templates_card_text', 'Prepare message templates and transactional content.')],
        'faq' => ['title' => admin_t($messages, 'page_faq_card_title', 'FAQ'), 'text' => admin_t($messages, 'page_faq_card_text', 'Edit quick questions and static help answers.')],
        'settings' => ['title' => admin_t($messages, 'page_settings_card_title', 'Settings'), 'text' => admin_t($messages, 'page_settings_card_text', 'Configure site data, branding and feature switches.')],
    ];

    return isset($cards[$route]) ? $cards[$route] : ['title' => '', 'text' => ''];
}
