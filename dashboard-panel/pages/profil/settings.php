<?php

switch ($site) {
    case 'settings':
        if (!$user['logged']) {
            $smarty->display('no_access.tpl');
            break;
        }

        $canEditMessengerIdentity = chat_customer_can_edit_messenger_identity($user, is_array($settings ?? null) ? $settings : []);
        $smarty->assign('settings_can_edit_messenger_identity', $canEditMessengerIdentity);
        $canEditPublicHandle = false;
        $smarty->assign('settings_can_edit_public_handle', $canEditPublicHandle);

        $smarty->assign('settings_open_password_modal', false);
        $sendSettingsJson = static function (array $payload): void {
            $jsonPrefix = '__SETTINGS_JSON__';

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            echo $jsonPrefix . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        };

        if (isset($_POST['action']) && (string)$_POST['action'] === 'upload_avatar') {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $sendSettingsJson([
                    'ok' => false,
                    'message' => localization_translate($t, 'csrf_invalid'),
                ]);
            }

            if (!$canEditMessengerIdentity) {
                $sendSettingsJson([
                    'ok' => false,
                    'message' => localization_translate($t, 'settings_avatar_upload_error'),
                ]);
            }

            $uploadResult = app_store_customer_avatar_upload($_FILES['avatar_file'] ?? [], (int)($user['id'] ?? 0));
            if (empty($uploadResult['ok'])) {
                $errorCode = (string)($uploadResult['code'] ?? '');
                $messageKey = 'settings_avatar_upload_error';
                if ($errorCode === 'invalid_type') {
                    $messageKey = 'settings_avatar_upload_invalid_type';
                } elseif ($errorCode === 'too_large') {
                    $messageKey = 'settings_avatar_upload_too_large';
                }

                $sendSettingsJson([
                    'ok' => false,
                    'message' => localization_translate($t, $messageKey),
                ]);
            }

            $newAvatarUrl = app_customer_avatar_url((string)($uploadResult['url'] ?? ''));
            $currentAvatarUrl = app_customer_avatar_url((string)($user['avatar_url'] ?? ''));
            $updated = $db->update_using_id(['avatar_url'], [$newAvatarUrl !== '' ? $newAvatarUrl : null], 'customers', (int)$user['id']);

            if (!$updated) {
                app_delete_customer_avatar_file($newAvatarUrl);
                $sendSettingsJson([
                    'ok' => false,
                    'message' => localization_translate($t, 'settings_avatar_upload_error'),
                ]);
            }

            if ($currentAvatarUrl !== '' && $currentAvatarUrl !== $newAvatarUrl) {
                app_delete_customer_avatar_file($currentAvatarUrl);
            }

            $sendSettingsJson([
                'ok' => true,
                'url' => $newAvatarUrl,
                'message' => localization_translate($t, 'settings_avatar_upload_success'),
            ]);
        }

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
            $resolvedHandle = [
                'ok' => true,
                'handle' => (string)($user['public_handle'] ?? ''),
            ];
            $avatarUrl = app_customer_avatar_url((string)($user['avatar_url'] ?? ''));

            if ($canEditPublicHandle) {
                $resolvedHandle = app_resolve_customer_public_handle(
                    $db,
                    (string)($_POST['public_handle'] ?? ''),
                    (string)($user['email'] ?? ''),
                    (int)($user['id'] ?? 0)
                );

                if (empty($resolvedHandle['ok'])) {
                    $user['public_handle'] = app_normalize_customer_public_handle((string)($_POST['public_handle'] ?? ''));
                    $user['avatar_url'] = $avatarUrl;
                    $user['lang'] = localization_to_legacy_value($selectedLocale);
                    $user['lang_code'] = $selectedLocale;
                    $user['locale_code'] = $selectedLocale;
                    $user['email_notification'] = $emailNotificationEnabled ? 1 : 0;
                    $user['is_newsletter_subscribed'] = $user['email_notification'];
                    $_SESSION['lang'] = $selectedLocale;
                    $smarty->assign('user', $user);
                    $smarty->assign('current_locale', $selectedLocale);
                    $smarty->assign('alert_error', (string)($resolvedHandle['message'] ?? localization_translate($t, 'settings_handle_taken', 'This username is already taken.')));
                    $smarty->display('alert.tpl');
                    $smarty->display('profil/settings.tpl');
                    break;
                }
            }

            $_SESSION['lang'] = $selectedLocale;
            $user['lang'] = localization_to_legacy_value($selectedLocale);
            $user['lang_code'] = $selectedLocale;
            $user['locale_code'] = $selectedLocale;
            $user['email_notification'] = $emailNotificationEnabled ? 1 : 0;
            $user['is_newsletter_subscribed'] = $user['email_notification'];
            $user['public_handle'] = (string)($resolvedHandle['handle'] ?? '');
            if ($canEditMessengerIdentity) {
                $user['avatar_url'] = $avatarUrl;
            }

            app_update_customer_locale($db, (int)$user['id'], $selectedLocale);
            app_update_customer_email_notification($db, (int)$user['id'], $emailNotificationEnabled);
            if ($canEditMessengerIdentity) {
                $db->update_using_id(
                    ['avatar_url'],
                    [$avatarUrl !== '' ? $avatarUrl : null],
                    'customers',
                    (int)$user['id']
                );
            }

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
