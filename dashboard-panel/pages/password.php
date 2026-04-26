<?php

switch ($site) {
    case 'password':
        if ($user['logged']) {
            $smarty->display('no_access.tpl');
            break;
        }

        if (isset($_POST['reset_password'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
                $smarty->display('alert.tpl');
                $smarty->display('password.tpl');
                break;
            }

            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $account = app_find_customer_by_email($db, $email);
            $smtpConfigured = app_email_smtp_is_configured($settings);

            if (!$account) {
                $smarty->assign('alert_error', localization_translate($t, 'password_error_not_found'));
                $smarty->display('alert.tpl');
            } elseif (!$smtpConfigured) {
                $smarty->assign('alert_error', localization_translate($t, 'password_error_not_configured'));
                $smarty->display('alert.tpl');
            } else {
                if (!isset($_SESSION['password_recovery_time'])) {
                    $_SESSION['password_recovery_time'] = 0;
                }

                if (time() < ((int)$_SESSION['password_recovery_time'] + 30)) {
                    $smarty->assign('alert_error', localization_translate($t, 'password_error_rate_limited'));
                    $smarty->display('alert.tpl');
                } else {
                    $_SESSION['password_recovery_time'] = time();
                    $customerEmail = strtolower(trim((string)($account['email'] ?? $email)));
                    $localeCode = isset($account['locale_code'])
                        ? (string)$account['locale_code']
                        : localization_from_legacy_value($account['lang'] ?? 0);
                    $mailLocale = app_normalize_email_locale($localeCode);
                    $newPassword = app_generate_customer_password(12);

                    try {
                        $mailTemplate = app_email_template_row($db, 'password-reset');
                        $mailTranslation = is_array($mailTemplate) && !empty($mailTemplate['id'])
                            ? app_email_template_translation_row($db, (int)$mailTemplate['id'], $mailLocale)
                            : null;
                        $subjectTemplate = is_array($mailTranslation) && trim((string)($mailTranslation['subject'] ?? '')) !== ''
                            ? (string)$mailTranslation['subject']
                            : (string)($mailTemplate['subject'] ?? 'Your new password');
                        $bodyTemplate = is_array($mailTranslation) && trim((string)($mailTranslation['body_text'] ?? '')) !== ''
                            ? (string)$mailTranslation['body_text']
                            : (string)($mailTemplate['body_html'] ?? 'Your new password is: {password}');

                        $mailSubject = app_email_subject(
                            app_email_render($subjectTemplate, [
                                'customer_email' => $customerEmail,
                                'email' => $customerEmail,
                                'password' => $newPassword,
                                'nowehaslo' => $newPassword,
                                'site_name' => (string)($settings['page_name'] ?? 'Subscription Panel'),
                                'site_url' => (string)($settings['page_url'] ?? ''),
                                'pagename' => (string)($settings['page_name'] ?? 'Subscription Panel'),
                                'pageurl' => (string)($settings['page_url'] ?? ''),
                            ]),
                            'Password recovery'
                        );
                        $mailBody = app_email_render($bodyTemplate, [
                            'customer_email' => $customerEmail,
                            'email' => $customerEmail,
                            'password' => $newPassword,
                            'nowehaslo' => $newPassword,
                            'site_name' => (string)($settings['page_name'] ?? 'Subscription Panel'),
                            'site_url' => (string)($settings['page_url'] ?? ''),
                            'pagename' => (string)($settings['page_name'] ?? 'Subscription Panel'),
                            'pageurl' => (string)($settings['page_url'] ?? ''),
                        ]);

                        if (app_email_plain_text($mailBody) === '') {
                            throw new RuntimeException('Password recovery email body is empty.');
                        }

                        if (!app_store_customer_password($db, (int)$account['id'], $newPassword)) {
                            $smarty->assign('alert_error', localization_translate($t, 'password_error_save_failed'));
                            $smarty->display('alert.tpl');
                            $smarty->display('password.tpl');
                            break;
                        }

                        $mail = app_email_mailer($settings);
                        $mail->addAddress($customerEmail);
                        $mail->Subject = $mailSubject;
                        $mail->Body = $mailBody;
                        $mail->AltBody = app_email_plain_text($mailBody);
                        $mail->send();

                        $smarty->assign('alert', localization_translate($t, 'password_success'));
                        $smarty->display('alert.tpl');
                    } catch (Exception $exception) {
                        app_restore_customer_password_snapshot($db, $account);
                        $smarty->assign('alert_error', localization_translate($t, 'password_error_send_failed'));
                        $smarty->display('alert.tpl');
                    }
                }
            }
        }

        $smarty->display('password.tpl');
        break;
}

?>
