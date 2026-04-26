<?php

switch ($site) {
    case 'change-password':
        if (!$user['logged']) {
            $smarty->display('no_access.tpl');
            break;
        }

        if (isset($_POST['change_password'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
                $smarty->display('alert.tpl');
                $smarty->display('profil/change_password.tpl');
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
            $smarty->display('alert.tpl');
        }

        $smarty->display('profil/change_password.tpl');
        break;
}

?>
