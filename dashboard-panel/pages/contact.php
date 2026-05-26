<?php

switch ($site) {
    case 'contact':
        $contactForm = [
            'subject' => 'service_issues',
            'email' => isset($user['email']) ? (string)$user['email'] : '',
            'message' => '',
            'send_copy' => false,
        ];

        $contactSubjectOptions = [
            'service_issues' => localization_translate($t, 'contact_subject_service_issues'),
            'other_problem' => localization_translate($t, 'contact_subject_other_problem'),
        ];
        $smarty->assign('contact_subject_options', $contactSubjectOptions);

        $contactFormDisabled = !app_contact_form_enabled($settings);
        $smarty->assign('contact_form_disabled', $contactFormDisabled);

        if (isset($_POST['send_contact']) || isset($_POST['wyslij']) || isset($_POST['wyslij2'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('contact_form', $contactForm);
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
                $smarty->display('alert.tpl');
                $smarty->display('contact.tpl');
                break;
            }

            $contactForm['subject'] = isset($_POST['subject']) ? trim((string)$_POST['subject']) : (isset($_POST['tytul']) ? trim((string)$_POST['tytul']) : $contactForm['subject']);
            $contactForm['email'] = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
            $contactForm['message'] = isset($_POST['message']) ? trim((string)$_POST['message']) : (isset($_POST['tresc']) ? trim((string)$_POST['tresc']) : '');
            $contactForm['send_copy'] = isset($_POST['send_copy']) || isset($_POST['chce_kopie']) || isset($_POST['chce_kopie2']);
            $smarty->assign('contact_form', $contactForm);

            if ($contactFormDisabled) {
                $smarty->assign('alert_error', localization_translate($t, 'contact_disabled_notice'));
                $smarty->display('alert.tpl');
                $smarty->display('contact.tpl');
                break;
            }

            if (app_contact_rate_limit_remaining() > 0) {
                $smarty->assign('alert_error', localization_translate($t, 'contact_rate_limited'));
                $smarty->display('alert.tpl');
                $smarty->display('contact.tpl');
                break;
            }

            $validator = new Validator(Validator::AUTO_EMPTY);

            $validator->add($contactForm['email']);
            $validator->empty_field()->val(localization_translate($t, 'contact_error_email_required'));
            $validator->email()->val(localization_translate($t, 'contact_error_email_invalid'));

            $validator->add($contactForm['subject']);
            $validator->empty_field()->val(localization_translate($t, 'contact_error_subject_required'));
            $validator->min(3)->val(localization_translate($t, 'contact_error_subject_short'));
            $validator->max(60)->val(localization_translate($t, 'contact_error_subject_long'));

            $validator->add($contactForm['message']);
            $validator->empty_field()->val(localization_translate($t, 'contact_error_message_required'));
            $errors = $validator->exe();

            if (!isset($contactSubjectOptions[$contactForm['subject']])) {
                $errors[] = localization_translate($t, 'contact_error_subject_required');
            }

            if ($errors) {
                $smarty->assign('errors', $errors);
                $smarty->display('alert.tpl');
                $smarty->display('contact.tpl');
                break;
            }

            $supportEmail = app_email_support_recipient($settings);
            if ($supportEmail === '' || !app_email_smtp_is_configured($settings)) {
                $smarty->assign('alert_error', localization_translate($t, 'contact_error_not_configured'));
                $smarty->display('alert.tpl');
                $smarty->display('contact.tpl');
                break;
            }

            $email = strtolower($contactForm['email']);
            $subjectLabel = $contactSubjectOptions[$contactForm['subject']];
            $clientIp = isset($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '';
            $contactLocaleCode = app_normalize_email_locale((string)($_SESSION['lang'] ?? ($user['locale_code'] ?? ($settings['default_locale_code'] ?? 'en'))));

            try {
                $supportMail = app_email_mailer($settings);
                $supportMail->clearReplyTos();
                $supportMail->addReplyTo($email);
                $supportMail->addAddress($supportEmail);
                $supportMail->Subject = app_email_subject(
                    app_contact_subject_prefix($settings, $subjectLabel),
                    'Contact form message'
                );
                $supportMail->Body = app_email_wrap_html(
                    $settings,
                    app_contact_support_body_html(
                        $settings,
                        $email,
                        $subjectLabel,
                        $contactForm['message'],
                        $clientIp,
                        $contactLocaleCode
                    ),
                    $contactLocaleCode
                );
                $supportMail->AltBody = app_contact_support_body(
                    $settings,
                    $email,
                    $subjectLabel,
                    $contactForm['message'],
                    $clientIp
                );
                $supportMail->send();

                $copySent = false;
                $copyRequested = !empty($contactForm['send_copy']);
                if ($copyRequested) {
                    try {
                        $copyMail = app_email_mailer($settings);
                        $copyMail->addAddress($email);
                        $copyMail->Subject = app_email_subject(
                            localization_translate($t, 'contact_copy_subject', 'Contact form confirmation'),
                            'Contact form confirmation'
                        );
                        $copyMail->Body = app_email_wrap_html(
                            $settings,
                            app_contact_copy_body_html(
                                $settings,
                                $email,
                                $subjectLabel,
                                $contactForm['message'],
                                $contactLocaleCode
                            ),
                            $contactLocaleCode
                        );
                        $copyMail->AltBody = app_contact_copy_body($settings, $subjectLabel, $contactForm['message']);
                        $copyMail->send();
                        $copySent = true;
                    } catch (\Throwable $copyException) {
                        $copyError = $copyException->getMessage();
                        error_log('[contact-copy-send-failed] ' . $copyError);
                    }
                }

                app_contact_mark_sent();

                $successMessage = localization_translate($t, 'contact_success');
                if ($copyRequested && $copySent) {
                    $successMessage .= ' ' . localization_translate($t, 'contact_copy_success');
                }

                $contactForm = [
                    'subject' => 'service_issues',
                    'email' => isset($user['email']) ? (string)$user['email'] : '',
                    'message' => '',
                    'send_copy' => false,
                ];
                $smarty->assign('contact_form', $contactForm);
                $smarty->assign('alert', $successMessage);
                if ($copyRequested && !$copySent) {
                    $smarty->assign('alert_error', localization_translate($t, 'contact_copy_failed'));
                }
                $smarty->display('alert.tpl');
            } catch (\Throwable $exception) {
                $smarty->assign('alert_error', localization_translate($t, 'contact_error_not_configured'));
                $smarty->display('alert.tpl');
            }
        }

        $pagesTable = schema_read_target($db, 'pages');
        $pageContentColumn = schema_read_column($db, 'pages', 'content', 'tresc');
        $podstrona = $db->select_using_id($pagesTable, $pageContentColumn, 7);
        if (is_array($podstrona) && isset($podstrona[$pageContentColumn]) && !isset($podstrona['tresc'])) {
            $podstrona['tresc'] = $podstrona[$pageContentColumn];
        }
        $smarty->assign('podstrona', $podstrona);
        $smarty->assign('contact_form', $contactForm);
        $smarty->assign('contact_form_disabled', $contactFormDisabled);

        $smarty->display('contact.tpl');
        break;
}
?>
