<?php

switch ($site) {
    case 'register':
        $registrationEnabled = (int)($settings['active_register'] ?? 0) === 1;
        $referralsEnabled = app_referrals_enabled($settings);

        if ($user['logged'] || !$registrationEnabled) {
            if (!$registrationEnabled) {
                $smarty->assign('alert_error', localization_translate($t, 'registration_disabled_notice', 'Registration is currently disabled.'));
                $smarty->display('alert.tpl');
            }

            $smarty->display('no_access.tpl');
            break;
        }

        $referrer = null;
        if ($referralsEnabled && isset($_SESSION['ref'])) {
            $referrer = app_find_customer_by_id($db, (int)$_SESSION['ref']);
        }
        $smarty->assign('ref', $referrer);
        $smarty->assign('referrals_enabled', $referralsEnabled);

        $buildCaptchaMarkup = static function () {
            if (!function_exists('gd_info')) {
                $_SESSION['captcha'] = ['code' => (string)mt_rand(1000, 9999), 'image_src' => ''];
                return '<div class="captcha captcha-fallback"><strong>' . $_SESSION['captcha']['code'] . '</strong></div>';
            }

            include_once 'captcha/simple-php-captcha.php';
            $generatedCaptcha = simple_php_captcha_inline([
                'characters' => '0123456789',
            ]);
            $_SESSION['captcha'] = [
                'code' => $generatedCaptcha['code'],
            ];

            return '<img src="' . $generatedCaptcha['image_src'] . '" class="captcha" alt="CAPTCHA code">';
        };

        if (isset($_POST['register']) && isset($_POST['captcha'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('captcha', $buildCaptchaMarkup());
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
                $smarty->display('alert.tpl');
                $smarty->display('register.tpl');
                break;
            }

            $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
            $password = isset($_POST['password']) ? trim((string)$_POST['password']) : '';
            $passwordRepeat = isset($_POST['password_repeat']) ? trim((string)$_POST['password_repeat']) : '';
            $referralEmail = $referralsEnabled ? trim((string)($_POST['referral_email'] ?? '')) : '';
            $captchaCode = isset($_POST['captcha']) ? trim((string)$_POST['captcha']) : '';
            $expectedCaptchaCode = (string)($_SESSION['captcha']['code'] ?? '');

            $blockedDomains = [
                '10minut.xyz',
                'armyspy.com',
                'cliptik.net',
                'cuvox.de',
                'eos2mail.com',
                'ezehe.com',
                'geroev.net',
                'getnada.com',
                'grr.la',
                'guerrillamail.info',
                'mail4gmail.com',
                'mailox.fun',
                'mhmmmkumen.ml',
                'mozillafirefox.cf',
                'mymail90.com',
                'nwytg.net',
                'pacz.to',
                'plutofox.com',
                'pokemail.net',
                'providier.com',
                'sharklasers.com',
                'shayzam.net',
                'spindl-e.com',
                'stelkendh00.ga',
                'superrito.com',
                'vmani.com',
                'wimsg.com',
                'xgmailoo.com',
                'yahooproduct.net',
                'yopmail.com',
                'zdfpost.net',
            ];

            $emailDomain = '';
            if (strpos($email, '@') !== false) {
                $emailParts = explode('@', $email, 2);
                $emailDomain = strtolower($emailParts[1]);
            }

            $validator = new Validator(Validator::AUTO_EMPTY);
            $validator->set_min_length(4);
            $validator->set_max_length(40);

            $validator->add($email);
            $validator->empty_field()->val(localization_translate($t, 'register_error_email_required'));
            $validator->email()->val(localization_translate($t, 'register_error_email_invalid'));

            if (in_array($emailDomain, $blockedDomains, true)) {
                $validator->user_check(false, localization_translate($t, 'register_error_fake_email'));
            }

            if (app_find_customer_by_email($db, $email)) {
                $validator->user_check(false, localization_translate($t, 'register_error_email_exists'));
            }

            $validator->add($password);
            $validator->empty_field()->val(localization_translate($t, 'register_error_password_required'));
            $validator->min()->val(localization_translate($t, 'register_error_password_short'));
            $validator->max()->val(localization_translate($t, 'register_error_password_long'));
            $validator->equals($password, $passwordRepeat, localization_translate($t, 'register_error_password_mismatch'));

            $validator->add($captchaCode);
            $validator->empty_field()->val(localization_translate($t, 'register_error_captcha_required'));

            $errors = $validator->exe();

            if ($captchaCode !== $expectedCaptchaCode) {
                $errors[] = localization_translate($t, 'register_error_captcha');
            }

            $activeResellerId = tenant_current_id($reseller);
            if ($activeResellerId <= 0) {
                $fallbackReseller = tenant_fetch_installation_profile($db);
                $activeResellerId = tenant_current_id($fallbackReseller);
            }

            if ($activeResellerId <= 0) {
                $errors[] = localization_translate($t, 'register_error_reseller_missing');
            }

            $referrerUser = null;
            if ($referralsEnabled && $referrer && !empty($referrer['email'])) {
                $referralEmail = trim((string)$referrer['email']);
                $referrerUser = $referrer;
            }

            if ($referralsEnabled && $referrerUser === null && $referralEmail !== '') {
                $referrerUser = app_find_customer_by_email($db, $referralEmail);
                if (!$referrerUser || !app_customer_is_active($referrerUser)) {
                    $errors[] = localization_translate($t, 'register_error_referral');
                }
            }

            if ($errors) {
                $smarty->assign('captcha', $buildCaptchaMarkup());
                $smarty->assign('errors', $errors);
                $smarty->display('alert.tpl');
                $smarty->display('register.tpl');
                break;
            }

            $createdUserId = app_insert_customer_registration(
                $db,
                $email,
                $password,
                $current_locale ?? 'en',
                $time,
                $ip,
                $activeResellerId
            );

            if ($createdUserId > 0) {
                app_queue_account_created_notification($db, $createdUserId);
            }

            if ($referralsEnabled && $referrerUser && $createdUserId > 0 && (int)$referrerUser['id'] !== $createdUserId) {
                app_attach_referral_to_customer($db, (int)$referrerUser['id'], $createdUserId);
            }

            unset($_SESSION['ref']);

            $smarty->assign('registered_email', $email);
            $smarty->display('register_correct.tpl');
            break;
        }

        $smarty->assign('captcha', $buildCaptchaMarkup());
        $smarty->display('register.tpl');
        break;
}

?>
