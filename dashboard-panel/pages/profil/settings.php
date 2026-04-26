<?php

switch ($site) {
    case 'settings':
        if (!$user['logged']) {
            $smarty->display('no_access.tpl');
            break;
        }

        $smarty->assign('settings_open_password_modal', false);

        if (isset($_POST['change_password'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('settings_open_password_modal', true);
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
                $smarty->display('alert.tpl');
                $smarty->display('profil/settings.tpl');
                break;
            }

            $currentPassword = isset($_POST['current_password']) ? trim((string)$_POST['current_password']) : '';
            $newPassword = isset($_POST['new_password']) ? trim((string)$_POST['new_password']) : '';
            $repeatPassword = isset($_POST['new_password_repeat']) ? trim((string)$_POST['new_password_repeat']) : '';

            $validator = new Validator(Validator::AUTO_EMPTY);
            $validator->set_min_length(8);
            $validator->set_max_length(72);

            $validator->add($currentPassword);
            $validator->empty_field()->val(localization_translate($t, 'change_password_error_current_required'));
            $validator->user_check(app_verify_customer_password($user, $currentPassword), localization_translate($t, 'change_password_error_current_invalid'));

            $validator->add($newPassword);
            $validator->empty_field()->val(localization_translate($t, 'change_password_error_new_required'));
            $validator->min()->val(localization_translate($t, 'change_password_error_new_short'));
            $validator->max()->val(localization_translate($t, 'change_password_error_new_long'));

            $validator->equals($newPassword, $repeatPassword, localization_translate($t, 'change_password_error_mismatch'));

            $errors = $validator->exe();

            if (!$errors) {
                app_store_customer_password($db, (int)$user['id'], $newPassword);

                if (schema_object_exists($db, 'user_online')) {
                    $onlineEntry = $db->select('user_online', '*', "WHERE user=" . (int)$user['id']);
                    if ($onlineEntry) {
                        $db->delete_using_id('user_online', $onlineEntry['id']);
                    }
                }

                unset($_SESSION['id']);

                $smarty->assign('alert', localization_translate($t, 'change_password_success'));
                $smarty->display('alert.tpl');
                $smarty->display('login.tpl');
                break;
            }

            $smarty->assign('errors', $errors);
            $smarty->assign('settings_open_password_modal', true);
            $smarty->display('alert.tpl');
        }

        if (isset($_POST['save_settings']) || isset($_POST['save_language'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
                $smarty->display('alert.tpl');
                $smarty->display('profil/settings.tpl');
                break;
            }

            $selectedLocale = isset($_POST['lang']) ? localization_normalize_locale($_POST['lang']) : 'en';
            $emailNotificationEnabled = isset($_POST['email_notification']) && (string)($_POST['email_notification'] ?? '') === '1';

            $_SESSION['lang'] = $selectedLocale;
            $user['lang'] = localization_to_legacy_value($selectedLocale);
            $user['lang_code'] = $selectedLocale;
            $user['locale_code'] = $selectedLocale;
            $user['email_notification'] = $emailNotificationEnabled ? 1 : 0;
            $user['is_newsletter_subscribed'] = $user['email_notification'];

            app_update_customer_locale($db, (int)$user['id'], $selectedLocale);
            app_update_customer_email_notification($db, (int)$user['id'], $emailNotificationEnabled);

            $localization = localization_load($selectedLocale, dirname(__DIR__, 2));
            $t = $localization['messages'];

            $smarty->assign('t', $t);
            $smarty->assign('current_locale', $selectedLocale);
            $smarty->assign('supported_locales', $localization['supported_locales']);
            $smarty->assign('html_lang', $selectedLocale);

            $smarty->assign('alert', localization_translate($t, 'settings_saved', localization_translate($t, 'language_saved')));

            $smarty->assign('user', $user);
            $smarty->display('alert.tpl');
        }

        if (isset($_GET['platnosc_zla'])) {
            unset($_SESSION['id_zamowienia']);
            $smarty->assign('alert_error', 'Payment was not completed. Please try again.');
            $smarty->display('alert.tpl');
        }

        if (isset($_GET['platnosc_ok'])) {
            unset($_SESSION['id_zamowienia']);
            $smarty->assign('alert', 'Payment has been confirmed successfully.');
            $smarty->display('alert.tpl');
        }

        $smarty->display('profil/settings.tpl');
        break;
}

?>
