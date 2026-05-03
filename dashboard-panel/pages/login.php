<?php

switch ($site) {
    case 'login':
        if ($user['logged']) {
            $smarty->display('no_access.tpl');
            break;
        }

        if (isset($_GET['aktywuj_user'])) {
            $activationUserId = (int)$_GET['aktywuj_user'];
            $activationUser = app_find_customer_by_id($db, $activationUserId);

            if (is_array($activationUser) && !app_customer_is_active($activationUser)) {
                app_activate_customer($db, $activationUserId);
                $smarty->assign('alert', 'Account activated successfully.');
            } else {
                $smarty->assign('alert', 'Account is already active.');
            }

            $smarty->display('alert.tpl');
        }

        if (isset($_POST['login']) && isset($_POST['email']) && isset($_POST['password'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
                $smarty->display('alert.tpl');
                $smarty->display('login.tpl');
                break;
            }

            $email = trim((string)$_POST['email']);
            $password = trim((string)$_POST['password']);
            $account = app_find_customer_by_email($db, $email);

            if (
                is_array($account)
                && isset($account['email'])
                && $account['email'] === $email
                && app_verify_customer_password($account, $password)
                && app_customer_is_active($account)
            ) {
                if (!headers_sent()) {
                    session_regenerate_id(true);
                }
                $_SESSION['id'] = (int)$account['id'];
                $_SESSION['lang'] = isset($account['locale_code'])
                    ? localization_normalize_locale($account['locale_code'])
                    : localization_from_legacy_value($account['lang'] ?? 0);
                $_SESSION['customer_login_state_needs_sync'] = 1;
                $_SESSION['customer_login_state_customer_id'] = (int)$account['id'];
                $_SESSION['customer_last_seen_touch_ts'] = time();

                $account['logged'] = 1;
                $smarty->assign('user', $account);
                $smarty->display('login_correct.tpl');
                break;
            }

            if (!$account) {
                $alertError = localization_translate($t, 'login_error_invalid');
            } elseif (app_customer_is_blocked($account)) {
                $alertError = localization_translate($t, 'login_error_blocked');
            } elseif (!app_customer_is_active($account)) {
                $alertError = localization_translate($t, 'login_error_inactive');
            } else {
                $alertError = localization_translate($t, 'login_error_invalid');
            }

            $smarty->assign('alert_error', $alertError);
            $smarty->display('alert.tpl');
        }

        $smarty->display('login.tpl');
        break;
}

?>
