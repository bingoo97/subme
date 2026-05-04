<?php

$appsUrl = '';
$appSettings = app_fetch_settings($db);
if (is_array($appSettings) && !empty($appSettings['apps_url'])) {
    $appsUrl = trim((string)$appSettings['apps_url']);
}
$applicationInstructionsEnabled = app_application_instructions_enabled($appSettings);

app_ensure_system_content_pages_runtime($db);
$currentStaticPageLocale = app_static_page_normalize_locale($currentLocale ?? ($user['locale_code'] ?? 'en'));

$instructionGuides = app_instruction_guides_seed_data($t, $applicationInstructionsEnabled);

if ($site === 'instructions') {
    $staticPage = app_static_page_find_for_route($db, 'instructions', $currentStaticPageLocale);
    $staticPageBody = is_array($staticPage) && !empty($staticPage['id'])
        ? (string)($staticPage['body'] ?? '')
        : app_static_page_body_instructions($t);
    $smarty->assign(
        'static_page_body_html',
        app_render_static_page_body(
            $staticPageBody,
            [
                'instructions_cards' => app_render_instruction_guides_markup($instructionGuides),
            ]
        )
    );
    $smarty->display('static_page.tpl');
    return;
}

$guideMap = [
    'instruction-trust-wallet' => localization_translate($t, 'instructions_trust_wallet'),
    'instruction-revolut' => localization_translate($t, 'instructions_revolut'),
    'instruction-crypto-exchange' => localization_translate($t, 'instructions_crypto_exchange'),
    'instruction-smart-iptv' => localization_translate($t, 'instructions_smart_iptv'),
    'instruction-ott-player' => localization_translate($t, 'instructions_ott_player'),
    'instruction-newlook' => localization_translate($t, 'instructions_newlook'),
];

if (isset($guideMap[$site])) {
    if (in_array($site, ['instruction-smart-iptv', 'instruction-ott-player', 'instruction-newlook'], true) && !$applicationInstructionsEnabled) {
        header('Location: /instructions');
        exit;
    }

    $supportButtonInline = '';
    $supportButtonFooter = '';
    if (!empty($settings['support_chat_enabled'])) {
        $supportButtonMarkup = '<button type="button" class="btn btn-danger btn-lg payment-support-button" onclick="return openMessengerPanel(\''
            . (int)($user['id'] ?? 0)
            . '\');"><i class="fa fa-life-ring" aria-hidden="true"></i> '
            . htmlspecialchars(localization_translate($t, 'instructions_contact_support', 'Contact support'), ENT_QUOTES, 'UTF-8')
            . '</button>';

        if ($site === 'instruction-trust-wallet') {
            $supportButtonInline = $supportButtonMarkup;
        }

        if (in_array($site, ['instruction-revolut', 'instruction-crypto-exchange'], true)) {
            $supportButtonFooter = $supportButtonMarkup;
        }
    }

    $staticPage = app_static_page_find_for_route($db, $site, $currentStaticPageLocale);
    $guideBodyMap = [
        'instruction-trust-wallet' => app_static_page_body_instruction_trust_wallet($t),
        'instruction-revolut' => app_static_page_body_instruction_revolut($t),
        'instruction-crypto-exchange' => app_static_page_body_instruction_crypto_exchange(),
        'instruction-smart-iptv' => app_static_page_body_instruction_smart_iptv(),
        'instruction-ott-player' => app_static_page_body_instruction_ott_player(),
        'instruction-newlook' => app_static_page_body_instruction_newlook(),
    ];
    $staticPageBody = is_array($staticPage) && !empty($staticPage['id'])
        ? (string)($staticPage['body'] ?? '')
        : (string)($guideBodyMap[$site] ?? '');

    $smarty->assign('instruction_guide_title', $guideMap[$site]);
    $smarty->assign(
        'instruction_guide_body_html',
        app_render_static_page_body(
            $staticPageBody,
            [
                'site_name' => htmlspecialchars((string)($settings['site_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'support_button_trust' => $supportButtonInline,
            ]
        )
    );
    $smarty->assign('instruction_guide_support_footer_html', $supportButtonFooter);
    $smarty->display('instruction_guide_static.tpl');
    return;
}

?>
